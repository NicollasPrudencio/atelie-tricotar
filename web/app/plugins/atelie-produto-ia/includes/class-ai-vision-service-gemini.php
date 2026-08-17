<?php
/**
 * Implementacao real via Gemini (Google AI Studio). Chave lida do .env,
 * nunca exposta ao navegador — essa classe so roda no servidor.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Ai_Vision_Service_Gemini implements Atelie_Ai_Vision_Service_Interface
{
    private string $api_key;
    private string $model;

    public function __construct(string $api_key, string $model = 'gemini-3.6-flash')
    {
        $this->api_key = $api_key;
        $this->model = $model;
    }

    public function analisar(array $imagens_paths, ?string $receita_texto = null): array
    {
        if (empty($this->api_key)) {
            throw new RuntimeException('AI_VISION_API_KEY não configurada.');
        }

        $parts = [
            ['text' => $this->montar_prompt($receita_texto)],
        ];

        foreach ($imagens_paths as $path) {
            if (!is_readable($path)) {
                continue;
            }
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $this->mime_type($path),
                    'data' => base64_encode((string) file_get_contents($path)),
                ],
            ];
        }

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($this->model),
            rawurlencode($this->api_key)
        );

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'contents' => [['parts' => $parts]],
                'generationConfig' => ['responseMimeType' => 'application/json'],
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Falha ao chamar a API de visão: ' . $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            $mensagem = $body['error']['message'] ?? ('HTTP ' . $status);
            throw new RuntimeException('API de visão retornou erro: ' . $mensagem);
        }

        $texto_json = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $sugestao = is_string($texto_json) ? json_decode($texto_json, true) : null;

        if (!is_array($sugestao)) {
            throw new RuntimeException('Resposta da IA não veio no formato esperado.');
        }

        return [
            'titulo' => (string) ($sugestao['titulo'] ?? ''),
            'descricao' => (string) ($sugestao['descricao'] ?? ''),
            'categoria' => (string) ($sugestao['categoria'] ?? ''),
            'material_tecnica' => (string) ($sugestao['material_tecnica'] ?? ''),
        ];
    }

    private function montar_prompt(?string $receita_texto): string
    {
        $prompt = 'Você ajuda um ateliê de tricô, crochê e amigurumis a cadastrar produtos artesanais. '
            . 'Com base nas fotos anexadas' . ($receita_texto ? ' e na receita/padrão a seguir' : '') . ', '
            . 'sugira os campos do produto. Responda SOMENTE um objeto JSON com exatamente estas chaves: '
            . '"titulo" (curto, atrativo), "descricao" (2-3 frases, tom acolhedor), '
            . '"categoria" (uma palavra ou expressão curta, ex: Amigurumis, Crochê, Tricô), '
            . '"material_tecnica" (materiais e técnica usados, vazio se não for possível saber).';

        if ($receita_texto) {
            $prompt .= "\n\nReceita/padrão anexada:\n" . $receita_texto;
        }

        return $prompt;
    }

    private function mime_type(string $path): string
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
