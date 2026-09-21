<?php
/**
 * Tela "Gasto da IA" — mostra quanto já foi gasto em IA no mês corrente (com
 * base no uso real de cada chamada, não uma estimativa vaga) e deixa
 * configurar um teto mensal, tipo o "gasto máximo mensal" do próprio Google
 * AI Studio (pedido explícito do usuário) — só que esse aqui é nosso, roda
 * sem depender de nenhuma API do Google (que não expõe isso publicamente) e
 * bloqueia de verdade toda ação de IA que custa dinheiro assim que
 * atingido, em qualquer tela do painel. Acessível pra Artesã, não só
 * Administrador — diferente da tela "Configurar IA" (chave de API etc.).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Gasto_Ia_Admin_Page {

	private const SLUG = 'atelie-gasto-ia';

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );
		add_action( 'admin_post_atelie_salvar_teto_ia', array( $this, 'salvar_teto' ) );
	}

	public function adicionar_menu(): void {
		add_submenu_page(
			'edit.php?post_type=product',
			'Gasto da IA',
			'Gasto da IA',
			'edit_products',
			self::SLUG,
			array( $this, 'renderizar' )
		);
	}

	public function salvar_teto(): void {
		if (
			! current_user_can( 'edit_products' )
			|| ! isset( $_POST['atelie_teto_ia_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_teto_ia_nonce'] ) ), 'atelie_salvar_teto_ia' )
		) {
			wp_die( 'Ação não permitida.' );
		}

		$bruto = isset( $_POST['teto_mensal'] ) ? str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['teto_mensal'] ) ) ) : '';
		$valor = is_numeric( $bruto ) ? (float) $bruto : null;

		Atelie_Ai_Custo_Tracker::salvar_teto_mensal( $valor );

		wp_safe_redirect( add_query_arg( 'salvo', '1', admin_url( 'edit.php?post_type=product&page=' . self::SLUG ) ) );
		exit;
	}

	public function renderizar(): void {
		$gasto_atual = Atelie_Ai_Custo_Tracker::gasto_mes_atual();
		$teto        = Atelie_Ai_Custo_Tracker::obter_teto_mensal();
		$excedido    = Atelie_Ai_Custo_Tracker::teto_excedido();
		?>
		<div class="wrap atelie-novo-produto">
			<h1>Gasto da IA
			<?php
			Atelie_Ajuda_Drawer::render(
				'Gasto da IA',
				array(
					'Mostra quanto já foi gasto em IA neste mês — calculado a partir do uso real de cada chamada (tokens de verdade), não um chute.',
					'Configure um teto mensal (em reais) pra ter um limite automático — igual o "gasto máximo mensal" do Google AI Studio, só que esse é nosso e roda direto no painel.',
					'Ao atingir o teto, os botões de IA ficam bloqueados em todo o painel (Sugerir, Editar imagem, SEO, Anúncios, etc.) até o mês virar ou alguém aumentar o teto.',
					'Deixe o campo em branco (ou zero) pra não ter nenhum teto — a IA fica sempre disponível, sem limite de gasto automático.',
				),
				'/gasto-da-ia/'
			);
			?>
			</h1>

			<?php if ( isset( $_GET['salvo'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Teto salvo.</p></div>
			<?php endif; ?>

			<div class="atelie-card" style="max-width:480px;">
				<h2>Este mês</h2>
				<p style="font-size:1.8rem; margin:0.25rem 0;">
					R$ <?php echo esc_html( number_format( $gasto_atual, 2, ',', '.' ) ); ?>
					<?php if ( $teto !== null ) : ?>
						<span style="font-size:1rem; color:#6c6072;">/ R$ <?php echo esc_html( number_format( $teto, 2, ',', '.' ) ); ?></span>
					<?php endif; ?>
				</p>

				<?php if ( $teto !== null ) : ?>
					<?php $percentual = $teto > 0 ? min( 100, ( $gasto_atual / $teto ) * 100 ) : 0; ?>
					<div style="background:#f0e6ea; border-radius:999px; height:10px; overflow:hidden; margin:0.75rem 0;">
						<div style="background:<?php echo $excedido ? '#c0392b' : '#b5477a'; ?>; height:100%; width:<?php echo esc_attr( (string) $percentual ); ?>%;"></div>
					</div>
				<?php endif; ?>

				<?php if ( $excedido ) : ?>
					<p class="atelie-status atelie-status-erro">⚠️ Teto atingido — ações de IA estão bloqueadas em todo o painel até o mês virar ou o teto aumentar.</p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="atelie_salvar_teto_ia">
					<?php wp_nonce_field( 'atelie_salvar_teto_ia', 'atelie_teto_ia_nonce' ); ?>
					<p>
						<label for="atelie-teto-mensal">Teto mensal (R$)</label><br>
						<input type="text" name="teto_mensal" id="atelie-teto-mensal" placeholder="Sem teto" style="width:140px;" value="<?php echo esc_attr( $teto !== null ? number_format( $teto, 2, ',', '.' ) : '' ); ?>">
					</p>
					<button type="submit" class="button button-primary">Salvar</button>
				</form>
			</div>
		</div>
		<?php
	}
}
