<?php
/**
 * Criacao em massa: cria um produto rascunho por item (uma foto ou um grupo
 * de fotos vira um produto) e agenda o processamento de cada um
 * separadamente via Action Scheduler — nao e um job so pra tudo, e o que
 * permite revisar o que ja terminou enquanto o resto ainda processa. Hoje o
 * unico jeito de entrar aqui e a importacao do Google Drive (uma subpasta =
 * um grupo) — upload solto sem organizacao foi removido (decisao explicita
 * do usuario: causava caos sem nenhuma forma de agrupar as fotos por
 * produto). Ver plano, secao "Criacao de produtos em massa via IA".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function Env\env;

class Atelie_Lote_Controller {

	private const HOOK_PROCESSAR_ITEM    = 'atelie_lote_processar_item';
	private const GRUPO_ACTION_SCHEDULER = 'atelie-lote';
	private const MAX_TENTATIVAS         = 3;
	private const BACKOFF_SEGUNDOS       = array( 60, 300, 900 ); // 1min, 5min, 15min

	/**
	 * Margem antes de considerar um item "atrasado" — o WP-Cron desse host não é
	 * confiável (depende de visita ao site pra disparar), então quem abre a tela
	 * "Revisar lote" acaba sendo o gatilho mais confiável que existe: se passou
	 * tempo demais do horário agendado, processa na hora em vez de esperar o cron.
	 */
	private const MARGEM_ATRASO_SEGUNDOS = 30;

	public function registrar(): void {
		add_action( self::HOOK_PROCESSAR_ITEM, array( $this, 'processar_item' ) );
	}

	public function limite_por_lote(): int {
		$valor = env( 'AI_BATCH_MAX_ITEMS' );
		return $valor !== null && $valor !== '' ? (int) $valor : 25;
	}

	/**
	 * Cria um produto rascunho por grupo de fotos e agenda o processamento
	 * de cada um — usado pela importação do Drive (um grupo = as fotos de
	 * uma subpasta).
	 *
	 * @param array<int, array<int, int>> $grupos_fotos_ids
	 */
	public function criar_lote_de_grupos( array $grupos_fotos_ids ): string {
		$lote_id = uniqid( 'lote_', true );

		foreach ( $grupos_fotos_ids as $fotos_do_item ) {
			$fotos_do_item = array_values( array_filter( array_map( 'absint', $fotos_do_item ) ) );
			if ( empty( $fotos_do_item ) ) {
				continue;
			}

			$produto_id = wp_insert_post(
				array(
					'post_type'   => 'product',
					'post_title'  => 'Processando…',
					'post_status' => 'draft',
				)
			);

			if ( is_wp_error( $produto_id ) || ! $produto_id ) {
				continue;
			}

			wp_set_object_terms( $produto_id, 'simple', 'product_type' );
			set_post_thumbnail( $produto_id, $fotos_do_item[0] );
			if ( count( $fotos_do_item ) > 1 ) {
				update_post_meta( $produto_id, '_product_image_gallery', implode( ',', array_slice( $fotos_do_item, 1 ) ) );
			}
			update_post_meta( $produto_id, '_manage_stock', 'no' );
			update_post_meta( $produto_id, '_stock_status', 'instock' );
			update_post_meta( $produto_id, '_atelie_lote_id', $lote_id );
			update_post_meta( $produto_id, '_atelie_lote_status', 'processando' );
			update_post_meta( $produto_id, '_atelie_lote_tentativas', 0 );
			update_post_meta( $produto_id, '_atelie_lote_fotos_ids', implode( ',', $fotos_do_item ) );
			update_post_meta( $produto_id, '_atelie_lote_agendado_em', time() );

			as_schedule_single_action( time(), self::HOOK_PROCESSAR_ITEM, array( 'produto_id' => $produto_id ), self::GRUPO_ACTION_SCHEDULER );
		}

		return $lote_id;
	}

	public function processar_item( int $produto_id ): void {
		$fotos_ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $produto_id, '_atelie_lote_fotos_ids', true ) ) ) );

		$caminhos = array();
		foreach ( $fotos_ids as $foto_id ) {
			$caminho = get_attached_file( $foto_id );
			if ( $caminho ) {
				$caminhos[] = $caminho;
			}
		}

		try {
			$servico  = Atelie_Ai_Vision_Service_Factory::criar();
			$sugestao = $servico->analisar( $caminhos );

			wp_update_post(
				array(
					'ID'           => $produto_id,
					'post_title'   => $sugestao['titulo'] !== '' ? $sugestao['titulo'] : 'Produto sem título — revisar',
					'post_content' => $sugestao['descricao'],
				)
			);

			if ( $sugestao['categoria'] !== '' ) {
				$termo = term_exists( $sugestao['categoria'], 'product_cat' );
				if ( ! $termo ) {
					$termo = wp_insert_term( $sugestao['categoria'], 'product_cat' );
				}
				if ( ! is_wp_error( $termo ) ) {
					wp_set_object_terms( $produto_id, (int) $termo['term_id'], 'product_cat' );
				}
			}

			if ( $sugestao['material_tecnica'] !== '' ) {
				update_post_meta( $produto_id, '_atelie_material_tecnica_sugerido', $sugestao['material_tecnica'] );
			}

			update_post_meta( $produto_id, '_atelie_lote_status', 'pronto' );
		} catch ( Throwable $e ) {
			$this->tratar_falha( $produto_id, $e->getMessage() );
		}
	}

	private function tratar_falha( int $produto_id, string $mensagem ): void {
		$tentativas = (int) get_post_meta( $produto_id, '_atelie_lote_tentativas', true );
		++$tentativas;
		update_post_meta( $produto_id, '_atelie_lote_tentativas', $tentativas );
		update_post_meta( $produto_id, '_atelie_lote_erro', $mensagem );

		if ( $tentativas < self::MAX_TENTATIVAS ) {
			$espera = self::BACKOFF_SEGUNDOS[ $tentativas - 1 ] ?? end( self::BACKOFF_SEGUNDOS );
			update_post_meta( $produto_id, '_atelie_lote_agendado_em', time() + $espera );
			as_schedule_single_action( time() + $espera, self::HOOK_PROCESSAR_ITEM, array( 'produto_id' => $produto_id ), self::GRUPO_ACTION_SCHEDULER );
			// continua "processando" ate esgotar as tentativas — o item so vira "erro" de vez depois da ultima falha
			return;
		}

		update_post_meta( $produto_id, '_atelie_lote_status', 'erro' );
	}

	public function reprocessar_item( int $produto_id ): void {
		update_post_meta( $produto_id, '_atelie_lote_status', 'processando' );
		update_post_meta( $produto_id, '_atelie_lote_tentativas', 0 );
		update_post_meta( $produto_id, '_atelie_lote_agendado_em', time() );
		as_schedule_single_action( time(), self::HOOK_PROCESSAR_ITEM, array( 'produto_id' => $produto_id ), self::GRUPO_ACTION_SCHEDULER );
	}

	/**
	 * Chamado sempre que a tela "Revisar lote" é aberta — quem visita essa tela
	 * claramente está esperando por aqueles produtos, então usa a própria visita
	 * como um segundo gatilho (além do cron) pra itens que já deveriam ter
	 * processado e não processaram. Idempotente: só processa de fato quem estiver
	 * mesmo atrasado, e usa uma trava curta pra não rodar duas vezes se a pessoa
	 * atualizar a página rápido demais (ou o cron disparar no mesmo instante).
	 *
	 * @param array<int, int> $produto_ids
	 */
	public function cutucar_pendentes( array $produto_ids ): void {
		foreach ( $produto_ids as $produto_id ) {
			if ( get_post_meta( $produto_id, '_atelie_lote_status', true ) !== 'processando' ) {
				continue;
			}

			$agendado_em = (int) get_post_meta( $produto_id, '_atelie_lote_agendado_em', true );
			if ( $agendado_em === 0 || time() - $agendado_em < self::MARGEM_ATRASO_SEGUNDOS ) {
				continue; // dentro do prazo normal, deixa o cron cuidar
			}

			$trava = 'atelie_lote_cutucar_' . $produto_id;
			if ( get_transient( $trava ) ) {
				continue; // outra requisicao ja esta processando esse item agora
			}
			set_transient( $trava, 1, 60 );

			as_unschedule_action( self::HOOK_PROCESSAR_ITEM, array( 'produto_id' => $produto_id ), self::GRUPO_ACTION_SCHEDULER );
			$this->processar_item( $produto_id );

			delete_transient( $trava );
		}
	}
}
