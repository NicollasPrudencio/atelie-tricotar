<?php
/**
 * Tela "Relatório de Produtos" — lista os produtos com referência (SKU),
 * categoria, preço, disponibilidade, estoque, peso e dimensões, pronta pra
 * IMPRIMIR (ou salvar em PDF pelo próprio diálogo de impressão do navegador).
 * Acessível pra Artesã (edit_products): é conferência do dia a dia, tipo
 * inventário de prateleira, não dado financeiro. Filtros e colunas
 * escolhíveis pra imprimir só o que interessa.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Relatorio_Produtos_Admin_Page {

	private const SLUG = 'atelie-relatorio-produtos';

	/**
	 * Colunas que dá pra ligar/desligar antes de imprimir: chave => rótulo.
	 *
	 * @var array<string, string>
	 */
	private const COLUNAS = array(
		'foto'            => 'Foto',
		'referencia'      => 'Referência',
		'produto'         => 'Produto',
		'categoria'       => 'Categoria',
		'preco'           => 'Preço',
		'disponibilidade' => 'Disponibilidade',
		'estoque'         => 'Estoque',
		'peso'            => 'Peso',
		'dimensoes'       => 'Dimensões',
		'status'          => 'Situação',
	);

	public function registrar(): void {
		add_action( 'admin_menu', array( $this, 'adicionar_menu' ) );
	}

	public function adicionar_menu(): void {
		add_submenu_page(
			'edit.php?post_type=product',
			'Relatório de Produtos',
			'Relatório de Produtos',
			'edit_products',
			self::SLUG,
			array( $this, 'renderizar' )
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function montar_linhas( string $categoria, string $disponibilidade, string $situacao, string $ordem ): array {
		$args = array(
			'post_type'   => 'product',
			'post_status' => $situacao === 'todos' ? array( 'publish', 'draft' ) : 'publish',
			'numberposts' => -1,
		);

		if ( $categoria !== '' ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtro opcional do relatorio, poucas dezenas de produtos.
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $categoria,
				),
			);
		}

		$linhas = array();
		foreach ( get_posts( $args ) as $post ) {
			$produto = wc_get_product( $post->ID );
			if ( ! $produto ) {
				continue;
			}

			$disp = get_post_meta( $post->ID, '_atelie_disponibilidade', true ) === 'pronta_entrega' ? 'pronta_entrega' : 'sob_encomenda';
			if ( $disponibilidade !== '' && $disponibilidade !== $disp ) {
				continue;
			}

			$termos = get_the_terms( $post->ID, 'product_cat' );

			$linhas[] = array(
				'id'              => $post->ID,
				'referencia'      => (string) $produto->get_sku(),
				'produto'         => $post->post_title,
				'categoria'       => is_array( $termos ) && ! empty( $termos ) ? $termos[0]->name : '',
				'preco'           => $produto->get_price(),
				'disponibilidade' => $disp,
				'prazo'           => (int) get_post_meta( $post->ID, '_atelie_prazo_producao', true ),
				'estoque'         => $produto->managing_stock() ? (int) $produto->get_stock_quantity() : null,
				'peso'            => (string) $produto->get_weight(),
				'dimensoes'       => array( (string) $produto->get_length(), (string) $produto->get_width(), (string) $produto->get_height() ),
				'status'          => $post->post_status === 'publish' ? 'Publicado' : 'Rascunho',
			);
		}

		usort(
			$linhas,
			static function ( array $a, array $b ) use ( $ordem ): int {
				if ( $ordem === 'referencia' ) {
					// Sem referência vai pro fim da lista.
					if ( $a['referencia'] === '' && $b['referencia'] !== '' ) {
						return 1;
					}
					if ( $b['referencia'] === '' && $a['referencia'] !== '' ) {
						return -1;
					}
					$cmp = strnatcasecmp( $a['referencia'], $b['referencia'] );
					if ( $cmp !== 0 ) {
						return $cmp;
					}
				}
				return strnatcasecmp( $a['produto'], $b['produto'] );
			}
		);

		return $linhas;
	}

	public function renderizar(): void {
		$categoria       = isset( $_GET['categoria'] ) ? sanitize_title( wp_unslash( $_GET['categoria'] ) ) : '';
		$disp_bruta      = isset( $_GET['disponibilidade'] ) ? sanitize_key( wp_unslash( $_GET['disponibilidade'] ) ) : '';
		$disponibilidade = in_array( $disp_bruta, array( 'pronta_entrega', 'sob_encomenda' ), true ) ? $disp_bruta : '';
		$situacao        = isset( $_GET['situacao'] ) && $_GET['situacao'] === 'todos' ? 'todos' : 'publicados';
		$ordem           = isset( $_GET['ordem'] ) && $_GET['ordem'] === 'produto' ? 'produto' : 'referencia';

		$linhas      = $this->montar_linhas( $categoria, $disponibilidade, $situacao, $ordem );
		$categorias  = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		$total_pecas = 0;
		foreach ( $linhas as $linha ) {
			$total_pecas += (int) ( $linha['estoque'] ?? 0 );
		}
		?>
		<style>
			.atelie-relatorio table { border-collapse: collapse; width: 100%; background: #fff; }
			.atelie-relatorio th, .atelie-relatorio td { border: 1px solid #c3c4c7; padding: 6px 8px; text-align: left; vertical-align: middle; font-size: 13px; }
			.atelie-relatorio th { background: #f0e6ea; }
			.atelie-relatorio td.num { text-align: right; white-space: nowrap; }
			.atelie-relatorio img { width: 48px; height: 48px; object-fit: cover; border-radius: 3px; display: block; }
			.atelie-relatorio .atelie-rel-cabecalho { display: none; }
			.atelie-relatorio .atelie-rel-filtros label { margin-right: 1rem; }
			.atelie-relatorio .atelie-rel-colunas { margin: 0.75rem 0; }
			.atelie-relatorio .atelie-rel-colunas label { margin-right: 0.9rem; white-space: nowrap; }
			@media print {
				#adminmenumain, #wpadminbar, #wpfooter, .notice, .atelie-no-print { display: none !important; }
				html.wp-toolbar { padding-top: 0 !important; }
				#wpcontent, #wpfooter { margin-left: 0 !important; padding-left: 0 !important; }
				.atelie-relatorio .atelie-rel-cabecalho { display: block; margin-bottom: 10px; }
				.atelie-relatorio th { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
				.atelie-relatorio tr { break-inside: avoid; }
			}
		</style>

		<div class="wrap atelie-novo-produto atelie-relatorio">
			<h1 class="atelie-no-print">Relatório de Produtos
			<?php
			Atelie_Ajuda_Drawer::render(
				'Relatório de Produtos',
				array(
					'Lista os produtos com referência, categoria, preço, disponibilidade, estoque, peso e dimensões — pra conferir e imprimir.',
					'Escolha os filtros e as colunas que quer ver e clique em "Imprimir" (dá pra "Salvar como PDF" na própria janela de impressão).',
					'Referência e estoque você cadastra na tela do produto ("Novo Produto" / editar). Sem controle de estoque aparece "—".',
				),
				'/relatorio-de-produtos/'
			);
			?>
			</h1>

			<form method="get" class="atelie-no-print atelie-rel-filtros">
				<input type="hidden" name="post_type" value="product">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<p>
					<label>Categoria
						<select name="categoria">
							<option value="">Todas</option>
							<?php if ( is_array( $categorias ) ) : ?>
								<?php foreach ( $categorias as $termo ) : ?>
									<option value="<?php echo esc_attr( $termo->slug ); ?>" <?php selected( $categoria, $termo->slug ); ?>><?php echo esc_html( $termo->name ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</label>
					<label>Disponibilidade
						<select name="disponibilidade">
							<option value="">Todas</option>
							<option value="pronta_entrega" <?php selected( $disponibilidade, 'pronta_entrega' ); ?>>Pronta entrega</option>
							<option value="sob_encomenda" <?php selected( $disponibilidade, 'sob_encomenda' ); ?>>Sob encomenda</option>
						</select>
					</label>
					<label>Mostrar
						<select name="situacao">
							<option value="publicados" <?php selected( $situacao, 'publicados' ); ?>>Só publicados</option>
							<option value="todos" <?php selected( $situacao, 'todos' ); ?>>Publicados e rascunhos</option>
						</select>
					</label>
					<label>Ordenar por
						<select name="ordem">
							<option value="referencia" <?php selected( $ordem, 'referencia' ); ?>>Referência</option>
							<option value="produto" <?php selected( $ordem, 'produto' ); ?>>Nome do produto</option>
						</select>
					</label>
					<button type="submit" class="button">Aplicar</button>
					<button type="button" class="button button-primary" onclick="window.print();">🖨️ Imprimir</button>
				</p>
				<p class="atelie-rel-colunas">
					<strong>Colunas no relatório:</strong>
					<?php foreach ( self::COLUNAS as $chave => $rotulo ) : ?>
						<label><input type="checkbox" class="atelie-rel-col" data-col="<?php echo esc_attr( $chave ); ?>" checked> <?php echo esc_html( $rotulo ); ?></label>
					<?php endforeach; ?>
				</p>
			</form>

			<div class="atelie-rel-cabecalho">
				<h2 style="margin:0;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — Relatório de Produtos</h2>
				<p style="margin:2px 0;">Emitido em <?php echo esc_html( wp_date( 'd/m/Y H:i' ) ); ?> · <?php echo esc_html( (string) count( $linhas ) ); ?> produto(s)</p>
			</div>

			<?php if ( empty( $linhas ) ) : ?>
				<p>Nenhum produto encontrado com esses filtros.</p>
			<?php else : ?>
				<table>
					<thead>
						<tr>
							<?php foreach ( self::COLUNAS as $chave => $rotulo ) : ?>
								<th data-col="<?php echo esc_attr( $chave ); ?>"><?php echo esc_html( $rotulo ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $linhas as $linha ) : ?>
							<tr>
								<td data-col="foto"><?php echo get_the_post_thumbnail( $linha['id'], array( 48, 48 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() ja escapa os atributos internamente. ?></td>
								<td data-col="referencia"><?php echo $linha['referencia'] !== '' ? esc_html( $linha['referencia'] ) : '—'; ?></td>
								<td data-col="produto"><?php echo esc_html( $linha['produto'] ); ?></td>
								<td data-col="categoria"><?php echo esc_html( $linha['categoria'] ); ?></td>
								<td data-col="preco" class="num"><?php echo $linha['preco'] !== '' ? wp_kses_post( wc_price( $linha['preco'] ) ) : '—'; ?></td>
								<td data-col="disponibilidade">
									<?php
									echo $linha['disponibilidade'] === 'pronta_entrega'
										? 'Pronta entrega'
										: 'Sob encomenda' . ( $linha['prazo'] > 0 ? ' (' . (int) $linha['prazo'] . ' dias)' : '' );
									?>
								</td>
								<td data-col="estoque" class="num">
									<?php
									if ( $linha['estoque'] === null ) {
										echo '—';
									} elseif ( $linha['estoque'] <= 0 ) {
										echo 'Esgotado';
									} else {
										echo esc_html( (string) $linha['estoque'] );
									}
									?>
								</td>
								<td data-col="peso" class="num"><?php echo $linha['peso'] !== '' ? esc_html( $linha['peso'] ) . ' kg' : '—'; ?></td>
								<td data-col="dimensoes" class="num">
									<?php
									$dim = array_filter( $linha['dimensoes'], static fn ( $v ) => $v !== '' );
									echo count( $dim ) === 3 ? esc_html( implode( ' × ', $linha['dimensoes'] ) ) . ' cm' : '—';
									?>
								</td>
								<td data-col="status"><?php echo esc_html( $linha['status'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="<?php echo esc_attr( (string) count( self::COLUNAS ) ); ?>">
								<?php echo esc_html( (string) count( $linhas ) ); ?> produto(s) · <?php echo esc_html( (string) $total_pecas ); ?> peça(s) em estoque controlado
							</th>
						</tr>
					</tfoot>
				</table>
			<?php endif; ?>
		</div>

		<script>
			// Liga/desliga colunas (na tela e na impressão) — cada coluna é marcada por data-col.
			document.querySelectorAll('.atelie-rel-col').forEach(function (caixa) {
				caixa.addEventListener('change', function () {
					document.querySelectorAll('.atelie-relatorio [data-col="' + caixa.dataset.col + '"]').forEach(function (celula) {
						if (celula.tagName === 'INPUT') { return; }
						celula.style.display = caixa.checked ? '' : 'none';
					});
				});
			});
		</script>
		<?php
	}
}
