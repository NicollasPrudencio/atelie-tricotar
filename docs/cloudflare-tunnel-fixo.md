# Túnel fixo do Cloudflare (ambiente de desenvolvimento)

Guia pra expor o ambiente local (`localhost:8000`) numa URL pública **fixa**, via domínio
próprio no Cloudflare — necessário pra testar integrações que exigem callback público (Mercado
Pago, Melhor Envio). Diferente do "túnel rápido" (`cloudflared tunnel --url ...`), que gera um
endereço aleatório novo a cada execução, este fica sempre no mesmo endereço.

`cloudflared` já está instalado localmente (via winget).

## 1. Domínio no Cloudflare

1. Registrar o domínio (fora do Cloudflare, em qualquer registrador).
2. No painel do Cloudflare: **Add a site** → digitar o domínio → escolher o plano **Free**.
3. O Cloudflare mostra dois nameservers — trocar os nameservers do domínio no registrador para
   esses (fora do Cloudflare, no painel do registrador).
4. Esperar a propagação (o Cloudflare avisa por e-mail quando o domínio fica ativo — geralmente
   minutos a poucas horas, pode levar até 24h em casos raros).

## 2. Login do cloudflared na conta

```powershell
cloudflared tunnel login
```

Abre uma URL no navegador — logar na conta Cloudflare e selecionar o domínio (zona) pra
autorizar. Isso salva um certificado local (`~/.cloudflared/cert.pem`) usado nos próximos
comandos.

## 3. Criar o túnel nomeado

```powershell
cloudflared tunnel create atelie-dev
```

Gera um ID de túnel e um arquivo de credenciais em `~/.cloudflared/<tunnel-id>.json`. **Guardar
esse ID** (aparece na saída do comando).

## 4. Apontar o DNS pro túnel

```powershell
cloudflared tunnel route dns atelie-dev dev.SEUDOMINIO.com.br
```

Cria automaticamente o registro CNAME no Cloudflare — não precisa mexer manualmente no painel de
DNS.

## 5. Arquivo de configuração

Criar `~/.cloudflared/config.yml` (ajustar `SEUDOMINIO` e o caminho do arquivo de credenciais
gerado no passo 3):

```yaml
tunnel: atelie-dev
credentials-file: C:\Users\<usuario>\.cloudflared\<tunnel-id>.json
ingress:
  - hostname: dev.SEUDOMINIO.com.br
    service: http://localhost:8000
  - service: http_status:404
```

## 6. Rodar o túnel

```powershell
cloudflared tunnel run atelie-dev
```

Enquanto esse comando estiver rodando, `https://dev.SEUDOMINIO.com.br` aponta pro
`localhost:8000` local. Pra deixar rodando sempre em segundo plano (sem depender de um terminal
aberto), instalar como serviço do Windows:

```powershell
cloudflared service install
```

## 7. Atualizar o projeto

Depois de configurado, trocar no `.env` local:

```
WP_HOME=https://dev.SEUDOMINIO.com.br
WP_SITEURL=${WP_HOME}/wp
```

E, como esse endereço agora é fixo, não precisa mais trocar a cada sessão de teste — só quando
alternar entre testar via túnel (integrações externas) e via `localhost:8000` puro (navegação
comum, mais rápida por não depender da rede).
