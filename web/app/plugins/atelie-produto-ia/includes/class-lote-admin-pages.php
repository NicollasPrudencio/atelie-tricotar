<?php
/**
 * Tela "Pendências" — status por item de uma criacao em massa (hoje, so
 * originada pela importacao do Google Drive; upload solto sem organizacao
 * foi removido, decisao explicita do usuario). Com `?lote=X` (link direto
 * apos importar), mostra so aquele lote; sem parametro (item de menu),
 * mostra tudo que ainda nao foi revisado, de qualquer lote — pra nada ficar
 * esquecido so por ninguem ter o link de um lote especifico. Tambem "cutuca"
 * itens atrasados a cada visita, ja que quem abre essa tela claramente esta
 * esperando por eles (ver Atelie_Lote_Controller::cutucar_pendentes). Ver
 * plano, secao "Criacao de produtos em massa via IA".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Lote_Admin_Pages {

	/**
	 * URL de "Revisar" de um item pronto — vai pra tela simplificada "Novo
	 * Produto" em modo edição (?produto=ID), não pro editor nativo do
	 * WooCommerce. Usada tanto no card da tela Pendências quanto no
	 * indicador da barra de admin; a REST (class-rest-controller.php,
	 * lote_status()) monta a mesma URL pro polling em JS.
	 */
	public static function url_revisar( int $produto_id ): string {
		return add_query_arg(
			'produto',
			$produto_id,
			admin_url( 'edit.php?post_type=product&page=' . Atelie_Admin_Page::SLUG )
		);
	}

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'carregar_assets' ) );
		add_action( 'admin_post_atelie_marcar_revisado', array( $this, 'marcar_revisado' ) );
		add_action( 'admin_post_atelie_reprocessar_item', array( $this, 'reprocessar_item' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box_revisado' ) );
		add_action( 'admin_bar_menu', array( $this, 'adicionar_admin_bar' ), 100 );
	}

	/**
	 * Indicador minimalista na barra preta do topo (visivel em QUALQUER tela
	 * do wp-admin, nao so na tela Pendencias) — pedido explicito do usuario,
	 * estilo indicador de atividade em nuvem (GCP e afins): fica ali rodando
	 * enquanto a pessoa navega noutra coisa, e ao clicar abre um resumo
	 * rapido sem precisar sair da tela em que estava.
	 */
	public function adicionar_admin_bar( WP_Admin_Bar $admin_bar ): void {
		if ( ! current_user_can( 'edit_products' ) || ! is_admin() ) {
			return;
		}

		$itens = $this->listar_pendentes( 6 );
		$total = $this->contar_pendentes();

		if ( $total === 0 ) {
			return;
		}

		$rotulos = array(
			'processando' => '⏳ Processando',
			'pronto'      => '✨ Pronto pra revisão',
			'erro'        => '⚠️ Erro',
		);

		$admin_bar->add_node(
			array(
				'id'    => 'atelie-pendencias',
				'title' => '⏳ ' . (int) $total . ' pendente' . ( $total > 1 ? 's' : '' ),
				'href'  => admin_url( 'edit.php?post_type=product&page=atelie-revisar-lote' ),
			)
		);

		foreach ( $itens as $item ) {
			$status = get_post_meta( $item->ID, '_atelie_lote_status', true ) ?: 'processando';
			$admin_bar->add_node(
				array(
					'id'     => 'atelie-pendencia-' . $item->ID,
					'parent' => 'atelie-pendencias',
					'title'  => esc_html( get_the_title( $item->ID ) ) . ' — ' . ( $rotulos[ $status ] ?? $status ),
					'href'   => $status === 'pronto' ? self::url_revisar( $item->ID ) : admin_url( 'edit.php?post_type=product&page=atelie-revisar-lote' ),
				)
			);
		}

		if ( $total > count( $itens ) ) {
			$admin_bar->add_node(
				array(
					'id'     => 'atelie-pendencia-ver-todas',
					'parent' => 'atelie-pendencias',
					'title'  => 'Ver todas as ' . (int) $total . ' pendências →',
					'href'   => admin_url( 'edit.php?post_type=product&page=atelie-revisar-lote' ),
				)
			);
		}
	}

	/**
	 * Produtos de lote ainda nao revisados — fonte unica usada tanto pro
	 * numero ao lado do item de menu quanto pro resumo no admin bar, pra dar
	 * pra "ver" que tem pendencia de qualquer tela do painel, sem precisar
	 * estar parado na tela Pendencias esperando (pedido explicito do
	 * usuario). $limite = -1 traz todos (so pra contar); um numero positivo
	 * traz so os N mais recentes (pro resumo do admin bar, que nao precisa
	 * de todos).
	 *
	 * @return array<int, WP_Post>
	 */
	private function listar_pendentes( int $limite = -1 ): array {
		return get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => array( 'draft', 'publish' ),
				'numberposts' => $limite,
				'orderby'     => 'ID',
				'order'       => 'DESC',
				'meta_query'  => array(
					array(
						'key'     => '_atelie_lote_status',
						'value'   => 'revisado',
						'compare' => '!=',
					),
				),
			)
		);
	}

	private function contar_pendentes(): int {
		return count( $this->listar_pendentes( -1 ) );
	}

	public function adicionar_menus(): void {
		$pendentes = $this->contar_pendentes();
		$rotulo    = 'Pendências';
		if ( $pendentes > 0 ) {
			$rotulo .= ' <span class="awaiting-mod count-' . (int) $pendentes . '"><span class="pending-count">' . (int) $pendentes . '</span></span>';
		}

		add_submenu_page(
			'edit.php?post_type=product',
			'Pendências',
			$rotulo,
			'edit_products',
			'atelie-revisar-lote',
			array( $this, 'renderizar_revisao' )
		);
	}

	public function carregar_assets( string $hook ): void {
		if ( strpos( $hook, 'atelie-revisar-lote' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'atelie-produto-ia-admin',
			plugins_url( 'assets/admin.css', dirname( __DIR__ ) . '/atelie-produto-ia.php' ),
			array(),
			'0.5.0'
		);

		wp_enqueue_script(
			'atelie-produto-ia-pendencias',
			plugins_url( 'assets/pendencias.js', dirname( __DIR__ ) . '/atelie-produto-ia.php' ),
			array(),
			'0.1.0',
			true
		);

		wp_localize_script(
			'atelie-produto-ia-pendencias',
			'atelieLoteStatus',
			array(
				'statusUrl' => esc_url_raw( rest_url( 'atelie/v1/lote-status' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'lote'      => isset( $_GET['lote'] ) ? sanitize_text_field( wp_unslash( $_GET['lote'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- so leitura pra montar a URL de status, mesmo padrao ja usado em renderizar_revisao().
			)
		);
	}

	public function renderizar_revisao(): void {
		$lote_id = isset( $_GET['lote'] ) ? sanitize_text_field( wp_unslash( $_GET['lote'] ) ) : '';

		// Item já revisado sai da lista em qualquer uma das duas visões — não tem
		// motivo pra continuar ocupando espaço numa tela de pendência depois de
		// resolvido, mesmo entrando direto pelo link de um lote específico.
		$meta_query = array(
			array(
				'key'     => '_atelie_lote_status',
				'value'   => 'revisado',
				'compare' => '!=',
			),
		);

		if ( $lote_id !== '' ) {
			$meta_query[] = array(
				'key'     => '_atelie_lote_id',
				'value'   => $lote_id,
				'compare' => '=',
			);
			$itens        = get_posts(
				array(
					'post_type'   => 'product',
					'post_status' => array( 'draft', 'publish' ),
					'numberposts' => -1,
					'meta_query'  => $meta_query,
					'orderby'     => 'ID',
					'order'       => 'ASC',
				)
			);
			$titulo       = 'Revisar lote';
		} else {
			// Sem lote especifico (item de menu): tudo que ainda nao foi revisado,
			// de qualquer lote — rede de seguranca pra nada ficar esquecido.
			$itens  = get_posts(
				array(
					'post_type'   => 'product',
					'post_status' => array( 'draft', 'publish' ),
					'numberposts' => -1,
					'meta_query'  => $meta_query,
					'orderby'     => 'ID',
					'order'       => 'DESC',
				)
			);
			$titulo = 'Pendências';
		}

		( new Atelie_Lote_Controller() )->cutucar_pendentes( wp_list_pluck( $itens, 'ID' ) );

		$rotulos = array(
			'processando' => 'Processando',
			'pronto'      => 'Pronto para revisão',
			'revisado'    => 'Revisado',
			'erro'        => 'Erro',
		);
		?>
		<div class="wrap atelie-novo-produto">
			<h1><?php echo esc_html( $titulo ); ?>
			<?php
			Atelie_Ajuda_Drawer::render(
				'Pendências',
				array(
					'Cada quadradinho é um produto candidato importado do Google Drive, com um status: <strong>Processando</strong>, <strong>Pronto para revisão</strong>, <strong>Erro</strong> ou <strong>Revisado</strong>.',
					'Não precisa esperar tudo terminar — pode ir revisando os que já ficaram prontos enquanto o resto processa em segundo plano.',
					'Se algo parecer travado em "Processando", só de abrir esta tela o painel já verifica e força o processamento de itens atrasados. Clique em "Atualizar status" depois de alguns instantes.',
					'Sem um lote específico (como agora, pelo menu), esta tela mostra tudo que está pendente de qualquer importação.',
				),
				'/pendencias/'
			);
			?>
				</h1>

			<?php if ( isset( $_GET['atualizado'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Produto atualizado e publicado! Já está visível no site.</p></div>
			<?php endif; ?>

			<p><button type="button" class="button" onclick="location.reload();">Atualizar status</button> <span id="atelie-lote-polling-aviso" class="description"></span></p>

			<?php if ( empty( $itens ) ) : ?>
				<p>Nada pendente no momento — tudo revisado. 🎉</p>
			<?php else : ?>
				<div class="atelie-lote-progresso" id="atelie-lote-progresso">
					<div class="atelie-lote-progresso-barra"><div class="atelie-lote-progresso-barra-preenchida" id="atelie-lote-progresso-barra" style="width:0%;"></div></div>
					<p class="atelie-lote-progresso-texto" id="atelie-lote-progresso-texto">Verificando…</p>
				</div>
			<?php endif; ?>

			<div class="atelie-lote-grid" id="atelie-lote-grid" data-lote="<?php echo esc_attr( $lote_id ); ?>">
				<?php
				foreach ( $itens as $item ) :
					// Recarrega o status: cutucar_pendentes() pode ter mudado ele agora mesmo.
					$status = get_post_meta( $item->ID, '_atelie_lote_status', true ) ?: 'processando';
					$rotulo = $rotulos[ $status ] ?? $status;
					?>
					<div class="atelie-lote-card" id="atelie-lote-item-<?php echo esc_attr( $item->ID ); ?>" data-item-id="<?php echo esc_attr( $item->ID ); ?>" data-status="<?php echo esc_attr( $status ); ?>">
						<div class="atelie-lote-card-thumb"><?php echo get_the_post_thumbnail( $item->ID, 'thumbnail' ); ?></div>
						<strong class="atelie-lote-card-titulo"><?php echo esc_html( get_the_title( $item->ID ) ); ?></strong>
						<span class="atelie-chip atelie-chip-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $rotulo ); ?></span>

						<div class="atelie-lote-card-acao">
						<?php if ( $status === 'pronto' || $status === 'revisado' ) : ?>
							<p><a class="button" href="<?php echo esc_url( self::url_revisar( $item->ID ) ); ?>">Revisar</a></p>
						<?php elseif ( $status === 'erro' ) : ?>
							<p>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="atelie_reprocessar_item">
									<input type="hidden" name="produto_id" value="<?php echo esc_attr( $item->ID ); ?>">
									<?php wp_nonce_field( 'atelie_reprocessar_item_' . $item->ID, 'atelie_reprocessar_nonce' ); ?>
									<button type="submit" class="button">Tentar novamente</button>
								</form>
							</p>
						<?php else : ?>
							<p><em class="atelie-lote-subtarefa">Aguardando…</em></p>
						<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public function marcar_revisado(): void {
		$produto_id = absint( $_POST['produto_id'] ?? 0 );
		if (
			! current_user_can( 'edit_products' )
			|| ! isset( $_POST['atelie_revisado_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_revisado_nonce'] ) ), 'atelie_marcar_revisado_' . $produto_id )
		) {
			wp_die( 'Ação não permitida.' );
		}

		update_post_meta( $produto_id, '_atelie_lote_status', 'revisado' );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	public function reprocessar_item(): void {
		$produto_id = absint( $_POST['produto_id'] ?? 0 );
		if (
			! current_user_can( 'edit_products' )
			|| ! isset( $_POST['atelie_reprocessar_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_reprocessar_nonce'] ) ), 'atelie_reprocessar_item_' . $produto_id )
		) {
			wp_die( 'Ação não permitida.' );
		}

		( new Atelie_Lote_Controller() )->reprocessar_item( $produto_id );
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}

	public function meta_box_revisado(): void {
		add_meta_box(
			'atelie_lote_revisado',
			'Criação em massa',
			function ( WP_Post $post ): void {
				$lote_id = get_post_meta( $post->ID, '_atelie_lote_id', true );
				if ( ! $lote_id ) {
					return;
				}
				$status   = get_post_meta( $post->ID, '_atelie_lote_status', true );
				$material = get_post_meta( $post->ID, '_atelie_material_tecnica_sugerido', true );
				?>
				<p>Status: <strong><?php echo esc_html( $status ); ?></strong></p>
				<?php if ( $material ) : ?>
					<p>Material/técnica sugerido: <?php echo esc_html( $material ); ?></p>
				<?php endif; ?>
				<?php if ( $status !== 'revisado' ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="atelie_marcar_revisado">
						<input type="hidden" name="produto_id" value="<?php echo esc_attr( $post->ID ); ?>">
						<?php wp_nonce_field( 'atelie_marcar_revisado_' . $post->ID, 'atelie_revisado_nonce' ); ?>
						<button type="submit" class="button">Marcar como revisado</button>
					</form>
				<?php else : ?>
					<p>✓ Já revisado</p>
				<?php endif; ?>
				<?php
			},
			'product',
			'side'
		);
	}
}
