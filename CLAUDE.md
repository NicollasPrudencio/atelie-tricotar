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
- **Nota fiscal**: ateliê ainda não tem CNPJ. A integração de NF-e é pra ser construída e ficar
  desligada (flag `NFE_EMISSAO_ATIVA=false`) até existir CNPJ — a flag reservada já existe no
  `.env`, mas a integração em si (chamadas ao provedor, geração de nota) **ainda não foi
  construída**. Quando for: ativar depois é só preencher dados numa tela de configuração, sem
  deploy de código novo.

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
  Melhor Envio (sandbox).
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
  seção "Onde encontrar o resto").
- Pendente do roadmap de IA: assistente de busca+tradução de receita em outro idioma (Fase F).
- Pendente geral: deploy real (CI/CD ainda não rodou contra hospedagem de verdade), Fase 2
  completa de tracking (Meta Pixel/CAPI, GA4, banner LGPD), hardening da Fase 4 (WPScan
  agendado, teste de restore de backup).
