<?php
// [class-head]

// [/class-head]

class StatusCrt extends TRecord
{
    const TABLENAME    = 'status_crt';
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
        parent::addAttribute('cor');
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
    
    
    /**
     * get_pill()
     */
    public function get_pill()
    {
    return "pill://{$this->nome}::{$this->cor}";    
    }//end-of-get_pill()

}//end-of-class
