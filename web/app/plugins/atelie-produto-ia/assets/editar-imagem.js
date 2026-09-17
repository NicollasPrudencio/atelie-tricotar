/**
 * Botão "Editar com IA" anexado a uma miniatura de foto — usado tanto no
 * painel de produto quanto no de case (admin.js / case.js chamam isso).
 * Sempre gera uma foto NOVA (nunca substitui o arquivo original no servidor);
 * quem usa o painel decide se troca a foto no formulário ou ignora o resultado.
 */
window.AtelieEditarImagem = (function () {
    "use strict";

    // Formata um numero cru (vindo da resposta da API, em ponto) no mesmo
    // formato usado no resto do painel (ex.: "R$ 0,0038").
    function formatarReal(valor) {
        return "R$ " + Number(valor || 0).toFixed(4).replace(".", ",");
    }

    function anexar(containerEl, imgEl, fotoId, config, aoTrocar) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "atelie-btn-editar-imagem";
        btn.textContent = "✏️ Editar com IA";

        if (!config.iaDisponivel) {
            btn.disabled = true;
        }

        var textoOriginal = btn.textContent;

        // config.custoEdicaoImagem ja vem formatado (string, virgula decimal) do PHP —
        // so prefixa "R$", nao passa por formatarReal() (que espera numero cru em ponto).
        var custoEstimadoTexto = "R$ " + config.custoEdicaoImagem;

        // Custo sempre visível ANTES de clicar — não depende de ler o texto do prompt().
        var custoLabel = document.createElement("span");
        custoLabel.className = "atelie-custo-estimado";
        custoLabel.textContent = "~" + custoEstimadoTexto + " por edição";

        btn.addEventListener("click", function () {
            var pedido = window.prompt(
                "Descreva a edição desejada (ex: \"deixe o fundo branco\"). Custo aproximado: " + custoEstimadoTexto
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
                        var mensagemErro = (resultado.dados && resultado.dados.erro) || "Não deu pra editar a imagem agora.";
                        if (resultado.dados && resultado.dados.custo) {
                            mensagemErro += " (custo já gasto nessa tentativa: " + formatarReal(resultado.dados.custo) + ")";
                        }
                        alert(mensagemErro);
                        return;
                    }

                    imgEl.src = resultado.dados.url;
                    aoTrocar(resultado.dados.imagem_id);
                    alert("Imagem editada! Custo desta edição: " + formatarReal(resultado.dados.custo));
                })
                .catch(function () {
                    btn.disabled = !config.iaDisponivel;
                    btn.textContent = textoOriginal;
                    alert("Erro de conexão. Tente de novo.");
                });
        });

        containerEl.appendChild(btn);
        containerEl.appendChild(custoLabel);
    }

    /**
     * Botão "IA sugere edição" — a IA avalia a foto sozinha (sem descrição do
     * usuário) e já aplica a edição que julgar ideal, se achar que vale a
     * pena. Sempre gera uma foto NOVA, igual ao botão "Editar com IA".
     */
    function anexarSugestao(containerEl, imgEl, fotoId, config, aoTrocar) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "atelie-btn-editar-imagem";
        btn.textContent = "✨ IA sugere edição";

        if (!config.iaDisponivel) {
            btn.disabled = true;
        }

        var textoOriginal = btn.textContent;

        // Custo sempre visível ANTES de clicar — é o "pior caso" (avaliar + editar);
        // se a IA decidir não editar, o custo real cobrado é bem menor (só a avaliação).
        var custoLabel = document.createElement("span");
        custoLabel.className = "atelie-custo-estimado";
        custoLabel.textContent = "até ~R$ " + config.custoEdicaoImagem + " (avaliação + edição, se aplicada)";

        btn.addEventListener("click", function () {
            btn.disabled = true;
            btn.textContent = "Avaliando…";

            fetch(config.sugerirEdicaoImagemUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": config.nonce,
                },
                body: JSON.stringify({ foto_id: fotoId }),
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
                        var mensagemErro = (resultado.dados && resultado.dados.erro) || "Não deu pra avaliar a foto agora.";
                        if (resultado.dados && resultado.dados.custo) {
                            mensagemErro += " (custo já gasto nessa tentativa: " + formatarReal(resultado.dados.custo) + ")";
                        }
                        alert(mensagemErro);
                        return;
                    }

                    if (!resultado.dados.editado) {
                        var custoAvaliacao = "Custo desta avaliação: " + formatarReal(resultado.dados.custo);
                        alert((resultado.dados.diagnostico || "A IA achou que essa foto já está boa, não precisa editar.") + "\n\n" + custoAvaliacao);
                        return;
                    }

                    imgEl.src = resultado.dados.url;
                    aoTrocar(resultado.dados.imagem_id);
                    var custoTotal = "Custo total (avaliação + edição): " + formatarReal(resultado.dados.custo);
                    var mensagemSucesso = custoTotal;
                    if (resultado.dados.diagnostico) {
                        mensagemSucesso = "Edição aplicada: " + resultado.dados.diagnostico + "\n\n" + custoTotal;
                    }
                    alert(mensagemSucesso);
                })
                .catch(function () {
                    btn.disabled = !config.iaDisponivel;
                    btn.textContent = textoOriginal;
                    alert("Erro de conexão. Tente de novo.");
                });
        });

        containerEl.appendChild(btn);
        containerEl.appendChild(custoLabel);
    }

    return { anexar: anexar, anexarSugestao: anexarSugestao };
})();
