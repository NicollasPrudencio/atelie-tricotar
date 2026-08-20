<?php
/**
 * Tela "Pendências" — status por item de uma criacao em massa (hoje, so
 * originada pela importacao do Google Drive; upload solto sem organizacao
 * foi removido, decisao explicita do usuario). Com `?lote=X` (link direto
 * apos importar), mostra so aquele lote; sem parametro (item de menu),
 * mostra tudo que ainda nao foi revisado, de qualquer lote — pra nada ficar
 * esquecido so por ninguem ter o link de um lote especifico. Tambem "cutuca"
 * itens atrasados a cada visita, ja que quem abre essa tela claramente esta
 * esperando por eles (ver Atelie_Lote_Controller::cutucar_pendentes). Ver
 * plano, secao "Criacao de produtos em massa via IA".
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Lote_Admin_Pages
{
    public function registrar(): void
    {
        add_action('admin_menu', [$this, 'adicionar_menus']);
        add_action('admin_enqueue_scripts', [$this, 'carregar_assets']);
        add_action('admin_post_atelie_marcar_revisado', [$this, 'marcar_revisado']);
        add_action('admin_post_atelie_reprocessar_item', [$this, 'reprocessar_item']);
        add_action('add_meta_boxes', [$this, 'meta_box_revisado']);
    }

    public function adicionar_menus(): void
    {
        add_submenu_page(
            'edit.php?post_type=product',
            'Pendências',
            'Pendências',
            'edit_products',
            'atelie-revisar-lote',
            [$this, 'renderizar_revisao']
        );
    }

    public function carregar_assets(string $hook): void
    {
        if (strpos($hook, 'atelie-revisar-lote') === false) {
            return;
        }
        wp_enqueue_style(
            'atelie-produto-ia-admin',
            plugins_url('assets/admin.css', dirname(__DIR__) . '/atelie-produto-ia.php'),
            [],
            '0.2.0'
        );
    }

    public function renderizar_revisao(): void
    {
        $lote_id = isset($_GET['lote']) ? sanitize_text_field(wp_unslash($_GET['lote'])) : '';

        // Item já revisado sai da lista em qualquer uma das duas visões — não tem
        // motivo pra continuar ocupando espaço numa tela de pendência depois de
        // resolvido, mesmo entrando direto pelo link de um lote específico.
        $meta_query = [
            ['key' => '_atelie_lote_status', 'value' => 'revisado', 'compare' => '!='],
        ];

        if ($lote_id !== '') {
            $meta_query[] = ['key' => '_atelie_lote_id', 'value' => $lote_id, 'compare' => '='];
            $itens = get_posts([
                'post_type' => 'product',
                'post_status' => ['draft', 'publish'],
                'numberposts' => -1,
                'meta_query' => $meta_query,
                'orderby' => 'ID',
                'order' => 'ASC',
            ]);
            $titulo = 'Revisar lote';
        } else {
            // Sem lote especifico (item de menu): tudo que ainda nao foi revisado,
            // de qualquer lote — rede de seguranca pra nada ficar esquecido.
            $itens = get_posts([
                'post_type' => 'product',
                'post_status' => ['draft', 'publish'],
                'numberposts' => -1,
                'meta_query' => $meta_query,
                'orderby' => 'ID',
                'order' => 'DESC',
            ]);
            $titulo = 'Pendências';
        }

        (new Atelie_Lote_Controller())->cutucar_pendentes(wp_list_pluck($itens, 'ID'));

        $rotulos = [
            'processando' => 'Processando',
            'pronto' => 'Pronto para revisão',
            'revisado' => 'Revisado',
            'erro' => 'Erro',
        ];
        ?>
        <div class="wrap atelie-novo-produto">
            <h1><?php echo esc_html($titulo); ?> <?php Atelie_Ajuda_Drawer::render('Pendências', [
                'Cada quadradinho é um produto candidato importado do Google Drive, com um status: <strong>Processando</strong>, <strong>Pronto para revisão</strong>, <strong>Erro</strong> ou <strong>Revisado</strong>.',
                'Não precisa esperar tudo terminar — pode ir revisando os que já ficaram prontos enquanto o resto processa em segundo plano.',
                'Se algo parecer travado em "Processando", só de abrir esta tela o painel já verifica e força o processamento de itens atrasados. Clique em "Atualizar status" depois de alguns instantes.',
                'Sem um lote específico (como agora, pelo menu), esta tela mostra tudo que está pendente de qualquer importação.',
            ], '/pendencias/'); ?></h1>
            <p><button type="button" class="button" onclick="location.reload();">Atualizar status</button></p>

            <?php if (empty($itens)) : ?>
                <p>Nada pendente no momento — tudo revisado. 🎉</p>
            <?php endif; ?>

            <div class="atelie-lote-grid">
                <?php foreach ($itens as $item) :
                    // Recarrega o status: cutucar_pendentes() pode ter mudado ele agora mesmo.
                    $status = get_post_meta($item->ID, '_atelie_lote_status', true) ?: 'processando';
                    $rotulo = $rotulos[$status] ?? $status;
                    ?>
                    <div class="atelie-lote-card">
                        <?php echo get_the_post_thumbnail($item->ID, 'thumbnail'); ?>
                        <strong><?php echo esc_html(get_the_title($item->ID)); ?></strong>
                        <span class="atelie-chip atelie-chip-<?php echo esc_attr($status); ?>"><?php echo esc_html($rotulo); ?></span>

                        <?php if ($status === 'pronto' || $status === 'revisado') : ?>
                            <p><a class="button" href="<?php echo esc_url(get_edit_post_link($item->ID)); ?>">Revisar</a></p>
                        <?php elseif ($status === 'erro') : ?>
                            <p>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="atelie_reprocessar_item">
                                    <input type="hidden" name="produto_id" value="<?php echo esc_attr($item->ID); ?>">
                                    <?php wp_nonce_field('atelie_reprocessar_item_' . $item->ID, 'atelie_reprocessar_nonce'); ?>
                                    <button type="submit" class="button">Tentar novamente</button>
                                </form>
                            </p>
                        <?php else : ?>
                            <p><em>Aguardando…</em></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function marcar_revisado(): void
    {
        $produto_id = absint($_POST['produto_id'] ?? 0);
        if (
            !current_user_can('edit_products')
            || !isset($_POST['atelie_revisado_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_revisado_nonce'])), 'atelie_marcar_revisado_' . $produto_id)
        ) {
            wp_die('Ação não permitida.');
        }

        update_post_meta($produto_id, '_atelie_lote_status', 'revisado');
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    public function reprocessar_item(): void
    {
        $produto_id = absint($_POST['produto_id'] ?? 0);
        if (
            !current_user_can('edit_products')
            || !isset($_POST['atelie_reprocessar_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_reprocessar_nonce'])), 'atelie_reprocessar_item_' . $produto_id)
        ) {
            wp_die('Ação não permitida.');
        }

        (new Atelie_Lote_Controller())->reprocessar_item($produto_id);
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    public function meta_box_revisado(): void
    {
        add_meta_box(
            'atelie_lote_revisado',
            'Criação em massa',
            function (WP_Post $post): void {
                $lote_id = get_post_meta($post->ID, '_atelie_lote_id', true);
                if (!$lote_id) {
                    return;
                }
                $status = get_post_meta($post->ID, '_atelie_lote_status', true);
                $material = get_post_meta($post->ID, '_atelie_material_tecnica_sugerido', true);
                ?>
                <p>Status: <strong><?php echo esc_html($status); ?></strong></p>
                <?php if ($material) : ?>
                    <p>Material/técnica sugerido: <?php echo esc_html($material); ?></p>
                <?php endif; ?>
                <?php if ($status !== 'revisado') : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="atelie_marcar_revisado">
                        <input type="hidden" name="produto_id" value="<?php echo esc_attr($post->ID); ?>">
                        <?php wp_nonce_field('atelie_marcar_revisado_' . $post->ID, 'atelie_revisado_nonce'); ?>
                        <button type="submit" class="button">Marcar como revisado</button>
                    </form>
                <?php else : ?>
                    <p>✓ Já revisado</p>
                <?php endif; ?>
                <?php
            },
            'product',
            'side'
        );
    }
}
