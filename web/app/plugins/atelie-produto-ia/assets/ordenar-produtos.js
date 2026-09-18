/**
 * Tela "Ordenar Produtos" — arrastar-e-soltar (jQuery UI Sortable, já vem
 * com o WordPress) pra reordenar a lista; salva sozinho a cada solta via
 * REST, sem precisar de um botão "Salvar" separado.
 */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var lista = document.getElementById("atelie-ordenar-lista");
        var status = document.getElementById("atelie-ordenar-status");

        if (!lista || !window.jQuery || !jQuery.fn.sortable) {
            return;
        }

        function mostrarStatus(texto, classeExtra) {
            status.textContent = texto;
            status.className = "atelie-status atelie-status-inline" + (classeExtra ? " " + classeExtra : "");
            status.style.display = "inline-block";
        }

        jQuery(lista).sortable({
            items: ".atelie-ordenar-item",
            handle: ".atelie-ordenar-alca",
            axis: "y",
            tolerance: "pointer",
            update: function () {
                var ordem = jQuery(lista)
                    .find(".atelie-ordenar-item")
                    .map(function () {
                        return parseInt(this.dataset.produtoId, 10);
                    })
                    .get();

                mostrarStatus("Salvando…", "");

                fetch(atelieOrdenarProdutos.reordenarUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-WP-Nonce": atelieOrdenarProdutos.nonce,
                    },
                    body: JSON.stringify({ ordem: ordem }),
                })
                    .then(function (resposta) {
                        return resposta.json().then(function (dados) {
                            return { ok: resposta.ok, dados: dados };
                        });
                    })
                    .then(function (resultado) {
                        if (resultado.ok) {
                            mostrarStatus("Ordem salva ✓", "atelie-status-ok");
                        } else {
                            mostrarStatus((resultado.dados && resultado.dados.erro) || "Não deu pra salvar a ordem agora.", "atelie-status-erro");
                        }
                    })
                    .catch(function () {
                        mostrarStatus("Erro de conexão — a ordem pode não ter sido salva.", "atelie-status-erro");
                    });
            },
        });
    });
})();
