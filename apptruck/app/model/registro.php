<?php
// [class-head]

// [/class-head]

class registro extends TRecord
{
    const TABLENAME    = 'registro';
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
        parent::addAttribute('codigo');
        parent::addAttribute('pais_destino');
        parent::addAttribute('sequenciacrt');
        parent::addAttribute('transportadora');
        parent::addAttribute('logo');
        parent::addAttribute('enlastre');
        parent::addAttribute('nometransportadora');
        parent::addAttribute('cnpj');
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
