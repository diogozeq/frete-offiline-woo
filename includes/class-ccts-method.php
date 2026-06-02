<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( class_exists( 'CC_Table_Shipping_Method' ) ) {
	return;
} // Stop if the class already exists


class CC_Table_Shipping_Method extends WC_Shipping_Method {
	public $table;

	public $method_name;

	public $limit_results;

	public $additional_time;

	public $cart_fee;

	public $extra_weight;

	public $weight_unit;

	public $mapping_field_key;

	public $debugging;

	public $shipping_class;


	public $shipping_class_type;

	public $delimiter;

	public $table_free_shipping;

	public $delivery_time_meta;

	public $mapped_columns;

	public $always_visible;

	public $use_database;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );

		$this->init_form_fields();

		$this->id                 = 'cc_table_shipping';
		$this->method_title       = __( 'Tabela de Frete Offline (Claude Code)', 'cc-table-shipping' );
		$this->title              = $this->get_option( 'title', $this->method_title );
		$this->method_description = __( 'Tabela offline para cálculo de frete — versão Claude Code (sem conflito com o original)', 'cc-table-shipping' );

		$this->supports = [
			'shipping-zones',
			'instance-settings',
		];

		$this->init();

		add_filter( 'woocommerce_shipping_' . $this->id . '_instance_option', function ( $value, $key ) {
			if ( $key === 'ccts_system_report' && isset( $_GET['page'] ) && $_GET['page'] === 'wc-settings' ) {
				$data_store = WC_Data_Store::load( 'shipping-zone' );
				$raw_zones  = $data_store->get_zones();
				$zone       = WC_Shipping_Zones::get_zone_by( 'instance_id', $this->instance_id );

				foreach ( $raw_zones as &$raw_zone ) {
					$zone                     = WC_Shipping_Zones::get_zone_by( 'zone_id', $raw_zone->zone_id );
					$raw_zone->zone_locations = $zone->get_zone_locations();
				}

				$report = [
					'instance_id'            => $this->instance_id,
					'zones'                  => $raw_zones,
					'current_zone_locations' => $zone->get_zone_locations(),
				];


				if ( empty( $this->instance_settings ) ) {
					$this->init_instance_settings();
					$this->init_form_fields();
				}

				$report['current_settings']             = $this->instance_settings;
				$report['current_settings']['file_url'] = wp_get_attachment_url( $this->instance_settings['table_rate'] ?? 0 );

				return json_encode( $report, JSON_PRETTY_PRINT );
			}

			return $value;
		}, 10, 2 );
	}

	/**
	 * Init fields.
	 *
	 * Add fields to the WAFS shipping settings page.
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields() {

		$this->instance_form_fields = apply_filters( 'cc_table_shipping_form_fields', [
			'enabled'             => [
				'title'   => __( 'Ativar/Desativar', 'cc-table-shipping' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ativar Tabela de Frete Offline (Claude Code)', 'cc-table-shipping' ),
				'default' => 'yes',
			],
			'title'               => [
				'title'       => __( 'Título do método', 'cc-table-shipping' ),
				'type'        => 'text',
				'description' => __( 'Nome exibido no carrinho/checkout. Use <code>{name}</code> para o nome da linha da tabela e <code>{delivery_time}</code> para o prazo.', 'cc-table-shipping' ),
				'desc_tip'    => false,
				'default'     => '{name} (entrega em até {delivery_time} dias úteis)',
			],
			'additional_time'     => [
				'title'       => __( 'Dias adicionais', 'cc-table-shipping' ),
				'type'        => 'number',
				'description' => __( 'Dias úteis adicionais somados ao prazo de entrega da tabela.', 'cc-table-shipping' ),
				'desc_tip'    => true,
				'default'     => '0',
				'placeholder' => '0',
			],

			// ── Taxas ──────────────────────────────────────────────────────────
			'fees_title'          => [
				'type'        => 'title',
				'title'       => __( 'Taxas', 'cc-table-shipping' ),
				'description' => __( 'Valores adicionados ao custo calculado pela tabela.', 'cc-table-shipping' ),
			],
			'fee'                 => [
				'title'       => __( 'Taxa de manuseio', 'cc-table-shipping' ),
				'type'        => 'text',
				'description' => __( 'Valor fixo (ex: 2.50) ou percentual (ex: 5%) sobre o custo do frete. Deixe vazio para desabilitar.', 'cc-table-shipping' ),
				'desc_tip'    => true,
				'placeholder' => '0.00',
				'default'     => '',
			],
			'cart_fee'            => [
				'title'       => __( 'Taxa sobre o carrinho', 'cc-table-shipping' ),
				'type'        => 'text',
				'description' => __( 'Valor fixo (ex: 2.50) ou percentual (ex: 5%) sobre o total do carrinho. Deixe vazio para desabilitar.', 'cc-table-shipping' ),
				'desc_tip'    => true,
				'placeholder' => '0.00',
				'default'     => '',
			],
			'table_free_shipping' => [
				'title'       => __( 'Frete grátis a partir de', 'cc-table-shipping' ),
				'type'        => 'text',
				'description' => __( 'Valor mínimo do carrinho para frete grátis em toda a tabela. Deixe vazio para desabilitar.', 'cc-table-shipping' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'Sem frete grátis', 'cc-table-shipping' ),
			],

			// ── Peso ───────────────────────────────────────────────────────────
			'weight_title'        => [
				'type'  => 'title',
				'title' => __( 'Peso', 'cc-table-shipping' ),
			],
			'weight_unit'         => [
				'title'       => __( 'Unidade da tabela', 'cc-table-shipping' ),
				'type'        => 'select',
				'description' => __( 'Unidade de peso usada nas colunas da tabela CSV. Pode ser diferente da unidade do seu site.', 'cc-table-shipping' ),
				'desc_tip'    => true,
				'default'     => 'g',
				'class'       => 'wc-enhanced-select',
				'options'     => [
					'g'  => __( 'Gramas (g)', 'cc-table-shipping' ),
					'kg' => __( 'Quilos (kg)', 'cc-table-shipping' ),
				],
			],
			'extra_weight'        => [
				'title'       => sprintf( __( 'Peso extra (%s)', 'cc-table-shipping' ), get_option( 'woocommerce_weight_unit' ) ),
				'type'        => 'text',
				'description' => __( 'Peso adicional somado ao pacote antes de consultar a tabela (embalagem, etc.).', 'cc-table-shipping' ),
				'desc_tip'    => true,
				'default'     => '0',
			],

			// ── Visibilidade ───────────────────────────────────────────────────
			'visibility_title'    => [
				'type'  => 'title',
				'title' => __( 'Visibilidade e filtros', 'cc-table-shipping' ),
			],
			'always_visible'      => [
				'title'   => __( 'Sempre visível', 'cc-table-shipping' ),
				'type'    => 'checkbox',
				'label'   => __( 'Exibir mesmo quando outros métodos de entrega estiverem disponíveis.', 'cc-table-shipping' ),
				'default' => 'yes',
			],
			'limit_results'       => [
				'title'   => __( 'Limitar a 1 resultado', 'cc-table-shipping' ),
				'type'    => 'checkbox',
				'label'   => __( 'Exibir apenas o primeiro resultado que atender aos critérios da tabela.', 'cc-table-shipping' ),
				'default' => 'no',
			],
			'delivery_time_meta'  => [
				'title'   => __( 'Prazo abaixo do nome', 'cc-table-shipping' ),
				'type'    => 'checkbox',
				'label'   => __( 'Exibir prazo de entrega em linha separada, abaixo do nome do método. Se ativar, remova <code>{delivery_time}</code> do título.', 'cc-table-shipping' ),
				'default' => 'no',
			],
			'shipping_class'      => [
				'title'       => __( 'Classe de entrega', 'cc-table-shipping' ),
				'type'        => 'select',
				'description' => __( 'Aplica este método somente a produtos da classe selecionada. Use "Qualquer classe" para não filtrar.', 'cc-table-shipping' ),
				'desc_tip'    => true,
				'default'     => '-1',
				'class'       => 'wc-enhanced-select',
				'options'     => $this->get_shipping_classes_options(),
			],
			'shipping_class_type' => [
				'title'       => __( 'Validação da classe', 'cc-table-shipping' ),
				'type'        => 'select',
				'description' => __( 'Como validar a classe de entrega nos itens do carrinho.', 'cc-table-shipping' ),
				'desc_tip'    => true,
				'default'     => 'all',
				'class'       => 'wc-enhanced-select',
				'options'     => [
					'all' => __( 'Todos os produtos precisam ter a classe', 'cc-table-shipping' ),
					'one' => __( 'Basta um produto ter a classe', 'cc-table-shipping' ),
				],
			],

			// ── Tabela CSV ─────────────────────────────────────────────────────
			'table_rate'          => [
				'type' => 'table_rate',
			],
			'mapped_columns'      => [
				'type' => 'mapping_columns',
			],
			'conditions'          => [
				'type' => 'conditions_table',
			],

			// ── Avançado ───────────────────────────────────────────────────────
			'advanced_title'      => [
				'type'        => 'title',
				'title'       => __( 'Avançado', 'cc-table-shipping' ),
				'description' => __( 'Na maioria dos casos você não precisará alterar estas opções.', 'cc-table-shipping' ),
			],
			'delimiter'           => [
				'title'       => __( 'Delimitador do CSV', 'cc-table-shipping' ),
				'type'        => 'select',
				'description' => __( 'Separador de colunas do arquivo CSV. Padrão: vírgula. Troque para ponto-e-vírgula se o seu Excel/Sheets exporta com <code>;</code>.', 'cc-table-shipping' ),
				'desc_tip'    => true,
				'default'     => '',
				'class'       => 'wc-enhanced-select',
				'options'     => [
					''  => __( 'Vírgula (padrão)', 'cc-table-shipping' ),
					';' => __( 'Ponto-e-vírgula', 'cc-table-shipping' ),
				],
			],
			'debug'               => [
				'title'   => __( 'Log de debug', 'cc-table-shipping' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ativar log detalhado (WooCommerce > Status > Logs).', 'cc-table-shipping' ),
				'default' => 'no',
			],
//            'ccts_system_report'  => [
//                'title'             => __('Relatório do sistema', 'cc-table-shipping'),
//                'type'              => 'textarea',
//                'description'       => __('Caso precise de ajuda na configuração, utilize isso para obter ajuda. <a href="https://ajuda.fernandoacosta.net/cc-table-shipping/not-working">Saiba mais</a>',
//                    'cc-table-shipping'),
//                'desc_tip'          => false,
//                'default'           => '',
//                'custom_attributes' => [
//                    'readonly' => true,
//                ],
//            ],
		] );
	}

	/**
	 * Get shipping classes options.
	 *
	 * @return array
	 */
	protected function get_shipping_classes_options() {
		$shipping_classes = WC()->shipping->get_shipping_classes();
		$options          = [
			'-1' => __( 'Any Shipping Class', 'cc-table-shipping' ),
			'0'  => __( 'No Shipping Class', 'cc-table-shipping' ),
		];

		if ( ! empty( $shipping_classes ) ) {
			$options += wp_list_pluck( $shipping_classes, 'name', 'term_id' );
		}

		return $options;
	}

	/**
	 * Init.
	 *
	 * Initialize WC Melhor Envio shipping method.
	 *
	 * @since 1.0.0
	 */
	function init() {
		$this->init_settings();

		$this->enabled             = $this->get_option( 'enabled' );
		$this->method_name         = $this->get_option( 'title' );
		$this->limit_results       = $this->get_option( 'limit_results', 'no' );
		$this->additional_time     = $this->get_option( 'additional_time' );
		$this->fee                 = str_replace( ',', '.', $this->get_option( 'fee' ) );
		$this->cart_fee            = str_replace( ',', '.', $this->get_option( 'cart_fee' ) );
		$this->extra_weight        = $this->get_option( 'extra_weight', '0' );
		$this->weight_unit         = $this->get_option( 'weight_unit', 'g' );
		$this->always_visible      = $this->get_option( 'always_visible', 'yes' );
		$this->mapped_columns      = $this->get_option( 'mapped_columns', [] );
		$this->debugging           = $this->get_option( 'debug' );
		$this->shipping_class      = (int) $this->get_option( 'shipping_class', '-1' );
		$this->shipping_class_type = $this->get_option( 'shipping_class_type', 'all' );

		$this->delimiter           = $this->get_option( 'delimiter', ccts_get_delimiter() );
		$this->table_free_shipping = $this->get_option( 'table_free_shipping' );
		$this->use_database        = $this->get_option( 'use_database', 'no' );

		$this->delivery_time_meta = $this->get_option( 'delivery_time_meta', 'no' );

		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );

		add_filter( 'woocommerce_package_rates', [ $this, 'hide_if_others_available' ], 9999, 2 );

		add_filter( 'woocommerce_order_item_get_formatted_meta_data', [ $this, 'hide_meta_data' ] );
	}


	// somente para o campo existir e podermos salvá-lo a seguir

	public function generate_mapping_columns_html( $key, $values ) {
		$this->mapping_field_key = $this->get_field_key( $key );
		// atualizar o campo com os novos valores após salvar!
		$this->mapped_columns = $mapped = $this->get_option( 'mapped_columns' );

		return false;
	}


	public function validate_mapping_columns_field( $key, $value ) {
		return is_array( $value ) ? array_map( 'wc_clean', array_map( 'stripslashes', $value ) ) : [];
	}


	public function generate_table_rate_html( $key, $values ) {
		$field_key = $this->get_field_key( $key );

		// atualizar o campo com os novos valores após salvar!
		$this->table = $this->get_table_rate();

		return '<tr valign="top"><td colspan="2"><input type="hidden" name="' . esc_attr( $field_key ) . '" id="' . esc_attr( $field_key ) . '" value="' . esc_attr( $this->get_option( $key ) ) . '" /></td></tr>';
	}

	// apenas para atualizar os detalhes da tabela

	protected function get_table_rate( $package = [] ) {
		$this->table = $this->get_option( 'table_rate' );

		return apply_filters( 'cc_table_shipping_table_rate', $this->table, $package, $this );
	}

	public function validate_table_rate_field( $key, $value ) {
		$this->rename_attachment( $value );

		return $value;
	}

	/**
	 * Rename Attachment.
	 *
	 * Rename Attachment after upload in a CCTS post.
	 *
	 * @since 1.0.0
	 */
	public function rename_attachment( $post_id ) {
		$attachment = get_post( $post_id );

		if ( 'text/csv' !== get_post_mime_type( $post_id ) ) {
			return false;
		}

		if ( $attachment ) {
			wp_update_post(
				[
					'ID'          => $post_id,
					'context'     => 'import',
					'post_status' => 'private',
				]
			);

			$original_file = get_attached_file( $post_id );

			$extension = pathinfo( $original_file, PATHINFO_EXTENSION );

			if ( 'txt' === $extension ) {
				return false;
			}

			$renamed = rename( $original_file, $original_file . '.txt' );

			if ( $renamed ) {
				update_attached_file( $post_id, $original_file . '.txt' );
			}
		}
	}

	/**
	 * Settings tab table.
	 *
	 * Load and render the table on the Advanced Free Shipping settings tab.
	 *
	 * @return  string
	 * @since 1.0.0
	 *
	 */
	public function generate_conditions_table_html() {
		ob_start();
		require_once plugin_dir_path( __FILE__ ) . 'admin/views/meta-box-file.php';

		return ob_get_clean();
	}

	/**
	 * Calculates the shipping rate.
	 *
	 * @param  array  $package  Order package.
	 */
	public function calculate_shipping( $package = [] ) {
		// First, check for shipping classes.
		if ( ! $this->is_shipping_class_valid( $package ) ) {
			ccts_log( 'sem classes de entrega', $this->debugging );

			return;
		}

		$extra_data = apply_filters( 'ccts_matched_rules_extra_data', [], $package, $this );

		// Calculate package weight only once per shipping method
		$package_weight = ccts_get_package_weight( $package, $this->weight_unit, $this->extra_weight, $this,
			$extra_data );

		if ( 'yes' === $this->use_database ) {
			$rates = $this->get_rates_from_database( $package, $package_weight );
			foreach ( $rates as $rate_data ) {
				if ( $rate = ccts_matched_rules_from_database( $rate_data, $package, $this, $extra_data,
					$package_weight ) ) {
					$this->process_rate( $rate, $package, $extra_data );
					if ( 'yes' === $this->limit_results ) {
						break;
					}
				}
			}
		} else {
			$handle = ccts_get_available_rates( $this->get_table_rate( $package ), $package, $this );

			if ( ! $handle ) {
				ccts_log( sprintf( 'O método #%s não possui um arquivo válido.', $this->instance_id ),
					$this->debugging );

				return false;
			}

			// skip first line
			$header = fgetcsv( $handle, 0, $this->delimiter, '"', '\\' );

			while ( ( $row = fgetcsv( $handle, 0, $this->delimiter, '"', '\\' ) ) !== false ) {
				if ( $rate = ccts_matched_rules( $row, $package, $this, $extra_data, $package_weight ) ) {
					$this->process_rate( $rate, $package, $extra_data );
					if ( 'yes' === $this->limit_results ) {
						break;
					}
				}
			}
		}
	}

	/**
	 * Check if package uses only the selected shipping class.
	 *
	 * @param  array  $package  Cart package.
	 *
	 * @return bool
	 */
	protected function is_shipping_class_valid( $package ) {
		if ( - 1 === $this->shipping_class ) {
			return true;
		}

		// default value
		$only_selected = 'all' === $this->shipping_class_type;

		foreach ( $package['contents'] as $item_id => $values ) {
			$product = $values['data'];
			$qty     = $values['quantity'];

			if ( $qty > 0 && $product->needs_shipping() ) {
				$class = $product->get_shipping_class_id();

				if ( 'one' === $this->shipping_class_type && $this->shipping_class === $class ) {
					$only_selected = true;
					break;
				} elseif ( $this->shipping_class !== $class && 'all' === $this->shipping_class_type ) {
					$only_selected = false;
					break;
				}
			}
		}

		return $only_selected;
	}

	/**
	 * Get rates from database for given package
	 *
	 * @param  array  $package
	 *
	 * @return array
	 */
	private function get_rates_from_database( $package, $package_weight ) {
		global $wpdb;

		if ( ! isset( $package['destination']['postcode'] ) ) {
			return [];
		}

		$postcode = ccts_sanitize_postcode( $package['destination']['postcode'] );

		$table_name = $wpdb->prefix . 'ccts_rates';

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table_name} 
			 WHERE instance_id = %d 
			   AND postcode_start <= %d 
			   AND postcode_end >= %d 
			   AND weight_start <= %f 
			   AND weight_end >= %f 
			 ORDER BY cost ASC",
			$this->instance_id,
			$postcode,
			$postcode,
			$package_weight,
			$package_weight
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );

		ccts_log( sprintf( 'Database query returned %d results for postcode %s and weight %s', count( $results ),
			$postcode, $package_weight ), $this->debugging );

		return $results ? $results : [];
	}

	/**
	 * Process and add rate to shipping methods
	 *
	 * @param  array  $rate
	 * @param  array  $package
	 * @param  array  $extra_data
	 */
	private function process_rate( $rate, $package, $extra_data ) {
		// Apply fees. But only if it's not free shipping!
		$fee = $cart_fee = 0;

		if ( 0 < $rate['cost'] ) {
			$fee      = $this->get_fee( $this->fee, wc_format_decimal( $rate['cost'] ) );
			$cart_fee = $this->get_fee( $this->cart_fee,
				isset( $package['contents_cost'] ) ? apply_filters( 'ccts_cart_contents_cost',
					$package['contents_cost'], $rate ) : 0 );
		}

		$cost = apply_filters( 'cc_table_shipping_cost',
			(float) wc_format_decimal( $rate['cost'] ) + (float) $fee + (float) $cart_fee, $rate, $package,
			$this );
		$cost = 0 > $cost ? 0 : $cost;

		if ( is_numeric( $this->table_free_shipping ) && $this->table_free_shipping <= apply_filters( 'ccts_cart_contents_cost',
				$package['contents_cost'], $rate ) ) {
			ccts_log( sprintf(
				'FRETE GRÁTIS GLOBAL. Pedido de %s. Mínimo de %s',
				wc_price( apply_filters( 'ccts_cart_contents_cost', $package['contents_cost'], $rate ) ),
				wc_price( $this->table_free_shipping )
			), $this->debugging );
			$cost = 0;
		}

		$args = apply_filters( 'cc_table_shipping_rate', [
			'id'        => $rate['key'],
			'label'     => $rate['label'],
			'cost'      => $cost,
			'taxes'     => false,
			'package'   => $package,
			'meta_data' => isset( $rate['meta_data'] ) ? $rate['meta_data'] : [],
		], $rate, $this, $extra_data );

		// fixed data
		$args['meta_data']['always_visible']     = $this->always_visible;
		$args['meta_data']['is_ccts']            = 'yes';
		$args['meta_data']['delivery_time_meta'] = 'yes' === $this->delivery_time_meta;

		$this->add_rate( $args );

	}

	public function hide_if_others_available( $rates, $package ) {
		if ( 'yes' === $this->always_visible ) {
			return $rates;
		}

		$has_methods = false;

		foreach ( $rates as $key => $rate ) {
			$meta_data = $rate->get_meta_data();

			if ( ! isset( $meta_data['is_ccts'] ) ) {
				$has_methods = true;
				break;
			}
		}

		// se há métodos
		if ( $has_methods ) {
			foreach ( $rates as $key => $rate ) {
				$meta_data = $rate->get_meta_data();

				if ( isset( $meta_data['is_ccts'] ) && 'no' === $meta_data['always_visible'] ) {
					unset( $rates[ $key ] );
				}
			}
		}

		return $rates;
	}

	public function hide_meta_data( $formatted_meta ) {
		foreach ( $formatted_meta as $key => $meta ) {
			if ( in_array( $meta->key, [ 'is_ccts', 'always_visible', '_is_ccts', '_always_visible' ] ) ) {
				unset( $formatted_meta[ $key ] );
			}
		}

		return $formatted_meta;
	}

	public function process_admin_options() {
		parent::process_admin_options();

		$use_database = isset( $_POST['use_database'] ) ? 'yes' : 'no';
		$this->update_option( 'use_database', $use_database );
	}
}
