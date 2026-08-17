<?php
/**
 * Bedrock wp-config.php — toda a config real vive em config/application.php
 * Isso mantem o wp-config.php estavel e versionavel sem segredo nenhum aqui.
 */

require_once dirname(__DIR__) . '/config/application.php';

require_once ABSPATH . 'wp-settings.php';
