<?php
/**
 * Case individual do portfolio — sem preco, sem botao de comprar (por design,
 * ver plano do projeto). Se o visitante quiser algo parecido, o caminho e o
 * formulario de orcamento personalizado ([atelie_orcamento]), linkado abaixo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="site-container">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class(); ?>>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'large' ); ?>
			<?php endif; ?>
			<div><?php the_content(); ?></div>

			<?php
			$galeria_ids = get_post_meta( get_the_ID(), '_atelie_case_galeria', true );
			$galeria_ids = $galeria_ids ? array_filter( array_map( 'absint', explode( ',', (string) $galeria_ids ) ) ) : array();
			?>
			<?php if ( ! empty( $galeria_ids ) ) : ?>
				<div class="atelie-case-galeria">
					<?php foreach ( $galeria_ids as $imagem_id ) : ?>
						<?php echo wp_get_attachment_image( $imagem_id, 'medium' ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>

	<div class="atelie-case-cta">
		<p><?php esc_html_e( 'Gostou e quer algo parecido?', 'atelie-theme' ); ?></p>
		<a class="atelie-btn" href="<?php echo esc_url( home_url( '/orcamento-personalizado/' ) ); ?>">
			<?php esc_html_e( 'Solicite um orçamento personalizado', 'atelie-theme' ); ?>
		</a>
	</div>
</div>

<?php get_footer(); ?>
