<?php
/**
 * Plugin Name: Ateliê — Gestão (patrimônio, fundo de reposição, canais e rateio)
 * Description: Módulo de gestão do ateliê para a Gestora (dona) e o Administrador — capacidade
 *              atelie_gestao. Fase 1: patrimônio (entrada, dano, conserto, baixa, reposição) e
 *              fundo de reposição. Ver docs/decisions/0003-gestao-patrimonio-canais-rateio.md.
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/atelie-gestao/class-gestao-util.php';
require_once __DIR__ . '/atelie-gestao/class-gestao-db.php';
require_once __DIR__ . '/atelie-gestao/class-fundo-repo.php';
require_once __DIR__ . '/atelie-gestao/class-patrimonio-repo.php';
require_once __DIR__ . '/atelie-gestao/class-patrimonio-admin-page.php';
require_once __DIR__ . '/atelie-gestao/class-fundo-admin-page.php';

add_action( 'init', array( 'Atelie_Gestao_Db', 'garantir' ) );

add_action(
	'plugins_loaded',
	function (): void {
		( new Atelie_Patrimonio_Admin_Page() )->registrar();
		( new Atelie_Fundo_Admin_Page() )->registrar();
	}
);
