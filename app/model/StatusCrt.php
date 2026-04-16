<?php
class StatusCrt extends TRecord
{
    const TABLENAME  = 'status_crt';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // {max, serial}

    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('nome');
        parent::addAttribute('cor');
        parent::addAttribute('kanban');
        parent::addAttribute('ordem');
        parent::addAttribute('situacao');
        parent::addAttribute('status_final');
        parent::addAttribute('deleted_at');
        parent::addAttribute('status_inicial');
        parent::addAttribute('permite_edicao');
        parent::addAttribute('updated_at');
        parent::addAttribute('created_at');
        parent::addAttribute('permite_exclusao');
    }

    /**
     * Method called before saving the record
     */
    public function onBeforeStore($object)
    {
        if (!$object->created_at) {
            $object->created_at = date('Y-m-d H:i:s');
        }
        $object->updated_at = date('Y-m-d H:i:s');
    }

    /**
     * Method called before deleting the record
     */
    public function onBeforeDelete($object)
    {
        $object->deleted_at = date('Y-m-d H:i:s');
        $object->store(); // Soft delete
        return false; // Prevent actual deletion
    }
}