<?php

namespace CC_Table_Shipping\Database_Import;

use Automattic\WooCommerce\Utilities\I18nUtil;
use Automattic\WooCommerce\Internal\Utilities\FilesystemUtil;
use Automattic\WooCommerce\Internal\Utilities\URL;
use WC_Shipping_Zones;
use CCTS_Helper;


class CSV_Rows_Importer_Controller {

	protected $map_from = [
		'CEP Inicial',
		'CEP Final',
		'Peso Inicial',
		'Peso Final',
		'Custo',
		'Prazo de Entrega',
		'Nome do Método',
	];
	protected $map_to = [
		'postcode_start',
		'postcode_end',
		'weight_start',
		'weight_end',
		'cost',
		'delivery_time',
		'label',
	];


	/**
	 * The path to the current file.
	 *
	 * @var string
	 */
	protected $file = '';

	/**
	 * The instance ID of the shipping method.
	 *
	 * @var int
	 */
	protected $instance_id = 0;

	/**
	 * The current import step.
	 *
	 * @var string
	 */
	protected $step = '';

	/**
	 * Progress steps.
	 *
	 * @var array
	 */
	protected $steps = [];

	/**
	 * Errors.
	 *
	 * @var array
	 */
	protected $errors = [];

	/**
	 * The current delimiter for the file being read.
	 *
	 * @var string
	 */
	protected $delimiter = ',';

	/**
	 * Whether to use previous mapping selections.
	 *
	 * @var bool
	 */
	protected $map_preferences = false;

	/**
	 * Whether to skip existing products.
	 *
	 * @var bool
	 */
	protected $update_existing = false;

	/**
	 * The character encoding to use to interpret the input file, or empty string for autodetect.
	 *
	 * @var string
	 */
	protected $character_encoding = 'UTF-8';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$default_steps = [
			'upload'  => [
				'name'    => __( 'Confirme requisitos', 'cc-table-shipping' ),
				'view'    => [ $this, 'upload_form' ],
				'handler' => [ $this, 'requisites_check' ],
			],
			'mapping' => [
				'name'    => __( 'Mapeamento das colunas', 'cc-table-shipping' ),
				'view'    => [ $this, 'mapping_form' ],
				'handler' => [ $this, 'mapping_handler' ],
			],
			'import'  => [
				'name'    => __( 'Importar', 'cc-table-shipping' ),
				'view'    => [ $this, 'import' ],
				'handler' => '',
			],
			'done'    => [
				'name'    => __( 'Concluído!', 'cc-table-shipping' ),
				'view'    => [ $this, 'done' ],
				'handler' => '',
			],
		];

		$this->steps = $default_steps;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$this->step = isset( $_REQUEST['step'] ) ? sanitize_key( $_REQUEST['step'] ) : current( array_keys( $this->steps ) );

		$this->instance_id = isset( $_REQUEST['instance_id'] ) ? absint( $_REQUEST['instance_id'] ) : 0;

		// Get file path from instance settings instead of URL
		$this->file = $this->get_file_path_from_instance();

		// Get dynamic delimiter from instance settings
		$this->delimiter = $this->get_delimiter_from_instance();

		// Set dynamic mapping based on instance configuration
		$this->set_dynamic_mapping();

