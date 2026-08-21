<?php
/**
 * Tela "Anúncios" — a IA gera o texto de anúncio (Meta e TikTok) em lote, a
 * partir de produtos/cases já publicados, pensando em gerar visita e venda
 * (nunca só descrever a peça). Não publica nada sozinha: quem usa o painel
 * copia o texto pro Gerenciador de Anúncios do Meta / TikTok Ads Manager, e
 * pode anexar um vídeo (enviado manualmente, a IA não gera vídeo) pro
 * anúncio de TikTok.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Ads_Admin_Page {

	private const SLUG             = 'atelie-anuncios';
	private const LIMITE_POR_LOTE  = 20;
	private const TRANSIENT_PREFIX = 'atelie_anuncios_lote_';

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );
		add_action( 'admin_post_atelie_gerar_anuncios', array( $this, 'gerar_anuncios' ) );
		add_action( 'admin_post_atelie_anexar_video_tiktok', array( $this, 'anexar_video_tiktok' ) );
	}

	public function adicionar_menu(): void {
		add_menu_page(
			'Anúncios',
			'Anúncios',
			'edit_products',
			self::SLUG,
			array( $this, 'renderizar' ),
			'dashicons-megaphone',
			27
		);
	}

	public function renderizar(): void {
		$lote_id = isset( $_GET['lote'] ) ? sanitize_text_field( wp_unslash( $_GET['lote'] ) ) : '';
		$lote    = $lote_id !== '' ? get_transient( self::TRANSIENT_PREFIX . $lote_id ) : false;

		if ( is_array( $lote ) ) {
			$this->renderizar_resultados( $lote_id, $lote );
			return;
		}

		$this->renderizar_selecao();
	}

	private function renderizar_selecao(): void {
		$itens = $this->itens_publicados();
		?>
		<div class="wrap atelie-novo-produto">
			<h1>Anúncios
			<?php
			if ( class_exists( 'Atelie_Ajuda_Drawer' ) ) {
				Atelie_Ajuda_Drawer::render(
					'Anúncios',
					array(
						'A IA gera o texto do anúncio (Meta e TikTok) a partir do título/descrição já publicados — pensando em gerar visita e venda, não em descrever a peça.',
						'Ela não publica nada sozinha: você copia o texto pro Gerenciador de Anúncios do Meta ou pro TikTok Ads Manager.',
						'A imagem do anúncio é uma foto que já existe do produto/case. Pra TikTok, se você tiver um vídeo, pode anexar depois de gerar os textos — a IA não cria vídeo.',
						'Selecione quantos itens quiser (até ' . self::LIMITE_POR_LOTE . ' por vez) e clique em "Gerar anúncios".',
					),
					'/admin/anuncios/'
				);
			}
			?>
			</h1>
			<p>Selecione os produtos/cases já publicados pra gerar o texto de anúncio (Meta + TikTok).</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="atelie_gerar_anuncios">
				<?php wp_nonce_field( 'atelie_gerar_anuncios', 'atelie_anuncios_nonce' ); ?>

				<?php if ( empty( $itens ) ) : ?>
					<p>Nenhum produto ou case publicado ainda.</p>
				<?php else : ?>
					<table class="widefat striped" style="max-width:700px;">
						<thead>
							<tr>
								<th style="width:2rem;"></th>
								<th>Item</th>
								<th>Tipo</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $itens as $item ) : ?>
								<tr>
									<td><input type="checkbox" name="itens[]" value="<?php echo esc_attr( $item['id'] ); ?>"></td>
									<td><?php echo esc_html( $item['titulo'] ); ?></td>
									<td><?php echo esc_html( $item['tipo'] === 'case' ? 'Case' : 'Produto' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<button type="submit" class="button button-primary button-hero">✨ Gerar anúncios</button>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return array<int, array{id: int, titulo: string, tipo: string}>
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
					'id'     => $post->ID,
					'titulo' => $post->post_title,
					'tipo'   => $post->post_type === 'atelie_case' ? 'case' : 'produto',
				);
			},
			$posts
		);
	}

	public function gerar_anuncios(): void {
		if (
			! current_user_can( 'edit_products' )
			|| ! isset( $_POST['atelie_anuncios_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_anuncios_nonce'] ) ), 'atelie_gerar_anuncios' )
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
			$anuncio     = $servico->gerarAnuncio( $post->post_title, wp_strip_all_tags( $post->post_content ), $tipo_objeto );

			$resultados[] = array(
				'id'         => $id,
				'titulo'     => $post->post_title,
				'tipo'       => $tipo_objeto,
				'imagem_url' => get_the_post_thumbnail_url( $id, 'medium' ),
				'meta'       => $anuncio['meta'],
				'tiktok'     => $anuncio['tiktok'],
				'video_url'  => get_post_meta( $id, '_atelie_anuncio_tiktok_video_url', true ),
			);
		}

		$lote_id = wp_generate_password( 12, false );
		set_transient( self::TRANSIENT_PREFIX . $lote_id, $resultados, HOUR_IN_SECONDS );

		wp_safe_redirect( add_query_arg( 'lote', $lote_id, admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * @param array<int, array<string, mixed>> $lote
	 */
	private function renderizar_resultados( string $lote_id, array $lote ): void {
		?>
		<div class="wrap atelie-novo-produto">
			<h1>Anúncios gerados
			<?php
			if ( class_exists( 'Atelie_Ajuda_Drawer' ) ) {
				Atelie_Ajuda_Drawer::render(
					'Anúncios',
					array(
						'Copie o texto de cada plataforma e cole no Gerenciador de Anúncios do Meta ou no TikTok Ads Manager.',
						'A imagem sugerida é a foto principal do produto/case — baixe ela do site ou use direto na tela de anúncios.',
						'Pra TikTok, se você tiver um vídeo pronto, anexe abaixo do texto — fica salvo no item pra usar quando for anunciar.',
					),
					'/admin/anuncios/'
				);
			}
			?>
			</h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ); ?>">← Gerar outro lote</a></p>

			<?php foreach ( $lote as $item ) : ?>
				<div class="atelie-card" style="margin-bottom:1.5rem;">
					<h2><?php echo esc_html( $item['titulo'] ); ?> <small>(<?php echo esc_html( $item['tipo'] === 'case' ? 'case' : 'produto' ); ?>)</small></h2>

					<?php if ( ! empty( $item['imagem_url'] ) ) : ?>
						<img src="<?php echo esc_url( $item['imagem_url'] ); ?>" alt="" style="max-width:180px; height:auto; border-radius:6px; margin-bottom:1rem;">
					<?php endif; ?>

					<h3>Meta (Instagram/Facebook)</h3>
					<p>
						<strong>Texto principal:</strong><br>
						<textarea readonly rows="3" style="width:100%; max-width:600px;"><?php echo esc_textarea( $item['meta']['texto_principal'] ); ?></textarea>
					</p>
					<p>
						<strong>Título:</strong><br>
						<input type="text" readonly value="<?php echo esc_attr( $item['meta']['titulo'] ); ?>" style="width:100%; max-width:600px;">
					</p>

					<h3>TikTok</h3>
					<p>
						<strong>Legenda:</strong><br>
						<textarea readonly rows="2" style="width:100%; max-width:600px;"><?php echo esc_textarea( $item['tiktok']['legenda'] ); ?></textarea>
					</p>

					<?php if ( ! empty( $item['video_url'] ) ) : ?>
						<p>🎬 Vídeo anexado: <a href="<?php echo esc_url( $item['video_url'] ); ?>" target="_blank" rel="noopener noreferrer">ver vídeo</a></p>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<input type="hidden" name="action" value="atelie_anexar_video_tiktok">
							<input type="hidden" name="post_id" value="<?php echo esc_attr( $item['id'] ); ?>">
							<input type="hidden" name="lote" value="<?php echo esc_attr( $lote_id ); ?>">
							<?php wp_nonce_field( 'atelie_anexar_video_tiktok', 'atelie_video_nonce' ); ?>
							<label>🎬 Anexar vídeo pro TikTok (opcional):
								<input type="file" name="video" accept="video/*">
							</label>
							<button type="submit" class="button">Anexar</button>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function anexar_video_tiktok(): void {
		$lote_id = isset( $_POST['lote'] ) ? sanitize_text_field( wp_unslash( $_POST['lote'] ) ) : '';

		if (
			! current_user_can( 'edit_products' )
			|| ! isset( $_POST['atelie_video_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_video_nonce'] ) ), 'atelie_anexar_video_tiktok' )
		) {
			wp_die( 'Ação não permitida.' );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( $post_id > 0 && ! empty( $_FILES['video']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$anexo_id = media_handle_upload( 'video', $post_id );
			if ( ! is_wp_error( $anexo_id ) ) {
				update_post_meta( $post_id, '_atelie_anuncio_tiktok_video_url', wp_get_attachment_url( $anexo_id ) );

				$lote = get_transient( self::TRANSIENT_PREFIX . $lote_id );
				if ( is_array( $lote ) ) {
					foreach ( $lote as &$item ) {
						if ( (int) $item['id'] === $post_id ) {
							$item['video_url'] = wp_get_attachment_url( $anexo_id );
						}
					}
					unset( $item );
					set_transient( self::TRANSIENT_PREFIX . $lote_id, $lote, HOUR_IN_SECONDS );
				}
			}
		}

		wp_safe_redirect( add_query_arg( 'lote', $lote_id, admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}
}
