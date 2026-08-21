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
