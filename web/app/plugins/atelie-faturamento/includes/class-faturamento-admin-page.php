<?php
/**
 * Tela "Faturamento" — resumo de receita vs custos (IA, taxa Mercado Pago,
 * frete, despesas manuais) e lucro líquido, com filtro de período. Dado
 * financeiro do negócio inteiro, não é tela de operação do dia a dia —
 * capability manage_options, a Vendedora não vê isso.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Faturamento_Admin_Page
{
    private const SLUG = 'atelie-faturamento';

    public function registrar(): void
    {
        add_action('admin_menu', [$this, 'adicionar_menu']);
        add_action('admin_enqueue_scripts', [$this, 'carregar_assets']);
        add_action('admin_post_atelie_lancar_despesa', [$this, 'lancar_despesa']);
        add_action('admin_post_atelie_remover_despesa', [$this, 'remover_despesa']);
    }

    public function carregar_assets(string $hook): void
    {
        if (strpos($hook, self::SLUG) === false) {
            return;
        }

        wp_enqueue_style(
            'atelie-faturamento-admin',
            plugins_url('assets/faturamento.css', dirname(__DIR__) . '/atelie-faturamento.php'),
            [],
            '0.1.0'
        );
    }

    public function adicionar_menu(): void
    {
        add_menu_page(
            'Faturamento',
            'Faturamento',
            'manage_options',
            self::SLUG,
            [$this, 'renderizar'],
            'dashicons-chart-area',
            59
        );
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: string}
     */
    private function periodo_selecionado(): array
    {
        $agora = current_datetime();
        $periodo = isset($_GET['periodo']) ? sanitize_key(wp_unslash($_GET['periodo'])) : 'este_mes';

        switch ($periodo) {
            case 'mes_passado':
                $inicio = $agora->modify('first day of last month')->setTime(0, 0);
                $fim = $agora->modify('last day of last month')->setTime(23, 59, 59);
                break;

            case 'ultimos_30':
                $inicio = $agora->modify('-30 days')->setTime(0, 0);
                $fim = $agora->setTime(23, 59, 59);
                break;

            case 'personalizado':
                $de = isset($_GET['de']) ? sanitize_text_field(wp_unslash($_GET['de'])) : '';
                $ate = isset($_GET['ate']) ? sanitize_text_field(wp_unslash($_GET['ate'])) : '';
                $inicio_tmp = $de !== '' ? DateTimeImmutable::createFromFormat('Y-m-d', $de, $agora->getTimezone()) : false;
                $fim_tmp = $ate !== '' ? DateTimeImmutable::createFromFormat('Y-m-d', $ate, $agora->getTimezone()) : false;

                if ($inicio_tmp === false || $fim_tmp === false) {
                    // datas inválidas — cai pro padrão em vez de quebrar a tela
                    $periodo = 'este_mes';
                    $inicio = $agora->modify('first day of this month')->setTime(0, 0);
                    $fim = $agora->setTime(23, 59, 59);
                    break;
                }

                $inicio = $inicio_tmp->setTime(0, 0);
                $fim = $fim_tmp->setTime(23, 59, 59);
                break;

            case 'este_mes':
            default:
                $periodo = 'este_mes';
                $inicio = $agora->modify('first day of this month')->setTime(0, 0);
                $fim = $agora->setTime(23, 59, 59);
                break;
        }

        return [$inicio, $fim, $periodo];
    }

    public function renderizar(): void
    {
        if (isset($_GET['lancado'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Despesa lançada.</p></div>';
        }
        if (isset($_GET['removido'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Despesa removida.</p></div>';
        }

        [$inicio, $fim, $periodo_atual] = $this->periodo_selecionado();
        $relatorio = new Atelie_Relatorio_Faturamento($inicio, $fim);

        $receita = $relatorio->receita();
        $taxa_mp = $relatorio->taxa_mercado_pago();
        $custo_frete = $relatorio->custo_frete();
        $custo_ia = $relatorio->custo_ia();
        $despesas = $relatorio->despesas_manuais();
        $lucro = $relatorio->lucro_liquido();
        $lista_despesas = Atelie_Despesas_Manuais::listar_periodo($inicio, $fim);
        ?>
        <div class="wrap atelie-faturamento">
            <h1>Faturamento <?php Atelie_Ajuda_Drawer::render('Faturamento', [
                '<strong>Receita</strong> é a soma dos pedidos pagos no período; <strong>Lucro líquido</strong> é a receita menos todos os custos abaixo.',
                'Custos considerados: taxa Mercado Pago, frete pago (sem margem, é o valor repassado à transportadora), custo de IA e despesas manuais.',
                'Pedidos sem dado de taxa Mercado Pago ainda disponível são contados à parte, não entram como R$ 0 — a tela avisa quantos ficaram de fora.',
                'Lance despesas do dia a dia do ateliê (hospedagem, domínio etc.) no formulário desta mesma tela.',
            ], '/admin/faturamento/'); ?></h1>

            <div class="atelie-card">
                <form method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <label for="atelie-fat-periodo"><strong>Período</strong></label><br>
                    <select name="periodo" id="atelie-fat-periodo" onchange="this.form.submit()">
                        <option value="este_mes" <?php selected($periodo_atual, 'este_mes'); ?>>Este mês</option>
                        <option value="mes_passado" <?php selected($periodo_atual, 'mes_passado'); ?>>Mês passado</option>
                        <option value="ultimos_30" <?php selected($periodo_atual, 'ultimos_30'); ?>>Últimos 30 dias</option>
                        <option value="personalizado" <?php selected($periodo_atual, 'personalizado'); ?>>Personalizado</option>
                    </select>
                    <span id="atelie-fat-personalizado" style="<?php echo $periodo_atual === 'personalizado' ? '' : 'display:none;'; ?>">
                        de <input type="date" name="de" value="<?php echo esc_attr($inicio->format('Y-m-d')); ?>">
                        até <input type="date" name="ate" value="<?php echo esc_attr($fim->format('Y-m-d')); ?>">
                        <button type="submit" class="button">Aplicar</button>
                    </span>
                    <p class="atelie-status-data">
                        <?php echo esc_html($inicio->format('d/m/Y')); ?> até <?php echo esc_html($fim->format('d/m/Y')); ?>
                        — <?php echo esc_html($relatorio->quantidade_pedidos()); ?> pedido(s) pago(s) no período.
                    </p>
                </form>
                <script>
                document.getElementById('atelie-fat-periodo').addEventListener('change', function () {
                    document.getElementById('atelie-fat-personalizado').style.display = this.value === 'personalizado' ? 'inline' : 'none';
                });
                </script>
            </div>

            <div class="atelie-fat-resumo">
                <div class="atelie-card atelie-fat-card">
                    <span class="atelie-fat-rotulo">Receita</span>
                    <span class="atelie-fat-valor atelie-fat-valor-positivo">R$ <?php echo esc_html(number_format($receita, 2, ',', '.')); ?></span>
                </div>
                <div class="atelie-card atelie-fat-card">
                    <span class="atelie-fat-rotulo">Custo de IA</span>
                    <span class="atelie-fat-valor">R$ <?php echo esc_html(number_format($custo_ia, 2, ',', '.')); ?></span>
                </div>
                <div class="atelie-card atelie-fat-card">
                    <span class="atelie-fat-rotulo">Taxa Mercado Pago</span>
                    <span class="atelie-fat-valor">R$ <?php echo esc_html(number_format($taxa_mp['total'], 2, ',', '.')); ?></span>
                    <?php if ($taxa_mp['pedidos_sem_dado'] > 0) : ?>
                        <span class="atelie-status-data">⚠️ <?php echo esc_html($taxa_mp['pedidos_sem_dado']); ?> pedido(s) sem essa informação — não entraram na soma (a taxa real deles é desconhecida, não é zero).</span>
                    <?php endif; ?>
                </div>
                <div class="atelie-card atelie-fat-card">
                    <span class="atelie-fat-rotulo">Frete pago</span>
                    <span class="atelie-fat-valor">R$ <?php echo esc_html(number_format($custo_frete, 2, ',', '.')); ?></span>
                </div>
                <div class="atelie-card atelie-fat-card">
                    <span class="atelie-fat-rotulo">Despesas manuais</span>
                    <span class="atelie-fat-valor">R$ <?php echo esc_html(number_format($despesas, 2, ',', '.')); ?></span>
                </div>
                <div class="atelie-card atelie-fat-card atelie-fat-card-lucro">
                    <span class="atelie-fat-rotulo">Lucro líquido</span>
                    <span class="atelie-fat-valor <?php echo $lucro >= 0 ? 'atelie-fat-valor-positivo' : 'atelie-fat-valor-negativo'; ?>">R$ <?php echo esc_html(number_format($lucro, 2, ',', '.')); ?></span>
                </div>
            </div>

            <div class="atelie-card">
                <h2>Despesas manuais</h2>
                <p class="atelie-status-data">Custos que não vêm de nenhum pedido — hospedagem, domínio, etc.</p>

                <?php if (!empty($lista_despesas)) : ?>
                    <table class="widefat striped" style="max-width:560px;">
                        <thead><tr><th>Data</th><th>Descrição</th><th>Valor</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($lista_despesas as $despesa) : ?>
                                <tr>
                                    <td><?php echo esc_html(wp_date('d/m/Y', strtotime((string) $despesa['quando']))); ?></td>
                                    <td><?php echo esc_html($despesa['descricao']); ?></td>
                                    <td>R$ <?php echo esc_html(number_format((float) $despesa['valor'], 2, ',', '.')); ?></td>
                                    <td>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                            <input type="hidden" name="action" value="atelie_remover_despesa">
                                            <input type="hidden" name="id" value="<?php echo esc_attr($despesa['id']); ?>">
                                            <?php wp_nonce_field('atelie_remover_despesa_' . $despesa['id'], 'atelie_remover_despesa_nonce'); ?>
                                            <button type="submit" class="button-link" style="color:#a8434b;" onclick="return confirm('Remover essa despesa?');">Remover</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:1.25rem;">
                    <input type="hidden" name="action" value="atelie_lancar_despesa">
                    <?php wp_nonce_field('atelie_lancar_despesa', 'atelie_lancar_despesa_nonce'); ?>

                    <p>
                        <label for="atelie-despesa-descricao">Descrição</label><br>
                        <input type="text" name="descricao" id="atelie-despesa-descricao" required style="width:100%;max-width:320px;" placeholder="Ex: Hospedagem HostGator">
                    </p>
                    <p>
                        <label for="atelie-despesa-valor">Valor (R$)</label><br>
                        <input type="text" name="valor" id="atelie-despesa-valor" required placeholder="0,00" style="width:140px;">
                    </p>
                    <p>
                        <label for="atelie-despesa-data">Data</label><br>
                        <input type="date" name="quando" id="atelie-despesa-data" value="<?php echo esc_attr(current_datetime()->format('Y-m-d')); ?>">
                    </p>
                    <p>
                        <button type="submit" class="button button-primary">Lançar despesa</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    public function lancar_despesa(): void
    {
        if (
            !current_user_can('manage_options')
            || !isset($_POST['atelie_lancar_despesa_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_lancar_despesa_nonce'])), 'atelie_lancar_despesa')
        ) {
            wp_die('Ação não permitida.');
        }

        $descricao = isset($_POST['descricao']) ? sanitize_text_field(wp_unslash($_POST['descricao'])) : '';
        $valor = isset($_POST['valor']) ? (float) str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['valor']))) : 0.0;
        $data_str = isset($_POST['quando']) ? sanitize_text_field(wp_unslash($_POST['quando'])) : '';
        $data = DateTimeImmutable::createFromFormat('Y-m-d', $data_str, current_datetime()->getTimezone());

        if ($descricao === '' || $valor <= 0 || $data === false) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
            exit;
        }

        Atelie_Despesas_Manuais::lancar($descricao, $valor, $data);

        wp_safe_redirect(add_query_arg('lancado', '1', admin_url('admin.php?page=' . self::SLUG)));
        exit;
    }

    public function remover_despesa(): void
    {
        $id = absint($_POST['id'] ?? 0);

        if (
            !current_user_can('manage_options')
            || !isset($_POST['atelie_remover_despesa_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_remover_despesa_nonce'])), 'atelie_remover_despesa_' . $id)
        ) {
            wp_die('Ação não permitida.');
        }

        Atelie_Despesas_Manuais::remover($id);

        wp_safe_redirect(add_query_arg('removido', '1', admin_url('admin.php?page=' . self::SLUG)));
        exit;
    }
}
