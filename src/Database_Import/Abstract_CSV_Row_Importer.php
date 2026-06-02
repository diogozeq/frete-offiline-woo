<?php

namespace CC_Table_Shipping\Database_Import;

use Automattic\WooCommerce\Utilities\NumberUtil;
use WP_Error;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Include dependencies.
 */
if ( ! class_exists( 'WC_Importer_Interface', false ) ) {
	include_once WC_ABSPATH . 'includes/interfaces/class-wc-importer-interface.php';
}

// arquivo baseado na classe WC_Product_Importer

/**
 * WC_Product_Importer Class.
 */
abstract class Abstract_CSV_Row_Importer {
	/**
	 * CSV file.
	 *
	 * @var string
	 */
	protected $file = '';

	/**
	 * The file position after the last read.
	 *
	 * @var int
	 */
	protected $file_position = 0;

	/**
	 * Importer parameters.
	 *
	 * @var array
	 */
	protected $params = [];

	/**
	 * Raw keys - CSV raw headers.
	 *
	 * @var array
	 */
	protected $raw_keys = [];

	/**
	 * Mapped keys - CSV headers.
	 *
	 * @var array
	 */
	protected $mapped_keys = [];

	/**
	 * Raw data.
	 *
	 * @var array
	 */
	protected $raw_data = [];

	/**
	 * Raw data.
	 *
	 * @var array
	 */
	protected $file_positions = [];

	/**
	 * Parsed data.
	 *
	 * @var array
	 */
	protected $parsed_data = [];

	/**
	 * Start time of current import.
	 *
	 * (default value: 0)
	 *
	 * @var int
	 */
	protected $start_time = 0;

	/**
	 * Get file raw headers.
	 *
	 * @return array
	 */
	public function get_raw_keys() {
		return $this->raw_keys;
	}

	/**
	 * Get file mapped headers.
	 *
	 * @return array
	 */
	public function get_mapped_keys() {
		return ! empty( $this->mapped_keys ) ? $this->mapped_keys : $this->raw_keys;
	}

	/**
	 * Get raw data.
	 *
	 * @return array
	 */
	public function get_raw_data() {
		return $this->raw_data;
	}

	/**
	 * Get parsed data.
	 *
	 * @return array
	 */
	public function get_parsed_data() {
		return $this->parsed_data;
	}

	/**
	 * Get importer parameters.
	 *
	 * @return array
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Get file pointer position from the last read.
	 *
	 * @return int
	 */
	public function get_file_position() {
		return $this->file_position;
	}

	/**
	 * Get file pointer position as a percentage of file size.
	 *
	 * @return int
	 */
	public function get_percent_complete() {
		$size = filesize( $this->file );
		if ( ! $size ) {
			return 0;
		}

		return absint( min( floor( ( $this->file_position / $size ) * 100 ), 100 ) );
	}

	/**
	 * Process a single item and save.
	 *
	 * @param  array  $data  Raw CSV data.
	 *
	 * @return array|WP_Error
	 * @throws Exception If item cannot be processed.
	 */
	protected function process_item( $data ) {
		try {
			error_log( print_r( $data, true ) );

			return [
				'id'           => random_int( 10000000, 99999999 ),
				'updated'      => false,
				'is_variation' => false,
			];
		} catch ( Exception $e ) {
			return new WP_Error( 'ccts_row_importer_error', $e->getMessage(),
				[ 'status' => $e->getCode() ] );
		}
	}


	/**
	 * Memory exceeded
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @return bool
	 */
	protected function memory_exceeded() {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;
		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}

		return $return;
	}

	/**
	 * Get memory limit
	 *
	 * @return int
	 */
	protected function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || - 1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}

		return wp_convert_hr_to_bytes( $memory_limit );
	}

	/**
	 * Time exceeded.
	 *
	 * Ensures the batch never exceeds a sensible time limit.
	 * A timeout limit of 30s is common on shared hosting.
	 *
	 * @return bool
	 */
	protected function time_exceeded() {
		$finish = $this->start_time + apply_filters( 'woocommerce_product_importer_default_time_limit',
				20 ); // 20 seconds
		$return = false;
		if ( time() >= $finish ) {
			$return = true;
		}

		return apply_filters( 'woocommerce_product_importer_time_exceeded', $return );
	}

	/**
	 * Explode CSV cell values using commas by default, and handling escaped
	 * separators.
	 *
	 * @param  string  $value  Value to explode.
	 * @param  string  $separator  Separator separating each value. Defaults to comma.
	 *
	 * @return array
	 * @since  3.2.0
	 */
	protected function explode_values( $value, $separator = ',' ) {
		$value  = str_replace( '\\,', '::separator::', $value );
		$values = explode( $separator, $value );
		$values = array_map( [ $this, 'explode_values_formatter' ], $values );

		return $values;
	}

	/**
	 * Remove formatting and trim each value.
	 *
	 * @param  string  $value  Value to format.
	 *
	 * @return string
	 * @since  3.2.0
	 */
	protected function explode_values_formatter( $value ) {
		return trim( str_replace( '::separator::', ',', $value ) );
	}

	/**
	 * The exporter prepends a ' to escape fields that start with =, +, - or @.
	 * Remove the prepended ' character preceding those characters.
	 *
	 * @param  string  $value  A string that may or may not have been escaped with '.
	 *
	 * @return string
	 * @since 3.5.2
	 */
	protected function unescape_data( $value ) {
		$active_content_triggers = [ "'=", "'+", "'-", "'@" ];

		if ( in_array( mb_substr( $value, 0, 2 ), $active_content_triggers, true ) ) {
			$value = mb_substr( $value, 1 );
		}

		return $value;
	}
}
