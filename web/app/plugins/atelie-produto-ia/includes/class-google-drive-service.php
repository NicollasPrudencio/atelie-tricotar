<?php
/**
 * Chamadas cruas (wp_remote_get/post) pra API do Google Drive e pro OAuth do
 * Google — de proposito sem o pacote google/apiclient via Composer (e enorme,
 * centenas de classes pra um punhado de endpoints REST simples). Mesmo
 * espirito das classes de IA: sem SDK pesado, so o necessario.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function Env\env;

class Atelie_Google_Drive_Service {

	private const ESCOPO             = 'https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/userinfo.email';
	private const CACHE_ACCESS_TOKEN = 'atelie_drive_access_token';

	public static function redirect_uri(): string {
		return admin_url( 'admin-post.php?action=atelie_drive_oauth_callback' );
	}

	/**
	 * access_type=offline + prompt=consent garantem que o Google devolve um
	 * refresh_token mesmo se essa conta ja tiver autorizado o app antes —
	 * sem os dois, o Google só reenvia refresh_token na PRIMEIRA autorização.
	 */
	public static function url_autorizacao(): string {
		$params = array(
			'client_id'     => env( 'GOOGLE_DRIVE_CLIENT_ID' ),
			'redirect_uri'  => self::redirect_uri(),
			'response_type' => 'code',
			'scope'         => self::ESCOPO,
			'access_type'   => 'offline',
			'prompt'        => 'consent',
		);

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
	}

	/**
	 * @return array{ok: bool, mensagem: string, refresh_token?: string, email?: string}
	 */
	public static function trocar_codigo_por_token( string $code ): array {
		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => env( 'GOOGLE_DRIVE_CLIENT_ID' ),
					'client_secret' => env( 'GOOGLE_DRIVE_CLIENT_SECRET' ),
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'       => false,
				'mensagem' => 'Falha de conexão: ' . $response->get_error_message(),
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$body   = is_array( $body ) ? $body : array();

		if ( $status !== 200 || empty( $body['refresh_token'] ) || empty( $body['access_token'] ) ) {
			$mensagem = $body['error_description'] ?? $body['error'] ?? ( 'HTTP ' . $status );
			return array(
				'ok'       => false,
				'mensagem' => 'Não deu pra conectar: ' . $mensagem,
			);
		}

		return array(
			'ok'            => true,
			'mensagem'      => 'Conectado com sucesso.',
			'refresh_token' => (string) $body['refresh_token'],
			'email'         => self::obter_email_da_conta( (string) $body['access_token'] ),
		);
	}

	private static function obter_email_da_conta( string $access_token ): string {
		$response = wp_remote_get(
			'https://www.googleapis.com/oauth2/v2/userinfo',
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return isset( $body['email'] ) ? (string) $body['email'] : '';
	}

	/**
	 * Renova o access token via refresh token quando preciso — cacheado em
	 * transient (com margem de 2 minutos antes da expiração real) pra não
	 * renovar toda hora dentro da mesma janela de uso.
	 */
	public static function token_de_acesso_valido(): ?string {
		$cache = get_transient( self::CACHE_ACCESS_TOKEN );
		if ( is_string( $cache ) && $cache !== '' ) {
			return $cache;
		}

		$refresh_token = Atelie_Drive_Config::obter_refresh_token();
		if ( $refresh_token === '' ) {
			return null;
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => env( 'GOOGLE_DRIVE_CLIENT_ID' ),
					'client_secret' => env( 'GOOGLE_DRIVE_CLIENT_SECRET' ),
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}

		$body         = json_decode( wp_remote_retrieve_body( $response ), true );
		$access_token = $body['access_token'] ?? null;

		if ( ! is_string( $access_token ) || $access_token === '' ) {
			return null;
		}

		$expira_em = (int) ( $body['expires_in'] ?? 3600 );
		set_transient( self::CACHE_ACCESS_TOKEN, $access_token, max( 60, $expira_em - 120 ) );

		return $access_token;
	}

	/**
	 * @return array<int, array{id: string, name: string}>
	 */
	public static function listar_subpastas( string $pasta_id ): array {
		return self::listar_arquivos( "'{$pasta_id}' in parents and mimeType='application/vnd.google-apps.folder'" );
	}

	/**
	 * @return array<int, array{id: string, name: string, mimeType: string}>
	 */
	public static function listar_imagens( string $pasta_id ): array {
		return self::listar_arquivos( "'{$pasta_id}' in parents and mimeType contains 'image/'" );
	}

	/**
	 * Pastas e fotos juntas, pro modal de navegação/seleção — sem $pasta_id, lista o que foi
	 * COMPARTILHADO com a conta conectada (é assim que a pasta da artesã aparece pro
	 * desenvolvedor, não em "Meu Drive"). Com $pasta_id, lista o conteúdo daquela pasta.
	 *
	 * @return array<int, array{id: string, name: string, mimeType: string, thumbnailLink?: string}>
	 */
	public static function listar_conteudo( ?string $pasta_id ): array {
		$filtro_pai = $pasta_id !== null
			? "'{$pasta_id}' in parents"
			: 'sharedWithMe=true';

		$query = "{$filtro_pai} and (mimeType='application/vnd.google-apps.folder' or mimeType contains 'image/')";

		return self::listar_arquivos( $query, 'files(id,name,mimeType,thumbnailLink)' );
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function listar_arquivos( string $query, string $campos = 'files(id,name,mimeType)' ): array {
		$token = self::token_de_acesso_valido();
		if ( $token === null ) {
			return array();
		}

		$url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(
			array(
				'q'        => $query . ' and trashed=false',
				'fields'   => $campos,
				'pageSize' => 200,
			)
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $body['files'] ?? null ) ? $body['files'] : array();
	}

	public static function baixar_arquivo( string $file_id ): ?string {
		$token = self::token_de_acesso_valido();
		if ( $token === null ) {
			return null;
		}

		$response = wp_remote_get(
			"https://www.googleapis.com/drive/v3/files/{$file_id}?alt=media",
			array(
				'timeout' => 30,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Baixa um arquivo do Drive e sobe pra Biblioteca de Mídia do WordPress — usado tanto
	 * pela criação em massa (grupos por pasta) quanto pelo caminho de fotos soltas
	 * selecionadas no modal, que viram um produto só, direto na tela "Novo Produto".
	 *
	 * @param array{id: string, name?: string, mimeType?: string} $imagem
	 */
	public static function baixar_e_salvar_na_biblioteca( array $imagem, string $prefixo_nome = 'drive' ): ?int {
		$dados_binarios = self::baixar_arquivo( $imagem['id'] );
		if ( $dados_binarios === null ) {
			return null;
		}

		$mime_type = $imagem['mimeType'] ?? 'image/jpeg';
		$extensao  = match ( $mime_type ) {
			'image/png' => 'png',
			'image/webp' => 'webp',
			'image/gif' => 'gif',
			default => 'jpg',
		};

		$nome_arquivo = sanitize_file_name( $prefixo_nome . '-' . ( $imagem['name'] ?? 'foto' ) . '.' . $extensao );

		$upload = wp_upload_bits( $nome_arquivo, null, $dados_binarios );
		if ( ! empty( $upload['error'] ) ) {
			return null;
		}

		$anexo_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime_type,
				'post_title'     => sanitize_file_name( $nome_arquivo ),
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		if ( is_wp_error( $anexo_id ) || ! $anexo_id ) {
			return null;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadados = wp_generate_attachment_metadata( $anexo_id, $upload['file'] );
		wp_update_attachment_metadata( $anexo_id, $metadados );

		return $anexo_id;
	}

	/**
	 * Aceita tanto o link completo compartilhado (.../folders/ID?usp=sharing)
	 * quanto o ID colado direto — pra não travar se a pessoa colar de um
	 * jeito ou de outro.
	 */
	public static function extrair_id_da_pasta( string $url_ou_id ): ?string {
		$url_ou_id = trim( $url_ou_id );

		if ( preg_match( '#/folders/([a-zA-Z0-9_-]+)#', $url_ou_id, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '#^[a-zA-Z0-9_-]{10,}$#', $url_ou_id ) ) {
			return $url_ou_id;
		}

		return null;
	}
}
