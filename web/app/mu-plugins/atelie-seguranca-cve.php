<?php
/**
 * Plugin Name: Atelie - Verificacao diaria de vulnerabilidade conhecida (WPScan)
 * Description: Checa todo dia (via cron real do servidor, nao WP-Cron pseudo — funciona mesmo
 * sem ninguem visitar o site) se o WordPress core, plugins e tema instalados tem vulnerabilidade
 * conhecida sem correcao na versao atual, usando a base do WPScan. Se achar algo: e-mail
 * chamativo pra admin@ (detalhe tecnico) e negocio@ (aviso simples, "e sobre seguranca") duas
 * vezes por dia enquanto nao for corrigido, e aviso fixo no painel visivel pra qualquer usuario
 * logado — nao so administrador, pra a Artesã tambem avisar se o admin nao ver.
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function Env\env;

const ATELIE_CVE_OPTION_RESULTADO       = 'atelie_cve_vulnerabilidades';
const ATELIE_CVE_OPTION_ULTIMA_CHECAGEM = 'atelie_cve_ultima_verificacao';
const ATELIE_CVE_EMAIL_ADMIN            = 'admin@atelietricotar.com.br';
const ATELIE_CVE_EMAIL_NEGOCIO          = 'negocio@atelietricotar.com.br';

/**
 * Agenda os dois crons (idempotente — so agenda se ainda nao estiver agendado):
 * - verificacao: 1x/dia, faz a chamada de verdade na API do WPScan (respeita a cota do plano
 *   gratuito, ~10-12 chamadas por rodada).
 * - lembrete: 2x/dia, so LE o resultado ja salvo e reenvia o e-mail se ainda tiver algo
 *   pendente — nao gasta cota de API de novo, so insiste enquanto ninguem corrigir.
 */
add_action(
	'init',
	function (): void {
		if ( ! wp_next_scheduled( 'atelie_cve_verificacao_diaria' ) ) {
			wp_schedule_event( time(), 'daily', 'atelie_cve_verificacao_diaria' );
		}
		if ( ! wp_next_scheduled( 'atelie_cve_lembrete_email' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'atelie_cve_lembrete_email' );
		}
	}
);

add_action( 'atelie_cve_verificacao_diaria', 'atelie_cve_verificar_tudo' );
add_action( 'atelie_cve_lembrete_email', 'atelie_cve_enviar_lembrete_se_pendente' );

/**
 * Varre WordPress core + plugins ativos + tema ativo contra a API do WPScan, guarda o
 * resultado, e ja dispara o primeiro e-mail se achar algo (o lembrete cuida do resto do dia).
 */
function atelie_cve_verificar_tudo(): void {
	$token = env( 'WPSCAN_API_TOKEN' );
	if ( empty( $token ) ) {
		return; // sem token configurado, nao tem como consultar — nao falha nem falso-alarme.
	}

	$achados = array();

	foreach ( atelie_cve_itens_instalados() as $item ) {
		$vulnerabilidades = atelie_cve_consultar_item( $item, $token );
		if ( ! empty( $vulnerabilidades ) ) {
			$achados[] = array(
				'nome'             => $item['nome'],
				'versao_instalada' => $item['versao'],
				'vulnerabilidades' => $vulnerabilidades,
			);
		}
	}

	$tinha_antes = ! empty( get_option( ATELIE_CVE_OPTION_RESULTADO ) );

	update_option( ATELIE_CVE_OPTION_RESULTADO, $achados, false );
	update_option( ATELIE_CVE_OPTION_ULTIMA_CHECAGEM, time(), false );

	if ( ! empty( $achados ) && ! $tinha_antes ) {
		// Primeira deteccao (nao tinha nada ontem, tem hoje) — manda na hora, nao espera
		// o proximo lembrete de 12h em 12h.
		atelie_cve_enviar_emails( $achados );
	}
}

/**
 * Monta a lista do que checar: core, plugins de terceiro ativos (os proprios, "atelie-*", nao
 * estao na base do WPScan — pular, senao so gera 404 gastando cota a toa), e o tema ativo.
 *
 * @return array<int, array{tipo: string, slug: string, nome: string, versao: string}>
 */
function atelie_cve_itens_instalados(): array {
	$itens = array();

	$itens[] = array(
		'tipo'   => 'core',
		'slug'   => str_replace( '.', '', get_bloginfo( 'version' ) ),
		'nome'   => 'WordPress core',
		'versao' => get_bloginfo( 'version' ),
	);

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$todos_plugins  = get_plugins();
	$plugins_ativos = get_option( 'active_plugins', array() );

	foreach ( $plugins_ativos as $caminho ) {
		if ( ! isset( $todos_plugins[ $caminho ] ) ) {
			continue;
		}
		$slug = strtok( $caminho, '/' );
		if ( str_starts_with( $slug, 'atelie-' ) ) {
			continue; // plugin proprio, nao existe na base do WPScan.
		}
		$itens[] = array(
			'tipo'   => 'plugin',
			'slug'   => $slug,
			'nome'   => $todos_plugins[ $caminho ]['Name'],
			'versao' => $todos_plugins[ $caminho ]['Version'],
		);
	}

	$tema = wp_get_theme();
	if ( ! str_starts_with( $tema->get_stylesheet(), 'atelie-' ) ) {
		$itens[] = array(
			'tipo'   => 'tema',
			'slug'   => $tema->get_stylesheet(),
			'nome'   => $tema->get( 'Name' ),
			'versao' => $tema->get( 'Version' ),
		);
	}

	return $itens;
}

