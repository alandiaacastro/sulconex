<?php
/**
 * Model Opportunity — CRM
 */
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
        parent::addAttribute('vendedor');
        parent::addAttribute('origem_contato');
        parent::addAttribute('data_inicio');
        parent::addAttribute('data_esperada_fechamento');
        parent::addAttribute('valor');
    }
}
