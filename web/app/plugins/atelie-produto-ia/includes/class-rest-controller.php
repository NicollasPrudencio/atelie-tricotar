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
        });
    }

    public function usuario_pode_criar_produto(): bool
    {
        return current_user_can('edit_products');
    }

    public function analisar_fotos(WP_REST_Request $request): WP_REST_Response
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

        $receita_imagem_id = absint($request->get_param('receita_imagem_id'));
        if ($receita_imagem_id) {
            $caminho_receita = get_attached_file($receita_imagem_id);
            if ($caminho_receita) {
                $caminhos[] = $caminho_receita;
            }
        }

        $receita_texto = $request->get_param('receita_texto');
        $receita_texto = is_string($receita_texto) && trim($receita_texto) !== '' ? sanitize_textarea_field($receita_texto) : null;

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
