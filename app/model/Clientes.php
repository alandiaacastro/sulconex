<?php
/**
 * Clientes Active Record
 */
class Clientes extends TRecord
{
    const TABLENAME  = 'clientes';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // 'max' se gera na aplicação, 'serial' se auto_increment no banco

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('nome');
        parent::addAttribute('email');
        parent::addAttribute('telefone');
        parent::addAttribute('endereco');
        parent::addAttribute('cidade');
        parent::addAttribute('estado');
        parent::addAttribute('cep');
        parent::addAttribute('cnpj');
        parent::addAttribute('inscricao_estadual');
        parent::addAttribute('atividade');
        parent::addAttribute('emissao_crt');
    }
}
?>