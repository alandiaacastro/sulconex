<?php
class ClientesList extends TPage
{
    private $datagrid, $form, $pageNavigation, $loaded;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_search_clientes');
        $this->form->setFormTitle('🔎 Buscar Clientes');

        $nome = new TEntry('nome');
        $this->form->addFields([new TLabel('Nome')], [$nome]);
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');

        // Cria datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'right', '50px'));
        $this->datagrid->addColumn(new TDataGridColumn('nome', 'Nome', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('email', 'Email', 'left'));
        $this->datagrid->addColumn(new TDataGridColumn('telefone', 'Telefone', 'left'));

        $action_edit = new TDataGridAction(['ClientesForm', 'onEdit'], ['id' => '{id}']);
        $action_edit->setLabel('Editar');
        $action_edit->setImage('fa:edit blue');
        $this->datagrid->addAction($action_edit);

        $action_delete = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}']);
        $action_delete->setLabel('Excluir');
        $action_delete->setImage('fa:trash red');
        $this->datagrid->addAction($action_delete);
        $this->form->addAction('Novo', new TAction(['ClientesForm', 'onEdit']), 'fa:plus green');
        $this->datagrid->createModel();

        // Navegação
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        // Layout
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($this->datagrid);
        $container->add($this->pageNavigation);

        parent::add($container);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');

            $repository = new TRepository('Clientes');
            $limit = 10;
            $criteria = new TCriteria;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            // filtro por nome
            $data = $this->form->getData();
            if (!empty($data->nome)) {
                $criteria->add(new TFilter('nome', 'like', "%{$data->nome}%"));
            }

            $clientes = $repository->load($criteria);
            $this->datagrid->clear();

            foreach ($clientes as $cliente) {
                $this->datagrid->addItem($cliente);
            }

            $count = $repository->count($criteria);
            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit($limit);

            TTransaction::close();
            $this->loaded = true;

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onSearch($param)
    {
        TSession::setValue('Clientes_filter_data', (object) $param);
        $this->form->setData((object) $param);
        $this->onReload($param);
    }

    public function onDelete($param)
    {
        $action = new TAction([$this, 'Delete']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente excluir o cliente?', $action);
    }

    public function Delete($param)
    {
        try {
            TTransaction::open('sample');
            $cliente = new Clientes($param['id']);
            $cliente->delete();
            TTransaction::close();
            $this->onReload();
            new TMessage('info', 'Cliente excluído com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }
}


