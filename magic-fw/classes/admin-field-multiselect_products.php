<?php
/**
*
*/

if ( ! class_exists( 'FA_WC_New_Settings_Fields' ) ) {

  class FA_WC_New_Settings_Fields {

    function __construct() {
      add_action( 'woocommerce_admin_field_multiselect_products', array( $this, 'multiselect_products' ), 10, 1 );
      add_action( 'woocommerce_admin_field_select_products', array( $this, 'multiselect_products' ), 10, 1 );
    }

    // campo para selecionar múltiplos produtos
    // feito com base no campo de PAÍSES
    public function multiselect_products( $value ) {
        // Description handling
        $field_description = WC_Admin_Settings::get_field_description( $value );
        extract( $field_description );

        $selections = (array) WC_Admin_Settings::get_option( $value['id'] );

        if ( ! empty( $value['options'] ) ) {
          $products = $value['options'];
        } else {
          $products = wc_get_products( array(
            'status'         => array( 'private', 'publish' ),
            'limit'          => -1,
            'orderby'        => array(
              'menu_order' => 'ASC',
              'ID'         => 'DESC',
            ),
            'return'         => 'objects',
          ) );
        }

        asort( $products );
        ?><tr valign="top">
          <th scope="row" class="titledesc">
            <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
            <?php // echo $tooltip_html; ?>
          </th>
          <td class="forminp">
            <select
              name="<?php echo esc_attr( $value['id'] ); ?><?php echo ( 'multiselect_products' === $value['type'] ) ? '[]' : ''; ?>"
              style="<?php echo esc_attr( $value['css'] ); ?>"
              data-placeholder="Selecione os produtos"
              aria-label="Produto"
              class="<?php echo esc_attr( $value['class'] ); ?>"
              <?php echo ( 'multiselect_products' == $value['type'] ) ? 'multiple="multiple"' : ''; ?>
              >
              <?php
                if ( ! empty( $products ) ) {
                  foreach ( $products as $product ) {
                    $product_id = $product->get_id();

                    echo '<option value="' . esc_attr( $product_id ) . '" ' . selected( in_array( $product_id, $selections ), true, false ) . '>' . $product->get_formatted_name() . '</option>';
                  }
                }
              ?>
            </select> <?php echo ( $description ) ? $description : ''; ?>

            <?php if ( 'multiselect_products' === $value['type'] ) { ?>
              <br /><a class="select_all button" href="#"><?php _e( 'Select all', 'woocommerce' ); ?></a> <a class="select_none button" href="#"><?php _e( 'Select none', 'woocommerce' ); ?></a>
            <?php } ?>

          </td>
        </tr>
    <?php }
  }

  new FA_WC_New_Settings_Fields();

}
