<?php
/**
 * Permisso Active Record
 * @author  
 */
class Permisso extends TRecord
{
    const TABLENAME  = 'Permisso';   // Nome da tabela conforme a base
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';        // ou 'serial', dependendo da política do seu banco

    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        
        // Campos definidos na base de dados
        parent::addAttribute('permisso');
        parent::addAttribute('pais_destino');
        parent::addAttribute('numerocrt');
        parent::addAttribute('numeroenlastre');
        parent::addAttribute('transportadora');
        parent::addAttribute('cnpj');
        parent::addAttribute('dados_documentos');
        parent::addAttribute('logo');
    }
}
?>

