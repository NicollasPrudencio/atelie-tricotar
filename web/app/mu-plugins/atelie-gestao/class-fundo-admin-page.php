<?php
/**
 * Tela "Fundo de reposição" (menu Gestão) — saldo e livro do fundo que
 * guarda parte do lucro das vendas pra repor patrimônio danificado. Entradas
 * vêm do rateio das vendas (ver tela Vendas) ou de aporte manual; saídas vêm
 * das reposições de patrimônio ou de retirada manual.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Fundo_Admin_Page {

	public const SLUG = 'atelie-fundo';

	private const TIPOS = array(
		'aporte'    => 'Aporte manual',
		'rateio'    => 'Rateio de venda',
		'reposicao' => 'Reposição de patrimônio',
		'retirada'  => 'Retirada manual',
	);

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );
		add_action( 'admin_post_atelie_fundo_lancar', array( $this, 'lancar' ) );
	}

	public function adicionar_menu(): void {
		add_submenu_page( Atelie_Patrimonio_Admin_Page::SLUG, 'Fundo de reposição', 'Fundo de reposição', 'atelie_gestao', self::SLUG, array( $this, 'renderizar' ) );
	}

	public function lancar(): void {
		if (
			! current_user_can( 'atelie_gestao' )
			|| ! isset( $_POST['atelie_gestao_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_gestao_nonce'] ) ), 'atelie_fundo_lancar' )
		) {
			wp_die( 'Ação não permitida.' );
		}

		$retirada  = isset( $_POST['tipo'] ) && $_POST['tipo'] === 'retirada';
		$valor     = Atelie_Gestao_Util::parse_valor( isset( $_POST['valor'] ) ? sanitize_text_field( wp_unslash( $_POST['valor'] ) ) : '' );
		$descricao = isset( $_POST['descricao'] ) ? sanitize_text_field( wp_unslash( $_POST['descricao'] ) ) : '';
		$data      = Atelie_Gestao_Util::data_valida( isset( $_POST['data'] ) ? sanitize_text_field( wp_unslash( $_POST['data'] ) ) : '' );

		$destino = admin_url( 'admin.php?page=' . self::SLUG );
		if ( $valor <= 0 || $descricao === '' || ( $retirada && $valor > Atelie_Fundo_Repo::saldo() ) ) {
			wp_safe_redirect( add_query_arg( 'erro', '1', $destino ) );
			exit;
		}

		Atelie_Fundo_Repo::lancar( $retirada ? 'retirada' : 'aporte', $retirada ? -$valor : $valor, $descricao, $data );
		wp_safe_redirect( add_query_arg( 'ok', '1', $destino ) );
		exit;
	}

	public function renderizar(): void {
		$saldo      = Atelie_Fundo_Repo::saldo();
		$movimentos = Atelie_Fundo_Repo::movimentos();
		?>
		<div class="wrap atelie-gestao">
			<h1>Fundo de reposição
			<?php
			Atelie_Ajuda_Drawer::render(
				'Fundo de reposição',
				array(
					'É o "cofrinho" do ateliê pra repor patrimônio que estraga: uma parte de cada venda (rateio) entra aqui automaticamente.',
					'O saldo é a soma do livro abaixo: entradas em verde, saídas em vermelho. Reposições de patrimônio pagas com o fundo aparecem sozinhas.',
					'Dá pra lançar aporte (entra dinheiro) ou retirada (sai) manualmente, sempre com uma descrição.',
				),
				'/gestao/fundo-de-reposicao/'
			);
			?>
			</h1>

			<?php if ( isset( $_GET['ok'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Lançamento registrado.</p></div>
			<?php elseif ( isset( $_GET['erro'] ) ) : ?>
				<div class="notice notice-error"><p>Não deu pra lançar — informe valor e descrição (e uma retirada não pode passar do saldo).</p></div>
			<?php endif; ?>

			<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 18px;display:inline-block;margin:10px 0 16px;">
				<span style="color:#6c6072;font-size:12px;">Saldo atual</span><br>
				<strong style="font-size:1.8rem;color:<?php echo $saldo >= 0 ? '#1a7f37' : '#c0392b'; ?>;"><?php echo esc_html( Atelie_Gestao_Util::moeda( $saldo ) ); ?></strong>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 16px;margin-bottom:18px;">
				<input type="hidden" name="action" value="atelie_fundo_lancar">
				<?php wp_nonce_field( 'atelie_fundo_lancar', 'atelie_gestao_nonce' ); ?>
				<strong>Lançamento manual</strong><br>
				<select name="tipo">
					<option value="aporte">Aporte (entra dinheiro)</option>
					<option value="retirada">Retirada (sai dinheiro)</option>
				</select>
				<input type="text" name="valor" required inputmode="decimal" placeholder="Valor (R$)" style="width:120px;">
				<input type="text" name="descricao" required placeholder="Descrição (obrigatória)" style="width:280px;">
				<input type="date" name="data" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>">
				<button type="submit" class="button button-primary">Lançar</button>
			</form>

			<?php if ( empty( $movimentos ) ) : ?>
				<p>Nenhum lançamento ainda.</p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:820px;">
					<thead><tr><th>Data</th><th>Tipo</th><th>Descrição</th><th style="text-align:right;">Valor</th></tr></thead>
					<tbody>
						<?php foreach ( $movimentos as $mov ) : ?>
							<tr>
								<td><?php echo esc_html( Atelie_Gestao_Util::data_br( $mov->data_mov ) ); ?></td>
								<td><?php echo esc_html( self::TIPOS[ $mov->tipo ] ?? $mov->tipo ); ?></td>
								<td><?php echo esc_html( $mov->descricao ); ?></td>
								<td style="text-align:right;color:<?php echo (float) $mov->valor >= 0 ? '#1a7f37' : '#c0392b'; ?>;"><?php echo esc_html( Atelie_Gestao_Util::moeda( (float) $mov->valor ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
