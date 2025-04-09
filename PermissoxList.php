<?php

class PermissoxList extends TPage
{
    private $datagrid, $form, $pageNavigation;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_search_permissox');
        $this->form->setFormTitle('Buscar Permissos');

        $permisso = new TEntry('permisso');
        $this->form->addFields([new TLabel('Permisso')], [$permisso]);
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search');

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', 50));
        $this->datagrid->addColumn(new TDataGridColumn('permisso', 'Permisso', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('pais_destino', 'País Destino', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('numerocrt', 'Número CRT', 'left'));

        $action_edit = new TDataGridAction(['PermissoxForm', 'onEdit'], ['id' => '{id}']);
        $action_delete = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}']);

        $this->datagrid->addAction($action_edit, 'Editar', 'fa:edit blue');
        $this->datagrid->addAction($action_delete, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $panel = new TPanelGroup('Lista de Permissões');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);
        $container->add($panel);

        parent::add($container);

        $this->onReload();
    }

    public function onReload($param = NULL)
    {
        try {
            TTransaction::open('sample');

            $repository = new TRepository('Permissox');
            $limit = 10;
            $criteria = new TCriteria;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            $objects = $repository->load($criteria, FALSE);

            $this->datagrid->clear();
            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            $criteria->resetProperties();
            $count = $repository->count($criteria);
            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit($limit);

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onSearch($param)
    {
        $this->onReload($param);
    }

    public function onDelete($param)
    {
        $action = new TAction([$this, 'Delete']);
        $action->setParameters($param);

        new TQuestion('Deseja realmente excluir?', $action);
    }

    public function Delete($param)
    {
        try {
            TTransaction::open('sample');
            $object = new Permissox($param['id']);
            $object->delete();
            TTransaction::close();

            $this->onReload();
            new TMessage('info', 'Registro excluído com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
