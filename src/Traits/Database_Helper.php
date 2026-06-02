<?php

namespace CC_Table_Shipping\Traits;

trait Database_Helper {
	public function bulk_insert( $table_name, $data_chunk ) {
		if ( ! $data_chunk ) {
			return 0;
		}

		global $wpdb;

		$columns      = array_keys( $data_chunk[0] );
		$placeholders = array_fill( 0, count( $columns ), '%s' );
		$placeholders = '(' . implode( ',', $placeholders ) . ')';
		$values       = [];
		$query        = "INSERT INTO $table_name (" . implode( ',', $columns ) . ") VALUES ";

		foreach ( $data_chunk as $data ) {
			$values = array_merge( $values, array_values( $data ) );
			$query  .= $placeholders . ',';
		}

		$query = rtrim( $query, ',' );

		return $wpdb->query( $wpdb->prepare( $query, ...$values ) );
	}

	public function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'ccts_rates';
	}
}
