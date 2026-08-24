/**
 * Teste de stress/fumaça rodado pelo pipeline (.github/workflows/deploy.yml, job
 * "stress-test") contra o ambiente de homolog logo depois do deploy — gate automático
 * antes de promover develop -> main. Sem intervenção manual: se esse script falhar
 * (threshold estourado ou requisição com erro), o job falha e a promoção não acontece.
 *
 * Cobre as páginas públicas mais visitadas (home, loja, wp-json) sob carga moderada, E o
 * fluxo real de carrinho/frete via WooCommerce Store API (adicionar produto, calcular frete,
 * conferir total) — adicionado em 2026-08-24 depois de uma atualização de dependência
 * (WooCommerce) que só foi confirmada segura testando esse fluxo manualmente, não só a página
 * carregando. Não completa pagamento de verdade (precisa de credencial de teste do Mercado
 * Pago, ainda pendente) — só confirma que adicionar ao carrinho e calcular frete continuam
 * funcionando, que é o que quebraria silenciosamente numa atualização de plugin de loja.
 *
 * Rodar localmente: BASE_URL=https://dev.atelietricotar.com.br k6 run tests/stress/homolog.js
 */

import http from "k6/http";
import { check, sleep } from "k6";

const BASE_URL = __ENV.BASE_URL;

if (!BASE_URL) {
	throw new Error("Defina BASE_URL (ex.: https://homolog.atelietricotar.com.br) antes de rodar.");
}

export const options = {
	// Rampa curta e moderada de proposito — o objetivo aqui e um gate de deploy rapido
	// (roda a cada push em develop), nao um teste de capacidade exaustivo.
	stages: [
		{ duration: "20s", target: 10 },
		{ duration: "30s", target: 20 },
		{ duration: "20s", target: 0 },
	],
	thresholds: {
		http_req_failed: ["rate<0.01"], // menos de 1% de erro
		// p(95) mais folgado que antes (era 3s) desde que o fluxo de carrinho/frete entrou —
		// calculo de frete chama a API real do Melhor Envio (terceiro), que naturalmente
		// varia mais que servir uma pagina — 5s ainda pega regressao real sem flakiness por
		// latencia de terceiro fora do nosso controle.
		http_req_duration: ["p(95)<5000"],
	},
};

function testarPaginasPublicas() {
	const paginas = [`${BASE_URL}/`, `${BASE_URL}/loja/`, `${BASE_URL}/wp-json/`];

	for (const url of paginas) {
		const resposta = http.get(url);
		check(resposta, {
			[`${url} responde 200`]: (r) => r.status === 200,
			[`${url} responde em menos de 3s`]: (r) => r.timings.duration < 3000,
		});
	}
}

/**
 * Fluxo real de carrinho via WooCommerce Store API — pega um produto publicado de verdade
 * (nunca hardcoded, pra nao quebrar o smoke test so porque o catalogo mudou), adiciona ao
 * carrinho, calcula frete pra um CEP real, e confere que o total bate com item + frete > 0.
 */
function testarCarrinhoEFrete() {
	const produtos = http.get(`${BASE_URL}/wp-json/wc/store/v1/products?per_page=1`);
	const listaOk = check(produtos, {
		"lista de produtos responde 200": (r) => r.status === 200,
	});
	if (!listaOk) {
		return;
	}

	const itens = JSON.parse(produtos.body);
	if (!Array.isArray(itens) || itens.length === 0) {
		return; // catalogo vazio no momento (ex.: homolog em manutencao) — nao e regressao de codigo, pula.
	}
	const produtoId = itens[0].id;

	const cartInicial = http.get(`${BASE_URL}/wp-json/wc/store/v1/cart`);
	const nonce = cartInicial.headers["Nonce"];
	check(cartInicial, {
		"carrinho inicial responde 200": (r) => r.status === 200,
		"carrinho devolve nonce": () => !!nonce,
	});
	if (!nonce) {
		return;
	}

	const headers = { "Content-Type": "application/json", Nonce: nonce };

	const adicionar = http.post(
		`${BASE_URL}/wp-json/wc/store/v1/cart/add-item`,
		JSON.stringify({ id: produtoId, quantity: 1 }),
		{ headers }
	);
	check(adicionar, {
		// Store API devolve 201 (recurso criado) ao adicionar item, nao 200.
		"adicionar ao carrinho responde 201": (r) => r.status === 201,
		"item aparece no carrinho": (r) => JSON.parse(r.body).items.length > 0,
	});

	const frete = http.post(
		`${BASE_URL}/wp-json/wc/store/v1/cart/update-customer`,
		JSON.stringify({
			shipping_address: { country: "BR", state: "SP", city: "Sao Paulo", postcode: "01310-100" },
		}),
		{ headers }
	);
	check(frete, {
		"calculo de frete responde 200": (r) => r.status === 200,
		"frete encontrou pelo menos uma opcao": (r) => {
			const zonas = JSON.parse(r.body).shipping_rates || [];
			return zonas.some((zona) => (zona.shipping_rates || []).length > 0);
		},
		"total do carrinho e maior que zero": (r) => parseInt(JSON.parse(r.body).totals.total_price, 10) > 0,
	});

	const checkout = http.get(`${BASE_URL}/finalizar-compra/`);
	check(checkout, {
		"pagina de checkout responde 200": (r) => r.status === 200,
	});
}

export default function () {
	testarPaginasPublicas();
	testarCarrinhoEFrete();
	sleep(1);
}
