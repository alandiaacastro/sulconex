<?php

class Permissox extends TRecord
{
    const TABLENAME  = 'permissox';      // Nome da tabela no banco
    const PRIMARYKEY = 'id';             // Chave primária
    const IDPOLICY   = 'serial';         // Auto-incremento

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        // Mapeia os atributos da tabela
        parent::addAttribute('permisso');
        parent::addAttribute('pais_destino');
        parent::addAttribute('numerocrt');
        parent::addAttribute('transportadora');
        parent::addAttribute('logo');
    }
}
