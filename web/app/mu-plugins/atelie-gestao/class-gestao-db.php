<?php
/**
 * Tabelas do módulo de gestão: patrimônio (bens + histórico de eventos) e o
 * livro do fundo de reposição. Tabelas próprias em vez de CPT porque o fundo é
 * um livro-razão (entradas/saídas somadas) e não faz sentido como "post".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Gestao_Db {

	private const VERSAO       = '1';
	private const OPCAO_VERSAO = 'atelie_gestao_db_versao';

	public static function tabela( string $nome ): string {
		global $wpdb;

		return $wpdb->prefix . 'atelie_' . $nome;
	}

	public static function garantir(): void {
		if ( get_option( self::OPCAO_VERSAO ) === self::VERSAO ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$bens    = self::tabela( 'patrimonio' );
		$eventos = self::tabela( 'patrimonio_eventos' );
		$fundo   = self::tabela( 'fundo' );

		dbDelta(
			"CREATE TABLE {$bens} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			codigo VARCHAR(20) NOT NULL DEFAULT '',
			nome VARCHAR(200) NOT NULL,
			categoria VARCHAR(80) NOT NULL DEFAULT '',
			valor_compra DECIMAL(12,2) NOT NULL DEFAULT 0,
			data_compra DATE NOT NULL,
			fornecedor VARCHAR(200) NOT NULL DEFAULT '',
			local_uso VARCHAR(200) NOT NULL DEFAULT '',
			observacao TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'em_uso',
			substitui_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			usuario_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			criado_em DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$eventos} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			bem_id BIGINT UNSIGNED NOT NULL,
			tipo VARCHAR(20) NOT NULL,
			data_evento DATE NOT NULL,
			valor DECIMAL(12,2) NOT NULL DEFAULT 0,
			observacao TEXT NULL,
			usuario_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			criado_em DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY bem_id (bem_id)
		) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$fundo} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			data_mov DATE NOT NULL,
			tipo VARCHAR(20) NOT NULL,
			valor DECIMAL(12,2) NOT NULL,
			descricao VARCHAR(255) NOT NULL DEFAULT '',
			bem_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			venda_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			usuario_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			criado_em DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY data_mov (data_mov)
		) {$charset};"
		);

		update_option( self::OPCAO_VERSAO, self::VERSAO, false );
	}
}
