---
title: Custos e rateio
parent: Gestão do ateliê (Gestora)
nav_order: 3
permalink: /gestao/custos-e-rateio/
---

# Custos por canal de venda e rateio

Menu **"Gestão" → "Custos e rateio"**. É onde a Gestora diz quanto o ateliê gasta pra vender em
cada **canal** — **Feira**, **Site** e **Venda individual** — e quanto de cada venda é separado
pra o fundo de reposição (rateio). O [orçamento](/admin/precificacao/) usa essas informações pra
mostrar o **preço final por canal** e **quanto a artesã recebe**.

## Custos de cada canal

Em cada canal, adicione quantos custos quiser. Três tipos:

- **Valor fixo por peça (R$)** — soma no preço de cada peça (ex.: embalagem especial pra feira).
- **Custo do evento ÷ peças esperadas (R$)** — o custo total do evento dividido pelo número de
  peças que você espera vender ali (ex.: taxa da barraca de R$ 300 ÷ 30 peças = R$ 10 por peça).
- **Percentual do preço final (%)** — uma taxa que é uma porcentagem do preço (ex.: taxa da
  maquininha, comissão do site).

## Rateio (vale pra todos os canais)

Percentual do preço final de cada venda separado pra um destino:

- **Fundo de reposição** — vira dinheiro guardado no [fundo](/gestao/fundo-de-reposicao/) quando a
  venda é registrada.
- **Caixa do ateliê** — a margem/ganho do ateliê.

## A conta

```
preço final = (valor da artesã + custos fixos e de evento) ÷ (1 − soma dos percentuais)
```

Os percentuais (taxas do canal + rateios) são sempre **sobre o preço final** — por isso o preço é
calculado "por dentro" e cada linha fecha certinho. A soma dos percentuais precisa ficar abaixo de
90%, senão o painel não deixa salvar. **Exemplo**: valor da artesã R$ 50, custo fixo da feira R$ 10,
rateio de 14,3% → preço final R$ 70 na feira; a artesã recebe os R$ 50 e os R$ 20 restantes são do
ateliê (R$ 10 do custo da feira + R$ 10 de rateio).

No fim da tela tem uma **simulação**: digite um valor da artesã e veja o preço final e a divisão
em cada canal com o que está salvo.

## No orçamento

No orçamento (menu **Orçamentos**, agora também aberto pra Gestora), abaixo do cálculo de custo
aparece a tabela **"Preço final por canal de venda"**: preço final, quanto a artesã recebe, custos
do ateliê e o detalhe de cada linha, atualizando na hora conforme você mexe nos itens e nas horas.
Ao salvar o orçamento, esses valores ficam guardados junto com ele.
