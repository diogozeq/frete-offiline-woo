<?php
/*
 * Plugin Name: WC Tabela de Frete Offline (Claude Code)
 * Description: Calcula frete offline a partir de uma tabela CSV ou colando a planilha direto na tela. Cópia independente — pode rodar junto com o plugin original sem conflito.
 * Plugin URI:  http://fernandoacosta.net
 * Author:      Fernando Acosta
 * Author URI:  http://fernandoacosta.net
 * Version:     1.6.1
 * WC requires at least: 5.0.0
 * WC tested up to:      10.1.4
 * Text Domain: cc-table-shipping
 * Domain Path: /languages
 * License:     GPL2
*/
/*
    Copyright (C) 2018  Fernando Acosta  contato@fernandoacosta.net
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * WC Table Shipping Init
 */
class CC_Table_Shipping {

	public const VERSION = '1.6.1';

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin public actions.
	 */
	function __construct() {
		$this->init();
	}

	/**
	 * Init.
	 *
	 * Initialize plugin parts.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		if ( ! defined( 'WC_VERSION' ) ) {
			return;
		}

		$this->init_composer();

		// Add framework
		$this->includes();

		// Add hooks/filters
		$this->hooks();

		// Functions
		require_once plugin_dir_path( __FILE__ ) . 'includes/core-functions.php';

		/**
		 * Require file with helpers.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-ccts-helper.php';
	}

	private function init_composer() {
		$file = __DIR__ . '/vendor/autoload.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}

		new \CC_Table_Shipping\Database_Import\Init();

		if ( is_admin() ) {
			new \CC_Table_Shipping\Admin\Migrations();
		}
	}

	public function includes() {
		include_once 'magic-fw/magic-fw.php';

		require_once 'updates/Licenses_Page.php';
		require_once 'updates/Licenses.php';
		require_once 'updates/updates-functions.php';

		include_once 'includes/class-ccts-delivery-time.php';
	}

	/**
	 * Hooks.
	 *
	 * Initialize all class hooks.
	 *
	 * @since 1.0.0
	 */
	public function hooks() {

		add_action( 'init', [ __CLASS__, 'load_plugin_textdomain' ], - 1 );

		// Initialize shipping method class
		add_action( 'woocommerce_shipping_init', [ $this, 'ccts_add_method' ] );

		// Add shipping method
		add_filter( 'woocommerce_shipping_methods', [ $this, 'ccts_add_shipping_method' ] );

	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain( 'cc-table-shipping', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Get main file.
	 *
	 * @return string
	 */
	public static function get_main_file() {
		return __FILE__;
	}

	/**
	 * Get the plugin url.
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Get the plugin dir url.
	 * @return string
	 */
	public static function plugin_dir_url() {
		return plugin_dir_url( __FILE__ );
	}

	/**
	 * Get templates path.
	 *
	 * @return string
	 */
	public static function get_templates_path() {
		return self::get_plugin_path() . 'templates/';
	}

	/**
	 * Get plugin path.
	 *
	 * @return string
	 */

	public static function get_plugin_path() {
		return plugin_dir_path( __FILE__ );
	}

	/**
	 * Get plugin path.
	 *
	 * @return string
	 */
	public static function get_plugin_basename() {
		return plugin_basename( __FILE__ );
	}

	/**
	 * Shipping method.
	 *
	 * Include the WooCommerce shipping method class.
	 *
	 * @since 1.0.0
	 */
	public function ccts_add_method() {

		/**
		 * ccts shipping method
		 */
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-ccts-method.php';
	}

	/**
	 * Add shipping method.
	 *
	 * Add shipping method to WooCommerce.
	 *
	 * @since 1.0.0
	 */
	public function ccts_add_shipping_method( $methods ) {

		if ( class_exists( 'CC_Table_Shipping_Method' ) ) :
			$methods['cc_table_shipping'] = 'CC_Table_Shipping_Method';
		endif;

		return $methods;
	}
}

add_action( 'plugins_loaded', [ 'CC_Table_Shipping', 'get_instance' ] );


/**
 * ERP integration
 */
add_filter( 'woocommerce_shipping_rate_method_id', 'ccts_official_custom_rate_method_id', 20, 2 );
function ccts_official_custom_rate_method_id( $rate_id, $rate ) {
	if ( 'cc_table_shipping' === $rate_id ) {
		$rate_id = 'ccts_' . $rate->get_instance_id();
	}

	return $rate_id;
}

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__,
				true );
		}
	}
);


// backward compatibility
class_alias( 'CC_Table_Shipping', 'WC_Table_Shippiing' );
