<?php

use Adianti\Database\TRecord;

/**
 * View somente-leitura: nome || ',' || uf AS nome_uf
 * Usada em campos TDBUniqueSearch para origem/fronteira/destino de fretes.
 */
class VCidadeUf extends TRecord
{
    const TABLENAME  = 'cidade_uf';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('nome');
        parent::addAttribute('uf');
        parent::addAttribute('nome_uf');
    }
}
