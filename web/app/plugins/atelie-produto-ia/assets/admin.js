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

        function adicionarFoto(id, thumbnailUrl) {
            var wrapper = document.createElement("div");
            wrapper.className = "atelie-foto-item";
            wrapper.dataset.fotoId = id;

            var img = document.createElement("img");
            img.src = thumbnailUrl;
            img.className = "atelie-foto-thumb";
            wrapper.appendChild(img);

            var btnVerOriginal = document.createElement("button");
            btnVerOriginal.type = "button";
            btnVerOriginal.className = "atelie-btn-ver-original";
            btnVerOriginal.textContent = "🔍 Ver tamanho original";
            btnVerOriginal.addEventListener("click", function () {
                abrirPreview(wrapper.dataset.fotoId);
            });
            wrapper.appendChild(btnVerOriginal);

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
                var vagas = LIMITE_FOTOS - fotosIds.length;
                if (itens.length > vagas) {
                    alert("Máximo de " + LIMITE_FOTOS + " fotos por produto — só as primeiras " + vagas + " dessa seleção foram anexadas.");
                    itens = itens.slice(0, vagas);
                }

                itens.forEach(function (item) {
                    adicionarFoto(item.id, item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url);
                });
                sincronizarFotosIds();
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
