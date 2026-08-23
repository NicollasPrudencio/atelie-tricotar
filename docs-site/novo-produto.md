---
title: Cadastrar produto novo
nav_order: 3
permalink: /novo-produto/
has_children: true
---

# Cadastrar um produto novo

Menu **"Produtos" → "Novo Produto"**. Essa tela sempre cria **um produto só** por vez — para
cadastrar vários produtos diferentes de uma vez, veja
[Importar vários produtos do Google Drive](/novo-produto/importar-drive/).

## Passo 1 — Anexar as fotos

Assim que a tela abre, ela já pede para você anexar fotos — é o primeiro passo, antes de
qualquer outra coisa.

1. Toque ou clique na área tracejada onde está escrito **"Toque para escolher as fotos do
   produto"** (ou no botão **"Escolher fotos"** logo abaixo).
2. Selecione uma ou mais fotos do produto pronto (até **10 fotos**, todas do mesmo produto —
   ângulos ou momentos diferentes da mesma peça), tiradas com boa luz, mostrando bem a peça. A
   11ª foto não é aceita — o painel avisa na hora.
3. As fotos escolhidas aparecem em miniatura, uma ao lado da outra.

Se você tiver a **receita ou o padrão** usado para fazer a peça (o passo a passo da técnica),
cole o texto no campo logo abaixo das fotos, ou anexe uma foto da receita. Não é obrigatório,
mas ajuda bastante: a inteligência artificial usa essa informação para descrever melhor o
material e a técnica usados, algo que às vezes não dá para perceber só olhando a foto.

## Passo 2 — Escolher como preencher o restante

Depois de anexar pelo menos uma foto, dois botões ficam disponíveis:

- **✨ Sugerir** — a inteligência artificial (chamamos de "IA" no painel) olha a foto (e a
  receita, se você anexou) e preenche sozinha o título, a descrição, a categoria e o material
  do produto. Cada campo que ela preencheu aparece com uma marquinha **"✨ sugerido"** ao lado —
  é um rascunho pronto para você revisar, não é definitivo.
- **Preencher manualmente** — pula a etapa da IA e abre o formulário em branco, para você
  escrever tudo com suas próprias palavras.

Ao lado do botão "✨ Sugerir" aparece quanto, aproximadamente, aquela consulta à IA vai custar
(por exemplo, "~R$ 0,003 nesta chamada") — é um valor muito pequeno, mostrado só para
transparência de custo do ateliê.

> **O botão "✨ Sugerir" está cinza e não deixa clicar?** Veja
> [Quando a IA não está disponível](/ia-indisponivel/).

## Passo 3 — Revisar e completar as informações

Depois de escolher um dos dois caminhos acima, o formulário completo aparece na tela, com estes
campos:

- **Título** e **Descrição** — preenchidos pela IA (se você usou "Sugerir") ou em branco. Leia
  com calma e ajuste o que quiser.
- **Categoria** e **Material/técnica** — o mesmo: sugerido pela IA ou em branco.
- Um separador com o aviso **"Sempre preenchido por você"**, seguido dos campos que só uma
  pessoa sabe:
  - **Preço** (em reais) — obrigatório.
  - **Disponibilidade** — escolha entre **"Pronta entrega"** (já está feito, pronto pra
    despachar) ou **"Sob encomenda"** (você ainda vai fazer depois da venda, valor padrão). Se
    escolher "sob encomenda", um campo extra aparece para você informar o **prazo de produção
    em dias**.
  - **Peso** e **Dimensões** (comprimento × largura × altura, em centímetros) — usados para
    calcular o valor do frete automaticamente na loja.

    ⚠️ **Esses dois campos não são obrigatórios — mas deixar em branco tem um custo real**: se
    você publicar sem preencher, o cálculo de frete usa um valor padrão genérico (uma caixinha
    de 10×10×10cm, 11g) em vez do tamanho de verdade da peça. Pra peças maiores ou mais
    pesadas que isso, o frete cobrado do cliente sai errado (baixo demais), e quem perde a
    diferença é o ateliê. Vale sempre preencher com o peso/medida real antes de publicar.

## Passo 4 — Publicar

Clique no botão **"Publicar"**. O produto some da tela de cadastro e você recebe a confirmação
de que já está visível no site.

Se preferir recomeçar do zero antes de publicar, use o botão **"Recomeçar"**.

## Por que às vezes a publicação é bloqueada

Este é um comportamento intencional do painel, vale a pena entender:

- Se você **aceitou a sugestão da IA sem mudar nada** no título e na descrição, o produto
  publica direto, sem nenhuma etapa extra.
- Se você **editou** o que a IA sugeriu, **ou** escreveu tudo **manualmente**, a IA faz uma
  segunda checagem — uma revisão de qualidade — antes de deixar publicar. Ela procura por
  coisas que dão uma impressão de loja amadora, especificamente:
  1. Seu nome (ou de outra pessoa) aparecendo no texto do produto — o texto deve falar da peça,
     não de quem fez.
  2. Elogio exagerado ou autopromoção vazia, tipo "a peça mais linda do mundo", sem nenhum
     motivo concreto.
  3. Linguagem muito informal ou com erros de português.
  4. Ausência completa de qualquer gatilho de venda — um texto puramente descritivo, que não
     desperta vontade de comprar.

Se a revisão encontrar algum desses problemas, a tela **não deixa publicar** — em vez disso,
mostra exatamente o que foi identificado e já sugere um texto corrigido, pronto pra você usar
ou ajustar do seu jeito. Depois de corrigir, é só clicar em "Publicar" de novo.

**Não existe uma forma de pular essa checagem.** Inclusive se a própria IA estiver indisponível
na hora da checagem, o painel trata isso como bloqueio — nunca deixa passar um texto sem
verificar por conta de uma falha técnica. Essa checagem existe para proteger a imagem da loja:
nenhum texto "amador" vai para o ar sem passar por essa revisão.
