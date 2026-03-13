<?php

/**
 * EstoqueMovimentoItem
 * Itens extraídos do XML da NF-e vinculados ao movimento de estoque
 */
class EstoqueMovimentoItem extends TRecord
{
    const TABLENAME  = 'estoque_movimento_item';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('estoque_movimento_id');
        parent::addAttribute('numero_item');
        parent::addAttribute('codigo_produto');
        parent::addAttribute('descricao');
        parent::addAttribute('ncm');
        parent::addAttribute('cfop');
        parent::addAttribute('unidade');
        parent::addAttribute('quantidade');
        parent::addAttribute('valor_unitario');
        parent::addAttribute('valor_total');
    }

    public function get_movimento()
    {
        if (empty($this->estoque_movimento_id)) return null;
        return new EstoqueMovimento($this->estoque_movimento_id);
    }

    public function get_quantidade_formatada()
    {
        return number_format((float)$this->quantidade, 4, ',', '.');
    }

    public function get_valor_unitario_formatado()
    {
        return 'R$ ' . number_format((float)$this->valor_unitario, 2, ',', '.');
    }

    public function get_valor_total_formatado()
    {
        return 'R$ ' . number_format((float)$this->valor_total, 2, ',', '.');
    }
}
