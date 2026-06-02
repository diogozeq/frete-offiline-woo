<?php

namespace FA_Updates;

if ( ! class_exists('\Fa_Updates\Updates', false ) ) {
  class Updates {
    public $path;


    function __construct( $path ) {
      $this->path = $path;

      // actions
      add_action( 'init',	array( $this, 'init' ), 20 );
    }

    function modify_plugin_update_message( $plugin_data, $response ) {

      // bail ealry if has key
      // if( fa_pro_get_license_key() ) return;


      // display message
      echo '<br />' . sprintf( __('To enable updates, please enter your license key on the <a href="%s">Updates</a> page. If you don\'t have a licence key, please see <a href="%s">details & pricing</a>.', 'fa'), admin_url('edit.php?post_type='), 'https://fernandoacosta.net' );

    }
  }
}
