<?php

namespace CC_Table_Shipping\Admin;

class Migrations {
	/**
	 * DB updates and callbacks that need to be run per version.
	 *
	 * @var array
	 */
	private static $db_updates = [
		'1.0.0' => [
			'create_database_tables',
		],
	];

	function __construct() {
		add_action( 'admin_head', [ $this, 'run' ] );
	}

	public function run() {
		$current_db_version = get_option( 'cc_table_shipping_db_version', 0 );
		$loop               = 0;

		if ( isset( $_GET['show_details'] ) ) {
			echo 'Current version: ' . $current_db_version;
		}

		foreach ( self::$db_updates as $version => $update_callbacks ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				foreach ( $update_callbacks as $update_callback ) {
					$this->{$update_callback}();
					++ $loop;
				}

				update_option( 'cc_table_shipping_db_version', $version );
			}
		}
	}


	private function create_database_tables() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ccts_rates';

		$sql = "CREATE TABLE $table_name (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    instance_id INT(10) UNSIGNED NOT NULL,
    postcode_start INT NOT NULL,
    postcode_end INT NOT NULL,
    weight_start DECIMAL(10, 2) NOT NULL,
    weight_end DECIMAL(10, 2) NOT NULL,
    cost DECIMAL(10, 2) NOT NULL,
    delivery_time TINYINT(3) UNSIGNED NOT NULL,
    label VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_instance_postcode (instance_id, postcode_start, postcode_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
}


new Migrations();
