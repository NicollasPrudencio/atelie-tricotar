# Site do Ateliê

E-commerce em WordPress + WooCommerce (estrutura [Bedrock](https://roots.io/bedrock/)) para
venda de tricô, crochê, amigurumis e artesanato em geral para todo o Brasil.

Contexto completo do projeto, decisões de arquitetura e roadmap: ver [`CLAUDE.md`](CLAUDE.md) e
[`docs/decisions/`](docs/decisions/).

## Estrutura

```
config/                  Configuracao do WordPress por ambiente (le tudo de .env)
web/wp-config.php        Ponto de entrada — nao editar, config real fica em config/
web/wp/                  Core do WordPress (instalado via composer, nao versionado)
web/app/themes/          Tema do site
web/app/plugins/         Plugins de terceiros (nao versionados) + atelie-produto-ia (nosso)
web/app/mu-plugins/      Plugins sempre ativos (hardening/bootstrap)
docker/                  Ambiente de desenvolvimento local
.github/workflows/       CI/CD
docs/decisions/          Log de decisoes de arquitetura
tests/                   PHPUnit (unit/) e Playwright (e2e/)
```

## Rodando localmente

Pré-requisitos: Docker Desktop (ou Docker + Compose no WSL).

```bash
cp .env.example .env
# edite o .env com os valores de desenvolvimento (ver comentarios no arquivo)
cd docker
docker compose up -d
docker compose exec app composer install
```

Site em `http://localhost:8000`, phpMyAdmin em `http://localhost:8080`, e-mails de teste
(Mailhog) em `http://localhost:8025`.

## Ambientes

- **Desenvolvimento**: local, via Docker (`WP_ENV=development`).
- **QA/staging**: `dev.<dominio>` — recebe deploy automático a cada push em `develop`, roda os
  testes de fumaça antes de qualquer promoção para produção.
- **Produção**: promovida automaticamente pelo pipeline quando os testes de QA passam (ver
  `.github/workflows/`).

## Hospedagem: HostGator

Plano compartilhado (cPanel). Confirmado por pesquisa — validar de novo na conta real depois de
contratar:

- PHP até 8.2 via "Alterar versão de PHP"/MultiPHP Manager do cPanel — configurar 8.2 para o
  domínio de produção e para o subdomínio de staging.
- Sem SSH — só SFTP/FTP (por isso o `composer install` roda no GitHub Actions, o servidor só
  recebe os arquivos prontos).
- Dá para criar subcontas de FTP restritas a uma pasta (Contas FTP no cPanel) — útil para
  isolar staging de produção, mas uma subconta restrita a `public_html` não enxerga um nível
  acima, onde o `.env` deveria ficar (ver `deploy.yml`). **A confirmar na conta real**: se o
  `.env` puder ficar fora de `public_html`; senão, ele fica dentro mesmo, protegido pelo
  `.htaccess` que o Bedrock gera (nega acesso a dotfiles).
- Criar o subdomínio de staging (`dev.<dominio>`) no cPanel antes do primeiro deploy — isso
  define a pasta que vai virar `WEB_REMOTE_PATH` de staging.

## Segredos necessários (GitHub → Settings → Environments)

`deploy.yml` usa dois **GitHub Environments**, `staging` e `production` — mesmo nome de secret
nos dois, valor diferente por ambiente. Preencher depois de ter a conta HostGator, o domínio
apontado (Cloudflare) e as contas de sandbox do Mercado Pago/Melhor Envio:

| Secret | O que é |
|---|---|
| `SFTP_HOST`, `SFTP_PORT`, `SFTP_USER`, `SFTP_PASSWORD` | Acesso SFTP do cPanel (Contas FTP) |
| `WEB_REMOTE_PATH` | Pasta remota onde o conteúdo de `web/` é publicado (`public_html` em produção, pasta do subdomínio em staging) |
| `ENV_REMOTE_PATH` | Pasta remota onde o `.env` gerado em CI é publicado (idealmente fora de `public_html`) |
| `WP_HOME` | URL completa do ambiente (`https://dev.dominio.com.br` ou `https://dominio.com.br`) |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST` | Banco MySQL criado no cPanel para o ambiente |
| `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT` | Gerar em https://roots.io/salts.html — um conjunto por ambiente, nunca reaproveitar |
| `AI_VISION_PROVIDER`, `AI_VISION_API_KEY`, `AI_BATCH_MAX_ITEMS` | Ver plano, seção do plugin de IA |
| `MERCADOPAGO_PUBLIC_KEY`, `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_SANDBOX` | Conta de desenvolvedor Mercado Pago |
| `MELHORENVIO_TOKEN`, `MELHORENVIO_SANDBOX` | Token gerado em melhorenvio.com.br/painel/gerenciar/tokens (ou sandbox.melhorenvio.com.br) — o plugin não usa OAuth/redirect URI |
| `NFE_EMISSAO_ATIVA`, `NFE_PROVEDOR`, `NFE_PROVEDOR_TOKEN` | Só produção — fica `false`/vazio até existir CNPJ (ver plano) |
| `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET` | Importação em massa (Fase 3) |
| `META_PIXEL_ID`, `META_CAPI_ACCESS_TOKEN`, `GA4_MEASUREMENT_ID` | Tracking (Fase 2) |
| `BREVO_API_KEY` | E-mail transacional |

Localmente, as mesmas variáveis vêm do `.env` (ver `.env.example`) — nunca commitar esse
arquivo. `WPSCAN_API_TOKEN` não entra aqui: é usado só pelo workflow de scan de segurança
(Fase 4), não pelo runtime do site.
