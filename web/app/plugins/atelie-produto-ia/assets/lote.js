(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var btnEscolher = document.getElementById("atelie-lote-escolher-fotos");
        if (!btnEscolher) {
            return;
        }

        var preview = document.getElementById("atelie-lote-fotos-preview");
        var inputIds = document.getElementById("atelie-lote-fotos-ids");
        var btnEnviar = document.getElementById("atelie-lote-enviar");
        var fotosIds = [];

        btnEscolher.addEventListener("click", function () {
            var frame = wp.media({
                title: "Escolher fotos do lote",
                library: { type: "image" },
                multiple: true,
            });
            frame.on("select", function () {
                var selecao = frame.state().get("selection").toJSON();
                selecao.forEach(function (item) {
                    fotosIds.push(item.id);
                    var img = document.createElement("img");
                    img.src = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
                    img.className = "atelie-foto-thumb";
                    preview.appendChild(img);
                });
                inputIds.value = fotosIds.join(",");
                btnEnviar.disabled = fotosIds.length === 0;
                btnEnviar.textContent = "Processar lote (" + fotosIds.length + " item" + (fotosIds.length === 1 ? "" : "s") + ")";
            });
            frame.open();
        });
    });
})();
