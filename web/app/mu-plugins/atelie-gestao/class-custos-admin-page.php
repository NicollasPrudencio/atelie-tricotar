<?php
/**
 * Tela "Custos e rateio" (menu Gestão) — configura, por canal de venda
 * (feira, site, venda individual), os custos que o ateliê tem pra vender ali
 * e os rateios (parte de cada venda separada pro fundo de reposição, margem
 * do ateliê...). O orçamento usa isso pra mostrar o preço final por canal e
 * quanto a artesã recebe. Tem uma simulação embaixo pra conferir os números.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Custos_Admin_Page {

	public const SLUG = 'atelie-custos';

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );
		add_action( 'admin_post_atelie_custos_salvar', array( $this, 'salvar' ) );
	}

	public function adicionar_menu(): void {
		add_submenu_page( Atelie_Patrimonio_Admin_Page::SLUG, 'Custos e rateio', 'Custos e rateio', 'atelie_gestao', self::SLUG, array( $this, 'renderizar' ) );
	}

	public function salvar(): void {
		if (
			! current_user_can( 'atelie_gestao' )
			|| ! isset( $_POST['atelie_gestao_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_gestao_nonce'] ) ), 'atelie_custos_salvar' )
		) {
			wp_die( 'Ação não permitida.' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cada campo e sanitizado individualmente (sanitize_text_field / parse_valor / absint) no loop logo abaixo.
		$canais_brutos = isset( $_POST['canais'] ) && is_array( $_POST['canais'] ) ? wp_unslash( $_POST['canais'] ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- idem: cada campo e sanitizado individualmente no loop abaixo.
		$rateios_brutos = isset( $_POST['rateios'] ) && is_array( $_POST['rateios'] ) ? wp_unslash( $_POST['rateios'] ) : array();

		$canais = array();
		foreach ( array_keys( Atelie_Canais_Config::canais_padrao() ) as $slug ) {
			$itens = array();
			foreach ( (array) ( $canais_brutos[ $slug ]['itens'] ?? array() ) as $item ) {
				$nome = sanitize_text_field( (string) ( $item['nome'] ?? '' ) );
				if ( $nome === '' ) {
					continue;
				}
				$itens[] = array(
					'nome'  => $nome,
					'tipo'  => sanitize_key( (string) ( $item['tipo'] ?? 'fixo' ) ),
					'valor' => Atelie_Gestao_Util::parse_valor( sanitize_text_field( (string) ( $item['valor'] ?? '' ) ) ),
					'pecas' => absint( $item['pecas'] ?? 1 ),
				);
			}
			$canais[ $slug ] = array( 'itens' => $itens );
		}

		$rateios = array();
		foreach ( $rateios_brutos as $r ) {
			$nome = sanitize_text_field( (string) ( $r['nome'] ?? '' ) );
			if ( $nome === '' ) {
				continue;
			}
			$rateios[] = array(
				'nome'       => $nome,
				'percentual' => Atelie_Gestao_Util::parse_valor( sanitize_text_field( (string) ( $r['percentual'] ?? '' ) ) ),
				'destino'    => sanitize_key( (string) ( $r['destino'] ?? 'caixa' ) ),
			);
		}

		$config  = array(
			'canais'  => $canais,
			'rateios' => $rateios,
		);
		$destino = admin_url( 'admin.php?page=' . self::SLUG );

		if ( Atelie_Canais_Config::percentual_maximo_usado( $config ) >= Atelie_Canais_Config::PERCENTUAL_MAXIMO ) {
			wp_safe_redirect( add_query_arg( 'erro', 'pct', $destino ) );
			exit;
		}

		Atelie_Canais_Config::salvar( $config );
		wp_safe_redirect( add_query_arg( 'ok', '1', $destino ) );
		exit;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private function linha_item( string $slug, string $indice, array $item ): void {
		$base = 'canais[' . $slug . '][itens][' . $indice . ']';
		?>
		<tr class="atelie-custo-linha">
			<td><input type="text" name="<?php echo esc_attr( $base ); ?>[nome]" value="<?php echo esc_attr( (string) ( $item['nome'] ?? '' ) ); ?>" placeholder="ex.: Taxa da barraca" style="width:100%;"></td>
			<td>
				<select name="<?php echo esc_attr( $base ); ?>[tipo]" class="atelie-custo-tipo">
					<?php foreach ( Atelie_Canais_Config::TIPOS as $chave => $rotulo ) : ?>
						<option value="<?php echo esc_attr( $chave ); ?>" <?php selected( (string) ( $item['tipo'] ?? 'fixo' ), $chave ); ?>><?php echo esc_html( $rotulo ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><input type="text" name="<?php echo esc_attr( $base ); ?>[valor]" value="<?php echo esc_attr( isset( $item['valor'] ) ? number_format( (float) $item['valor'], 2, ',', '' ) : '' ); ?>" inputmode="decimal" style="width:100px;"></td>
			<td><input type="number" min="1" name="<?php echo esc_attr( $base ); ?>[pecas]" value="<?php echo esc_attr( (string) ( $item['pecas'] ?? 1 ) ); ?>" class="atelie-custo-pecas" style="width:80px;" title="Só vale pra 'custo do evento': quantas peças você espera vender"></td>
			<td><button type="button" class="button-link atelie-custo-remover">Remover</button></td>
		</tr>
		<?php
	}

	public function renderizar(): void {
		$config  = Atelie_Canais_Config::obter();
		$ok      = isset( $_GET['ok'] );
		$erro    = isset( $_GET['erro'] ) ? sanitize_key( wp_unslash( $_GET['erro'] ) ) : '';
		$sim_str = isset( $_GET['sim'] ) ? sanitize_text_field( wp_unslash( $_GET['sim'] ) ) : '50';
		$sim     = Atelie_Gestao_Util::parse_valor( $sim_str );
		?>
		<style>
			.atelie-gestao .atelie-caixa { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; max-width: 980px; }
			.atelie-gestao .atelie-caixa h2 { margin-top: 0; }
			.atelie-gestao table.atelie-tab { width: 100%; border-collapse: collapse; }
			.atelie-gestao table.atelie-tab th, .atelie-gestao table.atelie-tab td { padding: 5px 6px; text-align: left; vertical-align: middle; border-bottom: 1px solid #eee; }
			.atelie-gestao .atelie-sim td, .atelie-gestao .atelie-sim th { border: 1px solid #dcdcde; padding: 6px 10px; }
		</style>
		<div class="wrap atelie-gestao">
			<h1>Custos e rateio
			<?php
			Atelie_Ajuda_Drawer::render(
				'Custos e rateio',
				array(
					'Aqui você diz quanto o ateliê gasta pra vender em cada canal (feira, site, venda individual) e quanto de cada venda separa pro fundo de reposição.',
					'Custo fixo soma por peça; "custo do evento" divide o valor pelas peças que você espera vender; percentual é sobre o preço final (taxa de cartão, comissão).',
					'Rateio vale pra todos os canais. O orçamento usa tudo isso pra mostrar o preço final por canal e quanto a artesã recebe.',
				),
				'/gestao/custos-e-rateio/'
			);
			?>
			</h1>

			<?php if ( $ok ) : ?>
				<div class="notice notice-success is-dismissible"><p>Custos e rateio salvos.</p></div>
			<?php elseif ( $erro === 'pct' ) : ?>
				<div class="notice notice-error"><p>A soma dos percentuais (taxas do canal + rateios) precisa ficar abaixo de <?php echo esc_html( (string) Atelie_Canais_Config::PERCENTUAL_MAXIMO ); ?>% — senão o preço final não faz sentido. Nada foi salvo.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="atelie_custos_salvar">
				<?php wp_nonce_field( 'atelie_custos_salvar', 'atelie_gestao_nonce' ); ?>

				<?php foreach ( $config['canais'] as $slug => $canal ) : ?>
					<div class="atelie-caixa" data-canal="<?php echo esc_attr( $slug ); ?>">
						<h2><?php echo esc_html( $canal['nome'] ); ?></h2>
						<table class="atelie-tab">
							<thead><tr><th>Custo</th><th>Tipo</th><th>Valor</th><th>Peças esperadas</th><th></th></tr></thead>
							<tbody class="atelie-custo-corpo">
								<?php foreach ( $canal['itens'] as $i => $item ) : ?>
									<?php $this->linha_item( $slug, (string) $i, $item ); ?>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p><button type="button" class="button atelie-custo-add">+ Adicionar custo</button></p>
					</div>
				<?php endforeach; ?>

				<div class="atelie-caixa">
					<h2>Rateio (vale pra todos os canais)</h2>
					<p class="description">Percentual do preço final de cada venda separado pra um destino. "Fundo de reposição" alimenta o saldo do <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Atelie_Fundo_Admin_Page::SLUG ) ); ?>">fundo</a> quando a venda é registrada; "Caixa do ateliê" é a margem do ateliê.</p>
					<table class="atelie-tab">
						<thead><tr><th>Nome</th><th>Percentual (%)</th><th>Destino</th><th></th></tr></thead>
						<tbody id="atelie-rateio-corpo">
							<?php foreach ( $config['rateios'] as $i => $r ) : ?>
								<tr class="atelie-rateio-linha">
									<td><input type="text" name="rateios[<?php echo esc_attr( (string) $i ); ?>][nome]" value="<?php echo esc_attr( $r['nome'] ); ?>" style="width:100%;"></td>
									<td><input type="text" name="rateios[<?php echo esc_attr( (string) $i ); ?>][percentual]" value="<?php echo esc_attr( number_format( $r['percentual'], 2, ',', '' ) ); ?>" inputmode="decimal" style="width:100px;"></td>
									<td>
										<select name="rateios[<?php echo esc_attr( (string) $i ); ?>][destino]">
											<?php foreach ( Atelie_Canais_Config::DESTINOS as $chave => $rotulo ) : ?>
												<option value="<?php echo esc_attr( $chave ); ?>" <?php selected( $r['destino'], $chave ); ?>><?php echo esc_html( $rotulo ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><button type="button" class="button-link atelie-custo-remover">Remover</button></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p><button type="button" class="button" id="atelie-rateio-add">+ Adicionar rateio</button></p>
				</div>

				<p><button type="submit" class="button button-primary button-hero">Salvar custos e rateio</button></p>
			</form>

			<div class="atelie-caixa">
				<h2>Simulação (com o que está salvo)</h2>
				<form method="get">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
					Se o valor da artesã for R$ <input type="text" name="sim" value="<?php echo esc_attr( $sim_str ); ?>" inputmode="decimal" style="width:100px;"> <button type="submit" class="button">Simular</button>
				</form>
				<table class="atelie-sim" style="border-collapse:collapse;margin-top:10px;">
					<thead><tr><th>Canal</th><th>Preço final</th><th>Artesã recebe</th><th>Fica pro ateliê</th></tr></thead>
					<tbody>
						<?php foreach ( Atelie_Canais_Config::calcular( $sim ) as $c ) : ?>
							<tr>
								<td><?php echo esc_html( $c['nome'] ); ?></td>
								<?php if ( $c['valido'] ) : ?>
									<td><strong><?php echo esc_html( Atelie_Gestao_Util::moeda( $c['preco'] ) ); ?></strong></td>
									<td><?php echo esc_html( Atelie_Gestao_Util::moeda( $c['artesa'] ) ); ?></td>
									<td><?php echo esc_html( Atelie_Gestao_Util::moeda( $c['atelie'] ) ); ?></td>
								<?php else : ?>
									<td colspan="3">percentuais altos demais</td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<template id="atelie-custo-template">
			<?php $this->linha_item( '__CANAL__', '__IDX__', array() ); ?>
		</template>
		<template id="atelie-rateio-template">
			<tr class="atelie-rateio-linha">
				<td><input type="text" name="rateios[__IDX__][nome]" placeholder="ex.: Fundo de reposição" style="width:100%;"></td>
				<td><input type="text" name="rateios[__IDX__][percentual]" inputmode="decimal" style="width:100px;"></td>
				<td>
					<select name="rateios[__IDX__][destino]">
						<?php foreach ( Atelie_Canais_Config::DESTINOS as $chave => $rotulo ) : ?>
							<option value="<?php echo esc_attr( $chave ); ?>"><?php echo esc_html( $rotulo ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td><button type="button" class="button-link atelie-custo-remover">Remover</button></td>
			</tr>
		</template>

		<script>
			(function () {
				var contador = Date.now();
				function clonar(idTemplate, substituicoes) {
					var html = document.getElementById(idTemplate).innerHTML;
					Object.keys(substituicoes).forEach(function (chave) { html = html.split(chave).join(substituicoes[chave]); });
					var tbody = document.createElement("tbody");
					tbody.innerHTML = html;
					return tbody.firstElementChild;
				}
				function ligar(linha) {
					linha.querySelector(".atelie-custo-remover").addEventListener("click", function () { linha.remove(); });
					var tipo = linha.querySelector(".atelie-custo-tipo");
					var pecas = linha.querySelector(".atelie-custo-pecas");
					if (tipo && pecas) {
						var sync = function () { pecas.disabled = tipo.value !== "evento"; };
						tipo.addEventListener("change", sync);
						sync();
					}
				}
				document.querySelectorAll(".atelie-custo-linha, .atelie-rateio-linha").forEach(ligar);

				document.querySelectorAll(".atelie-custo-add").forEach(function (botao) {
					botao.addEventListener("click", function () {
						var caixa = botao.closest("[data-canal]");
						var linha = clonar("atelie-custo-template", { "__CANAL__": caixa.dataset.canal, "__IDX__": String(++contador) });
						caixa.querySelector(".atelie-custo-corpo").appendChild(linha);
						ligar(linha);
					});
				});
				document.getElementById("atelie-rateio-add").addEventListener("click", function () {
					var linha = clonar("atelie-rateio-template", { "__IDX__": String(++contador) });
					document.getElementById("atelie-rateio-corpo").appendChild(linha);
					ligar(linha);
				});
			})();
		</script>
		<?php
	}
}
