<?php
/**
 * Livro do fundo de reposição do patrimônio: cada linha é uma entrada (valor
 * positivo — aporte manual ou rateio de venda) ou saída (valor negativo —
 * reposição de item danificado, retirada). O saldo é sempre a soma do livro.
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabela propria do modulo; nome vem de Atelie_Gestao_Db::tabela() (prefixo fixo, nao input de usuario).

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Fundo_Repo {

	public static function saldo(): float {
		global $wpdb;
		$tabela = Atelie_Gestao_Db::tabela( 'fundo' );

		return (float) $wpdb->get_var( "SELECT COALESCE(SUM(valor), 0) FROM {$tabela}" );
	}

	/**
	 * @param string $tipo aporte | rateio | reposicao | retirada
	 */
	public static function lancar( string $tipo, float $valor, string $descricao, string $data, int $bem_id = 0, int $venda_id = 0 ): int {
		global $wpdb;

		$wpdb->insert(
			Atelie_Gestao_Db::tabela( 'fundo' ),
			array(
				'data_mov'   => $data,
				'tipo'       => $tipo,
				'valor'      => $valor,
				'descricao'  => mb_substr( $descricao, 0, 255 ),
				'bem_id'     => $bem_id,
				'venda_id'   => $venda_id,
				'usuario_id' => get_current_user_id(),
				'criado_em'  => current_time( 'mysql' ),
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int, object>
	 */
	public static function movimentos( int $limite = 200 ): array {
		global $wpdb;
		$tabela = Atelie_Gestao_Db::tabela( 'fundo' );

		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$tabela} ORDER BY data_mov DESC, id DESC LIMIT %d", $limite )
		);
	}
}
