<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * Class CCTS_Helper.
 *
 * Initialize the CCTS Helpers.
 *
 * @class       CCTS_post_type
 * @author      Fernando Acosta
 * @package   WC Table Shipping
 * @version   1.0.0
 */
class CCTS_Helper {
  public static function import_columns() {
    $import_columns = apply_filters( 'ccts_mapping_columns', array(
      'zipcode_start' => 'CEP inicial',
      'zipcode_end'   => 'CEP final',
      'weight_start'  => 'Peso inicial',
      'weight_end'    => 'Peso final',
      'cost'          => 'Custo',
      'delivery_time' => 'Prazo de entrega',
      'label'         => 'Nome',
      'free_shipping' => 'Compra mínima para frete grátis',
    ));

    return $import_columns;
  }


  public static function column_placeholder( $key ) {
    $placeholders = apply_filters( 'ccts_placeholder_columns', array(
      'zipcode_start' => 'Selecione um CEP inicial',
      'zipcode_end'   => 'Selecione um CEP final',
      'weight_start'  => 'Sem peso mínimo',
      'weight_end'    => 'Sem peso máximo',
      'cost'          => 'Frete Grátis',
      'delivery_time' => 'Não exibir tempo de entrega',
      'label'         => 'Usar nome global',
      'free_shipping' => 'Não oferecer frete grátis',
    ));

    return isset( $placeholders[ $key ] ) ? $placeholders[ $key ] : __( 'Any value', 'cc-table-shipping' );
  }


  public static function default_column_values( $key = 'any' ) {
    $defaults = apply_filters( 'ccts_default_column_values', array(
      'zipcode_start' => 'CEPInicial',
      'zipcode_end'   => 'CEPFinal',
      'weight_start'  => 'PesoInicial',
      'weight_end'    => 'PesoFinal',
      'cost'          => 'CustoEntrega',
      'delivery_time' => 'Prazoentrega',
      'label'         => 'NomeMetodo',
      'free_shipping' => 'FreteGratis',
    ));

    return isset( $defaults[ $key ] ) ? $defaults[ $key ] : false;
  }


  public static function mapping( $file, $delimiter = null ) {
    if ( ! $delimiter ) {
      $delimiter = ccts_get_delimiter();
    }

    if ( ( $handle = fopen( $file, "r" ) ) !== false ) {

      $header = fgetcsv( $handle, 0, $delimiter );

      return $header;

    } else {
      return false;
    }
  }


  public static function required_fields_missing( $mapped ) {
    $errors = array(
      'error'   => array(),
      'warning' => array(),
    );

    if ( ! isset( $mapped['zipcode_start'] ) || '-1' === $mapped['zipcode_start'] ) {
      $errors['error']['zipcode_start'] = 'O <strong style="color: red;">CEP inicial</strong> precisa ser mapeado.';
    }

    if ( ! isset( $mapped['zipcode_end'] ) || '-1' === $mapped['zipcode_end'] ) {
      $errors['error']['zipcode_end'] = 'O <strong style="color: red;">CEP final</strong> precisa ser mapeado.';
    }

    if ( ! isset( $mapped['weight_start'] ) || '-1' === $mapped['weight_start'] ) {
      $errors['warning']['weight_start'] = 'O <strong style="color: red;">peso inicial</strong> não foi mapeado. Certifique-se de que seu único critério é o CEP, ignorando este atributo.';
    }

    if ( ! isset( $mapped['weight_end'] ) || '-1' === $mapped['weight_end'] ) {
      $errors['warning']['weight_end'] = 'O <strong style="color: red;">peso final</strong> não foi mapeado. Certifique-se de que seu único critério é o CEP, ignorando este atributo.';
    }

    if ( ! isset( $mapped['cost'] ) ) {
      $errors['warning']['cost'] = 'O <strong style="color: red;">custo de envio</strong> não está configurado. Mesmo que você queira oferecer frete grátis é necessário selecionar esta opção.';
    }

    if ( ! isset( $mapped['delivery_time'] ) || '-1' === $mapped['delivery_time'] ) {
      $errors['warning']['delivery_time'] = 'O <strong style="color: red;">prazo de envio</strong> não está configurado. Mesmo que você não queira exibir esta informação ao cliente, é necessário selecionar esta opção.';
    }

    foreach ( $errors as $group => $erros_list ) {
      if ( ! $errors[ $group ] ) {
        unset( $errors[ $group ] );
      }
    }

    return $errors;
  }


  public static function count_rows( $file ) {
    if ( ( $handle = fopen( $file, "r" ) ) !== false ) {

      $fp = file( $file, FILE_SKIP_EMPTY_LINES );

      if ( $fp ) {
        return 0 < count( $fp ) ? count( $fp ) : false;
      }

    }

    return false;
  }

}
