<?php
/**
 * Front controller do Bedrock — o core do WordPress fica em web/wp/, este
 * arquivo e o unico ponto de entrada publico que aponta pra la.
 */

define('WP_USE_THEMES', true);

require __DIR__ . '/wp/wp-blog-header.php';
