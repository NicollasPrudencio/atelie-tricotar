<?php
/**
 * Plugin Name: Atelie Checkout Campos BR
 * Description: Campo de CPF no checkout em blocos do WooCommerce, salvo nas mesmas chaves de meta que o Melhor Envio e o Bling esperam.
 * Version: 1.0.0
 */

/**
 * O plugin "woocommerce-extra-checkout-fields-for-brazil" (instalado pro Melhor Envio) foi
 * feito pro checkout classico e nao tem efeito nenhum no checkout em blocos que essa loja usa
 * — nenhum CPF era capturado de verdade, o que travava a geracao de etiqueta ("O documento do
 * remetente e/ou destinatario e obrigatorio") e vai travar a emissao de nota fiscal (Bling)
 * mais pra frente. Registra o campo direto via API de campos adicionais do WooCommerce Blocks,
 * salvando nas MESMAS chaves (_billing_cpf, _billing_persontype) que o Melhor Envio ja le em
 * MelhorEnvio\Services\BuyerService::getDataBuyerByOrderId() — nao precisa mudar nada do lado
 * deles. So CPF (pessoa fisica): artesanato vendido a consumidor final nao precisa de CNPJ do
 * comprador; se um dia fizer falta CNPJ, revisitar aqui.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    if (!function_exists('woocommerce_register_additional_checkout_field')) {
        return;
    }

    woocommerce_register_additional_checkout_field([
        'id' => 'atelie/cpf',
        'label' => 'CPF',
        'location' => 'order',
        'type' => 'text',
        'required' => true,
        'attributes' => [
            'maxLength' => 14,
            'autocomplete' => 'off',
        ],
        'sanitize_callback' => function ($valor) {
            return preg_replace('/\D/', '', (string) $valor);
        },
        'validate_callback' => function ($valor) {
            if (!atelie_cpf_valido((string) $valor)) {
                return new WP_Error('cpf_invalido', 'Esse CPF não parece válido — confira os números.');
            }
            return true;
        },
    ]);
});

/**
 * Verificação de dígitos do CPF (mod 11 padrão) — recusa sequências repetidas
 * (ex.: 111.111.111-11) que passariam na conta mas nunca são CPF real.
 */
function atelie_cpf_valido(string $cpf): bool
{
    $cpf = preg_replace('/\D/', '', $cpf);

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($posicao = 9; $posicao <= 10; $posicao++) {
        $soma = 0;
        for ($indice = 0; $indice < $posicao; $indice++) {
            $soma += (int) $cpf[$indice] * (($posicao + 1) - $indice);
        }
        $digito = (($soma * 10) % 11) % 10;
        if ($digito !== (int) $cpf[$posicao]) {
            return false;
        }
    }

    return true;
}

/**
 * Espelha o CPF salvo pelo campo de blocos nas chaves legadas que o Melhor Envio
 * (e, futuramente, qualquer coisa que reaproveite o mesmo padrão) já sabe ler.
 */
add_action('woocommerce_set_additional_field_value', function (string $chave, $valor, string $grupo, $objeto): void {
    if ($chave !== 'atelie/cpf' || !($objeto instanceof WC_Order)) {
        return;
    }

    $objeto->update_meta_data('_billing_cpf', $valor);
    $objeto->update_meta_data('_billing_persontype', '1');
    // Salva na hora — nao dá pra confiar que algo mais adiante no fluxo de
    // checkout vai persistir isso por conta própria.
    $objeto->save_meta_data();
}, 10, 4);
