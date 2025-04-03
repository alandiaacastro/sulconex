<?php
/**
 * Conhecimento Active Record
 * @author  <your-name-here>
 */
class Conhecimento extends TRecord
{
    const TABLENAME = 'conhecimento';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'max'; // {max, serial}
    
    
    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('permisso');
        parent::addAttribute('numero');
        parent::addAttribute('data_transportador_assinatura');
        parent::addAttribute('status_crt_id');
        parent::addAttribute('remetente_id');
        parent::addAttribute('destinatario_id');
        parent::addAttribute('consignatario_id');
        parent::addAttribute('notificar_id');
        parent::addAttribute('pagador_id');
        parent::addAttribute('endereco_remetente');
        parent::addAttribute('endereco_transportador');
        parent::addAttribute('endereco_destinatario');
        parent::addAttribute('endereco_consignatario');
        parent::addAttribute('notificar_nome');
        parent::addAttribute('notificar_endereco');
        parent::addAttribute('local_emissao');
        parent::addAttribute('local_responsabilidade');
        parent::addAttribute('local_entrega');
        parent::addAttribute('transportadores_sucessivos');
        parent::addAttribute('quantidade_volumes');
        parent::addAttribute('descricao_mercadoria');
        parent::addAttribute('peso_bruto_kg');
        parent::addAttribute('peso_liq_kg');
        parent::addAttribute('volume_m3');
        parent::addAttribute('incoterm');
        parent::addAttribute('valor_mercadorias');
        parent::addAttribute('moeda_valor_mercadorias');
        parent::addAttribute('valor_declarado');
        parent::addAttribute('documentos_anexos');
        parent::addAttribute('observacoes');
        parent::addAttribute('instrucoes_alfandega');
        parent::addAttribute('valor_reembolso');
        parent::addAttribute('valor_frete_externo');
        parent::addAttribute('moeda_frete_externo');
        parent::addAttribute('textogasto1');
        parent::addAttribute('textogasto2');
        parent::addAttribute('textogasto3');
        parent::addAttribute('custoremetente1');
        parent::addAttribute('custoremetente2');
        parent::addAttribute('custoremetente3');
        parent::addAttribute('custodestino1');
        parent::addAttribute('custodestino2');
        parent::addAttribute('custodestino3');
        parent::addAttribute('gastosmoeda');
        parent::addAttribute('total_custo_destinatario');
        parent::addAttribute('total_custo_remetente');
        parent::addAttribute('nome_remetente');
        parent::addAttribute('nome_destinatario');
        parent::addAttribute('nome_consignatario');
        parent::addAttribute('nome_transporte');
        parent::addAttribute('valorfaturausd');
        parent::addAttribute('copiacrt');
        parent::addAttribute('valor_fatbr');
        parent::addAttribute('assinatura_nome');
        parent::addAttribute('fatura_crt');
        parent::addAttribute('especie_vol');
        parent::addAttribute('nome_transportador');
        parent::addAttribute('fatura_brl');
        parent::addAttribute('taxadolar');
        parent::addAttribute('fatura_usd');
        parent::addAttribute('incoterm16');
        parent::addAttribute('nome_pagador');
        parent::addAttribute('pais_destino');
        parent::addAttribute('faturado');
        parent::addAttribute('porteador');
        parent::addAttribute('logotransporte');
    }


}
