<?php

class AcompEvento extends TRecord
{
    const TABLENAME = 'acomp_evento';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'serial';

    private $processo_obj;

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('processo_id');
        parent::addAttribute('data_evento');
        parent::addAttribute('demora');
        parent::addAttribute('status_texto');
        parent::addAttribute('franquia');
        parent::addAttribute('ordem');
        parent::addAttribute('created_at');
    }

    public function get_processo()
    {
        if (empty($this->processo_obj) && !empty($this->processo_id)) {
            $this->processo_obj = new AcompProcesso($this->processo_id);
        }
        return $this->processo_obj;
    }

    public function onBeforeStore($object)
    {
        if (empty($object->created_at)) {
            $object->created_at = date('Y-m-d H:i:s');
        }
    }
}
