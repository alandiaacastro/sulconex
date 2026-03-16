<?php
class Opportunity extends TRecord
{
    const TABLENAME  = 'opportunity';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('contact_id');
        parent::addAttribute('status');
        parent::addAttribute('company_name');
        parent::addAttribute('phone');
        parent::addAttribute('responsible_name');
        parent::addAttribute('position');
        parent::addAttribute('email');
        parent::addAttribute('notes');
        parent::addAttribute('closing_date');
        parent::addAttribute('valor_estimado');
        parent::addAttribute('origem_lead');
        parent::addAttribute('prioridade');
        parent::addAttribute('proximo_contato');
        parent::addAttribute('created_at');
    }
}

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
        parent::addAttribute('descricao');
        parent::addAttribute('data_atividade');
        parent::addAttribute('usuario');
        parent::addAttribute('created_at');
    }
}