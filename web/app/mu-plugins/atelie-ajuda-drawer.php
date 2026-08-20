<?php
/**
 * Plugin Name: Atelie Ajuda Drawer
 * Description: Botao de ajuda contextual ("?") que cada tela do painel chama pra abrir um painel deslizante com dicas curtas + link pro manual publicado.
 * Version: 1.0.0
 */

/**
 * Mu-plugin (sempre ativo) porque é usado tanto por `atelie-produto-ia` quanto
 * por `atelie-faturamento` — evita criar uma dependência forte entre os dois
 * plugins só por causa de um componente de UI compartilhado.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('ATELIE_DOCS_BASE_URL')) {
    define('ATELIE_DOCS_BASE_URL', 'https://docs.atelietricotar.com.br');
}

class Atelie_Ajuda_Drawer
{
    private static bool $assets_registrados = false;

    /**
     * @param array<int, string> $dicas Frases curtas (aceitam HTML simples via wp_kses_post).
     * @param string $caminhoDocs Caminho relativo no site de documentação, ex. "/novo-produto/".
     */
    public static function render(string $titulo, array $dicas, string $caminhoDocs): void
    {
        self::registrar_assets();

        $id = 'atelie-ajuda-' . sanitize_title($titulo);
        $url_docs = rtrim(ATELIE_DOCS_BASE_URL, '/') . '/' . ltrim($caminhoDocs, '/');
        ?>
        <button
            type="button"
            class="atelie-ajuda-botao"
            data-atelie-ajuda-abrir="<?php echo esc_attr($id); ?>"
            aria-label="<?php echo esc_attr('Ajuda: ' . $titulo); ?>"
        >?</button>

        <div class="atelie-ajuda-overlay" data-atelie-ajuda="<?php echo esc_attr($id); ?>" hidden>
            <aside class="atelie-ajuda-painel" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($id); ?>-titulo">
                <div class="atelie-ajuda-painel-cabecalho">
                    <h2 id="<?php echo esc_attr($id); ?>-titulo"><?php echo esc_html($titulo); ?></h2>
                    <button type="button" class="atelie-ajuda-fechar" data-atelie-ajuda-fechar aria-label="Fechar ajuda">&times;</button>
                </div>
                <ul class="atelie-ajuda-lista">
                    <?php foreach ($dicas as $dica) : ?>
                        <li><?php echo wp_kses_post($dica); ?></li>
                    <?php endforeach; ?>
                </ul>
                <a class="atelie-ajuda-link-completo" href="<?php echo esc_url($url_docs); ?>" target="_blank" rel="noopener noreferrer">
                    Ver guia completo →
                </a>
            </aside>
        </div>
        <?php
    }

    private static function registrar_assets(): void
    {
        if (self::$assets_registrados) {
            return;
        }
        self::$assets_registrados = true;

        wp_enqueue_style(
            'atelie-ajuda-drawer',
            content_url('mu-plugins/assets/ajuda-drawer.css'),
            [],
            '1.0.0'
        );
        wp_enqueue_script(
            'atelie-ajuda-drawer',
            content_url('mu-plugins/assets/ajuda-drawer.js'),
            [],
            '1.0.0',
            true
        );
    }
}
