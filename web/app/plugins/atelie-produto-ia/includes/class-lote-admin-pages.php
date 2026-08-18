<?php
/**
 * Telas de "Criar em massa" (upload) e "Revisar lote" (status por item).
 * Ver plano, secao "Criacao de produtos em massa via IA".
 */

if (!defined('ABSPATH')) {
    exit;
}

use function Env\env;

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
            'atelie-novo-produto',
            'Criar em massa',
            'Criar em massa',
            'edit_products',
            'atelie-criar-lote',
            [$this, 'renderizar_upload']
        );

        add_submenu_page(
            null, // nao aparece no menu lateral, so acessivel pelo link apos criar o lote
            'Revisar lote',
            'Revisar lote',
            'edit_products',
            'atelie-revisar-lote',
            [$this, 'renderizar_revisao']
        );
    }

    public function carregar_assets(string $hook): void
    {
        if (strpos($hook, 'atelie-criar-lote') === false && strpos($hook, 'atelie-revisar-lote') === false) {
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
            'atelie-produto-ia-lote',
            plugins_url('assets/lote.js', dirname(__DIR__) . '/atelie-produto-ia.php'),
            ['jquery'],
            '0.1.0',
            true
        );
    }

    public function renderizar_upload(): void
    {
        $limite = env('AI_BATCH_MAX_ITEMS') ?: 25;
        ?>
        <div class="wrap atelie-novo-produto">
            <h1>Criar em massa</h1>
            <?php if (isset($_GET['erro']) && $_GET['erro'] === 'limite') : ?>
                <div class="notice notice-error"><p>Esse lote passou do limite de <?php echo esc_html($_GET['limite'] ?? $limite); ?> itens por vez — divida em lotes menores.</p></div>
            <?php elseif (isset($_GET['erro'])) : ?>
                <div class="notice notice-error"><p>Selecione pelo menos uma foto.</p></div>
            <?php endif; ?>

            <div class="atelie-card">
                <p>Cada foto selecionada vira um produto candidato, revisado separadamente depois. Limite de <?php echo esc_html($limite); ?> fotos por lote.</p>
                <div id="atelie-lote-fotos-preview" class="atelie-fotos-preview"></div>
                <p><button type="button" class="button button-primary" id="atelie-lote-escolher-fotos">Escolher fotos</button></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="atelie_criar_lote">
                    <?php wp_nonce_field('atelie_criar_lote', 'atelie_lote_nonce'); ?>
                    <input type="hidden" name="fotos_ids" id="atelie-lote-fotos-ids">
                    <button type="submit" class="button button-primary button-hero" id="atelie-lote-enviar" disabled>Processar lote</button>
                </form>
            </div>
        </div>
        <?php
    }

    public function renderizar_revisao(): void
    {
        $lote_id = isset($_GET['lote']) ? sanitize_text_field(wp_unslash($_GET['lote'])) : '';
        if ($lote_id === '') {
            echo '<div class="wrap"><p>Lote não encontrado.</p></div>';
            return;
        }

        $itens = get_posts([
            'post_type' => 'product',
            'post_status' => ['draft', 'publish'],
            'numberposts' => -1,
            'meta_key' => '_atelie_lote_id',
            'meta_value' => $lote_id,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        $rotulos = [
            'processando' => 'Processando',
            'pronto' => 'Pronto para revisão',
            'revisado' => 'Revisado',
            'erro' => 'Erro',
        ];
        ?>
        <div class="wrap atelie-novo-produto">
            <h1>Revisar lote</h1>
            <p><button type="button" class="button" onclick="location.reload();">Atualizar status</button></p>

            <div class="atelie-lote-grid">
                <?php foreach ($itens as $item) :
                    $status = get_post_meta($item->ID, '_atelie_lote_status', true) ?: 'processando';
                    $rotulo = $rotulos[$status] ?? $status;
                    ?>
                    <div class="atelie-lote-card">
                        <?php echo get_the_post_thumbnail($item->ID, 'thumbnail'); ?>
                        <strong><?php echo esc_html($item->post_title); ?></strong>
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
