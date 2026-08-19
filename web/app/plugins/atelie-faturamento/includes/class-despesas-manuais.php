<?php
/**
 * Despesas que nao vem de nenhum pedido do WooCommerce (hospedagem, dominio,
 * etc) — sem isso o lucro liquido da tela de Faturamento nunca bate com a
 * realidade. Lancamento manual simples, sem categoria nem recorrencia
 * automatica de proposito (comeca simples, cresce se fizer falta).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Despesas_Manuais
{
    private const VERSAO_SCHEMA = '1';
    private const OPCAO_VERSAO_SCHEMA = 'atelie_despesas_schema_versao';

    public static function nome_tabela(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'atelie_despesas_manuais';
    }

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
            quando DATE NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            valor DECIMAL(10,2) NOT NULL DEFAULT 0,
            usuario_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY quando (quando)
        ) {$charset_collate};";

        dbDelta($sql);

        update_option(self::OPCAO_VERSAO_SCHEMA, self::VERSAO_SCHEMA, false);
    }

    public static function lancar(string $descricao, float $valor, DateTimeInterface $quando): int
    {
        self::garantir_tabela();

        global $wpdb;
        $wpdb->insert(self::nome_tabela(), [
            'quando' => $quando->format('Y-m-d'),
            'descricao' => $descricao,
            'valor' => $valor,
            'usuario_id' => get_current_user_id(),
        ]);

        return (int) $wpdb->insert_id;
    }

    public static function remover(int $id): void
    {
        global $wpdb;
        $wpdb->delete(self::nome_tabela(), ['id' => $id], ['%d']);
    }

    /**
     * @return array<int, array{id: int, quando: string, descricao: string, valor: float}>
     */
    public static function listar_periodo(DateTimeInterface $inicio, DateTimeInterface $fim): array
    {
        self::garantir_tabela();

        global $wpdb;
        $tabela = self::nome_tabela();

        $linhas = $wpdb->get_results($wpdb->prepare(
            "SELECT id, quando, descricao, valor FROM {$tabela} WHERE quando >= %s AND quando <= %s ORDER BY quando DESC, id DESC",
            $inicio->format('Y-m-d'),
            $fim->format('Y-m-d')
        ), ARRAY_A);

        return is_array($linhas) ? $linhas : [];
    }

    public static function total_periodo(DateTimeInterface $inicio, DateTimeInterface $fim): float
    {
        self::garantir_tabela();

        global $wpdb;
        $tabela = self::nome_tabela();

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(valor) FROM {$tabela} WHERE quando >= %s AND quando <= %s",
            $inicio->format('Y-m-d'),
            $fim->format('Y-m-d')
        ));

        return $total !== null ? (float) $total : 0.0;
    }
}
