<?php
/**
 * Implementacao real via Gemini (Google AI Studio). Chave lida do .env,
 * nunca exposta ao navegador — essa classe so roda no servidor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Ai_Vision_Service_Gemini implements Atelie_Ai_Vision_Service_Interface {

	/**
	 * Modelo pra edição/geração de imagem — nome confirmado via ListModels
	 * da API real (não é o mesmo modelo de texto/visão da classe toda).
	 */
	private const MODELO_IMAGEM = 'gemini-3.1-flash-image';

	private string $api_key;
	private string $model;

	public function __construct( string $api_key, string $model = 'gemini-3.6-flash' ) {
		$this->api_key = $api_key;
		$this->model   = $model;
	}

	public function analisar( array $imagens_paths, ?string $receita_texto = null ): array {
		if ( empty( $this->api_key ) ) {
			throw new RuntimeException( 'AI_VISION_API_KEY não configurada.' );
		}

		$parts = array(
			array( 'text' => $this->montar_prompt( $receita_texto ) ),
		);

		foreach ( $imagens_paths as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$parts[] = array(
				'inline_data' => array(
					'mime_type' => $this->mime_type( $path ),
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- leitura de arquivo local (upload do WP), nao URL remota; base64 aqui e formato exigido pela API, nao ofuscacao.
					'data'      => base64_encode( (string) file_get_contents( $path ) ),
				),
			);
		}

		// A latência do Gemini pra analisar imagem varia bastante (visto entre ~13s e
		// ~45s em teste real) e o proxy do host mata a conexão em algum ponto acima
		// disso sem deixar o PHP responder — quando isso acontece, o front recebe um
		// 502 cru do Cloudflare em vez do erro tratado (ver classe
		// Atelie_Rest_Controller::analisar_fotos()). Preferível errar rápido e limpo
		// (a pessoa tenta de novo ou preenche manualmente) do que esperar até esbarrar
		// nesse limite do proxy, que não temos como configurar nesse host.
		$body = $this->chamar(
			array(
				'contents'         => array( array( 'parts' => $parts ) ),
				'generationConfig' => array( 'responseMimeType' => 'application/json' ),
			),
			'analisar',
			40
		);

		$texto_json = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
		$sugestao   = is_string( $texto_json ) ? json_decode( $texto_json, true ) : null;

		if ( ! is_array( $sugestao ) ) {
			throw new RuntimeException( 'Resposta da IA não veio no formato esperado.' );
		}

		return array(
			'titulo'           => (string) ( $sugestao['titulo'] ?? '' ),
			'descricao'        => (string) ( $sugestao['descricao'] ?? '' ),
			'categoria'        => (string) ( $sugestao['categoria'] ?? '' ),
			'material_tecnica' => (string) ( $sugestao['material_tecnica'] ?? '' ),
		);
	}

	/**
	 * Recebe um monte de fotos soltas (upload direto na Biblioteca de Mídia,
	 * sem organização por pasta) e pede pra IA identificar quais são do MESMO
	 * produto (ângulos diferentes de uma peça só) — usado pela tela "Criar em
	 * Massa". Devolve os grupos como ÍNDICES (posição 0 a N-1, mesma ordem de
	 * `$imagens_paths`), não IDs de anexo — quem chama (Atelie_Rest_Controller)
	 * converte de volta pros IDs reais, já que essa classe não sabe de
	 * Biblioteca de Mídia.
	 *
	 * @param array<int, string> $imagens_paths
	 *
	 * @return array{ok: bool, grupos: array<int, array<int, int>>, custo: float, mensagem: string}
	 */
	public function agruparFotos( array $imagens_paths ): array {
		if ( empty( $this->api_key ) ) {
			return array(
				'ok'       => false,
				'grupos'   => array(),
				'custo'    => 0.0,
				'mensagem' => 'IA não configurada.',
			);
		}

		$parts   = array( array( 'text' => $this->montar_prompt_agrupamento( count( $imagens_paths ) ) ) );
		$indices = array();

		foreach ( $imagens_paths as $indice => $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$parts[]   = array( 'text' => "Foto {$indice}:" );
			$parts[]   = array(
				'inline_data' => array(
					'mime_type' => $this->mime_type( $path ),
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- leitura de arquivo local (upload do WP), nao URL remota; base64 aqui e formato exigido pela API, nao ofuscacao.
					'data'      => base64_encode( (string) file_get_contents( $path ) ),
				),
			);
			$indices[] = $indice;
		}

		if ( empty( $indices ) ) {
			return array(
				'ok'       => false,
				'grupos'   => array(),
				'custo'    => 0.0,
				'mensagem' => 'Nenhuma foto legível pra agrupar.',
			);
		}

		try {
			$body = $this->chamar(
				array(
					'contents'         => array( array( 'parts' => $parts ) ),
					'generationConfig' => array( 'responseMimeType' => 'application/json' ),
				),
				'agrupar_fotos',
				45
			);
		} catch ( Throwable $e ) {
			return array(
				'ok'       => false,
				'grupos'   => array(),
				'custo'    => 0.0,
				'mensagem' => $e->getMessage(),
			);
		}

		$custo = Atelie_Ai_Custo_Tracker::calcular_custo(
			(int) ( $body['usageMetadata']['promptTokenCount'] ?? 0 ),
			(int) ( $body['usageMetadata']['candidatesTokenCount'] ?? 0 )
		);

		$texto_json = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
		$resultado  = is_string( $texto_json ) ? json_decode( $texto_json, true ) : null;

		if ( ! is_array( $resultado ) || ! isset( $resultado['grupos'] ) || ! is_array( $resultado['grupos'] ) ) {
			return array(
				'ok'       => false,
				'grupos'   => array(),
				'custo'    => $custo,
				'mensagem' => 'Resposta da IA não veio no formato esperado.',
			);
		}

		// Valida os indices contra o que foi realmente enviado (a IA pode alucinar
		// um numero fora do intervalo ou repetir foto em dois grupos) — indice
		// repetido fica so no PRIMEIRO grupo em que aparece, indice invalido e
		// descartado. Qualquer foto que sobrar sem grupo (IA esqueceu dela) vira
		// grupo proprio no final, pra nenhuma foto selecionada sumir.
		$validos      = array_flip( $indices );
		$ja_agrupados = array();
		$grupos       = array();

		foreach ( $resultado['grupos'] as $grupo ) {
			if ( ! is_array( $grupo ) ) {
				continue;
			}
			$grupo_valido = array();
			foreach ( $grupo as $indice ) {
				$indice = (int) $indice;
				if ( isset( $validos[ $indice ] ) && ! isset( $ja_agrupados[ $indice ] ) ) {
					$grupo_valido[]          = $indice;
					$ja_agrupados[ $indice ] = true;
				}
			}
			if ( ! empty( $grupo_valido ) ) {
				$grupos[] = $grupo_valido;
			}
		}

		foreach ( $indices as $indice ) {
			if ( ! isset( $ja_agrupados[ $indice ] ) ) {
				$grupos[] = array( $indice );
			}
		}

		return array(
			'ok'       => true,
			'grupos'   => $grupos,
			'custo'    => $custo,
			'mensagem' => 'Agrupado com sucesso.',
		);
	}

	public function avaliarTexto( string $titulo, string $descricao, string $tipo_objeto ): array {
		if ( empty( $this->api_key ) ) {
			return array(
				'ok'                 => false,
				'problemas'          => array( 'IA não configurada — não deu pra revisar.' ),
				'titulo_sugerido'    => null,
				'descricao_sugerida' => null,
			);
		}

		$prompt = $this->montar_prompt_revisao( $titulo, $descricao, $tipo_objeto );

		try {
			$body = $this->chamar(
				array(
					'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
					'generationConfig' => array( 'responseMimeType' => 'application/json' ),
				),
				'revisar_texto'
			);
		} catch ( Throwable $e ) {
			return array(
				'ok'                 => false,
				'problemas'          => array( 'Não deu pra revisar agora: ' . $e->getMessage() ),
				'titulo_sugerido'    => null,
				'descricao_sugerida' => null,
			);
		}

		$texto_json = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
		$resultado  = is_string( $texto_json ) ? json_decode( $texto_json, true ) : null;

		if ( ! is_array( $resultado ) ) {
			return array(
				'ok'                 => false,
				'problemas'          => array( 'Resposta da IA não veio no formato esperado — não deu pra confirmar se o texto está ok.' ),
				'titulo_sugerido'    => null,
				'descricao_sugerida' => null,
			);
		}

		return array(
			'ok'                 => (bool) ( $resultado['ok'] ?? false ),
			'problemas'          => is_array( $resultado['problemas'] ?? null ) ? array_map( 'strval', $resultado['problemas'] ) : array(),
			'titulo_sugerido'    => isset( $resultado['titulo_sugerido'] ) && $resultado['titulo_sugerido'] !== '' ? (string) $resultado['titulo_sugerido'] : null,
			'descricao_sugerida' => isset( $resultado['descricao_sugerida'] ) && $resultado['descricao_sugerida'] !== '' ? (string) $resultado['descricao_sugerida'] : null,
		);
	}

	public function sugerirCase( array $imagens_paths, ?string $relato = null ): array {
		if ( empty( $this->api_key ) ) {
			throw new RuntimeException( 'AI_VISION_API_KEY não configurada.' );
		}

		$parts = array(
			array( 'text' => $this->montar_prompt_case( $relato ) ),
		);

		foreach ( $imagens_paths as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$parts[] = array(
				'inline_data' => array(
					'mime_type' => $this->mime_type( $path ),
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- leitura de arquivo local (upload do WP), nao URL remota; base64 aqui e formato exigido pela API, nao ofuscacao.
					'data'      => base64_encode( (string) file_get_contents( $path ) ),
				),
			);
		}

		$body = $this->chamar(
			array(
				'contents'         => array( array( 'parts' => $parts ) ),
				'generationConfig' => array( 'responseMimeType' => 'application/json' ),
			),
			'sugerir_case'
		);

		$texto_json = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
		$sugestao   = is_string( $texto_json ) ? json_decode( $texto_json, true ) : null;

		if ( ! is_array( $sugestao ) ) {
			throw new RuntimeException( 'Resposta da IA não veio no formato esperado.' );
		}

		return array(
			'titulo'    => (string) ( $sugestao['titulo'] ?? '' ),
			'descricao' => (string) ( $sugestao['descricao'] ?? '' ),
		);
	}

	public function editarImagem( string $imagem_path, string $prompt ): array {
		if ( empty( $this->api_key ) ) {
			return array(
				'ok'            => false,
				'imagem_base64' => null,
				'mime_type'     => null,
				'custo'         => 0.0,
				'mensagem'      => 'IA não configurada.',
			);
		}

		if ( ! is_readable( $imagem_path ) ) {
			return array(
				'ok'            => false,
				'imagem_base64' => null,
				'mime_type'     => null,
				'custo'         => 0.0,
				'mensagem'      => 'Foto não encontrada no servidor.',
			);
		}

		$mime_entrada  = $this->mime_type( $imagem_path );
		$imagem_pronta = $this->preparar_imagem_para_ia( $imagem_path );

		/**
		 * Endpoint diferente do resto da classe (/v1beta/interactions, não
		 * /generateContent) — é a "Interactions API" do Gemini pra geração e
		 * edição de imagem. Testada de verdade em 2026-09-17 (chave nova com
		 * faturamento ativo): a resposta vem em
		 * `steps[].content[].{type: "image", mime_type, data}` — não no
		 * formato `output_image`/`outputImage`/`output[]` que a doc do SDK
		 * dava a entender antes de testar. Ver parsing logo abaixo.
		 */
		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/interactions',
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $this->api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model' => self::MODELO_IMAGEM,
						'input' => array(
							array(
								'type' => 'text',
								'text' => $prompt,
							),
							array(
								'type'      => 'image',
								'mime_type' => $imagem_pronta['mime_type'],
								'data'      => $imagem_pronta['data'],
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'            => false,
				'imagem_base64' => null,
				'mime_type'     => null,
				'custo'         => 0.0,
				'mensagem'      => 'Falha de conexão: ' . $response->get_error_message(),
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$body   = is_array( $body ) ? $body : array();

		if ( $status !== 200 ) {
			$mensagem = $body['error']['message'] ?? ( 'HTTP ' . $status );
			return array(
				'ok'            => false,
				'imagem_base64' => null,
				'mime_type'     => null,
				'custo'         => 0.0,
				'mensagem'      => $mensagem,
			);
		}

		// Tokens REAIS que a propria API devolve (usage.total_input_tokens /
		// total_output_tokens) — usados pra registrar o custo de verdade, nao um
		// valor fixo chutado. Registrado mesmo se o parsing da imagem falhar
		// logo abaixo: a chamada ja consumiu tokens reais de qualquer jeito.
		$tokens_entrada_reais = (int) ( $body['usage']['total_input_tokens'] ?? 0 );
		$tokens_saida_reais   = (int) ( $body['usage']['total_output_tokens'] ?? 0 );

		// A resposta real vem em steps[].content[], podendo ter itens de texto
		// misturados com o de imagem — procura o primeiro item type=image.
		$dados_base64 = null;
		$mime_saida   = $mime_entrada;
		foreach ( (array) ( $body['steps'] ?? array() ) as $step ) {
			foreach ( (array) ( $step['content'] ?? array() ) as $item ) {
				if ( is_array( $item ) && ( $item['type'] ?? '' ) === 'image' && ! empty( $item['data'] ) ) {
					$dados_base64 = (string) $item['data'];
					$mime_saida   = (string) ( $item['mime_type'] ?? $mime_entrada );
					break 2;
				}
			}
		}

		if ( $dados_base64 === null || $dados_base64 === '' ) {
			$custo_mesmo_sem_imagem = Atelie_Ai_Custo_Tracker::registrar_imagem( 'editar_imagem', $tokens_entrada_reais, $tokens_saida_reais );
			return array(
				'ok'            => false,
				'imagem_base64' => null,
				'mime_type'     => null,
				'custo'         => $custo_mesmo_sem_imagem,
				'mensagem'      => 'Resposta da IA não trouxe imagem no formato esperado — revisar o parsing contra a documentação atual da Interactions API.',
			);
		}

		$custo = Atelie_Ai_Custo_Tracker::registrar_imagem( 'editar_imagem', $tokens_entrada_reais, $tokens_saida_reais );

		return array(
			'ok'            => true,
			'imagem_base64' => $dados_base64,
			'mime_type'     => (string) $mime_saida,
			'custo'         => $custo,
			'mensagem'      => 'Imagem editada com sucesso.',
		);
	}

	public function testarConexao(): array {
		if ( empty( $this->api_key ) ) {
			return array(
				'ok'       => false,
				'mensagem' => 'Nenhuma chave de API configurada.',
			);
		}

		try {
			$this->chamar(
				array(
					'contents' => array( array( 'parts' => array( array( 'text' => 'Responda apenas a palavra: ok' ) ) ) ),
				),
				'testar_conexao',
				20
			);
		} catch ( Throwable $e ) {
			return array(
				'ok'       => false,
				'mensagem' => $e->getMessage(),
			);
		}

		return array(
			'ok'       => true,
			'mensagem' => 'Conectado com sucesso.',
		);
	}

	/**
	 * Chamada HTTP compartilhada — centraliza erro, parsing e o registro de
	 * custo (todo mundo que chama a API real passa por aqui, então o custo
	 * nunca fica de fora sem querer).
	 *
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function chamar( array $payload, string $operacao, int $timeout = 30 ): array {
		$url = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $this->model ),
			rawurlencode( $this->api_key )
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensagem de excecao interna, nao e output renderizado.
			throw new RuntimeException( 'Falha de conexão: ' . $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$body   = is_array( $body ) ? $body : array();

		if ( $status !== 200 ) {
			$mensagem = $body['error']['message'] ?? ( 'HTTP ' . $status );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensagem de excecao interna, nao e output renderizado.
			throw new RuntimeException( 'API de visão retornou erro: ' . $mensagem );
		}

		$tokens_entrada = (int) ( $body['usageMetadata']['promptTokenCount'] ?? 0 );
		$tokens_saida   = (int) ( $body['usageMetadata']['candidatesTokenCount'] ?? 0 );
		Atelie_Ai_Custo_Tracker::registrar( $operacao, $tokens_entrada, $tokens_saida );

		return $body;
	}

	private function montar_prompt( ?string $receita_texto ): string {
		$prompt = 'Você é redator de marketing e vendas de um ateliê de tricô, crochê e amigurumis artesanais, '
			. 'especialista em transformar descrição de produto em gatilho de venda real. '
			. 'Com base nas fotos anexadas' . ( $receita_texto ? ' e na receita/padrão a seguir' : '' ) . ', '
			. 'sugira os campos do produto. Responda SOMENTE um objeto JSON com exatamente estas chaves: '
			. '"titulo" (curto, atrativo), '
			. '"descricao" (rica, persuasiva e BEM mais longa que uma descrição comum de e-commerce — pense em '
			. '4 a 6 parágrafos curtos, cobrindo o que fizer sentido pra ESSA peça especificamente, sempre com '
			. 'exemplos concretos, nunca genérico: as características do fio/material (textura, toque, cor, '
			. 'aparência) e o cuidado técnico da confecção; o apelo como presente ou peça de decoração (onde '
			. 'combina, que ambiente ou momento valoriza); se for brinquedo/amigurumi, o encanto pra criança '
			. '(estimula imaginação, é fofo, aconchegante) SEM AFIRMAR segurança médica ou infantil que não dá '
			. 'pra garantir — nunca diga "seguro para bebês", "hipoalergênico", "atóxico" ou equivalente, a menos '
			. 'que isso esteja explícito na receita/padrão anexada; e o valor do trabalho artesanal em si — tempo '
			. 'investido, tradição da técnica, exclusividade de peça feita à mão, nunca em série. Nunca invente '
			. 'característica que não dá pra ver ou inferir das fotos/receita, e nunca use elogio vazio sem '
			. 'motivo concreto por trás. '
			. '"categoria" (uma palavra ou expressão curta, ex: Amigurumis, Crochê, Tricô), '
			. '"material_tecnica" (materiais e técnica usados, vazio se não for possível saber).';

		if ( $receita_texto ) {
			$prompt .= "\n\nReceita/padrão anexada:\n" . $receita_texto;
		}

		return $prompt;
	}

	private function montar_prompt_agrupamento( int $total_fotos ): string {
		return 'Você recebeu ' . $total_fotos . ' fotos de peças artesanais de tricô, crochê ou amigurumi, '
			. 'identificadas por "Foto 0" até "Foto ' . ( $total_fotos - 1 ) . '", na mesma ordem em que aparecem '
			. 'a seguir. Algumas fotos são ângulos ou enquadramentos diferentes de UMA MESMA peça física; outras '
			. 'são de peças completamente diferentes. Sua tarefa: agrupar as fotos que mostram a mesma peça. '
			. 'Cada foto deve aparecer em exatamente um grupo — se uma foto for de uma peça sozinha, sem outra '
			. 'foto dela, ela forma um grupo com só ela mesma. Na dúvida entre juntar ou separar duas fotos, '
			. 'prefira separar (menos arriscado errar juntando peças diferentes do que separar fotos da mesma '
			. 'peça — quem revisa corrige facilmente juntando depois). '
			. 'Responda SOMENTE um objeto JSON no formato: {"grupos": [[0,1,2],[3],[4,5]]}, onde cada número é o '
			. 'índice da foto (0 a ' . ( $total_fotos - 1 ) . ').';
	}

	private function montar_prompt_case( ?string $relato ): string {
		$prompt = 'Você ajuda um ateliê de tricô, crochê e amigurumis a publicar um case de portfólio — '
			. 'um trabalho já entregue, mostrado como vitrine/prova social, sem preço e sem botão de comprar. '
			. 'Com base nas fotos anexadas' . ( $relato ? ' e no relato da artesã sobre como foi o trabalho' : '' ) . ', '
			. 'escreva um título curto e uma descrição de 2-4 frases em tom acolhedor e profissional, contando a peça/trabalho — '
			. 'não a artesã, não elogios vazios, com espaço pra quem ler se interessar por algo parecido. '
			. 'Responda SOMENTE um objeto JSON com exatamente estas chaves: "titulo", "descricao".';

		if ( $relato ) {
			$prompt .= "\n\nRelato da artesã sobre o trabalho:\n" . $relato;
		}

		return $prompt;
	}

	private function montar_prompt_revisao( string $titulo, string $descricao, string $tipo_objeto ): string {
		$objeto = $tipo_objeto === 'case' ? 'um case de portfólio (trabalho já entregue, sem preço, é vitrine)' : 'um produto à venda';

		return 'Você é revisor de textos de venda de um ateliê de tricô, crochê e amigurumis artesanais. '
			. 'O público é majoritariamente mulheres e o objetivo é vender — o tom deve ser delicado, acolhedor e profissional. '
			. "Avalie o título e a descrição abaixo, escritos para {$objeto}. Aponte SOMENTE problemas reais que dariam cara de amador, entre estes: "
			. '(1) nome próprio da artesã/pessoa aparecendo no texto (o texto fala da peça, não de quem fez), '
			. '(2) elogio exagerado ou autopromoção vazia ("a melhor peça", "simplesmente perfeita" sem motivo concreto), '
			. '(3) linguagem informal demais ou erro de português, '
			. '(4) ausência completa de qualquer gatilho de venda (não precisa ter todos, mas o texto não pode ser puramente descritivo/neutro). '
			. 'Se não houver nenhum desses problemas, responda ok=true e não invente problema. '
			. 'Responda SOMENTE um objeto JSON com as chaves: "ok" (bool), "problemas" (array de strings curtas, uma por problema encontrado, vazio se ok=true), '
			. '"titulo_sugerido" e "descricao_sugerida" (versão corrigida, só quando ok=false; string vazia quando ok=true).'
			. "\n\nTítulo: {$titulo}\nDescrição: {$descricao}";
	}

	public function gerarAnuncio( string $titulo, string $descricao, string $tipo_objeto ): array {
		$vazio = array(
			'meta'   => array(
				'texto_principal' => '',
				'titulo'          => '',
			),
			'tiktok' => array( 'legenda' => '' ),
		);

		if ( empty( $this->api_key ) ) {
			return $vazio;
		}

		$prompt = $this->montar_prompt_anuncio( $titulo, $descricao, $tipo_objeto );

		try {
			$body = $this->chamar(
				array(
					'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
					'generationConfig' => array( 'responseMimeType' => 'application/json' ),
				),
				'gerar_anuncio'
			);
		} catch ( Throwable $e ) {
			return $vazio;
		}

		$texto_json = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
		$resultado  = is_string( $texto_json ) ? json_decode( $texto_json, true ) : null;

		if ( ! is_array( $resultado ) ) {
			return $vazio;
		}

		return array(
			'meta'   => array(
				'texto_principal' => (string) ( $resultado['meta']['texto_principal'] ?? '' ),
				'titulo'          => (string) ( $resultado['meta']['titulo'] ?? '' ),
			),
			'tiktok' => array(
				'legenda' => (string) ( $resultado['tiktok']['legenda'] ?? '' ),
			),
		);
	}

	private function montar_prompt_anuncio( string $titulo, string $descricao, string $tipo_objeto ): string {
		$objeto = $tipo_objeto === 'case' ? 'um trabalho do portfólio (vitrine, sem preço — o anúncio deve gerar interesse e contato, não "comprar agora")' : 'um produto à venda';

		return 'Você é redator de anúncios pagos (Meta Ads e TikTok Ads) de um ateliê de tricô, crochê e amigurumis artesanais. '
			. 'O público é majoritariamente mulheres. O objetivo do anúncio NÃO é descrever a peça — é gerar CLIQUE (visita ao site) e, '
			. "a partir disso, venda. Use o título e a descrição já publicados de {$objeto} como referência do que é a peça, mas escreva o anúncio do zero, pensando em performance de anúncio pago (gatilho, urgência sutil ou curiosidade, tom caloroso e artesanal — nunca robótico ou exagerado a ponto de parecer falso). "
			. 'Gere DOIS anúncios diferentes, adequados a cada plataforma: '
			. '(1) Meta (Instagram/Facebook Ads): "texto_principal" (o texto principal do anúncio, até uns 150 caracteres, direto ao ponto) e "titulo" (manchete curta, até 40 caracteres). '
			. '(2) TikTok Ads: "legenda" (tom mais casual e direto que o Meta, como quem fala pra uma amiga, até uns 150 caracteres, pode incluir 1-2 hashtags relevantes ao final). '
			. 'Responda SOMENTE um objeto JSON no formato: {"meta": {"texto_principal": "...", "titulo": "..."}, "tiktok": {"legenda": "..."}}.'
			. "\n\nTítulo do item: {$titulo}\nDescrição do item: {$descricao}";
	}

	public function buscarReceita( string $descricao ): array {
		if ( empty( $this->api_key ) ) {
			return array(
				'ok'         => false,
				'resultados' => array(),
				'mensagem'   => 'IA não configurada.',
			);
		}

		$prompt = 'Busque na web receitas/padrões (em qualquer idioma) de tricô, crochê ou amigurumi que combinem com a descrição a seguir. '
			. 'IMPORTANTE: nunca reproduza o texto completo de uma receita encontrada — isso pode violar direito autoral do criador do padrão. '
			. 'Devolva no máximo 5 candidatos, cada um em UMA linha, exatamente neste formato (sem markdown, sem numeração): '
			. 'RESULTADO: título curto | URL da fonte | resumo de até 20 palavras (o que é a peça, não como fazer)'
			. "\n\nDescrição buscada: {$descricao}";

		try {
			$body = $this->chamar(
				array(
					'contents' => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
					'tools'    => array( array( 'google_search' => new stdClass() ) ),
				),
				'buscar_receita',
				45
			);
		} catch ( Throwable $e ) {
			return array(
				'ok'         => false,
				'resultados' => array(),
				'mensagem'   => 'Não deu pra buscar agora: ' . $e->getMessage(),
			);
		}

		$texto  = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
		$linhas = array_filter( explode( "\n", (string) $texto ) );

		$resultados = array();
		foreach ( $linhas as $linha ) {
			if ( stripos( $linha, 'RESULTADO:' ) !== 0 ) {
				continue;
			}
			$campos = explode( '|', substr( $linha, strlen( 'RESULTADO:' ) ) );
			if ( count( $campos ) < 3 ) {
				continue;
			}
			$resultados[] = array(
				'titulo' => trim( $campos[0] ),
				'url'    => trim( $campos[1] ),
				'resumo' => trim( $campos[2] ),
			);
		}

		if ( empty( $resultados ) ) {
			return array(
				'ok'         => false,
				'resultados' => array(),
				'mensagem'   => 'Não encontrei nenhum resultado pra essa descrição — tente descrever de outro jeito.',
			);
		}

		return array(
			'ok'         => true,
			'resultados' => $resultados,
			'mensagem'   => '',
		);
	}

	public function traduzirReceita( string $texto_original ): array {
		if ( empty( $this->api_key ) ) {
			return array(
				'ok'              => false,
				'texto_traduzido' => '',
				'mensagem'        => 'IA não configurada.',
			);
		}

		if ( trim( $texto_original ) === '' ) {
			return array(
				'ok'              => false,
				'texto_traduzido' => '',
				'mensagem'        => 'Cole o texto da receita antes de traduzir.',
			);
		}

		$prompt = 'Traduza pro português (Brasil) o texto de receita/padrão de tricô, crochê ou amigurumi abaixo, em qualquer idioma que esteja. '
			. 'Traduza fielmente as instruções técnicas (pontos, quantidades, medidas — mantenha abreviações de ponto padrão em português quando existir equivalente conhecido). '
			. 'Não resuma, não invente nem complete partes que não estejam no original. Responda SOMENTE com o texto traduzido, sem comentário nenhum antes ou depois.'
			. "\n\nTexto original:\n{$texto_original}";

		try {
			$body = $this->chamar(
				array( 'contents' => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ) ),
				'traduzir_receita',
				45
			);
		} catch ( Throwable $e ) {
			return array(
				'ok'              => false,
				'texto_traduzido' => '',
				'mensagem'        => 'Não deu pra traduzir agora: ' . $e->getMessage(),
			);
		}

		$traduzido = (string) ( $body['candidates'][0]['content']['parts'][0]['text'] ?? '' );

		if ( trim( $traduzido ) === '' ) {
			return array(
				'ok'              => false,
				'texto_traduzido' => '',
				'mensagem'        => 'A IA não devolveu nenhum texto — tente de novo.',
			);
		}

		return array(
			'ok'              => true,
			'texto_traduzido' => $traduzido,
			'mensagem'        => '',
		);
	}

	public function sugerirSeo( string $titulo, string $descricao, string $tipo_objeto ): array {
		$vazio = array(
			'meta_titulo'    => '',
			'meta_descricao' => '',
			'alt_text'       => '',
		);

		if ( empty( $this->api_key ) ) {
			return $vazio;
		}

		$prompt = $this->montar_prompt_seo( $titulo, $descricao, $tipo_objeto );

		try {
			$body = $this->chamar(
				array(
					'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
					'generationConfig' => array( 'responseMimeType' => 'application/json' ),
				),
				'sugerir_seo'
			);
		} catch ( Throwable $e ) {
			return $vazio;
		}

		$texto_json = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
		$resultado  = is_string( $texto_json ) ? json_decode( $texto_json, true ) : null;

		if ( ! is_array( $resultado ) ) {
			return $vazio;
		}

		return array(
			'meta_titulo'    => (string) ( $resultado['meta_titulo'] ?? '' ),
			'meta_descricao' => (string) ( $resultado['meta_descricao'] ?? '' ),
			'alt_text'       => (string) ( $resultado['alt_text'] ?? '' ),
		);
	}

	private function montar_prompt_seo( string $titulo, string $descricao, string $tipo_objeto ): string {
		$objeto = $tipo_objeto === 'case' ? 'um trabalho do portfólio (vitrine, sem preço)' : 'um produto à venda';

		return 'Você é especialista em SEO de e-commerce artesanal (tricô, crochê, amigurumis). '
			. "A partir do título e descrição já publicados de {$objeto}, gere: "
			. '(1) "meta_titulo" — título pra resultado de busca do Google, até 60 caracteres, incluindo palavra-chave natural do produto (ex.: tipo de peça + técnica), sem repetir clichês de loja; '
			. '(2) "meta_descricao" — descrição pra resultado de busca, até 155 caracteres, que desperte clique (o que é, o que torna especial), sem clickbait; '
			. '(3) "alt_text" — texto alternativo da foto principal, descrevendo objetivamente o que aparece na imagem pra quem usa leitor de tela ou pro Google Imagens (não é texto de venda, é descrição visual literal e curta, até 125 caracteres). '
			. 'Responda SOMENTE um objeto JSON no formato: {"meta_titulo": "...", "meta_descricao": "...", "alt_text": "..."}.'
			. "\n\nTítulo: {$titulo}\nDescrição: {$descricao}";
	}

	public function sugerirEdicaoImagem( string $imagem_path ): array {
		$vazio = array(
			'ok'            => false,
			'imagem_base64' => null,
			'mime_type'     => null,
			'diagnostico'   => '',
			'custo'         => 0.0,
			'mensagem'      => '',
		);

		if ( empty( $this->api_key ) ) {
			return array_merge( $vazio, array( 'mensagem' => 'IA não configurada.' ) );
		}

		if ( ! is_readable( $imagem_path ) ) {
			return array_merge( $vazio, array( 'mensagem' => 'Foto não encontrada no servidor.' ) );
		}

		$diagnostico = $this->diagnosticar_foto( $imagem_path );

		if ( ! $diagnostico['ok'] ) {
			// Mesmo diagnostico tendo falhado, pode ter custo (chamar() so registra
			// o custo DEPOIS de uma resposta HTTP 200 — ver diagnosticar_foto()).
			return array_merge(
				$vazio,
				array(
					'custo'    => $diagnostico['custo'],
					'mensagem' => $diagnostico['mensagem'],
				)
			);
		}

		if ( $diagnostico['prompt_edicao'] === '' ) {
			return array(
				'ok'            => true,
				'imagem_base64' => null,
				'mime_type'     => null,
				'diagnostico'   => $diagnostico['diagnostico'],
				// So o custo do diagnostico — nao editou, entao editarImagem() nunca rodou.
				'custo'         => $diagnostico['custo'],
				'mensagem'      => '',
			);
		}

		$edicao = $this->editarImagem( $imagem_path, $diagnostico['prompt_edicao'] );

		return array(
			'ok'            => $edicao['ok'],
			'imagem_base64' => $edicao['imagem_base64'],
			'mime_type'     => $edicao['mime_type'],
			'diagnostico'   => $diagnostico['diagnostico'],
			// Soma diagnostico + edicao — as duas chamadas realmente aconteceram.
			'custo'         => $diagnostico['custo'] + $edicao['custo'],
			'mensagem'      => $edicao['mensagem'],
		);
	}

	/**
	 * @return array{ok: bool, diagnostico: string, prompt_edicao: string, custo: float, mensagem: string}
	 */
	private function diagnosticar_foto( string $imagem_path ): array {
		$prompt = 'Você é fotógrafo de produto especialista em maximizar conversão em venda de e-commerce artesanal '
			. '(tricô, crochê, amigurumis). Avalie esta foto — iluminação, fundo, enquadramento, nitidez — e decida se ela '
			. 'se beneficiaria de uma edição simples (a edição não pode inventar nem remover elementos da peça em si, só '
			. 'melhorar apresentação: fundo, luz, corte, nitidez). '
			. 'Se sim, escreva um pedido de edição direto e objetivo em português, como se fosse escrito por uma pessoa '
			. '(ex.: "deixe o fundo branco e aumente um pouco o brilho"). Se a foto já estiver boa o suficiente, não sugira edição nenhuma. '
			. 'Responda SOMENTE um objeto JSON no formato: {"diagnostico": "resumo curto do que avaliou na foto", "prompt_edicao": "pedido de edição, ou string vazia se a foto já está boa"}.';

		$imagem_pronta = $this->preparar_imagem_para_ia( $imagem_path );

		try {
			$body = $this->chamar(
				array(
					'contents'         => array(
						array(
							'parts' => array(
								array( 'text' => $prompt ),
								array(
									'inline_data' => array(
										'mime_type' => $imagem_pronta['mime_type'],
										'data'      => $imagem_pronta['data'],
									),
								),
							),
						),
					),
					'generationConfig' => array( 'responseMimeType' => 'application/json' ),
				),
				'diagnosticar_foto',
				30
			);
		} catch ( Throwable $e ) {
			return array(
				'ok'            => false,
				'diagnostico'   => '',
				'prompt_edicao' => '',
				'custo'         => 0.0,
				'mensagem'      => 'Não deu pra avaliar a foto agora: ' . $e->getMessage(),
			);
		}

		// O custo ja foi registrado dentro de chamar() — recalculado aqui so pra devolver
		// pra quem chamou mostrar (chamar() so devolve o corpo da resposta, nao o custo).
		$custo = Atelie_Ai_Custo_Tracker::calcular_custo(
			(int) ( $body['usageMetadata']['promptTokenCount'] ?? 0 ),
			(int) ( $body['usageMetadata']['candidatesTokenCount'] ?? 0 )
		);

		$texto_json = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
		$dados      = is_string( $texto_json ) ? json_decode( $texto_json, true ) : null;

		if ( ! is_array( $dados ) ) {
			return array(
				'ok'            => false,
				'diagnostico'   => '',
				'prompt_edicao' => '',
				'custo'         => $custo,
				'mensagem'      => 'Resposta da IA não veio no formato esperado.',
			);
		}

		return array(
			'ok'            => true,
			'diagnostico'   => (string) ( $dados['diagnostico'] ?? '' ),
			'prompt_edicao' => (string) ( $dados['prompt_edicao'] ?? '' ),
			'custo'         => $custo,
			'mensagem'      => '',
		);
	}

	public function sugerirPrecoVenda( string $titulo_produto, string $descricao_produto, float $custo ): array {
		$vazio = array(
			'ok'             => false,
			'preco_sugerido' => 0.0,
			'faixa_min'      => 0.0,
			'faixa_max'      => 0.0,
			'justificativa'  => '',
			'mensagem'       => '',
		);

		if ( empty( $this->api_key ) ) {
			return array_merge( $vazio, array( 'mensagem' => 'IA não configurada.' ) );
		}

		if ( $custo <= 0 ) {
			return array_merge( $vazio, array( 'mensagem' => 'Informe um custo maior que zero pra calcular uma sugestão.' ) );
		}

		$prompt = 'Você ajuda a dona de um ateliê de tricô, crochê e amigurumis artesanais a precificar uma peça pra venda. '
			. 'O custo já calculado (matéria-prima + hora técnica) dessa peça é R$ ' . number_format( $custo, 2, '.', '' ) . '. '
			. 'Considerando esse custo, o tipo de peça e uma referência realista de mercado pra artesanato semelhante vendido no Brasil, '
			. 'sugira um preço de venda com margem saudável pra um ateliê pequeno (o preço final é sempre decisão de quem vende, isso é só uma sugestão de referência). '
			. 'Responda SOMENTE um objeto JSON no formato: {"preco_sugerido": 00.00, "faixa_min": 00.00, "faixa_max": 00.00, "justificativa": "1-2 frases curtas explicando o raciocínio"} — todos os valores numéricos em reais, sem símbolo de moeda.'
			. "\n\nTítulo da peça: {$titulo_produto}\nDescrição: {$descricao_produto}";

		try {
			$body = $this->chamar(
				array(
					'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
					'generationConfig' => array( 'responseMimeType' => 'application/json' ),
				),
				'sugerir_preco'
			);
		} catch ( Throwable $e ) {
			return array_merge( $vazio, array( 'mensagem' => 'Não deu pra sugerir um preço agora: ' . $e->getMessage() ) );
		}

		$texto_json = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
		$resultado  = is_string( $texto_json ) ? json_decode( $texto_json, true ) : null;

		if ( ! is_array( $resultado ) ) {
			return array_merge( $vazio, array( 'mensagem' => 'Resposta da IA não veio no formato esperado.' ) );
		}

		return array(
			'ok'             => true,
			'preco_sugerido' => (float) ( $resultado['preco_sugerido'] ?? 0 ),
			'faixa_min'      => (float) ( $resultado['faixa_min'] ?? 0 ),
			'faixa_max'      => (float) ( $resultado['faixa_max'] ?? 0 ),
			'justificativa'  => (string) ( $resultado['justificativa'] ?? '' ),
			'mensagem'       => '',
		);
	}

	public function rascunharRespostaOrcamento( string $descricao_pedido ): array {
		if ( empty( $this->api_key ) ) {
			return array(
				'ok'       => false,
				'rascunho' => '',
				'mensagem' => 'IA não configurada.',
			);
		}

		if ( trim( $descricao_pedido ) === '' ) {
			return array(
				'ok'       => false,
				'rascunho' => '',
				'mensagem' => 'Sem descrição do pedido pra rascunhar uma resposta.',
			);
		}

		$prompt = 'Você ajuda a dona de um ateliê de tricô, crochê e amigurumis a responder um pedido de orçamento personalizado. '
			. 'Escreva um rascunho de mensagem (WhatsApp ou e-mail, tom acolhedor e profissional) que: '
			. '(1) confirma que recebeu o pedido e mostra que entendeu o que a cliente quer; '
			. '(2) se a descrição do pedido estiver vaga em algum ponto importante (medida, cor, quantidade, prazo desejado pela cliente), pergunta o que falta; '
			. '(3) informa que o prazo de produção é "[PREENCHER]" e o valor é "[PREENCHER]" — NUNCA invente prazo nem preço, deixe literalmente esses dois placeholders pra artesã completar depois com dado real; '
			. '(4) termina de forma calorosa, sem soar robótico. '
			. 'Responda SOMENTE o texto da mensagem, sem comentário antes ou depois, sem aspas envolvendo tudo.'
			. "\n\nPedido da cliente:\n{$descricao_pedido}";

		try {
			$body = $this->chamar(
				array( 'contents' => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ) ),
				'rascunhar_orcamento',
				30
			);
		} catch ( Throwable $e ) {
			return array(
				'ok'       => false,
				'rascunho' => '',
				'mensagem' => 'Não deu pra rascunhar agora: ' . $e->getMessage(),
			);
		}

		$rascunho = (string) ( $body['candidates'][0]['content']['parts'][0]['text'] ?? '' );

		if ( trim( $rascunho ) === '' ) {
			return array(
				'ok'       => false,
				'rascunho' => '',
				'mensagem' => 'A IA não devolveu nenhum texto — tente de novo.',
			);
		}

		return array(
			'ok'       => true,
			'rascunho' => $rascunho,
			'mensagem' => '',
		);
	}

	private function mime_type( string $path ): string {
		$ext = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		return match ( $ext ) {
			'png' => 'image/png',
			'webp' => 'image/webp',
			'gif' => 'image/gif',
			default => 'image/jpeg',
		};
	}

	/**
	 * Reduz a foto ANTES de mandar pra IA (nunca mexe no arquivo original salvo
	 * no produto) — fotos de celular costumam vir bem maiores do que a IA
	 * precisa pra editar/avaliar direito, e o custo de entrada da API escala
	 * com o tamanho da imagem (mais "tiles" de visão = mais tokens). Achado
	 * em 2026-09-18 depois de um salto real de custo ao testar edicao de
	 * imagem. Se o redimensionamento falhar por qualquer motivo (biblioteca
	 * indisponivel, arquivo corrompido), cai pro arquivo original sem erro —
	 * mais caro, mas nunca quebra a funcionalidade por causa disso.
	 *
	 * @return array{data: string, mime_type: string}
	 */
	private function preparar_imagem_para_ia( string $imagem_path, int $lado_maximo = 1536 ): array {
		$mime_original = $this->mime_type( $imagem_path );

		$editor = wp_get_image_editor( $imagem_path );
		if ( is_wp_error( $editor ) ) {
			return array(
				'data'      => $this->ler_arquivo_local_base64( $imagem_path ),
				'mime_type' => $mime_original,
			);
		}

		$tamanho = $editor->get_size();
		if ( is_array( $tamanho ) && max( (int) $tamanho['width'], (int) $tamanho['height'] ) <= $lado_maximo ) {
			// Ja e pequena o suficiente, nao precisa redimensionar.
			return array(
				'data'      => $this->ler_arquivo_local_base64( $imagem_path ),
				'mime_type' => $mime_original,
			);
		}

		$editor->resize( $lado_maximo, $lado_maximo, false );

		$temp  = wp_tempnam( 'atelie-ia-redimensionada' );
		$salvo = $editor->save( $temp, $mime_original );

		if ( is_wp_error( $salvo ) || ! isset( $salvo['path'] ) || ! is_readable( $salvo['path'] ) ) {
			$this->apagar_arquivo_local( $temp );
			return array(
				'data'      => $this->ler_arquivo_local_base64( $imagem_path ),
				'mime_type' => $mime_original,
			);
		}

		$dados = $this->ler_arquivo_local_base64( $salvo['path'] );
		$this->apagar_arquivo_local( $salvo['path'] );

		return array(
			'data'      => $dados,
			'mime_type' => (string) ( $salvo['mime-type'] ?? $mime_original ),
		);
	}

	/**
	 * Le e codifica em base64 um arquivo LOCAL (upload do WP ou temporario
	 * proprio, nunca URL remota) — helper pra nao repetir o phpcs:ignore em
	 * cada chamada.
	 */
	private function ler_arquivo_local_base64( string $path ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- leitura de arquivo local (upload do WP ou temporario proprio), nao URL remota.
		return base64_encode( (string) file_get_contents( $path ) );
	}

	/**
	 * Apaga um arquivo temporario proprio (wp_tempnam ou WP_Image_Editor::save)
	 * — helper pra nao repetir o phpcs:ignore em cada chamada.
	 */
	private function apagar_arquivo_local( string $path ): void {
		if ( ! file_exists( $path ) ) {
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- limpeza de arquivo temporario proprio, nao input externo.
		unlink( $path );
	}
}
