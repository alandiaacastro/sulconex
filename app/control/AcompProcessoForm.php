<?php

class AcompProcessoForm extends TPage
{
    public function __construct($param = null)
    {
        parent::__construct();
        $this->redirectToKanban();
    }

    public function onEdit($param = null)
    {
        $this->redirectToKanban();
    }

    public function onSave($param = null)
    {
        $this->redirectToKanban();
    }

    private function redirectToKanban(): void
    {
        new TMessage('info', 'Cadastro direto desativado. O tracking agora e preenchido automaticamente via CRT no Kanban.', new TAction(['AcompProcessoKanban', 'onReload']));
    }
}
