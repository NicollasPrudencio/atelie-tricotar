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

## Segredos

Nunca commitar `.env`. Em CI/deploy, os segredos vêm do GitHub Actions Secrets. Veja
`.env.example` para a lista completa de variáveis esperadas.
