<?php
/**
 * Admin View: Product import form
 *
 * @package WooCommerce\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form class="wc-progress-form-content woocommerce-importer" enctype="multipart/form-data" method="post">
    <header>
        <h2><?php esc_html_e( 'Importar sua tabela para o banco de dados', 'cc-table-shipping' ); ?></h2>
        <p><?php esc_html_e( 'Dessa forma, o tempo de resposta deve melhorar consideravelmente. Isso porque o banco de dados é feito para processar grandes quantidades de dados.',
				'cc-table-shipping' ); ?></p>

        <p><span style="font-weight: bold; color: red;"><?php esc_html_e( 'Importante!',
					'cc-table-shipping' ) ?></span> <?php esc_html_e( 'Certifique-se de que sua tabela está funcionando e corretamente configurada. Caso contrário sua tabela será importada com os mesmos erros para o banco de dados.',
				'cc-table-shipping' ); ?></p>
    </header>
    <script type="text/javascript">
        jQuery(function () {
            jQuery('.woocommerce-importer-toggle-advanced-options').on('click', function () {
                var elements = jQuery('.woocommerce-importer-advanced');
                if (elements.is('.hidden')) {
                    elements.removeClass('hidden');
                    jQuery(this).text(jQuery(this).data('hidetext'));
                } else {
                    elements.addClass('hidden');
                    jQuery(this).text(jQuery(this).data('showtext'));
                }
                return false;
            });
        });
    </script>
    <div class="wc-actions">
        <?php if ( ! $is_valid_instance || ! $file_path ): ?>
            <div class="notice notice-error inline" style="margin-bottom: 10px;">
                <p><strong><?php esc_html_e( 'Não é possível continuar:', 'cc-table-shipping' ); ?></strong></p>
                <p>
                    <?php 
                    if ( ! $this->instance_id ) {
                        esc_html_e( 'ID da instância do método de frete não foi fornecido na URL.', 'cc-table-shipping' );
                    } elseif ( ! $is_valid_instance ) {
                        esc_html_e( 'A instância fornecida não é válida ou não é um método de Frete por Tabela.', 'cc-table-shipping' );
                    } elseif ( ! $file_path ) {
                        esc_html_e( 'Nenhum arquivo CSV foi encontrado. Configure um arquivo na página do método de frete.', 'cc-table-shipping' );
                    }
                    ?>
                </p>
                <p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>" class="button">
                        <?php esc_html_e( 'Ir para configurações de frete', 'cc-table-shipping' ); ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if ( $is_valid_instance && $file_path && $this->has_pending_import() ): ?>
            <div class="notice notice-info inline" style="margin-bottom: 10px;">
                <p><strong><?php esc_html_e( 'Importação pendente encontrada', 'cc-table-shipping' ); ?></strong></p>
                <p>
                    <?php 
                    printf(
                        esc_html__( 'Existe uma importação interrompida em %d%% de progresso. O que você deseja fazer?', 'cc-table-shipping' ),
                        $this->get_import_progress()
                    );
                    ?>
                </p>
                <p>
                    <label>
                        <input type="radio" name="import_action" value="resume" checked>
                        <?php esc_html_e( 'Continuar de onde parou', 'cc-table-shipping' ); ?>
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="import_action" value="restart">
                        <?php esc_html_e( 'Recomeçar do início', 'cc-table-shipping' ); ?>
                    </label>
                </p>
            </div>
        <?php endif; ?>
        
        <button type="submit" class="button button-primary button-next"
                value="<?php esc_attr_e( 'Avançar', 'cc-table-shipping' ); ?>"
                name="save_step" 
                <?php echo ( ! $is_valid_instance || ! $file_path ) ? 'disabled' : ''; ?>>
            <?php esc_html_e( 'Avançar', 'cc-table-shipping' ); ?>
        </button>
		<?php wp_nonce_field( 'cc-table-shipping-csv-importer' ); ?>
    </div>
</form>
