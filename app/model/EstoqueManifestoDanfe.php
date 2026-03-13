<?php

use Adianti\Database\TRecord;

class EstoqueManifestoDanfe extends TRecord
{
    const TABLENAME  = 'estoque_manifesto_danfe';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('manifesto_id');
        parent::addAttribute('danfe_codigo');
        parent::addAttribute('danfe_normalizado');
        parent::addAttribute('created_at');
    }
}
