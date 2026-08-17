<?php
/**
 * Setup basico do tema. Design/branding ainda por definir — aqui so o necessario
 * pra loja funcionar corretamente (suporte ao WooCommerce e assets).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');

    // Obrigatorio para a loja renderizar corretamente com este tema
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    register_nav_menus([
        'primary' => __('Menu principal', 'atelie-theme'),
    ]);
});

add_action('wp_enqueue_scripts', function (): void {
    wp_enqueue_style(
        'atelie-theme-style',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get('Version')
    );
});
