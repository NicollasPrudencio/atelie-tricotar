<?php
/**
 * Chamadas cruas (wp_remote_get/post) pra API do Google Drive e pro OAuth do
 * Google — de proposito sem o pacote google/apiclient via Composer (e enorme,
 * centenas de classes pra um punhado de endpoints REST simples). Mesmo
 * espirito das classes de IA: sem SDK pesado, so o necessario.
 */

if (!defined('ABSPATH')) {
    exit;
}

use function Env\env;

class Atelie_Google_Drive_Service
{
    private const ESCOPO = 'https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/userinfo.email';
    private const CACHE_ACCESS_TOKEN = 'atelie_drive_access_token';

    public static function redirect_uri(): string
    {
        return admin_url('admin-post.php?action=atelie_drive_oauth_callback');
    }

    /**
     * access_type=offline + prompt=consent garantem que o Google devolve um
     * refresh_token mesmo se essa conta ja tiver autorizado o app antes —
     * sem os dois, o Google só reenvia refresh_token na PRIMEIRA autorização.
     */
    public static function url_autorizacao(): string
    {
        $params = [
            'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
            'redirect_uri' => self::redirect_uri(),
            'response_type' => 'code',
            'scope' => self::ESCOPO,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * @return array{ok: bool, mensagem: string, refresh_token?: string, email?: string}
     */
    public static function trocar_codigo_por_token(string $code): array
    {
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body' => [
                'code' => $code,
                'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
                'redirect_uri' => self::redirect_uri(),
                'grant_type' => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'mensagem' => 'Falha de conexão: ' . $response->get_error_message()];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $body = is_array($body) ? $body : [];

        if ($status !== 200 || empty($body['refresh_token']) || empty($body['access_token'])) {
            $mensagem = $body['error_description'] ?? $body['error'] ?? ('HTTP ' . $status);
            return ['ok' => false, 'mensagem' => 'Não deu pra conectar: ' . $mensagem];
        }

        return [
            'ok' => true,
            'mensagem' => 'Conectado com sucesso.',
            'refresh_token' => (string) $body['refresh_token'],
            'email' => self::obter_email_da_conta((string) $body['access_token']),
        ];
    }

    private static function obter_email_da_conta(string $accessToken): string
    {
        $response = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return isset($body['email']) ? (string) $body['email'] : '';
    }

    /**
     * Renova o access token via refresh token quando preciso — cacheado em
     * transient (com margem de 2 minutos antes da expiração real) pra não
     * renovar toda hora dentro da mesma janela de uso.
     */
    public static function token_de_acesso_valido(): ?string
    {
        $cache = get_transient(self::CACHE_ACCESS_TOKEN);
        if (is_string($cache) && $cache !== '') {
            return $cache;
        }

        $refresh_token = Atelie_Drive_Config::obter_refresh_token();
        if ($refresh_token === '') {
            return null;
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body' => [
                'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $access_token = $body['access_token'] ?? null;

        if (!is_string($access_token) || $access_token === '') {
            return null;
        }

        $expira_em = (int) ($body['expires_in'] ?? 3600);
        set_transient(self::CACHE_ACCESS_TOKEN, $access_token, max(60, $expira_em - 120));

        return $access_token;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function listar_subpastas(string $pastaId): array
    {
        return self::listar_arquivos($pastaId, "mimeType='application/vnd.google-apps.folder'");
    }

    /**
     * @return array<int, array{id: string, name: string, mimeType: string}>
     */
    public static function listar_imagens(string $pastaId): array
    {
        return self::listar_arquivos($pastaId, "mimeType contains 'image/'");
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function listar_arquivos(string $pastaId, string $filtroMime): array
    {
        $token = self::token_de_acesso_valido();
        if ($token === null) {
            return [];
        }

        $query = sprintf("'%s' in parents and %s and trashed=false", $pastaId, $filtroMime);

        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
            'q' => $query,
            'fields' => 'files(id,name,mimeType)',
            'pageSize' => 200,
        ]);

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return is_array($body['files'] ?? null) ? $body['files'] : [];
    }

    public static function baixar_arquivo(string $fileId): ?string
    {
        $token = self::token_de_acesso_valido();
        if ($token === null) {
            return null;
        }

        $response = wp_remote_get("https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media", [
            'timeout' => 30,
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Aceita tanto o link completo compartilhado (.../folders/ID?usp=sharing)
     * quanto o ID colado direto — pra não travar se a pessoa colar de um
     * jeito ou de outro.
     */
    public static function extrair_id_da_pasta(string $urlOuId): ?string
    {
        $urlOuId = trim($urlOuId);

        if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $urlOuId, $matches)) {
            return $matches[1];
        }

        if (preg_match('#^[a-zA-Z0-9_-]{10,}$#', $urlOuId)) {
            return $urlOuId;
        }

        return null;
    }
}
