<?php
/**
 * Implementacao falsa — usada quando AI_MOCK_MODE=true (dev local e CI),
 * pra ninguem gastar credito de API de verdade testando o painel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Ai_Vision_Service_Mock implements Atelie_Ai_Vision_Service_Interface {

	public function analisar( array $imagens_paths, ?string $receita_texto = null ): array {
		// pequena pausa pra simular latencia real, ajuda a testar o estado "Analisando..." na tela
		usleep( 600000 );

		$qtd_fotos   = count( $imagens_paths );
		$tem_receita = ! empty( $receita_texto );

		return array(
			'titulo'           => sprintf( '[MOCK] Peça artesanal (%d foto%s)', $qtd_fotos, $qtd_fotos === 1 ? '' : 's' ),
			'descricao'        => $tem_receita
				? '[MOCK] Descrição gerada a partir da foto e da receita anexada. Peça feita à mão, com atenção aos detalhes.'
				: '[MOCK] Descrição gerada a partir da foto. Peça feita à mão, com atenção aos detalhes.',
			'categoria'        => 'Amigurumis',
			'material_tecnica' => $tem_receita ? '[MOCK] Fio de algodão, crochê, ponto baixo.' : '',
		);
	}

	public function sugerirCase( array $imagens_paths, ?string $relato = null ): array {
		usleep( 600000 );

		$qtd_fotos = count( $imagens_paths );

		return array(
			'titulo'    => sprintf( '[MOCK] Trabalho sob encomenda (%d foto%s)', $qtd_fotos, $qtd_fotos === 1 ? '' : 's' ),
			'descricao' => $relato
				? '[MOCK] Case gerado a partir do relato da artesã: ' . mb_substr( $relato, 0, 80 ) . '…'
				: '[MOCK] Trabalho feito sob encomenda, com atenção aos detalhes do pedido.',
		);
	}

	public function editarImagem( string $imagem_path, string $prompt ): array {
		usleep( 600000 );

		if ( ! is_readable( $imagem_path ) ) {
			return array(
				'ok'            => false,
				'imagem_base64' => null,
				'mime_type'     => null,
				'mensagem'      => 'Foto não encontrada.',
			);
		}

		// [MOCK] devolve a mesma foto sem alterar — só pra testar o fluxo (escolher, editar,
		// trocar no formulário) sem gastar API de verdade nem precisar de faturamento ativo.
		return array(
			'ok'            => true,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- leitura de arquivo local (upload do WP), nao URL remota.
			'imagem_base64' => base64_encode( (string) file_get_contents( $imagem_path ) ),
			'mime_type'     => 'image/jpeg',
			'mensagem'      => '[MOCK] Imagem "editada" (modo simulado, imagem não foi alterada de verdade).',
		);
	}

	public function testarConexao(): array {
		return array(
			'ok'       => true,
			'mensagem' => 'Modo simulado (AI_MOCK_MODE) — sempre disponível.',
		);
	}

	public function avaliarTexto( string $titulo, string $descricao, string $tipo_objeto ): array {
		// [MOCK] simula reprovar qualquer texto que contenha a palavra "artesã" no corpo,
		// só pra dar pra testar o fluxo de bloqueio localmente sem gastar API de verdade.
		$reprovar = stripos( $descricao, 'artesã' ) !== false || stripos( $titulo, 'artesã' ) !== false;

		if ( ! $reprovar ) {
			return array(
				'ok'                 => true,
				'problemas'          => array(),
				'titulo_sugerido'    => null,
				'descricao_sugerida' => null,
			);
		}

		return array(
			'ok'                 => false,
			'problemas'          => array( '[MOCK] Texto menciona a artesã — o texto deve falar da peça, não de quem fez.' ),
			'titulo_sugerido'    => $titulo,
			'descricao_sugerida' => '[MOCK] Descrição corrigida, sem mencionar a artesã.',
		);
	}

	public function gerarAnuncio( string $titulo, string $descricao, string $tipo_objeto ): array {
		usleep( 600000 );

		return array(
			'meta'   => array(
				'texto_principal' => "[MOCK] Peça feita à mão, com carinho em cada detalhe — conheça \"{$titulo}\" e encontre a sua.",
				'titulo'          => "[MOCK] {$titulo} — feito à mão",
			),
			'tiktok' => array(
				'legenda' => "[MOCK] Olha que fofura ✨ \"{$titulo}\" tá esperando por você #feitoamao #artesanato",
			),
		);
	}
}
