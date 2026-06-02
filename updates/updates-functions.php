<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! function_exists( 'fa_updates' ) ) {
  function fa_updates() {
    global $fa_updates;

    if ( ! isset( $fa_updates ) ) {
      $fa_updates = new \Fa_Updates\Licenses();
    }

    return $fa_updates;
  }
}

if ( ! function_exists( 'fa_pro_get_license' ) ) {
  function fa_pro_get_license( $slug ) {

    // get option
    $license = get_option( $slug . '_license_key_v2');

    // try previous type
    if( !$license ) {
      $license = get_option( $slug . '_license_key', $license );

      if ( ! $license ) {
        return false;
      }

      $license = fa_pro_update_license( $slug, $license );
    }

    $license = maybe_unserialize(base64_decode($license));

    // bail early if corrupt
    if ( ! is_array( $license ) ) {
      return false;
    };

    // return
    return $license;
  }
}


/*
*  fa_pro_get_license_key
*
*  This function will return the license key
*
*  @type	function
*  @date	20/09/2016
*  @since	5.4.0
*
*  @param	n/a
*  @return	n/a
*/
if ( ! function_exists( 'fa_pro_get_license_key' ) ) {
  function fa_pro_get_license_key( $slug ) {
    // vars
    $license = fa_pro_get_license( $slug );
    $home_url = home_url();


    // bail early if empty
    if( !$license || !$license['key'] ) return false;


    // bail early if url has changed
    if( fa_strip_protocol($license['url']) !== fa_strip_protocol($home_url) ) return false;

    // return
    return $license['key'];
  }
}


if ( ! function_exists( 'fa_strip_protocol' ) ) {
  function fa_strip_protocol( $url ) {
    return str_replace( array( 'http://', 'https://' ), '', $url );
  }
}




if ( ! function_exists( 'fa_pro_update_license' ) ) {
  function fa_pro_update_license( $slug, $key = '' ) {
    // vars
    $value = '';

    // key
    if( $key ) {
      // vars
      $data = array(
        'key'	=> $key,
        'url'	=> fa_strip_protocol( home_url() )
      );

      // encode
      $value = base64_encode(maybe_serialize($data));
    }

    $plugin = fa_updates()->get_plugin_by('slug', $slug);
    $plugin['key'] = $key;

    // re-register update (key has changed)
    fa_updates()->add_plugin( $plugin, ! empty( $key ) );

    // update
    return update_option( $slug . '_license_key_v2', $value );
  }
}



if ( ! function_exists( 'fa_verify_nonce' ) ) {
  function fa_verify_nonce($value) {
    $nonce = isset( $_POST['_fa_nonce'] ) ? $_POST['_fa_nonce'] : '';

    // bail early nonce does not match (post|user|comment|term)
    if( !$nonce || !wp_verify_nonce($nonce, $value) ) return false;

    // reset nonce (only allow 1 save)
    $_POST['_fa_nonce'] = false;

    return true;
  }
}


if ( ! function_exists( 'fa_inline_admin_notice' ) ) {
  function fa_inline_admin_notice( $notice_text, $notice_type = 'success', $global = false ) {
    if ( $global ) {

      $is_dismissible = true;

      printf('<div class="fa-admin-notice notice notice-%s %s">%s</div>',
        esc_attr( $notice_type ),
        $is_dismissible ? 'is-dismissible' : '',
        wpautop( $notice_text ) );

    } else {
      add_action( 'fa_admin_notices', function() use ( $notice_text, $notice_type ) {
        $is_dismissible = true;

        printf('<div class="fa-admin-notice notice notice-%s %s">%s</div>',
        esc_attr( $notice_type ),
        $is_dismissible ? 'is-dismissible' : '',
        wpautop( $notice_text ) );

      });
    }
  }
}
