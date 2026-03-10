<?php

class Proposta extends TRecord
{
    const TABLENAME  = 'Proposta';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    public function __construct($id = NULL)
    {
        parent::__construct($id);

        // Identificação
        parent::addAttribute('Cotacao_ID');
        parent::addAttribute('cliente_id');
        parent::addAttribute('Situacao');
        parent::addAttribute('Data_Cotacao');
        parent::addAttribute('Data_Validade_Cotacao');

        // Logística
        parent::addAttribute('Descricao_Mercadoria');
        parent::addAttribute('FOB_Mercadoria_Valor');
        parent::addAttribute('Aduana_Fronteira');
        parent::addAttribute('Local_Coleta');
        parent::addAttribute('Local_Entrega');
        parent::addAttribute('Tipo_Equipamento');
        parent::addAttribute('Tempo_Transito');

        // Custos Operacionais
        parent::addAttribute('frete_origem');
        parent::addAttribute('frete_destino');
        parent::addAttribute('enlonamento');
        parent::addAttribute('estadia_multilog');
        parent::addAttribute('repres_multilog');
        parent::addAttribute('repres_uruguaiana');
        parent::addAttribute('repres_libres');
        parent::addAttribute('repres_uspallata');
        parent::addAttribute('repres_chile');
        parent::addAttribute('armazenagem_transbordo');
        parent::addAttribute('comissao_venda');
        parent::addAttribute('gerenciadora_risco');
        parent::addAttribute('Custo_Total_Operacao_Valor');

        // Taxas e Alíquotas
        parent::addAttribute('Percentual_Impostos_FOB');
        parent::addAttribute('Percentual_Seguro_FOB');
        parent::addAttribute('Taxa_Dolar');
        parent::addAttribute('taxa_swift');

        // Faturamento e Resultados
        parent::addAttribute('Faturamento_Valor_1');
        parent::addAttribute('fat_dolar');
        parent::addAttribute('valor_seguro');
        parent::addAttribute('Impostos_Operacao_Valor');
        parent::addAttribute('valor_swift');
        parent::addAttribute('fat_liquido_reais');
        parent::addAttribute('resultado_final');
        parent::addAttribute('resultado_dolar');
        parent::addAttribute('margem_percentual');

        // Observações
        parent::addAttribute('observacoes');
    }
}