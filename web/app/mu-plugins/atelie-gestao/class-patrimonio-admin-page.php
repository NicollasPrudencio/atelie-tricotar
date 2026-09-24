<?php
/**
 * Tela "Patrimônio" (menu Gestão) — livro dos bens do ateliê: dar entrada
 * quando algo é comprado, registrar dano, conserto, baixa e reposição (o
 * item novo comprado no lugar, com opção de pagar com o fundo de reposição).
 * Só Gestora e Administrador (capacidade atelie_gestao).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Patrimonio_Admin_Page {

	public const SLUG = 'atelie-patrimonio';

	private const CATEGORIAS = array( 'Ferramenta', 'Equipamento', 'Móvel', 'Expositor / material de feira', 'Informática', 'Outro' );

	private const AVISOS = array(
		'entrada'   => 'Item(ns) cadastrado(s) no patrimônio.',
		'dano'      => 'Dano registrado.',
		'conserto'  => 'Conserto registrado — o item voltou pra "Em uso".',
		'baixa'     => 'Baixa registrada.',
		'reposicao' => 'Reposição registrada — o item novo já entrou no patrimônio.',
	);

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );
		foreach ( array( 'entrada', 'dano', 'conserto', 'baixa', 'reposicao' ) as $acao ) {
			add_action( 'admin_post_atelie_pat_' . $acao, array( $this, $acao ) );
		}
	}

	public function adicionar_menu(): void {
		add_menu_page( 'Patrimônio', 'Gestão', 'atelie_gestao', self::SLUG, array( $this, 'renderizar' ), 'dashicons-portfolio', 58 );
		add_submenu_page( self::SLUG, 'Patrimônio', 'Patrimônio', 'atelie_gestao', self::SLUG, array( $this, 'renderizar' ) );
	}

	private function autorizar( string $acao ): void {
		if (
			! current_user_can( 'atelie_gestao' )
			|| ! isset( $_POST['atelie_gestao_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_gestao_nonce'] ) ), 'atelie_pat_' . $acao )
		) {
			wp_die( 'Ação não permitida.' );
		}
	}

	private function voltar( string $chave, string $valor ): void {
		wp_safe_redirect( add_query_arg( $chave, $valor, admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	private function texto( string $campo ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce ja verificado em autorizar(), chamado antes de qualquer leitura.
		return isset( $_POST[ $campo ] ) ? sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ) : '';
	}

	private function valor( string $campo ): float {
		return Atelie_Gestao_Util::parse_valor( $this->texto( $campo ) );
	}

	public function entrada(): void {
		$this->autorizar( 'entrada' );

		$nome = $this->texto( 'nome' );
		$unit = $this->valor( 'valor' );
		$qtd  = min( 50, max( 1, absint( $this->texto( 'quantidade' ) ) ) );
		if ( $nome === '' || $unit <= 0 ) {
			$this->voltar( 'erro', 'entrada' );
		}

		$data       = Atelie_Gestao_Util::data_valida( $this->texto( 'data' ) );
		$fornecedor = $this->texto( 'fornecedor' );
		for ( $i = 0; $i < $qtd; $i++ ) {
			$id = Atelie_Patrimonio_Repo::criar(
				array(
					'nome'         => $nome,
					'categoria'    => $this->texto( 'categoria' ),
					'valor_compra' => $unit,
					'data_compra'  => $data,
					'fornecedor'   => $fornecedor,
					'local_uso'    => $this->texto( 'local_uso' ),
					'observacao'   => $this->texto( 'observacao' ),
				)
			);
			Atelie_Patrimonio_Repo::registrar_evento( $id, 'entrada', $data, $unit, $fornecedor !== '' ? 'Compra — ' . $fornecedor : 'Compra' );
		}

		$this->voltar( 'ok', 'entrada' );
	}

	public function dano(): void {
		$this->autorizar( 'dano' );

		$id  = absint( $this->texto( 'id' ) );
		$bem = Atelie_Patrimonio_Repo::obter( $id );
		$obs = $this->texto( 'observacao' );
		if ( ! $bem || $bem->status !== 'em_uso' || $obs === '' ) {
			$this->voltar( 'erro', 'dano' );
		}

		Atelie_Patrimonio_Repo::definir_status( $id, 'danificado' );
		Atelie_Patrimonio_Repo::registrar_evento( $id, 'dano', Atelie_Gestao_Util::data_valida( $this->texto( 'data' ) ), 0, $obs );
		$this->voltar( 'ok', 'dano' );
	}

	public function conserto(): void {
		$this->autorizar( 'conserto' );

		$id  = absint( $this->texto( 'id' ) );
		$bem = Atelie_Patrimonio_Repo::obter( $id );
		if ( ! $bem || $bem->status !== 'danificado' ) {
			$this->voltar( 'erro', 'conserto' );
		}

		Atelie_Patrimonio_Repo::definir_status( $id, 'em_uso' );
		Atelie_Patrimonio_Repo::registrar_evento( $id, 'conserto', Atelie_Gestao_Util::data_valida( $this->texto( 'data' ) ), $this->valor( 'valor' ), $this->texto( 'observacao' ) );
		$this->voltar( 'ok', 'conserto' );
	}

	public function baixa(): void {
		$this->autorizar( 'baixa' );

		$id  = absint( $this->texto( 'id' ) );
		$bem = Atelie_Patrimonio_Repo::obter( $id );
		$obs = $this->texto( 'observacao' );
		if ( ! $bem || $bem->status === 'baixado' || $obs === '' ) {
			$this->voltar( 'erro', 'baixa' );
		}

		Atelie_Patrimonio_Repo::definir_status( $id, 'baixado' );
		Atelie_Patrimonio_Repo::registrar_evento( $id, 'baixa', Atelie_Gestao_Util::data_valida( $this->texto( 'data' ) ), 0, $obs );
		$this->voltar( 'ok', 'baixa' );
	}

	public function reposicao(): void {
		$this->autorizar( 'reposicao' );

		$id     = absint( $this->texto( 'id' ) );
		$antigo = Atelie_Patrimonio_Repo::obter( $id );
		$nome   = $this->texto( 'nome' );
		$valor  = $this->valor( 'valor' );
		$abater = $this->valor( 'abater_fundo' );
		if ( ! $antigo || $antigo->status === 'em_uso' || $nome === '' || $valor <= 0 ) {
			$this->voltar( 'erro', 'reposicao' );
		}
		if ( $abater < 0 || $abater > $valor || $abater > Atelie_Fundo_Repo::saldo() ) {
			$this->voltar( 'erro', 'fundo' );
		}

		$data       = Atelie_Gestao_Util::data_valida( $this->texto( 'data' ) );
		$fornecedor = $this->texto( 'fornecedor' );
		$novo_id    = Atelie_Patrimonio_Repo::criar(
			array(
				'nome'         => $nome,
				'categoria'    => (string) $antigo->categoria,
				'valor_compra' => $valor,
				'data_compra'  => $data,
				'fornecedor'   => $fornecedor,
				'local_uso'    => (string) $antigo->local_uso,
				'observacao'   => 'Reposição de ' . $antigo->codigo . ' (' . $antigo->nome . ')',
				'substitui_id' => $id,
			)
		);
		$novo       = Atelie_Patrimonio_Repo::obter( $novo_id );
		$cod        = $novo ? (string) $novo->codigo : '';

		Atelie_Patrimonio_Repo::registrar_evento( $novo_id, 'entrada', $data, $valor, 'Reposição de ' . $antigo->codigo . ( $fornecedor !== '' ? ' — ' . $fornecedor : '' ) );
		Atelie_Patrimonio_Repo::registrar_evento( $id, 'reposicao', $data, $valor, 'Reposto por ' . $cod );
		Atelie_Patrimonio_Repo::definir_status( $id, 'baixado' );

		if ( $abater > 0 ) {
			Atelie_Fundo_Repo::lancar( 'reposicao', -$abater, 'Reposição de ' . $antigo->nome . ' (' . $antigo->codigo . ') por ' . $cod, $data, $novo_id );
		}

		$this->voltar( 'ok', 'reposicao' );
	}

	private function abrir_form( string $acao, int $bem_id = 0 ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="atelie-mini">';
		echo '<input type="hidden" name="action" value="atelie_pat_' . esc_attr( $acao ) . '">';
		wp_nonce_field( 'atelie_pat_' . $acao, 'atelie_gestao_nonce' );
		if ( $bem_id > 0 ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $bem_id ) . '">';
		}
	}

	public function renderizar(): void {
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$bens   = Atelie_Patrimonio_Repo::listar( $status );
		$resumo = Atelie_Patrimonio_Repo::resumo();
		$saldo  = Atelie_Fundo_Repo::saldo();
		$hoje   = wp_date( 'Y-m-d' );
		$ok     = isset( $_GET['ok'] ) ? sanitize_key( wp_unslash( $_GET['ok'] ) ) : '';
		$erro   = isset( $_GET['erro'] ) ? sanitize_key( wp_unslash( $_GET['erro'] ) ) : '';
		?>
		<style>
			.atelie-gestao .atelie-cards { display: flex; flex-wrap: wrap; gap: 12px; margin: 12px 0 18px; }
			.atelie-gestao .atelie-num { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 10px 16px; min-width: 170px; }
			.atelie-gestao .atelie-num strong { display: block; font-size: 1.4rem; }
			.atelie-gestao .atelie-num span { color: #6c6072; font-size: 12px; }
			.atelie-gestao .atelie-caixa { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
			.atelie-gestao .atelie-caixa summary { cursor: pointer; font-weight: 600; }
			.atelie-gestao .atelie-grade { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px 14px; margin: 12px 0; }
			.atelie-gestao .atelie-grade label { display: block; font-size: 12px; color: #50575e; }
			.atelie-gestao .atelie-grade input, .atelie-gestao .atelie-grade select { width: 100%; }
			.atelie-gestao table.widefat td { vertical-align: top; }
			.atelie-gestao .atelie-mini { border-top: 1px dashed #dcdcde; margin-top: 8px; padding-top: 8px; }
			.atelie-gestao .atelie-mini .atelie-grade { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
			.atelie-gestao .st-em_uso { color: #1a7f37; font-weight: 600; }
			.atelie-gestao .st-danificado { color: #b26200; font-weight: 600; }
			.atelie-gestao .st-baixado { color: #8c8f94; font-weight: 600; }
		</style>
		<div class="wrap atelie-gestao">
			<h1>Patrimônio
			<?php
			Atelie_Ajuda_Drawer::render(
				'Patrimônio',
				array(
					'Dê entrada em tudo que o ateliê compra pra funcionar (ferramentas, equipamentos, expositores de feira, móveis) — cada item ganha um código PAT-0001.',
					'Quando algo estraga, use "Registrar dano". Dá pra marcar como consertado ou dar baixa. Pra comprar outro no lugar, use "Repor".',
					'Na reposição dá pra pagar (parte do valor ou tudo) com o Fundo de reposição — o valor sai do saldo e fica registrado.',
				),
				'/gestao/patrimonio/'
			);
			?>
			</h1>

			<?php if ( isset( self::AVISOS[ $ok ] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( self::AVISOS[ $ok ] ); ?></p></div>
			<?php endif; ?>
			<?php if ( $erro === 'fundo' ) : ?>
				<div class="notice notice-error"><p>O valor tirado do fundo não pode passar do saldo do fundo nem do valor do item.</p></div>
			<?php elseif ( $erro !== '' ) : ?>
				<div class="notice notice-error"><p>Não deu pra salvar — confira os campos obrigatórios (nome, valor e, nos danos/baixas, o motivo) e o estado atual do item.</p></div>
			<?php endif; ?>

			<div class="atelie-cards">
				<div class="atelie-num"><strong><?php echo esc_html( Atelie_Gestao_Util::moeda( $resumo['valor_em_uso'] ) ); ?></strong><span>Patrimônio em uso (<?php echo esc_html( (string) $resumo['em_uso'] ); ?> itens)</span></div>
				<div class="atelie-num"><strong><?php echo esc_html( (string) $resumo['danificados'] ); ?></strong><span>Danificado(s) — pendente(s) de conserto/reposição</span></div>
				<div class="atelie-num"><strong><?php echo esc_html( Atelie_Gestao_Util::moeda( $saldo ) ); ?></strong><span>Saldo do <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Atelie_Fundo_Admin_Page::SLUG ) ); ?>">fundo de reposição</a></span></div>
			</div>

			<details class="atelie-caixa" <?php echo empty( $bens ) ? 'open' : ''; ?>>
				<summary>➕ Dar entrada em item comprado</summary>
				<?php $this->abrir_form( 'entrada' ); ?>
					<div class="atelie-grade">
						<label>Item *<input type="text" name="nome" required placeholder="ex.: Máquina de costura"></label>
						<label>Categoria
							<input type="text" name="categoria" list="atelie-pat-categorias" placeholder="escolha ou digite">
							<datalist id="atelie-pat-categorias">
								<?php foreach ( self::CATEGORIAS as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat ); ?>">
								<?php endforeach; ?>
							</datalist>
						</label>
						<label>Quantidade<input type="number" name="quantidade" min="1" max="50" value="1"></label>
						<label>Valor de cada um (R$) *<input type="text" name="valor" required inputmode="decimal" placeholder="0,00"></label>
						<label>Data da compra<input type="date" name="data" value="<?php echo esc_attr( $hoje ); ?>"></label>
						<label>Comprado onde/de quem<input type="text" name="fornecedor"></label>
						<label>Local / responsável<input type="text" name="local_uso" placeholder="ex.: bancada da Janete"></label>
						<label>Observação<input type="text" name="observacao"></label>
					</div>
					<button type="submit" class="button button-primary">Cadastrar no patrimônio</button>
				</form>
			</details>

			<p>
				<?php
				$abas = array_merge( array( '' => 'Todos' ), Atelie_Patrimonio_Repo::STATUS_ROTULOS );
				foreach ( $abas as $chave => $rotulo ) {
					$url = add_query_arg(
						array(
							'page'   => self::SLUG,
							'status' => $chave,
						),
						admin_url( 'admin.php' )
					);
					printf( '<a href="%s" class="button %s">%s</a> ', esc_url( $url ), $status === $chave ? 'button-primary' : '', esc_html( $rotulo ) );
				}
				?>
			</p>

			<?php if ( empty( $bens ) ) : ?>
				<p>Nenhum item aqui ainda.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr><th>Código</th><th>Item</th><th>Categoria</th><th>Comprado em</th><th>Valor</th><th>Situação</th><th>Ações</th></tr>
					</thead>
					<tbody>
						<?php foreach ( $bens as $bem ) : ?>
							<tr>
								<td><?php echo esc_html( $bem->codigo ); ?></td>
								<td>
									<strong><?php echo esc_html( $bem->nome ); ?></strong>
									<?php if ( $bem->local_uso !== '' ) : ?>
										<br><small><?php echo esc_html( $bem->local_uso ); ?></small>
									<?php endif; ?>
									<?php if ( (int) $bem->substitui_id > 0 ) : ?>
										<br><small>repõe outro item</small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $bem->categoria ); ?></td>
								<td><?php echo esc_html( Atelie_Gestao_Util::data_br( $bem->data_compra ) ); ?></td>
								<td><?php echo esc_html( Atelie_Gestao_Util::moeda( (float) $bem->valor_compra ) ); ?></td>
								<td class="st-<?php echo esc_attr( $bem->status ); ?>"><?php echo esc_html( Atelie_Patrimonio_Repo::STATUS_ROTULOS[ $bem->status ] ?? $bem->status ); ?></td>
								<td><?php $this->renderizar_acoes( $bem, $hoje, $saldo ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private function renderizar_acoes( object $bem, string $hoje, float $saldo ): void {
		$id = (int) $bem->id;

		if ( $bem->status === 'em_uso' ) {
			echo '<details><summary>Registrar dano</summary>';
			$this->abrir_form( 'dano', $id );
			echo '<div class="atelie-grade"><label>O que aconteceu? *<input type="text" name="observacao" required></label>';
			echo '<label>Data<input type="date" name="data" value="' . esc_attr( $hoje ) . '"></label></div>';
			echo '<button type="submit" class="button">Registrar dano</button></form></details>';
		}

		if ( $bem->status === 'danificado' ) {
			echo '<details><summary>Marcar como consertado</summary>';
			$this->abrir_form( 'conserto', $id );
			echo '<div class="atelie-grade"><label>Custo do conserto (R$)<input type="text" name="valor" inputmode="decimal" placeholder="0,00"></label>';
			echo '<label>Observação<input type="text" name="observacao"></label>';
			echo '<label>Data<input type="date" name="data" value="' . esc_attr( $hoje ) . '"></label></div>';
			echo '<button type="submit" class="button">Voltou a funcionar</button></form></details>';
		}

		if ( $bem->status !== 'em_uso' ) {
			echo '<details><summary>Repor (comprar outro)</summary>';
			$this->abrir_form( 'reposicao', $id );
			echo '<div class="atelie-grade"><label>Item novo *<input type="text" name="nome" required value="' . esc_attr( $bem->nome ) . '"></label>';
			echo '<label>Valor pago (R$) *<input type="text" name="valor" required inputmode="decimal" placeholder="0,00"></label>';
			echo '<label>Pagar com o fundo (R$) — saldo ' . esc_html( Atelie_Gestao_Util::moeda( $saldo ) ) . '<input type="text" name="abater_fundo" inputmode="decimal" placeholder="0,00"></label>';
			echo '<label>Comprado onde<input type="text" name="fornecedor"></label>';
			echo '<label>Data<input type="date" name="data" value="' . esc_attr( $hoje ) . '"></label></div>';
			echo '<button type="submit" class="button button-primary">Registrar reposição</button></form></details>';
		}

		if ( $bem->status !== 'baixado' ) {
			echo '<details><summary>Dar baixa</summary>';
			$this->abrir_form( 'baixa', $id );
			echo '<div class="atelie-grade"><label>Motivo *<input type="text" name="observacao" required placeholder="perdido, inutilizado..."></label>';
			echo '<label>Data<input type="date" name="data" value="' . esc_attr( $hoje ) . '"></label></div>';
			echo '<button type="submit" class="button">Dar baixa</button></form></details>';
		}

		echo '<details><summary>Histórico</summary><ul style="margin:6px 0 0 16px;list-style:disc;">';
		foreach ( Atelie_Patrimonio_Repo::eventos( $id ) as $ev ) {
			$linha = Atelie_Gestao_Util::data_br( $ev->data_evento ) . ' — ' . ucfirst( (string) $ev->tipo );
			if ( (float) $ev->valor > 0 ) {
				$linha .= ' (' . Atelie_Gestao_Util::moeda( (float) $ev->valor ) . ')';
			}
			if ( (string) $ev->observacao !== '' ) {
				$linha .= ': ' . $ev->observacao;
			}
			echo '<li>' . esc_html( $linha ) . '</li>';
		}
		echo '</ul></details>';
	}
}
