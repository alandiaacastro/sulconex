<?php

class PermissoList extends TPage
{
    private $datagrid;
    private $pageNavigation;
    private static $database = 'sample';
    private static $activeRecord = 'Permisso';
    private static $primaryKey = 'id';
    private static $formName = 'PermissoList';

    public function __construct()
    {
        parent::__construct();

        // Ação principal (sem filtros)
        $btnNew = new TAction(['PermissoForm', 'onShow']);

        // Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';
        $this->datagrid->enablePopover('Detalhes', '{dados_documentos}');

        // Colunas
        $col_id             = new TDataGridColumn('id', 'ID', 'center', '50');
        $col_permisso       = new TDataGridColumn('permisso', 'Permissão', 'center');
        $col_pais_destino   = new TDataGridColumn('pais_destino', 'País Destino', 'center');
        $col_transportadora = new TDataGridColumn('transportadora', 'Transportadora', 'center');
        $col_logo           = new TDataGridColumn('logo', 'Logo', 'center');

        // Transformador para exibir a imagem
        $col_logo->setTransformer(function($value) {
            if ($value && file_exists('app/images/logos/' . $value)) {
                return "<img src='app/images/logos/{$value}' style='width:110px; height:auto; border-radius:6px; border:1px solid #e5e7eb; padding:2px; background:#fff'>";
            }
            return '<span class="text-muted">—</span>';
        });

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_permisso);
        $this->datagrid->addColumn($col_pais_destino);
        $this->datagrid->addColumn($col_transportadora);
        $this->datagrid->addColumn($col_logo);

        // Ações do grid
        $action_edit = new TDataGridAction(['PermissoForm', 'onEdit'], ['id' => '{id}']);
        $action_edit->setLabel('Editar');
        $action_edit->setImage('fas:edit blue');

        $action_delete = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}']);
        $action_delete->setLabel('Excluir');
        $action_delete->setImage('fas:trash-alt red');

        $this->datagrid->addAction($action_edit);
        $this->datagrid->addAction($action_delete);
        $this->datagrid->createModel();

        // Navegação
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->enableCounters();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        // Painel
        $panel = new TPanelGroup('Permissões CRT');
        $panel->addHeaderActionLink('Novo', $btnNew, 'fas:plus green');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        // Layout final
        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $vbox->add($panel);

        parent::add($vbox);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open(self::$database);

            $repository = new TRepository(self::$activeRecord);
            $limit = 10;
            $criteria = new TCriteria;
            $criteria->setProperty('order', 'id desc');
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
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
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
            $key = $param['id'];
            TTransaction::open(self::$database);

            $object = new Permisso($key);
            if ($object->logo && file_exists('app/images/logos/' . $object->logo)) {
                unlink('app/images/logos/' . $object->logo);
            }
            $object->delete();

            TTransaction::close();
            $this->onReload();

            new TMessage('info', 'Registro excluído com sucesso!');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function show()
    {
        $this->onReload();
        parent::show();
    }
}
