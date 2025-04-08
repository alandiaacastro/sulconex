<?php

class PermissoxList extends TPage
{
    private $datagrid, $form, $pageNavigation;

    public function __construct()
    {
        parent::__construct();

        // Criação do formulário de busca
        $this->form = new BootstrapFormBuilder('form_search_permissox');
        $this->form->setFormTitle('Buscar Permissões');

        $permisso = new TEntry('permisso');
        $this->form->addFields([new TLabel('Permissão')], [$permisso]);
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search');

        // Criação do DataGrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', 50));
        $this->datagrid->addColumn(new TDataGridColumn('permisso', 'Permissão', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('pais_destino', 'País Destino', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('numerocrt', 'Número CRT', 'left'));

        // Ações do DataGrid
        $action_edit = new TDataGridAction(['PermissoxForm', 'onEdit'], ['id' => '{id}']);
        $action_delete = new TDataGridAction([__CLASS__, 'onDelete'], ['id' => '{id}']);

        $this->datagrid->addAction($action_edit, 'Editar', 'fa:edit blue');
        $this->datagrid->addAction($action_delete, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();

        // Navegação da página
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        // Agrupando os componentes
        $panel = new TPanelGroup('Lista de Permissões');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);
        $container->add($panel);

        parent::add($container);

        $this->onReload(); // Carrega os dados inicialmente
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

            // Filtro de busca (se houver)
            if (!empty($param['filter']['permisso'])) {
                $criteria->add(new TFilter('permisso', 'like', "%{$param['filter']['permisso']}%"));
            }

            // Carrega objetos
            $objects = $repository->load($criteria, FALSE);

            $this->datagrid->clear();
            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            // Paginação
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
        $data = $this->form->getData();
        $this->form->setData($data); // Retém os dados no formulário

        $filter = [];

        if (!empty($data->permisso)) {
            $filter['permisso'] = $data->permisso;
        }

        $this->onReload(['filter' => $filter]);
    }

    public static function onDelete($param)
    {
        $action = new TAction([__CLASS__, 'Delete']);
        $action->setParameters($param);

        new TQuestion('Deseja realmente excluir?', $action);
    }

    public static function Delete($param)
    {
        try {
            TTransaction::open('sample');
            $object = new Permissox($param['id']);
            $object->delete();
            TTransaction::close();

            new TMessage('info', 'Registro excluído com sucesso!');

            AdiantiCoreApplication::reloadPage(); // recarrega a página atual
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
