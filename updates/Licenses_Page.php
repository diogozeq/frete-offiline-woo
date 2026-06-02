<?php

namespace FA_Updates;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\FA_Updates\Licenses_Page', false ) ) :

  class Licenses_Page {
    /**
     * The settings require to add the submenu page "Activation"
     *
     * @since 1.0
     * @var array
     */
    protected $settings = array();

    /**
     * Updated plugins info
     *
     * @since 1.0
     * @var array
     */
    protected $plugins_info = array();

    /**
     * Licenses feed!
     *
     * @since 1.0
     * @var array
     */
    protected $feed = array();


    function __construct() {
      $this->settings = array(
        'parent_page' => function_exists( 'wc' ) ? 'woocommerce' : 'plugins.php',
        'page_title'  => __( 'Licenças', 'fernandoacosta' ),
        'menu_title'  => __( 'Licenças', 'fernandoacosta' ),
        'capability'  => 'manage_options',
        'page'        => 'rishi_plugins_activation',
      );

      add_action( 'admin_menu', array( $this, 'add_submenu_page' ), 99 );
      add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 99 );

      add_action( 'admin_notices', array( $this, 'pending_license_notice' ) );
    }


    public function add_submenu_page() {
      $bubble = '';

      $page = add_submenu_page(
        $this->settings['parent_page'],
        $this->settings['page_title'],
        $this->settings['menu_title'] . $bubble,
        $this->settings['capability'],
        $this->settings['page'],
        array( $this, 'show_activation_panel' )
      );

      add_action( "load-$page", array( $this, 'load' ) );
    }


    public function show_activation_panel() {
      echo '<style>.notice:not(.fa-admin-notice) { display: none; }</style>';
      include __DIR__ . '/views/html-settings-page.php';
    }


    public function admin_enqueue_scripts() {
      $current_screen = get_current_screen();

      if ( ! in_array( $current_screen->id, [ 'plugins_page_rishi_plugins_activation', 'woocommerce_page_rishi_plugins_activation', 'admin_page_rishi_plugins_activation' ] ) ) {
        return;
      }

      foreach ( fa_updates()->get_plugins() as $plugin ) {
        wp_enqueue_style(
          'rishi_plugins_activation-dashboard',
          plugin_dir_url( WP_PLUGIN_DIR . '/' . $plugin['basename'] ) . 'updates/assets/css/licenses.css',
        );

        break;
      }
    }






    /**
     * load
     *
     * Runs when loading the submenu page.
     *
     * @date	7/01/2014
     * @since	5.0.0
     *
     * @param	void
     * @return	void
     */
    function load() {
      // Check activate.
      if( fa_verify_nonce('fa_activate_plugin') ) {
        $this->activate_pro_licence();

      // Check deactivate.
      } elseif( fa_verify_nonce('fa_deactivate_plugin') ) {
        $this->deactivate_pro_licence();
      }

      $this->plugins_info = fa_updates()->get_plugin_updates( true );
      $this->feed = fa_updates()->get_feed();
    }


    /**
     * activate_pro_licence
     *
     * Activates the submitted license key.
     *
     * @date	16/01/2014
     * @since	5.0.0
     *
     * @param	void
     * @return	void
     */
    function activate_pro_licence() {
      $plugin = fa_updates()->get_plugin_by('slug', trim($_POST['slug']));

      if ( ! $plugin ) {
        return;
      }

      // Connect to API.
      $post = array(
        'license'	=> trim($_POST['license']),
        'version'	=> $plugin['version'],
        'slug'	=> $plugin['slug'],
        'wp' => array(
          'wp_name'		=> get_bloginfo('name'),
          'wp_url'		=> home_url(),
          'wp_version'	=> get_bloginfo('version'),
          'wp_language'	=> get_bloginfo('language'),
          'wp_timezone'	=> get_option('timezone_string'),
          'wp_email'	=> get_option('admin_email'),
        ),
      );

      $response = fa_updates()->request('v1/plugins/activate', $post);

      // Check response is expected JSON array (not string).
      if( is_string($response) ) {
        $response = new WP_Error( 'server_error', esc_html($response) );
      }

      // Display error.
      if( is_wp_error($response) ) {
        return fa_inline_admin_notice( $response->get_error_message(), 'warning' );
      }

      // On success.
      if( isset( $response['success'] ) && $response['success'] ) {

        // Update license.
        fa_pro_update_license( $plugin['slug'], trim($_POST['license']) );

        // Refresh plugins transient to fetch new update data.
        fa_updates()->refresh_plugins_transient();

        // Show notice.

        fa_inline_admin_notice( $response['message'], 'success' );

      // On failure.
      } else {

        // Show notice.
        fa_inline_admin_notice( $response['message'], 'warning' );
      }
    }

    /**
     * activate_pro_licence
     *
     * Deactivates the registered license key.
     *
     * @date	16/01/2014
     * @since	5.0.0
     *
     * @param	void
     * @return	void
     */
    function deactivate_pro_licence() {
      $plugin = fa_updates()->get_plugin_by('slug', trim($_POST['slug']));

      if ( ! $plugin ) {
        return;
      }

      // Get license key.
      $license = fa_pro_get_license_key( $plugin['slug'] );

      // Bail early if no key.
      if( !$license ) {
        return;
      }

      // Connect to API.
      $post = array(
        'license'	=> $license,
        'slug' => $plugin['slug'],
        'wp' => [
          'wp_url'		=> home_url(),
        ],
      );
      $response = fa_updates()->request('v1/plugins/deactivate', $post);

      // Check response is expected JSON array (not string).
      if( is_string($response) ) {
        $response = new WP_Error( 'server_error', esc_html($response) );
      }

      // Display error.
      if( is_wp_error($response) ) {
        return fa_inline_admin_notice( $response->get_error_message(), 'warning' );
      }

      // Remove license key from DB.
      fa_pro_update_license( $plugin['slug'], '' );

      // Refresh plugins transient to fetch new update data.
      fa_updates()->refresh_plugins_transient();

      // On success.
      if( isset( $response['success'] ) && $response['success'] ) {

        // Show notice.
        fa_inline_admin_notice( $response['message'], 'info' );

      // On failure.
      } else {

        // Show notice.
        fa_inline_admin_notice( $response['message'], 'warning' );
      }
    }


    public function pending_license_notice() {
      foreach ( fa_updates()->get_plugins() as $plugin ) {
        if ( ! $plugin['key'] ) {
          if ( ! isset( $_GET['page'] ) || 'rishi_plugins_activation' !== $_GET['page'] ) {
            fa_inline_admin_notice( sprintf( '<a href="%s">Clique aqui</a> e ative o plugin <strong>%s</strong> para obter suporte e atualizações automáticas.', admin_url( 'admin.php?page=rishi_plugins_activation' ), $plugin['name'] ), 'warning', true );
          }
        }
      }
    }


    public function get_plugin_new_version( $id ) {
      if ( ! $this->plugins_info || is_wp_error( $this->plugins_info ) ) {
        return;
      }

      $plugin = fa_updates()->get_plugin_by( 'id', $id );
      $new_version = isset( $this->plugins_info['plugins'][ $plugin['basename'] ] ) ? $this->plugins_info['plugins'][ $plugin['basename'] ] : false;

      if ( ! $new_version ) {
        return;
      }

      if ( isset( $new_version['version'] ) && $plugin['version'] === $new_version['version'] ) {
        return;
      }

      return $new_version;
    }
  }

  new Licenses_Page();

endif;
