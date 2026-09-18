/**
 * Aviso de foto duplicada — depois que a pessoa confirma a seleção num
 * frame do wp.media (foto recém-enviada OU escolhida da Biblioteca), checa
 * se cada uma já existe (mesmo conteúdo) e pede confirmação antes de deixar
 * passar. Compartilhado entre todo picker do painel (Novo Produto, Case,
 * Criar em Massa) — cada um chama AtelieDuplicataUpload.confirmarSelecao()
 * antes de seguir com os itens escolhidos.
 */
window.AtelieDuplicataUpload = (function () {
    "use strict";

    /**
     * @param {number[]} ids
     * @param {{duplicataUrl: string, nonce: string}} config
     * @return {Promise<number[]>} ids aprovados — recusados na confirmação saem da lista
     */
    function confirmarSelecao(ids, config) {
        if (!ids.length || !config || !config.duplicataUrl) {
            return Promise.resolve(ids);
        }

        return fetch(config.duplicataUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": config.nonce,
            },
            body: JSON.stringify({ ids: ids }),
        })
            .then(function (resposta) {
                return resposta.ok ? resposta.json() : { duplicadas: {} };
            })
            .then(function (dados) {
                var duplicadas = dados.duplicadas || {};
                return ids.filter(function (id) {
                    var match = duplicadas[id];
                    if (!match) {
                        return true;
                    }
                    return window.confirm(
                        'Essa foto parece já estar na Biblioteca de Mídia — mesma imagem de "' +
                            match.titulo +
                            '" (enviada em ' +
                            match.data +
                            "). Enviar mesmo assim?"
                    );
                });
            })
            .catch(function () {
                return ids; // erro de conexão não deve travar o fluxo normal de anexar fotos.
            });
    }

    return { confirmarSelecao: confirmarSelecao };
})();
