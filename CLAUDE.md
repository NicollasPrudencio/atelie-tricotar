# Site do Ateliê — contexto do projeto

Este arquivo é carregado automaticamente pelo Claude Code no início de toda conversa neste
repositório, em qualquer máquina. Mantenha-o atualizado conforme o projeto avança — é o
mecanismo oficial de continuidade de contexto entre PCs (ver plano do projeto, seção
"Continuidade de contexto entre máquinas").

## O que é este projeto

Site de e-commerce de um ateliê de tricô, crochê, amigurumis e artesanato em geral, vendendo
para todo o Brasil, com tráfego pago (Meta/Google Ads) como canal principal. Substitui um site
antigo (removido, este é o repo oficial novo).

## Decisões de arquitetura (não relitigar sem motivo novo)

- **Stack**: WordPress + WooCommerce, usando o boilerplate **Bedrock** (roots.io) — estrutura
  de pastas voltada a git, dependências via Composer, config por ambiente via `.env`.
  Decisão detalhada e alternativas descartadas (Laravel custom, headless, SaaS) estão no plano
  original e em `docs/decisions/0001-stack-wordpress-woocommerce-bedrock.md`.
- **Hospedagem**: cPanel compartilhado barato (Hostinger/HostGator, cotação pendente), deploy
  via SFTP (sem depender de SSH). Orçamento é uma restrição de design, não um detalhe.
- **Painel de produto/case**: NÃO é o admin nativo do WooCommerce/WordPress — é o plugin
  `web/app/plugins/atelie-produto-ia`, telas próprias com autofill por IA de visão a partir de
  fotos e/ou receita/padrão do artesanato. A tela "Adicionar novo" nativa (produto e case) é
  removida do menu e redireciona pra tela própria — nunca existem dois caminhos de criação.
  A IA também **revisa** qualquer texto editado ou 100% manual antes de publicar (nome da
  artesã no texto, elogio exagerado, linguagem amadora, falta de gatilho de venda) e **bloqueia
  a publicação** se achar problema, sem opção de ignorar — decisão explícita do usuário, ver
  manual do painel (link na seção "Onde encontrar o resto").
- **CI/CD**: pipeline em 2 estágios — todo push builda/testa, faz deploy automático em QA, roda
  testes de fumaça contra QA, e só promove para produção automaticamente se tudo passar. Sem
  aprovação manual no fluxo padrão (decisão explícita do usuário).
- **Nota fiscal**: ateliê ainda não tem CNPJ (caminho recomendado: MEI, gratuito). Emissão
  automática de NF-e (quando fizer sentido, por venda) será via **Bling** (ERP com plugin
  oficial pro WooCommerce), não uma integração própria com Focus NFe/NFe.io como planejado
  originalmente — decisão explícita do usuário 2026-08-20, ver `docs/decisions/0001`. Isso
  significa **sem token nem lógica no nosso código** pra isso — configuração fica 100% no
  painel do Bling. Pré-requisito ainda pendente: capturar CPF/CNPJ do comprador no checkout (o
  checkout em blocos do WooCommerce não é compatível com o plugin `woocommerce-extra-checkout-
  fields-for-brazil` instalado pro Melhor Envio — precisa de um campo customizado via API de
  blocos do próprio WooCommerce).

## Onde encontrar o resto

- **Plano completo do projeto** (arquitetura, roadmap por fases, custos, todas as decisões
  detalhadas): `docs/decisions/0001-stack-wordpress-woocommerce-bedrock.md`.
- **Precificação interna** (custo de matéria-prima + hora técnica, fora do plano original):
  `docs/decisions/0002-precificacao-interna.md`.
- **Manual do painel administrativo** (como usar cada tela — produto, case, criação em massa,
  Pendências, configurar IA, faturamento; documento vivo, atualizado a cada funcionalidade
  nova): site publicado via GitHub Pages a partir de `docs-site/` (Jekyll +
  `just-the-docs`), em `https://nicollasprudencio.github.io/atelie-tricotar/` (domínio próprio
  `docs.atelietricotar.com.br` fica pra depois — só falta apontar um CNAME). Cada tela do
  painel tem um botão "?" (canto do `<h1>`) que abre um resumo rápido e linka pra página certa
  dessa doc — componente compartilhado em
  `web/app/mu-plugins/atelie-ajuda-drawer.php` (`Atelie_Ajuda_Drawer::render()`).
- **Túnel fixo do Cloudflare pra dev local**: `docs/cloudflare-tunnel-fixo.md`.
- **Acesso SSH ao servidor de dev** (conveniência de depuração, não muda o deploy oficial via
  SFTP): `docs/acesso-ssh-dev.md`.
- **Estrutura de pastas e convenções de código**: ver `README.md`.

## Estado atual

Bem além da Fase 0. Já construído e funcionando:
- Ambiente de dev local via Docker, com túnel fixo do Cloudflare (`dev.atelietricotar.com.br`)
  pra testar integrações que exigem callback público.
