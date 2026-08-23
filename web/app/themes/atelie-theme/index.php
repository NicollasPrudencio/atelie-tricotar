<?php
/**
 * Template fallback minimo. Templates especificos (front-page, single-product, etc.)
 * entram conforme o design real do site for definido.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="site-container">
	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<div><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nada encontrado.', 'atelie-theme' ); ?></p>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
