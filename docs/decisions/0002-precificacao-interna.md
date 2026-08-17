# 0002 — Precificação interna (custo de matéria-prima + hora técnica)

## Contexto

Fora do escopo do plano original: uma ferramenta **interna** (o cliente nunca vê nem interage
com ela) para calcular o custo de um produto artesanal antes de publicá-lo, e conectar esse
custo direto ao campo de preço na hora de criar o produto.

## Decisão

Fórmula (decisão explícita do usuário, revisitada e confirmada após eu sugerir o padrão de
mercado com despesa fixa + margem — ele optou por manter simples):

```
custo = Σ(preço da matéria-prima × quantidade) + (horas técnicas × valor/hora da artesã)
```

**Sem despesa fixa rateada nem margem de lucro embutida.** O número calculado é o *custo*, não
o *preço de venda* — quem publica o produto ainda ajusta manualmente pra cima. Isso é diferente
do método SEBRAE padrão (que inclui as duas coisas), por escolha explícita do usuário.

## Modelo de dados (3 CPTs novos, sem tabela custom — volume não justifica)

- `atelie_materia_prima`: nome + preço + unidade (metro/grama/unidade/novelo/outro).
- `atelie_artesa`: nome + valor/hora. **Não é um usuário do WordPress** — cadastro simples, sem
  login, decisão explícita do usuário ("muitas artesãs podem nem usar o computador").
- `atelie_orcamento`: o registro do orçamento em si — artesã, categoria (reaproveita a taxonomia
  `product_cat` do WooCommerce, sem duplicar conceito), porte (pequeno/médio/grande), horas
  estimadas, itens de matéria-prima (repeater), horas *reais* (preenchido só depois que a peça é
  produzida de verdade) e status (Em orçamento / Produzido).

## Média de horas por histórico

A sugestão de horas técnicas **não usa a estimativa de outros orçamentos** — usa a média das
**horas reais** (`_atelie_orc_horas_reais`) de orçamentos já marcados como "Produzido", filtrando
por mesma categoria + porte. Isso é o que o usuário descreveu ("a artesã declara que fez em 4
horas, o sistema vai atualizando") — sem histórico suficiente, a sugestão simplesmente não
aparece e o campo fica manual. É uma média simples, não um modelo preditivo — não há
complexidade adicional que se justifique no volume de dados de um ateliê pequeno.

## Conexão com o preço do produto

Confirmado pelo usuário: conectada, não separada. Como o painel próprio de criação de produto
(Fase 3) ainda não existe, a conexão hoje vive na **tela nativa do WooCommerce** (hook
`woocommerce_product_options_pricing`): um seletor de orçamentos salvos + botão que preenche o
campo `_regular_price` com o custo calculado. Quando o painel de IA for construído, o mesmo
padrão (puxar `_atelie_orc_custo_total` de um orçamento) deve ser reaproveitado ali.

## Permissões

Sem capability nova concedida ao papel "Vendedora" — as 3 CPTs usam `capability_type` padrão
(`post`), que a Vendedora não tem (ela só tem capabilities específicas de produto/pedido/case).
Só quem já tem `edit_posts` (Administrador) vê essas telas. Isso é intencional: é uma ferramenta
de quem decide preço, não do fluxo de "postar produto" do dia a dia.

## Arquivo

`web/app/mu-plugins/atelie-precificacao.php`
