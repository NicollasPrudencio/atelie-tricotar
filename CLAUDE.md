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
- **CI/CD**: pipeline em 2 estágios — todo push em `develop` builda/testa, faz deploy automático
  em `atelietricotar.online` (ambiente "dev"/"homolog" — mesmo lugar, os dois nomes são usados
  no dia a dia; ver `docs/decisions/0001` e memória `project_mapa_ambientes`), roda teste de
  fumaça (k6) contra ele, e só promove para produção (`atelietricotar.com.br`) automaticamente
  se tudo passar. **Sem aprovação manual no fluxo padrão — decisão explícita do usuário,
  reafirmada 2026-08-24, não relitigar sem motivo novo** (ver memória
  `feedback_promocao_automatica_nao_relitigar`).
- **Nota fiscal**: ateliê ainda não tem CNPJ (caminho recomendado: MEI, gratuito). Emissão
  automática de NF-e (quando fizer sentido, por venda) será via **Bling** (ERP com plugin
  oficial pro WooCommerce), não uma integração própria com Focus NFe/NFe.io como planejado
  originalmente — decisão explícita do usuário 2026-08-20, ver `docs/decisions/0001`. Isso
  significa **sem token nem lógica no nosso código** pra isso — configuração fica 100% no
  painel do Bling. Pré-requisito já resolvido: campo de CPF no checkout em blocos do WooCommerce
  (`web/app/mu-plugins/atelie-checkout-campos-br.php`), com validação de dígito verificador,
  salvo nas mesmas chaves de meta (`_billing_cpf`, `_billing_persontype`) que o Melhor Envio e o
  Bling esperam — o plugin `woocommerce-extra-checkout-fields-for-brazil` não era compatível com
  checkout em blocos, por isso um campo próprio via API de blocos do WooCommerce. Ainda falta:
  o ateliê de fato abrir o MEI e criar a conta no Bling — sem isso, a emissão de NF-e não tem
  como funcionar mesmo com o campo pronto.

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
  pra testar integrações que exigem callback público — **depreciado desde 2026-08-24** (decisão
  explícita do usuário, "não estamos mais usando túnel"); não reviver sem perguntar. O ambiente
  usado pra desenvolver/testar hoje é `atelietricotar.online` (ver `project_mapa_ambientes`).
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
  seção "Onde encontrar o resto"), com cobertura completa das telas do painel, incluindo
  Precificação interna.
- Acesso SSH real ao servidor de dev liberado via chamado de suporte (ver
  `docs/acesso-ssh-dev.md`) — usado pra corrigir, no mesmo dia (2026-08-21), dois bugs que
  faziam o pipeline de CI/CD nunca ter rodado de verdade contra hospedagem real: `composer.lock`
  desatualizado + `composer audit` travando por pacote abandonado sem vulnerabilidade real, e um
  bug de sintaxe YAML no `deploy.yml` que derrubava o workflow inteiro silenciosamente. Lint
  (WPCS) também nunca tinha rodado de verdade — rodado pela primeira vez, ~9.590 problemas
  encontrados e corrigidos/justificados.
- Pipeline de deploy (2026-08-23): 100% automático, sem aprovação manual em nenhum ponto — push
  em `develop` roda lint/auditoria, faz deploy em homolog, roda teste de stress/fumaça (k6) contra
  o ambiente já no ar, e se passar promove `develop` → `main` sozinho, disparando o deploy de
  produção (lint/auditoria de novo, depois deploy). GitHub Environments `staging`/`production`
  criados, com os 8 salts de segurança do WordPress já configurados em cada um (gerados
  aleatoriamente, nunca reaproveitados entre ambientes) — os ~22 secrets restantes (banco, SFTP,
  chaves de API de pagamento/frete/IA/tracking) ainda pendentes, o usuário está configurando via
  `gh secret set` diretamente (nunca colados no chat, por segurança).
- Fase 2 de tracking: plugins instalados e ativos no dev (Pixel Manager for WooCommerce, versão
  gratuita — Meta Pixel só navegador + GA4, sem Meta CAPI por decisão explícita de custo; e
  Complianz pro banner de consentimento LGPD). Falta o usuário criar as contas reais (Pixel do
  Meta, propriedade GA4) e revisar o texto do banner de consentimento.
