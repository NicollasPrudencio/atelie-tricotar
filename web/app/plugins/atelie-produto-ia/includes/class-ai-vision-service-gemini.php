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
				'mensagem'      => 'IA não configurada.',
			);
		}

		if ( ! is_readable( $imagem_path ) ) {
			return array(
				'ok'            => false,
				'imagem_base64' => null,
				'mime_type'     => null,
				'mensagem'      => 'Foto não encontrada no servidor.',
			);
		}

		$mime_entrada = $this->mime_type( $imagem_path );

		/**
		 * Endpoint diferente do resto da classe (/v1beta/interactions, não
		 * /generateContent) — é a "Interactions API" do Gemini pra geração e
		 * edição de imagem, confirmada contra a documentação oficial (não
		 * testada de verdade: a chave de dev deste projeto tem cota ZERO pra
		 * modelos de imagem no nível gratuito — precisa de faturamento ativo
		 * pra validar isso de fato). O corpo da requisição é o confirmado na
		 * doc; o formato exato da IMAGEM DE VOLTA na resposta é inferido a
		 * partir do SDK oficial (que expõe `interaction.output_image.data`) —
		 * revisar contra uma chamada real assim que possível.
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
								'mime_type' => $mime_entrada,
								// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- leitura de arquivo local (upload do WP), nao URL remota.
								'data'      => base64_encode( (string) file_get_contents( $imagem_path ) ),
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
				'mensagem'      => $mensagem,
			);
		}

		// Tenta os formatos mais prováveis pro campo de imagem de saída — ver aviso acima.
		$imagem       = $body['output_image'] ?? $body['outputImage'] ?? ( $body['output'][0] ?? null ) ?? ( $body['outputs'][0] ?? null );
		$dados_base64 = is_array( $imagem ) ? ( $imagem['data'] ?? null ) : null;
		$mime_saida   = is_array( $imagem ) ? ( $imagem['mime_type'] ?? $imagem['mimeType'] ?? $mime_entrada ) : $mime_entrada;

		if ( ! is_string( $dados_base64 ) || $dados_base64 === '' ) {
			return array(
				'ok'            => false,
				'imagem_base64' => null,
				'mime_type'     => null,
				'mensagem'      => 'Resposta da IA não trouxe imagem no formato esperado — revisar o parsing contra a documentação atual da Interactions API.',
			);
		}

		Atelie_Ai_Custo_Tracker::registrar_fixo( 'editar_imagem', Atelie_Ai_Custo_Tracker::custo_por_imagem() );

		return array(
			'ok'            => true,
			'imagem_base64' => $dados_base64,
			'mime_type'     => (string) $mime_saida,
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
		$prompt = 'Você ajuda um ateliê de tricô, crochê e amigurumis a cadastrar produtos artesanais. '
			. 'Com base nas fotos anexadas' . ( $receita_texto ? ' e na receita/padrão a seguir' : '' ) . ', '
			. 'sugira os campos do produto. Responda SOMENTE um objeto JSON com exatamente estas chaves: '
			. '"titulo" (curto, atrativo), "descricao" (2-3 frases, tom acolhedor), '
			. '"categoria" (uma palavra ou expressão curta, ex: Amigurumis, Crochê, Tricô), '
			. '"material_tecnica" (materiais e técnica usados, vazio se não for possível saber).';

		if ( $receita_texto ) {
			$prompt .= "\n\nReceita/padrão anexada:\n" . $receita_texto;
		}

		return $prompt;
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

	private function mime_type( string $path ): string {
		$ext = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		return match ( $ext ) {
			'png' => 'image/png',
			'webp' => 'image/webp',
			'gif' => 'image/gif',
			default => 'image/jpeg',
		};
	}
}
