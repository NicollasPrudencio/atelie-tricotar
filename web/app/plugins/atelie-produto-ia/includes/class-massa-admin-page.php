<?php
/**
 * Tela "Criar em Massa" — alternativa ao Google Drive pra criar vários
 * produtos de uma vez: a pessoa escolhe fotos soltas direto da Biblioteca de
 * Mídia (upload solto, sem organizar em pasta antes), a IA identifica quais
 * fotos são da MESMA peça e sugere os grupos, a pessoa confere/corrige antes
 * de confirmar (diferente da pasta do Drive, aqui o agrupamento é a IA
 * ADIVINHANDO, pode errar — por isso a etapa de revisão antes de criar
 * produto de verdade). Depois de confirmado, cai no mesmo fluxo de sempre
 * (Atelie_Lote_Controller::criar_lote_de_grupos + tela Pendências).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Massa_Admin_Page {

	private const SLUG = 'atelie-criar-massa';

	/**
	 * Limite de fotos por chamada de agrupamento — mesmo valor usado em
	 * Atelie_Rest_Controller::LIMITE_FOTOS_AGRUPAMENTO, repetido aqui só pra
	 * mostrar na tela sem acoplar as duas classes uma na outra.
	 */
	private const LIMITE_FOTOS = 30;

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'carregar_assets' ) );
		add_action( 'admin_post_atelie_massa_criar', array( $this, 'criar' ) );
	}

	public function adicionar_menu(): void {
		add_submenu_page(
			'edit.php?post_type=product',
			'Criar em Massa',
			'Criar em Massa',
			'edit_products',
			self::SLUG,
			array( $this, 'renderizar' )
		);
	}

	public function carregar_assets( string $hook ): void {
		if ( strpos( $hook, self::SLUG ) === false ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'atelie-produto-ia-admin',
			plugins_url( 'assets/admin.css', dirname( __DIR__ ) . '/atelie-produto-ia.php' ),
			array(),
			'0.4.0'
		);

		wp_enqueue_script(
			'atelie-produto-ia-massa',
			plugins_url( 'assets/massa.js', dirname( __DIR__ ) . '/atelie-produto-ia.php' ),
			array( 'jquery', 'wp-util' ),
			'0.1.0',
			true
		);

		wp_localize_script(
			'atelie-produto-ia-massa',
			'atelieMassaIA',
			array(
				'agruparUrl'   => esc_url_raw( rest_url( 'atelie/v1/agrupar-fotos-massa' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'criarUrl'     => esc_url_raw( admin_url( 'admin-post.php' ) ),
				'criarNonce'   => wp_create_nonce( 'atelie_massa_criar' ),
				'limiteFotos'  => self::LIMITE_FOTOS,
				'iaDisponivel' => Atelie_Ai_Config::esta_disponivel(),
			)
		);
	}

	public function renderizar(): void {
		$ia_disponivel = Atelie_Ai_Config::esta_disponivel();
		$status        = Atelie_Ai_Config::obter_status();
		?>
		<div class="wrap atelie-novo-produto atelie-massa">
			<h1>Criar em Massa
			<?php
			Atelie_Ajuda_Drawer::render(
				'Criar em Massa',
				array(
					'Escolha várias fotos soltas da Biblioteca de Mídia (ou faça upload de várias de uma vez) — não precisa estar organizado em pasta.',
					'A IA olha as fotos e sugere quais são da MESMA peça (ângulos diferentes) — confira o resultado antes de confirmar, ela pode errar.',
					'Pode tirar uma foto de um grupo errado antes de criar os produtos — cada foto separada vira um produto próprio.',
					'Depois de confirmar, os produtos entram na tela "Pendências" igual a uma importação do Drive — a IA preenche título, descrição e categoria de cada um em segundo plano.',
					'Limite de ' . self::LIMITE_FOTOS . ' fotos por vez (mandar fotos demais de uma vez fica caro e lento) — pra mais fotos, rode de novo pro próximo grupo.',
				),
				'/novo-produto/'
			);
			?>
			</h1>

			<?php if ( ! $ia_disponivel ) : ?>
				<div class="notice notice-error"><p>⚠️ IA indisponível no momento: <?php echo esc_html( $status['mensagem'] ); ?>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=atelie-config-ia' ) ); ?>">Configurar agora</a>
				<?php endif; ?>
				</p></div>
			<?php endif; ?>

			<div class="atelie-card">
				<h2>1. Escolher fotos</h2>
				<p>
					<button type="button" class="button button-primary" id="atelie-massa-btn-escolher" <?php echo $ia_disponivel ? '' : 'disabled'; ?>>Selecionar fotos da Biblioteca de Mídia</button>
					<span id="atelie-massa-contador">Nenhuma foto selecionada</span>
				</p>
				<div id="atelie-massa-preview" class="atelie-lote-grid"></div>
				<p>
					<button type="button" class="button button-hero" id="atelie-massa-btn-agrupar" disabled>✨ Agrupar com IA</button>
					<span id="atelie-massa-custo-estimado" class="atelie-custo-estimado" style="display:none;"></span>
				</p>
				<div id="atelie-massa-status" style="display:none;"></div>
			</div>

			<div class="atelie-card" id="atelie-massa-revisao" style="display:none;">
				<h2>2. Conferir os grupos sugeridos</h2>
				<p class="description">Cada quadro abaixo vira UM produto. Tire fotos de um grupo errado clicando no "×" — a foto removida vira um produto próprio.</p>
				<div id="atelie-massa-grupos"></div>
				<p>
					<button type="button" class="button button-primary button-hero" id="atelie-massa-btn-criar">Criar <span id="atelie-massa-total-produtos">0</span> produtos</button>
				</p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="atelie-massa-form" style="display:none;">
				<input type="hidden" name="action" value="atelie_massa_criar">
				<?php wp_nonce_field( 'atelie_massa_criar', 'atelie_massa_criar_nonce' ); ?>
				<input type="hidden" name="grupos_json" id="atelie-massa-grupos-json">
			</form>
		</div>
		<?php
	}

	public function criar(): void {
		if (
			! current_user_can( 'edit_products' )
			|| ! isset( $_POST['atelie_massa_criar_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_massa_criar_nonce'] ) ), 'atelie_massa_criar' )
		) {
			wp_die( 'Ação não permitida.' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- json_decode() logo abaixo + absint() em cada valor no foreach mais adiante e a sanitizacao real; nao tem sanitize_text_field pra JSON estruturado.
		$grupos_json   = isset( $_POST['grupos_json'] ) ? wp_unslash( $_POST['grupos_json'] ) : '';
		$grupos_brutos = json_decode( is_string( $grupos_json ) ? $grupos_json : '', true );

		if ( ! is_array( $grupos_brutos ) || empty( $grupos_brutos ) ) {
			wp_safe_redirect( add_query_arg( 'massa_erro', 'sem_grupos', admin_url( 'edit.php?post_type=product&page=' . self::SLUG ) ) );
			exit;
		}

		$lote_controller = new Atelie_Lote_Controller();
		$limite          = $lote_controller->limite_por_lote();

		if ( count( $grupos_brutos ) > $limite ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'massa_erro' => 'limite',
						'limite'     => $limite,
					),
					admin_url( 'edit.php?post_type=product&page=' . self::SLUG )
				)
			);
			exit;
		}

		$grupos = array();
		foreach ( $grupos_brutos as $grupo ) {
			if ( ! is_array( $grupo ) ) {
				continue;
			}
			$ids_do_grupo = array_values( array_filter( array_map( 'absint', $grupo ) ) );
			if ( ! empty( $ids_do_grupo ) ) {
				$grupos[] = $ids_do_grupo;
			}
		}

		if ( empty( $grupos ) ) {
			wp_safe_redirect( add_query_arg( 'massa_erro', 'sem_grupos', admin_url( 'edit.php?post_type=product&page=' . self::SLUG ) ) );
			exit;
		}

		$lote_id = $lote_controller->criar_lote_de_grupos( $grupos );
		wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=atelie-revisar-lote&lote=' . rawurlencode( $lote_id ) ) );
		exit;
	}
}
