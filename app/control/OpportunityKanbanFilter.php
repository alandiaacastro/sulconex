<?php
class OpportunityKanbanFilter extends TWindow
{
    private $form;

    public function __construct()
    {
        parent::__construct();
        parent::setSize(400, null);
        parent::setTitle('Filtro');
        parent::setModal(true);

        $this->form = new BootstrapFormBuilder('form_filter');
        $this->form->setFormTitle('Filtrar por Empresa');

        $company = new TDBCombo('company', 'sample', 'Opportunity', 'company_name', 'company_name', 'company_name');
        $company->enableSearch();
        $company->setSize('100%');

        $this->form->addFields([new TLabel('Empresa')], [$company]);

        $this->form->addAction('Buscar', new TAction(['OpportunityKanban', 'onReload']), 'fa:search blue');
        $this->form->addAction('Limpar', new TAction(['OpportunityKanban', 'onClear']), 'fa:eraser red');

        parent::add($this->form);
    }
}
?>