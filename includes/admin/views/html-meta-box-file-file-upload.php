<?php
/**
 * Conditions table.
 *
 * Display table with all the user configured Table Shipping conditions.
 *
 * @author    Fernando Acosta
 * @package   WC Table Shipping
 * @version   1.0.0
 */

if ( ! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

?>
<div class="ccts_success"><?php _e('Looking for an Example Table?', 'cc-table-shipping'); ?> <a
            href="https://ajuda.fernandoacosta.net/cc-table-shipping/sample"
            target="_blank"><?php _e('Entenda aqui', 'cc-table-shipping'); ?></a>.
</div>
<div class="ccts_success ccts_warning"><?php _e('If you have a XLS file, you can convert it', 'cc-table-shipping'); ?>
    <a href="https://convertio.co/pt/conversor-csv" target="_blank"><?php _e('here', 'cc-table-shipping'); ?></a>.
</div>

<?php echo sprintf('<p class="ccts-upload-wrapper">%1$s<input class="button ccts-file-upload-button ccts-upload-button" type="button" value="%2$s" /></p>',
    __('No file selected', 'cc-table-shipping'), __('Select file', 'cc-table-shipping')); ?>

<div class="ccts-file-uploader hidden" data-library="all" data-mime_types="" data-uploader="wp">
    <div class="file-wrap">
        <div class="file-icon">
            <img data-name="icon" src=""/>
        </div>
        <div class="file-info">
            <p>
                <strong data-name="title"></strong>
            </p>
            <p>
                <strong><?php _e('File Name', 'cc-table-shipping') ?>:</strong>
                <a data-name="filename" href="" target="_blank"></a>
            </p>
            <p>
                <strong><?php _e('Size', 'cc-table-shipping') ?>:</strong>
                <span data-name="filesize"></span>
            </p>
        </div>
    </div>
</div>

<div class="ccts_error invalid-file hidden">
    <?php _e('O Arquivo precisa ser <code>.csv</code>. Existem alguns motivos que podem causar este problema. Dentre eles:',
        'cc-table-shipping'); ?>
    <ul>
        <li>O arquivo não está no formato CSV.</li>
        <li>O arquivo não tem o tipo interno adequado. Isso pode acontecer quando você mexe no arquivo no Excel e na
            hora de salvar ele tem o tipo <code>.csv</code> mas o formato real é do Excel. Para resolver isso, copie
            todos os seus dados para o Google Drive e exporte como CSV
        </li>
        <li>Outros motivos: entre em contato para obter assistência: ajuda.fernandoacosta.net</li>
    </ul>
</div>

<div class="ccts_error refresh-page-to-see-results hidden">
    <?php _e('Publish this settings to map your columns.', 'cc-table-shipping'); ?> <a href="#nogo"
                                                                                       onClick="window.location.reload()"><?php _e('To cancel, clique here.',
            'cc-table-shipping'); ?></a>
</div>

<?php
/**
 * "Cole sua planilha aqui" — paste grid alternativa ao upload de arquivo.
 * Gera um CSV no servidor e o injeta no mesmo campo "table_rate".
 */
$ccts_paste_cols = [
    __( 'CEP Inicial', 'cc-table-shipping' ),
    __( 'CEP Final', 'cc-table-shipping' ),
    __( 'Peso Inicial', 'cc-table-shipping' ),
    __( 'Peso Final', 'cc-table-shipping' ),
    __( 'Custo', 'cc-table-shipping' ),
    __( 'Prazo de Entrega', 'cc-table-shipping' ),
    __( 'Nome do Método', 'cc-table-shipping' ),
];
$ccts_paste_rows = 12;
?>
<div class="ccts-paste-card" id="ccts-paste-card">
    <div class="ccts-paste-head">
        <span class="ccts-paste-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 2H9a1 1 0 0 0-1 1v2c0 .6.4 1 1 1h6c.6 0 1-.4 1-1V3c0-.6-.4-1-1-1Z"></path>
                <path d="M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2M16 4h2a2 2 0 0 1 2 2v2M11 14h10"></path>
                <path d="m17 10 4 4-4 4"></path>
            </svg>
        </span>
        <div>
            <h3><?php esc_html_e( 'Cole sua planilha aqui', 'cc-table-shipping' ); ?></h3>
            <p>
                <?php
                echo wp_kses_post( __( 'Selecione e copie as colunas no Excel/Google Sheets, clique em qualquer célula abaixo e cole (<kbd>Ctrl+V</kbd>). O conteúdo é distribuído automaticamente nas células. Você também pode digitar manualmente. Ao processar, geramos a tabela CSV e ela já entra no método — basta salvar.', 'cc-table-shipping' ) );
                ?>
            </p>
        </div>
    </div>

    <div class="ccts-paste-grid-wrap">
        <table class="ccts-paste-grid" id="ccts-paste-grid">
            <thead>
                <tr>
                    <th class="ccts-paste-rownum">#</th>
                    <?php foreach ( $ccts_paste_cols as $i => $label ) : ?>
                        <th><?php echo esc_html( $label ); ?><?php echo ( 0 === $i || 1 === $i || 4 === $i ) ? ' <span class="ccts-req">*</span>' : ''; ?></th>
                    <?php endforeach; ?>
                    <th class="ccts-paste-rowdel"></th>
                </tr>
            </thead>
            <tbody>
                <?php for ( $r = 0; $r < $ccts_paste_rows; $r++ ) : ?>
                    <tr>
                        <td class="ccts-paste-rownum"><?php echo (int) ( $r + 1 ); ?></td>
                        <?php foreach ( $ccts_paste_cols as $c => $label ) : ?>
                            <td><input type="text" spellcheck="false" autocomplete="off"
                                       data-row="<?php echo (int) $r; ?>" data-col="<?php echo (int) $c; ?>"
                                       class="ccts-paste-cell"/></td>
                        <?php endforeach; ?>
                        <td class="ccts-paste-rowdel">
                            <button type="button" class="ccts-paste-del" title="<?php esc_attr_e( 'Remover linha', 'cc-table-shipping' ); ?>">&times;</button>
                        </td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="ccts-paste-actions">
        <div class="ccts-paste-actions-left">
            <button type="button" class="button" id="ccts-paste-add-row">+ <?php esc_html_e( 'Adicionar linha', 'cc-table-shipping' ); ?></button>
            <button type="button" class="button" id="ccts-paste-clear"><?php esc_html_e( 'Limpar grade', 'cc-table-shipping' ); ?></button>
            <span class="ccts-paste-count" id="ccts-paste-count">0 <?php esc_html_e( 'linha(s) preenchida(s)', 'cc-table-shipping' ); ?></span>
        </div>
        <button type="button" class="button button-primary" id="ccts-paste-process"><?php esc_html_e( 'Processar planilha', 'cc-table-shipping' ); ?></button>
    </div>
    <div class="ccts-paste-feedback" id="ccts-paste-feedback" style="display:none;"></div>
</div>
