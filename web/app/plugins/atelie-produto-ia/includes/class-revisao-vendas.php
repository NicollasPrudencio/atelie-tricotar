<?php
/**
 * Gate de revisao compartilhado entre o painel de produto e (futuramente) o
 * painel de case: decide se um titulo/descricao precisa passar pela IA antes
 * de publicar, e guarda o resultado do bloqueio pra tela renderizar de novo
 * com o formulario preenchido + o problema encontrado.
 *
 * Regra (ver plano "IA como copiloto de vendas"): se nao usou IA (sem
 * baseline) OU se o texto foi editado depois da sugestao, tem que revisar.
 * Se a sugestao da IA foi publicada sem editar nada, nao revisa de novo.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Revisao_Vendas
{
    private const EXPIRACAO_BLOQUEIO = 10 * MINUTE_IN_SECONDS;

    public static function precisa_revisar(
        string $tituloAtual,
        string $descAtual,
        string $tituloOriginalIA,
        string $descOriginalIA
    ): bool {
        // Sem baseline de IA (veio de "Preencher manualmente") — sempre revisa.
        if ($tituloOriginalIA === '' && $descOriginalIA === '') {
            return true;
        }

        return $tituloAtual !== $tituloOriginalIA || $descAtual !== $descOriginalIA;
    }

    private static function chave_transient(string $contexto): string
    {
        return 'atelie_revisao_bloqueio_' . $contexto . '_' . get_current_user_id();
    }

    /**
     * @param array<string, mixed> $dadosFormulario
     * @param array{ok: bool, problemas: string[], titulo_sugerido: ?string, descricao_sugerida: ?string} $resultadoRevisao
     */
    public static function bloquear_e_redirecionar(string $contexto, array $dadosFormulario, array $resultadoRevisao, string $urlVolta): void
    {
        set_transient(self::chave_transient($contexto), [
            'dados' => $dadosFormulario,
            'revisao' => $resultadoRevisao,
        ], self::EXPIRACAO_BLOQUEIO);

        wp_safe_redirect(add_query_arg('revisao_ia', '1', $urlVolta));
        exit;
    }

    /**
     * @return null|array{dados: array<string, mixed>, revisao: array{ok: bool, problemas: string[], titulo_sugerido: ?string, descricao_sugerida: ?string}}
     */
    public static function obter_bloqueio(string $contexto): ?array
    {
        $valor = get_transient(self::chave_transient($contexto));

        return is_array($valor) ? $valor : null;
    }

    public static function limpar_bloqueio(string $contexto): void
    {
        delete_transient(self::chave_transient($contexto));
    }
}
