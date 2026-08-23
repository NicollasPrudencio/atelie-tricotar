<?php
/**
 * Plugin Name: Atelie - Sincronizacao de credenciais (.env -> plugins)
 * Description: Mercado Pago e Melhor Envio guardam as proprias credenciais no banco (nao leem .env sozinhos). Essa ponte sincroniza a partir do .env pra nao depender de digitar chave manualmente em cada ambiente (dev/staging/producao) toda vez que o site for recriado.
 * Version: 0.1.0
 *
 * Nomes de option confirmados lendo o codigo dos proprios plugins:
 * - Mercado Pago: web/app/plugins/woocommerce-mercadopago/src/Hooks/Options.php (COMMON_CONFIGS)
 * - Melhor Envio: web/app/plugins/melhor-envio-cotacao/Models/Token.php
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
	},
	20
); // depois que os plugins tiverem terminado de registrar seus proprios defaults
