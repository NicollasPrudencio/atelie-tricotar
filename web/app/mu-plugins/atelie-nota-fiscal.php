<?php
/**
 * Plugin Name: Atelie - Nota Fiscal (fluxo manual)
 * Description: MEI nao e obrigado a emitir NF-e em venda pra pessoa fisica em 2026 (so se o
 * cliente pedir) — por isso nao existe integracao automatica (Bling/Focus NFe custam mensalidade
 * fixa que nao compensa pro volume esperado). Em vez disso: checkbox no checkout pra cliente
 * pedir a nota, marcacao clara na lista de pedidos pra artesa saber quais pedidos precisam,
 * e um jeito dela subir numero + arquivo da nota (emitida manualmente no portal gratuito da
 * Sefaz) que dispara e-mail automatico pro cliente avisando que chegou.
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ATELIE_NF_META_DESEJA  = '_atelie_deseja_nf';
const ATELIE_NF_META_NUMERO  = '_atelie_nf_numero';
const ATELIE_NF_META_ARQUIVO = '_atelie_nf_arquivo_id';

/**
 * Checkbox opcional no checkout — hoje o checkout so coleta CPF (ver
 * atelie-checkout-campos-br.php), entao isso vale pra toda compra. Se um dia CNPJ for coletado
 * tambem, revisitar aqui pra tornar a nota obrigatoria (nao so opcional) nesse caso.
 */
add_action(
	'init',
	function (): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'atelie/deseja_nf',
				'label'    => 'Desejo receber a nota fiscal desta compra por e-mail',
				'location' => 'order',
				'type'     => 'checkbox',
				'required' => false,
			)
		);
	}
);

add_action(
	'woocommerce_set_additional_field_value',
	function ( string $chave, $valor, string $grupo, $objeto ): void {
		if ( 'atelie/deseja_nf' !== $chave || ! ( $objeto instanceof WC_Order ) ) {
			return;
		}

		$objeto->update_meta_data( ATELIE_NF_META_DESEJA, $valor ? 'sim' : 'nao' );
		$objeto->save_meta_data();
	},
	10,
	4
);

/**
 * Coluna na lista de pedidos (HPOS e legado) — so aparece marcada pra quem pediu nota,
 * pra artesa bater o olho e saber exatamente quais pedidos ainda precisam de nota emitida.
 */
add_filter(
	'woocommerce_shop_order_list_table_columns',
	function ( array $colunas ): array {
		return atelie_nf_inserir_coluna( $colunas );
	}
);
add_filter(
	'manage_edit-shop_order_columns',
	function ( array $colunas ): array {
		return atelie_nf_inserir_coluna( $colunas );
	}
);

function atelie_nf_inserir_coluna( array $colunas ): array {
	$posicao = array();
	foreach ( $colunas as $chave => $rotulo ) {
		$posicao[ $chave ] = $rotulo;
		if ( 'order_status' === $chave ) {
			$posicao['atelie_nf'] = 'Nota Fiscal';
		}
	}
	return isset( $posicao['atelie_nf'] ) ? $posicao : $colunas + array( 'atelie_nf' => 'Nota Fiscal' );
}

add_action(
	'woocommerce_shop_order_list_table_custom_column',
	function ( string $coluna, $pedido ): void {
		if ( 'atelie_nf' === $coluna ) {
			atelie_nf_renderizar_badge( $pedido );
		}
	},
	10,
	2
);
add_action(
	'manage_shop_order_posts_custom_column',
	function ( string $coluna, int $post_id ): void {
		if ( 'atelie_nf' === $coluna ) {
			$pedido = wc_get_order( $post_id );
			if ( $pedido ) {
				atelie_nf_renderizar_badge( $pedido );
			}
		}
	},
	10,
	2
);

function atelie_nf_renderizar_badge( WC_Order $pedido ): void {
	if ( 'sim' !== $pedido->get_meta( ATELIE_NF_META_DESEJA ) ) {
		echo '—';
		return;
	}

	$numero = $pedido->get_meta( ATELIE_NF_META_NUMERO );

	if ( $numero ) {
		printf(
			'<span style="display:inline-block;padding:2px 8px;border-radius:12px;background:#e6f4ea;color:#1e7e34;font-size:12px;font-weight:600;" title="%s">Emitida</span>',
			esc_attr( 'Nota nº ' . $numero )
		);
	} else {
		echo '<span style="display:inline-block;padding:2px 8px;border-radius:12px;background:#fdecea;color:#a94442;font-size:12px;font-weight:600;">Pendente</span>';
	}
}

/**
 * Meta box na tela do pedido (funciona em HPOS e legado via wc_get_page_screen_id) — so
 * aparece pra pedidos onde a cliente pediu a nota. Numero + arquivo (PDF/XML emitido no portal
 * gratuito da Sefaz) sao preenchidos manualmente pela artesa; salvar dispara o e-mail.
 */
add_action(
	'add_meta_boxes',
	function (): void {
		add_meta_box(
			'atelie-nota-fiscal',
			'Nota Fiscal',
			'atelie_nf_renderizar_meta_box',
			wc_get_page_screen_id( 'shop-order' ),
			'side',
			'high'
		);
	}
);

