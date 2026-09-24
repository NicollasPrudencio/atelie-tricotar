<?php
/**
 * Helpers de dinheiro e data do módulo de gestão (patrimônio, fundo, vendas,
 * orçamento por canal) — formato brasileiro na tela, DECIMAL/DATE no banco.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Gestao_Util {

	/**
	 * Aceita "1.234,56", "1234,56", "1234.56" e "R$ 10,00".
	 */
	public static function parse_valor( string $bruto ): float {
		$limpo = preg_replace( '/[^0-9,.\-]/', '', $bruto );
		$limpo = is_string( $limpo ) ? $limpo : '';

		if ( strpos( $limpo, ',' ) !== false ) {
			$limpo = str_replace( '.', '', $limpo );
			$limpo = str_replace( ',', '.', $limpo );
		} elseif ( preg_match( '/^\d{1,3}(\.\d{3})+$/', $limpo ) === 1 ) {
			// "1.234" sem vírgula é mil duzentos e trinta e quatro, não 1,234.
			$limpo = str_replace( '.', '', $limpo );
		}

		return round( (float) $limpo, 2 );
	}

	public static function moeda( float $valor ): string {
		return 'R$ ' . number_format( $valor, 2, ',', '.' );
	}

	/**
	 * Data no formato do <input type="date"> (Y-m-d) validada; vazia/ inválida vira hoje.
	 */
	public static function data_valida( string $bruta ): string {
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $bruta, $m ) === 1 && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return $bruta;
		}

		return wp_date( 'Y-m-d' );
	}

	public static function data_br( string $ymd ): string {
		$ts = strtotime( $ymd );

		return $ts ? wp_date( 'd/m/Y', $ts ) : '';
	}
}
