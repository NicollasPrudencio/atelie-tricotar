<?php
/**
 * Plugin Name: Atelie - Precificacao
 * Description: Ferramenta interna de custo (materia-prima + hora tecnica), com media de horas por historico e integracao com o preco do produto. NAO e visivel para o papel Vendedora nem para o cliente.
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 *
 * Formula usada (decisao explicita): custo = materia-prima + (horas x valor/hora da artesa).
 * Sem despesa fixa rateada nem margem embutida — o numero calculado e o CUSTO, a margem quem
 * decide e adiciona manualmente ao revisar o preco do produto.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPTs
 */
add_action(
	'init',
	function (): void {
		register_post_type(
			'atelie_orcamento',
			array(
				'label'     => __( 'Orçamentos', 'atelie-theme' ),
				'labels'    => array(
					'name'          => __( 'Orçamentos', 'atelie-theme' ),
					'singular_name' => __( 'Orçamento', 'atelie-theme' ),
					'add_new_item'  => __( 'Novo orçamento', 'atelie-theme' ),
				),
				'public'    => false,
				'show_ui'   => true,
				'menu_icon' => 'dashicons-calculator',
				'supports'  => array( 'title' ),
			)
		);

		register_post_type(
			'atelie_materia_prima',
			array(
				'label'        => __( 'Matérias-primas', 'atelie-theme' ),
				'labels'       => array(
					'name'          => __( 'Matérias-primas', 'atelie-theme' ),
					'singular_name' => __( 'Matéria-prima', 'atelie-theme' ),
					'add_new_item'  => __( 'Nova matéria-prima', 'atelie-theme' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=atelie_orcamento',
				'supports'     => array( 'title' ),
			)
		);

		register_post_type(
			'atelie_artesa',
			array(
				'label'        => __( 'Artesãs', 'atelie-theme' ),
				'labels'       => array(
					'name'          => __( 'Artesãs', 'atelie-theme' ),
					'singular_name' => __( 'Artesã', 'atelie-theme' ),
					'add_new_item'  => __( 'Nova artesã', 'atelie-theme' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=atelie_orcamento',
				'supports'     => array( 'title' ),
			)
		);
	},
	10
);

// product_cat e registrada pelo WooCommerce — anexar depois, com prioridade menor
add_action(
	'init',
	function (): void {
		if ( taxonomy_exists( 'product_cat' ) ) {
			register_taxonomy_for_object_type( 'product_cat', 'atelie_orcamento' );
		}
	},
	20
);

/**
 * Meta box: Matéria-prima (preco + unidade)
 */
add_action(
	'add_meta_boxes',
	function (): void {
		add_meta_box(
			'atelie_mp_dados',
			__( 'Preço e unidade', 'atelie-theme' ),
			function ( WP_Post $post ): void {
				wp_nonce_field( 'atelie_mp_save', 'atelie_mp_nonce' );
				$preco   = get_post_meta( $post->ID, '_atelie_mp_preco', true );
				$unidade = get_post_meta( $post->ID, '_atelie_mp_unidade', true );
				?>
			<p>
					<label><?php esc_html_e( 'Preço (R$)', 'atelie-theme' ); ?></label><br>
					<input type="number" step="0.01" min="0" name="atelie_mp_preco" value="<?php echo esc_attr( $preco ); ?>" style="width:100%">
			</p>
			<p>
					<label><?php esc_html_e( 'Unidade', 'atelie-theme' ); ?></label><br>
				<select name="atelie_mp_unidade" style="width:100%">
						<?php
						foreach ( array(
							'unidade' => 'Unidade',
							'metro'   => 'Metro',
							'grama'   => 'Grama',
							'novelo'  => 'Novelo',
							'outro'   => 'Outro',
						) as $valor => $rotulo ) :
							?>
							<option value="<?php echo esc_attr( $valor ); ?>" <?php selected( $unidade, $valor ); ?>><?php echo esc_html( $rotulo ); ?></option>
						<?php endforeach; ?>
				</select>
			</p>
				<?php
			},
			'atelie_materia_prima',
			'normal',
			'high'
		);
	}
);

add_action(
	'save_post_atelie_materia_prima',
	function ( int $post_id ): void {
		if ( ! isset( $_POST['atelie_mp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_mp_nonce'] ) ), 'atelie_mp_save' ) ) {
			return;
		}
		if ( isset( $_POST['atelie_mp_preco'] ) ) {
			update_post_meta( $post_id, '_atelie_mp_preco', (float) $_POST['atelie_mp_preco'] );
		}
		if ( isset( $_POST['atelie_mp_unidade'] ) ) {
			update_post_meta( $post_id, '_atelie_mp_unidade', sanitize_text_field( wp_unslash( $_POST['atelie_mp_unidade'] ) ) );
		}
	}
);

/**
 * Meta box: Artesã (valor/hora)
 */
add_action(
	'add_meta_boxes',
	function (): void {
		add_meta_box(
			'atelie_artesa_dados',
			__( 'Valor da hora', 'atelie-theme' ),
			function ( WP_Post $post ): void {
				wp_nonce_field( 'atelie_artesa_save', 'atelie_artesa_nonce' );
				$valor_hora = get_post_meta( $post->ID, '_atelie_valor_hora', true );
				?>
			<p>
					<label><?php esc_html_e( 'Valor por hora (R$)', 'atelie-theme' ); ?></label><br>
					<input type="number" step="0.01" min="0" name="atelie_valor_hora" value="<?php echo esc_attr( $valor_hora ); ?>" style="width:100%">
			</p>
				<?php
			},
			'atelie_artesa',
			'normal',
			'high'
		);
	}
);

add_action(
	'save_post_atelie_artesa',
	function ( int $post_id ): void {
		if ( ! isset( $_POST['atelie_artesa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_artesa_nonce'] ) ), 'atelie_artesa_save' ) ) {
			return;
		}
		if ( isset( $_POST['atelie_valor_hora'] ) ) {
			update_post_meta( $post_id, '_atelie_valor_hora', (float) $_POST['atelie_valor_hora'] );
		}
	}
);

