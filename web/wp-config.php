<?php
/**
 * Bedrock wp-config.php — toda a config real vive em config/application.php
 * Isso mantem o wp-config.php estavel e versionavel sem segredo nenhum aqui.
 *
 * Atencao: o plugin WP 2FA tenta escrever uma constante WP2FA_ENCRYPT_KEY
 * direto aqui na ativacao. Ela e definida via .env em config/application.php
 * de proposito, pra esse arquivo nunca precisar de segredo — se o plugin
 * reinserir a linha por engano, apague e confirme que WP2FA_ENCRYPT_KEY
 * esta no .env.
 */

// Atras de proxy/tunel (Cloudflare Tunnel em dev, proxy do Cloudflare em producao), a conexao
// nginx<->PHP e HTTP mesmo com o publico acessando via HTTPS — sem isso, is_ssl() retorna falso
// e a sessao do carrinho do WooCommerce nao persiste (cookie gravado com o esquema errado).
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/application.php';

require_once ABSPATH . 'wp-settings.php';
