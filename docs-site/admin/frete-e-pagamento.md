---
title: Frete e pagamento
parent: Só para quem administra o site
nav_order: 4
permalink: /admin/frete-e-pagamento/
---

# Frete e pagamento

O cálculo de frete e o processamento de pagamento não são telas próprias do ateliê — são feitos
por dois parceiros (Melhor Envio pro frete, Mercado Pago pro pagamento), cada um com sua própria
área dentro do painel. Esta página explica o que acontece entre a compra e o envio, pra saber
onde procurar quando alguma coisa precisar de atenção.

## Como o frete é calculado

No checkout, o cliente digita o CEP e o site consulta automaticamente as opções de Correios
(PAC e SEDEX) através do Melhor Envio, usando peso e dimensões do produto. Por isso é importante
sempre preencher peso/dimensões reais ao cadastrar um produto — sem isso, o sistema usa um
tamanho padrão genérico e o frete mostrado pro cliente fica impreciso.

Se um dia for preciso trocar o token de acesso do Melhor Envio (ex.: expirou, ou trocou de
conta), a configuração fica no menu **"Melhor Envio"** → "Configurações", fora do menu do
ateliê — é uma tela do próprio plugin do parceiro.

## Por que o CPF é obrigatório no checkout

O Melhor Envio exige o CPF (ou CNPJ) de quem compra pra poder gerar a etiqueta de envio depois —
sem esse documento, a etiqueta simplesmente não é criada, mesmo com o pagamento já aprovado. Por
isso o campo de CPF é obrigatório no checkout do site, com validação automática dos dígitos.

## Gerar a etiqueta de envio

Depois que um pedido é pago, a etiqueta pode ser gerada de duas formas:

- Direto na tela do pedido (WooCommerce → Pedidos → abrir o pedido), se essa opção aparecer
  integrada ali.
- Pelo menu **"Melhor Envio"**, na lista de pedidos importados automaticamente — é onde ficam as
  etiquetas geradas e o histórico de envios.

## Confirmar que um pagamento caiu

O status do pedido muda sozinho quando o Mercado Pago confirma o pagamento (de "Aguardando
pagamento" para "Processando" ou "Concluído"). Dentro do próprio pedido, a aba de notas mostra
uma mensagem tipo "Mercado Pago: Pagamento aprovado" com os detalhes — é a forma mais rápida de
confirmar sem precisar abrir o painel do Mercado Pago.
