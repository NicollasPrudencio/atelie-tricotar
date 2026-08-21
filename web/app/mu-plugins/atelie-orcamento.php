<?php
/**
 * Plugin Name: Atelie - Orcamento Personalizado
 * Description: Formulario de "solicitar orcamento personalizado" (shortcode [atelie_orcamento]) para qualquer servico nao catalogado. Ver plano do projeto, secao "Portfolio/cases e encomenda personalizada".
 * Version: 0.1.0
 *
 * Codado em vez de usar um plugin de formulario (WPForms etc.) de proposito:
 * a config de um form builder fica no banco, nao em git — pra uma peca
 * central do site (captura de lead), preferimos algo versionado.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode(
	'atelie_orcamento',
	function (): string {
		ob_start();

		if ( isset( $_GET['orcamento'] ) && $_GET['orcamento'] === 'enviado' ) {
			?>
		<p class="atelie-orcamento-sucesso">
			<?php esc_html_e( 'Pedido enviado! Vamos te responder em breve pelo contato informado.', 'atelie-theme' ); ?>
		</p>
			<?php
			return (string) ob_get_clean();
		}

		if ( isset( $_GET['orcamento'] ) && $_GET['orcamento'] === 'erro' ) {
			?>
		<p class="atelie-orcamento-erro">
			<?php esc_html_e( 'Não deu pra enviar — confira os campos obrigatórios e tente de novo.', 'atelie-theme' ); ?>
		</p>
			<?php
		}
		?>
	<form class="atelie-orcamento-form" method="post" enctype="multipart/form-data"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="atelie_orcamento_submit">
		<?php wp_nonce_field( 'atelie_orcamento_submit', 'atelie_orcamento_nonce' ); ?>

		<!-- honeypot anti-spam: campo escondido, humano nunca preenche -->
		<div style="position:absolute; left:-9999px;" aria-hidden="true">
			<label>Deixe em branco<input type="text" name="atelie_orcamento_hp" tabindex="-1" autocomplete="off"></label>
		</div>

		<p>
			<label for="atelie-orcamento-nome"><?php esc_html_e( 'Nome', 'atelie-theme' ); ?></label><br>
			<input type="text" id="atelie-orcamento-nome" name="nome" required>
		</p>

		<p>
			<label for="atelie-orcamento-contato"><?php esc_html_e( 'E-mail ou WhatsApp para retorno', 'atelie-theme' ); ?></label><br>
			<input type="text" id="atelie-orcamento-contato" name="contato" required>
		</p>

		<p>
			<label for="atelie-orcamento-descricao"><?php esc_html_e( 'O que você quer encomendar?', 'atelie-theme' ); ?></label><br>
			<textarea id="atelie-orcamento-descricao" name="descricao" rows="4" required></textarea>
		</p>

		<p>
			<label for="atelie-orcamento-referencia"><?php esc_html_e( 'Foto de referência (opcional)', 'atelie-theme' ); ?></label><br>
			<input type="file" id="atelie-orcamento-referencia" name="referencia" accept="image/*">
		</p>

		<button type="submit"><?php esc_html_e( 'Solicitar orçamento', 'atelie-theme' ); ?></button>
	</form>
		<?php
		return (string) ob_get_clean();
	}
);

function atelie_orcamento_handle_submit(): void {
	$redirect_base = wp_get_referer() ?: home_url( '/' );

	$nonce_valido = isset( $_POST['atelie_orcamento_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_orcamento_nonce'] ) ), 'atelie_orcamento_submit' );

	// Honeypot preenchido = bot. Nome/contato/descricao vazios = formulario invalido.
	$honeypot_ok = empty( $_POST['atelie_orcamento_hp'] );
	$nome        = isset( $_POST['nome'] ) ? sanitize_text_field( wp_unslash( $_POST['nome'] ) ) : '';
	$contato     = isset( $_POST['contato'] ) ? sanitize_text_field( wp_unslash( $_POST['contato'] ) ) : '';
	$descricao   = isset( $_POST['descricao'] ) ? sanitize_textarea_field( wp_unslash( $_POST['descricao'] ) ) : '';

	if ( ! $nonce_valido || ! $honeypot_ok || $nome === '' || $contato === '' || $descricao === '' ) {
		wp_safe_redirect( add_query_arg( 'orcamento', 'erro', $redirect_base ) );
		exit;
	}

	$anexo_url = '';
	if ( ! empty( $_FILES['referencia']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload = wp_handle_upload( $_FILES['referencia'], array( 'test_form' => false ) );
		if ( isset( $upload['url'] ) ) {
			$anexo_url = $upload['url'];
		}
	}

	$lead = array(
		'nome'       => $nome,
		'contato'    => $contato,
		'descricao'  => $descricao,
		'anexo_url'  => $anexo_url,
		'enviado_em' => current_time( 'mysql' ),
	);

	$destinatario = apply_filters( 'atelie_orcamento_destinatario', get_option( 'admin_email' ) );

	$corpo = sprintf(
		"Novo pedido de orçamento personalizado.\n\nNome: %s\nContato: %s\nDescrição: %s\nReferência: %s\n",
		$lead['nome'],
		$lead['contato'],
		$lead['descricao'],
		$anexo_url !== '' ? $anexo_url : '(nenhuma)'
	);

	wp_mail( $destinatario, 'Novo pedido de orçamento personalizado', $corpo );

	/**
	 * Ponto de extensao para a Fase 2 (rastreabilidade): disparar o evento
	 * `Lead` no pixel/CAPI a partir daqui quando o tracking estiver implementado.
	 */
	do_action( 'atelie_lead_submitted', $lead );

	wp_safe_redirect( add_query_arg( 'orcamento', 'enviado', $redirect_base ) );
	exit;
}
add_action( 'admin_post_atelie_orcamento_submit', 'atelie_orcamento_handle_submit' );
add_action( 'admin_post_nopriv_atelie_orcamento_submit', 'atelie_orcamento_handle_submit' );