- Hardening (Fase 4): scan de segurança agendado (WPScan, semanal) criado como workflow —
  falta o usuário configurar o secret `WPSCAN_API_TOKEN` (conta gratuita em wpscan.com) pra
  funcionar de verdade. Teste de restore de backup feito e confirmado 2026-08-21 (backup
  completo via cPanel, extraído em pasta isolada, arquivos batendo 100% com o site ao vivo e
  dump do MySQL com as 62 tabelas reais) — mecanismo de backup do host confirmado confiável.
- Tela "Pedidos de Orçamento" (2026-08-21): fecha a lacuna que o formulário público "Solicitar
  orçamento personalizado" tinha — antes só mandava e-mail, sem nenhum registro no painel. Cada
  envio agora também vira registro (nome, contato, descrição, foto de referência, status Novo/
  Respondido), visível pra Vendedora e Administrador.
- Página 404 customizada (2026-08-21) na loja (identidade visual do tema, CTAs pra loja/
  portfólio) e no docs-site (link de volta pro manual).
- Bloqueio de MFA em 24h (2026-08-21): conta nova tem 24h pra configurar 2FA, bloqueio
  persistente a cada login (não só aviso dispensável), trava total passadas as 24h até um
  admin desbloquear — via configuração nativa do plugin WP 2FA + correção de um bypass real
  encontrado (cookies de sessão já saíam válidos na resposta do login, permitindo pular a
  tela de bloqueio acessando o wp-admin direto por URL).
- Tela "Anúncios" (2026-08-21, plugin `atelie-produto-ia`): IA gera texto de anúncio pago
  (Meta + TikTok) em lote a partir de produtos/cases já publicados, pensando em gerar visita/
  venda — não publica nem gasta nada sozinha, só o criativo pra copiar manualmente. Imagem
  sugerida é foto já existente do item; vídeo pro TikTok é anexado manualmente (sem geração de
  vídeo por IA). Testado com API real do Gemini.
- Páginas legais (2026-08-23): política de privacidade, termos de uso e trocas/devoluções
  publicadas com dados reais (antes: uma não existia, uma era rascunho, uma tinha link
  quebrado no rodapé). Ainda sem revisão de advogado/contador; resolver antes de ligar tráfego
  pago de verdade.
- Roadmap de IA (Fase F): tela "Receita em outro idioma" (submenu de Produtos) — "Buscar" usa
  grounding com Google Search do Gemini pra indicar candidatos de padrão em outro idioma (só
  título/fonte/resumo, nunca a receita inteira, por risco de direito autoral); "Traduzir" traduz
  fielmente um texto que a artesã já tem, em qualquer idioma de origem. "Traduzir" testado com
  API real (funcionou). "Buscar" bateu em erro de quota/billing do Google ao usar grounding
  (mesma pendência de faturamento da edição de imagem, ver acima) — parsing do resultado real
  ainda não verificado, ver memória `project_testar_apos_merge_prs`.
- Mais 4 pontos de entrada da IA (avaliados em 2026-08-20, construídos em 2026-08-23): tela
  "SEO" (meta título/descrição + alt text, salvos direto no post, nunca copiar/colar); botão
  "IA sugere edição" de imagem (diagnostica a foto sozinha e decide se edita, sem usuário
  descrever — reaproveita `editarImagem()`); botão "Rascunhar resposta" na tela Pedidos de
  Orçamento (nunca decide prazo/preço sozinha, marca `[PREENCHER]`); botão "IA sugere preço de
  venda" na tela de produto do WooCommerce (nunca preenche o campo de preço sozinha, só
  sugestão com faixa e justificativa). Todos testados com API real; detalhes de cobertura de
  teste em `project_testar_apos_merge_prs`. Ficaram de fora desta leva (avaliados, não
  construídos): busca de imagem na web (precisa decisão de fonte/licença — risco de direito
  autoral), e-mail via Brevo (integração nova do zero) e curadoria de reviews (sem reviews reais
  ainda) — ver memória `project_futuros_pontos_ia`.
