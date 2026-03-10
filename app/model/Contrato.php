<?php

use Adianti\Database\TRecord;

class Contrato extends TRecord
{
    const TABLENAME  = 'contrato';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    private $veiculo;
    private $permisso;

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        
        parent::addAttribute('veiculo_id');
        parent::addAttribute('conhecimento_numero');
        parent::addAttribute('permisso_id');
        parent::addAttribute('danfeoumic');
        parent::addAttribute('emissao');
        parent::addAttribute('origem1');
        parent::addAttribute('destino1');
        parent::addAttribute('frete1');
        parent::addAttribute('adt1');
        parent::addAttribute('inss1');
        parent::addAttribute('irrf1');
        parent::addAttribute('sest1');
        parent::addAttribute('descontos1');
        parent::addAttribute('saldo1');
        parent::addAttribute('pagamento');
        parent::addAttribute('observacoes');
        parent::addAttribute('extenso1');
        parent::addAttribute('forma');
        parent::addAttribute('vencimento');
        parent::addAttribute('dta_efet_pg');
        parent::addAttribute('pago');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public function get_veiculo()
    {
        if (empty($this->veiculo) && !empty($this->veiculo_id)) {
            $this->veiculo = new Veiculo($this->veiculo_id);
        }
        return $this->veiculo;
    }
    
    public function get_permisso()
    {
        if (empty($this->permisso) && !empty($this->permisso_id)) {
            $this->permisso = new Permisso($this->permisso_id);
        }
        return $this->permisso;
    }
}