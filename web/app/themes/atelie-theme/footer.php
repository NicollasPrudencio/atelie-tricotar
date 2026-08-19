<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
</main>

<footer class="site-footer">
    <div class="site-container site-footer__grid">
        <div class="site-footer__coluna">
            <p class="site-footer__marca"><?php bloginfo('name'); ?></p>
            <p><?php esc_html_e('Peças em tricô, crochê e amigurumi feitas à mão, com carinho, para todo o Brasil.', 'atelie-theme'); ?></p>
        </div>

        <div class="site-footer__coluna">
            <h3><?php esc_html_e('Navegue', 'atelie-theme'); ?></h3>
            <ul>
                <li><a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><?php esc_html_e('Loja', 'atelie-theme'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/portfolio/')); ?>"><?php esc_html_e('Portfólio', 'atelie-theme'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/orcamento-personalizado/')); ?>"><?php esc_html_e('Orçamento personalizado', 'atelie-theme'); ?></a></li>
            </ul>
        </div>

        <div class="site-footer__coluna">
            <h3><?php esc_html_e('Institucional', 'atelie-theme'); ?></h3>
            <ul>
                <li><a href="<?php echo esc_url(home_url('/politica-de-privacidade/')); ?>"><?php esc_html_e('Política de privacidade', 'atelie-theme'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/termos-de-uso/')); ?>"><?php esc_html_e('Termos de uso', 'atelie-theme'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/trocas-e-devolucoes/')); ?>"><?php esc_html_e('Trocas e devoluções', 'atelie-theme'); ?></a></li>
            </ul>
        </div>
    </div>

    <div class="site-footer__base">
        &copy; <?php echo esc_html(gmdate('Y')); ?> <?php bloginfo('name'); ?> — <?php esc_html_e('feito à mão, vendido com carinho.', 'atelie-theme'); ?>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
