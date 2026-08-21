<?php
/**
 * Plugin Name: Atelie Bootstrap
 * Description: Hardening e configuracoes base do site do ateliê (carregado sempre, nao pode ser desativado pelo admin).
 * Version: 0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Todo WordPress novo nasce com permalink "Plain" (?p=123), que o WooCommerce
 * nao recomenda e quebra a API REST que o carrinho/checkout em blocos usa
 * (descoberto testando localmente: sem isso o carrinho nao persiste). Ambiente
 * novo (staging, producao) cairia no mesmo problema sem essa correcao — por
 * isso fica automatico aqui, nao como passo manual de setup.
 */
add_action(
	'init',
	function (): void {
		if ( get_option( 'permalink_structure' ) === '' ) {
			global $wp_rewrite;
			$wp_rewrite->set_permalink_structure( '/%postname%/' );
			$wp_rewrite->flush_rules();
		}
	},
	5
);

/**
 * 2FA obrigatorio pra qualquer conta com acesso ao painel — mais de uma
 * pessoa vai ter login (ver plano, secao "Papeis e permissoes no painel").
 * O plugin WP 2FA ja vem com tudo que a regra pedida precisa, nativo (sem
 * codigo customizado pra logica de bloqueio):
 * - 'grace-policy' + 'grace-period'/'grace-period-denominator': carencia de
 *   24h pra configurar o MFA depois da conta criada.
 * - Sem 'grace-policy-notification-show' definido como 'dashboard-notification'
 *   (o padrao do plugin ja bloqueia o dashboard durante a carencia, so libera
 *   configurar o MFA — nao e so um aviso dispensavel).
 * - 'grace-policy-after-expire-action' => 'manual-block': passadas as 24h
 *   ainda sem MFA configurado, a conta trava de vez — so um admin consegue
 *   destravar (botao nativo "Unlock user and reset the grace period" na
 *   tela de perfil do usuario).
 */
add_action(
	'init',
	function (): void {
		$policy = get_option( 'wp_2fa_policy', array() );
		$alvo   = array(
			'enforcement-policy'               => 'all-users',
			'grace-policy'                     => 'use-grace-period',
			'grace-period'                     => '24',
			'grace-period-denominator'         => 'hours',
			'grace-policy-after-expire-action' => 'manual-block',
		);

		$precisa_atualizar = false;
		foreach ( $alvo as $chave => $valor ) {
			if ( ! is_array( $policy ) || ( $policy[ $chave ] ?? '' ) !== $valor ) {
				$precisa_atualizar = true;
				break;
			}
		}

		if ( $precisa_atualizar ) {
			$policy = is_array( $policy ) ? array_merge( $policy, $alvo ) : $alvo;
			update_option( 'wp_2fa_policy', $policy );
		}
	},
	5
);

/**
 * Rede de seguranca pro bloqueio de 2FA: testado ao vivo (2026-08-21) que o
 * formulario de "configure o 2FA" do WP 2FA e mostrado so como resposta do
 * POST de login em si — os cookies de sessao ja saem validos nessa mesma
 * resposta, entao acessar o wp-admin direto por URL (sem clicar no botao do
 * formulario) pula o bloqueio inteiro. Isso quebra a regra pedida ("bloqueio
 * persistente a cada acesso", nao um aviso de uma vez so). Esse hook fecha o
 * furo usando o proprio estado que o plugin ja mantem (User_Helper), sem
 * reimplementar a logica de carencia/expiracao dele.
 */
add_action(
	'admin_init',
	function (): void {
		if ( ! is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! class_exists( '\WP2FA\Admin\Helpers\User_Helper' ) ) {
			return;
		}

		$user_helper = '\WP2FA\Admin\Helpers\User_Helper';
		if ( ! $user_helper::is_enforced() || $user_helper::is_user_using_two_factor() ) {
			return;
		}

		// Ja esta na propria tela de configurar o 2FA — nao redireciona de novo.
		if ( isset( $GLOBALS['pagenow'] ) && 'profile.php' === $GLOBALS['pagenow'] ) {
			return;
		}

		wp_safe_redirect( admin_url( 'profile.php?show=wp-2fa-setup' ) );
		exit;
	},
	20
);

/**
 * Fora de producao (dev local, staging via tunel), o site fica invisivel
 * pra buscadores automaticamente — evita indexar um ambiente de teste sem
 * querer, mesmo estando publicamente acessivel via tunel. Nunca aplica em
 * producao, onde a indexacao normal precisa continuar ligada.
 */
add_action(
	'init',
	function (): void {
		if ( WP_ENV !== 'production' && get_option( 'blog_public' ) !== '0' ) {
			update_option( 'blog_public', '0' );
		}
	},
	5
);

/**
 * Papel "Vendedora" — acesso restrito a produto/pedido, sem acesso a
 * plugins, temas, configuracoes de pagamento ou usuarios.
 * Ver plano do projeto, secao "Papeis e permissoes no painel".
 */
