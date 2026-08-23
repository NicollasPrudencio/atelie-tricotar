# 0001 — Stack: WordPress + WooCommerce + Bedrock

> Registro de decisão de arquitetura. Copiado do plano original aprovado em 2026-08-17, para
> que fique versionado no repositório (não só na ferramenta de planejamento local) — ver
> `CLAUDE.md` e a seção "Continuidade de contexto entre máquinas" abaixo.

## Contexto

O repositório foi zerado (nenhum commit ainda) para se tornar o repo oficial do site do
ateliê, que hoje vende artesanato (tricô, crochê, amigurumis e afins) e quer passar a vender
para todo o Brasil pela internet, com tráfego pago (Facebook/Instagram/Google Ads). Requisitos
de negócio levantados diretamente com o usuário:

1. **Rastreabilidade forte** para ads — pixel client-side + Conversions API server-side (perda
   de sinal do iOS/bloqueadores).
2. **Painel simples e minimalista** usado por pessoas leigas — a função essencial agora é criar
   produtos ("a ideia é apenas elas ir e postar o produto").
3. **IA generativa** que, a partir das fotos, preenche os campos do produto como rascunho
   editável.
4. **Checkout completo e automático** — pagamento no site (Pix/cartão/boleto) e frete
   calculado, sem trabalho manual além de postar o produto.
5. **Orçamento mínimo de hospedagem** — plano anual barato (HostGator/similar), aberto a outras
   opções por preço (domínio já está no Cloudflare).
6. **Ambiente de desenvolvimento separado** de produção.
7. **Versionar tudo em git** + **pipeline de CI/CD com detecção de falhas de segurança**.
8. Manutenção: produtos entram o tempo todo (leigos via painel); features novas "de vez em
   quando" (dono do projeto, não é time full-time).
9. Pagamento e frete: **Mercado Pago** (Pix/cartão/boleto) + **Melhor Envio** (frete
   multi-transportadora).
10. **Continuidade de contexto entre máquinas**: clonar o repo em um PC novo deve restaurar o
    contexto do projeto para o Claude Code, sem depender de nada fora do git.

## Decisão de stack

**WordPress + WooCommerce + Bedrock.** Construir carrinho/checkout/catálogo/estoque/cupons do
zero (custom) levaria semanas/meses — contraria "lançar vendendo o quanto antes". WooCommerce
resolve isso pronto, com plugins oficiais maduros exatamente para Mercado Pago e Melhor Envio, e
plugins de tracking (Meta CAPI, GA4 server-side) que evitam código custom pesado. SaaS
(Shopify/Nuvemshop) foi descartado por ter mensalidade recorrente e dificultar versionamento em
git. Headless/custom foi descartado por não ser pedido e aumentar custo/complexidade sem
necessidade.

**Bedrock** (roots.io) resolve o requisito de versionamento: estrutura de pastas voltada a git,
dependências via Composer (versão pinada, auditável), config por ambiente via `.env` — sem
isso, WordPress "vanilla" (plugins instalados via clique no admin) não é realisticamente
versionável nem auditável em CI.

**Validação de hospedagem antes de contratar:** confirmar com o provedor (HostGator/Hostinger)
suporte a SFTP (não precisa SSH — o build roda no GitHub Actions) e PHP ≥ 8.1. O `.env` de
produção fica fora de `public_html` (ou protegido por `.htaccess` se o plano não permitir isso).

O painel "minimalista para leigos" **não é o admin nativo do WooCommerce** — é o plugin fino
customizado `web/app/plugins/atelie-produto-ia`.

## Pipeline CI/CD

Pipeline em 2 estágios, promoção automática por gate de testes (decisão explícita do usuário:
"todos os testes no ambiente QA e, se passar, implementa em prod"):

1. Push em `develop`/PR → lint + `composer audit` + PHPUnit → se passar, deploy automático para
   QA/staging (`dev.<dominio>`, mesma hospedagem, subdomínio).
2. Testes de fumaça/integração (Playwright) contra a URL de staging: homepage, página de
   produto, checkout completo em sandbox (Mercado Pago + Melhor Envio).
3. Se e somente se todos os testes de QA passarem, promove automaticamente para produção — sem
   aprovação manual no fluxo padrão.
4. Rede de segurança reativa: notificação em falha de deploy + job de rollback manual.

## Plugin "Criar Produto com IA"

Tela própria no admin (não a nativa do WooCommerce). Fluxo: anexar fotos e/ou receita/padrão →
botão explícito "Sugerir com IA" → formulário volta preenchido (rascunho editável) → usuária
completa preço/disponibilidade (pronta entrega vs. sob encomenda + prazo de produção)/peso →
publica.

