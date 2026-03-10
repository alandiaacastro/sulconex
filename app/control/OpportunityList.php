<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Database\TTransaction;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Container\THBox;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Util\TXMLBreadCrumb;

class OpportunityList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded = false;

    private $database = 'sample';
    private $activeRecord = 'Opportunity';

    public function __construct()
    {
        parent::__construct();

        $this->form = new TForm('form_search_Opportunity');

        $companyName = new TEntry('company_name');
        $companyName->setSize('100%');
        $companyName->setProperty('placeholder', 'Nome da empresa');

        $row = new THBox;
        $row->add(new TLabel('Empresa:'));
        $row->add($companyName);
        $this->form->add($row);

        $btn = new TButton('Buscar');
        $btn->setLabel('Buscar');
        $btn->setImage('fa:search blue');
        $btn->setAction(new TAction([$this, 'onReload']), 'Buscar');

        $this->form->addField($companyName);
        $this->form->addField($btn);
        $this->form->add($btn);

        // Criação da DataGrid
        $this->datagrid = new TDataGrid;
        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', '5%'));
        $this->datagrid->addColumn(new TDataGridColumn('company_name', 'Empresa', 'left', '25%'));
        $this->datagrid->addColumn(new TDataGridColumn('responsible_name', 'Responsável', 'left', '20%'));
        $this->datagrid->addColumn(new TDataGridColumn('phone', 'Telefone', 'left', '15%'));
        $this->datagrid->addColumn(new TDataGridColumn('email', 'E-mail', 'left', '20%'));
        $this->datagrid->addColumn(new TDataGridColumn('status', 'Status', 'center', '10%'));

        $action_edit = new TDataGridAction(['OpportunityForm', 'onEdit'], ['key' => '{id}']);
        $action_edit->setLabel(_t('Edit'));
        $action_edit->setImage('far:edit blue');
        $this->datagrid->addAction($action_edit);

        $action_email = new TDataGridAction(['EmailComposerView', 'onLoadFromOpportunity'], ['opportunity_id' => '{id}']);
        $action_email->setLabel('Compor E-mail');
        $action_email->setImage('fas:envelope green');
        $this->datagrid->addAction($action_email);

        $action_del = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $action_del->setLabel(_t('Delete'));
        $action_del->setImage('far:trash-alt red');
        $this->datagrid->addAction($action_del);

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setLimit(10);

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';

        if (is_file('menu.xml')) {
            $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        }

        $panel = new TPanelGroup(_t('Listing'));
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $vbox->add($this->form);
        $vbox->add($panel);

        parent::add($vbox);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open($this->database);
            $repository = new TRepository($this->activeRecord);
            $criteria = new TCriteria;

            $limit = 10;
            $param = $param ?? [];
            $page = isset($param['page']) ? (int) $param['page'] : 1;
            $offset = ($page - 1) * $limit;

            $data = $this->form->getData();
            if (!empty($data->company_name)) {
                $criteria->add(new TFilter('company_name', 'like', "%{$data->company_name}%"));
            }

            $criteria->setProperty('limit', $limit);
            $criteria->setProperty('offset', $offset);
            $criteria->setProperty('order', 'id');

            $this->form->setData($data);
            $this->datagrid->clear();

            $objects = $repository->load($criteria);
            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            $count = $repository->count($criteria);
            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setPage($page);

            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onDelete($param)
    {
        if (isset($param['key'])) {
            $action_yes = new TAction([$this, 'confirmDelete']);
            $action_yes->setParameters(['key' => $param['key']]);
            new TQuestion('Deseja excluir o registro?', $action_yes);
        }
    }

    public function confirmDelete($param)
    {
        try {
            TTransaction::open($this->database);
            $object = new $this->activeRecord($param['key']);
            $object->delete();
            TTransaction::close();

            $this->onReload();
            new TMessage('info', 'Registro excluído com sucesso');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload($_GET);
        }
        parent::show();
    }
}


