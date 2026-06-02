<?php
/**
 * Admin View: Importer - Done!
 *
 * @package WooCommerce\Admin\Importers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wc-progress-form-content woocommerce-importer">
    <section class="woocommerce-importer-done">
		<?php
		$results = [];

		if ( 0 < $imported ) {
			$results[] = sprintf(
			/* translators: %d: rates count */
				_n( '%s taxa de frete importada', '%s taxas de frete importadas', $imported, 'cc-table-shipping' ),
				'<strong>' . number_format_i18n( $imported ) . '</strong>'
			);
		}

		if ( 0 < $updated ) {
			$results[] = sprintf(
			/* translators: %d: rates count */
				_n( '%s tabela de frete atualizada', '%s tabelas de frete atualizadas', $updated, 'cc-table-shipping' ),
				'<strong>' . number_format_i18n( $updated ) . '</strong>'
			);
		}

		if ( 0 < $skipped ) {
			$results[] = sprintf(
			/* translators: %d: rates count */
				_n( '%s linha foi ignorada', '%s linhas foram ignoradas', $skipped, 'cc-table-shipping' ),
				'<strong>' . number_format_i18n( $skipped ) . '</strong>'
			);
		}

		if ( 0 < $failed ) {
			$results [] = sprintf(
			/* translators: %d: rates count */
				_n( 'Falha ao importar %s taxa', 'Falha ao importar %s taxas', $failed, 'cc-table-shipping' ),
				'<strong>' . number_format_i18n( $failed ) . '</strong>'
			);
		}

		if ( 0 < $failed || 0 < $skipped ) {
			$results[] = '<a href="#" class="woocommerce-importer-done-view-errors">' . __( 'Ver log de importação',
					'cc-table-shipping' ) . '</a>';
		}

		if ( ! empty( $file_name ) ) {
			$results[] = sprintf(
			/* translators: %s: File name */
				__( 'Arquivo processado: %s', 'cc-table-shipping' ),
				'<strong>' . $file_name . '</strong>'
			);
		}

		/* translators: %d: import results */
		echo wp_kses_post( __( 'Importação concluída!', 'cc-table-shipping' ) . ' ' . implode( '. ', $results ) );
		?>
    </section>
    <section class="wc-importer-error-log" style="display:none">
        <table class="widefat wc-importer-error-log-table">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Linha', 'cc-table-shipping' ); ?></th>
                <th><?php esc_html_e( 'Motivo da falha', 'cc-table-shipping' ); ?></th>
            </tr>
            </thead>
            <tbody>
			<?php
			if ( is_array( $errors ) && count( $errors ) ) {
				foreach ( $errors as $error ) {
					if ( ! is_wp_error( $error ) ) {
						continue;
					}
					$error_data = $error->get_error_data();
					?>
                    <tr>
                        <th><code><?php echo esc_html( $error_data['row'] ); ?></code></th>
                        <td><?php echo wp_kses_post( $error->get_error_message() ); ?></td>
                    </tr>
					<?php
				}
			}
			?>
            </tbody>
        </table>
    </section>
    <script type="text/javascript">
        jQuery(function () {
            jQuery('.woocommerce-importer-done-view-errors').on('click', function () {
                jQuery('.wc-importer-error-log').slideToggle();
                return false;
            });
        });
    </script>
    <div class="wc-actions">
        <a class="button button-primary"
           href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>"><?php esc_html_e( 'Ver métodos de frete',
				'cc-table-shipping' ); ?></a>
    </div>
</div>