/**
 * Meta box: Orçamento — artesa, categoria/porte (com sugestao de horas pelo
 * historico), itens de materia-prima e os totais calculados.
 */
add_action(
	'add_meta_boxes',
	function (): void {
		add_meta_box(
			'atelie_orcamento_dados',
			__( 'Cálculo do orçamento', 'atelie-theme' ),
			'atelie_orcamento_render_meta_box',
			'atelie_orcamento',
			'normal',
			'high'
		);
	}
);

function atelie_orcamento_render_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'atelie_orcamento_save', 'atelie_orcamento_nonce' );

	$artesas    = get_posts(
		array(
			'post_type'   => 'atelie_artesa',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);
	$materias   = get_posts(
		array(
			'post_type'   => 'atelie_materia_prima',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);
	$categorias = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		)
	);

	$artesa_id       = get_post_meta( $post->ID, '_atelie_orc_artesa_id', true );
	$valor_hora      = get_post_meta( $post->ID, '_atelie_orc_valor_hora', true );
	$categoria_id    = get_post_meta( $post->ID, '_atelie_orc_categoria', true );
	$porte           = get_post_meta( $post->ID, '_atelie_orc_porte', true );
	$horas_estimada  = get_post_meta( $post->ID, '_atelie_orc_horas_estimada', true );
	$horas_reais     = get_post_meta( $post->ID, '_atelie_orc_horas_reais', true );
	$status          = get_post_meta( $post->ID, '_atelie_orc_status', true ) ?: 'orcamento';
	$itens           = get_post_meta( $post->ID, '_atelie_orc_itens', true );
	$itens           = is_array( $itens ) ? $itens : array();
	$custo_materiais = get_post_meta( $post->ID, '_atelie_orc_custo_materiais', true );
	$custo_mao_obra  = get_post_meta( $post->ID, '_atelie_orc_custo_mao_obra', true );
	$custo_total     = get_post_meta( $post->ID, '_atelie_orc_custo_total', true );

	$mapa_valor_hora = array();
	foreach ( $artesas as $a ) {
		$mapa_valor_hora[ $a->ID ] = (float) get_post_meta( $a->ID, '_atelie_valor_hora', true );
	}
	$mapa_preco_mp = array();
	foreach ( $materias as $m ) {
		$mapa_preco_mp[ $m->ID ] = (float) get_post_meta( $m->ID, '_atelie_mp_preco', true );
	}
	?>
	<div id="atelie-orcamento-app"
		data-valor-hora="<?php echo esc_attr( wp_json_encode( $mapa_valor_hora ) ); ?>"
		data-preco-mp="<?php echo esc_attr( wp_json_encode( $mapa_preco_mp ) ); ?>"
		data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'atelie_sugerir_horas' ) ); ?>">

		<p>
			<label><strong><?php esc_html_e( 'Artesã', 'atelie-theme' ); ?></strong></label><br>
			<select name="atelie_orc_artesa_id" id="atelie-orc-artesa">
				<option value=""><?php esc_html_e( '— selecionar —', 'atelie-theme' ); ?></option>
				<?php foreach ( $artesas as $a ) : ?>
					<option value="<?php echo esc_attr( $a->ID ); ?>" <?php selected( $artesa_id, (string) $a->ID ); ?>><?php echo esc_html( $a->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			&nbsp;
			<label><?php esc_html_e( 'Valor/hora (R$)', 'atelie-theme' ); ?></label>
			<input type="number" step="0.01" min="0" name="atelie_orc_valor_hora" id="atelie-orc-valor-hora" value="<?php echo esc_attr( $valor_hora ); ?>" style="width:6rem">
		</p>

		<p>
			<label><strong><?php esc_html_e( 'Categoria', 'atelie-theme' ); ?></strong></label><br>
			<select name="atelie_orc_categoria" id="atelie-orc-categoria">
				<option value=""><?php esc_html_e( '— selecionar —', 'atelie-theme' ); ?></option>
				<?php foreach ( $categorias as $c ) : ?>
					<option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( $categoria_id, (string) $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
				<?php endforeach; ?>
			</select>
			&nbsp;
			<label><?php esc_html_e( 'Porte', 'atelie-theme' ); ?></label>
			<select name="atelie_orc_porte" id="atelie-orc-porte">
				<?php
				foreach ( array(
					'pequeno' => 'Pequeno',
					'medio'   => 'Médio',
					'grande'  => 'Grande',
				) as $valor => $rotulo ) :
					?>
					<option value="<?php echo esc_attr( $valor ); ?>" <?php selected( $porte, $valor ); ?>><?php echo esc_html( $rotulo ); ?></option>
				<?php endforeach; ?>
			</select>
			&nbsp;
			<button type="button" class="button" id="atelie-orc-sugerir-horas"><?php esc_html_e( 'Sugerir horas pelo histórico', 'atelie-theme' ); ?></button>
			<span id="atelie-orc-sugestao-info" style="font-style:italic;"></span>
		</p>

		<p>
			<label><strong><?php esc_html_e( 'Horas técnicas estimadas', 'atelie-theme' ); ?></strong></label><br>
			<input type="number" step="0.25" min="0" name="atelie_orc_horas_estimada" id="atelie-orc-horas-estimada" value="<?php echo esc_attr( $horas_estimada ); ?>" style="width:8rem">
		</p>

		<hr>
		<p><strong><?php esc_html_e( 'Itens de matéria-prima usados', 'atelie-theme' ); ?></strong></p>
		<table class="widefat" id="atelie-orc-itens-tabela">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Matéria-prima', 'atelie-theme' ); ?></th>
					<th><?php esc_html_e( 'Quantidade', 'atelie-theme' ); ?></th>
					<th><?php esc_html_e( 'Subtotal (R$)', 'atelie-theme' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="atelie-orc-itens-corpo"></tbody>
		</table>
		<p><button type="button" class="button" id="atelie-orc-add-item"><?php esc_html_e( '+ Adicionar item', 'atelie-theme' ); ?></button></p>

		<template id="atelie-orc-item-template">
			<tr class="atelie-orc-item-linha">
				<td>
					<select class="atelie-orc-item-mp" name="atelie_orc_item_mp[]" style="width:100%">
						<option value=""><?php esc_html_e( '— selecionar —', 'atelie-theme' ); ?></option>
						<?php foreach ( $materias as $m ) : ?>
							<option value="<?php echo esc_attr( $m->ID ); ?>"><?php echo esc_html( $m->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td><input type="number" step="0.01" min="0" class="atelie-orc-item-qtd" name="atelie_orc_item_qtd[]" style="width:6rem"></td>
				<td class="atelie-orc-item-subtotal">R$ 0,00</td>
				<td><button type="button" class="button-link atelie-orc-item-remover"><?php esc_html_e( 'Remover', 'atelie-theme' ); ?></button></td>
			</tr>
		</template>

		<hr>
		<table class="widefat">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Custo de matéria-prima', 'atelie-theme' ); ?></td>
					<td id="atelie-orc-total-materiais"><?php echo esc_html( number_format( (float) $custo_materiais, 2, ',', '.' ) ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Custo de mão de obra (horas × valor/hora)', 'atelie-theme' ); ?></td>
					<td id="atelie-orc-total-mao-obra"><?php echo esc_html( number_format( (float) $custo_mao_obra, 2, ',', '.' ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Custo total (sem margem — ajuste o preço de venda manualmente)', 'atelie-theme' ); ?></strong></td>
					<td id="atelie-orc-total-custo"><strong><?php echo esc_html( number_format( (float) $custo_total, 2, ',', '.' ) ); ?></strong></td>
				</tr>
			</tbody>
		</table>

		<hr>
		<p>
			<label><strong><?php esc_html_e( 'Status', 'atelie-theme' ); ?></strong></label><br>
			<select name="atelie_orc_status">
				<option value="orcamento" <?php selected( $status, 'orcamento' ); ?>><?php esc_html_e( 'Em orçamento', 'atelie-theme' ); ?></option>
				<option value="produzido" <?php selected( $status, 'produzido' ); ?>><?php esc_html_e( 'Produzido', 'atelie-theme' ); ?></option>
			</select>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Horas reais (preencher só depois que a peça for feita — alimenta o histórico)', 'atelie-theme' ); ?></strong></label><br>
			<input type="number" step="0.25" min="0" name="atelie_orc_horas_reais" value="<?php echo esc_attr( $horas_reais ); ?>" style="width:8rem">
		</p>

		<script type="application/json" id="atelie-orc-itens-salvos"><?php echo wp_json_encode( $itens ); ?></script>
	</div>
	<?php
}

add_action(
	'save_post_atelie_orcamento',
	function ( int $post_id ): void {
		if ( ! isset( $_POST['atelie_orcamento_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_orcamento_nonce'] ) ), 'atelie_orcamento_save' ) ) {
			return;
		}

		$artesa_id       = isset( $_POST['atelie_orc_artesa_id'] ) ? absint( $_POST['atelie_orc_artesa_id'] ) : 0;
		$valor_hora      = isset( $_POST['atelie_orc_valor_hora'] ) ? (float) $_POST['atelie_orc_valor_hora'] : 0.0;
		$categoria_id    = isset( $_POST['atelie_orc_categoria'] ) ? absint( $_POST['atelie_orc_categoria'] ) : 0;
		$porte           = isset( $_POST['atelie_orc_porte'] ) ? sanitize_text_field( wp_unslash( $_POST['atelie_orc_porte'] ) ) : '';
		$horas_estimada  = isset( $_POST['atelie_orc_horas_estimada'] ) ? (float) $_POST['atelie_orc_horas_estimada'] : 0.0;
		$horas_reais_raw = isset( $_POST['atelie_orc_horas_reais'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['atelie_orc_horas_reais'] ) ) ) : '';
		$status          = isset( $_POST['atelie_orc_status'] ) ? sanitize_text_field( wp_unslash( $_POST['atelie_orc_status'] ) ) : 'orcamento';

		update_post_meta( $post_id, '_atelie_orc_artesa_id', $artesa_id );
		update_post_meta( $post_id, '_atelie_orc_valor_hora', $valor_hora );
		update_post_meta( $post_id, '_atelie_orc_categoria', $categoria_id );
		update_post_meta( $post_id, '_atelie_orc_porte', $porte );
		update_post_meta( $post_id, '_atelie_orc_horas_estimada', $horas_estimada );
		update_post_meta( $post_id, '_atelie_orc_status', $status );

		if ( $horas_reais_raw !== '' ) {
			update_post_meta( $post_id, '_atelie_orc_horas_reais', (float) $horas_reais_raw );
		} else {
			delete_post_meta( $post_id, '_atelie_orc_horas_reais' );
		}

		if ( $categoria_id > 0 ) {
			wp_set_object_terms( $post_id, array( $categoria_id ), 'product_cat' );
		}

		// Itens de materia-prima — recalcula o custo sempre no servidor, nunca confia no total que o JS mostrou
		// Cada item ainda passa por absint()/(float) no loop abaixo — wp_unslash aqui e so
		// pra tirar as barras que o WordPress adiciona de volta em superglobais.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cada item ja passa por absint()/(float) no loop logo abaixo.
		$itens_mp = isset( $_POST['atelie_orc_item_mp'] ) && is_array( $_POST['atelie_orc_item_mp'] ) ? wp_unslash( $_POST['atelie_orc_item_mp'] ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cada item ja passa por absint()/(float) no loop logo abaixo.
		$itens_qtd = isset( $_POST['atelie_orc_item_qtd'] ) && is_array( $_POST['atelie_orc_item_qtd'] ) ? wp_unslash( $_POST['atelie_orc_item_qtd'] ) : array();

		$itens_salvos    = array();
		$custo_materiais = 0.0;
		foreach ( $itens_mp as $i => $mp_id ) {
			$mp_id = absint( $mp_id );
			$qtd   = isset( $itens_qtd[ $i ] ) ? (float) $itens_qtd[ $i ] : 0.0;
			if ( $mp_id <= 0 || $qtd <= 0 ) {
				continue;
			}
			$preco_unitario   = (float) get_post_meta( $mp_id, '_atelie_mp_preco', true );
			$subtotal         = $preco_unitario * $qtd;
			$custo_materiais += $subtotal;
			$itens_salvos[]   = array(
				'materia_prima_id' => $mp_id,
				'quantidade'       => $qtd,
			);
		}
		update_post_meta( $post_id, '_atelie_orc_itens', $itens_salvos );
		update_post_meta( $post_id, '_atelie_orc_custo_materiais', $custo_materiais );

		$custo_mao_obra = $horas_estimada * $valor_hora;
		update_post_meta( $post_id, '_atelie_orc_custo_mao_obra', $custo_mao_obra );
		update_post_meta( $post_id, '_atelie_orc_custo_total', $custo_materiais + $custo_mao_obra );
	}
);

/**
 * AJAX: sugere horas com base na media de orcamentos ja PRODUZIDOS (horas
 * reais, nao a estimativa) da mesma categoria + porte. Fica mais precisa
 * conforme mais registros com horas reais forem salvos.
 */
add_action(
	'wp_ajax_atelie_sugerir_horas',
	function (): void {
		check_ajax_referer( 'atelie_sugerir_horas', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Sem permissão' ), 403 );
		}

		$categoria_id = isset( $_POST['categoria'] ) ? absint( $_POST['categoria'] ) : 0;
		$porte        = isset( $_POST['porte'] ) ? sanitize_text_field( wp_unslash( $_POST['porte'] ) ) : '';

		if ( $categoria_id <= 0 || $porte === '' ) {
			wp_send_json_success(
				array(
					'media'      => null,
					'quantidade' => 0,
				)
			);
		}

		$orcamentos = get_posts(
			array(
				'post_type'   => 'atelie_orcamento',
				'numberposts' => -1,
				'fields'      => 'ids',
				'tax_query'   => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => array( $categoria_id ),
					),
				),
				'meta_query'  => array(
					array(
						'key'   => '_atelie_orc_porte',
						'value' => $porte,
					),
					array(
						'key'     => '_atelie_orc_horas_reais',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$horas = array();
		foreach ( $orcamentos as $id ) {
			$h = get_post_meta( $id, '_atelie_orc_horas_reais', true );
			if ( $h !== '' && $h !== null ) {
				$horas[] = (float) $h;
			}
		}

		if ( empty( $horas ) ) {
			wp_send_json_success(
				array(
					'media'      => null,
					'quantidade' => 0,
				)
			);
		}

		wp_send_json_success(
			array(
				'media'      => round( array_sum( $horas ) / count( $horas ), 2 ),
				'quantidade' => count( $horas ),
			)
		);
	}
);

/**
 * Conecta ao preco do produto: na tela nativa do WooCommerce (usada ate o
 * painel proprio de IA existir — Fase 3), oferece puxar o custo calculado
 * de um orcamento salvo para o campo Preço regular. O numero preenchido e
 * o CUSTO — quem publica ainda precisa ajustar pra cima com a margem.
 */
add_action(
	'woocommerce_product_options_pricing',
	function (): void {
		$orcamentos = get_posts(
			array(
				'post_type'   => 'atelie_orcamento',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		if ( empty( $orcamentos ) ) {
			return;
		}

		$mapa_custo = array();
		foreach ( $orcamentos as $o ) {
			$mapa_custo[ $o->ID ] = (float) get_post_meta( $o->ID, '_atelie_orc_custo_total', true );
		}
		?>
	<div class="options_group" id="atelie-puxar-orcamento" data-custos="<?php echo esc_attr( wp_json_encode( $mapa_custo ) ); ?>">
		<p class="form-field">
			<label for="atelie-orcamento-select"><?php esc_html_e( 'Puxar custo de um orçamento', 'atelie-theme' ); ?></label>
			<select id="atelie-orcamento-select" style="width:60%">
				<option value=""><?php esc_html_e( '— nenhum —', 'atelie-theme' ); ?></option>
				<?php foreach ( $orcamentos as $o ) : ?>
					<option value="<?php echo esc_attr( $o->ID ); ?>">
						<?php echo esc_html( $o->post_title . ' — ' . number_format( (float) get_post_meta( $o->ID, '_atelie_orc_custo_total', true ), 2, ',', '.' ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button" id="atelie-orcamento-preencher"><?php esc_html_e( 'Preencher preço (custo, sem margem)', 'atelie-theme' ); ?></button>
		</p>
	</div>
	<script>
	(function () {
		var wrap = document.getElementById("atelie-puxar-orcamento");
		if (!wrap) { return; }
		var custos = JSON.parse(wrap.dataset.custos || "{}");
		document.getElementById("atelie-orcamento-preencher").addEventListener("click", function () {
			var id = document.getElementById("atelie-orcamento-select").value;
			var precoField = document.getElementById("_regular_price");
			if (id && precoField && custos[id] !== undefined) {
				precoField.value = custos[id].toFixed(2);
				precoField.dispatchEvent(new Event("change"));
			}
		});
	})();
	</script>
		<?php
	}
);

/**
 * JS da tela de orcamento (repeater de itens + botao de sugestao de horas).
 * Inline e pequeno de proposito — nao justifica um build step so pra isso.
 */
add_action( 'admin_footer-post.php', 'atelie_orcamento_admin_js' );
add_action( 'admin_footer-post-new.php', 'atelie_orcamento_admin_js' );
function atelie_orcamento_admin_js(): void {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'atelie_orcamento' ) {
		return;
	}
	?>
	<script>
	(function () {
		"use strict";
		var app = document.getElementById("atelie-orcamento-app");
		if (!app) { return; }

		var valorHoraPorArtesa = JSON.parse(app.dataset.valorHora || "{}");
		var precoPorMp = JSON.parse(app.dataset.precoMp || "{}");
		var itensSalvos = JSON.parse(document.getElementById("atelie-orc-itens-salvos").textContent || "[]");

		var corpo = document.getElementById("atelie-orc-itens-corpo");
		var template = document.getElementById("atelie-orc-item-template");

		function formatarReal(v) {
			return "R$ " + Number(v || 0).toFixed(2).replace(".", ",");
		}

		function recalcular() {
			var totalMateriais = 0;
			corpo.querySelectorAll(".atelie-orc-item-linha").forEach(function (linha) {
				var mpId = linha.querySelector(".atelie-orc-item-mp").value;
				var qtd = parseFloat(linha.querySelector(".atelie-orc-item-qtd").value) || 0;
				var preco = precoPorMp[mpId] || 0;
				var subtotal = preco * qtd;
				linha.querySelector(".atelie-orc-item-subtotal").textContent = formatarReal(subtotal);
				totalMateriais += subtotal;
			});

			var horas = parseFloat(document.getElementById("atelie-orc-horas-estimada").value) || 0;
			var valorHora = parseFloat(document.getElementById("atelie-orc-valor-hora").value) || 0;
			var totalMaoObra = horas * valorHora;

			document.getElementById("atelie-orc-total-materiais").textContent = formatarReal(totalMateriais);
			document.getElementById("atelie-orc-total-mao-obra").textContent = formatarReal(totalMaoObra);
			document.getElementById("atelie-orc-total-custo").innerHTML = "<strong>" + formatarReal(totalMateriais + totalMaoObra) + "</strong>";
		}

		function novaLinha(mpId, qtd) {
			var linha = template.content.firstElementChild.cloneNode(true);
			if (mpId) { linha.querySelector(".atelie-orc-item-mp").value = mpId; }
			if (qtd) { linha.querySelector(".atelie-orc-item-qtd").value = qtd; }
			linha.querySelector(".atelie-orc-item-mp").addEventListener("change", recalcular);
			linha.querySelector(".atelie-orc-item-qtd").addEventListener("input", recalcular);
			linha.querySelector(".atelie-orc-item-remover").addEventListener("click", function () {
				linha.remove();
				recalcular();
			});
			corpo.appendChild(linha);
		}

		document.getElementById("atelie-orc-add-item").addEventListener("click", function () { novaLinha(); recalcular(); });

		itensSalvos.forEach(function (item) { novaLinha(item.materia_prima_id, item.quantidade); });
		if (itensSalvos.length === 0) { novaLinha(); }

		document.getElementById("atelie-orc-artesa").addEventListener("change", function () {
			var valor = valorHoraPorArtesa[this.value];
			if (valor !== undefined) {
				document.getElementById("atelie-orc-valor-hora").value = valor;
				recalcular();
			}
		});

		document.getElementById("atelie-orc-horas-estimada").addEventListener("input", recalcular);
		document.getElementById("atelie-orc-valor-hora").addEventListener("input", recalcular);

		document.getElementById("atelie-orc-sugerir-horas").addEventListener("click", function () {
			var categoria = document.getElementById("atelie-orc-categoria").value;
			var porte = document.getElementById("atelie-orc-porte").value;
			var info = document.getElementById("atelie-orc-sugestao-info");

			if (!categoria || !porte) {
				info.textContent = "Selecione categoria e porte primeiro.";
				return;
			}

			var body = new URLSearchParams();
			body.set("action", "atelie_sugerir_horas");
			body.set("nonce", app.dataset.nonce);
			body.set("categoria", categoria);
			body.set("porte", porte);

			info.textContent = "Calculando...";
			fetch(app.dataset.ajaxUrl, { method: "POST", body: body })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json.success && json.data.media !== null) {
						document.getElementById("atelie-orc-horas-estimada").value = json.data.media;
						info.textContent = "Média de " + json.data.quantidade + " peça(s) já produzida(s).";
						recalcular();
					} else {
						info.textContent = "Sem histórico ainda para essa combinação — preencha manualmente.";
					}
				});
		});

		recalcular();
	})();
	</script>
	<?php
}

/**
 * Botao de ajuda "?" nas 3 telas nativas de CPT (Orcamentos, Materias-primas,
 * Artesas). Diferente das telas com classe de admin-page propria, aqui nao
 * ha um ponto de injecao dentro do <h1> nativo do WordPress — usa-se
 * 'admin_notices', que roda em toda tela de post/list-table desses CPTs.
 */
add_action(
	'admin_notices',
	function (): void {
		$screen = get_current_screen();
		if ( ! $screen || ! class_exists( 'Atelie_Ajuda_Drawer' ) ) {
			return;
		}

		$config = array(
			'atelie_orcamento'     => array(
				'titulo' => 'Orçamentos',
				'dicas'  => array(
					'O custo aqui calculado é <strong>custo</strong>, não preço de venda — a margem é ajustada manualmente depois.',
					'O botão "Sugerir horas pelo histórico" só aparece com categoria e porte escolhidos, e fica mais preciso conforme mais peças forem marcadas como "Produzido".',
					'Depois que a peça for feita de verdade, volte aqui, mude o status pra "Produzido" e preencha "Horas reais" — é esse número que alimenta a sugestão dos próximos orçamentos.',
					'Na tela do produto (WooCommerce), o botão "Puxar custo de um orçamento" preenche o preço regular com esse custo calculado.',
				),
			),
			'atelie_materia_prima' => array(
				'titulo' => 'Matérias-primas',
				'dicas'  => array(
					'Cadastre cada item comprado (lã, linha, enchimento, embalagem...) com preço e unidade — é esse preço que entra na conta de custo dos orçamentos.',
					'Manter o preço atualizado aqui é o que garante que o custo calculado nos orçamentos continue confiável.',
				),
			),
			'atelie_artesa'        => array(
				'titulo' => 'Artesãs',
				'dicas'  => array(
					'Cadastro simples de quem produz — não é um login do sistema, só um registro pra entrar na conta.',
					'O valor da hora cadastrado aqui é puxado automaticamente ao escolher a artesã num orçamento.',
				),
			),
		);

		if ( ! isset( $config[ $screen->post_type ] ) ) {
			return;
		}
		$c = $config[ $screen->post_type ];
		?>
	<div class="notice atelie-ajuda-notice" style="display:flex; align-items:center; gap:4px; padding:10px 12px;">
		<span><?php esc_html_e( 'Precisa de ajuda com esta tela?', 'atelie-theme' ); ?></span>
		<?php Atelie_Ajuda_Drawer::render( $c['titulo'], $c['dicas'], '/admin/precificacao/' ); ?>
	</div>
		<?php
	}
);
