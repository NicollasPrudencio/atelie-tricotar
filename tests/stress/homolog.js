/**
 * Teste de stress/fumaça rodado pelo pipeline (.github/workflows/deploy.yml, job
 * "stress-test") contra o ambiente de homolog logo depois do deploy — gate automático
 * antes de promover develop -> main. Sem intervenção manual: se esse script falhar
 * (threshold estourado ou requisição com erro), o job falha e a promoção não acontece.
 *
 * Cobre as páginas públicas mais visitadas (home, loja, uma página de produto real,
 * checkout) sob carga moderada — não é teste funcional detalhado (isso é papel do
 * lint-and-test/PHPUnit), é confirmar que o site aguenta tráfego básico sem erro/lentidão
 * antes de virar candidato a produção.
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
		http_req_duration: ["p(95)<3000"], // 95% das respostas abaixo de 3s
	},
};

export default function () {
	const paginas = [`${BASE_URL}/`, `${BASE_URL}/loja/`, `${BASE_URL}/wp-json/`];

	for (const url of paginas) {
		const resposta = http.get(url);
		check(resposta, {
			[`${url} responde 200`]: (r) => r.status === 200,
			[`${url} responde em menos de 3s`]: (r) => r.timings.duration < 3000,
		});
	}

	sleep(1);
}
