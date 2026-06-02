<?php

namespace FA_Updates;

defined( 'ABSPATH' ) || exit;

use WP_Error;

if ( ! class_exists( '\FA_Updates\Licenses', false ) ) :

	class Licenses {
		/** @var string The af_Updates version */
		var $version = '1.0.0'; // debug

		/** @var array The array of registered plugins */
		var $plugins = array();

		/** @var int Counts the number of plugin update checks */
		var $checked = 0;

		/*
		*  __construct
		*
		*  Sets up the class functionality.
		*
		*  @date	5/03/2014
		*  @since	5.0.0
		*
		*  @param	void
		*  @return	void
		*/

		function __construct() {

			// append update information to transient
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_plugins_transient' ), 10, 1 );

			// modify plugin data visible in the 'View details' popup
			add_filter( 'plugins_api', array( $this, 'modify_plugin_details' ), 10, 3 );

			add_action( 'admin_init', array( $this, 'maybe_show_licenses_page' ), 50 );

			add_filter( 'upgrader_pre_download', array( $this, 'upgrader_pre_download' ), 10, 3 );
		}


		public function upgrader_pre_download( $reply, $package, $upgrader ) {
			$plugin       = false;
			$is_bulk_ajax = $upgrader->skin instanceof \WP_Ajax_Upgrader_Skin;

			if ( $is_bulk_ajax ) {
				//Bulk Update for WordPress 4.9 or greater
				if ( ! empty( $_POST['plugin'] ) ) {
					$plugin = plugin_basename( sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) );
				}
			} else {
				//Bulk action upgrade
				$action_url = parse_url( $upgrader->skin->options['url'] );
				$output     = [];

				if ( isset( $action_url['query'] ) ) {
					parse_str( rawurldecode( htmlspecialchars_decode( $action_url['query'] ) ), $output );
				}

				$plugins = isset( $output['plugins'] ) ? $output['plugins'] : '';
				$plugins = explode( ',', $plugins );

				foreach ( $plugins as $plugin_init ) {
					$to_upgrade = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_init );
					if ( isset( $upgrader->skin->plugin_info ) && $to_upgrade['Name'] == $upgrader->skin->plugin_info['Name'] ) {
						$plugin = $plugin_init;
					}
				}
			}

			$my_plugin = $this->get_plugin_by( 'basename', $plugin );

			/**
			 * not FernandoAcosta.net premium plugin!
			 */
			if ( ! $my_plugin ) {
				return $reply;
			}

			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $my_plugin['basename'] );

			$plugin_url = isset( $plugin_data['PluginURI'] ) ? $plugin_data['PluginURI'] : 'https://fernandoacosta.net';

			if ( empty( $my_plugin['key'] ) ) {
				return new WP_Error( 'license_not_valid',
					'Este é um plugin pago. <a target="_blank" href="' . $plugin_url . '">Você precisa de uma licença</a> para receber atualizações e suporte.' );
			}

			if ( ! preg_match( '!^(http|https|ftp)://!i', $package ) && file_exists( $package ) ) {
				//Local file or remote?
				return $package;
			}

			if ( empty( $package ) ) {
				// return new WP_Error( 'no_package', $upgrader->strings['no_package'] );
			}

			$upgrader->skin->feedback( 'downloading_package',
				__( 'De fernandoacosta.net...', 'yith-plugin-upgrade-fw' ) );

			$args = array(
				'url'    => $package,
				'plugin' => $my_plugin,
				'wp'     => array(
					'wp_name'     => get_bloginfo( 'name' ),
					'wp_url'      => home_url(),
					'wp_version'  => get_bloginfo( 'version' ),
					'wp_language' => get_bloginfo( 'language' ),
					'wp_timezone' => get_option( 'timezone_string' ),
					'wp_email'    => get_option( 'admin_email' ),
				),
			);

			return $this->_download_url( $args );
		}


		/*
		*  add_plugin
		*
		*  Registeres a plugin for updates.
		*
		*  @date	8/4/17
		*  @since	5.5.10
		*
		*  @param	array $plugin The plugin array.
		*  @return	void
		*/

		function add_plugin( $plugin, $refresh_token = false ) {

			// validate
			$plugin = wp_parse_args( $plugin, array(
				'setup_url' => admin_url( 'admin.php?page=wc-settings' ),
				'name'      => '',
				'id'        => '',
				'key'       => '',
				'slug'      => '',
				'basename'  => '',
				'version'   => '',
			) );

			$this->plugins[ $plugin['basename'] ] = $plugin;

			if ( $refresh_token ) {
				$active_plugins = count( $this->get_plugins() );
				$transient_name = 'af_plugin_info_licenses_missing';

				set_transient( $transient_name, $active_plugins, WEEK_IN_SECONDS * 2 );
			}
		}

		function get_plugins() {
			return $this->plugins;
		}

		/**
		 *  get_plugin_by
		 *
		 *  Returns a registered plugin for the give key and value.
		 *
		 * @date    3/8/18
		 *
		 * @param  string  $key  The array key to compare
		 * @param  string  $value  The value to compare against
		 *
		 * @return    array|false
		 * @since    5.7.2
		 *
		 */

		function get_plugin_by( $key = '', $value = null ) {
			foreach ( $this->plugins as $plugin ) {
				if ( isset( $plugin[ $key ] ) && $plugin[ $key ] === $value ) {
					return $plugin;
				}
			}

			return false;
		}

		/*
		* request
		*
		* Makes a request to the af connect server.
		*
		* @date	8/4/17
		* @since	5.5.10
		*
		* @param	string $endpoint The API endpoint.
		* @param	array $body The body to post.
		* @return	(array|string|WP_Error)
		*/
		function request( $endpoint = '', $body = [], $timeout = 10 ) {
			// Determine URL.
			$url = "https://api.fernandoacosta.net/$endpoint";

			// dev environment.
			if ( defined( 'FA_DEV_API' ) && FA_DEV_API ) {
				$url = FA_DEV_API . "/$endpoint";
			}

			// Make request.
			$raw_response = wp_remote_post( $url, array(
				'timeout' => $timeout,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			) );

			// Handle response error.
			if ( is_wp_error( $raw_response ) ) {
				return $raw_response;

				// Handle http error.
			} elseif ( wp_remote_retrieve_response_code( $raw_response ) != 200 ) {
				$json    = json_decode( wp_remote_retrieve_body( $raw_response ), true );
				$message = isset( $json['message'] ) ? $json['message'] : wp_remote_retrieve_response_message( $raw_response );

				return new WP_Error( 'server_error', $message );
			}

			// Decode JSON response.
			$json = json_decode( wp_remote_retrieve_body( $raw_response ), true );

			// Allow non json value.
			if ( $json === null ) {
				return wp_remote_retrieve_body( $raw_response );
			}

			// return
			return $json;
		}

		/*
		*  get_plugin_info
		*
		*  Returns update information for the given plugin id.
		*
		*  @date	9/4/17
		*  @since	5.5.10
		*
		*  @param	string $id The plugin id such as 'pro'.
		*  @param	boolean $force_check Bypasses cached result. Defaults to false.
		*  @return	array|WP_Error
		*/

		function get_plugin_info( $id = '', $force_check = false ) {
			$force_check = defined( 'FA_DEV_API' ) && FA_DEV_API ? true : $force_check;

			// var
			$transient_name = 'af_plugin_info_' . $id;

			// check cache but allow for $force_check override
			if ( ! $force_check ) {
				$transient = get_transient( $transient_name );
				if ( $transient !== false ) {
					return $transient;
				}
			}

			// connect
			$response = $this->request( 'v1/plugins/get-info?p=' . $id );

			// convert string (misc error) to WP_Error object
			if ( is_string( $response ) ) {
				$response = new WP_Error( 'server_error', esc_html( $response ) );
			}

			// allow json to include expiration but force minimum and max for safety
			$expiration = $this->get_expiration( $response, WEEK_IN_SECONDS, MONTH_IN_SECONDS );

			// update transient
			set_transient( $transient_name, $response, $expiration );

			// return
			return $response;
		}

		/**
		 *  get_plugin_update
		 *
		 *  Returns specific data from the 'update-check' response.
		 *
		 * @date    3/8/18
		 *
		 * @param  string  $basename  The plugin basename.
		 * @param  boolean  $force_check  Bypasses cached result. Defaults to false
		 *
		 * @return    array
		 * @since    5.7.2
		 *
		 */

		function get_plugin_update( $basename = '', $force_check = false ) {

			// get updates
			$updates = $this->get_plugin_updates( $force_check );

			if ( is_wp_error( $updates ) ) {
				return false;
			}

			// check for and return update
			if ( is_array( $updates ) && isset( $updates['plugins'][ $basename ] ) ) {
				return $updates['plugins'][ $basename ];
			}

			return false;
		}


		/**
		 *  get_plugin_updates
		 *
		 *  Checks for plugin updates.
		 *
		 * @date    8/7/18
		 *
		 * @param  boolean  $force_check  Bypasses cached result. Defaults to false.
		 *
		 * @return    array|WP_Error.
		 * @since    5.6.9
		 * @since    5.7.2 Added 'checked' comparison
		 *
		 */

		function get_plugin_updates( $force_check = false ) {
			$force_check = defined( 'FA_DEV_API' ) && FA_DEV_API ? true : $force_check;

			// var
			$transient_name = 'af_plugin_updates';

			// construct array of 'checked' plugins
			// sort by key to avoid detecting change due to "include order"
			$checked = array();

			foreach ( $this->plugins as $basename => $plugin ) {
				$checked[ $basename ] = $plugin['version'];
			}
			ksort( $checked );

			// $force_check prevents transient lookup
			if ( ! $force_check ) {
				$transient = get_transient( $transient_name );

				// if cached response was found, compare $transient['checked'] against $checked and ignore if they don't match (plugins/versions have changed)
				if ( is_array( $transient ) ) {
					$transient_checked = isset( $transient['checked'] ) ? $transient['checked'] : array();
					if ( wp_json_encode( $checked ) !== wp_json_encode( $transient_checked ) ) {
						$transient = false;
					}
				}
				if ( $transient !== false ) {
					return $transient;
				}
			}

			// vars
			$post = array(
				'plugins' => $this->plugins,
				'wp'      => array(
					'wp_name'     => get_bloginfo( 'name' ),
					'wp_url'      => home_url(),
					'wp_version'  => get_bloginfo( 'version' ),
					'wp_language' => get_bloginfo( 'language' ),
					'wp_timezone' => get_option( 'timezone_string' ),
					'wp_email'    => get_option( 'admin_email' ),
				),
			);

			// request
			$response = $this->request( 'v1/plugins/update-check', $post );

			// append checked reference
			if ( is_array( $response ) ) {
				$response['checked'] = $checked;
			}

			// allow json to include expiration but force minimum and max for safety
			$expiration = $this->get_expiration( $response, DAY_IN_SECONDS, MONTH_IN_SECONDS );

			// update transient
			set_transient( $transient_name, $response, $expiration );

			// return
			return $response;
		}

		/**
		 *  get_expiration
		 *
		 *  This function safely gets the expiration value from a response.
		 *
		 * @date    8/7/18
		 *
		 * @param  mixed  $response  The response from the server. Default false.
		 * @param  int  $min  The minimum expiration limit. Default 0.
		 * @param  int  $max  The maximum expiration limit. Default 0.
		 *
		 * @return    int
		 * @since    5.6.9
		 *
		 */

		function get_expiration( $response = false, $min = 0, $max = 0 ) {

			// vars
			$expiration = 0;

			// check
			if ( is_array( $response ) && isset( $response['expiration'] ) ) {
				$expiration = (int) $response['expiration'];
			}

			// min
			if ( $expiration < $min ) {
				return $min;
			}

			// max
			if ( $expiration > $max ) {
				return $max;
			}

			// return
			return $expiration;
		}

		/*
		*  refresh_plugins_transient
		*
		*  Deletes transients and allows a fresh lookup.
		*
		*  @date	11/4/17
		*  @since	5.5.10
		*
		*  @param	void
		*  @return	void
		*/

		function refresh_plugins_transient() {
			delete_site_transient( 'update_plugins' );
			delete_transient( 'af_plugin_updates' );
		}

		/*
		*  modify_plugins_transient
		*
		*  Called when WP updates the 'update_plugins' site transient. Used to inject af plugin update info.
		*
		*  @date	16/01/2014
		*  @since	5.0.0
		*
		*  @param	object $transient
		*  @return	$transient
		*/

		function modify_plugins_transient( $transient ) {

			// bail early if no response (error)
			if ( ! isset( $transient->response ) ) {
				return $transient;
			}

			// force-check (only once)
			$force_check = ( $this->checked == 0 ) ? ! empty( $_GET['force-check'] ) : false;

			// fetch updates (this filter is called multiple times during a single page load)
			$updates = $this->get_plugin_updates( $force_check );

			if ( is_wp_error( $updates ) ) {
				return $transient;
			}

			// append
			if ( isset( $updates['plugins'] ) && is_array( $updates['plugins'] ) ) {
				foreach ( $updates['plugins'] as $basename => $update ) {
					$transient->response[ $basename ] = (object) $update;
				}
			}

			// increase
			$this->checked ++;

			// return
			return $transient;
		}

		/*
		*  modify_plugin_details
		*
		*  Returns the plugin data visible in the 'View details' popup
		*
		*  @date	17/01/2014
		*  @since	5.0.0
		*
		*  @param	object $result
		*  @param	string $action
		*  @param	object $args
		*  @return	$result
		*/

		function modify_plugin_details( $result, $action = null, $args = null ) {

			// vars
			$plugin = false;

			// only for 'plugin_information' action
			if ( $action !== 'plugin_information' ) {
				return $result;
			}

			// find plugin via slug
			$plugin = $this->get_plugin_by( 'slug', $args->slug );

			if ( ! $plugin ) {
				return $result;
			}

			// connect
			$response = $this->get_plugin_info( $plugin['id'] );

			// bail early if no response
			if ( ! is_array( $response ) ) {
				return $result;
			}

			// remove tags (different context)
			unset( $response['tags'] );

			// convert to object
			$response = (object) $response;

			// sections
			$sections = array(
				'description'    => '',
				'installation'   => '',
				'changelog'      => '',
				'upgrade_notice' => '',
			);
			foreach ( $sections as $k => $v ) {
				$sections[ $k ] = $response->$k;
			}
			$response->sections = $sections;

			// return
			return $response;
		}


		public function get_feed() {
			$force_check = defined( 'FA_DEV_API' ) && FA_DEV_API ? true : isset( $_GET['refresh_plugins_feed'] );

			// var
			$transient_name = 'af_plugins_feed';

			// check cache but allow for $force_check override
			if ( ! $force_check ) {
				$transient = get_transient( $transient_name );
				if ( $transient !== false ) {
					return $transient;
				}
			}

			// vars
			$post = array(
				'plugins' => $this->plugins,
				'wp'      => array(
					'wp_name'     => get_bloginfo( 'name' ),
					'wp_url'      => home_url(),
					'wp_version'  => get_bloginfo( 'version' ),
					'wp_language' => get_bloginfo( 'language' ),
					'wp_timezone' => get_option( 'timezone_string' ),
					'wp_email'    => get_option( 'admin_email' ),
				),
			);

			// request
			$response = $this->request( 'v1/plugins/feed', $post );

			// convert string (misc error) to WP_Error object
			if ( is_string( $response ) ) {
				return false;
			}

			if ( is_wp_error( $response ) ) {
				return false;
			}

			if ( ! isset( $response['html_feed'] ) ) {
				return false;
			}

			if ( isset( $response['transients'] ) ) {
				foreach ( $response['options'] as $key => $value ) {
					delete_transient( $key );
				}
			}

			if ( isset( $response['options'] ) ) {
				foreach ( $response['options'] as $key => $value ) {
					update_option( $key, $value );
				}
			}

			// update transient
			set_transient( $transient_name, $response,
				isset( $response['expiration'] ) ? $response['expiration'] : WEEK_IN_SECONDS );

			// return
			return $response;
		}


		public function maybe_show_licenses_page() {
			if ( wp_doing_ajax() || ! isset( $_SERVER['SCRIPT_FILENAME'] ) || basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) ) === 'admin-post.php' ) {
				return false;
			}

			$active_plugins = count( $this->get_plugins() );
			$transient_name = 'af_plugin_info_licenses_missing';

			$transient = get_transient( $transient_name );

			if ( false !== $transient && $active_plugins === intval( $transient ) ) {
				return true;
			}

			$should_redirect = false;

			foreach ( $this->get_plugins() as $plugin ) {
				if ( empty( $plugin['key'] ) && $active_plugins > intval( $transient ) ) {
					$should_redirect = true;
					break;
				}
			}

			$should_redirect = false;

			set_transient( $transient_name, $active_plugins, WEEK_IN_SECONDS * 2 );

			if ( $should_redirect ) {
				$first_install = ! get_option( 'fa_plugins_first_activation_date' );
				$suffix        = $first_install ? 'welcome' : 're' . 'mi' . 'nd' . 'er';
				$suffix        = intval( $transient ) < $active_plugins ? '' : $suffix;

				if ( $first_install ) {
					update_option( 'fa_plugins_first_activation_date', time() );
				}

				if ( current_user_can( 'manage_woocommerce' ) ) {
					wp_redirect( admin_url( 'admin.php?page=rishi' . '_' . 'plugins' . '_' . 'activation&' . $suffix ) );
					exit;
				}
			}
		}


		/**
		 * Retrieve the temp filename
		 *
		 * @param  string  $body  The post data fields
		 * @param  int  $timeout  Execution timeout (default: 300)
		 *
		 * @return string | WP_Error
		 *
		 * @since    1.0
		 * @see      wp-admin/includes/class-wp-upgrader.php
		 */
		protected function _download_url( $body ) {
			$response = $this->request( 'v1/plugins/download-url', $body );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( empty( $response['url'] ) ) {
				return new WP_Error( 'invalid_url',
					'Download indisponível. Entre em contato para obter assistência (#0448)' );
			}

			$download_file = download_url( $response['url'], 300, false );

			if ( is_wp_error( $download_file ) && ! $download_file->get_error_data( 'softfail-filename' ) ) {
				return new WP_Error( 'download_failed', 'Ocorreu um erro no download.',
					$download_file->get_error_message() );
			}

			return $download_file;
		}
	}

endif;
