<?php
/**
 * Tela "SEO" — a IA gera meta título/descrição (pra resultado de busca do
 * Google) e o alt text da foto principal, em lote, a partir de produtos/cases
 * já publicados. Diferente da tela "Anúncios" (texto pra copiar em outra
 * plataforma), aqui o resultado é salvo direto no produto/case — SEO só faz
 * efeito se estiver de fato no site, não numa área de transferência.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Seo_Admin_Page {

	private const SLUG             = 'atelie-seo';
	private const LIMITE_POR_LOTE  = 20;
	private const TRANSIENT_PREFIX = 'atelie_seo_lote_';
	private const META_TITULO      = '_atelie_seo_meta_titulo';
	private const META_DESCRICAO   = '_atelie_seo_meta_descricao';

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );
		add_action( 'admin_post_atelie_gerar_seo', array( $this, 'gerar_seo' ) );

		add_filter( 'document_title_parts', array( $this, 'filtrar_titulo_documento' ) );
		add_action( 'wp_head', array( $this, 'imprimir_meta_descricao' ), 1 );
	}

	public function adicionar_menu(): void {
		add_submenu_page(
			'edit.php?post_type=product',
			'SEO',
			'SEO',
			'edit_products',
			self::SLUG,
			array( $this, 'renderizar' )
		);
	}

	public function renderizar(): void {
		$lote_id = isset( $_GET['lote'] ) ? sanitize_text_field( wp_unslash( $_GET['lote'] ) ) : '';
		$lote    = $lote_id !== '' ? get_transient( self::TRANSIENT_PREFIX . $lote_id ) : false;

		if ( is_array( $lote ) ) {
			$this->renderizar_resultados( $lote );
			return;
		}

		$this->renderizar_selecao();
	}

	private function renderizar_selecao(): void {
		$itens = $this->itens_publicados();
		?>
		<div class="wrap atelie-novo-produto">
			<h1>SEO
			<?php
			if ( class_exists( 'Atelie_Ajuda_Drawer' ) ) {
				Atelie_Ajuda_Drawer::render(
					'SEO',
					array(
						'A IA gera o título e a descrição que aparecem no resultado de busca do Google, e o texto alternativo (alt text) da foto principal — pensando em atrair tráfego orgânico, sem custo por clique.',
						'Diferente da tela "Anúncios", aqui o resultado já é salvo direto no produto/case, não precisa copiar e colar em lugar nenhum.',
						'Selecione quantos itens quiser (até ' . self::LIMITE_POR_LOTE . ' por vez) e clique em "Gerar SEO". Rodar de novo sobre o mesmo item substitui o que tinha antes.',
					),
					'/admin/seo/'
				);
			}
			?>
			</h1>
			<p>Selecione os produtos/cases já publicados pra gerar SEO (título/descrição de busca + alt text da foto).</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="atelie_gerar_seo">
				<?php wp_nonce_field( 'atelie_gerar_seo', 'atelie_seo_nonce' ); ?>

				<?php if ( empty( $itens ) ) : ?>
					<p>Nenhum produto ou case publicado ainda.</p>
				<?php else : ?>
					<table class="widefat striped" style="max-width:700px;">
						<thead>
							<tr>
								<th style="width:2rem;"></th>
								<th>Item</th>
								<th>Tipo</th>
								<th>SEO já gerado?</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $itens as $item ) : ?>
								<tr>
									<td><input type="checkbox" name="itens[]" value="<?php echo esc_attr( $item['id'] ); ?>"></td>
									<td><?php echo esc_html( $item['titulo'] ); ?></td>
									<td><?php echo esc_html( $item['tipo'] === 'case' ? 'Case' : 'Produto' ); ?></td>
									<td><?php echo $item['tem_seo'] ? '✅' : '—'; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<button type="submit" class="button button-primary button-hero">✨ Gerar SEO</button>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return array<int, array{id: int, titulo: string, tipo: string, tem_seo: bool}>
	 */
	private function itens_publicados(): array {
		$posts = get_posts(
			array(
				'post_type'   => array( 'product', 'atelie_case' ),
				'post_status' => 'publish',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- tela administrativa, catalogo pequeno de um atelie, so limite de seguranca.
				'numberposts' => 200,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		return array_map(
			static function ( WP_Post $post ): array {
				return array(
					'id'      => $post->ID,
					'titulo'  => $post->post_title,
					'tipo'    => $post->post_type === 'atelie_case' ? 'case' : 'produto',
					'tem_seo' => (bool) get_post_meta( $post->ID, self::META_TITULO, true ),
				);
			},
			$posts
		);
	}

	public function gerar_seo(): void {
		if (
			! current_user_can( 'edit_products' )
			|| ! isset( $_POST['atelie_seo_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_seo_nonce'] ) ), 'atelie_gerar_seo' )
		) {
			wp_die( 'Ação não permitida.' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cada id passa por absint() logo abaixo.
		$ids_brutos = isset( $_POST['itens'] ) && is_array( $_POST['itens'] ) ? wp_unslash( $_POST['itens'] ) : array();
		$ids        = array_slice( array_filter( array_map( 'absint', $ids_brutos ) ), 0, self::LIMITE_POR_LOTE );

		if ( empty( $ids ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
			exit;
		}

		$servico    = Atelie_Ai_Vision_Service_Factory::criar();
		$resultados = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post === null ) {
				continue;
			}

			$tipo_objeto = $post->post_type === 'atelie_case' ? 'case' : 'produto';
			$seo         = $servico->sugerirSeo( $post->post_title, wp_strip_all_tags( $post->post_content ), $tipo_objeto );

			$aplicado = $this->aplicar_seo( $id, $seo );

			$resultados[] = array(
				'id'       => $id,
				'titulo'   => $post->post_title,
				'tipo'     => $tipo_objeto,
				'aplicado' => $aplicado,
				'seo'      => $seo,
			);
		}

		$lote_id = wp_generate_password( 12, false );
		set_transient( self::TRANSIENT_PREFIX . $lote_id, $resultados, HOUR_IN_SECONDS );

		wp_safe_redirect( add_query_arg( 'lote', $lote_id, admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * Salva meta título/descrição no post e o alt text na foto principal
	 * (quando existir). Retorna false só quando a IA não devolveu nada útil —
	 * nesse caso nada é sobrescrito, pra não apagar um SEO anterior bom com
	 * um resultado vazio de uma chamada que falhou.
	 *
	 * @param array{meta_titulo: string, meta_descricao: string, alt_text: string} $seo
	 */
	private function aplicar_seo( int $post_id, array $seo ): bool {
		if ( $seo['meta_titulo'] === '' && $seo['meta_descricao'] === '' && $seo['alt_text'] === '' ) {
			return false;
		}

		if ( $seo['meta_titulo'] !== '' ) {
			update_post_meta( $post_id, self::META_TITULO, $seo['meta_titulo'] );
		}

		if ( $seo['meta_descricao'] !== '' ) {
			update_post_meta( $post_id, self::META_DESCRICAO, $seo['meta_descricao'] );
		}

		if ( $seo['alt_text'] !== '' ) {
			$imagem_id = get_post_thumbnail_id( $post_id );
			if ( $imagem_id ) {
				update_post_meta( $imagem_id, '_wp_attachment_image_alt', sanitize_text_field( $seo['alt_text'] ) );
			}
		}

		return true;
	}

	/**
	 * @param array<int, array{id: int, titulo: string, tipo: string, aplicado: bool, seo: array{meta_titulo: string, meta_descricao: string, alt_text: string}}> $lote
	 */
	private function renderizar_resultados( array $lote ): void {
		?>
		<div class="wrap atelie-novo-produto">
			<h1>SEO gerado
			<?php
			if ( class_exists( 'Atelie_Ajuda_Drawer' ) ) {
				Atelie_Ajuda_Drawer::render(
					'SEO',
					array(
						'O título/descrição de busca e o alt text já foram salvos direto no produto/case — não precisa copiar nada.',
						'Se quiser ajustar manualmente depois, edite o produto/case na tela nativa do WordPress (campo de imagem em destaque pro alt text; título/descrição de busca ficam guardados como dado interno, sem tela própria pra editar ainda).',
					),
					'/admin/seo/'
				);
			}
			?>
			</h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ); ?>">← Gerar outro lote</a></p>

			<?php foreach ( $lote as $item ) : ?>
				<div class="atelie-card" style="margin-bottom:1.5rem;">
					<h2><?php echo esc_html( $item['titulo'] ); ?> <small>(<?php echo esc_html( $item['tipo'] === 'case' ? 'case' : 'produto' ); ?>)</small></h2>

					<?php if ( ! $item['aplicado'] ) : ?>
						<p class="atelie-status atelie-status-erro">⚠️ Não deu pra gerar SEO pra esse item agora — tente de novo.</p>
					<?php else : ?>
						<p><strong>Meta título:</strong> <?php echo esc_html( $item['seo']['meta_titulo'] ); ?></p>
						<p><strong>Meta descrição:</strong> <?php echo esc_html( $item['seo']['meta_descricao'] ); ?></p>
						<p><strong>Alt text da foto:</strong> <?php echo esc_html( $item['seo']['alt_text'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param string[] $titulo_partes
	 * @return string[]
	 */
	public function filtrar_titulo_documento( array $titulo_partes ): array {
		if ( ! is_singular( array( 'product', 'atelie_case' ) ) ) {
			return $titulo_partes;
		}

		$meta_titulo = get_post_meta( get_queried_object_id(), self::META_TITULO, true );
		if ( is_string( $meta_titulo ) && $meta_titulo !== '' ) {
			$titulo_partes['title'] = $meta_titulo;
		}

		return $titulo_partes;
	}

	public function imprimir_meta_descricao(): void {
		if ( ! is_singular( array( 'product', 'atelie_case' ) ) ) {
			return;
		}

		$meta_descricao = get_post_meta( get_queried_object_id(), self::META_DESCRICAO, true );
		if ( ! is_string( $meta_descricao ) || $meta_descricao === '' ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( $meta_descricao ) . '">' . "\n";
	}
}
