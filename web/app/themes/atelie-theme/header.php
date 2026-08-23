<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="site-container site-header__bar">
		<a class="site-header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<span class="site-header__brand-nome"><?php bloginfo( 'name' ); ?></span>
			<?php endif; ?>
		</a>

		<nav class="site-header__nav" aria-label="<?php esc_attr_e( 'Menu principal', 'atelie-theme' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'fallback_cb'    => function (): void {
						echo '<ul>';
						echo '<li><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Loja', 'atelie-theme' ) . '</a></li>';
						echo '<li><a href="' . esc_url( home_url( '/portfolio/' ) ) . '">' . esc_html__( 'Portfólio', 'atelie-theme' ) . '</a></li>';
						echo '<li><a href="' . esc_url( home_url( '/orcamento-personalizado/' ) ) . '">' . esc_html__( 'Orçamento personalizado', 'atelie-theme' ) . '</a></li>';
						echo '</ul>';
					},
				)
			);
			?>
		</nav>

		<div class="site-header__acoes">
			<?php echo atelie_link_carrinho_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- já escapado em atelie_link_carrinho_html() ?>
			<button type="button" class="site-header__menu-toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Abrir menu', 'atelie-theme' ); ?>">☰</button>
		</div>
	</div>
</header>

<main class="site-main">
