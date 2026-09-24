(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var LIMITE_FOTOS = 10;
        var fotosIds = [];
        var receitaImagemId = null;

        var dropzoneTexto = document.getElementById("atelie-dropzone-texto");
        var fotosPreview = document.getElementById("atelie-fotos-preview");
        var btnEscolherFotos = document.getElementById("atelie-btn-escolher-fotos");
        var btnEscolherReceitaImagem = document.getElementById("atelie-btn-escolher-receita-imagem");
        var receitaImagemNome = document.getElementById("atelie-receita-imagem-nome");
        var btnSugerir = document.getElementById("atelie-btn-sugerir");
        var btnManual = document.getElementById("atelie-btn-manual");
        var analisando = document.getElementById("atelie-analisando");
        var form = document.getElementById("atelie-form-produto");
        var inputFotosIds = document.getElementById("atelie-input-fotos-ids");
        var btnRecomecar = document.getElementById("atelie-btn-recomecar");
        var fotosDicaOrdem = document.getElementById("atelie-fotos-dica-ordem");

        function atualizarBotaoSugerir() {
            btnSugerir.disabled = fotosIds.length === 0 || !atelieProdutoIA.iaDisponivel;
            btnManual.disabled = fotosIds.length === 0;
        }

        btnManual.addEventListener("click", function () {
            mostrarFormulario();
        });

        // O DOM (ordem visual das .atelie-foto-item, cada uma guardando o proprio ID em
        // data-foto-id) é a fonte da verdade — tanto o array fotosIds quanto o campo
        // oculto do formulario são sempre RECALCULADOS a partir dele, nunca o contrário.
        // Precisa ser assim porque arrastar pra reordenar muda a ordem sem passar por
        // nenhum código que sabia, antes, em que índice cada foto estava.
        function sincronizarFotosIds() {
            var itens = fotosPreview.querySelectorAll(".atelie-foto-item");
            fotosIds = Array.prototype.map.call(itens, function (el) {
                return parseInt(el.dataset.fotoId, 10);
            });
            inputFotosIds.value = fotosIds.join(",");
            dropzoneTexto.textContent = fotosIds.length + " foto(s) anexada(s)";
            fotosDicaOrdem.style.display = fotosIds.length > 1 ? "block" : "none";
            atualizarBotaoSugerir();
            atualizarBarraLote();
        }

        // Preview em tamanho original — busca a URL de verdade na hora (a miniatura na
        // tela é sempre um recorte pequeno), sem precisar guardar a URL grande de toda
        // foto anexada de antemão.
        function abrirPreview(fotoId) {
            var overlay = document.createElement("div");
            overlay.className = "atelie-preview-overlay";
            overlay.innerHTML =
                '<div class="atelie-preview-conteudo">' +
                '<button type="button" class="atelie-preview-fechar" aria-label="Fechar">&times;</button>' +
                '<p class="atelie-preview-carregando">Carregando…</p>' +
                '<img class="atelie-preview-imagem" style="display:none;" alt="Foto em tamanho original">' +
                "</div>";
            document.body.appendChild(overlay);

            var imgEl = overlay.querySelector(".atelie-preview-imagem");
            var carregandoEl = overlay.querySelector(".atelie-preview-carregando");

            function fechar() {
                overlay.remove();
                document.removeEventListener("keydown", aoTeclar);
            }
            function aoTeclar(e) {
                if (e.key === "Escape") {
                    fechar();
                }
            }
            document.addEventListener("keydown", aoTeclar);
            overlay.addEventListener("click", function (e) {
                if (e.target === overlay) {
                    fechar();
                }
            });
            overlay.querySelector(".atelie-preview-fechar").addEventListener("click", fechar);

            fetch(atelieProdutoIA.mediaUrl + fotoId)
                .then(function (resposta) {
                    return resposta.json();
                })
                .then(function (dados) {
                    if (!dados || !dados.source_url) {
                        carregandoEl.textContent = "Não deu pra carregar a imagem.";
                        return;
                    }
                    imgEl.src = dados.source_url;
                    imgEl.style.display = "block";
                    carregandoEl.style.display = "none";
                })
                .catch(function () {
                    carregandoEl.textContent = "Não deu pra carregar a imagem.";
                });
        }

        // Abre a foto no tamanho real numa NOVA GUIA. A aba é aberta na hora do clique
        // (senão o bloqueador de pop-up barra, já que a URL só chega depois do fetch)
        // e só então recebe o endereço.
        function abrirNovaGuia(fotoId) {
            var aba = window.open("", "_blank");
            fetch(atelieProdutoIA.mediaUrl + fotoId)
                .then(function (resposta) {
                    return resposta.json();
                })
                .then(function (dados) {
                    if (aba && dados && dados.source_url) {
                        aba.opener = null;
                        aba.location.href = dados.source_url;
                    } else if (aba) {
                        aba.close();
                        alert("Não deu pra abrir a imagem.");
                    }
                })
                .catch(function () {
                    if (aba) {
                        aba.close();
                    }
                    alert("Não deu pra abrir a imagem.");
                });
        }

        // ---- Edição com IA em lote (mesma instrução, fotos selecionadas ou todas) ----
        // Custo: cada foto é UMA chamada de edição de imagem, que é a operação mais cara da
        // IA — por isso sequencial, com estimativa e confirmação antes, parando na PRIMEIRA
        // falha (teto de gasto, limite diário, erro) em vez de insistir nas demais. A
        // foto já é reduzida (lado máx. 1536px) no servidor antes de ir pra IA, e o teto
        // mensal de gasto também vale aqui (checado no servidor a cada chamada).
        var barraLote = document.getElementById("atelie-fotos-lote");
        var checkTodas = document.getElementById("atelie-fotos-sel-todas");
        var campoInstrucao = document.getElementById("atelie-fotos-lote-instrucao");
        var btnLote = document.getElementById("atelie-fotos-lote-btn");
        var custoLote = document.getElementById("atelie-fotos-lote-custo");
        var statusLote = document.getElementById("atelie-fotos-lote-status");
        var loteRodando = false;

        function formatarReal(valor) {
            return "R$ " + Number(valor || 0).toFixed(4).replace(".", ",");
        }

        function itensAlvoDoLote() {
            var todos = Array.prototype.slice.call(fotosPreview.querySelectorAll(".atelie-foto-item"));
            var marcados = todos.filter(function (el) {
                var c = el.querySelector(".atelie-foto-check");
                return c && c.checked;
            });
            return marcados.length > 0 ? marcados : todos;
        }

        function atualizarBarraLote() {
            if (!barraLote) {
                return;
            }
            var total = fotosPreview.querySelectorAll(".atelie-foto-item").length;
            barraLote.style.display = total > 0 ? "flex" : "none";

            var marcadas = fotosPreview.querySelectorAll(".atelie-foto-check:checked").length;
            var n = marcadas > 0 ? marcadas : total;
            var custoPorFoto = Number(String(atelieProdutoIA.custoEdicaoImagem).replace(",", "."));

            checkTodas.checked = total > 0 && marcadas === total;
            btnLote.textContent = "✏️ Editar com IA em " + n + " foto" + (n === 1 ? "" : "s") + (marcadas > 0 ? " selecionada" + (n === 1 ? "" : "s") : "");
            custoLote.textContent = "até ~" + formatarReal(n * custoPorFoto) + " no total";
            btnLote.disabled = loteRodando || !atelieProdutoIA.iaDisponivel || campoInstrucao.value.trim() === "";
        }

        function editarEmLote() {
            var alvos = itensAlvoDoLote();
            var instrucao = campoInstrucao.value.trim();
            if (!alvos.length || instrucao === "") {
                return;
            }
            var custoPorFoto = Number(String(atelieProdutoIA.custoEdicaoImagem).replace(",", "."));
            if (
                !window.confirm(
                    "A IA vai editar " + alvos.length + " foto(s), uma de cada vez, com esta instrução:\n\n\"" + instrucao +
                        "\"\n\nCusto aproximado: até " + formatarReal(alvos.length * custoPorFoto) + ". As originais não são apagadas. Continuar?"
                )
            ) {
                return;
            }

            loteRodando = true;
            atualizarBarraLote();
            statusLote.style.display = "block";
            statusLote.className = "atelie-status atelie-status-inline";

            var indice = 0;
            var editadas = 0;
            var custoTotal = 0;

            function concluir(mensagemErro) {
                loteRodando = false;
                sincronizarFotosIds();
                var resumo = editadas + " de " + alvos.length + " foto(s) editada(s). Custo: " + formatarReal(custoTotal) + ".";
                statusLote.textContent = mensagemErro ? mensagemErro + " Parei aqui pra não gastar à toa. " + resumo : "Concluído: " + resumo;
                statusLote.className = "atelie-status atelie-status-inline" + (mensagemErro ? " atelie-status-erro" : " atelie-status-ok");
                atualizarBarraLote();
            }

            function proxima() {
                if (indice >= alvos.length) {
                    concluir("");
                    return;
                }
                var item = alvos[indice];
                statusLote.textContent = "Editando foto " + (indice + 1) + " de " + alvos.length + "…";

                fetch(atelieProdutoIA.editarImagemUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "X-WP-Nonce": atelieProdutoIA.nonce },
                    body: JSON.stringify({ foto_id: item.dataset.fotoId, prompt: instrucao }),
                })
                    .then(function (resposta) {
                        return resposta.json().then(function (dados) {
                            return { ok: resposta.ok, dados: dados };
                        });
                    })
                    .then(function (resultado) {
                        custoTotal += Number((resultado.dados && resultado.dados.custo) || 0);
                        if (!resultado.ok) {
                            concluir((resultado.dados && resultado.dados.erro) || "Não deu pra editar a foto " + (indice + 1) + ".");
                            return;
                        }
                        item.dataset.fotoId = resultado.dados.imagem_id;
                        item.querySelector(".atelie-foto-thumb").src = resultado.dados.url;
                        editadas++;
                        indice++;
                        proxima();
                    })
                    .catch(function () {
                        concluir("Erro de conexão.");
                    });
            }

            proxima();
        }

        if (barraLote) {
            checkTodas.addEventListener("change", function () {
                Array.prototype.forEach.call(fotosPreview.querySelectorAll(".atelie-foto-check"), function (c) {
                    c.checked = checkTodas.checked;
                });
                atualizarBarraLote();
            });
            campoInstrucao.addEventListener("input", atualizarBarraLote);
            btnLote.addEventListener("click", editarEmLote);
            fotosPreview.addEventListener("change", function (e) {
                if (e.target.classList && e.target.classList.contains("atelie-foto-check")) {
                    atualizarBarraLote();
                }
            });
        }

        function adicionarFoto(id, thumbnailUrl) {
            var wrapper = document.createElement("div");
            wrapper.className = "atelie-foto-item";
            wrapper.dataset.fotoId = id;

            var check = document.createElement("input");
            check.type = "checkbox";
            check.className = "atelie-foto-check";
            check.title = "Selecionar essa foto pra editar com IA em lote";
            wrapper.appendChild(check);

            var img = document.createElement("img");
            img.src = thumbnailUrl;
            img.className = "atelie-foto-thumb";
            wrapper.appendChild(img);

            var linhaVer = document.createElement("div");
            linhaVer.className = "atelie-foto-ver";

            var btnVerOriginal = document.createElement("button");
            btnVerOriginal.type = "button";
            btnVerOriginal.className = "atelie-btn-ver-original";
            btnVerOriginal.textContent = "🔍 Ampliar";
            btnVerOriginal.addEventListener("click", function () {
                abrirPreview(wrapper.dataset.fotoId);
            });
            linhaVer.appendChild(btnVerOriginal);

            var btnNovaGuia = document.createElement("button");
            btnNovaGuia.type = "button";
            btnNovaGuia.className = "atelie-btn-ver-original";
            btnNovaGuia.textContent = "↗ Abrir em nova guia";
            btnNovaGuia.addEventListener("click", function () {
                abrirNovaGuia(wrapper.dataset.fotoId);
            });
            linhaVer.appendChild(btnNovaGuia);

            wrapper.appendChild(linhaVer);

            fotosPreview.appendChild(wrapper);

            AtelieEditarImagem.anexar(wrapper, img, id, atelieProdutoIA, function (novoId) {
                wrapper.dataset.fotoId = novoId;
                sincronizarFotosIds();
            });
            AtelieEditarImagem.anexarSugestao(wrapper, img, id, atelieProdutoIA, function (novoId) {
                wrapper.dataset.fotoId = novoId;
                sincronizarFotosIds();
            });
        }

        if (window.jQuery && jQuery.fn.sortable) {
            jQuery(fotosPreview).sortable({
                items: ".atelie-foto-item",
                tolerance: "pointer",
                distance: 5,
                update: sincronizarFotosIds,
            });
        }

        // Ponto de entrada pra outras fontes de foto (ex.: fotos soltas escolhidas no modal
        // do Drive, ja baixadas e salvas na Biblioteca de Midia) entrarem no mesmo estado
        // e fluxo de "Escolher fotos" — mesmo limite, mesmo botao Sugerir/Manual.
        window.AtelieNovoProduto = {
            vagasDisponiveis: function () {
                return LIMITE_FOTOS - fotosIds.length;
            },
            adicionarFotosExternas: function (lista) {
                lista.forEach(function (item) {
                    adicionarFoto(item.id, item.url);
                });
                sincronizarFotosIds();
            },
        };

        function abrirSeletorMidia(callback, multiplo) {
            var frame = wp.media({
                title: "Escolher imagem",
                library: { type: "image" },
                multiple: !!multiplo,
            });
            frame.on("select", function () {
                var selecao = frame.state().get("selection").toJSON();
                callback(selecao);
            });
            frame.open();
        }

        btnEscolherFotos.addEventListener("click", function () {
            if (fotosIds.length >= LIMITE_FOTOS) {
                alert("Máximo de " + LIMITE_FOTOS + " fotos por produto — remova alguma antes de escolher mais.");
                return;
            }

            abrirSeletorMidia(function (itens) {
                var ids = itens.map(function (item) {
                    return item.id;
                });

                AtelieDuplicataUpload.confirmarSelecao(ids, atelieProdutoIA).then(function (idsAprovados) {
                    var aprovados = itens.filter(function (item) {
                        return idsAprovados.indexOf(item.id) !== -1;
                    });

                    var vagas = LIMITE_FOTOS - fotosIds.length;
                    if (aprovados.length > vagas) {
                        alert("Máximo de " + LIMITE_FOTOS + " fotos por produto — só as primeiras " + vagas + " dessa seleção foram anexadas.");
                        aprovados = aprovados.slice(0, vagas);
                    }

                    aprovados.forEach(function (item) {
                        // "medium" (300px) em vez de "thumbnail" (150px): a miniatura agora é maior na tela.
                        adicionarFoto(item.id, item.sizes && item.sizes.medium ? item.sizes.medium.url : item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url);
                    });
                    sincronizarFotosIds();
                });
            }, true);
        });

        btnEscolherReceitaImagem.addEventListener("click", function () {
            abrirSeletorMidia(function (itens) {
                if (itens.length) {
                    receitaImagemId = itens[0].id;
                    receitaImagemNome.textContent = "Foto da receita anexada ✓";
                }
            }, false);
        });

        btnSugerir.addEventListener("click", function () {
            btnSugerir.style.display = "none";
            analisando.style.display = "inline";

            var corpo = {
                fotos: fotosIds,
                receita_imagem_id: receitaImagemId,
                receita_texto: document.getElementById("atelie-receita-texto").value,
            };

            fetch(atelieProdutoIA.restUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": atelieProdutoIA.nonce,
                },
                body: JSON.stringify(corpo),
            })
                .then(function (resposta) {
                    return resposta.json().then(function (dados) {
                        return { ok: resposta.ok, dados: dados };
                    });
                })
                .then(function (resultado) {
                    analisando.style.display = "none";
                    btnSugerir.style.display = "inline-block";

                    if (!resultado.ok) {
                        alert((resultado.dados && resultado.dados.erro) || "Não deu pra sugerir agora. Preencha manualmente.");
                        mostrarFormulario();
                        return;
                    }

                    preencherSugestao(resultado.dados.sugestao);
                    mostrarFormulario();
                })
                .catch(function () {
                    analisando.style.display = "none";
                    btnSugerir.style.display = "inline-block";
                    alert("Erro de conexão. Preencha manualmente.");
                    mostrarFormulario();
                });
        });

        function preencherSugestao(sugestao) {
            if (!sugestao) {
                return;
            }
            if (sugestao.titulo) {
                document.getElementById("atelie-campo-titulo").value = sugestao.titulo;
                document.getElementById("atelie-badge-titulo").style.display = "inline";
                // Guarda o valor original da sugestão — se o texto publicado for igual a
                // isso, não precisa passar pela revisão da IA de novo (ver class-revisao-vendas.php).
                document.getElementById("atelie-titulo-ia-original").value = sugestao.titulo;
            }
            if (sugestao.descricao) {
                document.getElementById("atelie-campo-descricao").value = sugestao.descricao;
                document.getElementById("atelie-badge-descricao").style.display = "inline";
                document.getElementById("atelie-descricao-ia-original").value = sugestao.descricao;
            }
            if (sugestao.categoria) {
                document.getElementById("atelie-campo-categoria").value = sugestao.categoria;
                document.getElementById("atelie-badge-categoria").style.display = "inline";
            }
            if (sugestao.material_tecnica) {
                document.getElementById("atelie-campo-material").value = sugestao.material_tecnica;
                document.getElementById("atelie-campo-material-linha").style.display = "block";
            }
        }

        function mostrarFormulario() {
            form.style.display = "block";
            form.scrollIntoView({ behavior: "smooth" });
        }

        btnRecomecar.addEventListener("click", function () {
            window.location.reload();
        });

        var dispSelect = document.getElementById("atelie-campo-disponibilidade");
        var prazoLinha = document.getElementById("atelie-campo-prazo-linha");
        function sincronizarPrazo() {
            prazoLinha.style.display = dispSelect.value === "sob_encomenda" ? "block" : "none";
        }
        dispSelect.addEventListener("change", sincronizarPrazo);
        sincronizarPrazo();

        // Modo edição (tela aberta com ?produto=ID, vinda do "Revisar" das Pendências):
        // semeia o estado interno de fotos com o que o produto já tem, senão a próxima
        // foto escolhida em "Escolher fotos" substituiria a lista em vez de completá-la.
        if (atelieProdutoIA.edicaoFotos && atelieProdutoIA.edicaoFotos.length) {
            window.AtelieNovoProduto.adicionarFotosExternas(atelieProdutoIA.edicaoFotos);
        }

        atualizarBotaoSugerir();
    });
})();
