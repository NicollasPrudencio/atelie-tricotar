# Manual do Painel — Ateliê Tricotar

Bem-vinda! Este manual explica, passo a passo, como usar o painel do site do Ateliê Tricotar —
desde entrar com sua senha até publicar um produto novo. Não é preciso nenhum conhecimento
técnico para seguir este guia: escrevemos tudo pensando em quem nunca usou um painel
administrativo antes.

Se em algum momento você tentar fazer algo descrito aqui e a tela não corresponder ao que está
escrito, pare e peça ajuda antes de continuar — é melhor perguntar do que adivinhar.

> **Nota para quem mantém este documento:** este é um manual vivo, atualizado a cada
> funcionalidade nova do painel. Por enquanto vive só neste repositório, em Markdown puro.
> Quando o pipeline de CI/CD estiver pronto, ele passa a ser publicado automaticamente como um
> site, num subdomínio próprio (ex.: `docs.atelietricotar.com.br`), hospedado a partir deste
> repositório no GitHub. Até lá, é só ler o arquivo direto.

---

## Sumário

1. [Quem usa o painel](#1-quem-usa-o-painel)
2. [Como entrar no painel](#2-como-entrar-no-painel)
3. [Conhecendo o menu](#3-conhecendo-o-menu)
4. [Cadastrar um produto novo](#4-cadastrar-um-produto-novo)
5. [Cadastrar vários produtos de uma vez](#5-cadastrar-vários-produtos-de-uma-vez)
6. [Importar fotos do Google Drive](#6-importar-fotos-do-google-drive)
7. [Mostrar um trabalho no portfólio (case)](#7-mostrar-um-trabalho-no-portfólio-case)
8. [Quando a IA não está disponível](#8-quando-a-ia-não-está-disponível)
9. [Perguntas frequentes](#9-perguntas-frequentes)
10. [Só para quem administra o site](#10-só-para-quem-administra-o-site)
11. [Glossário — palavras que aparecem no painel](#11-glossário--palavras-que-aparecem-no-painel)
12. [O que vem por aí](#12-o-que-vem-por-aí)

---

## 1. Quem usa o painel

O painel tem dois níveis de acesso diferentes, dependendo do login de cada pessoa:

| | O que faz no dia a dia | O que mais vê |
|---|---|---|
| **Vendedora** | Cadastra produtos, cria vitrines de trabalhos (cases), acompanha pedidos | Nada de configuração, custo ou senha de sistema — a tela é enxuta de propósito |
| **Administrador** | Tudo que a Vendedora faz | Mais duas telas: configuração da IA e o financeiro do ateliê (Faturamento), além de conectar o Google Drive |

Se você é Vendedora, pode pular direto para a seção 4 depois de aprender a entrar no painel.
As seções marcadas **"só para quem administra"** não vão aparecer no seu menu — é normal, não é
erro.

---

## 2. Como entrar no painel

1. No navegador (Chrome, Safari, o que você usar no celular ou computador), acesse o endereço
   do painel que foi combinado com você (algo como `seusite.com.br/wp/wp-admin`).
2. Digite seu **usuário** e sua **senha** e clique em **Entrar**.
3. **Segundo passo de segurança (2FA):** depois da senha, o sistema vai pedir um **segundo
   código** antes de liberar o acesso. Isso é proposital — protege o painel mesmo se alguém
   descobrir sua senha por acidente. Existem algumas formas de receber esse código:
   - Um aplicativo autenticador no celular (como Google Authenticator) que gera um código novo
     a cada minuto.
   - Um código enviado por e-mail.
   - Um "código de backup" — uma lista de códigos de reserva, entregue quando sua conta foi
     criada, para usar caso você perca acesso às outras formas.

   Na primeira vez que você entrar, o sistema vai te guiar por uma configuração rápida dessa
   segunda etapa. Depois de configurada, você tem alguns dias de tolerância antes que ela passe
   a ser exigida em todo login — se tiver dúvida nessa configuração inicial, peça ajuda a quem
   te repassou o acesso.

**Esqueceu a senha?** Na tela de login, existe um link "Perdeu a senha?" — clique nele e siga
as instruções enviadas por e-mail. Se não tiver mais acesso ao e-mail cadastrado, peça pra quem
administra o site redefinir sua senha.

---

## 3. Conhecendo o menu

Depois de entrar, você verá uma barra de menu do lado esquerdo da tela (no celular, ela pode
aparecer atrás de um ícone de "três tracinhos" ☰ no canto). Os itens que interessam ao dia a
dia são:

- **✚ Novo Produto** — cadastrar um produto novo para vender, sozinho, em lote ou importado do
  Google Drive.
- **🖼️ Cases** — vitrine de trabalhos já entregues (sem preço, é só mostra de trabalho).
- **Pedidos** (dentro do menu WooCommerce) — acompanhar e gerenciar as vendas.
- **⭐ IA** *(só administrador)* — configurar a chave da inteligência artificial e ver o custo.
- **📊 Faturamento** *(só administrador)* — receita, custos e lucro do ateliê.

Não se preocupe com os outros itens do menu (Aparência, Plugins, Configurações etc.) — eles são
de uso técnico e não fazem parte do seu dia a dia.

---

## 4. Cadastrar um produto novo

Clique em **"Novo Produto"** no menu.

### Passo 1 — Anexar as fotos

Assim que a tela abre, ela já pede para você anexar fotos — é o primeiro passo, antes de
qualquer outra coisa.

1. Toque ou clique na área tracejada onde está escrito **"Toque para escolher as fotos do
   produto"** (ou no botão **"Escolher fotos"** logo abaixo).
2. Selecione uma ou mais fotos do produto pronto, tiradas com boa luz, mostrando bem a peça.
3. As fotos escolhidas aparecem em miniatura, uma ao lado da outra.

Se você tiver a **receita ou o padrão** usado para fazer a peça (o passo a passo da técnica),
cole o texto no campo logo abaixo das fotos, ou anexe uma foto da receita. Não é obrigatório,
mas ajuda bastante: a inteligência artificial usa essa informação para descrever melhor o
material e a técnica usados, algo que às vezes não dá para perceber só olhando a foto.

### Passo 2 — Escolher como preencher o restante

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

> **O botão "✨ Sugerir" está cinza e não deixa clicar?** Passe o mouse por cima dele (ou
> segure o dedo, no celular) que aparece uma explicação do motivo. Veja a seção
> [8. Quando a IA não está disponível](#8-quando-a-ia-não-está-disponível).

### Passo 3 — Revisar e completar as informações

Depois de escolher um dos dois caminhos acima, o formulário completo aparece na tela, com estes
campos:

- **Título** e **Descrição** — preenchidos pela IA (se você usou "Sugerir") ou em branco. Leia
  com calma e ajuste o que quiser.
- **Categoria** e **Material/técnica** — o mesmo: sugerido pela IA ou em branco.
- Um separador com o aviso **"Sempre preenchido por você"**, seguido dos campos que só uma
  pessoa sabe:
  - **Preço** (em reais).
  - **Disponibilidade** — escolha entre **"Pronta entrega"** (já está feito, pronto pra
    despachar) ou **"Sob encomenda"** (você ainda vai fazer depois da venda). Se escolher "sob
    encomenda", um campo extra aparece para você informar o **prazo de produção em dias**.
  - **Peso** e **Dimensões** (comprimento x largura x altura, em centímetros) — são usados para
    calcular o valor do frete automaticamente na loja. Preencha com atenção; se estiver muito
    errado, o frete calculado para o cliente também fica errado.

### Passo 4 — Publicar

Clique no botão **"Publicar"**. O produto some da tela de cadastro e você recebe a confirmação
de que já está visível no site.

Se preferir recomeçar do zero antes de publicar, use o botão **"Recomeçar"**.

### Por que às vezes a publicação é bloqueada

Este é um comportamento intencional do painel, vale a pena entender:

- Se você **aceitou a sugestão da IA sem mudar nada** no título e na descrição, o produto
  publica direto, sem nenhuma etapa extra.
- Se você **editou** o que a IA sugeriu, **ou** escreveu tudo **manualmente**, a IA faz uma
  segunda checagem — uma revisão de qualidade — antes de deixar publicar. Ela procura por
  coisas que dão uma impressão de loja amadora, como:
  - Seu nome (ou de outra pessoa) aparecendo no texto do produto — o texto deve falar da peça,
    não de quem fez.
  - Elogio exagerado, tipo "a peça mais linda do mundo", sem nenhum motivo concreto.
  - Linguagem muito informal ou com erros de português.
  - Um texto puramente descritivo, sem nada que desperte vontade de comprar.

Se a revisão encontrar algum desses problemas, a tela **não deixa publicar** — em vez disso,
mostra exatamente o que foi identificado e já sugere um texto corrigido, pronto pra você usar
ou ajustar do seu jeito. Depois de corrigir, é só clicar em "Publicar" de novo.

Essa checagem existe para proteger a imagem da loja — nenhum texto "amador" vai para o ar sem
passar por essa revisão.

---

## 5. Cadastrar vários produtos de uma vez

Use esta opção quando você tiver **várias fotos de produtos diferentes** para cadastrar de uma
só vez — por exemplo, um acervo antigo de fotos que ainda não está no site.

Menu **"Novo Produto" → "Criar em massa"**.

1. Clique em **"Escolher fotos"** e selecione todas as fotos que quer cadastrar de uma vez.
   Cada foto vira um produto candidato separado (existe um limite de fotos por lote — se você
   passar, a tela avisa e pede para dividir em grupos menores).
2. Clique em **"Processar lote"**.
3. Você é levada para uma tela de acompanhamento, com um quadradinho para cada foto. Cada
   quadradinho mostra um status:
   - **Processando** — a IA ainda está analisando essa foto.
   - **Pronto para revisão** — a IA já terminou, pode conferir.
   - **Erro** — algo deu errado nessa foto específica; tem um botão **"Tentar novamente"**.
   - **Revisado** — você já conferiu e confirmou esse item.

Você **não precisa esperar tudo terminar** para começar a revisar — pode ir revisando os que já
ficaram prontos enquanto o resto ainda processa em segundo plano, e pode até sair da tela e
voltar depois: o progresso continua sozinho.

4. Clique em **"Revisar"** em cada item pronto — isso abre a tela normal de edição do produto
   (a mesma da seção 4), onde você confirma o preço, a disponibilidade, o peso e publica.

---

## 6. Importar fotos do Google Drive

Para quando as fotos dos produtos já estão organizadas numa pasta do Google Drive (por
exemplo, o acervo antigo do ateliê) — importa tudo de uma vez, sem precisar baixar as fotos no
computador e depois subir uma por uma no painel.

Menu **"Novo Produto" → "Importar do Drive"**.

**Como organizar a pasta no Drive antes de importar:** dentro da pasta que você vai
compartilhar, crie uma **subpasta para cada produto**, com as fotos daquele produto dentro
dela. O nome da subpasta não precisa seguir nenhum padrão especial. Por exemplo:

```
📁 Fotos para o site
   📁 Touca de bebê rosa
      🖼️ foto1.jpg
      🖼️ foto2.jpg
   📁 Amigurumi coelho
      🖼️ foto1.jpg
```

Nesse exemplo, ao importar a pasta "Fotos para o site", o painel cria **dois** produtos
candidatos — um pra "Touca de bebê rosa" (com as duas fotos) e outro pra "Amigurumi coelho".

### Primeira vez: conectando o Google Drive

Essa etapa só precisa ser feita **uma vez**, e só quem administra o site consegue fazer (se
você é Vendedora e a tela pedir pra conectar, avise o administrador — depois de conectado uma
vez, fica valendo pra sempre, sem precisar repetir).

### Como importar

1. Compartilhe a pasta principal do Google Drive (a que contém as subpastas de cada produto)
   com o e-mail combinado com quem administra o site — use o botão **"Compartilhar"** do
   próprio Google Drive, do jeito que você já compartilha pastas normalmente.
2. No painel, copie o link dessa pasta (no Google Drive: botão direito na pasta → "Copiar
   link", ou o link que aparece na barra de endereço quando você abre a pasta).
3. Cole o link no campo **"Link da pasta compartilhada"** e clique em **"Importar"**.
4. Você é levada direto para a tela de revisão em lote (a mesma da seção 5) — cada subpasta já
   aparece como um item, com todas as suas fotos, sendo processado pela IA.

A partir daí, revisar e publicar cada produto funciona exatamente como na criação em massa.

---

## 7. Mostrar um trabalho no portfólio (case)

Um "case" é diferente de um produto: é a vitrine de um trabalho **já entregue**, mostrado como
exemplo do que o ateliê sabe fazer — **sem preço e sem botão de comprar**. Serve para mostrar
que o ateliê faz qualquer encomenda personalizada, não só o que já está catalogado na loja.

Menu **"Cases" → "Novo Case"**.

1. Anexe as fotos do trabalho pronto (mesmo jeito da tela de produto).
2. No campo **"Conte como foi esse trabalho"**, escreva livremente sobre a encomenda — o que a
   cliente pediu, algum detalhe do processo, uma dificuldade que você resolveu. Não precisa ser
   formal nem organizado: escreva como se estivesse contando pra uma colega. A IA transforma
   esse relato numa descrição de vitrine, profissional e convidativa. Esse campo é opcional,
   mas quanto mais você contar, melhor fica o resultado.
3. Escolha **"✨ Sugerir"** (a IA escreve o título e a descrição a partir das fotos e do seu
   relato) ou **"Preencher manualmente"**.
4. Revise o título e a descrição e clique em **"Publicar"**.

A mesma regra de revisão de qualidade da seção 4 vale aqui: se você editar o que a IA sugeriu,
ou escrever tudo manualmente, o texto passa pela checagem antes de publicar.

---

## 8. Quando a IA não está disponível

De vez em quando, por algum motivo fora do nosso controle (por exemplo, uma instabilidade do
serviço de inteligência artificial usado pelo site), a IA pode ficar temporariamente
indisponível. Quando isso acontece:

- O botão **"✨ Sugerir"** aparece **cinza, desabilitado**, em qualquer tela onde ele existe.
- Se você passar o mouse por cima dele (ou segurar o dedo, no celular), aparece um **balão de
  aviso** explicando o motivo.
- Uma mensagem na tela também avisa da indisponibilidade.

**O que fazer nesse caso:** use o botão **"Preencher manualmente"** normalmente — o cadastro de
produto e de case continua funcionando 100% sem a IA, só que sem o preenchimento automático.
O texto que você escrever manualmente ainda passa pela revisão de qualidade antes de publicar
(a única etapa que depende da IA estar de volta é justamente essa revisão — se ela também
estiver indisponível, o aviso na tela vai dizer isso especificamente, diferente do aviso de
"achei um problema no texto").

Se a indisponibilidade continuar por muito tempo, avise quem administra o site.

---

## 9. Perguntas frequentes

**Publiquei um produto e me arrependi do preço/da descrição. Como corrijo?**
Vá até a listagem de produtos do site (menu do WooCommerce, "Produtos" → "Todos os produtos"),
encontre o produto e edite normalmente pela tela padrão do WordPress. O painel de criação
rápida é só para o momento de cadastrar; edições depois de publicado são feitas ali.

**Posso cadastrar um produto sem foto?**
Não. O painel sempre pede pelo menos uma foto antes de liberar os outros campos — é assim de
propósito, já que uma loja sem foto não vende.

**A IA sugeriu um título ou descrição que eu não gostei. Preciso usar do jeito que veio?**
Não, de jeito nenhum. Edite à vontade — é só um rascunho. Se você mudar o texto sugerido, a
revisão de qualidade roda de novo automaticamente antes de publicar, então não tem risco de um
texto ruim ir para o ar sem essa checagem.

**Por que às vezes aparece um valor de custo ao lado do botão "Sugerir"?**
É uma estimativa de quanto aquela consulta à inteligência artificial custa para o ateliê — os
valores são bem pequenos (geralmente frações de centavo), e servem só para dar transparência.
Quem administra o site acompanha o total gasto na tela "IA".

**Esqueci meu segundo código de login (2FA) e não consigo entrar.**
Peça para quem administra o site te ajudar a reconfigurar — por segurança, isso não pode ser
feito sozinha sem acesso a pelo menos um dos métodos de verificação já cadastrados.

**Importei uma pasta do Drive e nenhum produto apareceu. Por quê?**
Confira se a pasta que você compartilhou tem **subpastas** dentro dela (cada subpasta vira um
produto — uma pasta só com fotos soltas, sem subpastas, não é reconhecida). Confira também se
o link colado é da pasta certa.

---

## 10. Só para quem administra o site

As telas abaixo só aparecem para o login de Administrador.

### Tela "IA"

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

### Tela "Faturamento"

Menu **"Faturamento"** (ícone de gráfico 📊).

Mostra, num só lugar, quanto o ateliê ganhou e quanto gastou num período escolhido (este mês,
mês passado, últimos 30 dias, ou um intervalo de datas específico):

- **Receita** — soma de tudo que foi vendido e pago no período.
- **Custo de IA** — o mesmo total que aparece na tela "IA".
- **Taxa Mercado Pago** — quanto a plataforma de pagamento cobrou em cada venda. Se algum
  pedido não tiver essa informação disponível ainda, a tela avisa quantos pedidos ficaram de
  fora da conta, para não passar a impressão errada de que a taxa foi zero.
- **Frete pago** — o valor do frete cobrado dos clientes (o ateliê não adiciona nenhuma margem
  em cima do frete, então esse valor já representa o custo real repassado pela transportadora).
- **Despesas manuais** — custos do dia a dia do ateliê que não vêm de nenhuma venda, como
  hospedagem do site ou domínio. Existe um formulário simples nessa mesma tela para lançar
  (descrição, valor e data) e remover despesas.
- **Lucro líquido** — o resultado final: receita menos todos os custos acima, destacado em
  verde quando positivo e em vermelho quando negativo.

### Conectar o Google Drive

Dentro de **"Novo Produto" → "Importar do Drive"**, quando ainda não conectado: botão
**"Conectar ao Google Drive"**, que leva pra tela de login do Google. Depois de autorizar, a
conexão fica **permanente**, salva na conta que fez a autorização — não é preciso repetir isso,
mesmo depois de meses. Se um dia for necessário trocar de conta (ex.: o desenvolvedor que
cuida do site mudar), existe um botão **"Desconectar"** na mesma tela, e o processo de conectar
pode ser refeito com a conta nova.

---

## 11. Glossário — palavras que aparecem no painel

- **IA (inteligência artificial)** — o serviço que analisa as fotos e sugere textos para você.
  Não substitui seu julgamento: é sempre um rascunho para revisar.
- **Custo estimado** — quanto uma consulta à IA custa para o ateliê, aproximadamente. Valores
  costumam ser bem pequenos.
- **Lote** — um grupo de vários produtos sendo cadastrados de uma vez, na tela "Criar em
  massa" ou na importação do Google Drive.
- **Case** — um trabalho já entregue, mostrado como vitrine no portfólio, sem preço.
- **Sob encomenda** — quando o produto ainda será feito depois da compra, dentro de um prazo
  informado. O oposto de "pronta entrega".
- **2FA (segundo fator de autenticação)** — a etapa extra de segurança no login, além da senha.

---

## 12. O que vem por aí

Estas funcionalidades estão planejadas, mas ainda não estão disponíveis no painel:

- **Editar uma foto com IA** a partir de um pedido em texto (por exemplo, "deixe o fundo
  branco") — o recurso já está preparado internamente, mas ainda depende de uma configuração
  adicional de cobrança numa conta do Google antes de poder ser testado e liberado de verdade.
- **Buscar um padrão ou receita em outro idioma na internet e traduzir para português**
  automaticamente.

Este manual será atualizado assim que cada uma dessas novidades estiver disponível.
