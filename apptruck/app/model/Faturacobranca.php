<?php
// [class-head]

// [/class-head]

class Faturacobranca extends TRecord
{
    const TABLENAME    = 'faturacobranca';
    const PRIMARYKEY   = 'id';
    const IDPOLICY     = 'serial'; // {max, serial}
    
    private $clientekey; // instance of Clientes
    private $conhecimentokey; // instance of Conhecimento
    
    
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
        parent::addAttribute('numero');
        parent::addAttribute('emissao');
        parent::addAttribute('cliente_id');
        parent::addAttribute('vencimento');
        parent::addAttribute('conhecimento');
        parent::addAttribute('notafiscal');
        parent::addAttribute('descricao1');
        parent::addAttribute('descricao2');
        parent::addAttribute('descricao3');
        parent::addAttribute('valor1');
        parent::addAttribute('valor2');
        parent::addAttribute('valor3');
        parent::addAttribute('total');
        parent::addAttribute('extenso');
        parent::addAttribute('prod');
        parent::addAttribute('obs');
    }//end-of-__construct()
    
    
    /**
     * Association method
     * @author Creator
     * @return Clientes clientekey 
     */
    public function get_clientekey()
    {
        if (empty($this->clientekey))
        {
            $this->clientekey = Clientes::findCache($this->cliente_id);
        }
        return $this->clientekey;
    }//end-of-get_clientekey()
    
    /**
     * Association method
     * @author Creator
     * @return Conhecimento conhecimentokey 
     */
    public function get_conhecimentokey()
    {
        if (empty($this->conhecimentokey))
        {
            $this->conhecimentokey = Conhecimento::findCache($this->conhecimento);
        }
        return $this->conhecimentokey;
    }//end-of-get_conhecimentokey()
    
    
    
    
    
    
    
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
              'var' => 'clientekey',
              'model' => 'Clientes',
              'fkey' => 'cliente_id',
            ),
            1 => 
            array (
              'var' => 'conhecimentokey',
              'model' => 'Conhecimento',
              'fkey' => 'conhecimento',
            ),
          ),
        );
    }//end-of-_get_relationships()
    

}//end-of-class
