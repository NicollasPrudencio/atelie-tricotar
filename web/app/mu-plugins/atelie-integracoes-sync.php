<?php
/**
 * Plugin Name: Atelie - Sincronizacao de credenciais (.env -> plugins)
 * Description: Mercado Pago, Melhor Envio e o endereco da loja guardam os proprios valores no
 * banco (nao leem .env sozinhos). Essa ponte sincroniza a partir do .env pra nao depender de
 * digitar isso manualmente em cada ambiente (dev/staging/producao) toda vez que o site for
 * recriado — foi exatamente essa lacuna que deixou o endereco da loja em branco na primeira
 * instalacao de producao (2026-08-24).
 * Version: 0.2.0
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

add_action(
	'init',
	function (): void {
		// --- Mercado Pago ---
		$mp_public_key   = env( 'MERCADOPAGO_PUBLIC_KEY' );
		$mp_access_token = env( 'MERCADOPAGO_ACCESS_TOKEN' );
		$mp_sandbox      = filter_var( env( 'MERCADOPAGO_SANDBOX' ), FILTER_VALIDATE_BOOLEAN );

		if ( ! empty( $mp_public_key ) && ! empty( $mp_access_token ) ) {
			if ( $mp_sandbox ) {
				update_option( '_mp_public_key_test', $mp_public_key );
				update_option( '_mp_access_token_test', $mp_access_token );
			} else {
				update_option( '_mp_public_key_prod', $mp_public_key );
				update_option( '_mp_access_token_prod', $mp_access_token );
			}

			// Ter credencial nao é suficiente — cada metodo de pagamento do Mercado
			// Pago precisa ser habilitado individualmente, senao o checkout mostra
			// "nenhum metodo de pagamento disponivel" mesmo com tudo configurado.
			foreach ( array( 'woo-mercado-pago-basic', 'woo-mercado-pago-pix', 'woo-mercado-pago-custom', 'woo-mercado-pago-ticket' ) as $gateway_id ) {
				$option_name = 'woocommerce_' . $gateway_id . '_settings';
				$settings    = get_option( $option_name, array() );
				if ( ! is_array( $settings ) ) {
					$settings = array();
				}
				if ( ( $settings['enabled'] ?? '' ) !== 'yes' ) {
					$settings['enabled'] = 'yes';
					update_option( $option_name, $settings );
				}
			}
		}

		// --- Melhor Envio ---
		$me_token   = env( 'MELHORENVIO_TOKEN' );
		$me_sandbox = filter_var( env( 'MELHORENVIO_SANDBOX' ), FILTER_VALIDATE_BOOLEAN );

		if ( ! empty( $me_token ) ) {
			update_option( 'wpmelhorenvio_token_environment', $me_sandbox ? 'sandbox' : 'production' );
			if ( $me_sandbox ) {
				update_option( 'wpmelhorenvio_token_sandbox', $me_token );
			} else {
				update_option( 'wpmelhorenvio_token', $me_token );
			}
		}

		// --- Endereco da loja (origem do calculo de frete) ---
		$loja_endereco = env( 'STORE_ADDRESS' );
		$loja_cidade   = env( 'STORE_CITY' );
		$loja_estado   = env( 'STORE_STATE' );
		$loja_cep      = env( 'STORE_POSTCODE' );

		if ( ! empty( $loja_endereco ) && ! empty( $loja_cidade ) && ! empty( $loja_estado ) && ! empty( $loja_cep ) ) {
			update_option( 'woocommerce_store_address', $loja_endereco );
			update_option( 'woocommerce_store_city', $loja_cidade );
			update_option( 'woocommerce_store_postcode', $loja_cep );
			update_option( 'woocommerce_default_country', 'BR:' . $loja_estado );
		}
	},
	20
); // depois que os plugins tiverem terminado de registrar seus proprios defaults
