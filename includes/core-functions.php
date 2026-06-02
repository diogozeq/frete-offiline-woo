<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

function ccts_get_available_rates( $file_id = false, $package = [], $method = false ) {
	$file = apply_filters( 'ccts_attached_file', get_attached_file( $file_id ), $package, $method );

	if ( ! $file || ! file_exists( $file ) ) {
		return false;
	}

	return $handle = fopen( $file, "r" );
}


function ccts_matched_rules( $row, $package, $method, $extra_data = [], $package_weight = null ) {
	$instance_id     = $method->instance_id;
	$method_title    = $method->method_name;
	$additional_time = isset( $method->additional_time ) ? $method->additional_time : '';
	$weight_unit     = $method->weight_unit;
	$extra_weight    = $method->extra_weight;
	$map             = $method->mapped_columns;

	// não há como checar se não for um array
	if ( ! is_array( $map ) ) {
		ccts_log( 'O mapeamento da regra #' . $instance_id . ' é inválido.', $method->debugging );

		return false;
	}

	$method_name = isset( $map['label'], $row[ $map['label'] ] ) ? $row[ $map['label'] ] : '';

	// não é possível prosseguir sem um nome para o método de envio
	if ( '' === $method_title ) {
		$method_title = __( 'Free Shipping', 'cc-table-shipping' );
	}

	// CEP do cliente é obrigatório
	if ( ! isset( $package['destination']['postcode'] ) ) {
		ccts_log( 'CEP de destino inválido!', $method->debugging );

		return false;
	}

	// verifica se algum mapeamento não foi definido
	if ( ! isset( $map['zipcode_start'], $map['zipcode_end'], $map['weight_start'], $map['weight_end'], $map['cost'], $map['delivery_time'], $row[ $map['zipcode_start'] ], $row[ $map['zipcode_end'] ] ) ) {
		ccts_log( 'O mapeamento da regra #' . $instance_id . ' é inválido, nem todos os campos estão definidos. Verifique sua tabela e tente novamente.',
			$method->debugging );

		return false;
	}

	$postcode = ccts_sanitize_postcode( $package['destination']['postcode'] );

	/** Allow third part plugins to validate a rule */
	$check = apply_filters( 'ccts_pre_rules', null, $row, $package, $method, $extra_data );
	if ( null !== $check ) {
		ccts_log( 'def23456432', $method->debugging );

		return $check;
	}

	// Os CEPS de início e fim são obrigatórios
	if ( '-1' === $map['zipcode_start'] || '-1' === $map['zipcode_end'] ) {
		ccts_log( 'O mapeamento da regra #' . $instance_id . ' é inválido. É obrigatório definir CEP inicial e CEP Final.',
			$method->debugging );

		return false;
	}

	// se o CEP é menor que o CEP inicial, não passa
	if ( $postcode < ccts_sanitize_postcode( $row[ $map['zipcode_start'] ] ) ) {
		return false;
	}

	// se o CEP é maior que o CEP final, não passa
	if ( $postcode > ccts_sanitize_postcode( $row[ $map['zipcode_end'] ] ) ) {
		return false;
	}

	ccts_log( 'CEP ' . $postcode . ' é válido na faixa ' . $row[ $map['zipcode_start'] ] . '-' . $row[ $map['zipcode_end'] ],
		$method->debugging );

	// Use pre-calculated weight if provided, otherwise calculate it
	if ( null === $package_weight ) {
		$package_weight = ccts_get_package_weight( $package, $weight_unit, $extra_weight, $method, $extra_data );
	}

	if ( isset( $row[ $map['weight_start'] ] ) ) {
		$row[ $map['weight_start'] ] = str_replace( ',', '.', $row[ $map['weight_start'] ] );
	}

	if ( isset( $row[ $map['weight_end'] ] ) ) {
		$row[ $map['weight_end'] ] = str_replace( ',', '.', $row[ $map['weight_end'] ] );
	}

	// se o peso do pacote é menor que o peso inicial, não passa
	if ( '-1' !== $map['weight_start'] && $package_weight < $row[ $map['weight_start'] ] ) {
		ccts_log( 'Peso inválido! Pedido: ' . $package_weight . '. Mínimo da Faixa: ' . $row[ $map['weight_start'] ],
			$method->debugging );

		return false;
	}

	// se o peso do pacote é maior que o peso final, não passa
	if ( '-1' !== $map['weight_end'] && $package_weight > $row[ $map['weight_end'] ] ) {

		if ( ! apply_filters( 'ccts_invalid_rule', false, $package, $method, $row, $map ) ) {
			ccts_log( 'Peso inválido! Pedido: ' . $package_weight . '. Máximo da Faixa: ' . $row[ $map['weight_end'] ],
				$method->debugging );

			return false;
		}
	}

	// se há regras para peso mínimo e o peso mínimo é maior que zero, não passa, já que ele está na regra anterior
	if ( '-1' !== $map['weight_start'] && 0 < $row[ $map['weight_start'] ] && $package_weight == $row[ $map['weight_start'] ] && apply_filters( 'ccts_ignore_weight_rule',
			true ) ) {
		return false;
	}

	if ( '' !== $additional_time && is_numeric( $additional_time ) ) {
		$delivery_time = '-1' === $map['delivery_time'] ? $additional_time : ( intval( $row[ $map['delivery_time'] ] ) + intval( $additional_time ) );
	} else {
		$delivery_time = '-1' === $map['delivery_time'] ? $additional_time : $row[ $map['delivery_time'] ];
	}

	// nunca zero
	if ( 1 > $delivery_time ) {
		$delivery_time = 1;
	}

	$method_title = str_replace( '{name}', $method_name, $method_title );

	// custo padrão
	$cost = floatval( '-1' === $map['cost'] ? 0 : str_replace( ',', '.', $row[ $map['cost'] ] ) );

	// possibilidade de oferecer frete grátis a partir da regra definida
	// precisa existir o mapa e também precisa que a coluna exista na tabela
	if ( isset( $map['free_shipping'], $row[ $map['free_shipping'] ] ) && '-1' !== $map['free_shipping'] && ! empty( $row[ $map['free_shipping'] ] ) ) {
		$cost = $row[ $map['free_shipping'] ] <= apply_filters( 'ccts_cart_contents_cost', $package['contents_cost'],
			false ) ? 0 : $cost;

		$cost = apply_filters( 'cc_table_shipping_free_shipping_matched', $cost, $postcode, $instance_id, $map, $row,
			$package );
	}

	if ( '-1' !== $map['weight_start'] && 0 < $row[ $map['weight_start'] ] && $package_weight == $row[ $map['weight_start'] ] && apply_filters( 'ccts_ignore_weight_rule',
			true ) ) {
		return false;
	}


	/**
	 * Permitir que plugins filtrem o resultado a partir de colunas personalizadas
	 */
	if ( true !== ( $custom_rules = apply_filters( 'ccts_custom_rules', true, $instance_id, $map, $row, $package ) ) ) {
		ccts_log( 'Uma regra personalizada não foi validada: ' . $custom_rules, $method->debugging );

		return false;
	}

	$rate = [
		'id'            => $instance_id,
		'key'           => apply_filters( 'cc_table_shipping_method_key',
			'ccts_' . $instance_id . '_' . md5( json_encode( $row ) ), $instance_id, $map, $row ),
		'label'         => $method_title,
		'cost'          => $cost,
		'delivery_time' => $delivery_time,
		'row'           => $row,
		'map'           => $map,
		'meta_data'     => [
			'delivery_time' => $delivery_time,
		],
		'extra_data'    => $extra_data,
	];

	ccts_log( print_r( [ 'Postcode destination' => $postcode, 'Rate' => $rate, 'Row' => $row ], true ),
		$method->debugging );

	return $rate;
}


