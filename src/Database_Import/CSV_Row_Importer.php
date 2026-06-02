<?php

namespace CC_Table_Shipping\Database_Import;

use CC_Table_Shipping\Traits\Database_Helper;
use Automattic\WooCommerce\Utilities\ArrayUtil;
use WP_Error;
use CCTS_Helper;

class CSV_Row_Importer extends Abstract_CSV_Row_Importer {
	use Database_Helper;

	public $instance_id = 0;

	/**
	 * Tracks current row being parsed.
	 *
	 * @var integer
	 */
	protected $parsing_raw_data_index = 0;

	/**
	 * Initialize importer.
	 *
	 * @param  string  $file  File to read.
	 * @param  array  $params  Arguments for the parser.
	 */
	public function __construct( $file, $params = [] ) {
		// Get instance_id from params
		if ( isset( $params['instance_id'] ) ) {
			$this->instance_id = absint( $params['instance_id'] );
		}
		$default_args = [
			'start_pos'        => 0,
			// File pointer start.
			'end_pos'          => - 1,
			// File pointer end.
			'lines'            => - 1,
			// Max lines to read.
			'mapping'          => [],
			// Column mapping. csv_heading => schema_heading.
			'parse'            => false,
			// Whether to sanitize and format data.
			'update_existing'  => false,
			// Whether to update existing items.
			'delimiter'        => ',',
			// CSV delimiter.
			'prevent_timeouts' => true,
			// Check memory and time usage and abort if reaching limit.
			'enclosure'        => '"',
			// The character used to wrap text in the CSV.
			'escape'           => "\0",
			// PHP uses '\' as the default escape character. This is not RFC-4180 compliant. This disables the escape character.
		];

		$this->params = wp_parse_args( $params, $default_args );
		$this->file   = $file;

		if ( isset( $this->params['mapping']['from'], $this->params['mapping']['to'] ) ) {
			$this->params['mapping'] = array_combine( $this->params['mapping']['from'],
				$this->params['mapping']['to'] );
		}

		$this->read_file();
	}

	/**
	 * Read file.
	 */
	protected function read_file() {
		if ( ! CSV_Rows_Importer_Controller::is_file_valid_csv( $this->file ) ) {
			wp_die( esc_html__( 'Invalid file type. The importer supports CSV and TXT file formats.', 'woocommerce' ) );
		}

		$handle = fopen( $this->file, 'r' ); // @codingStandardsIgnoreLine.

		if ( false !== $handle ) {
			$csv = fgetcsv( $handle, 0, $this->params['delimiter'], $this->params['enclosure'],
				$this->params['escape'] );

			if ( ! $csv ) {
				wp_die( 'O arquivo é inválido: ' . $this->file );
			}

			$this->raw_keys = array_map( 'trim', $csv ); // @codingStandardsIgnoreLine

			if ( ArrayUtil::is_truthy( $this->params, 'character_encoding' ) ) {
				$this->raw_keys = array_map( [ $this, 'adjust_character_encoding' ], $this->raw_keys );
			}

			// Remove line breaks in keys, to avoid mismatch mapping of keys.
			$this->raw_keys = wc_clean( wp_unslash( $this->raw_keys ) );

			// Remove BOM signature from the first item.
			if ( isset( $this->raw_keys[0] ) ) {
				$this->raw_keys[0] = $this->remove_utf8_bom( $this->raw_keys[0] );
			}

			if ( 0 !== $this->params['start_pos'] ) {
				fseek( $handle, (int) $this->params['start_pos'] );
			}

			while ( 1 ) {
				$row = fgetcsv( $handle, 0, $this->params['delimiter'], $this->params['enclosure'],
					$this->params['escape'] ); // @codingStandardsIgnoreLine

				if ( false !== $row ) {
					if ( ArrayUtil::is_truthy( $this->params, 'character_encoding' ) ) {
						$row = array_map( [ $this, 'adjust_character_encoding' ], $row );
					}

					$this->raw_data[]                                 = $row;
					$this->file_positions[ count( $this->raw_data ) ] = ftell( $handle );

					if ( ( $this->params['end_pos'] > 0 && ftell( $handle ) >= $this->params['end_pos'] ) || 0 === -- $this->params['lines'] ) {
						break;
					}
				} else {
					break;
				}
			}

			$this->file_position = ftell( $handle );
		}

		if ( ! empty( $this->params['mapping'] ) ) {
			$this->set_mapped_keys();
		}

		if ( $this->params['parse'] ) {
			$this->set_parsed_data();
		}
	}

