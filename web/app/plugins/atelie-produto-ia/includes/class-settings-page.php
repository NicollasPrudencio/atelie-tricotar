<?php
/**
 * Tela "Configurar IA" — quem administra o site cola/troca a chave da API de
 * visão por aqui, sem precisar de deploy nem mexer em .env. Só quem tem
 * manage_options (Administrador) acessa, já que é uma chave de API.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Settings_Page
{
    private const SLUG = 'atelie-config-ia';

    public function registrar(): void
    {
        add_action('admin_menu', [$this, 'adicionar_menu']);
        add_action('admin_post_atelie_salvar_config_ia', [$this, 'salvar']);
        add_action('admin_post_atelie_testar_conexao_ia', [$this, 'testar']);
        add_action('admin_post_atelie_salvar_custos_ia', [$this, 'salvar_custos']);
    }

    public function adicionar_menu(): void
    {
        add_menu_page(
            'Configurar IA',
            'IA',
            'manage_options',
            self::SLUG,
            [$this, 'renderizar'],
            'dashicons-star-filled',
            58
        );
    }

    public function renderizar(): void
    {
        $status = Atelie_Ai_Config::obter_status();
        $provedor_atual = Atelie_Ai_Config::obter_provedor();
        $chave_mascarada = Atelie_Ai_Config::chave_mascarada();
        ?>
        <div class="wrap atelie-novo-produto">
            <h1>Configurar IA</h1>

            <?php if (isset($_GET['salvo'])) : ?>
                <div class="notice notice-success is-dismissible"><p>Configuração salva.</p></div>
            <?php endif; ?>

            <div class="atelie-card">
                <h2>Status da conexão</h2>
                <?php if (Atelie_Ai_Config::em_modo_mock()) : ?>
                    <p class="atelie-status atelie-status-ok">🧪 Modo simulado ativo (AI_MOCK_MODE) — a IA sempre responde com dados de teste, nenhuma chamada real é feita.</p>
                <?php elseif ($status['ok']) : ?>
                    <p class="atelie-status atelie-status-ok">✅ Conectado — <?php echo esc_html($status['mensagem']); ?></p>
                <?php else : ?>
                    <p class="atelie-status atelie-status-erro">⚠️ Indisponível — <?php echo esc_html($status['mensagem']); ?></p>
                <?php endif; ?>

                <?php if ($status['verificado_em'] > 0) : ?>
                    <p class="atelie-status-data">
                        Última verificação: <?php echo esc_html(wp_date('d/m/Y H:i', $status['verificado_em'])); ?>
                        (a verificação também roda automaticamente uma vez por dia)
                    </p>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="atelie_testar_conexao_ia">
                    <?php wp_nonce_field('atelie_testar_conexao_ia', 'atelie_testar_nonce'); ?>
                    <button type="submit" class="button">Testar conexão agora</button>
                </form>
            </div>

            <div class="atelie-card">
                <h2>Provedor e chave de API</h2>
                <p>Hoje só o Google Gemini (Google AI Studio) é suportado. A chave é gerada gratuitamente em <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">aistudio.google.com/app/apikey</a>.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="atelie_salvar_config_ia">
                    <?php wp_nonce_field('atelie_salvar_config_ia', 'atelie_config_ia_nonce'); ?>

                    <p>
                        <label for="atelie-config-provedor">Provedor</label><br>
                        <select name="provedor" id="atelie-config-provedor">
                            <option value="gemini" <?php selected($provedor_atual, 'gemini'); ?>>Google Gemini</option>
                        </select>
                    </p>

                    <p>
                        <label for="atelie-config-chave">Chave de API</label><br>
                        <input type="text" name="chave" id="atelie-config-chave"
                               value="<?php echo esc_attr($chave_mascarada); ?>"
                               autocomplete="off" style="width:100%;max-width:480px;">
                        <?php if ($chave_mascarada !== '') : ?>
                            <br><span class="description">Chave já configurada (mostrando só os últimos 4 dígitos). Apague o campo e cole uma nova pra trocar.</span>
                        <?php endif; ?>
                    </p>

                    <p>
                        <button type="submit" class="button button-primary">Salvar e testar</button>
                    </p>
                </form>
            </div>

            <?php $this->renderizar_secao_custos(); ?>
        </div>
        <?php
    }

    private function renderizar_secao_custos(): void
    {
        $gasto_mes = Atelie_Ai_Custo_Tracker::gasto_mes_atual();
        $limite = Atelie_Ai_Custo_Tracker::limite_aviso();
        $precos = Atelie_Ai_Custo_Tracker::tabela_precos();
        $custo_imagem = Atelie_Ai_Custo_Tracker::custo_por_imagem();
        $historico = Atelie_Ai_Custo_Tracker::historico_recente(10);
        $passou_limite = Atelie_Ai_Custo_Tracker::deve_avisar();
        ?>
        <div class="atelie-card">
            <h2>Custos da IA</h2>
            <p class="atelie-status <?php echo $passou_limite ? 'atelie-status-erro' : 'atelie-status-ok'; ?>">
                <?php echo $passou_limite ? '⚠️' : '💰'; ?>
                Gasto estimado neste mês: <strong>R$ <?php echo esc_html(number_format($gasto_mes, 2, ',', '.')); ?></strong>
                <?php if ($limite > 0) : ?>
                    de R$ <?php echo esc_html(number_format($limite, 2, ',', '.')); ?> avisados
                <?php endif; ?>
            </p>
            <p class="atelie-status-data">Valor aproximado, calculado pelos tokens de cada chamada — não é fatura oficial do provedor.</p>

            <?php if (!empty($historico)) : ?>
                <table class="widefat striped" style="max-width:560px;">
                    <thead><tr><th>Quando</th><th>Operação</th><th>Custo</th></tr></thead>
                    <tbody>
                        <?php foreach ($historico as $linha) : ?>
                            <tr>
                                <td><?php echo esc_html(wp_date('d/m/Y H:i', strtotime((string) $linha['quando']))); ?></td>
                                <td><?php echo esc_html($linha['operacao']); ?></td>
                                <td>R$ <?php echo esc_html(number_format((float) $linha['custo_estimado'], 4, ',', '.')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:1.25rem;">
                <input type="hidden" name="action" value="atelie_salvar_custos_ia">
                <?php wp_nonce_field('atelie_salvar_custos_ia', 'atelie_custos_nonce'); ?>

                <p>
                    <label for="atelie-custo-limite">Avisar quando o gasto do mês passar de (R$)</label><br>
                    <input type="text" name="limite_aviso" id="atelie-custo-limite" value="<?php echo esc_attr(number_format($limite, 2, ',', '.')); ?>" style="width:120px;">
                </p>

                <p>
                    <label for="atelie-custo-entrada">Preço por 1 milhão de tokens de entrada (R$, aproximado)</label><br>
                    <input type="text" name="preco_entrada" id="atelie-custo-entrada" value="<?php echo esc_attr(number_format($precos['entrada_por_1m'], 2, ',', '.')); ?>" style="width:120px;">
                </p>

                <p>
                    <label for="atelie-custo-saida">Preço por 1 milhão de tokens de saída (R$, aproximado)</label><br>
                    <input type="text" name="preco_saida" id="atelie-custo-saida" value="<?php echo esc_attr(number_format($precos['saida_por_1m'], 2, ',', '.')); ?>" style="width:120px;">
                </p>
                <p class="description">Confira o preço atual em <a href="https://ai.google.dev/gemini-api/docs/pricing" target="_blank" rel="noopener noreferrer">ai.google.dev/gemini-api/docs/pricing</a> e ajuste aqui — a estimativa só é boa se o preço estiver atualizado.</p>

                <p>
                    <label for="atelie-custo-imagem">Preço por imagem editada (R$, aproximado)</label><br>
                    <input type="text" name="custo_imagem" id="atelie-custo-imagem" value="<?php echo esc_attr(number_format($custo_imagem, 2, ',', '.')); ?>" style="width:120px;">
                    <br><span class="description">Edição/geração de imagem é cobrada por unidade, não por token — e exige faturamento ativo na conta do Google (não está disponível no nível gratuito).</span>
                </p>

                <p>
                    <button type="submit" class="button button-primary">Salvar</button>
                </p>
            </form>
        </div>
        <?php
    }

    public function salvar(): void
    {
        if (
            !current_user_can('manage_options')
            || !isset($_POST['atelie_config_ia_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_config_ia_nonce'])), 'atelie_salvar_config_ia')
        ) {
            wp_die('Ação não permitida.');
        }

        $provedor = isset($_POST['provedor']) ? sanitize_text_field(wp_unslash($_POST['provedor'])) : 'gemini';
        $chave = isset($_POST['chave']) ? trim(sanitize_text_field(wp_unslash($_POST['chave']))) : '';

        Atelie_Ai_Config::salvar($provedor, $chave);
        Atelie_Ai_Config::testar_conexao();

        wp_safe_redirect(add_query_arg('salvo', '1', admin_url('admin.php?page=' . self::SLUG)));
        exit;
    }

    public function testar(): void
    {
        if (
            !current_user_can('manage_options')
            || !isset($_POST['atelie_testar_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_testar_nonce'])), 'atelie_testar_conexao_ia')
        ) {
            wp_die('Ação não permitida.');
        }

        Atelie_Ai_Config::testar_conexao();

        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
        exit;
    }

    public function salvar_custos(): void
    {
        if (
            !current_user_can('manage_options')
            || !isset($_POST['atelie_custos_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_custos_nonce'])), 'atelie_salvar_custos_ia')
        ) {
            wp_die('Ação não permitida.');
        }

        $limite = isset($_POST['limite_aviso']) ? (float) str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['limite_aviso']))) : 0.0;
        $preco_entrada = isset($_POST['preco_entrada']) ? (float) str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['preco_entrada']))) : 0.0;
        $preco_saida = isset($_POST['preco_saida']) ? (float) str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['preco_saida']))) : 0.0;
        $custo_imagem = isset($_POST['custo_imagem']) ? (float) str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['custo_imagem']))) : 0.0;

        Atelie_Ai_Custo_Tracker::salvar_limite_aviso(max(0.0, $limite));
        Atelie_Ai_Custo_Tracker::salvar_tabela_precos(max(0.0, $preco_entrada), max(0.0, $preco_saida));
        Atelie_Ai_Custo_Tracker::salvar_custo_por_imagem(max(0.0, $custo_imagem));

        wp_safe_redirect(add_query_arg('salvo', '1', admin_url('admin.php?page=' . self::SLUG)));
        exit;
    }
}
