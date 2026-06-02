<?php

namespace CC_Table_Shipping\Database_Import;

use Automattic\Jetpack\Constants;

class Init {
	function __construct() {
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ], 50000 );

		add_action( 'wp_ajax_ccts_do_ajax_table_shipping_import', [ $this, 'do_ajax_table_shipping_import' ], 1 );
	}

	public function admin_menu() {
		add_submenu_page(
			null,
			__( 'Importar Frete Offline', 'cc-table-shipping' ),
			__( 'Importar Frete Offline', 'cc-table-shipping' ),
			'manage_woocommerce',
			'cc-table-shipping-import',
			[ $this, 'output' ],
		);
	}


	public function output() {
		$this->product_importer();
	}


	public function product_importer() {
		// phpcs:ignore
		if ( isset( $_GET['source'] ) && 'wordpress-importer' === sanitize_text_field( wp_unslash( $_GET['source'] ) ) ) {
			wc_admin_record_tracks_event( 'product_importer_view_from_wp_importer' );
		}

		include_once WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';
		include_once WC_ABSPATH . 'includes/admin/importers/class-wc-product-csv-importer-controller.php';

		$importer = new CSV_Rows_Importer_Controller();
		$importer->dispatch();
	}

	/**
	 * Register importer scripts.
	 */
	public function admin_scripts() {
		$suffix  = Constants::is_true( 'SCRIPT_DEBUG' ) ? '' : '.min';
		$version = Constants::get_constant( 'WC_VERSION' );
		// Register our custom table shipping import script  
		$plugin_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) ); // Go up 2 levels to plugin root
		wp_register_script( 'ccts-table-shipping-import',
			$plugin_url . 'assets/js/ccts-table-shipping-import.js', [ 'jquery' ], '1.0.0', true );

		$locale        = localeconv();
		$decimal_point = isset( $locale['decimal_point'] ) ? $locale['decimal_point'] : '.';
		$decimal       = ( ! empty( wc_get_price_decimal_separator() ) ) ? wc_get_price_decimal_separator() : $decimal_point;

		$params = [
			/* translators: %s: decimal */
			'i18n_decimal_error'                => sprintf( __( 'Please enter a value with one decimal point (%s) without thousand separators.',
				'woocommerce' ), $decimal ),
			/* translators: %s: price decimal separator */
			'i18n_mon_decimal_error'            => sprintf( __( 'Please enter a value with one monetary decimal point (%s) without thousand separators and currency symbols.',
				'woocommerce' ), wc_get_price_decimal_separator() ),
			'i18n_country_iso_error'            => __( 'Please enter in country code with two capital letters.',
				'woocommerce' ),
			'i18n_sale_less_than_regular_error' => __( 'Please enter in a value less than the regular price.',
				'woocommerce' ),
			'i18n_delete_product_notice'        => __( 'This product has produced sales and may be linked to existing orders. Are you sure you want to delete it?',
				'woocommerce' ),
			'i18n_remove_personal_data_notice'  => __( 'This action cannot be reversed. Are you sure you wish to erase personal data from the selected orders?',
				'woocommerce' ),
			'i18n_confirm_delete'               => __( 'Are you sure you wish to delete this item?', 'woocommerce' ),
			'i18n_global_unique_id_error'       => __( 'Please enter only numbers and hyphens (-).', 'woocommerce' ),
			'decimal_point'                     => $decimal,
			'mon_decimal_point'                 => wc_get_price_decimal_separator(),
			'ajax_url'                          => admin_url( 'admin-ajax.php' ),
			'strings'                           => [
				'import_products' => __( 'Import', 'woocommerce' ),
				'export_products' => __( 'Export', 'woocommerce' ),
			],
			'nonces'                            => [
				'gateway_toggle' => current_user_can( 'manage_woocommerce' ) ? wp_create_nonce( 'woocommerce-toggle-payment-gateway-enabled' ) : null,
			],
			'urls'                              => [
				'add_product'     => \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'product_block_editor' ) ? esc_url_raw( admin_url( 'admin.php?page=wc-admin&path=/add-product' ) ) : null,
				'import_products' => current_user_can( 'import' ) ? esc_url_raw( admin_url( 'edit.php?post_type=product&page=product_importer' ) ) : null,
				'export_products' => current_user_can( 'export' ) ? esc_url_raw( admin_url( 'edit.php?post_type=product&page=product_exporter' ) ) : null,
			],
		];

		wp_localize_script( 'woocommerce_admin', 'woocommerce_admin', $params );

		wp_enqueue_script( 'woocommerce_admin' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}


	/**
	 * Ajax callback for importing one batch of table shipping rates from a CSV.
	 */
	public function do_ajax_table_shipping_import() {
		if ( ! $this->import_allowed() ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient privileges to import table shipping rates.', 'cc-table-shipping' ) ] );
		}

		CSV_Rows_Importer_Controller::dispatch_ajax();
	}

	/**
	 * Return true if WooCommerce imports are allowed for current user, false otherwise.
	 *
	 * @return bool Whether current user can perform imports.
	 */
	protected function import_allowed() {
		return current_user_can( 'manage_woocommerce' ) && current_user_can( 'import' );
	}
}
