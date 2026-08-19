<?php
/**
 * Rastreio de custo das chamadas de IA — grava cada chamada real numa tabela
 * propria (nao em WP option: e historico que cresce sem limite, e nao pode
 * virar autoload bloatado) e estima custo futuro pela media do que ja rodou.
 * Preco e aproximado, configuravel na tela "Configurar IA" — nao e fatura
 * oficial do provedor, e uma estimativa pra controle interno.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Ai_Custo_Tracker
{
    private const VERSAO_SCHEMA = '1';
    private const OPCAO_VERSAO_SCHEMA = 'atelie_ai_gastos_schema_versao';
    private const OPCAO_TABELA_PRECOS = 'atelie_ai_tabela_precos';
    private const OPCAO_LIMITE_AVISO = 'atelie_ai_limite_aviso_mensal';
    private const OPCAO_CUSTO_IMAGEM = 'atelie_ai_custo_por_imagem';

    /**
     * Estimativas de token usadas quando ainda nao ha historico real pra
     * calcular a media — chutes conservadores so pra nao mostrar "R$ 0,00"
     * (que passaria a ideia errada de "gratis") antes da primeira chamada.
     */
    private const TOKENS_PADRAO_POR_OPERACAO = [
        'analisar' => ['entrada' => 1200, 'saida' => 220],
        'revisar_texto' => ['entrada' => 350, 'saida' => 180],
        'testar_conexao' => ['entrada' => 15, 'saida' => 10],
    ];

    public static function nome_tabela(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'atelie_ai_gastos';
    }

    /**
     * Roda em plugins_loaded — cria/atualiza a tabela se ainda nao existir
     * nessa instalacao (cobre tanto ativacao nova quanto upgrade de uma
     * instalacao que ja estava rodando antes dessa versao existir).
     */
    public static function garantir_tabela(): void
    {
        if (get_option(self::OPCAO_VERSAO_SCHEMA) === self::VERSAO_SCHEMA) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tabela = self::nome_tabela();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$tabela} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quando DATETIME NOT NULL,
            operacao VARCHAR(50) NOT NULL,
            tokens_entrada INT UNSIGNED NOT NULL DEFAULT 0,
            tokens_saida INT UNSIGNED NOT NULL DEFAULT 0,
            custo_estimado DECIMAL(10,6) NOT NULL DEFAULT 0,
            usuario_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY quando (quando),
            KEY operacao (operacao)
        ) {$charset_collate};";

        dbDelta($sql);

        update_option(self::OPCAO_VERSAO_SCHEMA, self::VERSAO_SCHEMA, false);
    }

    /**
     * @return array{entrada_por_1m: float, saida_por_1m: float}
     */
    public static function tabela_precos(): array
    {
        $salvo = get_option(self::OPCAO_TABELA_PRECOS, null);
        if (is_array($salvo) && isset($salvo['entrada_por_1m'], $salvo['saida_por_1m'])) {
            return [
                'entrada_por_1m' => (float) $salvo['entrada_por_1m'],
                'saida_por_1m' => (float) $salvo['saida_por_1m'],
            ];
        }

        // Valores de partida aproximados (R$) — confirmar/ajustar na tela "Configurar IA"
        // contra o preço atual do provedor. Não é cobrança oficial, é estimativa interna.
        return ['entrada_por_1m' => 1.50, 'saida_por_1m' => 6.00];
    }

    public static function salvar_tabela_precos(float $entradaPor1M, float $saidaPor1M): void
    {
        update_option(self::OPCAO_TABELA_PRECOS, [
            'entrada_por_1m' => $entradaPor1M,
            'saida_por_1m' => $saidaPor1M,
        ], false);
    }

    public static function calcular_custo(int $tokensEntrada, int $tokensSaida): float
    {
        $precos = self::tabela_precos();

        return ($tokensEntrada / 1_000_000 * $precos['entrada_por_1m'])
            + ($tokensSaida / 1_000_000 * $precos['saida_por_1m']);
    }

    public static function registrar(string $operacao, int $tokensEntrada, int $tokensSaida): float
    {
        return self::gravar_linha($operacao, $tokensEntrada, $tokensSaida, self::calcular_custo($tokensEntrada, $tokensSaida));
    }

    /**
     * Pra operações cobradas por unidade (ex.: edição de imagem, preço fixo
     * por imagem gerada), não por token — geração de imagem normalmente nem
     * devolve contagem de token comparável à de texto.
     */
    public static function registrar_fixo(string $operacao, float $custo): float
    {
        return self::gravar_linha($operacao, 0, 0, $custo);
    }

    private static function gravar_linha(string $operacao, int $tokensEntrada, int $tokensSaida, float $custo): float
    {
        self::garantir_tabela();

        global $wpdb;
        $wpdb->insert(self::nome_tabela(), [
            'quando' => current_time('mysql'),
            'operacao' => $operacao,
            'tokens_entrada' => $tokensEntrada,
            'tokens_saida' => $tokensSaida,
            'custo_estimado' => $custo,
            'usuario_id' => get_current_user_id(),
        ]);

        return $custo;
    }

    public static function custo_por_imagem(): float
    {
        $salvo = get_option(self::OPCAO_CUSTO_IMAGEM, null);
        if ($salvo !== null && $salvo !== '') {
            return (float) $salvo;
        }

        // Estimativa de partida (R$) — sem chamada real bem-sucedida ainda pra
        // calibrar (ver Atelie_Ai_Vision_Service_Gemini::editarImagem). Ajustar
        // na tela "Configurar IA" assim que o custo real for confirmado.
        return 0.15;
    }

    public static function salvar_custo_por_imagem(float $custo): void
    {
        update_option(self::OPCAO_CUSTO_IMAGEM, $custo, false);
    }

    /**
     * Estimativa da PRÓXIMA chamada dessa operação — média das últimas 20
     * chamadas reais, ou o chute padrão se ainda não rodou nenhuma vez.
     */
    public static function estimar(string $operacao): float
    {
        if ($operacao === 'editar_imagem') {
            return self::custo_por_imagem();
        }

        self::garantir_tabela();

        global $wpdb;
        $tabela = self::nome_tabela();
        $media = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(tokens_entrada) AS media_entrada, AVG(tokens_saida) AS media_saida
             FROM (
                 SELECT tokens_entrada, tokens_saida FROM {$tabela}
                 WHERE operacao = %s ORDER BY id DESC LIMIT 20
             ) recentes",
            $operacao
        ));

        if ($media !== null && $media->media_entrada !== null) {
            return self::calcular_custo((int) round((float) $media->media_entrada), (int) round((float) $media->media_saida));
        }

        $padrao = self::TOKENS_PADRAO_POR_OPERACAO[$operacao] ?? ['entrada' => 500, 'saida' => 150];

        return self::calcular_custo($padrao['entrada'], $padrao['saida']);
    }

    public static function gasto_mes_atual(): float
    {
        self::garantir_tabela();

        global $wpdb;
        $tabela = self::nome_tabela();
        $inicio_mes = gmdate('Y-m-01 00:00:00');

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(custo_estimado) FROM {$tabela} WHERE quando >= %s",
            $inicio_mes
        ));

        return $total !== null ? (float) $total : 0.0;
    }

    /**
     * Gasto de IA num período arbitrário — usado pela tela "Faturamento"
     * (plugin atelie-faturamento), que filtra por período escolhido, não só
     * o mês corrente como gasto_mes_atual().
     */
    public static function gasto_periodo(DateTimeInterface $inicio, DateTimeInterface $fim): float
    {
        self::garantir_tabela();

        global $wpdb;
        $tabela = self::nome_tabela();

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(custo_estimado) FROM {$tabela} WHERE quando >= %s AND quando <= %s",
            $inicio->format('Y-m-d H:i:s'),
            $fim->format('Y-m-d H:i:s')
        ));

        return $total !== null ? (float) $total : 0.0;
    }

    public static function limite_aviso(): float
    {
        return (float) get_option(self::OPCAO_LIMITE_AVISO, 20.0);
    }

    public static function salvar_limite_aviso(float $limite): void
    {
        update_option(self::OPCAO_LIMITE_AVISO, $limite, false);
    }

    public static function deve_avisar(): bool
    {
        $limite = self::limite_aviso();

        return $limite > 0 && self::gasto_mes_atual() >= $limite;
    }

    /**
     * @return array<int, array{quando: string, operacao: string, custo_estimado: float}>
     */
    public static function historico_recente(int $quantidade = 15): array
    {
        self::garantir_tabela();

        global $wpdb;
        $tabela = self::nome_tabela();

        $linhas = $wpdb->get_results($wpdb->prepare(
            "SELECT quando, operacao, custo_estimado FROM {$tabela} ORDER BY id DESC LIMIT %d",
            $quantidade
        ), ARRAY_A);

        return is_array($linhas) ? $linhas : [];
    }
}