function ccts_get_package_weight( $package, $weight_unit = 'g', $extra_weight = 0, $method = false, $extra_data = [] ) {
	$weight      = 0;
	$weight_unit = apply_filters( 'cc_table_shipping_weight_unit', $weight_unit, $package );

	foreach ( $package['contents'] as $key => $values ) {
		$weight += (float) $values['data']->get_weight() * (float) $values['quantity'];
	}

	$extra_weight = str_replace( ',', '.', $extra_weight );

	$weight = $weight + (float) $extra_weight;

	$weight = apply_filters( 'cc_table_shipping_weight', wc_get_weight( $weight, $weight_unit ), $package,
		$extra_weight,
		$weight_unit, $method, $extra_data );

	return $weight;
}


function ccts_get_delimiter() {
	return apply_filters( 'cc_table_shipping_delimiter', ',' );
}


function ccts_fix_mapped_columns( $mapped, $header = [] ) {
	$columns = CCTS_Helper::import_columns();
	foreach ( array_keys( $columns ) as $column ) {
		if ( ! isset( $mapped[ $column ] ) ) {
			$default           = CCTS_Helper::default_column_values( $column );
			$mapped[ $column ] = array_search( $default, $header );
		}
	}

	return $mapped;
}


/**
 * Sanitize postcode.
 *
 * @param  string  $postcode  Postcode.
 *
 * @return string
 */