/**
 * Tela de acompanhamento — antes disso, o unico registro de um pedido de
 * orcamento personalizado era o e-mail avulso pro admin, sem nenhuma tela pra
 * rever pedidos antigos. Lacuna confirmada com o usuario em 2026-08-20 (nao
 * era escolha deliberada) — ver CLAUDE.md, secao "Estado atual".
 */
add_action(
	'init',
	function (): void {
		register_post_type(
			'atelie_pedido_orc',
			array(
				'label'           => __( 'Pedidos de Orçamento', 'atelie-theme' ),
				'labels'          => array(
					'name'          => __( 'Pedidos de Orçamento', 'atelie-theme' ),
					'singular_name' => __( 'Pedido de Orçamento', 'atelie-theme' ),
					'all_items'     => __( 'Pedidos de Orçamento', 'atelie-theme' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'menu_icon'       => 'dashicons-format-chat',
				// Mesmo padrao do atelie_case: capability_type proprio + map_meta_cap, capabilities
				// derivadas concedidas explicitamente pra Vendedora e Administrador em
				// atelie-bootstrap.php — "acompanhar pedidos" e trabalho dela, nao so do admin.
				'capability_type' => array( 'atelie_pedido_orc', 'atelie_pedidos_orc' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
			)
		);
	},
	10
);

add_action(
	'atelie_lead_submitted',
	function ( array $lead ): void {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'atelie_pedido_orc',
				'post_title'  => sprintf( '%s — %s', $lead['nome'], gmdate( 'd/m/Y H:i', strtotime( $lead['enviado_em'] ) ) ),
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $post_id ) || $post_id === 0 ) {
			return;
		}

		update_post_meta( $post_id, '_atelie_lead_contato', $lead['contato'] );
		update_post_meta( $post_id, '_atelie_lead_descricao', $lead['descricao'] );
		update_post_meta( $post_id, '_atelie_lead_anexo_url', $lead['anexo_url'] );
		update_post_meta( $post_id, '_atelie_lead_status', 'novo' );
	}
);

add_action(
	'add_meta_boxes',
	function (): void {
		add_meta_box(
			'atelie_lead_dados',
			__( 'Pedido', 'atelie-theme' ),
			function ( WP_Post $post ): void {
				wp_nonce_field( 'atelie_lead_save', 'atelie_lead_nonce' );
				$contato   = get_post_meta( $post->ID, '_atelie_lead_contato', true );
				$descricao = get_post_meta( $post->ID, '_atelie_lead_descricao', true );
				$anexo_url = get_post_meta( $post->ID, '_atelie_lead_anexo_url', true );
				$status    = get_post_meta( $post->ID, '_atelie_lead_status', true ) ?: 'novo';
				?>
			<p>
					<strong><?php esc_html_e( 'Contato (e-mail ou WhatsApp)', 'atelie-theme' ); ?></strong><br>
					<?php echo esc_html( $contato ); ?>
			</p>
			<p>
					<strong><?php esc_html_e( 'O que a cliente quer encomendar', 'atelie-theme' ); ?></strong><br>
					<?php echo nl2br( esc_html( $descricao ) ); ?>
			</p>
				<?php if ( $anexo_url !== '' ) : ?>
				<p>
						<strong><?php esc_html_e( 'Foto de referência', 'atelie-theme' ); ?></strong><br>
						<a href="<?php echo esc_url( $anexo_url ); ?>" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url( $anexo_url ); ?>" alt="" style="max-width:100%; height:auto; border-radius:4px;">
					</a>
				</p>
				<?php endif; ?>
			<p>
					<label><strong><?php esc_html_e( 'Status', 'atelie-theme' ); ?></strong></label><br>
				<select name="atelie_lead_status">
						<option value="novo" <?php selected( $status, 'novo' ); ?>><?php esc_html_e( 'Novo', 'atelie-theme' ); ?></option>
						<option value="respondido" <?php selected( $status, 'respondido' ); ?>><?php esc_html_e( 'Respondido', 'atelie-theme' ); ?></option>
				</select>
			</p>
				<?php
			},
			'atelie_pedido_orc',
			'normal',
			'high'
		);
	}
);

add_action(
	'save_post_atelie_pedido_orc',
	function ( int $post_id ): void {
		if ( ! isset( $_POST['atelie_lead_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_lead_nonce'] ) ), 'atelie_lead_save' ) ) {
			return;
		}
		if ( isset( $_POST['atelie_lead_status'] ) ) {
			update_post_meta( $post_id, '_atelie_lead_status', sanitize_text_field( wp_unslash( $_POST['atelie_lead_status'] ) ) );
		}
	}
);