- Domínio `atelietricotar.com.br` conectado ao Cloudflare (DNS-only); hospedagem HostGator
  Plano M já contratada, deploy real ainda pendente.
- Tema (`web/app/themes/atelie-theme`) com identidade visual completa (paleta pêssego/rosa/
  marfim, tipografia Fraunces+Mulish, baseada no logo real do ateliê), loja, carrinho, checkout,
  portfólio e formulário de orçamento personalizado funcionando via Mercado Pago (sandbox) +
  Melhor Envio (sandbox) — zona de entrega "Brasil" com Correios PAC/SEDEX precisou ser
  configurada manualmente (nunca tinha sido de fato, apesar do token do Melhor Envio já
  configurado), junto com o plugin `woocommerce-extra-checkout-fields-for-brazil` (exigido pelo
  Melhor Envio pros campos de checkout BR). Moeda da loja também estava em USD por padrão do
  WooCommerce — corrigida pra BRL com formatação brasileira (R$ 1.234,50).
- Plugin `atelie-produto-ia` completo: criação de produto e de case com autofill por IA
  (Gemini), criação em massa assíncrona, revisor de qualidade de venda com bloqueio de
  publicação, rastreamento de custo de IA por chamada, tela própria "IA" (chave/status/custo).
  Edição de imagem por IA está com o código pronto mas não testada de verdade (exige
  faturamento ativo numa conta Google).
- Plugin `atelie-faturamento`: receita vs. custos (IA, taxa Mercado Pago, frete, despesas
  manuais) e lucro líquido, por período.
- Importação do Google Drive implementada (autorização OAuth fixa na conta do desenvolvedor,
  não da artesã — decisão explícita; ela só compartilha a pasta), com modal próprio de seleção
  (pastas e/ou fotos soltas, não o Picker oficial do Google — não dava pra customizar o
  suficiente pra usuário leigo). Testado ponta a ponta no ambiente dev.
- Tela "Pendências" (cross-lote, força reprocessamento de itens atrasados só de ser visitada —
  "cutucar_pendentes()") + cron real do cPanel no ambiente dev (`DISABLE_WP_CRON=true` no `.env`
  do servidor, WP-Cron pseudo-cron não é confiável nesse host).
- Site de documentação (`docs-site/`, GitHub Pages) + botão de ajuda contextual no painel (ver
  seção "Onde encontrar o resto"), com cobertura completa das telas do painel, incluindo
  Precificação interna.
- Acesso SSH real ao servidor de dev liberado via chamado de suporte (ver
  `docs/acesso-ssh-dev.md`) — usado pra corrigir, no mesmo dia (2026-08-21), dois bugs que
  faziam o pipeline de CI/CD nunca ter rodado de verdade contra hospedagem real: `composer.lock`
  desatualizado + `composer audit` travando por pacote abandonado sem vulnerabilidade real, e um
  bug de sintaxe YAML no `deploy.yml` que derrubava o workflow inteiro silenciosamente. Lint
  (WPCS) também nunca tinha rodado de verdade — rodado pela primeira vez, ~9.590 problemas
  encontrados e corrigidos/justificados. Tudo isolado em PRs (#1 CI/lint, #2 WPScan agendado),
  **sem merge pra `main`** — decisão explícita do usuário: pipeline de deploy pra produção fica
  por último, só quando o ambiente de dev estiver pronto pra lançar de verdade.
- Fase 2 de tracking: plugins instalados e ativos no dev (Pixel Manager for WooCommerce, versão
  gratuita — Meta Pixel só navegador + GA4, sem Meta CAPI por decisão explícita de custo; e
  Complianz pro banner de consentimento LGPD). Falta o usuário criar as contas reais (Pixel do
  Meta, propriedade GA4) e revisar o texto do banner de consentimento.
- Hardening (Fase 4): scan de segurança agendado (WPScan, semanal) criado como workflow —
  falta o usuário configurar o secret `WPSCAN_API_TOKEN` (conta gratuita em wpscan.com) pra
  funcionar de verdade. Teste de restore de backup feito e confirmado 2026-08-21 (backup
  completo via cPanel, extraído em pasta isolada, arquivos batendo 100% com o site ao vivo e
  dump do MySQL com as 62 tabelas reais) — mecanismo de backup do host confirmado confiável.
- Pendente do roadmap de IA: assistente de busca+tradução de receita em outro idioma (Fase F).
- Pendente geral: deploy real pra produção (decisão explícita: fica por último).
- Tela "Pedidos de Orçamento" (2026-08-21): fecha a lacuna do formulário público "Solicitar
  orçamento personalizado" só mandar e-mail — cada envio agora também vira registro no painel
  (nome, contato, descrição, foto de referência, status Novo/Respondido), visível pra Vendedora
  e Administrador. Ver PR #4.
