<?php

class Placa extends TRecord
{
    const TABLENAME = 'placa';
    const PRIMARYKEY= 'id';
    const IDPOLICY =  'serial'; 
    
    /**
     * Este método especial __toString() define qual campo será exibido
     * quando o objeto for tratado como um texto. Ã‰ uma boa prÃ¡tica.
     */
    public function __toString()
    {
        // IMPORTANTE: Altere 'descricao' se o nome da coluna que contém
        // o texto da placa for outro (ex: 'numero', 'placa_numero', etc.).
        if (isset($this->descricao))
        {
            return $this->descricao;
        }
        return ''; // Retorna vazio se não houver descrição
    }
}
?>