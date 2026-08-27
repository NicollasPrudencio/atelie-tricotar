<?php
/**
 * Plugin Name: Atelie - Traducao do WP 2FA
 * Description: O plugin WP 2FA nunca foi traduzido pra portugues pelos mantenedores (confirmado
 * via `wp language plugin list wp-2fa` — pt_BR nem aparece como opcao "nao instalado"), entao a
 * tela de bloqueio/wizard que toda artesa ve no primeiro login ficava inteira em ingles. Traduz
 * usando o sistema de White Label do proprio plugin (so os textos que quem nao e admin
 * realmente ve, nao as ~1.670 strings do plugin inteiro — a maioria e config que so o admin
 * ve, em ingles mesmo tudo bem) + um filtro de gettext pro punhado de strings que nao passam
 * pelo White Label (ex.: "before %s" na data de expiracao do prazo).
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Semeia so se a option ainda nao existir — se alguem customizar esses textos depois pela
 * propria tela de White Label do WP 2FA, esse valor fica valendo (mesmo padrao ja usado em
 * atelie-integracoes-sync.php).
 */
add_action(
	'init',
	function (): void {
		if ( get_option( 'wp_2fa_white_label', array() ) !== array() ) {
			return;
		}

		update_option(
			'wp_2fa_white_label',
			array(
				'default-text-code-page'              => '<p>Digite abaixo o código de verificação da autenticação de dois fatores (2FA) pra entrar. Dependendo de como o 2FA foi configurado, você pega o código no aplicativo ou ele foi enviado por e-mail.</p><p><strong>Nota: se você deveria ter recebido um e-mail mas não recebeu, clique em "Reenviar código" pra pedir outro.</strong></p>',
				'default-text-pw-reset-code-page'     => '<p>Você recebeu um código de uso único por e-mail. Digite o código abaixo e clique em "Gerar nova senha" pra continuar a redefinição.</p><br><p><strong>Nota: se você não recebeu o código, clique em "Reenviar código". Se ainda assim não chegar, entre em contato com o administrador do site.</strong></p>',
				'default-2fa-required-notice'         => '<p>O administrador deste site exige que você ative a autenticação de dois fatores (2FA) {grace_period_remaining}.</p><br><p>Não configurar o 2FA dentro desse prazo vai bloquear sua conta. Pra mais informações, entre em contato com o administrador do site.</p>',
				'default-2fa-resetup-required-notice' => '<p>O administrador deste site exige que você ative a autenticação de dois fatores (2FA) {grace_period_remaining}.</p><br><p>Não configurar o 2FA dentro desse prazo vai bloquear sua conta. Pra mais informações, entre em contato com o administrador do site.</p>',

				'custom-text-app-code-page'           => '<p>Digite abaixo o código de verificação da autenticação de dois fatores (2FA) pra entrar. Dependendo de como o 2FA foi configurado, você pega o código no aplicativo ou ele foi enviado por e-mail.</p><p><strong>Nota: se você deveria ter recebido um e-mail mas não recebeu, clique em "Reenviar código" pra pedir outro.</strong></p>',
				'custom-text-email-code-page'         => '<p>Digite abaixo o código de verificação da autenticação de dois fatores (2FA) pra entrar. Dependendo de como o 2FA foi configurado, você pega o código no aplicativo ou ele foi enviado por e-mail.</p><p><strong>Nota: se você deveria ter recebido um e-mail mas não recebeu, clique em "Reenviar código" pra pedir outro.</strong></p>',

				'default-backup-code-page'            => 'Digite um código de backup de verificação.',
				'enable_wizard_styling'               => 'enable_wizard_styling',
				'show_help_text'                      => 'show_help_text',
				'enable_wizard_logo'                  => '',
				'hide_page_generated_by'              => false,
				'enable_welcome'                      => '',
				'welcome'                             => '',
				'method_selection'                    => '<h3>Escolha o método de 2FA</h3>',

				'no_further_action'                   => '<h3>Parabéns! Está tudo pronto.</h3>',
				'wp-2fa_required_intro'               => '<h3>Você precisa configurar o 2FA.</h3><p>Pra manter este site — e seus dados — seguros, o administrador deste site exige que você ative a autenticação de dois fatores pra continuar.</p><p>A autenticação de dois fatores garante que só você tem acesso à sua conta, criando uma camada extra de segurança no login — <a href="https://melapress.com/wordpress-2fa/?&utm_source=plugin&utm_medium=wp2fa&utm_campaign=learn_more" target="_blank" rel="noopener">saiba mais</a></p>',
				'wp-2fa_wizard_cancel'                => '<h3>Tem certeza?</h3><p>Qualquer alteração não salva vai ser perdida!</p>',
				'2fa_wizard_logout'                   => '<h3>Tem certeza?</h3><p>Isso vai encerrar sua sessão e desconectar você do WordPress.</p>',

				'custom_css'                          => '',
				'login_custom_css'                    => '',
				'logo-code-page'                      => '',
				'logo-code-page-url'                  => '',
				'disable_login_css'                   => '',
				'login-to-view-area'                  => '<p>Você precisa estar conectado(a) pra ver esta página. {login_url}</p>',

				'user-profile-form-preamble-title'    => 'Configurações de autenticação de dois fatores',
				'user-profile-form-preamble-desc'     => 'Adicione a autenticação de dois fatores pra fortalecer a segurança da sua conta.',
				'use_custom_2fa_message'              => 'use-defaults',
			)
		);
	}
);

/**
 * Strings que o WP 2FA nao expoe pelo sistema de White Label (montadas direto com
 * esc_html__ em outro lugar do codigo) — traduzidas via filtro de gettext, so pro
 * dominio 'wp-2fa', sem precisar de um arquivo .mo pra 1.670 strings.
 */
add_filter(
	'gettext',
	function ( string $traduzido, string $original, string $dominio ) {
		if ( $dominio !== 'wp-2fa' ) {
			return $traduzido;
		}

		$mapa = array(
			'before %s'       => 'antes de %s',
			'no grace period' => 'sem prazo de carência',
		);

		return $mapa[ $original ] ?? $traduzido;
	},
	10,
	3
);
