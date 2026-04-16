<?php
// [class-head]

// [/class-head]

class Clientes extends TRecord
{
    const TABLENAME    = 'clientes';
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
        parent::addAttribute('nome');
        parent::addAttribute('inscricao_estadual');
        parent::addAttribute('cnpj');
        parent::addAttribute('cidade');
        parent::addAttribute('estado');
        parent::addAttribute('email');
        parent::addAttribute('telefone');
        parent::addAttribute('endereco');
        parent::addAttribute('cep');
        parent::addAttribute('atividade');
        parent::addAttribute('dados_crt');
    }//end-of-__construct()
    
    
    
    
    
    
    
    
    /**
     * 
     * @author Creator
     */
    public function clearParts()
    {
    
    }//end-of-clearParts()
    
    /**
     * Delete the object and its parts
     * @param $id object ID
     * @author Creator
     */
    public function delete($id = NULL)
    {
        $id = isset($id) ? $id : $this->id;
        
        parent::checkDependencies('Conhecimento', 'remetente_id', $id, 'Conhecimentos');
        
        
        // delete the object itself
        parent::delete($id);
    }//end-of-delete()
    
    /**
     * Return the object relationships
     * @author Creator
     */
    public function get_relationships()
    {
        return array (
          'dependencies' => 
          array (
            0 => 
            array (
              'var' => 'conhecimentos',
              'model' => 'Conhecimento',
              'fkey' => 'remetente_id',
            ),
          ),
        );
    }//end-of-_get_relationships()
    

}//end-of-class