	/**
	 * Remove UTF-8 BOM signature.
	 *
	 * @param  string  $string  String to handle.
	 *
	 * @return string
	 */
	protected function remove_utf8_bom( $string ) {
		if ( 'efbbbf' === substr( bin2hex( $string ), 0, 6 ) ) {
			$string = substr( $string, 3 );
		}

		return $string;
	}

	/**
	 * Set file mapped keys.
	 */
	protected function set_mapped_keys() {
		$mapping = $this->params['mapping'];

		foreach ( $this->raw_keys as $key ) {
			$this->mapped_keys[] = isset( $mapping[ $key ] ) ? $mapping[ $key ] : $key;
		}
	}

	/**
	 * Map and format raw data to known fields.
	 */
	protected function set_parsed_data() {
		$parse_functions = $this->get_formatting_callback();
		$mapped_keys     = $this->get_mapped_keys();
		$use_mb          = function_exists( 'mb_convert_encoding' );

		// Parse the data.
		foreach ( $this->raw_data as $row_index => $row ) {
			// Skip empty rows.
			if ( ! count( array_filter( $row ) ) ) {
				continue;
			}

			$this->parsing_raw_data_index = $row_index;

			$data = [];

			foreach ( $row as $id => $value ) {
				// Skip ignored columns.
				if ( empty( $mapped_keys[ $id ] ) ) {
					continue;
				}

				// Convert UTF8.
				if ( $use_mb ) {
					$encoding = mb_detect_encoding( $value, mb_detect_order(), true );
					if ( $encoding ) {
						$value = mb_convert_encoding( $value, 'UTF-8', $encoding );
					} else {
						$value = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
					}
				} else {
					$value = wp_check_invalid_utf8( $value, true );
				}

				$data[ $mapped_keys[ $id ] ] = call_user_func( $parse_functions[ $id ], $value );
			}

			/**
			 * Filter product importer parsed data.
			 *
			 * @param  array  $parsed_data  Parsed data.
			 * @param  CSV_Row_Importer  $importer  Importer instance.
			 *
			 * @since
			 */
			$this->parsed_data[] = apply_filters( 'ccts_row_importer_parsed_data',
				$this->expand_data( $data ), $this );
		}
	}

	/**
	 * Get formatting callback.
	 *
	 * @return array
	 * @since 4.3.0
	 */
	protected function get_formatting_callback() {

		/**
		 * Columns not mentioned here will get parsed with 'wc_clean'.
		 * column_name => callback.
		 */
		$data_formatting = [
			'postcode_start' => [ $this, 'parse_postcode_field' ],
			'postcode_end'   => [ $this, 'parse_postcode_field' ],
			'weight_start'   => [ $this, 'parse_float_field' ],
			'weight_end'     => [ $this, 'parse_float_field' ],
			'cost'           => [ $this, 'parse_cost_field' ],
			'delivery_time'  => [ $this, 'parse_int_field' ],
			'label'          => [ $this, 'parse_nullable_field' ],
		];

		$callbacks = [];

		// Figure out the parse function for each column.
		foreach ( $this->get_mapped_keys() as $index => $heading ) {
			$callback = 'wc_clean';

			if ( isset( $data_formatting[ $heading ] ) ) {
				$callback = $data_formatting[ $heading ];
			}

			$callbacks[] = $callback;
		}

		return $callbacks;
	}

	/**
	 * Expand special and internal data into the correct formats for the product CRUD.
	 *
	 * @param  array  $data  Data to import.
	 *
	 * @return array
	 */
	protected function expand_data( $data ) {
		// modify the data, if needed
		return $data;
	}

	/**
	 * Parse the postcode field
	 *
	 * @param  string  $value  Field value.
	 *
	 * @return int|string
	 */
	public function parse_postcode_field( $value ) {
		return ccts_sanitize_postcode( $value );
	}


