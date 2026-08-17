<?php
/**
 * Plugin Name: Atelie Bootstrap
 * Description: Hardening e configuracoes base do site do ateliê (carregado sempre, nao pode ser desativado pelo admin).
 * Version: 0.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Papel "Vendedora" — acesso restrito a produto/pedido, sem acesso a
 * plugins, temas, configuracoes de pagamento ou usuarios.
 * Ver plano do projeto, secao "Papeis e permissoes no painel".
 */
add_action('init', function (): void {
    $capabilities_version = '2';

    if (get_option('atelie_vendedora_caps_version') === $capabilities_version) {
        return;
    }

    remove_role('atelie_vendedora');
    add_role('atelie_vendedora', 'Vendedora', [
        'read' => true,
        'upload_files' => true,
        // Produtos
        'edit_products' => true,
        'edit_published_products' => true,
        'publish_products' => true,
        'read_private_products' => true,
        // Pedidos (inclui gerar etiqueta de frete via Melhor Envio na tela do pedido)
        'edit_shop_orders' => true,
        'edit_others_shop_orders' => true,
        'read_private_shop_orders' => true,
        // Portfolio/cases
        'edit_atelie_cases' => true,
        'edit_published_atelie_cases' => true,
        'publish_atelie_cases' => true,
        'read_private_atelie_cases' => true,
    ]);

    update_option('atelie_vendedora_caps_version', $capabilities_version);

    // capability_type customizado (atelie_case) nao é herdado pelo Administrador
    // automaticamente — sem isso, nem o admin consegue gerenciar o portfolio.
    $administrator = get_role('administrator');
    if ($administrator !== null) {
        foreach (['edit_atelie_case', 'read_atelie_case', 'delete_atelie_case', 'edit_atelie_cases', 'edit_others_atelie_cases', 'publish_atelie_cases', 'read_private_atelie_cases', 'delete_atelie_cases', 'delete_private_atelie_cases', 'delete_published_atelie_cases', 'delete_others_atelie_cases', 'edit_private_atelie_cases', 'edit_published_atelie_cases'] as $cap) {
            $administrator->add_cap($cap);
        }
    }
});

/**
 * Campos de disponibilidade e prazo de producao — a maioria dos produtos do
 * ateliê é feita sob encomenda. Ver plano, secao "Disponibilidade — pronta
 * entrega vs. sob encomenda". Fica no admin nativo do WooCommerce ate a
 * Fase 3 substituir por uma tela propria.
 */
add_action('woocommerce_product_options_general_product_data', function (): void {
    woocommerce_wp_select([
        'id' => '_atelie_disponibilidade',
        'label' => __('Disponibilidade', 'atelie-theme'),
        'options' => [
            'pronta_entrega' => __('Pronta entrega', 'atelie-theme'),
            'sob_encomenda' => __('Sob encomenda', 'atelie-theme'),
        ],
    ]);

    woocommerce_wp_text_input([
        'id' => '_atelie_prazo_producao',
        'label' => __('Prazo de produção (dias)', 'atelie-theme'),
        'type' => 'number',
        'custom_attributes' => ['min' => '0', 'step' => '1'],
        'desc_tip' => true,
        'description' => __('Preencher só quando "Sob encomenda". Somado ao prazo de frete na comunicação ao cliente.', 'atelie-theme'),
    ]);
});

add_action('woocommerce_process_product_meta', function (int $post_id): void {
    if (isset($_POST['_atelie_disponibilidade'])) {
        $disponibilidade = sanitize_text_field(wp_unslash($_POST['_atelie_disponibilidade']));
        if (in_array($disponibilidade, ['pronta_entrega', 'sob_encomenda'], true)) {
            update_post_meta($post_id, '_atelie_disponibilidade', $disponibilidade);
        }
    }

    if (isset($_POST['_atelie_prazo_producao'])) {
        $prazo = absint(wp_unslash($_POST['_atelie_prazo_producao']));
        update_post_meta($post_id, '_atelie_prazo_producao', $prazo);
    }
});

/**
 * Portfolio/cases — vitrine de trabalhos sob encomenda, sem preco/compra,
 * separada do catalogo. Ver plano, secao "Portfolio/cases e encomenda
 * personalizada". Galeria de multiplas fotos por case fica para quando a
 * pagina de listagem for desenhada (Fase 1, design ainda pendente) — por
 * ora, uma foto de destaque por case ja cobre o uso inicial.
 */
add_action('init', function (): void {
    register_post_type('atelie_case', [
        'label' => __('Cases', 'atelie-theme'),
        'labels' => [
            'name' => __('Cases', 'atelie-theme'),
            'singular_name' => __('Case', 'atelie-theme'),
            'add_new_item' => __('Novo case', 'atelie-theme'),
        ],
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'portfolio'],
        'menu_icon' => 'dashicons-images-alt2',
        'supports' => ['title', 'editor', 'thumbnail'],
        'capability_type' => ['atelie_case', 'atelie_cases'],
        'map_meta_cap' => true,
    ]);
});
