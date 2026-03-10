<?php

use Adianti\Database\TRecord;

/**
 * EmailTemplate Active Record
 */
class EmailTemplate extends TRecord
{
    const TABLENAME  = 'email_template';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    public function __construct($id = NULL)
    {
        parent::__construct($id);
        parent::addAttribute('title');
        parent::addAttribute('subject');
        parent::addAttribute('body');
        parent::addAttribute('variables_json');
    }
}
