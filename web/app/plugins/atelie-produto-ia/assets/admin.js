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

        function atualizarBotaoSugerir() {
            btnSugerir.disabled = fotosIds.length === 0 || !atelieProdutoIA.iaDisponivel;
            btnManual.disabled = fotosIds.length === 0;
        }

        btnManual.addEventListener("click", function () {
            mostrarFormulario();
        });

        function adicionarFoto(id, thumbnailUrl) {
            fotosIds.push(id);
            var indice = fotosIds.length - 1;

            var wrapper = document.createElement("div");
            wrapper.className = "atelie-foto-item";

            var img = document.createElement("img");
            img.src = thumbnailUrl;
            img.className = "atelie-foto-thumb";
            wrapper.appendChild(img);
            fotosPreview.appendChild(wrapper);

            AtelieEditarImagem.anexar(wrapper, img, id, atelieProdutoIA, function (novoId) {
                fotosIds[indice] = novoId;
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
                dropzoneTexto.textContent = fotosIds.length + " foto(s) anexada(s)";
                atualizarBotaoSugerir();
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
                dropzoneTexto.textContent = fotosIds.length + " foto(s) anexada(s)";
                atualizarBotaoSugerir();
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
            inputFotosIds.value = fotosIds.join(",");
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

        atualizarBotaoSugerir();
    });
})();
