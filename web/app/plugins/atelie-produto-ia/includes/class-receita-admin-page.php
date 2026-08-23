<?php
/**
 * Tela "Buscar/Traduzir Receita" — ajuda quem cadastra um produto a lidar
 * com receita/padrão em outro idioma. Duas ferramentas separadas, de
 * propósito:
 * - Buscar: a IA pesquisa na web e aponta candidatos (título + fonte +
 *   resumo curto), nunca reproduz a receita inteira — risco de direito
 *   autoral do criador do padrão. Quem usa o painel abre a fonte e traz o
 *   texto ela mesma.
 * - Traduzir: cola o texto que já tem (de qualquer fonte, em qualquer
 *   idioma) e a IA traduz fielmente, sem resumir.
 * O resultado (texto traduzido) não entra automático em nenhum produto —
 * quem usa o painel copia e cola em "Novo Produto" no campo de receita.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Receita_Admin_Page {

	private const SLUG               = 'atelie-receita';
	private const TRANSIENT_BUSCA    = 'atelie_receita_busca_';
	private const TRANSIENT_TRADUCAO = 'atelie_receita_traducao_';

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );
		add_action( 'admin_post_atelie_buscar_receita', array( $this, 'buscar_receita' ) );
		add_action( 'admin_post_atelie_traduzir_receita', array( $this, 'traduzir_receita' ) );
	}

	public function adicionar_menu(): void {
		add_submenu_page(
			'edit.php?post_type=product',
			'Buscar/Traduzir Receita',
			'Receita em outro idioma',
			'edit_products',
			self::SLUG,
			array( $this, 'renderizar' )
		);
	}

	public function renderizar(): void {
		$usuario_id      = get_current_user_id();
		$resultado_busca = get_transient( self::TRANSIENT_BUSCA . $usuario_id );
		$resultado_trad  = get_transient( self::TRANSIENT_TRADUCAO . $usuario_id );
		delete_transient( self::TRANSIENT_BUSCA . $usuario_id );
		delete_transient( self::TRANSIENT_TRADUCAO . $usuario_id );
		?>
		<div class="wrap atelie-novo-produto">
			<h1>Receita em outro idioma
			<?php
			if ( class_exists( 'Atelie_Ajuda_Drawer' ) ) {
				Atelie_Ajuda_Drawer::render(
					'Receita em outro idioma',
					array(
						'"Buscar" pede pra IA procurar na web um padrão parecido com o que você descrever — ela mostra só o título, a fonte e um resumo curto, nunca a receita inteira (evita problema de direito autoral). Abra o link da fonte pra pegar o texto de verdade.',
						'"Traduzir" pega um texto que você já tem (colado de qualquer lugar, em qualquer idioma) e traduz pro português, fiel ao original.',
						'O texto traduzido não vai direto pro produto — copie e cole no campo de receita/padrão da tela "Novo Produto".',
					),
					'/admin/receita-em-outro-idioma/'
				);
			}
			?>
			</h1>

			<div class="atelie-card" style="margin-bottom:1.5rem;">
				<h2>🔎 Buscar um padrão</h2>
				<p>Descreva o que você procura (ex.: "amigurumi de elefante sentado").</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="atelie_buscar_receita">
					<?php wp_nonce_field( 'atelie_buscar_receita', 'atelie_busca_nonce' ); ?>
					<input type="text" name="descricao" style="width:100%; max-width:500px;" placeholder="O que você está procurando?" required>
					<button type="submit" class="button button-primary">Buscar</button>
				</form>

				<?php if ( is_array( $resultado_busca ) ) : ?>
					<hr>
					<?php if ( empty( $resultado_busca['ok'] ) ) : ?>
						<p class="atelie-status atelie-status-erro">⚠️ <?php echo esc_html( $resultado_busca['mensagem'] ); ?></p>
					<?php else : ?>
						<p><em>Lembrete: são só indicações — confira na fonte se o criador permite vender peças feitas a partir do padrão dela antes de usar comercialmente.</em></p>
						<table class="widefat striped" style="max-width:800px;">
							<thead>
								<tr>
									<th>Título</th>
									<th>Resumo</th>
									<th>Fonte</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $resultado_busca['resultados'] as $item ) : ?>
									<tr>
										<td><?php echo esc_html( $item['titulo'] ); ?></td>
										<td><?php echo esc_html( $item['resumo'] ); ?></td>
										<td><a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">Abrir fonte ↗</a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="atelie-card">
				<h2>🔤 Traduzir um texto que você já tem</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="atelie_traduzir_receita">
					<?php wp_nonce_field( 'atelie_traduzir_receita', 'atelie_traducao_nonce' ); ?>
					<textarea name="texto_original" rows="6" style="width:100%; max-width:700px;" placeholder="Cole aqui o texto da receita/padrão, em qualquer idioma" required></textarea><br>
					<button type="submit" class="button button-primary">Traduzir</button>
				</form>

				<?php if ( is_array( $resultado_trad ) ) : ?>
					<hr>
					<?php if ( empty( $resultado_trad['ok'] ) ) : ?>
						<p class="atelie-status atelie-status-erro">⚠️ <?php echo esc_html( $resultado_trad['mensagem'] ); ?></p>
					<?php else : ?>
						<p><strong>Texto traduzido</strong> (copie e cole no campo de receita de "Novo Produto"):</p>
						<textarea readonly rows="8" style="width:100%; max-width:700px;"><?php echo esc_textarea( $resultado_trad['texto_traduzido'] ); ?></textarea>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function buscar_receita(): void {
		if (
			! current_user_can( 'edit_products' )
			|| ! isset( $_POST['atelie_busca_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_busca_nonce'] ) ), 'atelie_buscar_receita' )
		) {
			wp_die( 'Ação não permitida.' );
		}

		$descricao = isset( $_POST['descricao'] ) ? sanitize_text_field( wp_unslash( $_POST['descricao'] ) ) : '';
		$servico   = Atelie_Ai_Vision_Service_Factory::criar();
		$resultado = $descricao !== ''
			? $servico->buscarReceita( $descricao )
			: array(
				'ok'         => false,
				'resultados' => array(),
				'mensagem'   => 'Descreva o que você procura antes de buscar.',
			);

		set_transient( self::TRANSIENT_BUSCA . get_current_user_id(), $resultado, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=' . self::SLUG ) );
		exit;
	}

	public function traduzir_receita(): void {
		if (
			! current_user_can( 'edit_products' )
			|| ! isset( $_POST['atelie_traducao_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_traducao_nonce'] ) ), 'atelie_traduzir_receita' )
		) {
			wp_die( 'Ação não permitida.' );
		}

		$texto_original = isset( $_POST['texto_original'] ) ? sanitize_textarea_field( wp_unslash( $_POST['texto_original'] ) ) : '';
		$servico        = Atelie_Ai_Vision_Service_Factory::criar();
		$resultado      = $servico->traduzirReceita( $texto_original );

		set_transient( self::TRANSIENT_TRADUCAO . get_current_user_id(), $resultado, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=' . self::SLUG ) );
		exit;
	}
}
