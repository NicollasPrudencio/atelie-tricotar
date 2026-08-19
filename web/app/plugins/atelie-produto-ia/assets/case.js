(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        if (typeof atelieCaseIA === "undefined") {
            return;
        }

        var fotosIds = [];

        var dropzoneTexto = document.getElementById("atelie-dropzone-texto");
        var fotosPreview = document.getElementById("atelie-fotos-preview");
        var btnEscolherFotos = document.getElementById("atelie-btn-escolher-fotos");
        var btnSugerir = document.getElementById("atelie-btn-sugerir");
        var btnManual = document.getElementById("atelie-btn-manual");
        var analisando = document.getElementById("atelie-analisando");
        var form = document.getElementById("atelie-form-produto");
        var inputFotosIds = document.getElementById("atelie-input-fotos-ids");
        var btnRecomecar = document.getElementById("atelie-btn-recomecar");

        function atualizarBotaoSugerir() {
            btnSugerir.disabled = fotosIds.length === 0 || !atelieCaseIA.iaDisponivel;
            btnManual.disabled = fotosIds.length === 0;
        }

        btnManual.addEventListener("click", function () {
            mostrarFormulario();
        });

        btnEscolherFotos.addEventListener("click", function () {
            var frame = wp.media({
                title: "Escolher fotos do trabalho",
                library: { type: "image" },
                multiple: true,
            });
            frame.on("select", function () {
                var selecao = frame.state().get("selection").toJSON();
                selecao.forEach(function (item) {
                    fotosIds.push(item.id);
                    var indice = fotosIds.length - 1;

                    var wrapper = document.createElement("div");
                    wrapper.className = "atelie-foto-item";

                    var img = document.createElement("img");
                    img.src = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
                    img.className = "atelie-foto-thumb";
                    wrapper.appendChild(img);
                    fotosPreview.appendChild(wrapper);

                    AtelieEditarImagem.anexar(wrapper, img, item.id, atelieCaseIA, function (novoId) {
                        fotosIds[indice] = novoId;
                    });
                });
                dropzoneTexto.textContent = fotosIds.length + " foto(s) anexada(s)";
                atualizarBotaoSugerir();
            });
            frame.open();
        });

        btnSugerir.addEventListener("click", function () {
            btnSugerir.style.display = "none";
            analisando.style.display = "inline";

            var corpo = {
                fotos: fotosIds,
                relato: document.getElementById("atelie-relato-texto").value,
            };

            fetch(atelieCaseIA.restUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": atelieCaseIA.nonce,
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
                document.getElementById("atelie-titulo-ia-original").value = sugestao.titulo;
            }
            if (sugestao.descricao) {
                document.getElementById("atelie-campo-descricao").value = sugestao.descricao;
                document.getElementById("atelie-badge-descricao").style.display = "inline";
                document.getElementById("atelie-descricao-ia-original").value = sugestao.descricao;
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

        atualizarBotaoSugerir();
    });
})();