function ccts_sanitize_postcode( $postcode ) {
	return preg_replace( '([^0-9])', '', sanitize_text_field( $postcode ) );
}

function ccts_log( $message, $enabled = 'no' ) {
	if ( 'yes' === $enabled ) {
		$log = new WC_Logger();
		$log->add( 'cc-table-shipping', $message );
	}
}


add_action( 'admin_enqueue_scripts', 'ccts_enqueue_scripts' );
function ccts_enqueue_scripts() {
	global $current_screen;

	if ( 'woocommerce_page_wc-settings' !== $current_screen->base ) {
		return;
	}

	// file field key
	$field_key = 'woocommerce_cc_table_shipping_table_rate';

	// Media Upload.
	wp_enqueue_media( [] );

	wp_enqueue_style( 'ccts-admin', plugins_url( 'assets/css/admin.css', CC_Table_Shipping::get_main_file() ), [],
		'1.3.5' );
	wp_register_script( 'ccts-admin', plugins_url( 'assets/js/admin.js', CC_Table_Shipping::get_main_file() ),
		[ 'jquery' ],
		'1.3.7', true );

	if ( ! wp_script_is( 'ccts-admin', 'enqueued' ) ) {
		wp_enqueue_script( 'ccts-admin' );

		// Localize strings.
		wp_localize_script(
			'ccts-admin',
			'cctsAdminParams',
			[
				'uploadTitle'  => __( 'Choose a table', 'cc-table-shipping' ),
				'uploadButton' => __( 'Select Table', 'cc-table-shipping' ),
				'uploadInput'  => sprintf( '#%s', $field_key ),
			]
		);
	}

	// Paste-grid script (colar planilha direto na tela).
	wp_register_script( 'ccts-paste-grid',
		plugins_url( 'assets/js/ccts-paste-grid.js', CC_Table_Shipping::get_main_file() ),
		[ 'jquery', 'ccts-admin' ], '1.0.0', true );
	wp_enqueue_script( 'ccts-paste-grid' );
	wp_localize_script(
		'ccts-paste-grid',
		'cctsPasteParams',
		[
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'ccts_paste_to_csv' ),
			'fieldKey'   => $field_key,
			'i18nEmpty'  => __( 'Cole ou preencha ao menos uma linha com CEP inicial, CEP final e custo.', 'cc-table-shipping' ),
			'i18nError'  => __( 'Não foi possível gerar a tabela. Tente novamente.', 'cc-table-shipping' ),
			'i18nDone'   => __( 'Planilha processada! Salvando...', 'cc-table-shipping' ),
		]
	);
}

add_filter( 'woocommerce_shipping_rate_label', 'ccts_override_delivery_time_label', 5, 2 );
add_filter( 'woocommerce_cart_shipping_method_full_label', 'ccts_override_delivery_time_label', 5, 2 );
function ccts_override_delivery_time_label( $label, $method ) {
	$meta_data = $method->get_meta_data();

	if ( isset( $meta_data['is_ccts'], $meta_data['delivery_time'] ) ) {
		$label = str_replace( '{delivery_time}', $meta_data['delivery_time'], $label );
	}

	return $label;
}