add_filter(
	'manage_atelie_pedido_orc_posts_columns',
	function ( array $colunas ): array {
		$nova = array();
		foreach ( $colunas as $chave => $rotulo ) {
			$nova[ $chave ] = $rotulo;
			if ( $chave === 'title' ) {
				$nova['atelie_lead_contato'] = __( 'Contato', 'atelie-theme' );
				$nova['atelie_lead_status']  = __( 'Status', 'atelie-theme' );
			}
		}
		return $nova;
	}
);

add_action(
	'manage_atelie_pedido_orc_posts_custom_column',
	function ( string $coluna, int $post_id ): void {
		if ( $coluna === 'atelie_lead_contato' ) {
			echo esc_html( get_post_meta( $post_id, '_atelie_lead_contato', true ) );
			return;
		}
		if ( $coluna === 'atelie_lead_status' ) {
			$status = get_post_meta( $post_id, '_atelie_lead_status', true ) ?: 'novo';
			$rotulo = $status === 'respondido' ? __( 'Respondido', 'atelie-theme' ) : __( 'Novo', 'atelie-theme' );
			$cor    = $status === 'respondido' ? '#6c6072' : '#b3486a';
			printf( '<strong style="color:%s;">%s</strong>', esc_attr( $cor ), esc_html( $rotulo ) );
		}
	},
	10,
	2
);

add_action(
	'admin_notices',
	function (): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'atelie_pedido_orc' || ! class_exists( 'Atelie_Ajuda_Drawer' ) ) {
			return;
		}
		?>
	<div class="notice atelie-ajuda-notice" style="display:flex; align-items:center; gap:4px; padding:10px 12px;">
		<span><?php esc_html_e( 'Precisa de ajuda com esta tela?', 'atelie-theme' ); ?></span>
		<?php
		Atelie_Ajuda_Drawer::render(
			'Pedidos de Orçamento',
			array(
				'Toda vez que alguém envia o formulário "Solicitar orçamento personalizado" do site, um pedido novo aparece aqui — além do e-mail que já chegava antes.',
				'Abra o pedido pra ver o contato, o que a cliente quer encomendar e a foto de referência (se enviou uma).',
				'Depois de responder pelo contato informado, marque o status como "Respondido" — ajuda a saber o que já foi tratado.',
			),
			'/orcamento-personalizado/'
		);
		?>
	</div>
		<?php
	}
);
