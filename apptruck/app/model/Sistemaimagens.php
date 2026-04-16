<?php
// [class-head]

// [/class-head]

class Sistemaimagens extends TRecord
{
    const TABLENAME    = 'sistemaimagens';
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
        parent::addAttribute('imagem');
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