- **Produção no ar** (2026-08-24): pipeline de deploy automático corrigido (dois bugs reais —
  `appleboy/scp-action` não entregava o arquivo no servidor apesar de reportar sucesso, e o
  secret `APP_REMOTE_PATH` corrompido duas vezes por conversão de caminho do git-bash/PowerShell
  no Windows, ambos mascarados porque homolog continuava servindo conteúdo antigo colocado
  manualmente) — confirmado ponta a ponta via SSH direto no servidor, não só pelo status
  reportado pela Action. WordPress instalado em produção (`nico7638_atelie_prod`), configuração
  completa (tema, moeda BRL, slugs em português, zona de frete "Brasil", páginas legais)
  replicada de homolog via `wp db export`/`import` + `search-replace` de domínio — bootstrap
  único, nunca mais repetir isso depois que produção tiver dados reais (ver memória
  `feedback_nunca_clonar_banco_para_prod`). Pedidos e produtos de teste que vieram no dump foram
  removidos; produção começa com histórico limpo. Login admin de produção criado
  (`admin@atelietricotar.com.br`). Credencial de produção do Mercado Pago sincroniza sozinha do
  `.env` (mu-plugin `atelie-integracoes-sync.php` já existia pra isso). Observabilidade de
  produção (uptime, k6 agendado, alerta de pedido/webhook falho, Sentry) planejada e aprovada em
  espírito, mas adiada pro pós-lançamento — ver memória `project_observabilidade_pendente`.
- **Segurança e endereço da loja** (2026-08-24/25): verificação diária de CVE no ar
  (`atelie-seguranca-cve.php`, base do WPScan, e-mail 2x/dia + aviso no painel enquanto
  pendente) — ao testar de verdade achou uma vulnerabilidade real em produção (WooCommerce
  9.9.7, 2 CVEs sem correção), corrigida na hora (10.9.4). Smoke test do pipeline
  (`tests/stress/homolog.js`) deixou de só checar página carregando — agora testa carrinho +
  cálculo de frete de verdade via WooCommerce Store API a cada deploy. Endereço da loja
  (`STORE_ADDRESS`/`CITY`/`STATE`/`POSTCODE`) passou a sincronizar automático do secret, mesmo
  padrão do Mercado Pago/Melhor Envio.
- **Nota fiscal — fluxo manual, não Bling** (2026-08-24): MEI não é obrigado a emitir NF-e em
  venda pra pessoa física em 2026 (só a partir de 2027, LC 214/2025) — Bling/Focus NFe custam
  mensalidade que não compensa pro volume esperado. Construído em vez disso:
  `atelie-nota-fiscal.php` — checkbox opcional no checkout, coluna na lista de pedidos marcando
  quem pediu, artesã sobe número + arquivo (emitido manualmente no portal gratuito da Sefaz) no
  próprio pedido, e-mail automático pra cliente quando emitida.
- **Revisão jurídica/LGPD sem advogado** (2026-08-24): MEI pequeno, sem profissional contratado —
  revisão feita por IA com base em legislação pública (CDC, LGPD, resoluções ANPD), corrigiu uma
  inconsistência real (política de privacidade afirmava usar Conversions API do Meta, nunca
  implementada) direto na página publicada. Ver memória `project_revisao_juridica_pendente`.
- **Papéis do painel e wizard de configuração** (2026-08-25): papel "Vendedora" (criado, nunca
  usado) renomeado pra "Artesã" — reflete melhor quem de fato usa a conta. Nova tela
  "Configuração Inicial" (`class-wizard-config-page.php`, dentro de `atelie-produto-ia`): wizard
  guiado que só aparece no menu enquanto faltar configurar chave da IA, Meta Pixel ou GA4 —
  explica onde buscar cada valor, valida antes de avançar (teste de conexão real pra IA;
  checagem de formato pra Pixel/GA4). Mercado Pago, Melhor Envio, WPScan e endereço da loja
  ficam de fora de propósito — continuam nascendo configurados via secret no deploy, não são
  passo de wizard. Isso expôs e corrigiu um bug real: `atelie-integracoes-sync.php` sincronizava
  do `.env` toda vez que uma página carregava, o que sobrescreveria qualquer valor que uma
  pessoa configurasse depois pelo painel — agora só semeia se a option estiver vazia, nunca mais
  pisa em cima de configuração real.
