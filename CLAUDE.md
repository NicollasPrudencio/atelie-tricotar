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
- **Painel de produto**: NÃO é o admin nativo do WooCommerce — é o plugin
  `web/app/plugins/atelie-produto-ia`, uma tela minimalista própria com autofill por IA de
  visão a partir de fotos e/ou receita/padrão do artesanato.
- **CI/CD**: pipeline em 2 estágios — todo push builda/testa, faz deploy automático em QA, roda
  testes de fumaça contra QA, e só promove para produção automaticamente se tudo passar. Sem
  aprovação manual no fluxo padrão (decisão explícita do usuário).
- **Nota fiscal**: ateliê ainda não tem CNPJ. Integração de NF-e é construída mas fica
  desligada (flag `NFE_EMISSAO_ATIVA=false`) até existir CNPJ — ativar depois é só preencher
  dados numa tela de configuração, sem deploy de código novo.

## Onde encontrar o resto

- **Plano completo do projeto** (arquitetura, roadmap por fases, custos, todas as decisões
  detalhadas): `docs/decisions/0001-stack-wordpress-woocommerce-bedrock.md`.
- **Precificação interna** (custo de matéria-prima + hora técnica, fora do plano original):
  `docs/decisions/0002-precificacao-interna.md`.
- **Estrutura de pastas e convenções de código**: ver `README.md`.

## Estado atual

Fase 0 (fundação) em andamento: scaffold do repositório, Docker de dev, CI básico. Ainda sem
deploy, sem tema, sem o plugin de IA implementado — só o scaffold. Consulte o roadmap no plano
para a ordem das próximas fases.
