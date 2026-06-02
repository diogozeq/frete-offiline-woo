<?php
if ( ! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class CC_Table_Shipping_Delivery_Time
{
    function __construct()
    {
        add_action('woocommerce_after_shipping_rate', [$this, 'shipping_delivery_forecast'], 100);

        fa_updates()->add_plugin([
            'setup_url' => admin_url('admin.php?page=wc-settings&tab=shipping'),
            'name'      => 'WC Tabela de Frete Offline',
            'id'        => 'cc-table-shipping',
            'key'       => fa_pro_get_license_key('cc-table-shipping'),
            'slug'      => 'cc-table-shipping',
            'basename'  => CC_Table_Shipping::get_plugin_basename(),
            'version'   => CC_Table_Shipping::VERSION,
        ]);
    }

    /**
     * Adds delivery forecast after method name.
     *
     * @param  WC_Shipping_Rate  $shipping_method  Shipping method data.
     */
    public function shipping_delivery_forecast($shipping_method)
    {
        $meta_data = $shipping_method->get_meta_data();

        if (isset($meta_data['delivery_time_meta']) && $meta_data['delivery_time_meta']) {
            $total = isset($meta_data['delivery_time']) ? intval($meta_data['delivery_time']) : 0;

            if ($total) {
                /* translators: %d: days to delivery */
                echo '<p><small>'.esc_html(sprintf(_n('Entrega em até %d dia útil', 'Entrega em até %d dias úteis',
                        $total, 'cc-table-shipping'), $total)).'</small></p>';
            }
        }
    }
}

new CC_Table_Shipping_Delivery_Time();
