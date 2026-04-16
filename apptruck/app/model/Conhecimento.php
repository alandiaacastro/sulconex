<?php
// [class-head]

// [/class-head]

class Conhecimento extends TRecord
{
    const TABLENAME    = 'conhecimento';
    const PRIMARYKEY   = 'id';
    const IDPOLICY     = 'serial'; // {max, serial}
    
    private $statuscrt; // instance of StatusCrt
    private $remetente; // instance of Clientes
    private $destinatario; // instance of Clientes
    private $consignatario; // instance of Clientes
    private $notificar; // instance of Clientes
    private $pagador; // instance of Clientes
    
    
    // [class-body]

    // [/class-body]
    
    /**
     * Constructor method
     * @author Creator
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        
        parent::addAttribute('id');
        parent::addAttribute('data_emissao');
        parent::addAttribute('permisso');
        parent::addAttribute('pais_destino');
        parent::addAttribute('numero');
        parent::addAttribute('fatura_crt');
        parent::addAttribute('status_crt_id');
        parent::addAttribute('remetente_id');
        parent::addAttribute('nome_remetente');
        parent::addAttribute('endereco_remetente');
        parent::addAttribute('destinatario_id');
        parent::addAttribute('endereco_destinatario');
        parent::addAttribute('nome_destinatario');
        parent::addAttribute('consignatario_id');
        parent::addAttribute('nome_consignatario');
        parent::addAttribute('endereco_consignatario');
        parent::addAttribute('notificar_id');
        parent::addAttribute('notificar_nome');
        parent::addAttribute('notificar_endereco');
        parent::addAttribute('pagador_id');
        parent::addAttribute('endereco_transportador');
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
        parent::addAttribute('incoterm16');
        parent::addAttribute('valor_mercadorias');
        parent::addAttribute('moeda_valor_mercadorias');
        parent::addAttribute('valor_declarado');
        parent::addAttribute('documentos_anexos');
        parent::addAttribute('nome_transporte');
        parent::addAttribute('nome_transportador');
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
        parent::addAttribute('valorfaturausd');
        parent::addAttribute('assinatura_nome');
        parent::addAttribute('porteador');
        parent::addAttribute('especie_vol');
        parent::addAttribute('fatura_brl');
        parent::addAttribute('fatura_usd');
        parent::addAttribute('nome_pagador');
        parent::addAttribute('copiar');
    }//end-of-__construct()
    
    
    /**
     * Association method
     * @author Creator
     * @return StatusCrt statuscrt 
     */
    public function get_statuscrt()
    {
        if (empty($this->statuscrt))
        {
            $this->statuscrt = StatusCrt::findCache($this->status_crt_id);
        }
        return $this->statuscrt;
    }//end-of-get_statuscrt()
    
    /**
     * Association method
     * @author Creator
     * @return Clientes remetente 
     */
    public function get_remetente()
    {
        if (empty($this->remetente))
        {
            $this->remetente = Clientes::findCache($this->remetente_id);
        }
        return $this->remetente;
    }//end-of-get_remetente()
    
    /**
     * Association method
     * @author Creator
     * @return Clientes destinatario 
     */
    public function get_destinatario()
    {
        if (empty($this->destinatario))
        {
            $this->destinatario = Clientes::findCache($this->destinatario_id);
        }
        return $this->destinatario;
    }//end-of-get_destinatario()
    
    /**
     * Association method
     * @author Creator
     * @return Clientes consignatario 
     */
    public function get_consignatario()
    {
        if (empty($this->consignatario))
        {
            $this->consignatario = Clientes::findCache($this->consignatario_id);
        }
        return $this->consignatario;
    }//end-of-get_consignatario()
    
    /**
     * Association method
     * @author Creator
     * @return Clientes notificar 
     */
    public function get_notificar()
    {
        if (empty($this->notificar))
        {
            $this->notificar = Clientes::findCache($this->notificar_id);
        }
        return $this->notificar;
    }//end-of-get_notificar()
    
    /**
     * Association method
     * @author Creator
     * @return Clientes pagador 
     */
    public function get_pagador()
    {
        if (empty($this->pagador))
        {
            $this->pagador = Clientes::findCache($this->pagador_id);
        }
        return $this->pagador;
    }//end-of-get_pagador()
    
    
    
    
    
    
    
    /**
     * 
     * @author Creator
     */
    public function clearParts()
    {
    
    }//end-of-clearParts()
    
    
    /**
     * Return the object relationships
     * @author Creator
     */
    public function get_relationships()
    {
        return array (
          'associations' => 
          array (
            0 => 
            array (
              'var' => 'statuscrt',
              'model' => 'StatusCrt',
              'fkey' => 'status_crt_id',
            ),
            1 => 
            array (
              'var' => 'remetente',
              'model' => 'Clientes',
              'fkey' => 'remetente_id',
            ),
            2 => 
            array (
              'var' => 'destinatario',
              'model' => 'Clientes',
              'fkey' => 'destinatario_id',
            ),
            3 => 
            array (
              'var' => 'consignatario',
              'model' => 'Clientes',
              'fkey' => 'consignatario_id',
            ),
            4 => 
            array (
              'var' => 'notificar',
              'model' => 'Clientes',
              'fkey' => 'notificar_id',
            ),
            5 => 
            array (
              'var' => 'pagador',
              'model' => 'Clientes',
              'fkey' => 'pagador_id',
            ),
          ),
        );
    }//end-of-_get_relationships()
    

}//end-of-class
