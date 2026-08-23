---
title: Desbloquear conta sem 2FA
parent: Só para quem administra o site
nav_order: 5
permalink: /admin/desbloquear-2fa/
---

# Desbloquear conta sem 2FA

Toda conta nova tem **24 horas** pra configurar o segundo passo de segurança (2FA) — enquanto
isso não acontece, a pessoa cai numa tela de configuração toda vez que tenta entrar, sem acesso
ao resto do painel. Se as 24 horas passarem sem configurar, a conta é **bloqueada** de verdade —
a pessoa não consegue mais nem tentar entrar.

## Como desbloquear

1. Vá em **Usuários** → encontre a conta bloqueada → **Editar**.
2. Role até a área de segurança/2FA do perfil.
3. Clique em **"Unlock user and reset the grace period"** — isso libera o acesso e dá mais 24
   horas pra pessoa configurar o 2FA.
4. Avise a pessoa que ela já pode tentar entrar de novo.

Não tem como a própria pessoa se desbloquear sozinha — é proposital, por segurança.
