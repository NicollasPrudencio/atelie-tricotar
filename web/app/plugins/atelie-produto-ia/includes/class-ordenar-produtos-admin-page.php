<?php
/**
 * Tela "Ordenar Produtos" — lista arrastar-e-soltar pra decidir em que ordem
 * os produtos aparecem na loja. Grava no menu_order nativo (mesma coisa que
 * o WooCommerce usa pra "Ordenação personalizada", já configurada como
 * padrão da loja — ver Atelie_Rest_Controller::reordenar_produtos()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Ordenar_Produtos_Admin_Page {

	private const SLUG = 'atelie-ordenar-produtos';

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'carregar_assets' ) );
	}

	public function adicionar_menu(): void {
		add_submenu_page(
			'edit.php?post_type=product',
			'Ordenar Produtos',
			'Ordenar Produtos',
			'edit_products',
			self::SLUG,
			array( $this, 'renderizar' )
		);
	}

	public function carregar_assets( string $hook ): void {
		if ( strpos( $hook, self::SLUG ) === false ) {
			return;
		}

		wp_enqueue_style(
			'atelie-produto-ia-admin',
			plugins_url( 'assets/admin.css', dirname( __DIR__ ) . '/atelie-produto-ia.php' ),
			array(),
			'0.4.0'
		);

		wp_enqueue_script(
			'atelie-produto-ia-ordenar-produtos',
			plugins_url( 'assets/ordenar-produtos.js', dirname( __DIR__ ) . '/atelie-produto-ia.php' ),
			array( 'jquery', 'jquery-ui-sortable' ),
			'0.1.0',
			true
		);

		wp_localize_script(
			'atelie-produto-ia-ordenar-produtos',
			'atelieOrdenarProdutos',
			array(
				'reordenarUrl' => esc_url_raw( rest_url( 'atelie/v1/reordenar-produtos' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	public function renderizar(): void {
		$produtos = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'menu_order title',
				'order'       => 'ASC',
			)
		);
		?>
		<div class="wrap atelie-novo-produto">
			<h1>Ordenar Produtos
			<?php
			Atelie_Ajuda_Drawer::render(
				'Ordenar Produtos',
				array(
					'Arraste um produto pra cima ou pra baixo, pela linha inteira, pra mudar a posição.',
					'A ordem salva aqui é a mesma que aparece na loja pra quem visita o site (ordenação "Personalizada" — já é o padrão configurado).',
					'Salva sozinho a cada vez que você solta um produto numa posição nova — não precisa de um botão "Salvar" separado.',
					'Só mostra produtos já publicados — os que ainda estão pendentes de revisão não aparecem aqui.',
				),
				'/ordenar-produtos/'
			);
			?>
			</h1>

			<p class="description">Arraste os produtos pela lista abaixo pra mudar a ordem em que aparecem na loja.</p>
			<p id="atelie-ordenar-status" class="atelie-status atelie-status-inline" style="display:none;"></p>

			<?php if ( empty( $produtos ) ) : ?>
				<p>Nenhum produto publicado ainda.</p>
			<?php else : ?>
				<ul id="atelie-ordenar-lista" class="atelie-ordenar-lista">
					<?php foreach ( $produtos as $produto ) : ?>
						<li class="atelie-ordenar-item" data-produto-id="<?php echo esc_attr( (string) $produto->ID ); ?>">
							<span class="atelie-ordenar-alca" aria-hidden="true">⠿</span>
							<?php echo get_the_post_thumbnail( $produto->ID, 'thumbnail', array( 'class' => 'atelie-ordenar-thumb' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() ja escapa os atributos internamente. ?>
							<span class="atelie-ordenar-titulo"><?php echo esc_html( $produto->post_title ); ?></span>
							<span class="atelie-ordenar-preco"><?php echo wp_kses_post( wc_price( get_post_meta( $produto->ID, '_price', true ) ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
