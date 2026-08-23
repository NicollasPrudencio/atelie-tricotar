<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="site-container atelie-404">
	<div class="atelie-404__card">
		<p class="atelie-404__numero">404</p>
		<h1><?php esc_html_e( 'Essa página não existe (mais)', 'atelie-theme' ); ?></h1>
		<p class="atelie-404__texto">
			<?php esc_html_e( 'O link pode estar desatualizado, ou o produto que você procurava já não está mais na loja. Mas o resto do ateliê continua por aqui:', 'atelie-theme' ); ?>
		</p>
		<div class="atelie-404__acoes">
			<a class="atelie-404__botao atelie-404__botao--primario" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
				<?php esc_html_e( 'Ver a loja', 'atelie-theme' ); ?>
			</a>
			<a class="atelie-404__botao" href="<?php echo esc_url( home_url( '/portfolio/' ) ); ?>">
				<?php esc_html_e( 'Ver o portfólio', 'atelie-theme' ); ?>
			</a>
			<a class="atelie-404__botao" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php esc_html_e( 'Voltar ao início', 'atelie-theme' ); ?>
			</a>
		</div>
	</div>
</div>

<?php get_footer(); ?>
