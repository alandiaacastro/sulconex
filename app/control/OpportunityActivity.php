<?php
/**
 * Model OpportunityActivity — atividades/tarefas vinculadas a uma oportunidade
 */
class OpportunityActivity extends TRecord
{
    const TABLENAME  = 'opportunity_activity';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('opportunity_id');
        parent::addAttribute('tipo');
        parent::addAttribute('titulo');
        parent::addAttribute('descricao');
        parent::addAttribute('usuario');
        parent::addAttribute('data_inicio');
        parent::addAttribute('data_fim');
        parent::addAttribute('created_at');
    }
}