		$this->update_existing    = isset( $_REQUEST['update_existing'] ) ? (bool) $_REQUEST['update_existing'] : false;
		$this->map_preferences    = false;
		$this->character_encoding = isset( $_REQUEST['character_encoding'] ) ? wc_clean( wp_unslash( $_REQUEST['character_encoding'] ) ) : 'UTF-8';
		// phpcs:enable
	}

	/**
	 * Get file path from shipping method instance settings.
	 *
	 * @return string File path or empty string if not found.
	 */
	protected function get_file_path_from_instance() {
		if ( ! $this->instance_id ) {
			return '';
		}

		// Get the shipping method instance
		$shipping_method = WC_Shipping_Zones::get_shipping_method( $this->instance_id );

		if ( ! $shipping_method || $shipping_method->id !== 'cc_table_shipping' ) {
			return '';
		}

		// Get the file attachment ID from instance settings
		$file_id = $shipping_method->get_option( 'table_rate' );

		if ( ! $file_id ) {
			return '';
		}

		// Get the actual file path
		$file_path = get_attached_file( $file_id );

		return $file_path ? $file_path : '';
	}

	/**
	 * Get delimiter from shipping method instance settings.
	 *
	 * @return string Delimiter character.
	 */
	protected function get_delimiter_from_instance() {
		if ( ! $this->instance_id ) {
			return ',';
		}

		$shipping_method = WC_Shipping_Zones::get_shipping_method( $this->instance_id );

		if ( ! $shipping_method || $shipping_method->id !== 'cc_table_shipping' ) {
			return ',';
		}

		// Get delimiter from instance settings or default to comma
		$delimiter = $shipping_method->get_option( 'delimiter', ',' );

		return ! empty( $delimiter ) ? $delimiter : ',';
	}

	/**
	 * Set dynamic mapping based on instance configuration.
	 */
	protected function set_dynamic_mapping() {
		if ( ! $this->instance_id ) {
			return;
		}

		$shipping_method = WC_Shipping_Zones::get_shipping_method( $this->instance_id );

		if ( ! $shipping_method || $shipping_method->id !== 'cc_table_shipping' ) {
			return;
		}

		// Get mapped columns from instance settings
		$mapped_columns = $shipping_method->get_option( 'mapped_columns', [] );

		if ( ! empty( $mapped_columns ) && is_array( $mapped_columns ) ) {
			$this->map_from = array_keys( $mapped_columns );
			$this->map_to   = array_values( $mapped_columns );
		}
	}

	/**
	 * Processes AJAX requests related to a product CSV import.
	 *
	 * @since 9.3.0
	 */
	public static function dispatch_ajax() {
		global $wpdb;

		check_ajax_referer( 'ccts-table-shipping-import', 'security' );

		try {
			$instance_id = isset( $_POST['instance_id'] ) ? absint( $_POST['instance_id'] ) : 0;

			// Create a temporary instance to get the file path
			$temp_importer              = new self();
			$temp_importer->instance_id = $instance_id;
			$file                       = $temp_importer->get_file_path_from_instance();

			if ( ! $file ) {
				throw new \Exception( esc_html__( 'File not found for the provided instance.', 'cc-table-shipping' ) );
			}

			self::check_file_path( $file );

			// For resume operations, use exact position to avoid processing duplicates
			$start_pos = isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0;

			$params = [
				'delimiter'          => ! empty( $_POST['delimiter'] ) ? wc_clean( wp_unslash( $_POST['delimiter'] ) ) : ',',
				// PHPCS: input var ok.
				'start_pos'          => $start_pos,
				// PHPCS: input var ok.
				'mapping'            => isset( $_POST['mapping'] ) ? (array) wc_clean( wp_unslash( $_POST['mapping'] ) ) : [],
				// PHPCS: input var ok.
				'update_existing'    => isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false,
				// PHPCS: input var ok.
				'character_encoding' => isset( $_POST['character_encoding'] ) ? wc_clean( wp_unslash( $_POST['character_encoding'] ) ) : '',

				/**
				 * Batch size for the product import process.
				 *
				 * @param  int  $size  Batch size.
				 *
				 * @since 3.1.0
				 */
				'lines'              => apply_filters( 'woocommerce_product_import_batch_size', 30 ),
				'parse'              => true,
			];

			// Log failures - only get existing errors if we're resuming, otherwise start fresh
			if ( 0 !== $params['start_pos'] ) {
				$error_log = array_filter( (array) get_user_option( 'ccts_method_import_error_log' ) );
			} else {
				$error_log = [];
				// Clear error log when starting fresh
				delete_user_option( get_current_user_id(), 'ccts_method_import_error_log' );
			}

			$params['instance_id'] = $instance_id;
			$importer              = self::get_importer( $file, $params );
			$results               = $importer->import();
			$percent_complete      = $importer->get_percent_complete();
			$error_log             = array_merge( $error_log, $results['failed'], $results['skipped'] );

			update_user_option( get_current_user_id(), 'ccts_method_import_error_log', $error_log );

			// Save checkpoint progress
			$temp_controller              = new self();
			$temp_controller->instance_id = $instance_id;
			$current_stats                = [
				'imported' => is_countable( $results['imported'] ) ? count( $results['imported'] ) : 0,
				'failed'   => is_countable( $results['failed'] ) ? count( $results['failed'] ) : 0,
				'updated'  => is_countable( $results['updated'] ) ? count( $results['updated'] ) : 0,
				'skipped'  => is_countable( $results['skipped'] ) ? count( $results['skipped'] ) : 0,
			];

			// For checkpoint saving, use cumulative stats but for response use only current batch
			$checkpoint       = get_option( 'ccts_import_checkpoint_' . $instance_id, false );
			$cumulative_stats = $current_stats;
			if ( $checkpoint && isset( $checkpoint['stats'] ) ) {
				$cumulative_stats['imported'] += $checkpoint['stats']['imported'];
				$cumulative_stats['failed']   += $checkpoint['stats']['failed'];
				$cumulative_stats['updated']  += $checkpoint['stats']['updated'];
				$cumulative_stats['skipped']  += $checkpoint['stats']['skipped'];
			}

			if ( 100 === $percent_complete ) {
				// Clear checkpoint when import is complete
				$temp_controller->clear_import_checkpoint();

				// Remove duplicate rates for this instance
				$temp_controller->remove_duplicate_rates();

				// Send success.
				wp_send_json_success(
					[
						'position'   => 'done',
						'percentage' => 100,
						'url'        => add_query_arg( [
							'_wpnonce'    => wp_create_nonce( 'cc-table-shipping-csv-importer' ),
							'instance_id' => $instance_id,
						],
							admin_url( 'admin.php?page=cc-table-shipping-import&step=done' ) ),
						'imported'   => $cumulative_stats['imported'],
						'failed'     => $cumulative_stats['failed'],
						'updated'    => $cumulative_stats['updated'],
						'skipped'    => $cumulative_stats['skipped'],
					]
				);
			} else {
				// Save checkpoint for resuming later
				$temp_controller->save_import_checkpoint( $importer->get_file_position(), $cumulative_stats );

				wp_send_json_success(
					[
						'position'   => $importer->get_file_position(),
						'percentage' => $percent_complete,
						'imported'   => $cumulative_stats['imported'],
						'failed'     => $cumulative_stats['failed'],
						'updated'    => $cumulative_stats['updated'],
						'skipped'    => $cumulative_stats['skipped'],
					]
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * Runs before controller actions to check that the file used during the import is valid.
	 *
	 * @param  string  $path  Path to test.
	 *
	 * @throws \Exception When file validation fails.
	 * @since 9.3.0
	 *
	 */
	protected static function check_file_path( string $path ): void {
		$wp_filesystem = FilesystemUtil::get_wp_filesystem();

		// File must exist and be readable.
		$is_valid_file = $wp_filesystem->is_readable( $path );

		// Check that file is within an allowed location.
		if ( $is_valid_file ) {
			$is_valid_file = self::file_is_in_directory( $path, $wp_filesystem->abspath() );

			if ( ! $is_valid_file ) {
				$upload_dir    = wp_get_upload_dir();
				$is_valid_file = false === $upload_dir['error'] && self::file_is_in_directory( $path,
						$upload_dir['basedir'] );
			}
		}

		if ( ! $is_valid_file ) {
			throw new \Exception( esc_html__( 'File path provided for import is invalid.', 'woocommerce' ) );
		}

		if ( ! self::is_file_valid_csv( $path ) ) {
			throw new \Exception( esc_html__( 'Invalid file type. The importer supports CSV and TXT file formats.',
				'woocommerce' ) );
		}
	}

	/**
	 * Check if a given file is inside a given directory.
	 *
	 * @param  string  $file_path  The full path of the file to check.
	 * @param  string  $directory  The path of the directory to check.
	 *
	 * @return bool True if the file is inside the directory.
	 */
	private static function file_is_in_directory( string $file_path, string $directory ): bool {
		return true;

		$file_path = (string) new URL( $file_path ); // This resolves '/../' sequences.
		$file_path = preg_replace( '/^file:\\/\\//', '', $file_path );

		return 0 === stripos( wp_normalize_path( $file_path ), trailingslashit( wp_normalize_path( $directory ) ) );
	}

	/**
	 * Check whether a file is a valid CSV file.
	 *
	 * @param  string  $file  File path.
	 * @param  bool  $check_path  Whether to also check the file is located in a valid location (Default: true).
	 *
	 * @return bool
	 */
	public static function is_file_valid_csv( $file, $check_path = true ) {
		return wc_is_file_valid_csv( $file, $check_path );
	}

	/**
	 * Get importer instance.
	 *
	 * @param  string  $file  File to import.
	 * @param  array  $args  Importer arguments.
	 *
	 * @return WC_Product_CSV_Importer
	 */
	public static function get_importer( $file, $args = [] ) {
		$importer_class = CSV_Row_Importer::class;

		return new $importer_class( $file, $args );
	}

	/**
	 * Import the file if it exists and is valid.
	 */
	public function import() {
		check_admin_referer( 'cc-table-shipping-csv-importer' );

		// Validate instance and get file path
		if ( ! $this->validate_instance() ) {
			$this->add_error( __( 'Invalid shipping method instance or file not found.', 'cc-table-shipping' ) );

			return;
		}

		// Validate mapping before starting import
		if ( ! $this->validate_mapping() ) {
			$this->add_error( __( 'Mapeamento de colunas incompleto. CEP inicial e final são obrigatórios.',
				'cc-table-shipping' ) );

			return;
		}

		self::check_file_path( $this->file );

		// Check if user wants to resume or restart
		$action     = isset( $_POST['import_action'] ) ? sanitize_text_field( $_POST['import_action'] ) : 'auto';
		$checkpoint = $this->get_import_checkpoint();

		// If no explicit action and there's a pending import, default to resume
		if ( $action === 'auto' && $checkpoint ) {
			$action = 'resume';
		} elseif ( $action === 'auto' ) {
			$action = 'start';
		}

		if ( $action === 'restart' ) {
			$this->clear_import_checkpoint();
			$checkpoint = false;

			// Clear all existing rates for this instance when starting fresh
			$this->clear_existing_rates();
		}

		// Get mapping configuration (from checkpoint if resuming, otherwise current)
		if ( $checkpoint && $action === 'resume' ) {
			$mapped_columns           = $checkpoint['mapping'];
			$this->delimiter          = $checkpoint['delimiter'];
			$this->character_encoding = $checkpoint['character_encoding'];
		} else {
			$mapped_columns = $this->get_current_mapping();

			// If starting new import (not restart, not resume), clear existing rates
			if ( $action === 'start' ) {
				$this->clear_existing_rates();
				delete_user_option( get_current_user_id(), 'ccts_method_import_error_log' );
			}
		}

		$headers = $this->get_csv_headers();

		// Create mapping arrays for the importer
		$mapping_from = [];
		$mapping_to   = [];

		foreach ( $mapped_columns as $field_key => $header_index ) {
			if ( $header_index !== - 1 && isset( $headers[ $header_index ] ) ) {
				$mapping_from[] = $headers[ $header_index ];

				// Map old field names to new database field names
				$field_mapping = [
					'zipcode_start' => 'postcode_start',
					'zipcode_end'   => 'postcode_end',
					'weight_start'  => 'weight_start',
					'weight_end'    => 'weight_end',
					'cost'          => 'cost',
					'delivery_time' => 'delivery_time',
					'label'         => 'label',
				];

				$mapping_to[] = isset( $field_mapping[ $field_key ] ) ? $field_mapping[ $field_key ] : $field_key;
			}
		}

		// Check if there's a checkpoint to resume from
		$start_position = 0;
		$existing_stats = [
			'imported' => 0,
			'failed'   => 0,
			'updated'  => 0,
			'skipped'  => 0,
		];

		if ( $checkpoint && $action === 'resume' ) {
			$start_position = $checkpoint['position'];
			$existing_stats = isset( $checkpoint['stats'] ) ? $checkpoint['stats'] : $existing_stats;
		}

		wp_localize_script(
			'ccts-table-shipping-import',
			'ccts_table_shipping_import_params',
			[
				'import_nonce'       => wp_create_nonce( 'ccts-table-shipping-import' ),
				'mapping'            => [
					'from' => $mapping_from,
					'to'   => $mapping_to,
				],
				'file'               => $this->file,
				'update_existing'    => $this->update_existing,
				'delimiter'          => $this->delimiter,
				'character_encoding' => $this->character_encoding,
				'instance_id'        => $this->instance_id,
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'ajax_action'        => 'ccts_do_ajax_table_shipping_import',
				'start_position'     => $start_position,
				'existing_stats'     => $existing_stats,
				'is_resume'          => $action === 'resume',
			]
		);
		wp_enqueue_script( 'ccts-table-shipping-import' );

		include_once dirname( __FILE__ ) . '/views/html-csv-import-progress.php';
	}

	/**
	 * Validate if the instance_id is valid and corresponds to a WC Table Shipping method.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_instance() {
		if ( ! $this->instance_id ) {
			return false;
		}

		$shipping_method = WC_Shipping_Zones::get_shipping_method( $this->instance_id );

		if ( ! $shipping_method || $shipping_method->id !== 'cc_table_shipping' ) {
			return false;
		}

		// Check if the method has a file configured
		$file_id = $shipping_method->get_option( 'table_rate' );

		if ( ! $file_id ) {
			return false;
		}

		// Check if the file exists
		$file_path = get_attached_file( $file_id );

		return $file_path && file_exists( $file_path );
	}

	/**
	 * Add error message.
	 *
	 * @param  string  $message  Error message.
	 * @param  array  $actions  List of actions with 'url' and 'label'.
	 */
	protected function add_error( $message, $actions = [] ) {
		$this->errors[] = [
			'message' => $message,
			'actions' => $actions,
		];
	}

	/**
	 * Validate mapping configuration.
	 *
	 * @return bool
	 */
	protected function validate_mapping() {
		$mapped_columns = $this->get_current_mapping();
		$errors         = CCTS_Helper::required_fields_missing( $mapped_columns );

		// Only check for critical errors, not warnings
		return empty( $errors['error'] );
	}

	/**
	 * Get current mapping from shipping method instance.
	 *
	 * @return array
	 */
	protected function get_current_mapping() {
		if ( ! $this->instance_id ) {
			return [];
		}

		$shipping_method = WC_Shipping_Zones::get_shipping_method( $this->instance_id );

		if ( ! $shipping_method || $shipping_method->id !== 'cc_table_shipping' ) {
			return [];
		}

		// Get current mapping from instance settings
		$mapped_columns = $shipping_method->get_option( 'mapped_columns', [] );

		// If no mapping exists, try to auto-map using headers
		if ( empty( $mapped_columns ) ) {
			$headers        = $this->get_csv_headers();
			$mapped_columns = ccts_fix_mapped_columns( [], $headers );
		}

		return $mapped_columns;
	}

	/**
	 * Get CSV headers from the file.
	 *
	 * @return array
	 */
	protected function get_csv_headers() {
		if ( ! $this->file ) {
			return [];
		}

		return CCTS_Helper::mapping( $this->file, $this->delimiter );
	}

	/**
	 * Get saved import checkpoint.
	 *
	 * @return array|false Checkpoint data or false if none exists.
	 */
	protected function get_import_checkpoint() {
		return get_option( 'ccts_import_checkpoint_' . $this->instance_id, false );
	}

	/**
	 * Clear import checkpoint.
	 */
	protected function clear_import_checkpoint() {
		delete_option( 'ccts_import_checkpoint_' . $this->instance_id );
	}

	/**
	 * Clear all existing rates for this instance.
	 */
	protected function clear_existing_rates() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ccts_rates';
		$wpdb->delete( $table_name, [ 'instance_id' => $this->instance_id ] );
	}

	/**
	 * Remove duplicate rates for this instance.
	 * Duplicates are defined as same postcode_start, postcode_end, weight_start, weight_end, cost and label.
	 */
	protected function remove_duplicate_rates() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ccts_rates';

		// SQL to remove duplicates, keeping only the first occurrence (lowest ID)
		$sql = "DELETE t1 FROM {$table_name} t1
				INNER JOIN {$table_name} t2
				WHERE t1.id > t2.id
				AND t1.instance_id = %d
				AND t2.instance_id = %d
				AND t1.postcode_start = t2.postcode_start
				AND t1.postcode_end = t2.postcode_end
				AND t1.weight_start = t2.weight_start
				AND t1.weight_end = t2.weight_end
				AND t1.cost = t2.cost
				AND COALESCE(t1.label, '') = COALESCE(t2.label, '')";

		$wpdb->query( $wpdb->prepare( $sql, $this->instance_id, $this->instance_id ) );
	}

	/**
	 * Save import progress checkpoint.
	 *
	 * @param  int  $position  Current file position.
	 * @param  array  $stats  Import statistics.
	 */
	protected function save_import_checkpoint( $position, $stats = [] ) {
		$checkpoint_data = [
			'instance_id'        => $this->instance_id,
			'file_path'          => $this->file,
			'position'           => $position,
			'stats'              => $stats,
			'timestamp'          => time(),
			'mapping'            => $this->get_current_mapping(),
			'delimiter'          => $this->delimiter,
			'character_encoding' => $this->character_encoding,
		];

		update_option( 'ccts_import_checkpoint_' . $this->instance_id, $checkpoint_data );
	}

	/**
	 * Get all the valid filetypes for a CSV file.
	 *
	 * @return array
	 */
	protected static function get_valid_csv_filetypes() {
		return apply_filters(
			'woocommerce_csv_product_import_valid_filetypes',
			[
				'csv' => 'text/csv',
				'txt' => 'text/plain',
			]
		);
	}

	/**
	 * Dispatch current step and show correct view.
	 */
	public function dispatch() {
		$output = '';

		try {

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! empty( $_POST['save_step'] ) && ! empty( $this->steps[ $this->step ]['handler'] ) ) {
				if ( is_callable( $this->steps[ $this->step ]['handler'] ) ) {
					call_user_func( $this->steps[ $this->step ]['handler'], $this );
				}
			}

			ob_start();

			if ( is_callable( $this->steps[ $this->step ]['view'] ) ) {
				call_user_func( $this->steps[ $this->step ]['view'], $this );
			}

			$output = ob_get_clean();
		} catch ( \Exception $e ) {
			$this->add_error( $e->getMessage() );
		}

		$this->output_header();
		$this->output_steps();
		$this->output_errors();
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output is HTML we've generated ourselves.
		$this->output_footer();
	}

	/**
	 * Output header view.
	 */
	protected function output_header() {
		include dirname( __FILE__ ) . '/views/html-csv-import-header.php';
	}

	/**
	 * Output steps view.
	 */
	protected function output_steps() {
		include dirname( __FILE__ ) . '/views/html-csv-import-steps.php';
	}

	/**
	 * Add error message.
	 */
	protected function output_errors() {
		if ( ! $this->errors ) {
			return;
		}

		foreach ( $this->errors as $error ) {
			echo '<div class="error inline">';
			echo '<p>' . esc_html( $error['message'] ) . '</p>';

			if ( ! empty( $error['actions'] ) ) {
				echo '<p>';
				foreach ( $error['actions'] as $action ) {
					echo '<a class="button button-primary" href="' . esc_url( $action['url'] ) . '">' . esc_html( $action['label'] ) . '</a> ';
				}
				echo '</p>';
			}
			echo '</div>';
		}
	}

	/**
	 * Output footer view.
	 */
	protected function output_footer() {
		include dirname( __FILE__ ) . '/views/html-csv-import-footer.php';
	}

	/**
	 * Handle the upload form and store options.
	 */
	public function requisites_check() {
		check_admin_referer( 'cc-table-shipping-csv-importer' );

		// Validate instance before proceeding
		if ( ! $this->validate_instance() ) {
			// Redirect to shipping zones if instance is invalid
			wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) );
			exit;
		}

		// Check if there's a pending import and if the user wants to restart
		$action = isset( $_POST['import_action'] ) ? sanitize_text_field( $_POST['import_action'] ) : 'auto';

		// If no explicit action and there's a pending import, default to resume
		if ( $action === 'auto' && $this->has_pending_import() ) {
			$action = 'resume';
		} elseif ( $action === 'auto' ) {
			$action = 'start';
		}

		if ( $action === 'restart' ) {
			$this->clear_import_checkpoint();
		}

		// Always go to mapping step - let the mapping step decide if it should proceed
		wp_redirect( esc_url_raw( $this->get_next_step_link() ) );
		exit;
	}

	/**
	 * Check if there's a pending import for this instance.
	 *
	 * @return bool
	 */
	public function has_pending_import() {
		$checkpoint = $this->get_import_checkpoint();
		if ( ! $checkpoint ) {
			return false;
		}

		// Check if file still exists and matches
		if ( $checkpoint['file_path'] !== $this->file || ! file_exists( $this->file ) ) {
			$this->clear_import_checkpoint();

			return false;
		}

		return true;
	}

	/**
	 * Get the URL for the next step's screen.
	 *
	 * @param  string  $step  slug (default: current step).
	 *
	 * @return string       URL for next step if a next step exists.
	 *                      Admin URL if it's the last step.
	 *                      Empty string on failure.
	 */
	public function get_next_step_link( $step = '' ) {
		if ( ! $step ) {
			$step = $this->step;
		}

		$keys = array_keys( $this->steps );

		if ( end( $keys ) === $step ) {
			return admin_url();
		}

		$step_index = array_search( $step, $keys, true );

		if ( false === $step_index ) {
			return '';
		}

		$params = [
			'page'               => 'cc-table-shipping-import',
			'step'               => $keys[ $step_index + 1 ],
			'instance_id'        => $this->instance_id,
			'delimiter'          => $this->delimiter,
			'update_existing'    => $this->update_existing,
			'map_preferences'    => $this->map_preferences,
			'character_encoding' => $this->character_encoding,
			'_wpnonce'           => wp_create_nonce( 'cc-table-shipping-csv-importer' ),
			// wp_nonce_url() escapes & to &amp; breaking redirects.
		];

		return add_query_arg( $params, admin_url( 'admin.php' ) );
	}

	/**
	 * Get import progress percentage from checkpoint.
	 *
	 * @return int Progress percentage (0-100).
	 */
	public function get_import_progress() {
		$checkpoint = $this->get_import_checkpoint();
		if ( ! $checkpoint || ! file_exists( $this->file ) ) {
			return 0;
		}

		$file_size = filesize( $this->file );
		if ( $file_size <= 0 ) {
			return 0;
		}

		return min( 100, round( ( $checkpoint['position'] / $file_size ) * 100 ) );
	}

	/**
	 * Map columns using the user's latest import mappings.
	 *
	 * @param  array  $headers  Header columns.
	 *
	 * @return array
	 */
	public function auto_map_user_preferences( $headers ) {
		$mapping_preferences = get_user_option( '' );

		if ( ! empty( $mapping_preferences ) && is_array( $mapping_preferences ) ) {
			return $mapping_preferences;
		}

		return $headers;
	}

	/**
	 * Handle mapping form submission.
	 */
	public function mapping_handler() {
		check_admin_referer( 'cc-table-shipping-csv-importer' );

		// Get submitted mapping data
		$mapped_columns = isset( $_POST['mapped_columns'] ) ? (array) wc_clean( wp_unslash( $_POST['mapped_columns'] ) ) : [];

		// Save mapping to shipping method instance
		if ( $this->instance_id && ! empty( $mapped_columns ) ) {
			$shipping_method = WC_Shipping_Zones::get_shipping_method( $this->instance_id );
			if ( $shipping_method && $shipping_method->id === 'cc_table_shipping' ) {
				// Update the mapped_columns option
				$shipping_method->update_option( 'mapped_columns', $mapped_columns );
			}
		}

		// Validate the updated mapping
		$errors = CCTS_Helper::required_fields_missing( $mapped_columns );
		if ( ! empty( $errors['error'] ) ) {
			// Redirect back to mapping with error
			wp_redirect( esc_url_raw( $this->get_next_step_link( 'mapping' ) ) );
			exit;
		}

		// Proceed to import step
		wp_redirect( esc_url_raw( $this->get_next_step_link() ) );
		exit;
	}

	/**
	 * Output information about the uploading process.
	 */
	protected function upload_form() {
		$bytes      = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size       = size_format( $bytes );
		$upload_dir = wp_upload_dir();

		// Define variables for the view
		$is_valid_instance = $this->validate_instance();
		$file_path         = $this->file;

		include dirname( __FILE__ ) . '/views/html-product-csv-import-form.php';
	}

	/**
	 * Mapping step.
	 */
	protected function mapping_form() {
		check_admin_referer( 'cc-table-shipping-csv-importer' );

		if ( ! $this->validate_instance() ) {
			$this->add_error( __( 'Invalid shipping method instance or file not found.', 'cc-table-shipping' ) );

			return;
		}

		self::check_file_path( $this->file );

		$headers = $this->get_csv_headers();

		if ( empty( $headers ) ) {
			$this->add_error(
				__( 'O arquivo está vazio ou usa uma codificação diferente de UTF-8. Tente novamente com um novo arquivo.',
					'cc-table-shipping' ),
				[
					[
						'url'   => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
						'label' => __( 'Fazer upload de um novo arquivo', 'cc-table-shipping' ),
					],
				]
			);

			// Force output the errors in the same page.
			$this->output_errors();

			return;
		}

		include_once dirname( __FILE__ ) . '/views/html-csv-import-mapping.php';
	}

	/**
	 * Done step.
	 */
	protected function done() {
		check_admin_referer( 'cc-table-shipping-csv-importer' );
		$imported  = isset( $_GET['rates-imported'] ) ? absint( $_GET['rates-imported'] ) : 0;
		$updated   = isset( $_GET['rates-updated'] ) ? absint( $_GET['rates-updated'] ) : 0;
		$failed    = isset( $_GET['rates-failed'] ) ? absint( $_GET['rates-failed'] ) : 0;
		$skipped   = isset( $_GET['rates-skipped'] ) ? absint( $_GET['rates-skipped'] ) : 0;
		$file_name = isset( $_GET['file-name'] ) ? sanitize_text_field( wp_unslash( $_GET['file-name'] ) ) : '';
		$errors    = array_filter( (array) get_user_option( 'ccts_method_import_error_log' ) );

		include_once dirname( __FILE__ ) . '/views/html-csv-import-done.php';
	}
}