Inclui modo "Criar em massa" (upload de várias receitas/fotos ou importação de pasta do Google
Drive), processado item por item via Action Scheduler — cada item revisável assim que termina,
com retry automático com backoff em caso de erro e tela de revisão em lote com status por item.

Papel customizado **"Vendedora"**: acesso restrito a produto/pedido, sem acesso a
plugins/configurações/pagamento.

## Rastreabilidade, KPIs e anúncios pelo painel

Pixel Manager for WooCommerce (Meta Pixel + CAPI + GA4). Evento `Purchase` sempre com
`value`/`currency`/`content_ids`. Convenção de UTM documentada. Meta for WooCommerce e Google
Listings & Ads permitem criar/impulsionar anúncios básicos direto do admin do WooCommerce,
catálogo sincronizado automaticamente.

## Portfólio/cases e encomenda personalizada

Além do catálogo com preço fixo: galeria de portfólio (`case_atelie`, sem preço/compra) e
formulário de "solicitar orçamento personalizado" para qualquer serviço não catalogado — vira
lead por e-mail e dispara evento `Lead` no pixel/CAPI.

## Nota fiscal

Ateliê ainda não tem CNPJ — caminho recomendado é abrir como **MEI** (gratuito, imposto fixo
baixo, sem exigência de contador, teto de ~R$81 mil/ano). Emissão manual de nota pelo emissor
gratuito do próprio MEI é suficiente em baixo volume, sem precisar de nenhuma plataforma paga.

**Decisão 2026-08-20 (revisão da decisão original deste documento)**: quando a emissão
automática de NF-e fizer sentido (a cada venda, sem intervenção manual), a escolha é o
**Bling** (ERP com emissor de NF-e integrado + plugin oficial pro WooCommerce) em vez de
construir uma integração própria com Focus NFe/NFe.io. Motivo: é uma área onde erro custa caro
(nota fiscal errada é problema com o fisco, não só um bug de software) — preferível usar uma
plataforma testada por milhares de lojas do que manter lógica fiscal customizada (rejeição da
Sefaz, contingência, regras por NCM, etc.). Com o Bling, a automação fica 100% configurada no
painel deles (dispara sozinha quando o pedido é marcado como pago) — não exige token nem
lógica no nosso código, diferente do que a flag `NFE_EMISSAO_ATIVA` (ainda existente só como
reserva) originalmente previa.

Pré-requisito descoberto na prática: emitir nota (por qualquer via) exige o CPF/CNPJ do
comprador capturado no checkout — hoje esse campo não é capturado de verdade (o checkout em
blocos do WooCommerce não é compatível com o plugin usado pra isso), então precisa ser resolvido
antes de qualquer emissão automática funcionar.

## Continuidade de contexto entre máquinas

- `CLAUDE.md` na raiz — carregado automaticamente pelo Claude Code, mantido como resumo vivo.
- `docs/decisions/` — log cronológico de decisões (este arquivo é o primeiro).
- Camada complementar best-effort (não oficialmente suportada): diretório portátil espelhando
  memória e transcripts de sessão + script de restauração — ver plano completo para as
  ressalvas de segurança antes de sincronizar transcripts brutos.

## Custo

Único custo fixo recorrente relevante: hospedagem (~R$70–150/ano no 1º ano). CI/CD, tracking,
IA de visão, e-mail transacional e backup cabem em free tiers no estágio inicial. Pagamento e
frete só custam proporcionalmente ao que é vendido (Mercado Pago sem taxa fixa, Melhor Envio
sem mensalidade).

## Roadmap

- **Fase 0 — Fundação**: scaffold Bedrock, Docker de dev, CI básico, CLAUDE.md. *(em andamento)*
- **Fase 1 — MVP vendável**: WooCommerce + tema, Mercado Pago + Melhor Envio em sandbox, deploy
  automatizado, campos de disponibilidade/prazo, papel Vendedora, cache Cloudflare, compressão
  de imagem, 2FA, políticas legais, portfólio/cases + orçamento personalizado.
- **Fase 2 — Tracking e KPIs de anúncio**: Meta Pixel + CAPI, GA4, LGPD, verificação de domínio
  Meta/Google Ads, Looker Studio.
- **Fase 3 — Painel simplificado + IA de visão**: plugin `atelie-produto-ia` completo, criação
  em massa, importação do Google Drive, receita como entrada da IA.
- **Fase 4 — Hardening e NF-e pronta-para-ativar**: WPScan agendado, backups testados,
  integração de NF-e desligada.
- **Fase 5 — Melhorias contínuas**: SEO, cache fino, features sob demanda.
