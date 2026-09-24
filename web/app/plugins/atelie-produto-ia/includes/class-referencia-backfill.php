<?php
/**
 * Migração única: dá uma referência (SKU) a todo produto que já existia sem
 * uma — pedido explícito do usuário quando "Referência" passou a existir na
 * tela simplificada. Códigos sequenciais "ATL-0001", "ATL-0002"… na ordem de
 * criação; nunca mexe em quem já tem referência e continua a numeração a
 * partir do maior "ATL-NNNN" já existente. Idempotente e protegida por
 * flag + trava (roda uma vez por ambiente, mesmo com requisições
 * simultâneas logo depois do deploy).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Referencia_Backfill {

	private const PREFIXO      = 'ATL-';
	private const OPCAO_FEITO  = 'atelie_referencia_backfill_v1';
	private const OPCAO_TRAVA  = 'atelie_referencia_backfill_trava';
	private const TRAVA_SEGUND = 300;

	public static function registrar(): void {
		add_action( 'init', array( __CLASS__, 'executar_se_pendente' ), 30 );
	}

	public static function executar_se_pendente(): void {
		if ( get_option( self::OPCAO_FEITO ) !== false || ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		// add_option só cria se ainda não existe — vira trava contra duas requisições rodando juntas.
		if ( ! add_option( self::OPCAO_TRAVA, time(), '', 'no' ) ) {
			$desde = (int) get_option( self::OPCAO_TRAVA );
			if ( time() - $desde < self::TRAVA_SEGUND ) {
				return;
			}
			update_option( self::OPCAO_TRAVA, time(), false );
		}

		$resultado = self::executar();

		update_option( self::OPCAO_FEITO, $resultado, false );
		delete_option( self::OPCAO_TRAVA );
	}

	/**
	 * @return array{atribuidas: int, ja_tinham: int, falharam: int, quando: string}
	 */
	public static function executar(): array {
		$ids = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => -1,
				'orderby'     => 'ID',
				'order'       => 'ASC',
				'fields'      => 'ids',
			)
		);

		$ultimo    = 0;
		$sem_sku   = array();
		$ja_tinham = 0;
		foreach ( $ids as $id ) {
			$produto = wc_get_product( $id );
			if ( ! $produto ) {
				continue;
			}
			$sku = (string) $produto->get_sku();
			if ( $sku === '' ) {
				$sem_sku[] = $produto;
				continue;
			}
			++$ja_tinham;
			if ( preg_match( '/^' . preg_quote( self::PREFIXO, '/' ) . '(\d+)$/', $sku, $m ) ) {
				$ultimo = max( $ultimo, (int) $m[1] );
			}
		}

		$atribuidas = 0;
		$falharam   = 0;
		$data_store = WC_Data_Store::load( 'product' );
		foreach ( $sem_sku as $produto ) {
			do {
				++$ultimo;
				$codigo = sprintf( '%s%04d', self::PREFIXO, $ultimo );
			} while ( $data_store->is_existing_sku( $produto->get_id(), $codigo ) );

			try {
				$produto->set_sku( $codigo );
				$produto->save();
				++$atribuidas;
			} catch ( Throwable $e ) {
				++$falharam;
			}
		}

		return array(
			'atribuidas' => $atribuidas,
			'ja_tinham'  => $ja_tinham,
			'falharam'   => $falharam,
			'quando'     => gmdate( 'Y-m-d H:i:s' ),
		);
	}
}
