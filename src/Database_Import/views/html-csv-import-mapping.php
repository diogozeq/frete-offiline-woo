<?php
/**
 * Admin View: Table Shipping Import - Column mapping
 *
 * @package CC_Table_Shipping\Database_Import
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shipping_method = WC_Shipping_Zones::get_shipping_method( $this->instance_id );
$mapped_columns  = $this->get_current_mapping();
$headers         = $this->get_csv_headers();
$mapped_columns  = ccts_fix_mapped_columns( $mapped_columns, $headers );
$errors          = CCTS_Helper::required_fields_missing( $mapped_columns );
?>
<form class="wc-progress-form-content woocommerce-importer" enctype="multipart/form-data" method="post">
    <header>
        <h2><?php esc_html_e( 'Mapear colunas da tabela', 'cc-table-shipping' ); ?></h2>
        <p><?php esc_html_e( 'Selecione as colunas que correspondem aos dados na sua tabela CSV.',
				'cc-table-shipping' ); ?></p>

		<?php if ( ! empty( $errors ) ): ?>
            <div class="notice notice-warning">
                <p><strong><?php esc_html_e( 'Atenção:', 'cc-table-shipping' ); ?></strong></p>
				<?php foreach ( $errors as $group => $error_list ): ?>
					<?php foreach ( $error_list as $error ): ?>
                        <p><?php echo wp_kses_post( $error ); ?></p>
					<?php endforeach; ?>
				<?php endforeach; ?>
            </div>
		<?php endif; ?>
    </header>

    <section>
		<?php if ( ! empty( $headers ) ): ?>
            <h3><?php esc_html_e( 'Mapeamento das colunas', 'cc-table-shipping' ); ?></h3>
            <table class="wp-list-table widefat striped">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Campo do plugin', 'cc-table-shipping' ); ?></th>
                    <th><?php esc_html_e( 'Coluna da tabela', 'cc-table-shipping' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'cc-table-shipping' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php
				$i                   = 0;
				foreach ( CCTS_Helper::import_columns() as $column_key => $column_name ):
					$is_required = in_array( $column_key, [ 'zipcode_start', 'zipcode_end' ] );
					$current_mapping = isset( $mapped_columns[ $column_key ] ) ? $mapped_columns[ $column_key ] : - 1;
					$alt             = ( $i ++ ) % 2 == 0 ? 'alternate' : '';
					?>
                    <tr class="<?php echo esc_attr( $alt ); ?>">
                        <td>
                            <label for="mapped_columns_<?php echo esc_attr( $column_key ); ?>">
                                <strong><?php echo esc_html( $column_name ); ?></strong>
								<?php if ( $is_required ): ?>
                                    <span style="color: red;">*</span>
								<?php endif; ?>
                            </label>
                        </td>
                        <td>
                            <select name="mapped_columns[<?php echo esc_attr( $column_key ); ?>]"
                                    id="mapped_columns_<?php echo esc_attr( $column_key ); ?>">
                                <option value="-1" <?php selected( $current_mapping, - 1 ); ?>>
									<?php echo esc_html( CCTS_Helper::column_placeholder( $column_key ) ); ?>
                                </option>
								<?php foreach ( $headers as $header_key => $header_name ): ?>
                                    <option value="<?php echo esc_attr( $header_key ); ?>"
										<?php selected( $current_mapping, $header_key ); ?>>
										<?php echo esc_html( $header_name ); ?>
                                    </option>
								<?php endforeach; ?>
                            </select>
                        </td>
                        <td>
							<?php if ( $current_mapping != - 1 && isset( $headers[ $current_mapping ] ) ): ?>
                                <span style="color: green;">✓ Mapeado</span>
							<?php elseif ( $is_required ): ?>
                                <span style="color: red;">⚠ Obrigatório</span>
							<?php else: ?>
                                <span style="color: #666;">○ Opcional</span>
							<?php endif; ?>
                        </td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 10px;">
                <small><span style="color: red;">*</span> <?php esc_html_e( 'Campos obrigatórios',
						'cc-table-shipping' ); ?></small>
            </p>

		<?php else: ?>
            <div class="notice notice-error">
                <p><?php esc_html_e( 'Não foi possível ler o cabeçalho da tabela. Verifique se o arquivo é um CSV válido.',
						'cc-table-shipping' ); ?></p>
            </div>
		<?php endif; ?>
    </section>

    <div class="wc-actions">
		<?php $can_proceed = empty( $errors['error'] ); ?>
        <button type="submit" class="button button-primary button-next"
                value="<?php esc_attr_e( 'Continuar para importação', 'cc-table-shipping' ); ?>"
                name="save_step"
			<?php echo ! $can_proceed ? 'disabled' : ''; ?>>
			<?php esc_html_e( 'Continuar para importação', 'cc-table-shipping' ); ?>
        </button>
		<?php wp_nonce_field( 'cc-table-shipping-csv-importer' ); ?>
    </div>
</form>

<script>
    jQuery(document).ready(function ($) {
        // Update mapping status when selections change
        $('select[name^="mapped_columns"]').on('change', function () {
            validateMappings();
        });

        function validateMappings() {
            var errors = [];
            var allRequired = true;

            // Get all mappings
            var zipcode_start = $('select[name="mapped_columns[zipcode_start]"]').val();
            var zipcode_end = $('select[name="mapped_columns[zipcode_end]"]').val();
            var weight_start = $('select[name="mapped_columns[weight_start]"]').val();
            var weight_end = $('select[name="mapped_columns[weight_end]"]').val();

            // Check each field and update status
            $('select[name^="mapped_columns"]').each(function () {
                var row = $(this).closest('tr');
                var statusCell = row.find('td:last-child');
                var value = $(this).val();
                var fieldName = $(this).attr('name').match(/\[(.+)\]/)[1];
                var isRequired = $(this).closest('tr').find('span[style*="color: red"]').length > 0;

                if (value != -1) {
                    statusCell.html('<span style="color: green;">✓ Mapeado</span>');
                } else if (isRequired) {
                    statusCell.html('<span style="color: red;">⚠ Obrigatório</span>');
                    allRequired = false;
                } else {
                    statusCell.html('<span style="color: #666;">○ Opcional</span>');
                }
            });

            // NOVA VALIDAÇÃO 1: CEP inicial não pode ser igual ao CEP final
            if (zipcode_start != -1 && zipcode_end != -1 && zipcode_start == zipcode_end) {
                errors.push('CEP inicial não pode usar a mesma coluna do CEP final');
                $('select[name="mapped_columns[zipcode_start]"]').closest('tr').find('td:last-child')
                    .html('<span style="color: red;">⚠ Mesma coluna do CEP final</span>');
                $('select[name="mapped_columns[zipcode_end]"]').closest('tr').find('td:last-child')
                    .html('<span style="color: red;">⚠ Mesma coluna do CEP inicial</span>');
                allRequired = false;
            }

            // NOVA VALIDAÇÃO 2: Peso inicial não pode ser igual ao peso final (se ambos preenchidos)
            if (weight_start != -1 && weight_end != -1 && weight_start == weight_end) {
                errors.push('Peso inicial não pode usar a mesma coluna do peso final');
                $('select[name="mapped_columns[weight_start]"]').closest('tr').find('td:last-child')
                    .html('<span style="color: red;">⚠ Mesma coluna do peso final</span>');
                $('select[name="mapped_columns[weight_end]"]').closest('tr').find('td:last-child')
                    .html('<span style="color: red;">⚠ Mesma coluna do peso inicial</span>');
                allRequired = false;
            }

            // Show/hide error messages
            $('.mapping-errors').remove();
            if (errors.length > 0) {
                var errorHtml = '<div class="notice notice-error mapping-errors" style="margin: 10px 0;"><p><strong>Erros no mapeamento:</strong></p><ul>';
                errors.forEach(function(error) {
                    errorHtml += '<li>' + error + '</li>';
                });
                errorHtml += '</ul></div>';
                $('.wc-actions').before(errorHtml);
            }

            // Enable/disable continue button
            $('.button-next').prop('disabled', !allRequired);
        }

        // Run initial validation
        validateMappings();
    });
</script>
