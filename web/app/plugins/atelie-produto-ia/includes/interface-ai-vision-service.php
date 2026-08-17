<?php
/**
 * Contrato que qualquer provedor de IA de visao precisa implementar.
 * Trocar de provedor (Gemini, GPT-4o-mini, Claude) e so escrever uma nova
 * classe que implementa essa interface, sem mexer no resto do plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

interface Atelie_Ai_Vision_Service_Interface
{
    /**
     * Analisa fotos (e opcionalmente uma receita/padrao) e sugere os campos
     * do produto.
     *
     * @param array<int, string> $imagens_paths Caminhos absolutos das imagens no servidor.
     * @param string|null $receita_texto Texto extraido da receita/padrao, se anexada.
     *
     * @return array{titulo: string, descricao: string, categoria: string, material_tecnica: string}
     */
    public function analisar(array $imagens_paths, ?string $receita_texto = null): array;
}