add_action(
	'init',
	function (): void {
		$capabilities_version = '2';

		if ( get_option( 'atelie_vendedora_caps_version' ) === $capabilities_version ) {
			return;
		}

		remove_role( 'atelie_vendedora' );
		add_role(
			'atelie_vendedora',
			'Vendedora',
			array(
				'read'                        => true,
				'upload_files'                => true,
				// Produtos
				'edit_products'               => true,
				'edit_published_products'     => true,
				'publish_products'            => true,
				'read_private_products'       => true,
				// Pedidos (inclui gerar etiqueta de frete via Melhor Envio na tela do pedido)
				'edit_shop_orders'            => true,
				'edit_others_shop_orders'     => true,
				'read_private_shop_orders'    => true,
				// Portfolio/cases
				'edit_atelie_cases'           => true,
				'edit_published_atelie_cases' => true,
				'publish_atelie_cases'        => true,
				'read_private_atelie_cases'   => true,
			)
		);

		update_option( 'atelie_vendedora_caps_version', $capabilities_version );

		// capability_type customizado (atelie_case) nao é herdado pelo Administrador
		// automaticamente — sem isso, nem o admin consegue gerenciar o portfolio.
		$administrator = get_role( 'administrator' );
		if ( $administrator !== null ) {
			foreach ( array( 'edit_atelie_case', 'read_atelie_case', 'delete_atelie_case', 'edit_atelie_cases', 'edit_others_atelie_cases', 'publish_atelie_cases', 'read_private_atelie_cases', 'delete_atelie_cases', 'delete_private_atelie_cases', 'delete_published_atelie_cases', 'delete_others_atelie_cases', 'edit_private_atelie_cases', 'edit_published_atelie_cases' ) as $cap ) {
				$administrator->add_cap( $cap );
			}
		}
	}
);

/**
 * Campos de disponibilidade e prazo de producao — a maioria dos produtos do
 * ateliê é feita sob encomenda. Ver plano, secao "Disponibilidade — pronta
 * entrega vs. sob encomenda". Fica no admin nativo do WooCommerce ate a
 * Fase 3 substituir por uma tela propria.
 */
add_action(
	'woocommerce_product_options_general_product_data',
	function (): void {
		woocommerce_wp_select(
			array(
				'id'      => '_atelie_disponibilidade',
				'label'   => __( 'Disponibilidade', 'atelie-theme' ),
				'options' => array(
					'pronta_entrega' => __( 'Pronta entrega', 'atelie-theme' ),
					'sob_encomenda'  => __( 'Sob encomenda', 'atelie-theme' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_atelie_prazo_producao',
				'label'             => __( 'Prazo de produção (dias)', 'atelie-theme' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'desc_tip'          => true,
				'description'       => __( 'Preencher só quando "Sob encomenda". Somado ao prazo de frete na comunicação ao cliente.', 'atelie-theme' ),
			)
		);
	}
);

add_action(
	'woocommerce_process_product_meta',
	function ( int $post_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- hook 'woocommerce_process_product_meta' so dispara depois que o WooCommerce ja verificou o nonce da tela de edicao de produto.
		if ( isset( $_POST['_atelie_disponibilidade'] ) ) {
			$disponibilidade = sanitize_text_field( wp_unslash( $_POST['_atelie_disponibilidade'] ) );
			if ( in_array( $disponibilidade, array( 'pronta_entrega', 'sob_encomenda' ), true ) ) {
				update_post_meta( $post_id, '_atelie_disponibilidade', $disponibilidade );
			}
		}

		if ( isset( $_POST['_atelie_prazo_producao'] ) ) {
			$prazo = absint( wp_unslash( $_POST['_atelie_prazo_producao'] ) );
			update_post_meta( $post_id, '_atelie_prazo_producao', $prazo );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
);

/**
 * O campo "Título" de cada método do Melhor Envio (config da zona de entrega)
 * não é respeitado pelo plugin — a classe base não recarrega a opção salva ao
 * montar a cotação, então o rótulo sempre volta pro padrão com a marca deles
 * ("Correios Pac (Melhor Envio)"), mesmo mudando o campo pelo admin. Cliente
 * não precisa saber qual parceiro de envio o ateliê usa por trás, só a
 * modalidade — removemos esse sufixo aqui em vez de depender do plugin.
 */
add_filter(
	'woocommerce_package_rates',
	function ( array $tarifas ): array {
		foreach ( $tarifas as $tarifa ) {
			if ( strpos( $tarifa->get_method_id(), 'melhorenvio_' ) === 0 ) {
				$tarifa->label = trim( str_replace( ' (Melhor Envio)', '', $tarifa->label ) );
			}
		}
		return $tarifas;
	},
	20
);

/**
 * Portfolio/cases — vitrine de trabalhos sob encomenda, sem preco/compra,
 * separada do catalogo. Ver plano, secao "Portfolio/cases e encomenda
 * personalizada". Galeria de multiplas fotos por case fica para quando a
 * pagina de listagem for desenhada (Fase 1, design ainda pendente) — por
 * ora, uma foto de destaque por case ja cobre o uso inicial.
 */
add_action(
	'init',
	function (): void {
		register_post_type(
			'atelie_case',
			array(
				'label'           => __( 'Cases', 'atelie-theme' ),
				'labels'          => array(
					'name'          => __( 'Cases', 'atelie-theme' ),
					'singular_name' => __( 'Case', 'atelie-theme' ),
					'add_new_item'  => __( 'Novo case', 'atelie-theme' ),
				),
				'public'          => true,
				'has_archive'     => true,
				'rewrite'         => array( 'slug' => 'portfolio' ),
				'menu_icon'       => 'dashicons-images-alt2',
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'capability_type' => array( 'atelie_case', 'atelie_cases' ),
				'map_meta_cap'    => true,
			)
		);
	}
);
