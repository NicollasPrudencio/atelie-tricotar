<?php
/**
 * Fonte unica de verdade pra config da IA de visao (provedor + chave) e pro
 * status da ultima verificacao de conexao. Prioridade: opcao salva pela tela
 * "Configurar IA" (editavel por quem tem acesso ao painel) > .env (usado no
 * dev local/CI, ninguem precisa configurar nada pra rodar o projeto do zero).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use function Env\env;

class Atelie_Ai_Config {

	private const OPCAO_PROVEDOR = 'atelie_ai_provider';
	private const OPCAO_CHAVE    = 'atelie_ai_api_key';
	private const OPCAO_STATUS   = 'atelie_ai_status';

	public static function obter_provedor(): string {
		$opcao = trim( (string) get_option( self::OPCAO_PROVEDOR, '' ) );
		if ( $opcao !== '' ) {
			return $opcao;
		}

		return env( 'AI_VISION_PROVIDER' ) ?: 'gemini';
	}

	public static function obter_chave(): string {
		$opcao = trim( (string) get_option( self::OPCAO_CHAVE, '' ) );
		if ( $opcao !== '' ) {
			return $opcao;
		}

		return (string) env( 'AI_VISION_API_KEY' );
	}

	public static function em_modo_mock(): bool {
		return filter_var( env( 'AI_MOCK_MODE' ), FILTER_VALIDATE_BOOLEAN );
	}

	public static function salvar( string $provedor, string $chave ): void {
		update_option( self::OPCAO_PROVEDOR, $provedor, false );

		// Campo de chave vem mascarado ("chave já configurada") quando a pessoa nao
		// quis trocar — nesse caso nao sobrescreve o que ja estava salvo.
		if ( $chave !== '' && $chave !== self::mascara_placeholder() ) {
			update_option( self::OPCAO_CHAVE, $chave, false );
		}
	}

	public static function mascara_placeholder(): string {
		return '••••••••••••';
	}

	public static function chave_mascarada(): string {
		$chave = self::obter_chave();
		if ( $chave === '' ) {
			return '';
		}

		return self::mascara_placeholder() . substr( $chave, -4 );
	}

	/**
	 * @return array{ok: bool, mensagem: string, verificado_em: int}
	 */
	public static function obter_status(): array {
		$status = get_option( self::OPCAO_STATUS, null );
		if ( is_array( $status ) && isset( $status['ok'], $status['mensagem'], $status['verificado_em'] ) ) {
			return $status;
		}

		return array(
			'ok'            => false,
			'mensagem'      => 'Ainda não testado.',
			'verificado_em' => 0,
		);
	}

	public static function esta_disponivel(): bool {
		return self::obter_status()['ok'] === true;
	}

	/**
	 * Faz uma chamada real (leve, sem imagem) pro provedor configurado e
	 * grava o resultado — usado pela tela de configuração (botão "Testar
	 * conexão") e pela verificação automática diária (ver atelie-produto-ia.php).
	 *
	 * @return array{ok: bool, mensagem: string, verificado_em: int}
	 */
	public static function testar_conexao(): array {
		try {
			$resultado = Atelie_Ai_Vision_Service_Factory::criar()->testarConexao();
		} catch ( Throwable $e ) {
			$resultado = array(
				'ok'       => false,
				'mensagem' => $e->getMessage(),
			);
		}

		$status = array(
			'ok'            => (bool) $resultado['ok'],
			'mensagem'      => (string) $resultado['mensagem'],
			'verificado_em' => time(),
		);

		update_option( self::OPCAO_STATUS, $status, false );

		return $status;
	}

	/**
	 * Atributo `data-tooltip` (ver .atelie-tooltip no admin.css) pra qualquer
	 * botão de IA desabilitado por indisponibilidade — mesma mensagem em
	 * todo lugar do painel que dispara IA (Novo Produto, Novo Case, Criar em
	 * massa), pra quem usa o painel nunca ver um botão cinza sem explicação.
	 *
	 * @param array{ok: bool, mensagem: string, verificado_em: int} $status
	 */
	public static function atributo_tooltip_indisponivel( array $status ): string {
		$mensagem = current_user_can( 'manage_options' )
			? sprintf( 'IA indisponível: %s Vá em "Configurar IA" pra corrigir.', $status['mensagem'] )
			: 'IA indisponível no momento. Avise o administrador do site — ou use "Preencher manualmente".';

		return 'data-tooltip="' . esc_attr( $mensagem ) . '"';
	}
}
