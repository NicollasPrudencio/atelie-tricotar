/**
 * Tela "Criar em Massa" — seleciona fotos soltas na Biblioteca de Mídia
 * (wp.media nativo), manda pra IA agrupar por "mesma peça", deixa corrigir
 * antes de confirmar, e só então cria os produtos (reaproveita o mesmo
 * fluxo de lote/Pendências que a importação do Drive já usa).
 */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var btnEscolher = document.getElementById("atelie-massa-btn-escolher");
        var btnAgrupar = document.getElementById("atelie-massa-btn-agrupar");
        var btnCriar = document.getElementById("atelie-massa-btn-criar");
        var contador = document.getElementById("atelie-massa-contador");
        var preview = document.getElementById("atelie-massa-preview");
        var statusEl = document.getElementById("atelie-massa-status");
        var custoEstimado = document.getElementById("atelie-massa-custo-estimado");
        var secaoRevisao = document.getElementById("atelie-massa-revisao");
        var gruposEl = document.getElementById("atelie-massa-grupos");
        var totalProdutosEl = document.getElementById("atelie-massa-total-produtos");
        var form = document.getElementById("atelie-massa-form");
        var inputGruposJson = document.getElementById("atelie-massa-grupos-json");

        if (!btnEscolher) {
            return; // não é a tela "Criar em Massa"
        }

        var fotosSelecionadas = []; // [{id, url}]
        var grupos = []; // [[{id,url}, {id,url}], [{id,url}], ...]
        var frame = null;

        function formatarReal(valor) {
            return "R$ " + Number(valor || 0).toFixed(4).replace(".", ",");
        }

        function atualizarContador() {
            var n = fotosSelecionadas.length;
            contador.textContent = n === 0
                ? "Nenhuma foto selecionada"
                : n + " foto" + (n === 1 ? "" : "s") + " selecionada" + (n === 1 ? "" : "s") + (n > atelieMassaIA.limiteFotos ? " — passou do limite de " + atelieMassaIA.limiteFotos + "!" : "");
            contador.style.color = n > atelieMassaIA.limiteFotos ? "#c0392b" : "";
            btnAgrupar.disabled = n === 0 || n > atelieMassaIA.limiteFotos || !atelieMassaIA.iaDisponivel;
        }

        function renderizarPreview() {
            preview.innerHTML = "";
            fotosSelecionadas.forEach(function (foto) {
                var item = document.createElement("div");
                item.className = "atelie-foto-item";
                var img = document.createElement("img");
                img.src = foto.url;
                img.className = "atelie-foto-thumb";
                item.appendChild(img);
                preview.appendChild(item);
            });
        }

        btnEscolher.addEventListener("click", function () {
            if (!frame) {
                frame = wp.media({
                    title: "Selecionar fotos pra criar em massa",
                    button: { text: "Usar estas fotos" },
                    multiple: true,
                    library: { type: "image" },
                });

                frame.on("select", function () {
                    var selecao = frame.state().get("selection").toJSON();
                    fotosSelecionadas = selecao.map(function (item) {
                        return {
                            id: item.id,
                            url: (item.sizes && item.sizes.thumbnail) ? item.sizes.thumbnail.url : item.url,
                        };
                    });
                    renderizarPreview();
                    atualizarContador();
                    secaoRevisao.style.display = "none";
                    statusEl.style.display = "none";
                });
            }
            frame.open();
        });

        btnAgrupar.addEventListener("click", function () {
            btnAgrupar.disabled = true;
            statusEl.style.display = "block";
            statusEl.textContent = "Analisando " + fotosSelecionadas.length + " foto(s) e agrupando por peça…";

            fetch(atelieMassaIA.agruparUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": atelieMassaIA.nonce,
                },
                body: JSON.stringify({ fotos_ids: fotosSelecionadas.map(function (f) { return f.id; }) }),
            })
                .then(function (resposta) {
                    return resposta.json().then(function (dados) {
                        return { ok: resposta.ok, dados: dados };
                    });
                })
                .then(function (resultado) {
                    btnAgrupar.disabled = false;

                    if (!resultado.ok) {
                        statusEl.textContent = (resultado.dados && resultado.dados.erro) || "Não deu pra agrupar as fotos agora.";
                        return;
                    }

                    statusEl.style.display = "none";
                    grupos = resultado.dados.grupos || [];
                    custoEstimado.style.display = "inline-block";
                    custoEstimado.textContent = "Custo desta chamada: " + formatarReal(resultado.dados.custo);
                    renderizarGrupos();
                    secaoRevisao.style.display = "block";
                    secaoRevisao.scrollIntoView({ behavior: "smooth", block: "start" });
                })
                .catch(function () {
                    btnAgrupar.disabled = false;
                    statusEl.textContent = "Erro de conexão. Tente de novo.";
                });
        });

        function removerFotoDoGrupo(indiceGrupo, indiceFoto) {
            var foto = grupos[indiceGrupo][indiceFoto];
            grupos[indiceGrupo].splice(indiceFoto, 1);
            if (grupos[indiceGrupo].length === 0) {
                grupos.splice(indiceGrupo, 1);
            }
            grupos.push([foto]); // a foto removida vira produto próprio
            renderizarGrupos();
        }

        function renderizarGrupos() {
            gruposEl.innerHTML = "";
            totalProdutosEl.textContent = grupos.length;

            grupos.forEach(function (grupo, indiceGrupo) {
                var card = document.createElement("div");
                card.className = "atelie-massa-grupo-card";

                var titulo = document.createElement("strong");
                titulo.textContent = "Produto " + (indiceGrupo + 1) + " (" + grupo.length + " foto" + (grupo.length === 1 ? "" : "s") + ")";
                card.appendChild(titulo);

                var fotosWrap = document.createElement("div");
                fotosWrap.className = "atelie-massa-grupo-fotos";

                grupo.forEach(function (foto, indiceFoto) {
                    var item = document.createElement("div");
                    item.className = "atelie-massa-grupo-foto";

                    var img = document.createElement("img");
                    img.src = foto.url;
                    item.appendChild(img);

                    if (grupo.length > 1) {
                        var remover = document.createElement("button");
                        remover.type = "button";
                        remover.className = "atelie-massa-remover-foto";
                        remover.textContent = "×";
                        remover.title = "Tirar essa foto desse grupo";
                        remover.addEventListener("click", function () {
                            removerFotoDoGrupo(indiceGrupo, indiceFoto);
                        });
                        item.appendChild(remover);
                    }

                    fotosWrap.appendChild(item);
                });

                card.appendChild(fotosWrap);
                gruposEl.appendChild(card);
            });
        }

        btnCriar.addEventListener("click", function () {
            if (grupos.length === 0) {
                return;
            }
            if (!window.confirm("Criar " + grupos.length + " produto(s) a partir desses grupos?")) {
                return;
            }
            inputGruposJson.value = JSON.stringify(
                grupos.map(function (grupo) {
                    return grupo.map(function (foto) { return foto.id; });
                })
            );
            form.submit();
        });
    });
})();
