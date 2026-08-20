<?php
/**
 * Endpoint REST que recebe fotos (ja enviadas pra Biblioteca de Midia) e
 * uma receita opcional, chama a IA de visao e devolve a sugestao — nunca
 * publica nada sozinho, so sugere. Ver plano, secao "Plugin custom Criar
 * Produto com IA".
 */

if (!defined('ABSPATH')) {
    exit;
}

use function Env\env;

class Atelie_Rest_Controller
{
    private const LIMITE_DIARIO_PADRAO = 100;
    private const LIMITE_FOTOS_POR_PRODUTO = 10;

    public function registrar(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route('atelie/v1', '/analisar-fotos', [
                'methods' => 'POST',
                'callback' => [$this, 'analisar_fotos'],
                'permission_callback' => [$this, 'usuario_pode_criar_produto'],
                'args' => [
                    'fotos' => ['required' => true],
                    'receita_imagem_id' => ['required' => false],
                    'receita_texto' => ['required' => false],
                ],
            ]);

            register_rest_route('atelie/v1', '/sugerir-case', [
                'methods' => 'POST',
                'callback' => [$this, 'sugerir_case'],
                'permission_callback' => [$this, 'usuario_pode_criar_case'],
                'args' => [
                    'fotos' => ['required' => true],
                    'relato' => ['required' => false],
                ],
            ]);

            register_rest_route('atelie/v1', '/editar-imagem', [
                'methods' => 'POST',
                'callback' => [$this, 'editar_imagem'],
                'permission_callback' => [$this, 'usuario_pode_editar_imagem'],
                'args' => [
                    'foto_id' => ['required' => true],
                    'prompt' => ['required' => true],
                ],
            ]);

            register_rest_route('atelie/v1', '/drive-listar', [
                'methods' => 'GET',
                'callback' => [$this, 'drive_listar'],
                'permission_callback' => [$this, 'usuario_pode_criar_produto'],
                'args' => [
                    'pasta' => ['required' => false],
                ],
            ]);

            register_rest_route('atelie/v1', '/drive-baixar-fotos', [
                'methods' => 'POST',
                'callback' => [$this, 'drive_baixar_fotos'],
                'permission_callback' => [$this, 'usuario_pode_criar_produto'],
                'args' => [
                    'fotos' => ['required' => true],
                ],
            ]);
        });
    }

    public function usuario_pode_criar_produto(): bool
    {
        return current_user_can('edit_products');
    }

    public function usuario_pode_criar_case(): bool
    {
        return current_user_can('edit_atelie_cases');
    }

    public function usuario_pode_editar_imagem(): bool
    {
        return current_user_can('edit_products') || current_user_can('edit_atelie_cases');
    }

    public function analisar_fotos(WP_REST_Request $request): WP_REST_Response
    {
        $fotos_ids = array_map('absint', (array) $request->get_param('fotos'));
        $fotos_ids = array_filter($fotos_ids);

        if (empty($fotos_ids)) {
            return new WP_REST_Response(['erro' => 'Anexe pelo menos uma foto.'], 400);
        }

        if (count($fotos_ids) > self::LIMITE_FOTOS_POR_PRODUTO) {
            return new WP_REST_Response(
                ['erro' => 'Máximo de ' . self::LIMITE_FOTOS_POR_PRODUTO . ' fotos por produto.'],
                400
            );
        }

        if (!$this->dentro_do_limite_diario()) {
            return new WP_REST_Response(
                ['erro' => 'Limite diário de análises por IA atingido. Tente de novo amanhã ou preencha manualmente.'],
                429
            );
        }

        $caminhos = [];
        foreach ($fotos_ids as $id) {
            $caminho = $this->caminho_para_ia($id);
            if ($caminho) {
                $caminhos[] = $caminho;
            }
        }

        $receita_imagem_id = absint($request->get_param('receita_imagem_id'));
        if ($receita_imagem_id) {
            $caminho_receita = $this->caminho_para_ia($receita_imagem_id);
            if ($caminho_receita) {
                $caminhos[] = $caminho_receita;
            }
        }

        $receita_texto = $request->get_param('receita_texto');
        $receita_texto = is_string($receita_texto) && trim($receita_texto) !== '' ? sanitize_textarea_field($receita_texto) : null;

        // Análise de fotos por IA pode levar até 40s (ver timeout em
        // Atelie_Ai_Vision_Service_Gemini::analisar()) — garante margem além disso
        // pro PHP não matar o processo antes de conseguir responder com o erro
        // tratado, mesmo que o default de execução do host seja mais baixo.
        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }

        try {
            $servico = Atelie_Ai_Vision_Service_Factory::criar();
            $sugestao = $servico->analisar($caminhos, $receita_texto);
        } catch (Throwable $e) {
            $this->registrar_log('erro: ' . $e->getMessage());
            return new WP_REST_Response(
                ['erro' => 'Não foi possível gerar a sugestão agora. Pode preencher os campos manualmente.'],
                502
            );
        }

        $this->incrementar_contador_diario();
        $this->registrar_log('ok, ' . count($caminhos) . ' imagem(ns)');

        return new WP_REST_Response(['sugestao' => $sugestao], 200);
    }

    public function sugerir_case(WP_REST_Request $request): WP_REST_Response
    {
        $fotos_ids = array_map('absint', (array) $request->get_param('fotos'));
        $fotos_ids = array_filter($fotos_ids);

        if (empty($fotos_ids)) {
            return new WP_REST_Response(['erro' => 'Anexe pelo menos uma foto.'], 400);
        }

        if (!$this->dentro_do_limite_diario()) {
            return new WP_REST_Response(
                ['erro' => 'Limite diário de análises por IA atingido. Tente de novo amanhã ou preencha manualmente.'],
                429
            );
        }

        $caminhos = [];
        foreach ($fotos_ids as $id) {
            $caminho = get_attached_file($id);
            if ($caminho) {
                $caminhos[] = $caminho;
            }
        }

        $relato = $request->get_param('relato');
        $relato = is_string($relato) && trim($relato) !== '' ? sanitize_textarea_field($relato) : null;

        try {
            $servico = Atelie_Ai_Vision_Service_Factory::criar();
            $sugestao = $servico->sugerirCase($caminhos, $relato);
        } catch (Throwable $e) {
            $this->registrar_log('erro (case): ' . $e->getMessage());
            return new WP_REST_Response(
                ['erro' => 'Não foi possível gerar a sugestão agora. Pode preencher os campos manualmente.'],
                502
            );
        }

        $this->incrementar_contador_diario();
        $this->registrar_log('ok (case), ' . count($caminhos) . ' imagem(ns)');

        return new WP_REST_Response(['sugestao' => $sugestao], 200);
    }

    /**
     * Lista pastas e fotos pro modal próprio de navegação/seleção do Drive —
     * sem parâmetro "pasta", lista o que foi compartilhado com a conta
     * conectada (raiz do modal); com "pasta", lista o conteúdo dela.
     */
    public function drive_listar(WP_REST_Request $request): WP_REST_Response
    {
        if (!Atelie_Drive_Config::conectado()) {
            return new WP_REST_Response(['erro' => 'Google Drive não conectado.'], 400);
        }

        $pasta = $request->get_param('pasta');
        $pasta_id = is_string($pasta) && trim($pasta) !== '' ? sanitize_text_field($pasta) : null;

        $itens = Atelie_Google_Drive_Service::listar_conteudo($pasta_id);

        return new WP_REST_Response(['itens' => $itens], 200);
    }

    /**
     * Baixa fotos soltas escolhidas no modal do Drive e sobe pra Biblioteca de Mídia —
     * usado quando a seleção é só de fotos (sem pasta), pra entrarem no mesmo fluxo
     * inline de "Novo Produto" (um produto só), sem passar pela fila de lote.
     */
    public function drive_baixar_fotos(WP_REST_Request $request): WP_REST_Response
    {
        if (!Atelie_Drive_Config::conectado()) {
            return new WP_REST_Response(['erro' => 'Google Drive não conectado.'], 400);
        }

        $fotos = (array) $request->get_param('fotos');
        $resultado = [];

        foreach ($fotos as $foto) {
            if (!is_array($foto) || !isset($foto['id'])) {
                continue;
            }

            $imagem = [
                'id' => sanitize_text_field((string) $foto['id']),
                'name' => isset($foto['name']) ? sanitize_text_field((string) $foto['name']) : 'foto',
                'mimeType' => isset($foto['mimeType']) ? sanitize_text_field((string) $foto['mimeType']) : 'image/jpeg',
            ];

            $anexo_id = Atelie_Google_Drive_Service::baixar_e_salvar_na_biblioteca($imagem, 'drive-solta');
            if ($anexo_id !== null) {
                $resultado[] = [
                    'id' => $anexo_id,
                    'url' => wp_get_attachment_image_url($anexo_id, 'thumbnail'),
                ];
            }
        }

        if (empty($resultado)) {
            return new WP_REST_Response(['erro' => 'Não deu pra baixar nenhuma das fotos selecionadas.'], 502);
        }

        return new WP_REST_Response(['fotos' => $resultado], 200);
    }

    public function editar_imagem(WP_REST_Request $request): WP_REST_Response
    {
        $foto_id = absint($request->get_param('foto_id'));
        $prompt = is_string($request->get_param('prompt')) ? sanitize_text_field($request->get_param('prompt')) : '';

        if (!$foto_id || $prompt === '') {
            return new WP_REST_Response(['erro' => 'Selecione a foto e descreva a edição desejada.'], 400);
        }

        if (!$this->dentro_do_limite_diario()) {
            return new WP_REST_Response(['erro' => 'Limite diário de chamadas de IA atingido. Tente de novo amanhã.'], 429);
        }

        $caminho = get_attached_file($foto_id);
        if (!$caminho) {
            return new WP_REST_Response(['erro' => 'Foto não encontrada.'], 404);
        }

        try {
            $servico = Atelie_Ai_Vision_Service_Factory::criar();
            $resultado = $servico->editarImagem($caminho, $prompt);
        } catch (Throwable $e) {
            $this->registrar_log('erro (editar imagem): ' . $e->getMessage());
            return new WP_REST_Response(['erro' => 'Não foi possível editar a imagem agora.'], 502);
        }

        if (!$resultado['ok']) {
            $this->registrar_log('erro (editar imagem): ' . $resultado['mensagem']);
            return new WP_REST_Response(['erro' => $resultado['mensagem']], 502);
        }

        $novo_id = $this->salvar_imagem_editada((string) $resultado['imagem_base64'], (string) $resultado['mime_type'], $foto_id);
        if (!$novo_id) {
            return new WP_REST_Response(['erro' => 'A IA editou a imagem, mas não deu pra salvar na biblioteca de mídia.'], 500);
        }

        $this->incrementar_contador_diario();
        $this->registrar_log('ok (editar imagem), origem foto ' . $foto_id . ' -> nova ' . $novo_id);

        return new WP_REST_Response([
            'imagem_id' => $novo_id,
            'url' => wp_get_attachment_image_url($novo_id, 'thumbnail'),
        ], 200);
    }

    /**
     * Salva o resultado da edição como um anexo NOVO na Biblioteca de Mídia —
     * nunca sobrescreve o arquivo original, pra quem usa o painel poder
     * comparar e escolher qual usar (ou descartar a versão editada).
     */
    private function salvar_imagem_editada(string $imagem_base64, string $mime_type, int $foto_original_id): ?int
    {
        $dados_binarios = base64_decode($imagem_base64, true);
        if ($dados_binarios === false) {
            return null;
        }

        $extensao = match ($mime_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $nome_base = get_the_title($foto_original_id) ?: 'imagem';
        $nome_arquivo = sanitize_file_name($nome_base . '-editada-' . time() . '.' . $extensao);

        $upload = wp_upload_bits($nome_arquivo, null, $dados_binarios);
        if (!empty($upload['error'])) {
            return null;
        }

        $anexo_id = wp_insert_attachment([
            'post_mime_type' => $mime_type,
            'post_title' => sanitize_file_name($nome_arquivo),
            'post_status' => 'inherit',
            'post_parent' => 0,
        ], $upload['file']);

        if (is_wp_error($anexo_id) || !$anexo_id) {
            return null;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadados = wp_generate_attachment_metadata($anexo_id, $upload['file']);
        wp_update_attachment_metadata($anexo_id, $metadados);

        return $anexo_id;
    }

    /**
     * Fotos de celular costumam vir com vários MB — mandar o arquivo original pra
     * IA deixa o upload e o processamento lentos o bastante pra estourar o timeout
     * do proxy do host (confirmado: chamada travando ~45s e voltando 502 cru do
     * Cloudflare em vez de erro tratado). O tamanho "large" do WordPress (até
     * 1024px) já é mais que suficiente pra IA reconhecer o produto, então usamos
     * ele quando existir, caindo pro original só se não tiver sido gerado.
     */
    private function caminho_para_ia(int $id): ?string
    {
        $reduzida = image_get_intermediate_size($id, 'large');
        if (is_array($reduzida) && isset($reduzida['path'])) {
            $upload_dir = wp_get_upload_dir();
            $caminho = trailingslashit($upload_dir['basedir']) . $reduzida['path'];
            if (is_readable($caminho)) {
                return $caminho;
            }
        }

        $original = get_attached_file($id);
        return $original !== false ? $original : null;
    }

    private function limite_diario(): int
    {
        $valor = env('AI_DAILY_LIMIT');
        return $valor !== null && $valor !== '' ? (int) $valor : self::LIMITE_DIARIO_PADRAO;
    }

    private function chave_contador(): string
    {
        return 'atelie_ia_chamadas_' . get_current_user_id() . '_' . gmdate('Y-m-d');
    }

    private function dentro_do_limite_diario(): bool
    {
        return (int) get_transient($this->chave_contador()) < $this->limite_diario();
    }

    private function incrementar_contador_diario(): void
    {
        $chave = $this->chave_contador();
        $atual = (int) get_transient($chave);
        set_transient($chave, $atual + 1, DAY_IN_SECONDS);
    }

    private function registrar_log(string $mensagem): void
    {
        $log = get_option('atelie_ia_log', []);
        if (!is_array($log)) {
            $log = [];
        }
        $log[] = [
            'quando' => current_time('mysql'),
            'usuario' => get_current_user_id(),
            'mensagem' => $mensagem,
        ];
        // mantem so os ultimos 200 registros, isso e log operacional, nao auditoria
        $log = array_slice($log, -200);
        update_option('atelie_ia_log', $log, false);
    }
}