add_filter( 'woocommerce_order_item_get_method_title', 'ccts_override_item_get_method_title', 10, 2 );
function ccts_override_item_get_method_title( $label, $method ) {
	if ( $method->get_meta( 'is_ccts' ) && $method->get_meta( 'delivery_time' ) ) {
		$label = str_replace( '{delivery_time}', $method->get_meta( 'delivery_time' ), $label );
	}

	return $label;
}

add_filter( 'wc_simulador_frete_method_label', 'ccts_shipping_simulator', 10, 3 );
function ccts_shipping_simulator( $label, $method_key, $method ) {
	return ccts_override_delivery_time_label( $label, $method );
}

add_filter( 'woocommerce_order_shipping_method', 'ccts_order_shipping_method', 300, 2 );
function ccts_order_shipping_method( $names, $order ) {
	if ( false === strpos( $names, '{delivery_time}' ) ) {
		return $names;
	}

	foreach ( $order->get_shipping_methods() as $shipping_method ) {
		$name = $shipping_method->get_name();
		if ( false !== strpos( $name, '{delivery_time}' ) ) {
			$new_name = str_replace( '{delivery_time}', $shipping_method->get_meta( 'delivery_time' ), $name );

			$names = str_replace( $name, $new_name, $names );
		}
	}

	return $names;
}

/**
 * Match rules from database record
 *
 * @param array $rate_data Database record
 * @param array $package Package data
 * @param object $method Method instance
 * @param array $extra_data Extra data
 * @return array|false
 */
function ccts_matched_rules_from_database( $rate_data, $package, $method, $extra_data = [], $package_weight = null ) {
	$instance_id     = $method->instance_id;
	$method_title    = $method->method_name;
	$additional_time = isset( $method->additional_time ) ? $method->additional_time : '';

	// não é possível prosseguir sem um nome para o método de envio
	if ( '' === $method_title ) {
		$method_title = __( 'Free Shipping', 'cc-table-shipping' );
	}

	// CEP do cliente é obrigatório
	if ( ! isset( $package['destination']['postcode'] ) ) {
		ccts_log( 'CEP de destino inválido!', $method->debugging );
		return false;
	}

	$postcode = ccts_sanitize_postcode( $package['destination']['postcode'] );

	/** Allow third part plugins to validate a rule */
	$check = apply_filters( 'ccts_pre_rules', null, $rate_data, $package, $method, $extra_data );
	if ( null !== $check ) {
		ccts_log( 'def23456432', $method->debugging );
		return $check;
	}

	// Database already filtered by postcode and weight, so we just need to validate other conditions
	ccts_log( 'CEP ' . $postcode . ' é válido na faixa ' . $rate_data['postcode_start'] . '-' . $rate_data['postcode_end'], $method->debugging );

	if ( '' !== $additional_time && is_numeric( $additional_time ) ) {
		$delivery_time = intval( $rate_data['delivery_time'] ) + intval( $additional_time );
	} else {
		$delivery_time = $rate_data['delivery_time'];
	}

	// nunca zero
	if ( 1 > $delivery_time ) {
		$delivery_time = 1;
	}

	// Use label from database if available, otherwise use method title
	$label = ! empty( $rate_data['label'] ) ? $rate_data['label'] : $method_title;
	$method_title = str_replace( '{name}', $label, $method_title );

	// custo padrão
	$cost = floatval( $rate_data['cost'] );

	// Check custom rules for database
	if ( true !== ( $custom_rules = apply_filters( 'ccts_custom_rules_database', true, $instance_id, $rate_data, $package ) ) ) {
		ccts_log( 'Uma regra personalizada não foi validada: ' . $custom_rules, $method->debugging );
		return false;
	}

	$rate = [
		'id'            => $instance_id,
		'key'           => apply_filters( 'cc_table_shipping_method_key',
			'ccts_' . $instance_id . '_' . md5( json_encode( $rate_data ) ), $instance_id, $rate_data ),
		'label'         => $method_title,
		'cost'          => $cost,
		'delivery_time' => $delivery_time,
		'row'           => $rate_data,
		'meta_data'     => [
			'delivery_time' => $delivery_time,
		],
		'extra_data'    => $extra_data,
	];

	ccts_log( print_r( [ 'Postcode destination' => $postcode, 'Rate' => $rate, 'Database Row' => $rate_data ], true ), $method->debugging );

	return $rate;
}