	/**
	 * Parse a float value field.
	 *
	 * @param  string  $value  Field value.
	 *
	 * @return float|string
	 */
	public function parse_float_field( $value ) {
		if ( '' === $value ) {
			return $value;
		}

		// Remove the ' prepended to fields that start with - if needed.
		$value = $this->unescape_data( $value );

		$value = str_replace( ',', '.', $value );

		return floatval( $value );
	}


	/**
	 * Parse the cost value as float.
	 *
	 * @param  string  $value  Field value.
	 *
	 * @return float|string
	 */
	public function parse_cost_field( $value ) {
		if ( '' === $value ) {
			return $value;
		}

		// Handle -1 value which means free shipping or no cost
		if ( '-1' === trim( $value ) ) {
			return 0;
		}

		// Remove the ' prepended to fields that start with - if needed.
		$value = $this->unescape_data( $value );

		$cost = str_replace( ',', '.', $value );

		return floatval( $cost );
	}


	/**
	 * Just skip current field.
	 *
	 * By default is applied wc_clean() to all not listed fields
	 * in self::get_formatting_callback(), use this method to skip any formatting.
	 *
	 * @param  string  $value  Field value.
	 *
	 * @return string
	 */
	public function parse_skip_field( $value ) {
		return $value;
	}


	/**
	 * Convert empty strings to null.
	 *
	 *
	 * @param  string  $value  Field value.
	 *
	 * @return string
	 */
	public function parse_nullable_field( $value ) {
		return empty( $value ) ? null : $value;
	}


	/**
	 * Parse an int value field
	 *
	 * @param  int  $value  field value.
	 *
	 * @return int
	 */
	public function parse_int_field( $value ) {
		// Remove the ' prepended to fields that start with - if needed.
		$value = $this->unescape_data( $value );

		return intval( $value );
	}


	/**
	 * Process importer.
	 *
	 * Do not import products with IDs or SKUs that already exist if option
	 * update existing is false, and likewise, if updating products, do not
	 * process rows which do not exist if an ID/SKU is provided.
	 *
	 * @return array
	 */
	public function import() {
		$this->start_time = time();
		$index            = 0;
		$data             = [
			'imported' => [],
			'failed'   => [],
			'updated'  => [],
			'skipped'  => [],
		];

		$to_insert = [];

		$table_name = $this->get_table_name();

		foreach ( $this->parsed_data as $parsed_data_key => $parsed_data ) {
			$row_error = $this->validate_row( $parsed_data );

			if ( $row_error ) {
				error_log( print_r( $row_error, true ) );
			}

			// Avalia se os dados tem um formato válido e então processa.
			if ( $row_error ) {
				$data['skipped'][] = new WP_Error(
					'ccts_error_db_importing',
					$row_error,
					[
						'row' => $this->get_row_id( $parsed_data ),
					]
				);
				continue;
			}

			// Prepare data according to migration table structure
			$row_data = $this->prepare_row_for_database( $parsed_data );

			$to_insert[] = $row_data;

			// woocommerce original format item by item
//			$result = $this->process_item( $parsed_data );

//			if ( is_wp_error( $result ) ) {
//				$result->add_data( [ 'row' => $this->get_row_id( $parsed_data ) ] );
//				$data['failed'][] = $result;
//			} else {
//				$data['imported'][] = $result['id'];
//			}
//
			$index ++;
//
//			if ( $this->params['prevent_timeouts'] && ( $this->time_exceeded() || $this->memory_exceeded() ) ) {
//				$this->file_position = $this->file_positions[ $index ];
//				break;
//			}
		}

		if ( ! empty( $to_insert ) ) {
			$result = $this->bulk_insert( $table_name, $to_insert );

			if ( false === $result ) {
				global $wpdb;
				$data['failed'][] = new WP_Error(
					'ccts_error_db_bulk_insert',
					'Erro ao inserir dados no banco de dados: ' . $wpdb->last_error,
					[ 'rows' => count( $to_insert ) ]
				);
			} else {
				// para exibir corretamente o número de linhas importadas
				foreach ( $to_insert as $inserted_row ) {
					$data['imported'][] = $inserted_row;
				}
			}
		}

		return $data;
	}

