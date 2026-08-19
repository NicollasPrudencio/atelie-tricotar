<?php
/**
 * Agrega receita e custos de um periodo — a base numerica da tela
 * "Faturamento". Cada metodo busca de uma fonte diferente (ver plano
 * "Faturamento — receita vs custos num so lugar" pra detalhe de onde cada
 * numero vem e por que).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Relatorio_Faturamento
{
    private DateTimeInterface $inicio;
    private DateTimeInterface $fim;

    /** @var WC_Order[]|null */
    private ?array $pedidos_pagos_cache = null;

    public function __construct(DateTimeInterface $inicio, DateTimeInterface $fim)
    {
        $this->inicio = $inicio;
        $this->fim = $fim;
    }

    /**
     * @return WC_Order[]
     */
    private function pedidos_pagos(): array
    {
        if ($this->pedidos_pagos_cache !== null) {
            return $this->pedidos_pagos_cache;
        }

        $pedidos = wc_get_orders([
            'status' => wc_get_is_paid_statuses(),
            'date_created' => $this->inicio->format('Y-m-d') . '...' . $this->fim->format('Y-m-d'),
            'limit' => -1,
            'return' => 'objects',
        ]);

        $this->pedidos_pagos_cache = is_array($pedidos) ? $pedidos : [];

        return $this->pedidos_pagos_cache;
    }

    public function receita(): float
    {
        $total = 0.0;
        foreach ($this->pedidos_pagos() as $pedido) {
            $total += (float) $pedido->get_total();
        }

        return $total;
    }

    /**
     * A chave de meta vem do proprio plugin oficial do Mercado Pago
     * (Order/OrderMetadata.php::addFeeDetails — `mercadopago_fee`), populada
     * so quando o pagamento completa de verdade via webhook. Pedidos sem
     * essa meta (ex.: os de teste do sandbox que nunca completaram) ficam de
     * fora da soma e contam em `pedidos_sem_dado` — a tela precisa avisar
     * disso, não mostrar como se a taxa desses pedidos fosse zero.
     *
     * @return array{total: float, pedidos_sem_dado: int}
     */
    public function taxa_mercado_pago(): array
    {
        $total = 0.0;
        $sem_dado = 0;

        foreach ($this->pedidos_pagos() as $pedido) {
            $taxa = $pedido->get_meta('mercadopago_fee', true);
            if ($taxa === '' || $taxa === null) {
                $sem_dado++;
                continue;
            }
            $total += (float) $taxa;
        }

        return ['total' => $total, 'pedidos_sem_dado' => $sem_dado];
    }

    /**
     * Usa o frete cobrado do cliente (`_order_shipping` nativo do
     * WooCommerce) como proxy do custo — decisão já tomada no projeto é não
     * aplicar markup sobre o frete, repassa a cotação do Melhor Envio direto.
     */
    public function custo_frete(): float
    {
        $total = 0.0;
        foreach ($this->pedidos_pagos() as $pedido) {
            $total += (float) $pedido->get_shipping_total();
        }

        return $total;
    }

    public function custo_ia(): float
    {
        if (!class_exists('Atelie_Ai_Custo_Tracker')) {
            return 0.0;
        }

        return Atelie_Ai_Custo_Tracker::gasto_periodo($this->inicio, $this->fim);
    }

    public function despesas_manuais(): float
    {
        return Atelie_Despesas_Manuais::total_periodo($this->inicio, $this->fim);
    }

    public function lucro_liquido(): float
    {
        $taxa_mp = $this->taxa_mercado_pago();

        return $this->receita()
            - $taxa_mp['total']
            - $this->custo_frete()
            - $this->custo_ia()
            - $this->despesas_manuais();
    }

    public function quantidade_pedidos(): int
    {
        return count($this->pedidos_pagos());
    }
}
