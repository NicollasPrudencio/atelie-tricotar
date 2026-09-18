<?php
/**
 * Plugin Name: Atelie - Criar Produto com IA
 * Description: Painel simplificado de criacao de produto com autofill por IA de visao. Ver plano do projeto, secao "Plugin custom Criar Produto com IA".
 * Version: 0.5.0
 * Requires Plugins: woocommerce
 *
 * Fluxo individual, criacao em massa (upload direto) e importacao do Google
 * Drive implementados.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-ai-custo-tracker.php';
require_once __DIR__ . '/includes/class-ai-config.php';
require_once __DIR__ . '/includes/interface-ai-vision-service.php';
require_once __DIR__ . '/includes/class-ai-vision-service-mock.php';
require_once __DIR__ . '/includes/class-ai-vision-service-gemini.php';
require_once __DIR__ . '/includes/class-ai-vision-service-factory.php';
require_once __DIR__ . '/includes/class-revisao-vendas.php';
require_once __DIR__ . '/includes/class-rest-controller.php';
require_once __DIR__ . '/includes/class-admin-page.php';
require_once __DIR__ . '/includes/class-case-admin-page.php';
require_once __DIR__ . '/includes/class-lote-controller.php';
require_once __DIR__ . '/includes/class-lote-admin-pages.php';
require_once __DIR__ . '/includes/class-massa-admin-page.php';
require_once __DIR__ . '/includes/class-ordenar-produtos-admin-page.php';
require_once __DIR__ . '/includes/class-settings-page.php';
require_once __DIR__ . '/includes/class-drive-config.php';
require_once __DIR__ . '/includes/class-google-drive-service.php';
require_once __DIR__ . '/includes/class-drive-admin-page.php';
require_once __DIR__ . '/includes/class-ads-admin-page.php';
require_once __DIR__ . '/includes/class-receita-admin-page.php';
require_once __DIR__ . '/includes/class-seo-admin-page.php';
require_once __DIR__ . '/includes/class-wizard-config-page.php';

register_activation_hook( __FILE__, array( 'Atelie_Ai_Custo_Tracker', 'garantir_tabela' ) );

/**
 * Cobre tambem quem ja tinha o plugin ativo antes dessa tabela existir —
 * register_activation_hook so dispara em ativacoes novas.
 */
add_action( 'plugins_loaded', array( 'Atelie_Ai_Custo_Tracker', 'garantir_tabela' ) );

add_action(
	'plugins_loaded',
	function (): void {
		( new Atelie_Rest_Controller() )->registrar();
		( new Atelie_Admin_Page() )->registrar();
		( new Atelie_Case_Admin_Page() )->registrar();
		( new Atelie_Lote_Controller() )->registrar();
		( new Atelie_Lote_Admin_Pages() )->registrar();
		( new Atelie_Massa_Admin_Page() )->registrar();
		( new Atelie_Ordenar_Produtos_Admin_Page() )->registrar();
		( new Atelie_Settings_Page() )->registrar();
		( new Atelie_Drive_Admin_Page() )->registrar();
		( new Atelie_Ads_Admin_Page() )->registrar();
		( new Atelie_Receita_Admin_Page() )->registrar();
		( new Atelie_Seo_Admin_Page() )->registrar();
		( new Atelie_Wizard_Config_Page() )->registrar();
	}
);

/**
 * Verificação diária automática da conexão com a IA — sem isso, se a chave
 * parar de funcionar (cota estourada, chave revogada, modelo descontinuado —
 * já aconteceu de verdade nesse projeto) ninguém saberia até tentar usar e
 * tomar erro. Com a checagem diária, o botão "Sugerir com IA" já aparece
 * desabilitado com o aviso antes de alguém perder tempo tentando.
 */
add_action(
	'init',
	function (): void {
		if ( ! wp_next_scheduled( 'atelie_ai_verificar_conexao_diaria' ) ) {
			wp_schedule_event( time(), 'daily', 'atelie_ai_verificar_conexao_diaria' );
		}
	}
);

add_action(
	'atelie_ai_verificar_conexao_diaria',
	function (): void {
		Atelie_Ai_Config::testar_conexao();
	}
);

/**
 * O painel simplificado é o único caminho pra criar produto/case — não faz
 * sentido ter a tela nativa "Adicionar novo" do WordPress do lado (seria uma
 * segunda forma de criar sem IA, competindo com o botão "Preencher
 * manualmente" que já existe dentro do painel). Some com o item de menu e
 * redireciona quem chegar direto na URL nativa (link salvo, digitado, etc).
 */
add_action(
	'admin_menu',
	function (): void {
		remove_submenu_page( 'edit.php?post_type=product', 'post-new.php?post_type=product' );
		remove_submenu_page( 'edit.php?post_type=atelie_case', 'post-new.php?post_type=atelie_case' );
	},
	999
);

add_action(
	'load-post-new.php',
	function (): void {
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';

		if ( $post_type === 'product' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=atelie-novo-produto' ) );
			exit;
		}

		if ( $post_type === 'atelie_case' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=atelie-novo-case' ) );
			exit;
		}
	}
);

/**
 * Editar um produto já publicado (clicar em "Editar" na lista nativa de
 * Produtos) também tem que cair na tela simplificada — sem isso, só dava pra
 * chegar nela enquanto o produto ainda estivesse pendente em "Pendências";
 * depois de revisado/publicado, não sobrava nenhum jeito de reabrir a tela
 * simples pra, por exemplo, reordenar as fotos. Administrador fica de fora
 * (mantém acesso à tela nativa, que tem campos avançados — variações,
 * estoque, frete — que a tela simplificada não cobre).
 */
add_action(
	'load-post.php',
	function (): void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' || ! isset( $_GET['post'] ) ) {
			return;
		}

		$post_id = absint( $_GET['post'] );

		if ( get_post_type( $post_id ) === 'product' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=atelie-novo-produto&produto=' . $post_id ) );
			exit;
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function (): void {
		wp_clear_scheduled_hook( 'atelie_ai_verificar_conexao_diaria' );
	}
);
