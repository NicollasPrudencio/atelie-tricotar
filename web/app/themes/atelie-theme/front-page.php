<?php
/**
 * Página inicial — vitrine (hero + produtos em destaque + chamada pro portfólio e
 * orçamento personalizado). Catálogo completo fica na página "Loja" (WooCommerce nativo).
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<section class="atelie-hero">
    <div class="site-container atelie-hero__conteudo">
        <?php if (has_custom_logo()) : ?>
            <div class="atelie-hero__logo"><?php the_custom_logo(); ?></div>
        <?php endif; ?>

        <h1><?php bloginfo('name'); ?></h1>
        <p class="atelie-hero__tagline">
            <?php esc_html_e('Tricô, crochê e amigurumi feitos à mão, um ponto de cada vez.', 'atelie-theme'); ?>
        </p>

        <div class="atelie-hero__acoes">
            <a class="atelie-btn" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
                <?php esc_html_e('Ver a loja', 'atelie-theme'); ?>
            </a>
            <a class="atelie-btn atelie-btn--fantasma" href="<?php echo esc_url(home_url('/portfolio/')); ?>">
                <?php esc_html_e('Ver portfólio', 'atelie-theme'); ?>
            </a>
        </div>
    </div>
</section>

<?php if (function_exists('wc_get_products')) : ?>
    <section class="atelie-secao">
        <div class="site-container">
            <div class="atelie-secao__cabecalho">
                <span class="atelie-eyebrow"><?php esc_html_e('Recém-chegados', 'atelie-theme'); ?></span>
                <h2><?php esc_html_e('Novidades do ateliê', 'atelie-theme'); ?></h2>
            </div>

            <?php echo do_shortcode('[products limit="8" columns="4" orderby="date"]'); ?>
        </div>
    </section>
<?php endif; ?>

<section class="atelie-secao atelie-secao--pessego">
    <div class="site-container atelie-secao__cabecalho">
        <span class="atelie-eyebrow"><?php esc_html_e('Sob encomenda', 'atelie-theme'); ?></span>
        <h2><?php esc_html_e('Não achou o que queria? A gente faz pra você.', 'atelie-theme'); ?></h2>
        <p><?php esc_html_e('Veja trabalhos que já fizemos e peça um orçamento personalizado — qualquer peça, sob medida.', 'atelie-theme'); ?></p>
        <div class="atelie-hero__acoes">
            <a class="atelie-btn" href="<?php echo esc_url(home_url('/orcamento-personalizado/')); ?>">
                <?php esc_html_e('Solicitar orçamento', 'atelie-theme'); ?>
            </a>
            <a class="atelie-btn atelie-btn--fantasma" href="<?php echo esc_url(home_url('/portfolio/')); ?>">
                <?php esc_html_e('Ver portfólio', 'atelie-theme'); ?>
            </a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
