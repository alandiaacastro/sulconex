<?php

class TabelaFrete extends TRecord
{
    const TABLENAME  = 'tabela_fretes';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('origem');
        parent::addAttribute('destino');
        parent::addAttribute('tipo_veiculo');
        parent::addAttribute('valor_frete');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
