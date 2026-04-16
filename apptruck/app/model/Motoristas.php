<?php
// [class-head]

// [/class-head]

class Motoristas extends TRecord
{
    const TABLENAME    = 'motoristas';
    const PRIMARYKEY   = 'id';
    const IDPOLICY     = 'serial'; // {max, serial}
    
    
    
    // [class-body]

    // [/class-body]
    
    /**
     * Constructor method
     * @author Creator
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        
        parent::addAttribute('id');
        parent::addAttribute('cnh_numero');
        parent::addAttribute('data_emissao_cnh');
        parent::addAttribute('data_validade_cnh');
        parent::addAttribute('categoria');
        parent::addAttribute('registro_num');
        parent::addAttribute('nome');
        parent::addAttribute('data_nascimento');
        parent::addAttribute('local_nascimento');
        parent::addAttribute('cpf');
        parent::addAttribute('rg_numero');
        parent::addAttribute('rg_emissor');
        parent::addAttribute('rg_uf');
        parent::addAttribute('filiacao_pai');
        parent::addAttribute('filiacao_mae');
    }//end-of-__construct()
    
    
    
    
    
    
    
    
    /**
     * 
     * @author Creator
     */
    public function clearParts()
    {
    
    }//end-of-clearParts()
    
    
    /**
     * Return the object relationships
     * @author Creator
     */
    public function get_relationships()
    {
        return array (
        );
    }//end-of-_get_relationships()
    

}//end-of-class
