<?php
/**
 * Plugin Name: Atelie - Faturamento
 * Description: Tela de receita vs custos (IA, taxa Mercado Pago, frete, despesas manuais) e lucro líquido, por período.
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-despesas-manuais.php';
require_once __DIR__ . '/includes/class-relatorio-faturamento.php';
require_once __DIR__ . '/includes/class-faturamento-admin-page.php';

register_activation_hook(__FILE__, ['Atelie_Despesas_Manuais', 'garantir_tabela']);

// Cobre tambem quem ja tinha o plugin ativo antes dessa tabela existir.
add_action('plugins_loaded', ['Atelie_Despesas_Manuais', 'garantir_tabela']);

add_action('plugins_loaded', function (): void {
    (new Atelie_Faturamento_Admin_Page())->registrar();
});
