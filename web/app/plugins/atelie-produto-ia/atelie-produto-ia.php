<?php
/**
 * Plugin Name: Atelie - Criar Produto com IA
 * Description: Painel simplificado de criacao de produto com autofill por IA de visao. Ver plano do projeto, secao "Plugin custom Criar Produto com IA".
 * Version: 0.2.0
 * Requires Plugins: woocommerce
 *
 * Fluxo individual (upload -> sugestao -> revisar -> publicar) implementado
 * na Fase 3. Criacao em massa e importacao do Google Drive ainda por vir.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/interface-ai-vision-service.php';
require_once __DIR__ . '/includes/class-ai-vision-service-mock.php';
require_once __DIR__ . '/includes/class-ai-vision-service-gemini.php';
require_once __DIR__ . '/includes/class-ai-vision-service-factory.php';
require_once __DIR__ . '/includes/class-rest-controller.php';
require_once __DIR__ . '/includes/class-admin-page.php';

add_action('plugins_loaded', function (): void {
    (new Atelie_Rest_Controller())->registrar();
    (new Atelie_Admin_Page())->registrar();
});
