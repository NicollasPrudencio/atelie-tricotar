<?php
/**
 * Contrato que qualquer provedor de IA de visao precisa implementar.
 * Trocar de provedor (Gemini, GPT-4o-mini, Claude) e so escrever uma nova
 * classe que implementa essa interface, sem mexer no resto do plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Atelie_Ai_Vision_Service_Interface {

	/**
	 * Analisa fotos (e opcionalmente uma receita/padrao) e sugere os campos
	 * do produto.
	 *
	 * @param array<int, string> $imagens_paths Caminhos absolutos das imagens no servidor.
	 * @param string|null        $receita_texto Texto extraido da receita/padrao, se anexada.
	 *
	 * @return array{titulo: string, descricao: string, categoria: string, material_tecnica: string}
	 */
	public function analisar( array $imagens_paths, ?string $receita_texto = null ): array;

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
	public function avaliarTexto( string $titulo, string $descricao, string $tipo_objeto ): array;

	/**
	 * Transforma o relato informal da artesã sobre um trabalho já entregue
	 * (texto livre, tipo "conte como foi esse trabalho") em título/descrição
	 * profissionais pro case de portfólio — sem preço, é vitrine.
	 *
	 * @param array<int, string> $imagens_paths Caminhos absolutos das fotos do trabalho pronto.
	 * @param string|null        $relato Texto livre da artesã sobre o trabalho, se houver.
	 *
	 * @return array{titulo: string, descricao: string}
	 */
	public function sugerirCase( array $imagens_paths, ?string $relato = null ): array;

	/**
	 * Edita uma foto existente a partir de um pedido em texto (ex.: "deixe o
	 * fundo branco"). Sempre gera uma imagem NOVA — nunca sobrescreve o
	 * arquivo original, quem usa o painel decide se troca ou descarta.
	 *
	 * @return array{ok: bool, imagem_base64: ?string, mime_type: ?string, mensagem: string}
	 */
	public function editarImagem( string $imagem_path, string $prompt ): array;

	/**
	 * Gera o texto de anuncio (Meta e TikTok) a partir do titulo/descricao ja
	 * publicados de um produto ou case — sempre pensando em gerar visita e
	 * venda, nunca so "descrever" o item. So texto: a imagem usada no anuncio
	 * e uma foto ja existente do item (escolhida por quem publica), e video
	 * pro TikTok, se houver, e enviado manualmente — a IA nao gera midia aqui.
	 *
	 * @return array{
	 *     meta: array{texto_principal: string, titulo: string},
	 *     tiktok: array{legenda: string}
	 * }
	 */
	public function gerarAnuncio( string $titulo, string $descricao, string $tipo_objeto ): array;

	/**
	 * Busca na web candidatos de receita/padrão em outro idioma a partir de
	 * uma descricao (ex.: "amigurumi de elefante"). NUNCA reproduz o texto
	 * completo da receita encontrada (risco de direito autoral) — só aponta
	 * titulo/fonte/resumo curto, pra artesã decidir se quer abrir a fonte
	 * original e trazer o texto ela mesma (pra depois usar em
	 * `traduzirReceita()`).
	 *
	 * @return array{
	 *     ok: bool,
	 *     resultados: array<int, array{titulo: string, url: string, resumo: string}>,
	 *     mensagem: string
	 * }
	 */
	public function buscarReceita( string $descricao ): array;

	/**
	 * Traduz pro portugues um texto de receita/padrao que a artesã já tem
	 * (colado ou extraido de PDF/foto), em qualquer idioma de origem — sem
	 * resumir nem reinterpretar, só traduzir fielmente as instrucoes.
	 *
	 * @return array{ok: bool, texto_traduzido: string, mensagem: string}
	 */
	public function traduzirReceita( string $texto_original ): array;

	/**
	 * Rascunha o texto de acompanhamento pra responder um pedido de orçamento
	 * personalizado, a partir do que a cliente pediu — tom acolhedor,
	 * confirmando o entendimento do pedido e pedindo detalhes que estejam
	 * faltando (medida, cor, prazo desejado). NUNCA decide prazo de produção
	 * nem preço sozinha (risco real: promessa direta à cliente, envolve
	 * dinheiro) — esses dois pontos ficam marcados como "[PREENCHER]" no
	 * rascunho, pra artesã completar com dado real (da tela "Precificação",
	 * se for o caso) antes de enviar. Nunca sai sozinho: é sempre um rascunho
	 * pra copiar, completar e enviar manualmente pelo contato da cliente.
	 *
	 * @return array{ok: bool, rascunho: string, mensagem: string}
	 */
	public function rascunharRespostaOrcamento( string $descricao_pedido ): array;
}
