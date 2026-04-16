<?php
// [class-head]

// [/class-head]

class enlastre extends TRecord
{
    const TABLENAME    = 'enlastre';
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
        parent::addAttribute('dta_emissao');
        parent::addAttribute('numeroenlastre');
        parent::addAttribute('nometransporte');
        parent::addAttribute('trator');
        parent::addAttribute('semi');
        parent::addAttribute('motoristaedoc');
        parent::addAttribute('transportadora');
        parent::addAttribute('cnpj');
        parent::addAttribute('enlastre_id');
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
          'associations' => 
          array (
            0 => 
            array (
              'var' => 'registro',
              'model' => 'registro',
              'fkey' => 'permisso_id',
            ),
          ),
        );
    }//end-of-_get_relationships()
    

}//end-of-class
