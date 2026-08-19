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
    add_theme_support('custom-logo', [
        'height' => 120,
        'width' => 120,
        'flex-height' => true,
        'flex-width' => true,
    ]);

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

    wp_enqueue_script(
        'atelie-theme-menu',
        get_theme_file_uri('/assets/menu.js'),
        [],
        wp_get_theme()->get('Version'),
        true
    );
});

/**
 * O wrapper padrao do WooCommerce abre seu proprio <main id="main" class="site-main">,
 * o que duplicaria o <main> que header.php/footer.php ja abrem pro tema inteiro (blog,
 * portfolio, etc). Troca pelo wrapper simples do tema (mesma <div class="site-container">
 * usada nos outros templates) pra manter HTML valido e o layout consistente em toda a loja.
 */
add_action('init', function (): void {
    remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
    remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);

    add_action('woocommerce_before_main_content', function (): void {
        echo '<div class="site-container woocommerce-content">';
    }, 10);

    add_action('woocommerce_after_main_content', function (): void {
        echo '</div>';
    }, 10);

    /**
     * O design é de coluna única, de propósito (sem sidebar) — sem remover isso,
     * o WooCommerce chama get_sidebar('shop'), o tema não tem sidebar.php, e o
     * WordPress cai no fallback legado (theme-compat/sidebar.php): um aviso de
     * "obsoleto" + busca/lista de páginas cruas, sem estilo nenhum, no fim da
     * página de loja/produto.
     */
    remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar');
});

/**
 * Link do carrinho no cabeçalho, com contador de itens — usado em header.php.
 * Fica fora do template pra poder checar function_exists(WooCommerce) num só lugar.
 */
function atelie_link_carrinho_html(): string
{
    if (!function_exists('WC') || WC()->cart === null) {
        return '';
    }

    $quantidade = WC()->cart->get_cart_contents_count();

    return sprintf(
        '<a class="site-header__carrinho" href="%s">🧺 %s (%d)</a>',
        esc_url(wc_get_cart_url()),
        esc_html__('Carrinho', 'atelie-theme'),
        (int) $quantidade
    );
}
