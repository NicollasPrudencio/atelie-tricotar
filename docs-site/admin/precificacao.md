---
title: Precificação interna
parent: Só para quem administra o site
nav_order: 3
permalink: /admin/precificacao/
---

# Precificação interna

Menu **"Orçamentos"** (ícone de calculadora 🧮), com duas sub-telas: **"Matérias-primas"** e
**"Artesãs"**. Ferramenta interna de cálculo de custo — a cliente nunca vê nem interage com
nada disso, e a Vendedora também não vê essas telas no menu (é normal, não é erro).

## Como as três telas se conectam

- **Artesãs** — cadastro simples de quem produz (nome + valor da hora técnica). Não é um login
  de usuário do sistema, é só um registro pra entrar na conta — muitas artesãs nem usam
  computador no dia a dia.
- **Matérias-primas** — cada item comprado (lã, linha, enchimento, embalagem...) com preço e
  unidade (metro, grama, unidade, novelo ou outro).
- **Orçamentos** — a calculadora em si. Cada orçamento junta: uma artesã (puxa o valor/hora
  automaticamente), uma categoria e porte (pequeno/médio/grande) do produto, uma lista de itens
  de matéria-prima usados (com quantidade) e as horas técnicas estimadas.

## A fórmula

```
custo = (soma dos itens de matéria-prima) + (horas técnicas × valor/hora da artesã)
```

O número final é o **custo** de produzir a peça — não é o preço de venda. A margem de lucro é
decisão manual de quem publica o produto: pegue esse custo e ajuste pra cima na hora de definir
o preço.

## Sugestão de horas pelo histórico

Ao escolher categoria e porte, o botão **"Sugerir horas pelo histórico"** calcula a média das
**horas reais** de orçamentos já marcados como "Produzido" com a mesma combinação. Quanto mais
peças forem produzidas e tiverem o campo "Horas reais" preenchido depois, mais precisa essa
sugestão fica — no começo, sem histórico suficiente, o campo simplesmente fica em branco pra
preencher manualmente.

Depois que a peça for feita de verdade, volte no orçamento, mude o status pra **"Produzido"** e
preencha **"Horas reais"** — é esse número (não a estimativa) que alimenta a média para os
próximos orçamentos da mesma categoria/porte.

## Puxar o custo pro preço do produto

Na tela nativa do WooCommerce de edição de produto (aba "Dados do produto" → "Geral"), aparece
um seletor **"Puxar custo de um orçamento"** com a lista de orçamentos salvos. Escolher um e
clicar em **"Preencher preço"** coloca o custo calculado direto no campo de preço regular — de
novo, lembrando que esse valor ainda precisa da margem somada por cima antes de publicar.
