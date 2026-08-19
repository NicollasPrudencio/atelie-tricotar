<?php
/**
 * Galeria de portfolio/cases — vitrine, sem preco/compra. Ver plano do projeto,
 * secao "Portfolio/cases e encomenda personalizada". Layout minimo, visual real
 * entra quando o design do site for definido.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="site-container">
    <div class="atelie-secao__cabecalho">
        <span class="atelie-eyebrow"><?php esc_html_e('Sob encomenda', 'atelie-theme'); ?></span>
        <h1><?php esc_html_e('Nosso portfólio', 'atelie-theme'); ?></h1>
        <p><?php esc_html_e('Trabalhos feitos sob medida — não é catálogo de venda direta.', 'atelie-theme'); ?></p>
    </div>

    <?php if (have_posts()) : ?>
        <div class="atelie-case-grid">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('atelie-case-card'); ?>>
                    <a href="<?php the_permalink(); ?>">
                        <?php if (has_post_thumbnail()) : ?>
                            <?php the_post_thumbnail('medium'); ?>
                        <?php endif; ?>
                        <h2><?php the_title(); ?></h2>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p><?php esc_html_e('Nenhum case publicado ainda.', 'atelie-theme'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
