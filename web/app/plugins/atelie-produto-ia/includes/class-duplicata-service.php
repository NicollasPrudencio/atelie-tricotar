<?php
/**
 * Deteccao de foto duplicada na Biblioteca de Midia — compara pelo hash do
 * arquivo ORIGINAL (wp_get_original_image_path(), que o WordPress preserva
 * intacto mesmo quando cria uma versao redimensionada automatica pra fotos
 * grandes — sufixo "-scaled" — entao o hash nao muda so por causa disso).
 * Cada foto so e "hasheada" uma vez (guardado em post meta) — nem toda vez
 * que e comparada, nem em lote pra biblioteca inteira de uma vez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atelie_Duplicata_Service {

	private const META_HASH = '_atelie_hash_arquivo';

	public static function registrar(): void {
		// add_attachment ja roda com o arquivo movido pro lugar final —
		// indexa toda foto nova sozinho, sem precisar de nenhum backfill
		// recorrente pra manter em dia.
		add_action( 'add_attachment', array( __CLASS__, 'garantir_hash' ) );
	}

	public static function garantir_hash( int $attachment_id ): ?string {
		$existente = get_post_meta( $attachment_id, self::META_HASH, true );
		if ( is_string( $existente ) && $existente !== '' ) {
			return $existente;
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}

		$caminho = wp_get_original_image_path( $attachment_id );
		if ( ! $caminho || ! file_exists( $caminho ) ) {
			$caminho = get_attached_file( $attachment_id );
		}
		if ( ! $caminho || ! file_exists( $caminho ) ) {
			return null;
		}

		$hash = hash_file( 'sha256', $caminho );
		if ( $hash === false ) {
			return null;
		}

		update_post_meta( $attachment_id, self::META_HASH, $hash );
		return $hash;
	}

	/**
	 * @return array{id: int, titulo: string, data: string, thumbnail: string}|null
	 */
	public static function encontrar_duplicata( int $attachment_id ): ?array {
		$hash = self::garantir_hash( $attachment_id );
		if ( $hash === null ) {
			return null;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- busca exata por meta_value (hash); sem wrapper de query nativo pra isso, e nao guarda cache proprio porque o resultado muda a cada foto nova indexada.
		$outro_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND pm.meta_value = %s AND pm.post_id != %d AND p.post_type = 'attachment'
				ORDER BY pm.post_id ASC
				LIMIT 1",
				self::META_HASH,
				$hash,
				$attachment_id
			)
		);

		if ( ! $outro_id ) {
			return null;
		}

		$outro_id = (int) $outro_id;

		return array(
			'id'        => $outro_id,
			'titulo'    => get_the_title( $outro_id ),
			'data'      => get_the_date( 'd/m/Y', $outro_id ),
			'thumbnail' => (string) wp_get_attachment_image_url( $outro_id, 'thumbnail' ),
		);
	}
}
