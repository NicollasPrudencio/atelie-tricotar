---
title: Tela "IA"
parent: Só para quem administra o site
nav_order: 1
permalink: /admin/ia/
---

# Tela "IA"

Menu **"IA"** (ícone de estrela ⭐).

- **Status da conexão** — mostra se a inteligência artificial está funcionando neste momento, e
  quando foi a última verificação (o sistema confere sozinho uma vez por dia, e também na hora
  se alguém abrir uma tela que precisa dela e a informação estiver desatualizada). O botão
  **"Testar conexão agora"** força uma nova checagem imediata.
- **Provedor e chave de API** — hoje o ateliê usa o Google Gemini. A "chave de API" é como uma
  senha que autoriza o site a usar o serviço de IA em nome do ateliê; ela é gerada
  gratuitamente em [aistudio.google.com/app/apikey](https://aistudio.google.com/app/apikey).
  Trocar a chave aqui é simples — cole a nova chave e clique em "Salvar e testar", sem precisar
  de nenhuma ajuda técnica.
- **Custos** — mostra quanto foi gasto com IA no mês, um histórico das últimas consultas, e
  permite configurar um **aviso automático** para quando o gasto do mês ultrapassar um valor
  definido por você. Também é possível ajustar a tabela de preço usada para calcular as
  estimativas (o preço do serviço muda de tempos em tempos e vale conferir periodicamente).

## Conectar o Google Drive

Dentro de [Cadastrar produto novo](/novo-produto/), quando ainda não conectado: botão
**"Conectar Google Drive"**, ao lado de "Escolher fotos", que leva pra tela de login do Google.
Depois de autorizar, a conexão fica **permanente**, salva na conta que fez a autorização — não
é preciso repetir isso, mesmo depois de meses. Se um dia for necessário trocar de conta (ex.: o
desenvolvedor que cuida do site mudar), existe um link **"desconectar"** na mesma tela (ao lado
do e-mail da conta conectada), e o processo de conectar pode ser refeito com a conta nova.