	/**
	 * Validate row data before insertion.
	 *
	 * @param  array  $parsed_data  Parsed CSV row data.
	 *
	 * @return string|null Error message or null if valid.
	 */
	private function validate_row( $parsed_data ) {
		// Check required fields
		if ( ! isset( $parsed_data['postcode_start'] ) || ! isset( $parsed_data['postcode_end'] ) ) {
			return 'CEP inicial e final são obrigatórios';
		}

		if ( ! isset( $parsed_data['weight_start'] ) || ! isset( $parsed_data['weight_end'] ) ) {
			return 'Peso inicial e final são obrigatórios';
		}

		if ( ! isset( $parsed_data['cost'] ) ) {
			return 'Custo é obrigatório';
		}

		// Validate numeric values
		if ( ! is_numeric( $parsed_data['postcode_start'] ) || ! is_numeric( $parsed_data['postcode_end'] ) ) {
			return 'CEPs devem ser numéricos';
		}

		if ( ! is_numeric( $parsed_data['weight_start'] ) || ! is_numeric( $parsed_data['weight_end'] ) ) {
			return 'Pesos devem ser numéricos';
		}

		if ( ! is_numeric( $parsed_data['cost'] ) ) {
			return 'Custo deve ser numérico';
		}

		// Validate ranges
		if ( $parsed_data['postcode_end'] < $parsed_data['postcode_start'] ) {
			return 'O CEP final deve ser maior que o CEP inicial';
		}

		if ( $parsed_data['weight_end'] < $parsed_data['weight_start'] ) {
			return 'O peso final deve ser maior que o peso inicial';
		}

		return null;
	}

	/**
	 * Get a string to identify the row from parsed data.
	 *
	 * @param  array  $parsed_data  Parsed data.
	 *
	 * @return string
	 */
	protected function get_row_id( $parsed_data ) {
		return implode( '|', $parsed_data );

		$id       = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
		$sku      = isset( $parsed_data['sku'] ) ? esc_attr( $parsed_data['sku'] ) : '';
		$name     = isset( $parsed_data['name'] ) ? esc_attr( $parsed_data['name'] ) : '';
		$row_data = [];

		if ( $name ) {
			$row_data[] = $name;
		}
		if ( $id ) {
			/* translators: %d: product ID */
			$row_data[] = sprintf( __( 'ID %d', 'woocommerce' ), $id );
		}
		if ( $sku ) {
			/* translators: %s: product SKU */
			$row_data[] = sprintf( __( 'SKU %s', 'woocommerce' ), $sku );
		}

		return implode( ', ', $row_data );
	}

	/**
	 * Prepare row data according to the database migration structure.
	 *
	 * @param  array  $parsed_data  Parsed CSV row data.
	 *
	 * @return array Formatted data for database insertion.
	 */
	private function prepare_row_for_database( $parsed_data ) {
		// Map to database table structure from migration:
		// instance_id, postcode_start, postcode_end, weight_start, weight_end, cost, delivery_time, label
		return [
			'instance_id'    => $this->instance_id,
			'postcode_start' => isset( $parsed_data['postcode_start'] ) ? intval( $parsed_data['postcode_start'] ) : 0,
			'postcode_end'   => isset( $parsed_data['postcode_end'] ) ? intval( $parsed_data['postcode_end'] ) : 0,
			'weight_start'   => isset( $parsed_data['weight_start'] ) ? floatval( $parsed_data['weight_start'] ) : 0.00,
			'weight_end'     => isset( $parsed_data['weight_end'] ) ? floatval( $parsed_data['weight_end'] ) : 0.00,
			'cost'           => isset( $parsed_data['cost'] ) ? floatval( $parsed_data['cost'] ) : 0.00,
			'delivery_time'  => isset( $parsed_data['delivery_time'] ) ? intval( $parsed_data['delivery_time'] ) : 0,
			'label'          => isset( $parsed_data['label'] ) ? sanitize_text_field( $parsed_data['label'] ) : null,
		];
	}

	/**
	 * Convert a string from the input encoding to UTF-8.
	 *
	 * @param  string  $value  The string to convert.
	 *
	 * @return string The converted string.
	 */
	private function adjust_character_encoding( $value ) {
		$encoding = $this->params['character_encoding'];

		return 'UTF-8' === $encoding ? $value : mb_convert_encoding( $value, 'UTF-8', $encoding );
	}
}
