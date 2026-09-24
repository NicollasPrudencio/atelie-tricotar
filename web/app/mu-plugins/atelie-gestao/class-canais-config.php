<?php
/**
 * Custos do ateliê por canal de venda (feira, site, venda individual) e
 * rateios (parte de cada venda separada pro fundo de reposição, margem do
 * ateliê...) — e o cálculo do preço final POR CANAL a partir do valor da
 * artesã (custo de produção do orçamento).
 *
 * Fórmula (ver docs/decisions/0003): itens fixos e de evento somam por cima;
 * itens percentuais e rateios são percentual sobre o PREÇO FINAL, então
 *   preço = (valor da artesã + fixos) / (1 − Σ percentuais)
 * e o que sobra pra cada linha é preço × seu percentual.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Canais_Config {

	private const OPCAO = 'atelie_gestao_canais';

	/** Percentual total máximo (canal + rateios) — acima disso o preço explode/fica sem sentido. */
	public const PERCENTUAL_MAXIMO = 90.0;

	public const TIPOS = array(
		'fixo'       => 'Valor fixo por peça (R$)',
		'evento'     => 'Custo do evento ÷ peças esperadas (R$)',
		'percentual' => 'Percentual do preço final (%)',
	);

	public const DESTINOS = array(
		'fundo' => 'Fundo de reposição',
		'caixa' => 'Caixa do ateliê',
	);

	/**
	 * @return array<string, string> slug => nome
	 */
	public static function canais_padrao(): array {
		return array(
			'feira'      => 'Feira',
			'site'       => 'Site',
			'individual' => 'Venda individual',
		);
	}

	/**
	 * @return array{canais: array<string, array{nome: string, itens: array<int, array{nome: string, tipo: string, valor: float, pecas: int}>}>, rateios: array<int, array{nome: string, percentual: float, destino: string}>}
	 */
	public static function obter(): array {
		$salvo = get_option( self::OPCAO, array() );
		$salvo = is_array( $salvo ) ? $salvo : array();

		$canais = array();
		foreach ( self::canais_padrao() as $slug => $nome ) {
			$itens = array();
			foreach ( (array) ( $salvo['canais'][ $slug ]['itens'] ?? array() ) as $item ) {
				$tipo      = isset( self::TIPOS[ $item['tipo'] ?? '' ] ) ? (string) $item['tipo'] : 'fixo';
				$nome_item = trim( (string) ( $item['nome'] ?? '' ) );
				if ( $nome_item === '' ) {
					continue;
				}
				$itens[] = array(
					'nome'  => $nome_item,
					'tipo'  => $tipo,
					'valor' => max( 0.0, (float) ( $item['valor'] ?? 0 ) ),
					'pecas' => max( 1, (int) ( $item['pecas'] ?? 1 ) ),
				);
			}
			$canais[ $slug ] = array(
				'nome'  => $nome,
				'itens' => $itens,
			);
		}

		$rateios = array();
		foreach ( (array) ( $salvo['rateios'] ?? array() ) as $r ) {
			$nome_rateio = trim( (string) ( $r['nome'] ?? '' ) );
			if ( $nome_rateio === '' ) {
				continue;
			}
			$rateios[] = array(
				'nome'       => $nome_rateio,
				'percentual' => max( 0.0, (float) ( $r['percentual'] ?? 0 ) ),
				'destino'    => isset( self::DESTINOS[ $r['destino'] ?? '' ] ) ? (string) $r['destino'] : 'caixa',
			);
		}

		return array(
			'canais'  => $canais,
			'rateios' => $rateios,
		);
	}

	/**
	 * @param array<string, mixed> $config no formato de obter()
	 */
	public static function salvar( array $config ): void {
		update_option( self::OPCAO, $config, false );
	}

	/**
	 * Maior soma de percentuais entre os canais (canal + rateios) — pra validar antes de salvar.
	 *
	 * @param array<string, mixed> $config
	 */
	public static function percentual_maximo_usado( array $config ): float {
		$rateios = 0.0;
		foreach ( (array) ( $config['rateios'] ?? array() ) as $r ) {
			$rateios += (float) ( $r['percentual'] ?? 0 );
		}

		$maior = 0.0;
		foreach ( (array) ( $config['canais'] ?? array() ) as $canal ) {
			$soma = $rateios;
			foreach ( (array) ( $canal['itens'] ?? array() ) as $item ) {
				if ( ( $item['tipo'] ?? '' ) === 'percentual' ) {
					$soma += (float) $item['valor'];
				}
			}
			$maior = max( $maior, $soma );
		}

		return $maior;
	}

	/**
	 * Preço final e divisão do dinheiro em cada canal, dado o valor da artesã.
	 *
	 * @return array<string, array{nome: string, valido: bool, preco: float, artesa: float, atelie: float, linhas: array<int, array{nome: string, valor: float, grupo: string, destino: string}>}>
	 */
	public static function calcular( float $valor_artesa ): array {
		$config     = self::obter();
		$pct_rateio = 0.0;
		foreach ( $config['rateios'] as $r ) {
			$pct_rateio += $r['percentual'];
		}

		$resultado = array();
		foreach ( $config['canais'] as $slug => $canal ) {
			$fixos    = 0.0;
			$pct      = $pct_rateio;
			$linhas   = array();
			$pcts_itn = array();

			foreach ( $canal['itens'] as $item ) {
				if ( $item['tipo'] === 'percentual' ) {
					$pct       += $item['valor'];
					$pcts_itn[] = $item;
					continue;
				}
				$valor    = $item['tipo'] === 'evento' ? $item['valor'] / max( 1, $item['pecas'] ) : $item['valor'];
				$fixos   += $valor;
				$linhas[] = array(
					'nome'    => $item['nome'],
					'valor'   => round( $valor, 2 ),
					'grupo'   => 'custo',
					'destino' => '',
				);
			}

			if ( $pct >= self::PERCENTUAL_MAXIMO ) {
				$resultado[ $slug ] = array(
					'nome'   => $canal['nome'],
					'valido' => false,
					'preco'  => 0.0,
					'artesa' => $valor_artesa,
					'atelie' => 0.0,
					'linhas' => array(),
				);
				continue;
			}

			$preco = round( ( $valor_artesa + $fixos ) / ( 1 - $pct / 100 ), 2 );

			foreach ( $pcts_itn as $item ) {
				$linhas[] = array(
					'nome'    => $item['nome'] . ' (' . self::formatar_pct( $item['valor'] ) . ')',
					'valor'   => round( $preco * $item['valor'] / 100, 2 ),
					'grupo'   => 'taxa',
					'destino' => '',
				);
			}
			foreach ( $config['rateios'] as $r ) {
				$linhas[] = array(
					'nome'    => $r['nome'] . ' (' . self::formatar_pct( $r['percentual'] ) . ')',
					'valor'   => round( $preco * $r['percentual'] / 100, 2 ),
					'grupo'   => 'rateio',
					'destino' => $r['destino'],
				);
			}

			$resultado[ $slug ] = array(
				'nome'   => $canal['nome'],
				'valido' => true,
				'preco'  => $preco,
				'artesa' => round( $valor_artesa, 2 ),
				'atelie' => round( $preco - $valor_artesa, 2 ),
				'linhas' => $linhas,
			);
		}

		return $resultado;
	}

	private static function formatar_pct( float $pct ): string {
		return rtrim( rtrim( number_format( $pct, 2, ',', '' ), '0' ), ',' ) . '%';
	}
}
