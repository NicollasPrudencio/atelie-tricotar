<?php
/**
 * Plugin Name: Atelie - Sincronizacao de credenciais (.env -> plugins)
 * Description: Mercado Pago, Melhor Envio e o endereco da loja guardam os proprios valores no
 * banco (nao leem .env sozinhos). Essa ponte SEMEIA a partir do .env na primeira vez (ambiente
 * novo nasce ja configurado), mas nunca mais sobrescreve depois que o valor existir — se uma
 * pessoa real trocar algo pelo painel (ex.: via wizard de configuracao ou tela nativa do
 * plugin), esse valor fica valendo pra sempre, sem risco do proximo deploy pisar em cima.
 * Ver memoria de projeto sobre esse comportamento (2026-08-25).
 * Version: 0.3.0
 *
 * Nomes de option confirmados lendo o codigo dos proprios plugins:
 * - Mercado Pago: web/app/plugins/woocommerce-mercadopago/src/Hooks/Options.php (COMMON_CONFIGS)
 * - Melhor Envio: web/app/plugins/melhor-envio-cotacao/Models/Token.php
 * - Endereco da loja: options nativas do WooCommerce (Ajustes > Geral).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function Env\env;

/**
 * So grava se a option ainda nao tiver valor — semeia ambiente novo, nunca pisa em
 * cima de algo que uma pessoa real ja configurou depois.
 */
function atelie_sync_semear_opcao( string $nome, string $valor ): void {
	$atual = get_option( $nome, '' );
	if ( $atual === '' || $atual === false ) {
		update_option( $nome, $valor );
	}
}

add_action(
	'init',
	function (): void {
		// --- Mercado Pago ---
		$mp_public_key   = env( 'MERCADOPAGO_PUBLIC_KEY' );
		$mp_access_token = env( 'MERCADOPAGO_ACCESS_TOKEN' );
		$mp_sandbox      = filter_var( env( 'MERCADOPAGO_SANDBOX' ), FILTER_VALIDATE_BOOLEAN );

		if ( ! empty( $mp_public_key ) && ! empty( $mp_access_token ) ) {
			if ( $mp_sandbox ) {
				atelie_sync_semear_opcao( '_mp_public_key_test', $mp_public_key );
				atelie_sync_semear_opcao( '_mp_access_token_test', $mp_access_token );
			} else {
				atelie_sync_semear_opcao( '_mp_public_key_prod', $mp_public_key );
				atelie_sync_semear_opcao( '_mp_access_token_prod', $mp_access_token );
			}

			// Ter credencial nao e suficiente — cada metodo de pagamento do Mercado
			// Pago precisa ser habilitado individualmente, senao o checkout mostra
			// "nenhum metodo de pagamento disponivel" mesmo com tudo configurado.
			// So mexe se a option nunca existiu (settings genuinamente novo) — se
			// uma pessoa desligou um metodo de proposito depois, isso fica valendo.
			foreach ( array( 'woo-mercado-pago-basic', 'woo-mercado-pago-pix', 'woo-mercado-pago-custom', 'woo-mercado-pago-ticket' ) as $gateway_id ) {
				$option_name = 'woocommerce_' . $gateway_id . '_settings';
				$settings    = get_option( $option_name, array() );
				if ( ! is_array( $settings ) ) {
					$settings = array();
				}
				if ( ! isset( $settings['enabled'] ) ) {
					$settings['enabled'] = 'yes';
					update_option( $option_name, $settings );
				}
			}
		}

		// --- Melhor Envio ---
		$me_token   = env( 'MELHORENVIO_TOKEN' );
		$me_sandbox = filter_var( env( 'MELHORENVIO_SANDBOX' ), FILTER_VALIDATE_BOOLEAN );

		if ( ! empty( $me_token ) ) {
			atelie_sync_semear_opcao( 'wpmelhorenvio_token_environment', $me_sandbox ? 'sandbox' : 'production' );
			if ( $me_sandbox ) {
				atelie_sync_semear_opcao( 'wpmelhorenvio_token_sandbox', $me_token );
			} else {
				atelie_sync_semear_opcao( 'wpmelhorenvio_token', $me_token );
			}
		}

		// --- Endereco da loja (origem do calculo de frete) ---
		$loja_endereco = env( 'STORE_ADDRESS' );
		$loja_cidade   = env( 'STORE_CITY' );
		$loja_estado   = env( 'STORE_STATE' );
		$loja_cep      = env( 'STORE_POSTCODE' );

		if ( ! empty( $loja_endereco ) && ! empty( $loja_cidade ) && ! empty( $loja_estado ) && ! empty( $loja_cep ) ) {
			atelie_sync_semear_opcao( 'woocommerce_store_address', $loja_endereco );
			atelie_sync_semear_opcao( 'woocommerce_store_city', $loja_cidade );
			atelie_sync_semear_opcao( 'woocommerce_store_postcode', $loja_cep );
			atelie_sync_semear_opcao( 'woocommerce_default_country', 'BR:' . $loja_estado );
		}
	},
	20
); // depois que os plugins tiverem terminado de registrar seus proprios defaults
