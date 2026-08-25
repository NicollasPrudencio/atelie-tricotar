<?php
/**
 * Tela "Configuração Inicial" — wizard guiado pra quem não é técnico configurar,
 * um passo de cada vez, o que falta pra habilitar todas as funções do sistema:
 * chave da IA, Meta Pixel e GA4. Cada passo explica onde ir buscar o valor, valida
 * antes de avançar (teste de conexão real pra IA; checagem de formato pra Pixel/GA4,
 * já que esses dois não têm como confirmar de verdade sem token de acesso adicional).
 *
 * Só aparece no menu enquanto tiver algo pendente — depois que tudo estiver
 * configurado, some sozinho (decisão explícita: não vira uma tela de ajuste
 * permanente, é só onboarding). Mercado Pago, Melhor Envio, WPScan e endereço da
 * loja ficam de fora de propósito — esses nascem configurados via secret/deploy,
 * não são passo de wizard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Wizard_Config_Page {

	private const SLUG = 'atelie-config-inicial';

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );
		add_action( 'admin_post_atelie_wizard_salvar_ia', array( $this, 'salvar_ia' ) );
		add_action( 'admin_post_atelie_wizard_salvar_pixel', array( $this, 'salvar_pixel' ) );
		add_action( 'admin_post_atelie_wizard_salvar_ga4', array( $this, 'salvar_ga4' ) );
	}

	public function adicionar_menu(): void {
		if ( empty( self::etapas_pendentes() ) ) {
			return;
		}

		add_menu_page(
			'Configuração Inicial',
			'Configuração Inicial',
			'manage_options',
			self::SLUG,
			array( $this, 'renderizar' ),
			'dashicons-admin-generic',
			2
		);
	}

	/**
	 * @return array<int, string> Slugs das etapas ainda pendentes, na ordem fixa ia -> meta_pixel -> ga4.
	 */
	private static function etapas_pendentes(): array {
		$etapas = array();

		if ( Atelie_Ai_Config::obter_chave() === '' || ! Atelie_Ai_Config::esta_disponivel() ) {
			$etapas[] = 'ia';
		}
		if ( self::obter_pixel_id() === '' ) {
			$etapas[] = 'meta_pixel';
		}
		if ( self::obter_ga4_id() === '' ) {
			$etapas[] = 'ga4';
		}

		return $etapas;
	}

	private static function obter_pixel_id(): string {
		$opcoes = get_option( 'wgact_plugin_options', array() );
		return (string) ( $opcoes['facebook']['pixel_id'] ?? '' );
	}

	private static function obter_ga4_id(): string {
		$opcoes = get_option( 'wgact_plugin_options', array() );
		return (string) ( $opcoes['google']['analytics']['ga4']['measurement_id'] ?? '' );
	}

	private static function salvar_pixel_id( string $valor ): void {
		$opcoes                         = get_option( 'wgact_plugin_options', array() );
		$opcoes['facebook']['pixel_id'] = $valor;
		update_option( 'wgact_plugin_options', $opcoes );
	}

	private static function salvar_ga4_id( string $valor ): void {
		$opcoes = get_option( 'wgact_plugin_options', array() );
		$opcoes['google']['analytics']['ga4']['measurement_id'] = $valor;
		update_option( 'wgact_plugin_options', $opcoes );
	}

	private static function numero_da_etapa( string $slug ): int {
		return array(
			'ia'         => 1,
			'meta_pixel' => 2,
			'ga4'        => 3,
		)[ $slug ] ?? 0;
	}

	public function renderizar(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Você não tem permissão pra acessar esta tela.' );
		}

		$pendentes = self::etapas_pendentes();
		$erro      = isset( $_GET['erro'] ) ? sanitize_text_field( wp_unslash( $_GET['erro'] ) ) : '';

		echo '<div class="wrap atelie-novo-produto"><h1>Configuração Inicial</h1>';

		if ( empty( $pendentes ) ) {
			echo '<p>Tudo pronto! IA, Meta Pixel e GA4 já estão configurados e funcionando.</p></div>';
			return;
		}

		$etapa_atual = $pendentes[0];

		printf( '<p style="color:#666;">Passo %d de 3</p>', (int) self::numero_da_etapa( $etapa_atual ) );

		if ( $erro !== '' ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $erro ) );
		}

		switch ( $etapa_atual ) {
			case 'ia':
				$this->renderizar_etapa_ia();
				break;
			case 'meta_pixel':
				$this->renderizar_etapa_pixel();
				break;
			case 'ga4':
				$this->renderizar_etapa_ga4();
				break;
		}

		echo '</div>';
	}

	private function renderizar_etapa_ia(): void {
		?>
		<h2>Chave da IA (Google Gemini)</h2>
		<ol>
			<li>Acesse <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">aistudio.google.com/app/apikey</a></li>
			<li>Clique em "Create API key" (é gratuito)</li>
			<li>Copie a chave gerada e cole abaixo</li>
		</ol>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'atelie_wizard_ia' ); ?>
			<input type="hidden" name="action" value="atelie_wizard_salvar_ia">
			<p>
				<label for="atelie-wizard-chave-ia"><strong>Chave de API</strong></label><br>
				<input type="text" id="atelie-wizard-chave-ia" name="chave" style="width:420px;max-width:100%;" required>
			</p>
			<button type="submit" class="button button-primary">Validar e continuar</button>
		</form>
		<?php
	}

	private function renderizar_etapa_pixel(): void {
		?>
		<h2>Meta Pixel (Facebook/Instagram)</h2>
		<ol>
			<li>Acesse <a href="https://business.facebook.com" target="_blank" rel="noopener noreferrer">business.facebook.com</a> e entre (ou crie) numa conta Business Manager</li>
			<li>Vá em <strong>Gerenciador de Eventos</strong> → <strong>Conectar fontes de dados</strong> → <strong>Web</strong> → escolha <strong>Meta Pixel</strong></li>
			<li>Dê um nome (ex.: "Ateliê Tricotar") e crie</li>
			<li>O ID do Pixel aparece logo depois — é só um número, cole abaixo</li>
		</ol>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'atelie_wizard_pixel' ); ?>
			<input type="hidden" name="action" value="atelie_wizard_salvar_pixel">
			<p>
				<label for="atelie-wizard-pixel-id"><strong>ID do Meta Pixel</strong></label><br>
				<input type="text" id="atelie-wizard-pixel-id" name="pixel_id" placeholder="Ex.: 1234567890123456" style="width:300px;max-width:100%;" required>
			</p>
			<button type="submit" class="button button-primary">Continuar</button>
		</form>
		<?php
	}

	private function renderizar_etapa_ga4(): void {
		?>
		<h2>Google Analytics 4 (GA4)</h2>
		<ol>
			<li>Acesse <a href="https://analytics.google.com" target="_blank" rel="noopener noreferrer">analytics.google.com</a></li>
			<li>Crie uma conta (se não tiver) → crie uma <strong>propriedade</strong> nova, nome "Ateliê Tricotar"</li>
			<li>Dentro da propriedade: <strong>Administrador</strong> → <strong>Fluxos de dados</strong> → <strong>Adicionar fluxo</strong> → <strong>Web</strong>, coloque a URL <code>atelietricotar.com.br</code></li>
			<li>O <strong>ID de mensuração</strong> aparece no fluxo criado, formato <code>G-XXXXXXXXXX</code> — cole abaixo</li>
		</ol>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'atelie_wizard_ga4' ); ?>
			<input type="hidden" name="action" value="atelie_wizard_salvar_ga4">
			<p>
				<label for="atelie-wizard-ga4-id"><strong>ID de mensuração (GA4)</strong></label><br>
				<input type="text" id="atelie-wizard-ga4-id" name="ga4_id" placeholder="Ex.: G-ABC1234XYZ" style="width:300px;max-width:100%;" required>
			</p>
			<button type="submit" class="button button-primary">Continuar</button>
		</form>
		<?php
	}

	public function salvar_ia(): void {
		check_admin_referer( 'atelie_wizard_ia' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$chave = isset( $_POST['chave'] ) ? sanitize_text_field( wp_unslash( $_POST['chave'] ) ) : '';

		if ( $chave === '' ) {
			$this->redirecionar_com_erro( 'Cole a chave antes de continuar.' );
		}

		Atelie_Ai_Config::salvar( 'gemini', $chave );
		$status = Atelie_Ai_Config::testar_conexao();

		if ( ! $status['ok'] ) {
			$this->redirecionar_com_erro( 'Não conseguimos confirmar essa chave: ' . $status['mensagem'] . ' Confira se copiou certinho e tente de novo.' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	public function salvar_pixel(): void {
		check_admin_referer( 'atelie_wizard_pixel' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$pixel_id = isset( $_POST['pixel_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['pixel_id'] ) ) ) : '';

		if ( ! preg_match( '/^\d{10,20}$/', $pixel_id ) ) {
			$this->redirecionar_com_erro( 'Esse não parece um ID de Pixel válido — deve ser só números (geralmente 15 ou 16 dígitos). Confira no Gerenciador de Eventos e tente de novo.' );
		}

		self::salvar_pixel_id( $pixel_id );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	public function salvar_ga4(): void {
		check_admin_referer( 'atelie_wizard_ga4' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$ga4_id = isset( $_POST['ga4_id'] ) ? strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['ga4_id'] ) ) ) ) : '';

		if ( ! preg_match( '/^G-[A-Z0-9]{4,}$/', $ga4_id ) ) {
			$this->redirecionar_com_erro( 'Esse não parece um ID de mensuração válido — deve começar com "G-". Confira no fluxo de dados do GA4 e tente de novo.' );
		}

		self::salvar_ga4_id( $ga4_id );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	private function redirecionar_com_erro( string $mensagem ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&erro=' . rawurlencode( $mensagem ) ) );
		exit;
	}
}
