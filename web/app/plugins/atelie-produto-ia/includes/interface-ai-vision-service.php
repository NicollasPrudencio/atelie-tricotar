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

    /**
     * Chamada minima (sem imagem, sem custo relevante) so pra confirmar que a
     * chave/assinatura configurada esta realmente funcionando agora — usado
     * pela tela de configuracao e pela verificacao automatica diaria.
     *
     * @return array{ok: bool, mensagem: string}
     */
    public function testarConexao(): array;

    /**
     * Revisa um titulo/descricao ja escritos (sugeridos pela IA e editados,
     * ou 100% manuais) procurando por sinais de amadorismo — nome proprio no
     * texto, elogio exagerado, linguagem nao profissional, falta de gatilho
     * de venda. E o que garante que texto que sai do painel tem cara de loja
     * de verdade, nao de rascunho.
     *
     * @return array{ok: bool, problemas: string[], titulo_sugerido: ?string, descricao_sugerida: ?string}
     */
    public function avaliarTexto(string $titulo, string $descricao, string $tipoObjeto): array;

    /**
     * Transforma o relato informal da artesã sobre um trabalho já entregue
     * (texto livre, tipo "conte como foi esse trabalho") em título/descrição
     * profissionais pro case de portfólio — sem preço, é vitrine.
     *
     * @param array<int, string> $imagens_paths Caminhos absolutos das fotos do trabalho pronto.
     * @param string|null $relato Texto livre da artesã sobre o trabalho, se houver.
     *
     * @return array{titulo: string, descricao: string}
     */
    public function sugerirCase(array $imagens_paths, ?string $relato = null): array;

    /**
     * Edita uma foto existente a partir de um pedido em texto (ex.: "deixe o
     * fundo branco"). Sempre gera uma imagem NOVA — nunca sobrescreve o
     * arquivo original, quem usa o painel decide se troca ou descarta.
     *
     * @return array{ok: bool, imagem_base64: ?string, mime_type: ?string, mensagem: string}
     */
    public function editarImagem(string $imagem_path, string $prompt): array;
}
