<?php

class OpportunityHistory extends TRecord
{
    const TABLENAME  = 'opportunity_history';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    use SystemChangeLogTrait;
}