<?php

class Motorista extends TRecord
{
    const TABLENAME  = 'motorista';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        $this->addAttribute('cnh_numero');
        $this->addAttribute('data_emissao_cnh');
        $this->addAttribute('data_validade_cnh');
        $this->addAttribute('categoria');
        $this->addAttribute('registro_num');
        $this->addAttribute('nome');
        $this->addAttribute('data_nascimento');
        $this->addAttribute('local_nascimento');
        $this->addAttribute('cpf');
        $this->addAttribute('rg_numero');
        $this->addAttribute('rg_emissor');
        $this->addAttribute('rg_uf');
        $this->addAttribute('filiacao_pai');
        $this->addAttribute('filiacao_mae');
    }

    public function get_nome_completo()
    {
        return $this->nome;
    }
}
?>