/**
 * Default header used when generating a CSV from a pasted spreadsheet.
 *
 * Matches the example table so the column mapping behaves like a normal upload.
 *
 * @return array
 */
function ccts_paste_csv_header() {
	return apply_filters( 'ccts_paste_csv_header', [
		'CEP Inicial',
		'CEP Final',
		'Peso Inicial',
		'Peso Final',
		'Custo',
		'Prazo de Entrega',
		'Nome do Método',
	] );
}

/**
 * AJAX: turn a pasted/typed spreadsheet into a CSV attachment.
 *
 * Receives the grid rows, builds a CSV file, stores it as a private
 * attachment and returns the attachment data so the JS can plug it into
 * the existing "table_rate" field exactly like a media-library upload.
 */
add_action( 'wp_ajax_ccts_paste_to_csv', 'ccts_ajax_paste_to_csv' );
function ccts_ajax_paste_to_csv() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( [ 'message' => __( 'Permissão insuficiente.', 'cc-table-shipping' ) ] );
	}

	check_ajax_referer( 'ccts_paste_to_csv', 'nonce' );

	$raw_rows = isset( $_POST['rows'] ) ? json_decode( wp_unslash( $_POST['rows'] ), true ) : [];

	if ( ! is_array( $raw_rows ) || empty( $raw_rows ) ) {
		wp_send_json_error( [ 'message' => __( 'Nenhuma linha recebida.', 'cc-table-shipping' ) ] );
	}

	$header  = ccts_paste_csv_header();
	$columns = count( $header );
	$rows    = [];

	foreach ( $raw_rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		// Normalize to the exact number of columns.
		$row = array_slice( array_pad( $row, $columns, '' ), 0, $columns );
		$row = array_map( function ( $cell ) {
			return sanitize_text_field( wp_unslash( (string) $cell ) );
		}, $row );

		// Skip fully empty rows.
		if ( '' === trim( implode( '', $row ) ) ) {
			continue;
		}

		// Require the essentials: CEP inicial (0), CEP final (1) and custo (4).
		if ( '' === trim( $row[0] ) || '' === trim( $row[1] ) || '' === trim( $row[4] ) ) {
			continue;
		}

		$rows[] = $row;
	}

	if ( empty( $rows ) ) {
		wp_send_json_error( [ 'message' => __( 'Preencha ao menos uma linha com CEP inicial, CEP final e custo.', 'cc-table-shipping' ) ] );
	}

	// Build CSV content in memory (comma delimited, decimals stay as typed).
	$fh = fopen( 'php://temp', 'r+' );
	fputcsv( $fh, $header, ',', '"', '\\' );
	foreach ( $rows as $row ) {
		fputcsv( $fh, $row, ',', '"', '\\' );
	}
	rewind( $fh );
	$csv_content = stream_get_contents( $fh );
	fclose( $fh );

	// Persist into the uploads directory.
	$filename = 'frete-offline-colado-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 4, false ) . '.csv';
	$upload   = wp_upload_bits( $filename, null, $csv_content );

	if ( ! empty( $upload['error'] ) ) {
		wp_send_json_error( [ 'message' => $upload['error'] ] );
	}

	// Register as a private CSV attachment (same shape the upload flow expects).
	$attachment_id = wp_insert_attachment(
		[
			'post_mime_type' => 'text/csv',
			'post_title'     => __( 'Tabela de frete (colada)', 'cc-table-shipping' ),
			'post_content'   => '',
			'post_status'    => 'private',
		],
		$upload['file']
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		wp_send_json_error( [ 'message' => __( 'Falha ao criar o anexo CSV.', 'cc-table-shipping' ) ] );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

	wp_send_json_success( [
		'id'       => (int) $attachment_id,
		'url'      => $upload['url'],
		'filename' => $filename,
		'filesize' => size_format( strlen( $csv_content ) ),
		'count'    => count( $rows ),
	] );
}
