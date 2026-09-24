# 0003 — Gestão do ateliê: patrimônio, custos por canal e rateio

## Contexto

Pedido do usuário (2026-09-24): (1) sistema de patrimônio — dar entrada no que o ateliê compra e
registrar dano/reposição; (2) orçamento passar a incluir os demais custos do ateliê por canal de
venda (feira, site, individual), mostrando o preço final por canal e quanto a artesã recebe;
(3) rateio — separar uma parte do lucro de cada venda pra um fundo de reposição do patrimônio,
com saldo real.

Exemplo dado: preço final de R$ 70 definido pelo orçamento; a artesã responsável recebe R$ 50; os
R$ 20 restantes são custo do ateliê pra vender naquele canal.

## Decisões

- **Preço**: o orçamento define o preço final POR CANAL a partir do valor da artesã (o custo de
  produção já existente, ADR 0002) + custos do ateliê do canal (soma por cima). Itens de custo do
  canal: valor fixo por peça, percentual sobre o preço final (taxa de cartão, comissão, rateio
  pro fundo) e custo de evento dividido pelas peças esperadas (ex.: taxa da feira). Percentuais
  são sobre o PREÇO FINAL, então: `preço = (valor da artesã + fixos) / (1 − Σ percentuais)`.
- **Fundo com saldo real** (não simulação): exige registrar as vendas (site, feira, individual)
  pra o rateio virar lançamento no livro do fundo; reposições de patrimônio abatem o saldo.
- **Acesso**: nova capacidade `atelie_gestao`, dada ao Administrador e ao novo papel **Gestora**
  (`atelie_gestora` — a "artesã especial", dona/gestora administrativa: tudo da Artesã + gestão).
  Artesã comum não vê nada disso.
- **Armazenamento**: tabelas próprias (`atelie_patrimonio`, `atelie_patrimonio_eventos`,
  `atelie_fundo`, e nas próximas fases vendas) em vez de CPT — o fundo é um livro-razão somado,
  não faz sentido como "post". Carregado como mu-plugin (`atelie-gestao.php` + pasta
  `atelie-gestao/`), sem precisar ativar plugin.

## Fases

1. **Patrimônio + fundo de reposição** (entrada, dano, conserto, baixa, reposição; livro do fundo
   com aporte/retirada manual). ✔ entregue
2. **Orçamento por canal** (custos do ateliê por canal em Gestão › Custos e rateio, preço final
   por canal e valor da artesã no orçamento; snapshot em `_atelie_orc_canais`; as 3 telas de
   precificação passaram a exigir `atelie_gestao`, abrindo pra Gestora). ← esta entrega
3. Vendas (registro por canal) alimentando o rateio → fundo, com relatório.
