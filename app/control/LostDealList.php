<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Wrapper\BootstrapDatagridWrapper;

class LostDealList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded = false;

    private $database     = 'sample';
    private $activeRecord = 'LostDeal';

    public function __construct()
    {
        parent::__construct();

        // ---- Botão Novo ----
        $btnNovo = new TButton('btn_novo');
        $btnNovo->setLabel('Novo');
        $btnNovo->setImage('fa:plus green');
        $btnNovo->setAction(new TAction(['LostDealForm', 'onEdit']));

        $toolbarForm = new TForm('form_lostdeal_toolbar');
        $toolbarForm->setFields([$btnNovo]);
        $toolbarForm->add($btnNovo);

        // ---- Filtro ----
        $this->form = new TForm('form_search_LostDeal');

        $company_name = new TEntry('company_name');
        $company_name->setMaxLength(120);
        $company_name->placeholder = 'Empresa...';

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        $table = new TTable;
        $table->style = 'width:100%';

        $row1 = $table->addRow();
        $row1->addCell(new TLabel('Empresa:'))->style = 'text-align:right;width:100px;';
        $row1->addCell($company_name);

        $btnSearch = new TButton('btn_search');
        $btnSearch->setLabel('Buscar');
        $btnSearch->setImage('fa:search blue');
        $btnSearch->setAction(new TAction([$this, 'onSearch']));

        $btnClear = new TButton('btn_clear');
        $btnClear->setLabel('Limpar');
        $btnClear->setImage('fa:eraser red');
        $btnClear->setAction(new TAction([$this, 'onClear']));

        $row2 = $table->addRow();
        $row2->addCell($btnSearch);
        $row2->addCell($btnClear);

        $this->form->setFields([$company_name, $btnSearch, $btnClear]);
        $this->form->add($table);

        // ---- Datagrid ----
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';
        $this->datagrid->datatable = 'true';

        $colId      = new TDataGridColumn('id',           'ID',       'center', '8%');
        $colEmpresa = new TDataGridColumn('company_name', 'Empresa',  'left',   '35%');
        $colTel     = new TDataGridColumn('phone',        'Telefone', 'left',   '20%');
        $colMotivo  = new TDataGridColumn('reason',       'Motivo',   'left',   '37%');

        $this->datagrid->addColumn($colId);
        $this->datagrid->addColumn($colEmpresa);
        $this->datagrid->addColumn($colTel);
        $this->datagrid->addColumn($colMotivo);

        $actionEdit   = new TDataGridAction(['LostDealForm', 'onEdit'],   ['key' => '{id}', 'register_state' => 'false']);
        $actionDelete = new TDataGridAction([$this,          'onDelete'], ['key' => '{id}', 'register_state' => 'false']);

        $actionEdit->setLabel('Editar');
        $actionEdit->setImage('fa:pencil-alt blue');
        $actionDelete->setLabel('Excluir');
        $actionDelete->setImage('fa:trash red');

        $this->datagrid->addAction($actionEdit);
        $this->datagrid->addAction($actionDelete);
        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        // ---- Layout ----
        $panel = new TPanelGroup('Negócios Perdidos');
        $panel->add($this->form);

        $container = new TVBox;
        $container->style = 'width:100%';
        $container->add($toolbarForm);
        $container->add($this->datagrid);
        $container->add($this->pageNavigation);
        $panel->addFooter($container);

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $box->add($panel);

        parent::add($box);
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();
        TSession::setValue(__CLASS__ . '_filter_data', $data);
        TSession::setValue(__CLASS__ . '_filter_company', $data->company_name ? new TFilter('company_name', 'like', "%{$data->company_name}%") : null);

        $this->form->setData($data);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onClear($param = null)
    {
        TSession::setValue(__CLASS__ . '_filter_data', null);
        TSession::setValue(__CLASS__ . '_filter_company', null);
        $this->form->clear();
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open($this->database);

            $repo     = new TRepository($this->activeRecord);
            $limit    = 15;
            $criteria = new TCriteria;

            $filter = TSession::getValue(__CLASS__ . '_filter_company');
            if ($filter) {
                $criteria->add($filter);
            }

            $criteria->setProperty('order',     'id');
            $criteria->setProperty('direction', 'desc');
            $criteria->setProperty('limit',     $limit);
            $criteria->setProperty('offset',    $param['offset'] ?? 0);

            $objects = $repo->load($criteria, false);
            $criteria->resetProperties();
            $count   = $repo->count($criteria);

            $this->datagrid->clear();
            if ($objects) {
                foreach ($objects as $obj) {
                    $this->datagrid->addItem($obj);
                }
            }

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

    public function onDelete($param = null)
    {
        $action_yes = new TAction([$this, 'onConfirmDelete']);
        $action_yes->setParameters($param);

        new TQuestion('Confirma exclusão do registro?', $action_yes);
    }

    public function onConfirmDelete($param = null)
    {
        try {
            TTransaction::open($this->database);
            $obj = new LostDeal($param['key']);
            $obj->delete();
            TTransaction::close();
            $this->onReload($param);
            new TMessage('info', 'Registro excluído com sucesso.');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload(func_get_arg(0));
        }
        parent::show();
    }
}
