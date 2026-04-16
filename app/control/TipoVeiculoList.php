<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

class TipoVeiculoList extends TPage
{
    private const DATABASE   = 'sample';
    private const PAGE_LIMIT = 15;

    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;

    private static function getFilterData(): ?object
    {
        return TSession::getValue('TipoVeiculoList_filter_data');
    }

    private static function getNameFilter(): ?TFilter
    {
        return TSession::getValue('TipoVeiculoList_filter_nome');
    }

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_search_TipoVeiculo');
        $this->form->setFormTitle('Tipos de Veiculo');

        $nome = new TEntry('nome');
        $nome->setSize('100%');

        $row = $this->form->addFields([new TLabel('Nome'), $nome]);
        $row->layout = ['col-sm-4'];

        $this->form->setData(self::getFilterData());

        $searchButton = $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search');
        $searchButton->class = 'btn btn-sm btn-primary';

        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addAction('Novo', new TAction(['TipoVeiculoForm', 'onEdit']), 'fa:plus green');

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';
        $this->datagrid->setHeight(320);

        $columnId = new TDataGridColumn('id', 'ID', 'center', 60);
        $columnNome = new TDataGridColumn('nome', 'Nome', 'left');

        foreach ([[$columnId, 'id'], [$columnNome, 'nome']] as [$column, $field]) {
            $column->setAction(new TAction([$this, 'onReload'], ['order' => $field]));
            $this->datagrid->addColumn($column);
        }

        $editAction = new TDataGridAction(['TipoVeiculoForm', 'onEdit']);
        $editAction->setButtonClass('btn btn-default');
        $editAction->setLabel('Editar');
        $editAction->setImage('far:edit blue');
        $editAction->setField('id');
        $this->datagrid->addAction($editAction);

        $deleteAction = new TDataGridAction([$this, 'onDelete']);
        $deleteAction->setButtonClass('btn btn-default');
        $deleteAction->setLabel('Excluir');
        $deleteAction->setImage('far:trash-alt red');
        $deleteAction->setField('id');
        $this->datagrid->addAction($deleteAction);

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->enableCounters();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        $container = new TVBox;
        $container->style = 'width:100%';
        $container->add($this->form);
        $container->add($this->datagrid);
        $container->add($this->pageNavigation);

        parent::add($container);
    }

    public function onReload($param = null): void
    {
        $param = is_array($param) ? $param : [];

        try {
            TTransaction::open(self::DATABASE);

            $repository = new TRepository('TipoVeiculo');
            $criteria   = new TCriteria;

            if (empty($param['order'])) {
                $param['order'] = 'nome';
                $param['direction'] = 'asc';
            }

            $criteria->setProperties($param);
            $criteria->setProperty('limit', self::PAGE_LIMIT);

            $nameFilter = self::getNameFilter();
            if ($nameFilter) {
                $criteria->add($nameFilter);
            }

            $objects = $repository->load($criteria, false);
            $this->datagrid->clear();

            foreach ($objects ?? [] as $object) {
                $this->datagrid->addItem($object);
            }

            $criteria->resetProperties();
            $count = $repository->count($criteria);

            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit(self::PAGE_LIMIT);

            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onSearch(): void
    {
        $data = $this->form->getData();

        TSession::setValue('TipoVeiculoList_filter_data', $data);
        TSession::setValue(
            'TipoVeiculoList_filter_nome',
            !empty($data->nome) ? new TFilter('nome', 'like', "%{$data->nome}%") : null
        );

        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onClear(): void
    {
        TSession::setValue('TipoVeiculoList_filter_data', null);
        TSession::setValue('TipoVeiculoList_filter_nome', null);

        $this->form->clear();
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public static function onDelete($param): void
    {
        $action = new TAction([__CLASS__, 'onConfirmDelete']);
        $action->setParameters($param);

        new TQuestion('Deseja realmente excluir?', $action);
    }

    public static function onConfirmDelete($param): void
    {
        try {
            TTransaction::open(self::DATABASE);

            $object = new TipoVeiculo($param['key']);
            $object->delete();

            TTransaction::close();

            $reloadAction = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', 'Registro excluido', $reloadAction);
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function show(): void
    {
        if (!$this->loaded) {
            $this->onReload(func_get_arg(0));
        }

        parent::show();
    }
}
