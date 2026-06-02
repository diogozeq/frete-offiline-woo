<?php $alt = ( $i++ ) % 2 == 0 ? 'alternate' : ''; ?>
<div class="map-row <?php echo $alt; ?>">
  <label for="ccts_mapped_columns[<?php echo $colum_key; ?>]"><?php echo $colum_name; ?></label>
  <select name="<?php printf( '%s[%s]', $this->mapping_field_key, $colum_key ); ?>" id="ccts_mapped_columns[<?php echo $colum_key; ?>]">
    <option value="-1" <?php selected( $mapped[ $colum_key ], -1 ); ?>><?php echo CCTS_Helper::column_placeholder( $colum_key ); ?></option>

    <?php foreach ( $header as $header_key => $header_name ) { ?>
      <option value="<?php echo $header_key; ?>" <?php selected( $mapped[ $colum_key ], $header_key ); ?>><?php echo $header_name; ?></option>
    <?php } ?>
  </select>
</div>
