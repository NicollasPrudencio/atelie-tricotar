<?php
/**
 * Tela "Novo Produto" — o painel simplificado em si. Ver plano, secao
 * "Plugin custom Criar Produto com IA", passo a passo do fluxo.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Admin_Page
{
    private const SLUG = 'atelie-novo-produto';
    public const LIMITE_FOTOS = 10;

    public function registrar(): void
    {
        add_action('admin_menu', [$this, 'adicionar_menu']);
        add_action('admin_enqueue_scripts', [$this, 'carregar_assets']);
        add_action('admin_post_atelie_publicar_produto', [$this, 'publicar_produto']);
    }

    public function adicionar_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=product',
            'Novo Produto',
            'Novo Produto',
            'edit_products',
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
            '0.2.0'
        );

        wp_enqueue_script(
            'atelie-produto-ia-editar-imagem',
            plugins_url('assets/editar-imagem.js', dirname(__DIR__) . '/atelie-produto-ia.php'),
            [],
            '0.1.0',
            true
        );

        wp_enqueue_script(
            'atelie-produto-ia-admin',
            plugins_url('assets/admin.js', dirname(__DIR__) . '/atelie-produto-ia.php'),
            ['jquery', 'atelie-produto-ia-editar-imagem'],
            '0.2.1',
            true
        );

        wp_enqueue_script(
            'atelie-produto-ia-drive-picker',
            plugins_url('assets/drive-picker.js', dirname(__DIR__) . '/atelie-produto-ia.php'),
            ['atelie-produto-ia-admin'],
            '0.2.1',
            true
        );

        wp_localize_script('atelie-produto-ia-admin', 'atelieProdutoIA', [
            'restUrl' => esc_url_raw(rest_url('atelie/v1/analisar-fotos')),
            'editarImagemUrl' => esc_url_raw(rest_url('atelie/v1/editar-imagem')),
            'driveListarUrl' => esc_url_raw(rest_url('atelie/v1/drive-listar')),
            'driveBaixarFotosUrl' => esc_url_raw(rest_url('atelie/v1/drive-baixar-fotos')),
            'driveConectado' => Atelie_Drive_Config::conectado(),
            'nonce' => wp_create_nonce('wp_rest'),
            'iaDisponivel' => Atelie_Ai_Config::esta_disponivel(),
            'custoEdicaoImagem' => number_format(Atelie_Ai_Custo_Tracker::estimar('editar_imagem'), 4, ',', '.'),
        ]);
    }

    public function renderizar(): void
    {
        if (isset($_GET['publicado'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Produto publicado! Já está visível no site.</p></div>';
        }
        if (isset($_GET['erro']) && $_GET['erro'] === 'limite-fotos') {
            echo '<div class="notice notice-error is-dismissible"><p>Máximo de 10 fotos por produto — remova algumas e tente de novo.</p></div>';
        } elseif (isset($_GET['erro'])) {
            echo '<div class="notice notice-error is-dismissible"><p>Não deu pra publicar — confira os campos obrigatórios (fotos, título e preço) e tente de novo.</p></div>';
        }
        if (isset($_GET['conectado'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Google Drive conectado.</p></div>';
        }
        if (isset($_GET['desconectado'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Google Drive desconectado.</p></div>';
        }
        if (isset($_GET['drive_erro'])) {
            echo '<div class="notice notice-error"><p>' . esc_html(Atelie_Drive_Admin_Page::mensagem_erro(sanitize_key(wp_unslash($_GET['drive_erro'])))) . '</p></div>';
        }

        $bloqueio = null;
        if (isset($_GET['revisao_ia'])) {
            $bloqueio = Atelie_Revisao_Vendas::obter_bloqueio('produto');
            Atelie_Revisao_Vendas::limpar_bloqueio('produto');
        }
        $dados = $bloqueio['dados'] ?? [];
        $revisao = $bloqueio['revisao'] ?? null;

        $status = Atelie_Ai_Config::obter_status();
        if ($status['verificado_em'] === 0) {
            // Primeira vez que a tela roda desde a instalação/reset — testa na hora
            // em vez de esperar o cron diário, pra não mostrar "indisponível" à toa.
            $status = Atelie_Ai_Config::testar_conexao();
        }
        $ia_disponivel = $status['ok'];
        $custo_estimado_sugestao = Atelie_Ai_Custo_Tracker::estimar('analisar');
        $drive_conectado = Atelie_Drive_Config::conectado();

        // Quando a revisão bloqueou a publicação: se a IA deu uma correção, já pré-preenche
        // com ela (vira o novo "baseline confiável" — se publicar sem mexer, não revisa de
        // novo); senão, mantém o que a pessoa tinha digitado, pra não perder o trabalho.
        $titulo_valor = $revisao['titulo_sugerido'] ?? ($dados['titulo'] ?? '');
        $descricao_valor = $revisao['descricao_sugerida'] ?? ($dados['descricao'] ?? '');
        $titulo_ia_original_valor = $revisao['titulo_sugerido'] ?? '';
        $descricao_ia_original_valor = $revisao['descricao_sugerida'] ?? '';
        ?>
        <div class="wrap atelie-novo-produto">
            <h1>Novo Produto <?php Atelie_Ajuda_Drawer::render('Novo Produto', [
                'Anexe até <strong>10 fotos</strong> do mesmo produto — ângulos ou momentos diferentes da mesma peça.',
                '<strong>✨ Sugerir</strong> preenche tudo com IA a partir das fotos (e da receita, se você anexar); <strong>Preencher manualmente</strong> deixa o formulário em branco.',
                'Se você editar a sugestão da IA ou escrever tudo manualmente, o texto passa por uma revisão de qualidade antes de publicar — não dá pra pular essa checagem.',
                'Preço, disponibilidade, peso e dimensões são sempre preenchidos por você. Deixar peso/dimensões em branco faz o frete ser calculado com um tamanho padrão genérico, não o real.',
                'Pra cadastrar vários produtos de uma vez, use "Escolher do Google Drive" — cada pasta selecionada vira um produto.',
            ], '/novo-produto/'); ?></h1>

            <div id="atelie-passo-anexar" class="atelie-card">
                <h2>1. Anexar fotos</h2>
                <div id="atelie-dropzone" class="atelie-dropzone">
                    <p id="atelie-dropzone-texto">Toque para escolher as fotos do produto</p>
                    <div id="atelie-fotos-preview" class="atelie-fotos-preview"></div>
                </div>
                <p class="atelie-lote-origem-fotos">
                    <button type="button" class="button" id="atelie-btn-escolher-fotos">Escolher fotos</button>
                    <span class="atelie-dica-limite">(até 10 fotos)</span>

                    <?php if ($drive_conectado) : ?>
                        <span class="atelie-lote-drive-import">
                            ou, pra criar vários produtos de uma vez organizados em pastas,
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="atelie-lote-drive-import-form">
                                <input type="hidden" name="action" value="atelie_drive_importar">
                                <?php wp_nonce_field('atelie_drive_importar', 'atelie_drive_importar_nonce'); ?>
                                <input type="hidden" name="pastas_ids" id="atelie-drive-pastas-ids">
                                <input type="hidden" name="fotos_soltas_ids" id="atelie-drive-fotos-soltas-ids">
                                <input type="text" name="pasta" id="atelie-drive-pasta-input"
                                       placeholder="colar link, ou escolher pasta/fotos →"
                                       aria-label="Link da pasta do Google Drive">
                                <button type="button" class="button" id="atelie-btn-escolher-pasta-drive">Escolher pasta ou fotos</button>
                                <button type="submit" class="button" id="atelie-btn-importar-drive" disabled>Importar</button>
                            </form>
                            <?php if (current_user_can('manage_options')) : ?>
                                (conectado como <?php echo esc_html(Atelie_Drive_Config::conta_email()); ?> —
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="atelie-lote-drive-desconectar-form" onsubmit="return confirm('Desconectar o Google Drive? Você vai precisar autorizar de novo pra importar depois.');">
                                    <input type="hidden" name="action" value="atelie_drive_desconectar">
                                    <?php wp_nonce_field('atelie_drive_desconectar', 'atelie_drive_desconectar_nonce'); ?>
                                    <button type="submit" class="button-link">desconectar</button>
                                </form>)
                            <?php endif; ?>
                        </span>
                    <?php elseif (current_user_can('manage_options')) : ?>
                        <span class="atelie-lote-drive-import">
                            ou, pra criar vários produtos de uma vez organizados em pastas,
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="atelie_drive_conectar">
                                <?php wp_nonce_field('atelie_drive_conectar', 'atelie_drive_conectar_nonce'); ?>
                                <button type="submit" class="button">Conectar Google Drive</button>
                            </form>
                        </span>
                    <?php endif; ?>
                </p>

                <p class="atelie-receita-linha">
                    <label for="atelie-receita-texto">Tem a receita/padrão dessa peça? (opcional — cole o texto ou anexe uma foto)</label>
                </p>
                <textarea id="atelie-receita-texto" rows="3" placeholder="Cole aqui o texto da receita, se tiver..."></textarea>
                <button type="button" class="button" id="atelie-btn-escolher-receita-imagem">Ou anexar foto da receita</button>
                <span id="atelie-receita-imagem-nome"></span>

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
                        <span class="atelie-custo-estimado">~R$ <?php echo esc_html(number_format($custo_estimado_sugestao, 4, ',', '.')); ?> nesta chamada</span>
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
                <input type="hidden" name="action" value="atelie_publicar_produto">
                <?php wp_nonce_field('atelie_publicar_produto', 'atelie_publicar_nonce'); ?>
                <input type="hidden" name="fotos_ids" id="atelie-input-fotos-ids" value="<?php echo esc_attr($dados['fotos_ids'] ?? ''); ?>">
                <input type="hidden" name="titulo_ia_original" id="atelie-titulo-ia-original" value="<?php echo esc_attr($titulo_ia_original_valor); ?>">
                <input type="hidden" name="descricao_ia_original" id="atelie-descricao-ia-original" value="<?php echo esc_attr($descricao_ia_original_valor); ?>">

                <div class="atelie-card">
                    <h2>2. Revisar produto</h2>

                    <p>
                        <label>Título <span class="atelie-badge-ia" id="atelie-badge-titulo" style="display:none;">✨ sugerido</span></label><br>
                        <input type="text" name="titulo" id="atelie-campo-titulo" required style="width:100%;max-width:480px;" value="<?php echo esc_attr($titulo_valor); ?>">
                    </p>

                    <p>
                        <label>Descrição <span class="atelie-badge-ia" id="atelie-badge-descricao" style="display:none;">✨ sugerido</span></label><br>
                        <textarea name="descricao" id="atelie-campo-descricao" rows="4" style="width:100%;max-width:480px;"><?php echo esc_textarea($descricao_valor); ?></textarea>
                    </p>

                    <p>
                        <label>Categoria <span class="atelie-badge-ia" id="atelie-badge-categoria" style="display:none;">✨ sugerido</span></label><br>
                        <input type="text" name="categoria" id="atelie-campo-categoria" style="width:100%;max-width:320px;" value="<?php echo esc_attr($dados['categoria'] ?? ''); ?>">
                    </p>

                    <p id="atelie-campo-material-linha" style="display:none;">
                        <label>Material/técnica (da receita) <span class="atelie-badge-ia">✨ sugerido</span></label><br>
                        <input type="text" name="material_tecnica" id="atelie-campo-material" style="width:100%;max-width:480px;">
                    </p>

                    <hr>
                    <p class="atelie-secao-manual">Sempre preenchido por você</p>

                    <p>
                        <label>Preço (R$)</label><br>
                        <input type="text" name="preco" id="atelie-campo-preco" required placeholder="0,00" style="width:140px;" value="<?php echo esc_attr($dados['preco'] ?? ''); ?>">
                    </p>

                    <p>
                        <label>Disponibilidade</label><br>
                        <select name="disponibilidade" id="atelie-campo-disponibilidade">
                            <option value="sob_encomenda" <?php selected(($dados['disponibilidade'] ?? 'sob_encomenda'), 'sob_encomenda'); ?>>Sob encomenda</option>
                            <option value="pronta_entrega" <?php selected(($dados['disponibilidade'] ?? ''), 'pronta_entrega'); ?>>Pronta entrega</option>
                        </select>
                    </p>

                    <p id="atelie-campo-prazo-linha">
                        <label>Prazo de produção (dias)</label><br>
                        <input type="number" name="prazo_producao" id="atelie-campo-prazo" min="0" step="1" placeholder="7" style="width:100px;" value="<?php echo esc_attr($dados['prazo_producao'] ?? ''); ?>">
                    </p>

                    <p>
                        <label>Peso (kg)</label><br>
                        <input type="text" name="peso" placeholder="0,15" style="width:100px;" value="<?php echo esc_attr($dados['peso'] ?? ''); ?>">
                        &nbsp;&nbsp;
                        <label>Dimensões (cm) — comp. x larg. x alt.</label><br>
                        <input type="text" name="comprimento" placeholder="15" style="width:70px;" value="<?php echo esc_attr($dados['comprimento'] ?? ''); ?>">
                        x
                        <input type="text" name="largura" placeholder="15" style="width:70px;" value="<?php echo esc_attr($dados['largura'] ?? ''); ?>">
                        x
                        <input type="text" name="altura" placeholder="10" style="width:70px;" value="<?php echo esc_attr($dados['altura'] ?? ''); ?>">
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

    public function publicar_produto(): void
    {
        if (
            !current_user_can('edit_products')
            || !isset($_POST['atelie_publicar_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_publicar_nonce'])), 'atelie_publicar_produto')
        ) {
            wp_die('Ação não permitida.');
        }

        $titulo = isset($_POST['titulo']) ? sanitize_text_field(wp_unslash($_POST['titulo'])) : '';
        $preco = isset($_POST['preco']) ? str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['preco']))) : '';
        $fotos_ids = isset($_POST['fotos_ids']) ? array_filter(array_map('absint', explode(',', (string) wp_unslash($_POST['fotos_ids'])))) : [];

        if ($titulo === '' || !is_numeric($preco) || empty($fotos_ids)) {
            wp_safe_redirect(add_query_arg('erro', '1', admin_url('admin.php?page=atelie-novo-produto')));
            exit;
        }

        if (count($fotos_ids) > self::LIMITE_FOTOS) {
            wp_safe_redirect(add_query_arg('erro', 'limite-fotos', admin_url('admin.php?page=atelie-novo-produto')));
            exit;
        }

        $descricao = isset($_POST['descricao']) ? sanitize_textarea_field(wp_unslash($_POST['descricao'])) : '';
        $categoria_nome = isset($_POST['categoria']) ? sanitize_text_field(wp_unslash($_POST['categoria'])) : '';
        $disponibilidade = isset($_POST['disponibilidade']) && $_POST['disponibilidade'] === 'pronta_entrega' ? 'pronta_entrega' : 'sob_encomenda';
        $prazo_producao = absint($_POST['prazo_producao'] ?? 0);
        $peso = isset($_POST['peso']) ? str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['peso']))) : '';
        $comprimento = isset($_POST['comprimento']) ? sanitize_text_field(wp_unslash($_POST['comprimento'])) : '';
        $largura = isset($_POST['largura']) ? sanitize_text_field(wp_unslash($_POST['largura'])) : '';
        $altura = isset($_POST['altura']) ? sanitize_text_field(wp_unslash($_POST['altura'])) : '';
        $titulo_ia_original = isset($_POST['titulo_ia_original']) ? sanitize_text_field(wp_unslash($_POST['titulo_ia_original'])) : '';
        $descricao_ia_original = isset($_POST['descricao_ia_original']) ? sanitize_textarea_field(wp_unslash($_POST['descricao_ia_original'])) : '';

        $dados_formulario = [
            'titulo' => $titulo,
            'descricao' => $descricao,
            'categoria' => $categoria_nome,
            'preco' => $preco,
            'disponibilidade' => $disponibilidade,
            'prazo_producao' => $prazo_producao,
            'peso' => $peso,
            'comprimento' => $comprimento,
            'largura' => $largura,
            'altura' => $altura,
            'fotos_ids' => implode(',', $fotos_ids),
        ];

        /**
         * IA como responsável pela qualidade do texto: se não usou sugestão da IA
         * (sem baseline) ou editou o que ela sugeriu, revisa antes de publicar.
         * Decisão do usuário: bloqueia até corrigir, sem opção de "publicar mesmo
         * assim" — inclusive se a própria revisão falhar (ver plano, seção "Revisor
         * de vendas").
         */
        if (Atelie_Revisao_Vendas::precisa_revisar($titulo, $descricao, $titulo_ia_original, $descricao_ia_original)) {
            $revisao = Atelie_Ai_Vision_Service_Factory::criar()->avaliarTexto($titulo, $descricao, 'produto');
            if (!$revisao['ok']) {
                Atelie_Revisao_Vendas::bloquear_e_redirecionar(
                    'produto',
                    $dados_formulario,
                    $revisao,
                    admin_url('admin.php?page=atelie-novo-produto')
                );
            }
        }

        $produto_id = wp_insert_post([
            'post_type' => 'product',
            'post_title' => $titulo,
            'post_content' => $descricao,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($produto_id) || !$produto_id) {
            wp_safe_redirect(add_query_arg('erro', '1', admin_url('admin.php?page=atelie-novo-produto')));
            exit;
        }

        wp_set_object_terms($produto_id, 'simple', 'product_type');

        if ($categoria_nome !== '') {
            $termo = term_exists($categoria_nome, 'product_cat');
            if (!$termo) {
                $termo = wp_insert_term($categoria_nome, 'product_cat');
            }
            if (!is_wp_error($termo)) {
                wp_set_object_terms($produto_id, (int) $termo['term_id'], 'product_cat');
            }
        }

        update_post_meta($produto_id, '_regular_price', $preco);
        update_post_meta($produto_id, '_price', $preco);
        update_post_meta($produto_id, '_visibility', 'visible');
        update_post_meta($produto_id, '_stock_status', 'instock');
        update_post_meta($produto_id, '_manage_stock', 'no');
        update_post_meta($produto_id, '_atelie_disponibilidade', $disponibilidade);
        update_post_meta($produto_id, '_atelie_prazo_producao', $prazo_producao);
        if ($peso !== '') {
            update_post_meta($produto_id, '_weight', $peso);
        }
        if ($comprimento !== '') {
            update_post_meta($produto_id, '_length', $comprimento);
        }
        if ($largura !== '') {
            update_post_meta($produto_id, '_width', $largura);
        }
        if ($altura !== '') {
            update_post_meta($produto_id, '_height', $altura);
        }

        set_post_thumbnail($produto_id, $fotos_ids[0]);
        if (count($fotos_ids) > 1) {
            update_post_meta($produto_id, '_product_image_gallery', implode(',', array_slice($fotos_ids, 1)));
        }

        wp_safe_redirect(add_query_arg('publicado', '1', admin_url('admin.php?page=atelie-novo-produto')));
        exit;
    }
}
