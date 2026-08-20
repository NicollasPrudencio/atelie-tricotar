(function () {
    "use strict";

    var MIME_PASTA = "application/vnd.google-apps.folder";

    document.addEventListener("DOMContentLoaded", function () {
        var btnEscolherPasta = document.getElementById("atelie-btn-escolher-pasta-drive");
        var inputPasta = document.getElementById("atelie-drive-pasta-input");
        var inputPastasIds = document.getElementById("atelie-drive-pastas-ids");
        var inputFotosSoltasIds = document.getElementById("atelie-drive-fotos-soltas-ids");
        var btnImportar = document.getElementById("atelie-btn-importar-drive");
        var formImportar = btnImportar ? btnImportar.closest("form") : null;

        if (!btnEscolherPasta || !inputPasta || !window.atelieProdutoIA || !atelieProdutoIA.driveConectado) {
            return;
        }

        // Selecao persiste entre pastas visitadas — Map id -> {tipo, nome}, so aplicada
        // aos campos ocultos/tela quando a pessoa confirma no rodape do modal.
        var selecionados = {};
        // Pilha de navegacao: [{id: null, nome: "Compartilhado comigo"}, {id: "...", nome: "Site"}, ...]
        var caminho = [{ id: null, nome: "Compartilhado comigo" }];

        var overlay = null;
        var elementos = {};

        // Digitar o link a mao limpa a selecao do modal (evita mandar os dois ao mesmo tempo,
        // com significados diferentes: um item so vs. varios pasta/fotos escolhidos).
        inputPasta.addEventListener("input", function () {
            inputPastasIds.value = "";
            inputFotosSoltasIds.value = "";
            btnImportar.disabled = inputPasta.value.trim() === "";
        });

        function construirModal() {
            overlay = document.createElement("div");
            overlay.className = "atelie-drive-modal-overlay";
            overlay.innerHTML =
                '<div class="atelie-drive-modal" role="dialog" aria-modal="true" aria-label="Escolher pasta(s) e/ou foto(s) do Drive">' +
                '  <div class="atelie-drive-modal-cabecalho">' +
                '    <nav class="atelie-drive-breadcrumb"></nav>' +
                '    <button type="button" class="atelie-drive-modal-fechar" aria-label="Fechar">&times;</button>' +
                "  </div>" +
                '  <div class="atelie-drive-modal-corpo">' +
                '    <p class="atelie-drive-carregando">Carregando…</p>' +
                '    <div class="atelie-drive-grid" style="display:none;"></div>' +
                '    <p class="atelie-drive-vazio" style="display:none;">Nada aqui — pastas e fotos aparecem juntas.</p>' +
                "  </div>" +
                '  <div class="atelie-drive-modal-rodape">' +
                '    <span class="atelie-drive-modal-contagem">Nada selecionado</span>' +
                '    <button type="button" class="button atelie-drive-modal-cancelar">Cancelar</button>' +
                '    <button type="button" class="button button-primary atelie-drive-modal-confirmar" disabled>Selecionar</button>' +
                "  </div>" +
                "</div>";

            document.body.appendChild(overlay);

            elementos.breadcrumb = overlay.querySelector(".atelie-drive-breadcrumb");
            elementos.carregando = overlay.querySelector(".atelie-drive-carregando");
            elementos.grid = overlay.querySelector(".atelie-drive-grid");
            elementos.vazio = overlay.querySelector(".atelie-drive-vazio");
            elementos.contagem = overlay.querySelector(".atelie-drive-modal-contagem");
            elementos.confirmar = overlay.querySelector(".atelie-drive-modal-confirmar");

            overlay.querySelector(".atelie-drive-modal-fechar").addEventListener("click", fecharModal);
            overlay.querySelector(".atelie-drive-modal-cancelar").addEventListener("click", fecharModal);
            overlay.addEventListener("click", function (evento) {
                if (evento.target === overlay) {
                    fecharModal();
                }
            });
            elementos.confirmar.addEventListener("click", confirmarSelecao);
        }

        function abrirModal() {
            selecionados = {};
            caminho = [{ id: null, nome: "Compartilhado comigo" }];
            if (!overlay) {
                construirModal();
            }
            atualizarContagem();
            overlay.style.display = "flex";
            carregarPasta();
        }

        function fecharModal() {
            overlay.style.display = "none";
        }

        function renderizarBreadcrumb() {
            elementos.breadcrumb.innerHTML = "";
            caminho.forEach(function (nivel, indice) {
                if (indice > 0) {
                    var separador = document.createElement("span");
                    separador.textContent = " / ";
                    elementos.breadcrumb.appendChild(separador);
                }
                var link = document.createElement("button");
                link.type = "button";
                link.className = "atelie-drive-breadcrumb-item";
                link.textContent = nivel.nome;
                if (indice === caminho.length - 1) {
                    link.disabled = true;
                } else {
                    link.addEventListener("click", function () {
                        caminho = caminho.slice(0, indice + 1);
                        carregarPasta();
                    });
                }
                elementos.breadcrumb.appendChild(link);
            });
        }

        function carregarPasta() {
            renderizarBreadcrumb();
            elementos.grid.style.display = "none";
            elementos.vazio.style.display = "none";
            elementos.carregando.style.display = "block";

            var pastaAtual = caminho[caminho.length - 1].id;
            var url = atelieProdutoIA.driveListarUrl + (pastaAtual ? "?pasta=" + encodeURIComponent(pastaAtual) : "");

            fetch(url, { headers: { "X-WP-Nonce": atelieProdutoIA.nonce } })
                .then(function (resposta) {
                    return resposta.json().then(function (dados) {
                        return { ok: resposta.ok, dados: dados };
                    });
                })
                .then(function (resultado) {
                    elementos.carregando.style.display = "none";

                    if (!resultado.ok) {
                        elementos.vazio.textContent = (resultado.dados && resultado.dados.erro) || "Não deu pra listar agora.";
                        elementos.vazio.style.display = "block";
                        return;
                    }

                    var itens = resultado.dados.itens || [];
                    if (itens.length === 0) {
                        elementos.vazio.textContent = "Vazio, ou só tem arquivos que não são pasta nem foto.";
                        elementos.vazio.style.display = "block";
                        return;
                    }

                    renderizarGrid(itens);
                    elementos.grid.style.display = "grid";
                })
                .catch(function () {
                    elementos.carregando.style.display = "none";
                    elementos.vazio.textContent = "Erro de conexão — tente de novo.";
                    elementos.vazio.style.display = "block";
                });
        }

        function renderizarGrid(itens) {
            elementos.grid.innerHTML = "";

            // Pastas primeiro, depois fotos — mais previsivel de navegar.
            var ordenados = itens.slice().sort(function (a, b) {
                var aPasta = a.mimeType === MIME_PASTA ? 0 : 1;
                var bPasta = b.mimeType === MIME_PASTA ? 0 : 1;
                return aPasta - bPasta;
            });

            ordenados.forEach(function (item) {
                var ehPasta = item.mimeType === MIME_PASTA;

                var tile = document.createElement("div");
                tile.className = "atelie-drive-item" + (ehPasta ? " atelie-drive-item-pasta" : "");

                var check = document.createElement("button");
                check.type = "button";
                check.className = "atelie-drive-item-check";
                check.setAttribute("aria-label", "Selecionar " + item.name);
                atualizarCheck(check, item.id in selecionados);
                check.addEventListener("click", function (evento) {
                    evento.stopPropagation();
                    alternarSelecao(item, check);
                });
                tile.appendChild(check);

                var thumb = document.createElement("div");
                thumb.className = "atelie-drive-item-thumb";
                if (ehPasta) {
                    thumb.textContent = "📁";
                } else if (item.thumbnailLink) {
                    var img = document.createElement("img");
                    img.src = item.thumbnailLink;
                    img.alt = "";
                    img.onerror = function () {
                        thumb.textContent = "🖼️";
                    };
                    thumb.appendChild(img);
                } else {
                    thumb.textContent = "🖼️";
                }
                tile.appendChild(thumb);

                var nome = document.createElement("div");
                nome.className = "atelie-drive-item-nome";
                nome.textContent = item.name;
                nome.title = item.name;
                tile.appendChild(nome);

                if (ehPasta) {
                    // Clicar na miniatura/nome navega pra dentro — selecionar a pasta em
                    // si (sem entrar) e so pelo quadradinho de marcar.
                    tile.addEventListener("click", function () {
                        caminho.push({ id: item.id, nome: item.name });
                        carregarPasta();
                    });
                } else {
                    tile.addEventListener("click", function () {
                        alternarSelecao(item, check);
                    });
                }

                elementos.grid.appendChild(tile);
            });
        }

        function atualizarCheck(check, marcado) {
            check.classList.toggle("atelie-drive-item-check-marcado", marcado);
            check.textContent = marcado ? "✓" : "";
        }

        function alternarSelecao(item, check) {
            var ehPasta = item.mimeType === MIME_PASTA;
            if (item.id in selecionados) {
                delete selecionados[item.id];
                atualizarCheck(check, false);
            } else {
                selecionados[item.id] = { tipo: ehPasta ? "pasta" : "foto", nome: item.name, mimeType: item.mimeType };
                atualizarCheck(check, true);
            }
            atualizarContagem();
        }

        function atualizarContagem() {
            var pastas = 0;
            var fotos = 0;
            Object.keys(selecionados).forEach(function (id) {
                if (selecionados[id].tipo === "pasta") {
                    pastas++;
                } else {
                    fotos++;
                }
            });

            var partes = [];
            if (pastas) {
                partes.push(pastas + " pasta" + (pastas === 1 ? "" : "s"));
            }
            if (fotos) {
                partes.push(fotos + " foto" + (fotos === 1 ? "" : "s") + " solta" + (fotos === 1 ? "" : "s"));
            }
            elementos.contagem.textContent = partes.length ? partes.join(" + ") + " selecionado" + (pastas + fotos === 1 ? "" : "s") : "Nada selecionado";
            elementos.confirmar.disabled = pastas + fotos === 0;
        }

        function confirmarSelecao() {
            var pastas = [];
            var fotos = [];
            Object.keys(selecionados).forEach(function (id) {
                var item = selecionados[id];
                if (item.tipo === "pasta") {
                    pastas.push(id);
                } else {
                    fotos.push({ id: id, name: item.nome, mimeType: item.mimeType });
                }
            });

            fecharModal();

            if (pastas.length === 0 && fotos.length > 0) {
                // So fotos soltas: baixa e entra no MESMO fluxo de "Escolher fotos" —
                // um produto so, gerado na hora, sem passar pela fila de lote.
                baixarFotosSoltasInline(fotos);
                return;
            }

            // Tem pasta (com ou sem fotos soltas junto): cada pasta vira um produto,
            // as fotos soltas (se tiver) viram mais um — sempre via fila de lote,
            // ja que sao varios produtos de uma vez. Confirma UMA vez so (aqui) e ja
            // envia — sem pedir pra clicar em outro botao "Importar" na tela depois.
            inputPastasIds.value = pastas.join(",");
            inputFotosSoltasIds.value = fotos.map(function (f) { return f.id; }).join(",");
            inputPasta.value = "";

            var totalProdutos = pastas.length + (fotos.length > 0 ? 1 : 0);
            var partes = [];
            if (pastas.length) {
                partes.push(pastas.length + " pasta" + (pastas.length === 1 ? "" : "s"));
            }
            if (fotos.length) {
                partes.push(fotos.length + " foto" + (fotos.length === 1 ? "" : "s") + " solta" + (fotos.length === 1 ? "" : "s"));
            }
            var mensagem = "Isso vai criar " + totalProdutos + " produto" + (totalProdutos === 1 ? "" : "s")
                + " a partir de " + partes.join(" + ") + ". Continuar?";

            if (formImportar && confirm(mensagem)) {
                formImportar.submit();
            }
        }

        function baixarFotosSoltasInline(fotos) {
            var vagas = window.AtelieNovoProduto ? window.AtelieNovoProduto.vagasDisponiveis() : fotos.length;
            if (fotos.length > vagas) {
                alert("Máximo de " + vagas + " foto(s) — só as primeiras " + vagas + " foram enviadas.");
                fotos = fotos.slice(0, vagas);
            }
            if (fotos.length === 0) {
                return;
            }

            var textoOriginal = btnEscolherPasta.textContent;
            btnEscolherPasta.disabled = true;
            btnEscolherPasta.textContent = "Baixando " + fotos.length + " foto(s) do Drive…";

            fetch(atelieProdutoIA.driveBaixarFotosUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": atelieProdutoIA.nonce,
                },
                body: JSON.stringify({ fotos: fotos }),
            })
                .then(function (resposta) {
                    return resposta.json().then(function (dados) {
                        return { ok: resposta.ok, dados: dados };
                    });
                })
                .then(function (resultado) {
                    btnEscolherPasta.disabled = false;
                    btnEscolherPasta.textContent = textoOriginal;

                    if (!resultado.ok || !resultado.dados.fotos) {
                        alert((resultado.dados && resultado.dados.erro) || "Não deu pra baixar as fotos do Drive agora.");
                        return;
                    }

                    window.AtelieNovoProduto.adicionarFotosExternas(resultado.dados.fotos);
                })
                .catch(function () {
                    btnEscolherPasta.disabled = false;
                    btnEscolherPasta.textContent = textoOriginal;
                    alert("Erro de conexão — tente de novo.");
                });
        }

        btnEscolherPasta.addEventListener("click", abrirModal);
    });
})();