function atelie_nf_renderizar_meta_box( $post_ou_pedido ): void {
	$pedido = ( $post_ou_pedido instanceof WC_Order )
		? $post_ou_pedido
		: wc_get_order( $post_ou_pedido->ID ?? 0 );

	if ( ! $pedido ) {
		return;
	}

	if ( 'sim' !== $pedido->get_meta( ATELIE_NF_META_DESEJA ) ) {
		echo '<p style="color:#666;">A cliente não pediu nota fiscal nesta compra.</p>';
		return;
	}

	wp_nonce_field( 'atelie_nf_salvar', 'atelie_nf_nonce' );
	wp_enqueue_media();

	$numero      = $pedido->get_meta( ATELIE_NF_META_NUMERO );
	$arquivo_id  = (int) $pedido->get_meta( ATELIE_NF_META_ARQUIVO );
	$arquivo_url = $arquivo_id ? wp_get_attachment_url( $arquivo_id ) : '';

	if ( $numero && $arquivo_id ) {
		echo '<p><strong>Nota já emitida e enviada à cliente.</strong></p>';
		printf( '<p>Número: %s<br><a href="%s" target="_blank">Ver arquivo</a></p>', esc_html( $numero ), esc_url( $arquivo_url ) );
		echo '<p style="color:#666;font-size:12px;">Pra substituir (ex.: nota cancelada e reemitida), preencha os campos abaixo de novo e salve — um novo e-mail será enviado.</p>';
	} else {
		echo '<p style="color:#a94442;">Emitir manualmente no portal gratuito da Sefaz do seu estado, depois preencher aqui:</p>';
	}

	?>
	<p>
		<label for="atelie_nf_numero"><strong>Número da nota fiscal</strong></label><br>
		<input type="text" id="atelie_nf_numero" name="atelie_nf_numero" value="<?php echo esc_attr( $numero ); ?>" style="width:100%;">
	</p>
	<p>
		<label><strong>Arquivo da nota (PDF ou XML)</strong></label><br>
		<input type="hidden" id="atelie_nf_arquivo_id" name="atelie_nf_arquivo_id" value="<?php echo esc_attr( $arquivo_id ); ?>">
		<button type="button" class="button" id="atelie_nf_escolher_arquivo">Escolher arquivo</button>
		<span id="atelie_nf_arquivo_nome" style="display:block;margin-top:4px;font-size:12px;color:#666;">
			<?php echo $arquivo_url ? esc_html( basename( $arquivo_url ) ) : 'Nenhum arquivo selecionado.'; ?>
		</span>
	</p>
	<script>
	jQuery(function ($) {
		$('#atelie_nf_escolher_arquivo').on('click', function (e) {
			e.preventDefault();
			var frame = wp.media({ title: 'Escolher arquivo da nota fiscal', multiple: false });
			frame.on('select', function () {
				var anexo = frame.state().get('selection').first().toJSON();
				$('#atelie_nf_arquivo_id').val(anexo.id);
				$('#atelie_nf_arquivo_nome').text(anexo.filename);
			});
			frame.open();
		});
	});
	</script>
	<?php
}

add_action(
	'woocommerce_process_shop_order_meta',
	function ( int $id_pedido ): void {
		if ( ! isset( $_POST['atelie_nf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_nf_nonce'] ) ), 'atelie_nf_salvar' ) ) {
			return;
		}

		$pedido = wc_get_order( $id_pedido );
		if ( ! $pedido || 'sim' !== $pedido->get_meta( ATELIE_NF_META_DESEJA ) ) {
			return;
		}

		$numero_novo  = isset( $_POST['atelie_nf_numero'] ) ? sanitize_text_field( wp_unslash( $_POST['atelie_nf_numero'] ) ) : '';
		$arquivo_novo = isset( $_POST['atelie_nf_arquivo_id'] ) ? (int) $_POST['atelie_nf_arquivo_id'] : 0;

		$numero_anterior  = $pedido->get_meta( ATELIE_NF_META_NUMERO );
		$arquivo_anterior = (int) $pedido->get_meta( ATELIE_NF_META_ARQUIVO );

		$pedido->update_meta_data( ATELIE_NF_META_NUMERO, $numero_novo );
		$pedido->update_meta_data( ATELIE_NF_META_ARQUIVO, $arquivo_novo );
		$pedido->save();

		// So dispara e-mail quando os dois campos estao preenchidos E algo realmente mudou —
		// sem o segundo check, salvar o pedido de novo por qualquer motivo (ex.: so trocar o
		// status) reenviaria o e-mail toda vez, mesmo sem a nota ter mudado.
		$mudou = ( $numero_novo !== $numero_anterior ) || ( $arquivo_novo !== $arquivo_anterior );
		if ( $numero_novo && $arquivo_novo && $mudou ) {
			atelie_nf_enviar_email_cliente( $pedido, $numero_novo, $arquivo_novo );
		}
	}
);

function atelie_nf_enviar_email_cliente( WC_Order $pedido, string $numero, int $arquivo_id ): void {
	$destinatario = $pedido->get_billing_email();
	if ( ! $destinatario ) {
		return;
	}

	$caminho_arquivo = get_attached_file( $arquivo_id );

	$assunto  = sprintf( 'Sua nota fiscal do pedido #%s chegou', $pedido->get_order_number() );
	$conteudo = '<p>Olá, ' . esc_html( $pedido->get_billing_first_name() ) . '!</p>'
		. '<p>A nota fiscal do seu pedido <strong>#' . esc_html( $pedido->get_order_number() ) . '</strong> já foi emitida.</p>'
		. '<p>Número da nota: <strong>' . esc_html( $numero ) . '</strong></p>'
		. '<p>Segue em anexo. Qualquer dúvida, é só responder este e-mail.</p>';

	$mailer   = WC()->mailer();
	$mensagem = $mailer->wrap_message( 'Nota fiscal emitida', $conteudo );

	$mailer->send(
		$destinatario,
		$assunto,
		$mensagem,
		"Content-Type: text/html\r\n",
		$caminho_arquivo ? array( $caminho_arquivo ) : array()
	);
}
