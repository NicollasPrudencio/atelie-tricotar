<?php
/**
 * Tela "Importar do Drive" — conectar a conta (uma vez, so administrador) e
 * importar fotos de uma pasta compartilhada (Vendedora tambem usa, uma vez
 * ja conectado). Convencao: cada subpasta dentro da pasta compartilhada vira
 * um produto candidato, entregue pro mesmo pipeline de "Criar em massa"
 * (Atelie_Lote_Controller::criar_lote_de_grupos).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Drive_Admin_Page
{
    private const SLUG = 'atelie-importar-drive';

    public function registrar(): void
    {
        add_action('admin_menu', [$this, 'adicionar_menu']);
        add_action('admin_post_atelie_drive_oauth_callback', [$this, 'oauth_callback']);
        add_action('admin_post_atelie_drive_conectar', [$this, 'iniciar_conexao']);
        add_action('admin_post_atelie_drive_desconectar', [$this, 'desconectar']);
        add_action('admin_post_atelie_drive_importar', [$this, 'importar']);
    }

    public function adicionar_menu(): void
    {
        add_submenu_page(
            'atelie-novo-produto',
            'Importar do Drive',
            'Importar do Drive',
            'edit_products',
            self::SLUG,
            [$this, 'renderizar']
        );
    }

    public function renderizar(): void
    {
        if (isset($_GET['conectado'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Google Drive conectado.</p></div>';
        }
        if (isset($_GET['desconectado'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Google Drive desconectado.</p></div>';
        }
        if (isset($_GET['drive_erro'])) {
            $codigo = sanitize_key(wp_unslash($_GET['drive_erro']));
            echo '<div class="notice notice-error"><p>' . esc_html($this->mensagem_erro($codigo)) . '</p></div>';
        }

        $conectado = Atelie_Drive_Config::conectado();
        ?>
        <div class="wrap atelie-novo-produto">
            <h1>Importar do Drive</h1>
            <p>Cada subpasta dentro da pasta compartilhada vira um produto candidato — mesma
               tela de revisão da criação em massa.</p>

            <?php if (!$conectado) : ?>
                <div class="atelie-card">
                    <h2>Conectar ao Google Drive</h2>
                    <?php if (current_user_can('manage_options')) : ?>
                        <p>Peça pra artesã compartilhar a pasta com fotos usando o e-mail da conta
                           Google que você vai conectar aqui — a conexão é feita uma vez só.</p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="atelie_drive_conectar">
                            <?php wp_nonce_field('atelie_drive_conectar', 'atelie_drive_conectar_nonce'); ?>
                            <button type="submit" class="button button-primary button-hero">Conectar ao Google Drive</button>
                        </form>
                    <?php else : ?>
                        <p class="atelie-status atelie-status-erro">O Google Drive ainda não foi conectado. Peça pro administrador do site conectar antes de importar.</p>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="atelie-card">
                    <p class="atelie-status atelie-status-ok">✅ Conectado como <strong><?php echo esc_html(Atelie_Drive_Config::conta_email()); ?></strong></p>
                    <?php if (current_user_can('manage_options')) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Desconectar o Google Drive? Você vai precisar autorizar de novo pra importar depois.');">
                            <input type="hidden" name="action" value="atelie_drive_desconectar">
                            <?php wp_nonce_field('atelie_drive_desconectar', 'atelie_drive_desconectar_nonce'); ?>
                            <button type="submit" class="button-link" style="color:#a8434b;">Desconectar</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="atelie-card">
                    <h2>Importar pasta</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="atelie_drive_importar">
                        <?php wp_nonce_field('atelie_drive_importar', 'atelie_drive_importar_nonce'); ?>
                        <p>
                            <label for="atelie-drive-pasta">Link da pasta compartilhada</label><br>
                            <input type="text" name="pasta" id="atelie-drive-pasta" required
                                   placeholder="https://drive.google.com/drive/folders/..."
                                   style="width:100%;max-width:480px;">
                        </p>
                        <p>
                            <button type="submit" class="button button-primary button-hero">Importar</button>
                        </p>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function mensagem_erro(string $codigo): string
    {
        $limite = isset($_GET['limite']) ? absint($_GET['limite']) : 0;

        return match ($codigo) {
            'consentimento_negado' => 'A autorização foi cancelada no Google. Tente conectar de novo se foi sem querer.',
            'sessao_invalida' => 'A sessão de autorização expirou ou é inválida. Tente conectar de novo.',
            'sem_codigo' => 'O Google não retornou os dados esperados. Tente conectar de novo.',
            'nao_conectado' => 'Conecte o Google Drive antes de importar.',
            'link_invalido' => 'Não reconheci esse link — copie o link da pasta compartilhada direto do Google Drive.',
            'pasta_vazia' => 'Essa pasta não tem nenhuma subpasta dentro dela — confirme se é a pasta certa (cada subpasta vira um produto).',
            'sem_fotos_validas' => 'Não encontrei nenhuma foto válida nas subpastas dessa pasta.',
            'limite' => "Essa pasta tem mais subpastas do que o limite de {$limite} por lote — divida em pastas menores.",
            default => 'Não deu pra importar agora: ' . $codigo,
        };
    }

    public function iniciar_conexao(): void
    {
        if (
            !current_user_can('manage_options')
            || !isset($_POST['atelie_drive_conectar_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_drive_conectar_nonce'])), 'atelie_drive_conectar')
        ) {
            wp_die('Ação não permitida.');
        }

        // "state" e a protecao contra CSRF do proprio fluxo OAuth — o Google
        // devolve esse valor de volta no callback, comparamos pra confirmar
        // que a resposta corresponde a uma conexao que a gente mesmo iniciou.
        $state = wp_generate_password(32, false);
        set_transient('atelie_drive_oauth_state_' . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS);

        wp_redirect(add_query_arg('state', $state, Atelie_Google_Drive_Service::url_autorizacao()));
        exit;
    }

    public function oauth_callback(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Ação não permitida.');
        }

        $chave_state = 'atelie_drive_oauth_state_' . get_current_user_id();
        $state_esperado = get_transient($chave_state);
        $state_recebido = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        delete_transient($chave_state);

        if ($state_esperado === false || $state_recebido === '' || !hash_equals((string) $state_esperado, $state_recebido)) {
            wp_safe_redirect(add_query_arg('drive_erro', 'sessao_invalida', admin_url('admin.php?page=' . self::SLUG)));
            exit;
        }

        if (isset($_GET['error'])) {
            wp_safe_redirect(add_query_arg('drive_erro', 'consentimento_negado', admin_url('admin.php?page=' . self::SLUG)));
            exit;
        }

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        if ($code === '') {
            wp_safe_redirect(add_query_arg('drive_erro', 'sem_codigo', admin_url('admin.php?page=' . self::SLUG)));
            exit;
        }

        $resultado = Atelie_Google_Drive_Service::trocar_codigo_por_token($code);

        if (!$resultado['ok']) {
            wp_safe_redirect(add_query_arg('drive_erro', rawurlencode($resultado['mensagem']), admin_url('admin.php?page=' . self::SLUG)));
            exit;
        }

        Atelie_Drive_Config::salvar_conexao($resultado['refresh_token'], $resultado['email']);

        wp_safe_redirect(add_query_arg('conectado', '1', admin_url('admin.php?page=' . self::SLUG)));
        exit;
    }

    public function desconectar(): void
    {
        if (
            !current_user_can('manage_options')
            || !isset($_POST['atelie_drive_desconectar_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_drive_desconectar_nonce'])), 'atelie_drive_desconectar')
        ) {
            wp_die('Ação não permitida.');
        }

        Atelie_Drive_Config::desconectar();

        wp_safe_redirect(add_query_arg('desconectado', '1', admin_url('admin.php?page=' . self::SLUG)));
        exit;
    }

    public function importar(): void
    {
        if (
            !current_user_can('edit_products')
            || !isset($_POST['atelie_drive_importar_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelie_drive_importar_nonce'])), 'atelie_drive_importar')
        ) {
            wp_die('Ação não permitida.');
        }

        if (!Atelie_Drive_Config::conectado()) {
            wp_safe_redirect(add_query_arg('drive_erro', 'nao_conectado', admin_url('admin.php?page=' . self::SLUG)));
            exit;
        }

        $link = isset($_POST['pasta']) ? sanitize_text_field(wp_unslash($_POST['pasta'])) : '';
        $pasta_id = Atelie_Google_Drive_Service::extrair_id_da_pasta($link);

        if ($pasta_id === null) {
            wp_safe_redirect(add_query_arg('drive_erro', 'link_invalido', admin_url('admin.php?page=' . self::SLUG)));
            exit;
        }

        $subpastas = Atelie_Google_Drive_Service::listar_subpastas($pasta_id);

        if (empty($subpastas)) {
            wp_safe_redirect(add_query_arg('drive_erro', 'pasta_vazia', admin_url('admin.php?page=' . self::SLUG)));
            exit;
        }

        $lote_controller = new Atelie_Lote_Controller();
        $limite = $lote_controller->limite_por_lote();

        if (count($subpastas) > $limite) {
            wp_safe_redirect(add_query_arg(['drive_erro' => 'limite', 'limite' => $limite], admin_url('admin.php?page=' . self::SLUG)));
            exit;
        }

        $grupos = [];
        foreach ($subpastas as $subpasta) {
            $imagens = Atelie_Google_Drive_Service::listar_imagens($subpasta['id']);
            $ids_do_grupo = [];

            foreach ($imagens as $imagem) {
                $anexo_id = $this->baixar_e_salvar_imagem($imagem, $subpasta['name'] ?? 'drive');
                if ($anexo_id !== null) {
                    $ids_do_grupo[] = $anexo_id;
                }
            }

            if (!empty($ids_do_grupo)) {
                $grupos[] = $ids_do_grupo;
            }
        }

        if (empty($grupos)) {
            wp_safe_redirect(add_query_arg('drive_erro', 'sem_fotos_validas', admin_url('admin.php?page=' . self::SLUG)));
            exit;
        }

        $lote_id = $lote_controller->criar_lote_de_grupos($grupos);

        wp_safe_redirect(admin_url('admin.php?page=atelie-revisar-lote&lote=' . rawurlencode($lote_id)));
        exit;
    }

    /**
     * @param array{id: string, name: string, mimeType?: string} $imagem
     */
    private function baixar_e_salvar_imagem(array $imagem, string $nomeSubpasta): ?int
    {
        $dados_binarios = Atelie_Google_Drive_Service::baixar_arquivo($imagem['id']);
        if ($dados_binarios === null) {
            return null;
        }

        $mime_type = $imagem['mimeType'] ?? 'image/jpeg';
        $extensao = match ($mime_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $nome_arquivo = sanitize_file_name($nomeSubpasta . '-' . ($imagem['name'] ?? 'foto') . '.' . $extensao);

        $upload = wp_upload_bits($nome_arquivo, null, $dados_binarios);
        if (!empty($upload['error'])) {
            return null;
        }

        $anexo_id = wp_insert_attachment([
            'post_mime_type' => $mime_type,
            'post_title' => sanitize_file_name($nome_arquivo),
            'post_status' => 'inherit',
        ], $upload['file']);

        if (is_wp_error($anexo_id) || !$anexo_id) {
            return null;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadados = wp_generate_attachment_metadata($anexo_id, $upload['file']);
        wp_update_attachment_metadata($anexo_id, $metadados);

        return $anexo_id;
    }
}
