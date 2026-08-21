# Acesso SSH ao servidor de dev (HostGator)

Hospedagem compartilhada em cPanel normalmente só libera SFTP — o **Shell Access (SSH)** precisa
ser pedido por chamado de suporte (não tem um botão de autoatendimento no cPanel pra isso).
Liberado em 2026-08-20 pra conta `nico7638` depois de abrir chamado pedindo explicitamente
("Shell access is not enabled on your account" era o erro antes de pedir).

**Isso não muda a decisão de arquitetura do deploy oficial** (`.github/workflows/deploy.yml`
continua via SFTP, sem depender de SSH — decisão registrada no `CLAUDE.md`, pensada pra
funcionar em qualquer hospedagem compartilhada barata, mesmo uma que nunca libere shell). SSH
aqui é só uma conveniência **para depuração/deploy manual pontual no ambiente de dev** durante o
desenvolvimento — mais rápido que os workarounds via SFTP puro.

## Conexão

```
ssh -i ~/.ssh/atelie_hostgator_dev -p 2222 nico7638@50.116.87.138
```

- Chave: `~/.ssh/atelie_hostgator_dev` (par gerado localmente, chave pública já autorizada no
  servidor).
- Porta **2222**, não a 22 padrão — a primeira tentativa numa porta errada falha com "Host key
  verification failed" de um jeito que engana (parece problema de host key, mas é porta errada).
- Mesmo usuário e IP usados pro SFTP (`nico7638` / `50.116.87.138`, plano M).

## O que fica disponível

- **PHP 8.3 CLI** (`php -l arquivo.php`) — dá pra lintar um arquivo antes/depois de subir, sem
  depender só de "não quebrou o curl" como sinal de sucesso.
- **WP-CLI** (`wp ...`) — permite rodar comandos WordPress direto (cache, opcache, etc.) sem o
  workaround de subir um `opcache-reset.php` temporário no docroot, chamar via `curl` várias
  vezes (pra pegar os vários workers do LiteSpeed) e apagar depois. Preferir isso a partir de
  agora pra reset de opcache/cache no ambiente de dev.
- Shell é `jailshell` (cPanel) — sandboxed, não é acesso irrestrito ao servidor todo.
