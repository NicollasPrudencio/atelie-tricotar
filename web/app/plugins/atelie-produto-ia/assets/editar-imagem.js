/**
 * Botão "Editar com IA" anexado a uma miniatura de foto — usado tanto no
 * painel de produto quanto no de case (admin.js / case.js chamam isso).
 * Sempre gera uma foto NOVA (nunca substitui o arquivo original no servidor);
 * quem usa o painel decide se troca a foto no formulário ou ignora o resultado.
 */
window.AtelieEditarImagem = (function () {
    "use strict";

    function anexar(containerEl, imgEl, fotoId, config, aoTrocar) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "atelie-btn-editar-imagem";
        btn.textContent = "✏️ Editar com IA";

        if (!config.iaDisponivel) {
            btn.disabled = true;
        }

        var textoOriginal = btn.textContent;

        btn.addEventListener("click", function () {
            var pedido = window.prompt(
                "Descreva a edição desejada (ex: \"deixe o fundo branco\"). Custo aproximado: R$ " + config.custoEdicaoImagem
            );
            if (!pedido) {
                return;
            }

            btn.disabled = true;
            btn.textContent = "Editando…";

            fetch(config.editarImagemUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": config.nonce,
                },
                body: JSON.stringify({ foto_id: fotoId, prompt: pedido }),
            })
                .then(function (resposta) {
                    return resposta.json().then(function (dados) {
                        return { ok: resposta.ok, dados: dados };
                    });
                })
                .then(function (resultado) {
                    btn.disabled = !config.iaDisponivel;
                    btn.textContent = textoOriginal;

                    if (!resultado.ok) {
                        alert((resultado.dados && resultado.dados.erro) || "Não deu pra editar a imagem agora.");
                        return;
                    }

                    imgEl.src = resultado.dados.url;
                    aoTrocar(resultado.dados.imagem_id);
                })
                .catch(function () {
                    btn.disabled = !config.iaDisponivel;
                    btn.textContent = textoOriginal;
                    alert("Erro de conexão. Tente de novo.");
                });
        });

        containerEl.appendChild(btn);
    }

    return { anexar: anexar };
})();