/**
 * Consulta um item na API do WPScan e devolve so as vulnerabilidades que realmente afetam a
 * versao instalada (fixed_in maior que a versao atual, ou nunca corrigida).
 *
 * @param array{tipo: string, slug: string, nome: string, versao: string} $item Item a checar.
 *
 * @return array<int, array{titulo: string, fixed_in: ?string, url: string}>
 */
function atelie_cve_consultar_item( array $item, string $token ): array {
	$base = match ( $item['tipo'] ) {
		'core'   => 'wordpresses',
		'plugin' => 'plugins',
		'tema'   => 'themes',
	};

	$resposta = wp_remote_get(
		"https://wpscan.com/api/v3/{$base}/{$item['slug']}",
		array(
			'headers' => array( 'Authorization' => 'Token token=' . $token ),
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $resposta ) || 200 !== wp_remote_retrieve_response_code( $resposta ) ) {
		return array(); // item desconhecido da base (404) ou erro de rede — pula, nao falha.
	}

	$corpo = json_decode( wp_remote_retrieve_body( $resposta ), true );
	if ( ! is_array( $corpo ) || empty( $corpo ) ) {
		return array();
	}

	// A resposta da API vem sempre com uma unica chave no nivel raiz (a versao ou o slug
	// consultado) contendo os dados do item — reset() pega esse valor sem precisar saber a chave.
	$dados                     = reset( $corpo );
	$vulnerabilidades_afetando = array();

	foreach ( $dados['vulnerabilities'] ?? array() as $vulnerabilidade ) {
		$fixed_in = $vulnerabilidade['fixed_in'] ?? null;
		$afetado  = empty( $fixed_in ) || version_compare( $item['versao'], $fixed_in, '<' );

		if ( $afetado ) {
			$vulnerabilidades_afetando[] = array(
				'titulo'   => $vulnerabilidade['title'],
				'fixed_in' => $fixed_in,
				'url'      => $vulnerabilidade['references']['url'][0] ?? '',
			);
		}
	}

	return $vulnerabilidades_afetando;
}

/**
 * Roda 2x/dia — se ainda tiver vulnerabilidade pendente da ultima verificacao, insiste e
 * reenvia. Nao consulta a API de novo (so le o que ja foi salvo), pra nao gastar cota.
 */
function atelie_cve_enviar_lembrete_se_pendente(): void {
	$achados = get_option( ATELIE_CVE_OPTION_RESULTADO, array() );
	if ( ! empty( $achados ) ) {
		atelie_cve_enviar_emails( $achados );
	}
}

/**
 * @param array<int, array{nome: string, versao_instalada: string, vulnerabilidades: array}> $achados
 */
function atelie_cve_enviar_emails( array $achados ): void {
	$lista_tecnica = '';
	foreach ( $achados as $item ) {
		$lista_tecnica .= sprintf( '<p><strong>%s</strong> (versão instalada: %s)</p><ul>', esc_html( $item['nome'] ), esc_html( $item['versao_instalada'] ) );
		foreach ( $item['vulnerabilidades'] as $vulnerabilidade ) {
			$lista_tecnica .= sprintf(
				'<li>%s%s%s</li>',
				esc_html( $vulnerabilidade['titulo'] ),
				$vulnerabilidade['fixed_in'] ? ' — corrigido na versão ' . esc_html( $vulnerabilidade['fixed_in'] ) : ' — sem correção disponível ainda',
				$vulnerabilidade['url'] ? ' (<a href="' . esc_url( $vulnerabilidade['url'] ) . '">detalhes</a>)' : ''
			);
		}
		$lista_tecnica .= '</ul>';
	}

	$mailer = WC()->mailer();

	$mailer->send(
		ATELIE_CVE_EMAIL_ADMIN,
		'[SEGURANÇA URGENTE] Vulnerabilidade conhecida encontrada no site',
		$mailer->wrap_message(
			'Vulnerabilidade conhecida encontrada',
			'<p>A verificação diária encontrou item(ns) instalado(s) com vulnerabilidade conhecida sem correção aplicada:</p>' . $lista_tecnica . '<p>Atualize o(s) item(ns) acima assim que possível.</p>'
		),
		"Content-Type: text/html\r\n"
	);

	$mailer->send(
		ATELIE_CVE_EMAIL_NEGOCIO,
		'[URGENTE] Assunto de segurança do site — precisa da atenção do administrador',
		$mailer->wrap_message(
			'Atenção: assunto de segurança',
			'<p>O site encontrou um problema de <strong>segurança</strong> que precisa da atenção da pessoa responsável pela parte técnica o quanto antes.</p><p>Por favor, entre em contato com o administrador do site (' . esc_html( ATELIE_CVE_EMAIL_ADMIN ) . ') e confirme que ele já está ciente.</p>'
		),
		"Content-Type: text/html\r\n"
	);
}

/**
 * Aviso fixo no painel — sem checar capability nenhuma de proposito, pra QUALQUER usuario
 * logado ver (inclusive a Artesã), que pode avisar o administrador se ele mesmo nao tiver
 * visto o e-mail.
 */
add_action(
	'admin_notices',
	function (): void {
		$achados = get_option( ATELIE_CVE_OPTION_RESULTADO, array() );
		if ( empty( $achados ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>Segurança:</strong> o site encontrou ' . count( $achados ) . ' item(ns) com vulnerabilidade conhecida sem correção. Avise o administrador do site se ele ainda não estiver ciente — um e-mail já foi enviado pra ' . esc_html( ATELIE_CVE_EMAIL_ADMIN ) . '.</p></div>';
	}
);
