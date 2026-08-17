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

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/application.php';

require_once ABSPATH . 'wp-settings.php';
