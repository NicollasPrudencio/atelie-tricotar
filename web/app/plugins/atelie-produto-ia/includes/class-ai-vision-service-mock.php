<?php
/**
 * Implementacao falsa — usada quando AI_MOCK_MODE=true (dev local e CI),
 * pra ninguem gastar credito de API de verdade testando o painel.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atelie_Ai_Vision_Service_Mock implements Atelie_Ai_Vision_Service_Interface
{
    public function analisar(array $imagens_paths, ?string $receita_texto = null): array
    {
        // pequena pausa pra simular latencia real, ajuda a testar o estado "Analisando..." na tela
        usleep(600000);

        $qtd_fotos = count($imagens_paths);
        $tem_receita = !empty($receita_texto);

        return [
            'titulo' => sprintf('[MOCK] Peça artesanal (%d foto%s)', $qtd_fotos, $qtd_fotos === 1 ? '' : 's'),
            'descricao' => $tem_receita
                ? '[MOCK] Descrição gerada a partir da foto e da receita anexada. Peça feita à mão, com atenção aos detalhes.'
                : '[MOCK] Descrição gerada a partir da foto. Peça feita à mão, com atenção aos detalhes.',
            'categoria' => 'Amigurumis',
            'material_tecnica' => $tem_receita ? '[MOCK] Fio de algodão, crochê, ponto baixo.' : '',
        ];
    }
}
