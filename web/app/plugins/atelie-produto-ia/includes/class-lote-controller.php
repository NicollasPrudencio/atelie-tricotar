<?php
/**
 * Criacao em massa: cria um produto rascunho por item (upload direto = uma
 * foto por produto; import do Drive = varias fotos de uma subpasta por
 * produto) e agenda o processamento de cada um separadamente via Action
 * Scheduler — nao e um job so pra tudo, e o que permite revisar o que ja
 * terminou enquanto o resto ainda processa. Ver plano, secao "Criacao de
 * produtos em massa via IA".
 */

if (!defined('ABSPATH')) {
    exit;
}

use function Env\env;

class Atelie_Lote_Controller
{
    private const HOOK_PROCESSAR_ITEM = 'atelie_lote_processar_item';
    private const GRUPO_ACTION_SCHEDULER = 'atelie-lote';
    private const MAX_TENTATIVAS = 3;
    private const BACKOFF_SEGUNDOS = [60, 300, 900]; // 1min, 5min, 15min

    public function registrar(): void
    {
        add_action('admin_post_atelie_criar_lote', [$this, 'criar_lote']);
        add_action(self::HOOK_PROCESSAR_ITEM, [$this, 'processar_item']);
    }

    public function limite_por_lote(): int
    {
        $valor = env('AI_BATCH_MAX_ITEMS');
        return $valor !== null && $valor !== '' ? (int) $valor : 25;
    }

    public function criar_lote(): void
    {
        if (
            !current_user_can('edit_products')
            || !isset($_POST['atelie_lote_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_lote_nonce'])), 'atelie_criar_lote')
        ) {
            wp_die('Ação não permitida.');
        }

        $fotos_ids = isset($_POST['fotos_ids']) ? array_filter(array_map('absint', explode(',', (string) wp_unslash($_POST['fotos_ids'])))) : [];
        $limite = $this->limite_por_lote();

        if (empty($fotos_ids)) {
            wp_safe_redirect(add_query_arg('erro', 'sem-fotos', admin_url('admin.php?page=atelie-criar-lote')));
            exit;
        }

        if (count($fotos_ids) > $limite) {
            wp_safe_redirect(add_query_arg(['erro' => 'limite', 'limite' => $limite], admin_url('admin.php?page=atelie-criar-lote')));
            exit;
        }

        // Upload direto: uma foto = um produto candidato, cada foto vira seu proprio grupo de 1.
        $grupos = array_map(static fn (int $id): array => [$id], $fotos_ids);
        $lote_id = $this->criar_lote_de_grupos($grupos);

        wp_safe_redirect(admin_url('admin.php?page=atelie-revisar-lote&lote=' . rawurlencode($lote_id)));
        exit;
    }

    /**
     * Cria um produto rascunho por grupo de fotos e agenda o processamento
     * de cada um — usado tanto pelo upload direto (grupos de 1) quanto pela
     * importação do Drive (um grupo = as fotos de uma subpasta).
     *
     * @param array<int, array<int, int>> $gruposFotosIds
     */
    public function criar_lote_de_grupos(array $gruposFotosIds): string
    {
        $lote_id = uniqid('lote_', true);

        foreach ($gruposFotosIds as $fotos_do_item) {
            $fotos_do_item = array_values(array_filter(array_map('absint', $fotos_do_item)));
            if (empty($fotos_do_item)) {
                continue;
            }

            $produto_id = wp_insert_post([
                'post_type' => 'product',
                'post_title' => 'Processando…',
                'post_status' => 'draft',
            ]);

            if (is_wp_error($produto_id) || !$produto_id) {
                continue;
            }

            wp_set_object_terms($produto_id, 'simple', 'product_type');
            set_post_thumbnail($produto_id, $fotos_do_item[0]);
            if (count($fotos_do_item) > 1) {
                update_post_meta($produto_id, '_product_image_gallery', implode(',', array_slice($fotos_do_item, 1)));
            }
            update_post_meta($produto_id, '_manage_stock', 'no');
            update_post_meta($produto_id, '_stock_status', 'instock');
            update_post_meta($produto_id, '_atelie_lote_id', $lote_id);
            update_post_meta($produto_id, '_atelie_lote_status', 'processando');
            update_post_meta($produto_id, '_atelie_lote_tentativas', 0);
            update_post_meta($produto_id, '_atelie_lote_fotos_ids', implode(',', $fotos_do_item));

            as_schedule_single_action(time(), self::HOOK_PROCESSAR_ITEM, ['produto_id' => $produto_id], self::GRUPO_ACTION_SCHEDULER);
        }

        return $lote_id;
    }

    public function processar_item(int $produto_id): void
    {
        $fotos_ids = array_filter(array_map('absint', explode(',', (string) get_post_meta($produto_id, '_atelie_lote_fotos_ids', true))));

        $caminhos = [];
        foreach ($fotos_ids as $foto_id) {
            $caminho = get_attached_file($foto_id);
            if ($caminho) {
                $caminhos[] = $caminho;
            }
        }

        try {
            $servico = Atelie_Ai_Vision_Service_Factory::criar();
            $sugestao = $servico->analisar($caminhos);

            wp_update_post([
                'ID' => $produto_id,
                'post_title' => $sugestao['titulo'] !== '' ? $sugestao['titulo'] : 'Produto sem título — revisar',
                'post_content' => $sugestao['descricao'],
            ]);

            if ($sugestao['categoria'] !== '') {
                $termo = term_exists($sugestao['categoria'], 'product_cat');
                if (!$termo) {
                    $termo = wp_insert_term($sugestao['categoria'], 'product_cat');
                }
                if (!is_wp_error($termo)) {
                    wp_set_object_terms($produto_id, (int) $termo['term_id'], 'product_cat');
                }
            }

            if ($sugestao['material_tecnica'] !== '') {
                update_post_meta($produto_id, '_atelie_material_tecnica_sugerido', $sugestao['material_tecnica']);
            }

            update_post_meta($produto_id, '_atelie_lote_status', 'pronto');
        } catch (Throwable $e) {
            $this->tratar_falha($produto_id, $e->getMessage());
        }
    }

    private function tratar_falha(int $produto_id, string $mensagem): void
    {
        $tentativas = (int) get_post_meta($produto_id, '_atelie_lote_tentativas', true);
        $tentativas++;
        update_post_meta($produto_id, '_atelie_lote_tentativas', $tentativas);
        update_post_meta($produto_id, '_atelie_lote_erro', $mensagem);

        if ($tentativas < self::MAX_TENTATIVAS) {
            $espera = self::BACKOFF_SEGUNDOS[$tentativas - 1] ?? end(self::BACKOFF_SEGUNDOS);
            as_schedule_single_action(time() + $espera, self::HOOK_PROCESSAR_ITEM, ['produto_id' => $produto_id], self::GRUPO_ACTION_SCHEDULER);
            // continua "processando" ate esgotar as tentativas — o item so vira "erro" de vez depois da ultima falha
            return;
        }

        update_post_meta($produto_id, '_atelie_lote_status', 'erro');
    }

    public function reprocessar_item(int $produto_id): void
    {
        update_post_meta($produto_id, '_atelie_lote_status', 'processando');
        update_post_meta($produto_id, '_atelie_lote_tentativas', 0);
        as_schedule_single_action(time(), self::HOOK_PROCESSAR_ITEM, ['produto_id' => $produto_id], self::GRUPO_ACTION_SCHEDULER);
    }
}
