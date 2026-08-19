<?php
/**
 * Fonte unica de verdade pra config da conexao com o Google Drive — guarda o
 * refresh token (autorizacao fixa na conta de quem conectou, decisao
 * explicita do usuario: nao e a artesa quem autoriza, e o desenvolvedor).
 * Mesmo padrao de armazenamento de Atelie_Ai_Config (option protegida por
 * capability, sem criptografia extra — segue o precedente ja estabelecido
 * no projeto pra chave do Gemini).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Drive_Config
{
    private const OPCAO_REFRESH_TOKEN = 'atelie_drive_refresh_token';
    private const OPCAO_CONTA_EMAIL = 'atelie_drive_conta_email';

    public static function conectado(): bool
    {
        return self::obter_refresh_token() !== '';
    }

    public static function obter_refresh_token(): string
    {
        return trim((string) get_option(self::OPCAO_REFRESH_TOKEN, ''));
    }

    public static function conta_email(): string
    {
        return trim((string) get_option(self::OPCAO_CONTA_EMAIL, ''));
    }

    public static function salvar_conexao(string $refreshToken, string $contaEmail): void
    {
        update_option(self::OPCAO_REFRESH_TOKEN, $refreshToken, false);
        update_option(self::OPCAO_CONTA_EMAIL, $contaEmail, false);
    }

    public static function desconectar(): void
    {
        delete_option(self::OPCAO_REFRESH_TOKEN);
        delete_option(self::OPCAO_CONTA_EMAIL);
    }
}
