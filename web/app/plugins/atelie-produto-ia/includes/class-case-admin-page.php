<?php
/**
 * Tela "Novo Case" — mesma lógica do painel de produto (foto → IA sugere →
 * humano confirma → revisão de vendas se editou/fez manual), mas pro
 * portfólio: sem preço, com um campo de relato livre da artesã sobre como
 * foi o trabalho, que a IA transforma em título/descrição profissionais.
 * Ver plano "IA como copiloto de vendas", Fase C.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Case_Admin_Page
{
    private const SLUG = 'atelie-novo-case';

    public function registrar(): void
    {
        add_action('admin_menu', [$this, 'adicionar_menu']);
        add_action('admin_enqueue_scripts', [$this, 'carregar_assets']);
        add_action('admin_post_atelie_publicar_case', [$this, 'publicar_case']);
    }

    public function adicionar_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=atelie_case',
            'Novo Case',
            'Novo Case',
            'edit_atelie_cases',
            self::SLUG,
            [$this, 'renderizar']
        );
    }

    public function carregar_assets(string $hook): void
    {
        if (strpos($hook, self::SLUG) === false) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'atelie-produto-ia-admin',
            plugins_url('assets/admin.css', dirname(__DIR__) . '/atelie-produto-ia.php'),
            [],
            '0.1.0'
        );

        wp_enqueue_script(
            'atelie-produto-ia-editar-imagem',
            plugins_url('assets/editar-imagem.js', dirname(__DIR__) . '/atelie-produto-ia.php'),
            [],
            '0.1.0',
            true
        );

        wp_enqueue_script(
            'atelie-produto-ia-case',
            plugins_url('assets/case.js', dirname(__DIR__) . '/atelie-produto-ia.php'),
            ['jquery', 'atelie-produto-ia-editar-imagem'],
            '0.1.0',
            true
        );

        wp_localize_script('atelie-produto-ia-case', 'atelieCaseIA', [
            'restUrl' => esc_url_raw(rest_url('atelie/v1/sugerir-case')),
            'editarImagemUrl' => esc_url_raw(rest_url('atelie/v1/editar-imagem')),
            'nonce' => wp_create_nonce('wp_rest'),
            'iaDisponivel' => Atelie_Ai_Config::esta_disponivel(),
            'custoEdicaoImagem' => number_format(Atelie_Ai_Custo_Tracker::estimar('editar_imagem'), 4, ',', '.'),
        ]);
    }

    public function renderizar(): void
    {
        if (isset($_GET['publicado'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Case publicado! Já está visível no portfólio.</p></div>';
        }
        if (isset($_GET['erro'])) {
            echo '<div class="notice notice-error is-dismissible"><p>Não deu pra publicar — confira os campos obrigatórios (fotos e título) e tente de novo.</p></div>';
        }

        $bloqueio = null;
        if (isset($_GET['revisao_ia'])) {
            $bloqueio = Atelie_Revisao_Vendas::obter_bloqueio('case');
            Atelie_Revisao_Vendas::limpar_bloqueio('case');
        }
        $dados = $bloqueio['dados'] ?? [];
        $revisao = $bloqueio['revisao'] ?? null;

        $status = Atelie_Ai_Config::obter_status();
        if ($status['verificado_em'] === 0) {
            $status = Atelie_Ai_Config::testar_conexao();
        }
        $ia_disponivel = $status['ok'];
        $custo_estimado = Atelie_Ai_Custo_Tracker::estimar('sugerir_case');

        $titulo_valor = $revisao['titulo_sugerido'] ?? ($dados['titulo'] ?? '');
        $descricao_valor = $revisao['descricao_sugerida'] ?? ($dados['descricao'] ?? '');
        $titulo_ia_original_valor = $revisao['titulo_sugerido'] ?? '';
        $descricao_ia_original_valor = $revisao['descricao_sugerida'] ?? '';
        ?>
        <div class="wrap atelie-novo-produto">
            <h1>Novo Case <?php Atelie_Ajuda_Drawer::render('Novo Case', [
                'Um "case" é um trabalho já <strong>entregue</strong>, mostrado como vitrine no portfólio — sem preço e sem botão de comprar.',
                'Anexe as fotos do trabalho pronto, do mesmo jeito que em "Novo Produto".',
                'No campo "Conte como foi esse trabalho", escreva livremente sobre a encomenda — a IA transforma esse relato numa descrição de vitrine. É opcional, mas ajuda bastante.',
                'A mesma revisão de qualidade de "Novo Produto" vale aqui: texto editado ou manual passa pela checagem antes de publicar.',
            ], '/cases/'); ?></h1>
            <p>Vitrine de um trabalho já entregue — sem preço, sem botão de comprar.</p>

            <div id="atelie-passo-anexar" class="atelie-card">
                <h2>1. Anexar fotos do trabalho pronto</h2>
                <div id="atelie-dropzone" class="atelie-dropzone">
                    <p id="atelie-dropzone-texto">Toque para escolher as fotos do trabalho</p>
                    <div id="atelie-fotos-preview" class="atelie-fotos-preview"></div>
                </div>
                <button type="button" class="button" id="atelie-btn-escolher-fotos">Escolher fotos</button>

                <p class="atelie-receita-linha">
                    <label for="atelie-relato-texto">Conte como foi esse trabalho (opcional, mas ajuda muito a IA a escrever melhor)</label>
                </p>
                <textarea id="atelie-relato-texto" rows="4" placeholder="Ex: cliente pediu um cachecol para o filho recém-nascido, escolhi um fio hipoalergênico..."></textarea>

                <p>
                    <span class="atelie-tooltip" <?php echo $ia_disponivel ? '' : Atelie_Ai_Config::atributo_tooltip_indisponivel($status); ?>>
                        <button type="button" class="button button-primary button-hero" id="atelie-btn-sugerir" disabled>
                            ✨ Sugerir
                        </button>
                    </span>
                    <button type="button" class="button button-hero" id="atelie-btn-manual">
                        Preencher manualmente
                    </button>
                    <span id="atelie-analisando" style="display:none;">Analisando…</span>
                    <?php if ($ia_disponivel) : ?>
                        <span class="atelie-custo-estimado">~R$ <?php echo esc_html(number_format($custo_estimado, 4, ',', '.')); ?> nesta chamada</span>
                    <?php endif; ?>
                </p>
                <?php if (!$ia_disponivel) : ?>
                    <p class="atelie-status atelie-status-erro atelie-status-inline">
                        ⚠️ IA indisponível no momento — <?php echo esc_html($status['mensagem']); ?>
                        <?php if (current_user_can('manage_options')) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=atelie-config-ia')); ?>">Configurar agora</a>
                        <?php else : ?>
                            Avise o administrador do site.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($revisao !== null) : ?>
                <div class="atelie-card atelie-revisao-bloqueio">
                    <h2>⚠️ A IA encontrou um problema antes de publicar</h2>
                    <?php if (!empty($revisao['problemas'])) : ?>
                        <ul>
                            <?php foreach ($revisao['problemas'] as $problema) : ?>
                                <li><?php echo esc_html($problema); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($revisao['titulo_sugerido'] !== null || $revisao['descricao_sugerida'] !== null) : ?>
                            <p>Já preenchi os campos abaixo com uma correção sugerida — reveja e publique, ou ajuste do seu jeito.</p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p>Não deu pra confirmar se o texto está ok agora. Tente publicar de novo em instantes.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form id="atelie-form-produto" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="<?php echo $bloqueio !== null ? '' : 'display:none;'; ?>">
                <input type="hidden" name="action" value="atelie_publicar_case">
                <?php wp_nonce_field('atelie_publicar_case', 'atelie_publicar_nonce'); ?>
                <input type="hidden" name="fotos_ids" id="atelie-input-fotos-ids" value="<?php echo esc_attr($dados['fotos_ids'] ?? ''); ?>">
                <input type="hidden" name="titulo_ia_original" id="atelie-titulo-ia-original" value="<?php echo esc_attr($titulo_ia_original_valor); ?>">
                <input type="hidden" name="descricao_ia_original" id="atelie-descricao-ia-original" value="<?php echo esc_attr($descricao_ia_original_valor); ?>">

                <div class="atelie-card">
                    <h2>2. Revisar case</h2>

                    <p>
                        <label>Título <span class="atelie-badge-ia" id="atelie-badge-titulo" style="display:none;">✨ sugerido</span></label><br>
                        <input type="text" name="titulo" id="atelie-campo-titulo" required style="width:100%;max-width:480px;" value="<?php echo esc_attr($titulo_valor); ?>">
                    </p>

                    <p>
                        <label>Descrição <span class="atelie-badge-ia" id="atelie-badge-descricao" style="display:none;">✨ sugerido</span></label><br>
                        <textarea name="descricao" id="atelie-campo-descricao" rows="4" style="width:100%;max-width:480px;"><?php echo esc_textarea($descricao_valor); ?></textarea>
                    </p>

                    <p>
                        <button type="submit" class="button button-primary button-hero">Publicar</button>
                        <button type="button" class="button" id="atelie-btn-recomecar">Recomeçar</button>
                    </p>
                </div>
            </form>
        </div>
        <?php
    }

    public function publicar_case(): void
    {
        if (
            !current_user_can('edit_atelie_cases')
            || !isset($_POST['atelie_publicar_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_publicar_nonce'])), 'atelie_publicar_case')
        ) {
            wp_die('Ação não permitida.');
        }

        $titulo = isset($_POST['titulo']) ? sanitize_text_field(wp_unslash($_POST['titulo'])) : '';
        $fotos_ids = isset($_POST['fotos_ids']) ? array_filter(array_map('absint', explode(',', (string) wp_unslash($_POST['fotos_ids'])))) : [];

        if ($titulo === '' || empty($fotos_ids)) {
            wp_safe_redirect(add_query_arg('erro', '1', admin_url('admin.php?page=' . self::SLUG)));
            exit;
        }

        $descricao = isset($_POST['descricao']) ? sanitize_textarea_field(wp_unslash($_POST['descricao'])) : '';
        $titulo_ia_original = isset($_POST['titulo_ia_original']) ? sanitize_text_field(wp_unslash($_POST['titulo_ia_original'])) : '';
        $descricao_ia_original = isset($_POST['descricao_ia_original']) ? sanitize_textarea_field(wp_unslash($_POST['descricao_ia_original'])) : '';

        $dados_formulario = [
            'titulo' => $titulo,
            'descricao' => $descricao,
            'fotos_ids' => implode(',', $fotos_ids),
        ];

        if (Atelie_Revisao_Vendas::precisa_revisar($titulo, $descricao, $titulo_ia_original, $descricao_ia_original)) {
            $revisao = Atelie_Ai_Vision_Service_Factory::criar()->avaliarTexto($titulo, $descricao, 'case');
            if (!$revisao['ok']) {
                Atelie_Revisao_Vendas::bloquear_e_redirecionar(
                    'case',
                    $dados_formulario,
                    $revisao,
                    admin_url('admin.php?page=' . self::SLUG)
                );
            }
        }

        $case_id = wp_insert_post([
            'post_type' => 'atelie_case',
            'post_title' => $titulo,
            'post_content' => $descricao,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($case_id) || !$case_id) {
            wp_safe_redirect(add_query_arg('erro', '1', admin_url('admin.php?page=' . self::SLUG)));
            exit;
        }

        set_post_thumbnail($case_id, $fotos_ids[0]);
        if (count($fotos_ids) > 1) {
            update_post_meta($case_id, '_atelie_case_galeria', implode(',', array_slice($fotos_ids, 1)));
        }

        wp_safe_redirect(add_query_arg('publicado', '1', admin_url('admin.php?page=' . self::SLUG)));
        exit;
    }
}
