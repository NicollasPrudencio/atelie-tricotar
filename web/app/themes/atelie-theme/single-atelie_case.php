<?php
/**
 * Case individual do portfolio — sem preco, sem botao de comprar (por design,
 * ver plano do projeto). Se o visitante quiser algo parecido, o caminho e o
 * formulario de orcamento personalizado ([atelie_orcamento]), linkado abaixo.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="site-container">
    <?php while (have_posts()) : the_post(); ?>
        <article <?php post_class(); ?>>
            <h1><?php the_title(); ?></h1>
            <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('large'); ?>
            <?php endif; ?>
            <div><?php the_content(); ?></div>
        </article>
    <?php endwhile; ?>

    <p>
        <?php esc_html_e('Gostou e quer algo parecido?', 'atelie-theme'); ?>
        <a href="<?php echo esc_url(home_url('/orcamento-personalizado/')); ?>">
            <?php esc_html_e('Solicite um orçamento personalizado', 'atelie-theme'); ?>
        </a>
    </p>
</div>

<?php get_footer(); ?>
