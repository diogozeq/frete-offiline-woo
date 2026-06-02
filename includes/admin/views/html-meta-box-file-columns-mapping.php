<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$attached_file     = get_attached_file( $current );
$bytes             = filesize( $attached_file );
$header            = CCTS_Helper::mapping( $attached_file, $delimiter );

$mapped            = $this->mapped_columns;
$mapped            = ccts_fix_mapped_columns( $mapped, $header );
$errors            = CCTS_Helper::required_fields_missing( $this->mapped_columns );
?>

<?php
if ( $errors ) {
  echo '<p style="display: block; max-width: 700px; font-style: italic; font-size: 18px; margin-bottom: 25px;">Mapear campos significa informar ao plugin quais colunas da sua tabela tem os dados necessários para o cálculo do frete. Apenas a faixa de CEP é obrigatório. O restante é opcional, mas certifique-se de que preencheu todos os dados necessários para que o plugin funcione como você espera.</p>';
}

foreach ( $errors as $group => $erros_list ) {
  echo '<div class="ccts_error ccts_' . $group . '">';
  foreach ( $erros_list as $error ) {
    echo '<p>' . $error . '</p>';
  }
  echo '</div>';
} ?>

<div class="ccts-file-uploader" data-library="all" data-mime_types="" data-uploader="wp">
  <div class="file-wrap">
    <div class="file-icon">
      <img data-name="icon" src="<?php echo wp_mime_type_icon( $current ); ?>" />
    </div>
    <div class="file-info">
      <p>
        <strong data-name="title"><?php echo $attachment->post_title; ?></strong>
      </p>
      <p>
        <strong><?php _e( 'File Name', 'cc-table-shipping' ) ?>:</strong>
        <a data-name="filename" href="<?php echo wp_get_attachment_url( $current ); ?>" target="_blank"><?php echo wp_basename( get_attached_file( $current ) ) ?></a>
      </p>
      <p>
        <strong><?php _e( 'Size', 'cc-table-shipping' ) ?>:</strong>
        <span data-name="filesize">
          <?php
            echo size_format( $bytes );
          ?>
        </span>
      </p>
    </div>
  </div>
</div>

<div class="ccts-mapping">
  <h4><?php _e( 'Map your table columns', 'cc-table-shipping' ) ?></h4>
  <p><?php _e( 'Select the columns to match with your file.', 'cc-table-shipping' ) ?></p>


  <?php
  // verificar se é CSV e se ainda existe/
  if ( is_object( $attachment ) && in_array( $attachment->post_mime_type, array( 'text/csv', 'text/txt', 'text/plain' ) ) && file_exists( $attached_file ) ) {

    $handle = fopen( $attached_file, 'r' );
    $header = fgetcsv( $handle, 0, $delimiter );
  ?>

    <div class="ccts-table-preview-wrapper">
      <h3 class="ccts-table-preview-title"><?php _e( 'Preview da tabela atual', 'cc-table-shipping' ); ?></h3>
      <div class="ccts-table-container">
        <table class="ccts-table-preview">
          <thead>
            <tr>
              <?php foreach ( $header as $column ) {
                echo '<th>' . esc_html( $column ) .'</th>';
              } ?>
            </tr>
          </thead>
          <tbody>
            <?php
            $has_error = false;
            $i = 0;
            while ( ( $i < 5 && $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) { $i++;
              // check if has less columns than required...
              if ( ! $has_error && count( $row ) <= 3 ) {
                $has_error = true;
              }
            ?>
              <tr>
                <?php foreach ( $row as $column ) {
                  echo '<td>' . esc_html( $column ) .'</td>';
                } ?>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php
  if ( $has_error ) {
    _e( '<div class="ccts_error ccts_error"><strong>Há erros na sua tabela</strong>. Veja acima a prévia dela. Geralmente isso indica que ela possui menos colunas que o necessário. Ou seja: ou ela não segue o modelo sugerido ou na hora de criá-la tudo ficou em uma coluna individual, e não em várias. Isso é um erro comum ao editar no Excel se não estiver usando uma versão recente/configurações corretas. Experimente fazê-la no Google Drive e então exportar como CSV.</div>' );
  }


  } ?>

  <?php if ( $header ) {
    $i = 0;
    foreach ( CCTS_Helper::import_columns() as $colum_key => $colum_name ) {
      require 'html-mapping-row.php';
    }

  } else {
    echo '<p>';
    _e( 'Invalid file. Delete this post and try again.', 'cc-table-shipping' );
    echo '</p>';

    return false;
  } ?>

</div>
