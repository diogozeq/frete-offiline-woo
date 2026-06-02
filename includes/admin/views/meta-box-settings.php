<?php
/**
 * CCTS meta box settings.
 *
 * Display the shipping settings in the meta box.
 *
 * @author		Fernando Acosta
 * @package		WC Table Shipping
 * @version		1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

wp_nonce_field( 'ccts_settings_meta_box', 'ccts_settings_meta_box_nonce' );

global $post;
$shipping_title  = get_post_meta( $post->ID, '_ccts_shipping_title', true );
$shipping_title  = ! empty( $shipping_title ) ? $shipping_title : '';
$additional_days = get_post_meta( $post->ID, '_ccts_additional_days', true );

?>

<div class='ccts ccts_settings ccts_meta_box ccts_settings_meta_box'>
  <?php do_action( 'cc_table_shipping_before_method_settings', $post ); ?>

	<div class='ccts-option'>
		<label for='ccts_shipping_title'><?php _e( 'Shipping title', 'cc-table-shipping' ); ?></label>
		<input
			type='text'
      class='input-text'
      id='ccts_shipping_title'
			name='_ccts_shipping_title'
			value='<?php echo esc_attr( $shipping_title ); ?>'
			placeholder='<?php _e( 'e.g. Correios', 'cc-table-shipping' ); ?>'
		>

    <p class="description">Utilize {name} para o <strong>Nome do método</strong> e {delivery_time} para o <strong>Prazo de entrega</strong></p>
	</div>

  <div class='ccts-option'>
    <label for='ccts_additional_days'><?php _e( 'Additional Days', 'cc-table-shipping' ); ?></label>
    <input
      type='number'
      class='input-text'
      id='ccts_additional_days'
      name='_ccts_additional_days'
      value='<?php echo esc_attr( $additional_days ); ?>'
      placeholder='<?php _e( 'e.g. 1', 'cc-table-shipping' ); ?>'
      min='0'
    >

    <p class="description"><?php _e( 'Additional days to the estimated delivery.', 'cc-table-shipping' ) ?></p>
  </div>

  <?php do_action( 'cc_table_shipping_after_method_settings', $post ); ?>
</div>
