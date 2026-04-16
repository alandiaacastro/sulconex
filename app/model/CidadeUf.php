<?php

use Adianti\Database\TRecord;

class CidadeUf extends TRecord
{
    const TABLENAME  = 'cidade_uf';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('nome');
        parent::addAttribute('uf');
    }

    public function getCidadeUf()
    {
        return $this->nome . ',' . $this->uf;
    }
}
