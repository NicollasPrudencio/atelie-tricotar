<?php
/**
 * Bens do ateliê (patrimônio) e o histórico de eventos de cada um: entrada
 * (comprado), dano, conserto, baixa (perdido/inutilizado) e reposição (outro
 * item comprado no lugar).
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabelas proprias do modulo; nomes vem de Atelie_Gestao_Db::tabela() (prefixo fixo, nao input de usuario).

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Patrimonio_Repo {

	public const STATUS_ROTULOS = array(
		'em_uso'     => 'Em uso',
		'danificado' => 'Danificado',
		'baixado'    => 'Baixado',
	);

	/**
	 * @param array{nome: string, categoria: string, valor_compra: float, data_compra: string, fornecedor: string, local_uso: string, observacao: string, substitui_id?: int} $d
	 */
	public static function criar( array $d ): int {
		global $wpdb;
		$tabela = Atelie_Gestao_Db::tabela( 'patrimonio' );

		$wpdb->insert(
			$tabela,
			array(
				'nome'         => $d['nome'],
				'categoria'    => $d['categoria'],
				'valor_compra' => $d['valor_compra'],
				'data_compra'  => $d['data_compra'],
				'fornecedor'   => $d['fornecedor'],
				'local_uso'    => $d['local_uso'],
				'observacao'   => $d['observacao'],
				'status'       => 'em_uso',
				'substitui_id' => (int) ( $d['substitui_id'] ?? 0 ),
				'usuario_id'   => get_current_user_id(),
				'criado_em'    => current_time( 'mysql' ),
			)
		);
		$id = (int) $wpdb->insert_id;

		$wpdb->update( $tabela, array( 'codigo' => sprintf( 'PAT-%04d', $id ) ), array( 'id' => $id ) );

		return $id;
	}

	public static function obter( int $id ): ?object {
		global $wpdb;
		$tabela = Atelie_Gestao_Db::tabela( 'patrimonio' );
		$linha  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE id = %d", $id ) );

		return $linha ?: null;
	}

	/**
	 * @return array<int, object>
	 */
	public static function listar( string $status = '' ): array {
		global $wpdb;
		$tabela = Atelie_Gestao_Db::tabela( 'patrimonio' );

		if ( isset( self::STATUS_ROTULOS[ $status ] ) ) {
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE status = %s ORDER BY id DESC", $status ) );
		}

		return (array) $wpdb->get_results( "SELECT * FROM {$tabela} ORDER BY id DESC" );
	}

	public static function definir_status( int $id, string $status ): void {
		global $wpdb;

		$wpdb->update( Atelie_Gestao_Db::tabela( 'patrimonio' ), array( 'status' => $status ), array( 'id' => $id ) );
	}

	public static function registrar_evento( int $bem_id, string $tipo, string $data, float $valor, string $observacao ): void {
		global $wpdb;

		$wpdb->insert(
			Atelie_Gestao_Db::tabela( 'patrimonio_eventos' ),
			array(
				'bem_id'      => $bem_id,
				'tipo'        => $tipo,
				'data_evento' => $data,
				'valor'       => $valor,
				'observacao'  => $observacao,
				'usuario_id'  => get_current_user_id(),
				'criado_em'   => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * @return array<int, object>
	 */
	public static function eventos( int $bem_id ): array {
		global $wpdb;
		$tabela = Atelie_Gestao_Db::tabela( 'patrimonio_eventos' );

		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabela} WHERE bem_id = %d ORDER BY data_evento DESC, id DESC", $bem_id ) );
	}

	/**
	 * @return array{em_uso: int, valor_em_uso: float, danificados: int, baixados: int}
	 */
	public static function resumo(): array {
		global $wpdb;
		$tabela = Atelie_Gestao_Db::tabela( 'patrimonio' );
		$linhas = (array) $wpdb->get_results( "SELECT status, COUNT(*) qtd, COALESCE(SUM(valor_compra), 0) total FROM {$tabela} GROUP BY status" );

		$resumo = array(
			'em_uso'       => 0,
			'valor_em_uso' => 0.0,
			'danificados'  => 0,
			'baixados'     => 0,
		);
		foreach ( $linhas as $l ) {
			if ( $l->status === 'em_uso' ) {
				$resumo['em_uso']       = (int) $l->qtd;
				$resumo['valor_em_uso'] = (float) $l->total;
			} elseif ( $l->status === 'danificado' ) {
				$resumo['danificados'] = (int) $l->qtd;
			} elseif ( $l->status === 'baixado' ) {
				$resumo['baixados'] = (int) $l->qtd;
			}
		}

		return $resumo;
	}
}
