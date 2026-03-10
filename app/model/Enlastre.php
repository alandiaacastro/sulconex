<?php

/**
 * Enlastre Active Record
 * @author Seu Nome
 */
class Enlastre extends TRecord
{
    const TABLENAME  = 'enlastre';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial'; // ou 'max' se for SQLite

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('permisso_id');
        parent::addAttribute('numeroenlastre');
        parent::addAttribute('trator');
        parent::addAttribute('semi');
        parent::addAttribute('motorista');
    }
}
