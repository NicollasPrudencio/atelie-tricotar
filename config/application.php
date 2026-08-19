<?php
/**
 * Configuracao base do WordPress, comum a todos os ambientes.
 * Especificos de cada ambiente ficam em config/environments/{WP_ENV}.php
 */

use Roots\WPConfig\Config;
use function Env\env;

/**
 * Directory containing all of the site's files
 */
$root_dir = dirname(__DIR__);
$webroot_dir = $root_dir . '/web';

/**
 * Use Dotenv to set required environment variables and load .env file in root
 */
$dotenv = Dotenv\Dotenv::createUnsafeImmutable($root_dir);
if (file_exists($root_dir . '/.env')) {
    $dotenv->load();
    $dotenv->required(['WP_HOME', 'WP_SITEURL']);
    if (!env('DATABASE_URL')) {
        $dotenv->required(['DB_NAME', 'DB_USER', 'DB_PASSWORD']);
    }
}

/**
 * Set up our global environment constant and load its config first
 * Default: production
 */
define('WP_ENV', env('WP_ENV') ?: 'production');

/**
 * URLs
 */
Config::define('WP_HOME', env('WP_HOME'));
Config::define('WP_SITEURL', env('WP_SITEURL'));

/**
 * Site vende só pro Brasil, publico e admin em pt_BR sempre — não varia por ambiente.
 */
Config::define('WPLANG', 'pt_BR');

/**
 * Custom Content Directory
 */
Config::define('CONTENT_DIR', '/app');
Config::define('WP_CONTENT_DIR', $webroot_dir . Config::get('CONTENT_DIR'));
Config::define('WP_CONTENT_URL', Config::get('WP_HOME') . Config::get('CONTENT_DIR'));

/**
 * DB settings
 */
Config::define('DB_NAME', env('DB_NAME'));
Config::define('DB_USER', env('DB_USER'));
Config::define('DB_PASSWORD', env('DB_PASSWORD'));
Config::define('DB_HOST', env('DB_HOST') ?: 'localhost');
Config::define('DB_CHARSET', 'utf8mb4');
Config::define('DB_COLLATE', '');
$table_prefix = env('DB_PREFIX') ?: 'wp_';

/**
 * Authentication unique keys and salts
 */
Config::define('AUTH_KEY', env('AUTH_KEY'));
Config::define('SECURE_AUTH_KEY', env('SECURE_AUTH_KEY'));
Config::define('LOGGED_IN_KEY', env('LOGGED_IN_KEY'));
Config::define('NONCE_KEY', env('NONCE_KEY'));
Config::define('AUTH_SALT', env('AUTH_SALT'));
Config::define('SECURE_AUTH_SALT', env('SECURE_AUTH_SALT'));
Config::define('LOGGED_IN_SALT', env('LOGGED_IN_SALT'));
Config::define('NONCE_SALT', env('NONCE_SALT'));

/**
 * Chave de criptografia do plugin WP 2FA. Definida aqui via env de proposito
 * — sem isso o plugin escreve o valor direto no wp-config.php (versionado em
 * git) na hora de ativar. Gerar um valor novo por ambiente, nunca reaproveitar.
 */
Config::define('WP2FA_ENCRYPT_KEY', env('WP2FA_ENCRYPT_KEY'));

/**
 * Custom settings
 */
Config::define('AUTOMATIC_UPDATER_DISABLED', true);
Config::define('DISABLE_WP_CRON', env('DISABLE_WP_CRON') ?: false);
Config::define('DISALLOW_FILE_EDIT', true);

/**
 * Chaves de integracao proprias do ateliê (lidas via env, nunca hardcoded)
 * Uso documentado no plano do projeto, secao "Nota fiscal" e "Plugin custom Criar Produto com IA"
 */
Config::define('ATELIE_AI_VISION_PROVIDER', env('AI_VISION_PROVIDER') ?: 'gemini');
Config::define('ATELIE_AI_VISION_API_KEY', env('AI_VISION_API_KEY'));
Config::define('ATELIE_AI_MOCK_MODE', filter_var(env('AI_MOCK_MODE'), FILTER_VALIDATE_BOOLEAN));
Config::define('ATELIE_AI_BATCH_MAX_ITEMS', (int) (env('AI_BATCH_MAX_ITEMS') ?: 25));
Config::define('ATELIE_NFE_EMISSAO_ATIVA', filter_var(env('NFE_EMISSAO_ATIVA'), FILTER_VALIDATE_BOOLEAN));

/**
 * Carrega config especifica do ambiente atual, se existir
 */
$env_config = __DIR__ . '/environments/' . WP_ENV . '.php';
if (file_exists($env_config)) {
    require_once $env_config;
}

Config::apply();

/**
 * Bootstrap WordPress
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', $webroot_dir . '/wp/');
}
