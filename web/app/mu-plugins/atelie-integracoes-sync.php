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

if (!defined('ABSPATH')) {
    exit;
}

use function Env\env;

add_action('init', function (): void {
    // --- Mercado Pago ---
    $mp_public_key = env('MERCADOPAGO_PUBLIC_KEY');
    $mp_access_token = env('MERCADOPAGO_ACCESS_TOKEN');
    $mp_sandbox = filter_var(env('MERCADOPAGO_SANDBOX'), FILTER_VALIDATE_BOOLEAN);

    if (!empty($mp_public_key) && !empty($mp_access_token)) {
        if ($mp_sandbox) {
            update_option('_mp_public_key_test', $mp_public_key);
            update_option('_mp_access_token_test', $mp_access_token);
        } else {
            update_option('_mp_public_key_prod', $mp_public_key);
            update_option('_mp_access_token_prod', $mp_access_token);
        }
    }

    // --- Melhor Envio ---
    $me_token = env('MELHORENVIO_TOKEN');
    $me_sandbox = filter_var(env('MELHORENVIO_SANDBOX'), FILTER_VALIDATE_BOOLEAN);

    if (!empty($me_token)) {
        update_option('wpmelhorenvio_token_environment', $me_sandbox ? 'sandbox' : 'production');
        if ($me_sandbox) {
            update_option('wpmelhorenvio_token_sandbox', $me_token);
        } else {
            update_option('wpmelhorenvio_token', $me_token);
        }
    }
}, 20); // depois que os plugins tiverem terminado de registrar seus proprios defaults
