<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

$current    = $this->table;
$attachment = get_post( $current );
$file       = get_attached_file( $current );
$delimiter  = $this->delimiter;
?>

<tr valign="top">
    <th scope="row" class="titledesc">
		<?php _e( 'Detalhes do método', 'cc-table-shipping' ); ?><br/>
    </th>
    <td class="forminp" id="<?php echo '' ?>_shipping_methods">

        <div style="overflow: auto; background: #fff;">
            <table class='wp-list-table wcst-post-table wpc-sortable-post-table widefat'>
                <tbody>
                <tr>
                    <td>
                        <div style="padding: 20px; max-width: 700px">
							<?php
							// verificar se é CSV e se ainda existe/
							if ( $current && is_object( $attachment ) && in_array( $attachment->post_mime_type,
									[ 'text/csv', 'text/txt', 'text/plain' ] ) && file_exists( $file ) ) {
								require_once plugin_dir_path( __FILE__ ) . 'html-meta-box-file-columns-mapping.php';

								?>

                                <div style="background: linear-gradient(135deg, #cce5ff 0%, #f2f9ff 100%); border-left: 4px solid #0073aa; padding: 20px; margin: 20px 0; font-family: Arial, sans-serif; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 6px;">
                                    <div style="display: flex; align-items: center;">
                                        <svg style="flex-shrink: 0; margin-right: 15px;" width="40" height="40"
                                             fill="#0073aa" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 2C11.4477 2 11 2.44772 11 3V14.5858L7.70711 11.2929C7.31658 10.9024 6.68342 10.9024 6.29289 11.2929C5.90237 11.6834 5.90237 12.3166 6.29289 12.7071L11.2929 17.7071C11.6834 18.0976 12.3166 18.0976 12.7071 17.7071L17.7071 12.7071C18.0976 12.3166 18.0976 11.6834 17.7071 11.2929C17.3166 10.9024 16.6834 10.9024 16.2929 11.2929L13 14.5858V3C13 2.44772 12.5523 2 12 2Z"/>
                                            <path d="M5 18C4.44772 18 4 18.4477 4 19V20C4 20.5523 4.44772 21 5 21H19C19.5523 21 20 20.5523 20 20V19C20 18.4477 19.5523 18 19 18H5Z"/>
                                        </svg>

                                        <!-- Text content -->
                                        <div>
                                            <h2 style="margin: 0 0 10px; color: #0073aa;">
                                                Importar tabela para o Banco de Dados
                                            </h2>
                                            <p style="font-size: 16px; color: #333; margin: 0;">
												<?php echo __( 'É possível melhorar muito a performance do plugin ao importar a tabela para o banco de dados, e não ler direto do CSV. Na maioria dos casos o impacto não é tão grande, mas em tabelas com centenas de milhares de linhas pode haver uma redução de até 90% do tempo de processamento. Experimente, é grátis!',
													'cc-table-shipping' ); ?>
                                            </p>
                                        </div>
                                    </div>

									<?php
									// Check if there are imported rates for this instance
									global $wpdb;
									$instance_id    = isset( $_GET['instance_id'] ) ? absint( $_GET['instance_id'] ) : 0;
									$table_name     = $wpdb->prefix . 'ccts_rates';
									$imported_count = $wpdb->get_var( $wpdb->prepare(
										"SELECT COUNT(*) FROM {$table_name} WHERE instance_id = %d",
										$instance_id
									) );

									// Check if database mode is enabled
									$use_database = $this->get_option( 'use_database', 'no' );
									?>

                                    <!-- Buttons -->
                                    <div style="display:flex; gap: 15px; margin-top: 15px; flex-wrap: wrap;">
										<?php if ( $imported_count > 0 ): ?>
                                            <!-- Database Toggle Form -->
                                            <div style="display: flex; align-items: center; background: #f8f9fa; padding: 10px 15px; border-radius: 4px; border: 1px solid #ddd;">
                                                <label style="margin: 0; display: flex; align-items: center; cursor: pointer;">
                                                    <input type="checkbox" name="use_database" value="yes"
														<?php checked( $use_database, 'yes' ); ?>
                                                           style="margin-right: 8px;">
                                                    <span style="font-weight: 500;">
															<?php esc_html_e( 'Usar Banco de Dados',
																'cc-table-shipping' ); ?>
                                                        </span>
                                                </label>
                                                <span style="margin-left: 10px; color: #666; font-size: 14px;">
														(<?php echo number_format( $imported_count ); ?> registros)
													</span>
                                            </div>
										<?php endif; ?>

                                        <!-- Import Button -->
                                        <a href="<?php echo esc_url(
											wp_nonce_url( admin_url( 'admin.php?page=cc-table-shipping-import&instance_id=' . esc_attr( $instance_id ) ),
												'cc-table-shipping-csv-importer' ) ); ?>"
                                           style="display: inline-block; background-color: #0073aa; color: #fff; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; text-decoration: none;">
											<?php if ( $imported_count > 0 ): ?>
												<?php esc_html_e( 'Reimportar Tabela', 'cc-table-shipping' ); ?>
											<?php else: ?>
												<?php esc_html_e( 'Iniciar Importação', 'cc-table-shipping' ); ?>
											<?php endif; ?>
                                        </a>
                                    </div>

									<?php if ( $imported_count > 0 && $use_database === 'yes' ): ?>
                                        <div style="margin-top: 10px; padding: 8px 12px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; font-size: 14px;">
                                            <strong>✓ Modo Banco de Dados Ativo:</strong>
                                            O plugin está usando o banco de dados para melhor performance.
                                        </div>
									<?php elseif ( $imported_count > 0 ): ?>
                                        <div style="margin-top: 10px; padding: 8px 12px; background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; border-radius: 4px; font-size: 14px;">
                                            <strong>⚠ Modo CSV Ativo:</strong>
                                            Ative o modo banco de dados acima para melhor performance.
                                        </div>
									<?php endif; ?>
                                </div>

								<?php

								echo '<h1>Atualizar tabela</h1>';
								echo '<p class="description">Você pode fazer upload de uma nova tabela para substituir a atual. Isso irá manter os IDs e o mapeamento das colunas. Se você mudou alguma a ordem das colunas, certifique-se de mapeá-las outra vez.</p>';
							}

							require_once plugin_dir_path( __FILE__ ) . 'html-meta-box-file-file-upload.php';
							?>
                        </div>
                    </td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                    <th colspan='2' style='padding-left: 10px;'>
                        <?php esc_html_e( 'Tabela de Frete Offline (Claude Code)', 'cc-table-shipping' ); ?>
                        &mdash; <a href="https://github.com/diogozeq/frete-offiline-woo" target="_blank">github.com/diogozeq/frete-offiline-woo</a>
                    </th>
                </tr>
                </tfoot>
            </table>
        </div>
    </td>
</tr>
