<?php
/**
 * Admin View: Importer - CSV import progress
 *
 * @package WooCommerce\Admin\Importers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wc-progress-form-content woocommerce-importer woocommerce-importer__importing">
    <header>
        <span class="spinner is-active"></span>
        <h2><?php esc_html_e( 'Importando', 'cc-table-shipping' ); ?></h2>
        <p><?php esc_html_e( 'Sua tabela de frete está sendo importada para o banco de dados...', 'cc-table-shipping' ); ?></p>
    </header>
    <section>
        <progress class="woocommerce-importer-progress" max="100" value="0"></progress>
    </section>
</div>
