<?php

use Adianti\Database\TRecord;

class Cidade extends TRecord
{
    const TABLENAME  = 'cidade';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('nome');
        parent::addAttribute('estado');
        parent::addAttribute('pais');
    }
}
