<?php

class FaturaList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;

    private static $database = 'sample';
    private static $activeRecord = 'Fatura';
    private static $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();

        // Formulario de filtros
        $this->form = new BootstrapFormBuilder('form_search_fatura');
        $this->form->setFormTitle('Faturas');

        $id            = new TEntry('id');
        $numero_fatura = new TEntry('numero_fatura');
        $numero_crt    = new TEntry('numero_crt');
        $fatura_cliente = new TEntry('fatura_cliente');
        $pessoa_id     = new TDBUniqueSearch('pessoa_id', self::$database, 'Clientes', 'id', 'nome');

        $emissao_de    = new TDate('emissao_de');
        $emissao_ate   = new TDate('emissao_ate');

        foreach ([$id, $numero_fatura, $numero_crt, $fatura_cliente, $pessoa_id, $emissao_de, $emissao_ate] as $f) {
            $f->setSize('100%');
        }

        $pessoa_id->setMinLength(0);
        $pessoa_id->setMask('{nome}');

        $emissao_de->setMask('dd/mm/yyyy');
        $emissao_de->setDatabaseMask('yyyy-mm-dd');
        $emissao_ate->setMask('dd/mm/yyyy');
        $emissao_ate->setDatabaseMask('yyyy-mm-dd');

        $this->form->addFields([new TLabel('ID')], [$id], [new TLabel('Numero fatura')], [$numero_fatura]);
        $this->form->addFields([new TLabel('Numero CRT')], [$numero_crt], [new TLabel('Fatura cliente')], [$fatura_cliente]);
        $this->form->addFields([new TLabel('Cliente')], [$pessoa_id]);
        $this->form->addFields([new TLabel('Emissao (de)')], [$emissao_de], [new TLabel('Emissao (ate)')], [$emissao_ate]);

        $this->form->addAction('Filtrar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addAction('Nova', new TAction(['FaturaForm', 'onEdit']), 'fa:plus green');
        $this->form->addAction('Recarregar', new TAction([$this, 'onReload']), 'fa:refresh');

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        // Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->datatable = 'true';

        $this->datagrid->addColumn(new TDataGridColumn('id', 'ID', 'center', '5%'));
        $this->datagrid->addColumn(new TDataGridColumn('numero_fatura', 'Fatura', 'left', '10%'));
        $this->datagrid->addColumn(new TDataGridColumn('numero_crt', 'CRT', 'left', '10%'));
        $this->datagrid->addColumn(new TDataGridColumn('fatura_cliente', 'Fatura cliente', 'left', '15%'));

        $colCliente = new TDataGridColumn('clientekey->nome', 'Cliente', 'left', '25%');
        $colCliente->setTransformer(function ($val, $obj) {
            try {
                return $obj->clientekey->nome ?? '-';
            } catch (Exception $e) {
                return '-';
            }
        });
        $this->datagrid->addColumn($colCliente);

        $colEmissao = new TDataGridColumn('emissao', 'Emissao', 'center', '10%');
        $colEmissao->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
        });
        $this->datagrid->addColumn($colEmissao);

        $colVenc = new TDataGridColumn('vencimento', 'Vencimento', 'center', '10%');
        $colVenc->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
        });
        $this->datagrid->addColumn($colVenc);

        $colValor = new TDataGridColumn('valor_fatura', 'Valor', 'right', '10%');
        $colValor->setTransformer(function ($value) {
            if ($value === null || $value === '') {
                return '';
            }
            return number_format((float) $value, 2, ',', '.');
        });
        $this->datagrid->addColumn($colValor);

        // Acoes
        $actionEdit = new TDataGridAction(['FaturaForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:edit blue');

        $actionDelete = new TDataGridAction([__CLASS__, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($actionDelete, 'Excluir', 'fa:trash red');

        $actionReais = new TDataGridAction(['FaturaReport', 'onGenerateReais'], ['key' => '{id}']);
        $this->datagrid->addAction($actionReais, 'Relatorio (R$)', 'fa:file-pdf green');

        $actionDolar = new TDataGridAction(['FaturaReport', 'onGenerateDolar'], ['key' => '{id}']);
        $this->datagrid->addAction($actionDolar, 'Relatorio (US$)', 'fa:file-pdf orange');

        $this->datagrid->createModel();

        // Paginacao
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->enableCounters();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        $panel = new TPanelGroup('Listagem de faturas');
        $panel->add($this->form);
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($panel);

        parent::add($container);
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();

        TSession::setValue(__CLASS__ . '_filter_id', $data->id ? new TFilter('id', '=', $data->id) : null);
        TSession::setValue(__CLASS__ . '_filter_numero_fatura', $data->numero_fatura ? new TFilter('numero_fatura', 'like', "%{$data->numero_fatura}%") : null);
        TSession::setValue(__CLASS__ . '_filter_numero_crt', $data->numero_crt ? new TFilter('numero_crt', 'like', "%{$data->numero_crt}%") : null);
        TSession::setValue(__CLASS__ . '_filter_fatura_cliente', $data->fatura_cliente ? new TFilter('fatura_cliente', 'like', "%{$data->fatura_cliente}%") : null);
        TSession::setValue(__CLASS__ . '_filter_pessoa_id', $data->pessoa_id ? new TFilter('pessoa_id', '=', $data->pessoa_id) : null);

        TSession::setValue(__CLASS__ . '_filter_emissao_de', $data->emissao_de ? new TFilter('emissao', '>=', $data->emissao_de) : null);
        TSession::setValue(__CLASS__ . '_filter_emissao_ate', $data->emissao_ate ? new TFilter('emissao', '<=', $data->emissao_ate) : null);

        TSession::setValue(__CLASS__ . '_filter_data', $data);

        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open(self::$database);
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $repo = new TRepository(self::$activeRecord);
            $limit = 10;
            $criteria = new TCriteria;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);
            $criteria->setProperty('order', self::$primaryKey);
            $criteria->setProperty('direction', 'desc');

            foreach (['_filter_id', '_filter_numero_fatura', '_filter_numero_crt', '_filter_fatura_cliente', '_filter_pessoa_id', '_filter_emissao_de', '_filter_emissao_ate'] as $sf) {
                $filter = TSession::getValue(__CLASS__ . $sf);
                if ($filter) {
                    $criteria->add($filter);
                }
            }

            $this->datagrid->clear();
            $objects = $repo->load($criteria, FALSE);
            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }

            $criteria->resetProperties();
            $count = $repo->count($criteria);

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

    public static function onDelete($param)
    {
        $action = new TAction([__CLASS__, 'delete'], $param);
        new TQuestion('Deseja realmente excluir esta fatura?', $action);
    }

    public static function delete($param)
    {
        try {
            TTransaction::open(self::$database);
            TTransaction::get()->exec('PRAGMA foreign_keys = ON');

            $object = new Fatura($param['key']);
            $object->delete();

            TTransaction::close();
            new TMessage('info', 'Registro excluido com sucesso!', new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao excluir: ' . $e->getMessage());
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

