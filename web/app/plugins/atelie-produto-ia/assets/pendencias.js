/**
 * Tela "Pendências" — atualiza status dos itens sozinha (sem precisar clicar
 * em "Atualizar status"), com barra de progresso geral e mensagem variando
 * pra cada item ainda em processamento, pra dar sensação real de progresso
 * enquanto a IA trabalha em segundo plano.
 */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var grid = document.getElementById("atelie-lote-grid");
        if (!grid || typeof atelieLoteStatus === "undefined") {
            return; // não é a tela Pendências, ou nada pendente pra acompanhar
        }

        var barraProgresso = document.getElementById("atelie-lote-progresso-barra");
        var textoProgresso = document.getElementById("atelie-lote-progresso-texto");
        var avisoPolling = document.getElementById("atelie-lote-polling-aviso");

        var ROTULOS = {
            processando: "Processando",
            pronto: "Pronto para revisão",
            revisado: "Revisado",
            erro: "Erro",
        };

        var SUBTAREFAS = [
            "Olhando as fotos…",
            "Pensando num título…",
            "Escrevendo a descrição…",
            "Organizando categoria e material…",
            "Quase lá…",
        ];

        var intervaloPoll = null;
        var intervaloSubtarefa = null;
        var tentativas = 0;
        var LIMITE_TENTATIVAS = 150; // ~10min a cada 4s — depois disso para sozinho, evita XHR eterno em aba esquecida

        function contarPendentesNaTela() {
            return grid.querySelectorAll('.atelie-lote-card[data-status="processando"]').length;
        }

        function atualizarProgresso() {
            var cards = grid.querySelectorAll(".atelie-lote-card");
            var total = cards.length;
            var prontos = grid.querySelectorAll('.atelie-lote-card[data-status="pronto"], .atelie-lote-card[data-status="revisado"], .atelie-lote-card[data-status="erro"]').length;

            if (total === 0) {
                return;
            }

            var pct = Math.round((prontos / total) * 100);
            if (barraProgresso) {
                barraProgresso.style.width = pct + "%";
            }
            if (textoProgresso) {
                textoProgresso.textContent = prontos === total
                    ? "Tudo processado! 🎉"
                    : prontos + " de " + total + " já processados — " + (total - prontos) + " ainda em andamento.";
            }
        }

        function girarSubtarefas() {
            var pendentes = grid.querySelectorAll('.atelie-lote-card[data-status="processando"] .atelie-lote-subtarefa');
            pendentes.forEach(function (el) {
                var indiceAtual = SUBTAREFAS.indexOf(el.textContent);
                var proximo = SUBTAREFAS[(indiceAtual + 1) % SUBTAREFAS.length];
                el.textContent = proximo;
            });
        }

        function montarAcao(item) {
            if (item.status === "pronto" || item.status === "revisado") {
                return '<p><a class="button" href="' + item.editar_url + '">Revisar</a></p>';
            }
            if (item.status === "erro") {
                return '<p><em>Erro — <a href="javascript:location.reload()">atualize a página</a> pra tentar de novo.</em></p>';
            }
            return '<p><em class="atelie-lote-subtarefa">' + SUBTAREFAS[0] + "</em></p>";
        }

        function aplicarAtualizacao(item) {
            var card = document.getElementById("atelie-lote-item-" + item.id);
            if (!card) {
                return; // item novo que não estava na tela ao carregar — ignora, próximo reload pega
            }
            if (card.getAttribute("data-status") === item.status) {
                return; // nada mudou nesse item
            }

            card.setAttribute("data-status", item.status);

            var chip = card.querySelector(".atelie-chip");
            if (chip) {
                chip.className = "atelie-chip atelie-chip-" + item.status;
                chip.textContent = ROTULOS[item.status] || item.status;
            }

            var titulo = card.querySelector(".atelie-lote-card-titulo");
            if (titulo) {
                titulo.textContent = item.titulo;
            }

            var acao = card.querySelector(".atelie-lote-card-acao");
            if (acao) {
                acao.innerHTML = montarAcao(item);
            }
        }

        function consultarStatus() {
            tentativas++;
            if (tentativas > LIMITE_TENTATIVAS) {
                pararPolling("Acompanhamento automático pausado — atualize a página se ainda tiver algo pendente.");
                return;
            }

            var url = atelieLoteStatus.statusUrl + (atelieLoteStatus.lote ? "?lote=" + encodeURIComponent(atelieLoteStatus.lote) : "");

            fetch(url, {
                headers: { "X-WP-Nonce": atelieLoteStatus.nonce },
            })
                .then(function (resposta) {
                    return resposta.ok ? resposta.json() : null;
                })
                .then(function (dados) {
                    if (!dados || !dados.itens) {
                        return;
                    }
                    dados.itens.forEach(aplicarAtualizacao);
                    atualizarProgresso();

                    if (contarPendentesNaTela() === 0) {
                        pararPolling("");
                    }
                })
                .catch(function () {
                    // falha de rede pontual não para o acompanhamento — tenta de novo no próximo ciclo
                });
        }

        function pararPolling(mensagem) {
            if (intervaloPoll) {
                clearInterval(intervaloPoll);
                intervaloPoll = null;
            }
            if (intervaloSubtarefa) {
                clearInterval(intervaloSubtarefa);
                intervaloSubtarefa = null;
            }
            if (avisoPolling) {
                avisoPolling.textContent = mensagem;
            }
        }

        if (contarPendentesNaTela() > 0) {
            atualizarProgresso();
            intervaloPoll = setInterval(consultarStatus, 4000);
            intervaloSubtarefa = setInterval(girarSubtarefas, 2500);
            consultarStatus();
        } else {
            atualizarProgresso();
        }
    });
})();
