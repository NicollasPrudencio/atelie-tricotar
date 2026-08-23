<?php
/**
 * Conexao com o Google Drive (uma vez, so administrador) e importacao de
 * fotos de uma pasta compartilhada. Sem tela propria — a IU fica embutida em
 * "Novo Produto" (class-admin-page.php), do lado do upload direto, como o
 * unico jeito de criar VARIOS produtos de uma vez (organizado em pastas —
 * decisao explicita do usuario: sem upload solto de fotos sem organizacao
 * nenhuma, isso vira caos). Convencao: cada subpasta dentro da pasta
 * compartilhada vira um produto candidato, entregue pro pipeline de lote
 * (Atelie_Lote_Controller::criar_lote_de_grupos).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Drive_Admin_Page {

	private const PAGINA_RETORNO = 'atelie-novo-produto';

	public function registrar(): void {
		add_action( 'admin_post_atelie_drive_oauth_callback', array( $this, 'oauth_callback' ) );
		add_action( 'admin_post_atelie_drive_conectar', array( $this, 'iniciar_conexao' ) );
		add_action( 'admin_post_atelie_drive_desconectar', array( $this, 'desconectar' ) );
		add_action( 'admin_post_atelie_drive_importar', array( $this, 'importar' ) );
	}

	public static function mensagem_erro( string $codigo ): string {
		$limite = isset( $_GET['limite'] ) ? absint( $_GET['limite'] ) : 0;

		return match ( $codigo ) {
			'consentimento_negado' => 'A autorização foi cancelada no Google. Tente conectar de novo se foi sem querer.',
			'sessao_invalida' => 'A sessão de autorização expirou ou é inválida. Tente conectar de novo.',
			'sem_codigo' => 'O Google não retornou os dados esperados. Tente conectar de novo.',
			'nao_conectado' => 'Conecte o Google Drive antes de importar.',
			'link_invalido' => 'Não reconheci esse link — copie o link da pasta compartilhada direto do Google Drive.',
			'pasta_vazia' => 'Essa pasta não tem nenhuma subpasta dentro dela — confirme se é a pasta certa (cada subpasta vira um produto).',
			'sem_fotos_validas' => 'Não encontrei nenhuma foto válida nas subpastas dessa pasta.',
			'limite' => "Essa pasta tem mais subpastas do que o limite de {$limite} por lote — divida em pastas menores.",
			default => 'Não deu pra importar agora: ' . $codigo,
		};
	}

	public function iniciar_conexao(): void {
		if (
			! current_user_can( 'manage_options' )
			|| ! isset( $_POST['atelie_drive_conectar_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_drive_conectar_nonce'] ) ), 'atelie_drive_conectar' )
		) {
			wp_die( 'Ação não permitida.' );
		}

		// "state" e a protecao contra CSRF do proprio fluxo OAuth — o Google
		// devolve esse valor de volta no callback, comparamos pra confirmar
		// que a resposta corresponde a uma conexao que a gente mesmo iniciou.
		$state = wp_generate_password( 32, false );
		set_transient( 'atelie_drive_oauth_state_' . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS );

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- destino e o dominio do Google (accounts.google.com), wp_safe_redirect() bloquearia por nao ser o mesmo host.
		wp_redirect( add_query_arg( 'state', $state, Atelie_Google_Drive_Service::url_autorizacao() ) );
		exit;
	}

	public function oauth_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Ação não permitida.' );
		}

		$chave_state    = 'atelie_drive_oauth_state_' . get_current_user_id();
		$state_esperado = get_transient( $chave_state );
		$state_recebido = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		delete_transient( $chave_state );

		if ( $state_esperado === false || $state_recebido === '' || ! hash_equals( (string) $state_esperado, $state_recebido ) ) {
			wp_safe_redirect( add_query_arg( 'drive_erro', 'sessao_invalida', admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO ) ) );
			exit;
		}

		if ( isset( $_GET['error'] ) ) {
			wp_safe_redirect( add_query_arg( 'drive_erro', 'consentimento_negado', admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO ) ) );
			exit;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( $code === '' ) {
			wp_safe_redirect( add_query_arg( 'drive_erro', 'sem_codigo', admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO ) ) );
			exit;
		}

		$resultado = Atelie_Google_Drive_Service::trocar_codigo_por_token( $code );

		if ( ! $resultado['ok'] ) {
			wp_safe_redirect( add_query_arg( 'drive_erro', rawurlencode( $resultado['mensagem'] ), admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO ) ) );
			exit;
		}

		Atelie_Drive_Config::salvar_conexao( $resultado['refresh_token'], $resultado['email'] );

		wp_safe_redirect( add_query_arg( 'conectado', '1', admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO ) ) );
		exit;
	}

	public function desconectar(): void {
		if (
			! current_user_can( 'manage_options' )
			|| ! isset( $_POST['atelie_drive_desconectar_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_drive_desconectar_nonce'] ) ), 'atelie_drive_desconectar' )
		) {
			wp_die( 'Ação não permitida.' );
		}

		Atelie_Drive_Config::desconectar();

		wp_safe_redirect( add_query_arg( 'desconectado', '1', admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO ) ) );
		exit;
	}

	public function importar(): void {
		if (
			! current_user_can( 'edit_products' )
			|| ! isset( $_POST['atelie_drive_importar_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atelie_drive_importar_nonce'] ) ), 'atelie_drive_importar' )
		) {
			wp_die( 'Ação não permitida.' );
		}

		if ( ! Atelie_Drive_Config::conectado() ) {
			wp_safe_redirect( add_query_arg( 'drive_erro', 'nao_conectado', admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO ) ) );
			exit;
		}

		$lote_controller = new Atelie_Lote_Controller();
		$limite          = $lote_controller->limite_por_lote();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ids_da_lista() faz wp_unslash+sanitize_text_field+whitelist de regex internamente.
		$pastas_ids = $this->ids_da_lista( $_POST['pastas_ids'] ?? '' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ids_da_lista() faz wp_unslash+sanitize_text_field+whitelist de regex internamente.
		$fotos_soltas_ids = $this->ids_da_lista( $_POST['fotos_soltas_ids'] ?? '' );

		// Selecao pelo modal do Google Picker (uma ou mais pastas e/ou fotos soltas,
		// escolhidas a mao — decisao explicita do usuario: cada pasta selecionada vira
		// um produto com as fotos dela (ate 10), as fotos soltas selecionadas juntas
		// viram UM produto so (mesmas fotos, mesmo produto).
		if ( ! empty( $pastas_ids ) || ! empty( $fotos_soltas_ids ) ) {
			$total_produtos = count( $pastas_ids ) + ( ! empty( $fotos_soltas_ids ) ? 1 : 0 );
			if ( $total_produtos > $limite ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'drive_erro' => 'limite',
							'limite'     => $limite,
						),
						admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO )
					)
				);
				exit;
			}

			$grupos = array();

			foreach ( $pastas_ids as $pasta_id ) {
				$imagens      = array_slice( Atelie_Google_Drive_Service::listar_imagens( $pasta_id ), 0, Atelie_Admin_Page::LIMITE_FOTOS );
				$ids_do_grupo = array();
				foreach ( $imagens as $imagem ) {
					$anexo_id = $this->baixar_e_salvar_imagem( $imagem, 'drive-pasta' );
					if ( $anexo_id !== null ) {
						$ids_do_grupo[] = $anexo_id;
					}
				}
				if ( ! empty( $ids_do_grupo ) ) {
					$grupos[] = $ids_do_grupo;
				}
			}

			if ( ! empty( $fotos_soltas_ids ) ) {
				$ids_do_grupo = array();
				foreach ( array_slice( $fotos_soltas_ids, 0, Atelie_Admin_Page::LIMITE_FOTOS ) as $foto_id ) {
					$anexo_id = $this->baixar_e_salvar_imagem( array( 'id' => $foto_id ), 'drive-solta' );
					if ( $anexo_id !== null ) {
						$ids_do_grupo[] = $anexo_id;
					}
				}
				if ( ! empty( $ids_do_grupo ) ) {
					$grupos[] = $ids_do_grupo;
				}
			}

			if ( empty( $grupos ) ) {
				wp_safe_redirect( add_query_arg( 'drive_erro', 'sem_fotos_validas', admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO ) ) );
				exit;
			}

			$lote_id = $lote_controller->criar_lote_de_grupos( $grupos );
			wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=atelie-revisar-lote&lote=' . rawurlencode( $lote_id ) ) );
			exit;
		}

		// Fallback: link colado a mao (pasta unica, compartilhada com varias subpastas
		// dentro — cada subpasta vira um produto). Comportamento original da Fase E.
		$link     = isset( $_POST['pasta'] ) ? sanitize_text_field( wp_unslash( $_POST['pasta'] ) ) : '';
		$pasta_id = Atelie_Google_Drive_Service::extrair_id_da_pasta( $link );

		if ( $pasta_id === null ) {
			wp_safe_redirect( add_query_arg( 'drive_erro', 'link_invalido', admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO ) ) );
			exit;
		}

		$subpastas = Atelie_Google_Drive_Service::listar_subpastas( $pasta_id );

		if ( empty( $subpastas ) ) {
			wp_safe_redirect( add_query_arg( 'drive_erro', 'pasta_vazia', admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO ) ) );
			exit;
		}

		if ( count( $subpastas ) > $limite ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'drive_erro' => 'limite',
						'limite'     => $limite,
					),
					admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO )
				)
			);
			exit;
		}

		$grupos = array();
		foreach ( $subpastas as $subpasta ) {
			$imagens      = array_slice( Atelie_Google_Drive_Service::listar_imagens( $subpasta['id'] ), 0, Atelie_Admin_Page::LIMITE_FOTOS );
			$ids_do_grupo = array();

			foreach ( $imagens as $imagem ) {
				$anexo_id = $this->baixar_e_salvar_imagem( $imagem, $subpasta['name'] ?? 'drive' );
				if ( $anexo_id !== null ) {
					$ids_do_grupo[] = $anexo_id;
				}
			}

			if ( ! empty( $ids_do_grupo ) ) {
				$grupos[] = $ids_do_grupo;
			}
		}

		if ( empty( $grupos ) ) {
			wp_safe_redirect( add_query_arg( 'drive_erro', 'sem_fotos_validas', admin_url( 'edit.php?post_type=product&page=' . self::PAGINA_RETORNO ) ) );
			exit;
		}

		$lote_id = $lote_controller->criar_lote_de_grupos( $grupos );

		wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=atelie-revisar-lote&lote=' . rawurlencode( $lote_id ) ) );
		exit;
	}

	/**
	 * IDs de arquivo/pasta do Drive nao sao numericos (absint nao serve) — filtra pelo
	 * mesmo formato aceito em Atelie_Google_Drive_Service::extrair_id_da_pasta().
	 *
	 * @return array<int, string>
	 */
	private function ids_da_lista( mixed $valor ): array {
		$lista = is_string( $valor ) ? explode( ',', sanitize_text_field( wp_unslash( $valor ) ) ) : array();

		return array_values(
			array_filter(
				array_map( 'trim', $lista ),
				static function ( string $id ): bool {
					return preg_match( '#^[a-zA-Z0-9_-]{10,}$#', $id ) === 1;
				}
			)
		);
	}

	/**
	 * @param array{id: string, name: string, mimeType?: string} $imagem
	 */
	private function baixar_e_salvar_imagem( array $imagem, string $nome_subpasta ): ?int {
		return Atelie_Google_Drive_Service::baixar_e_salvar_na_biblioteca( $imagem, $nome_subpasta );
	}
}
