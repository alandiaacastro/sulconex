<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TUniqueSearch;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;

class TabelaFreteList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;

    public function __construct()
    {
        parent::__construct();

        // Carregar cidades para autocomplete dos filtros
        $cidades = [];
        TTransaction::open('default');
        $lista = (new TRepository('CidadeUf'))->load(null, false);
        if ($lista) {
            foreach ($lista as $c) {
                $label = $c->nome . ',' . $c->uf;
                $cidades[$label] = $label;
            }
        }
        TTransaction::close();

        // Formulário de filtros
        $this->form = new BootstrapFormBuilder('form_search_TabelaFrete');
        $this->form->setFormTitle('Tabela de Fretes');

        $origem       = new TUniqueSearch('origem');
        $fronteira    = new TUniqueSearch('fronteira');
        $destino      = new TUniqueSearch('destino');
        $tipo_veiculo = new TEntry('tipo_veiculo');

        $origem->addItems($cidades);
        $fronteira->addItems($cidades);
        $destino->addItems($cidades);

        $origem->setMinLength(2);
        $fronteira->setMinLength(2);
        $destino->setMinLength(2);

        $origem->setSize('100%');
        $fronteira->setSize('100%');
        $destino->setSize('100%');
        $tipo_veiculo->setSize('100%');
        $tipo_veiculo->setTip('Ex: GERAL, CARRETA SIDER, TRUCK');

        $this->form->addFields([new TLabel('Origem')],             [$origem]);
        $this->form->addFields([new TLabel('Fronteira / Aduana')], [$fronteira]);
        $this->form->addFields([new TLabel('Destino')],            [$destino]);
        $this->form->addFields([new TLabel('Tipo Veículo')],       [$tipo_veiculo]);

        $this->form->setData(TSession::getValue('TabelaFreteList_filter_data'));

        $btn = $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search');
        $btn->class = 'btn btn-sm btn-primary';
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addAction('Novo',   new TAction(['TabelaFreteForm', 'onEdit']), 'fa:plus green');

        // DataGrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->setHeight(320);

        $column_id         = new TDataGridColumn('id',           'ID',          'center', 50);
        $column_tipo       = new TDataGridColumn('tipo_veiculo', 'Tipo Veíc.',  'left',  120);
        $column_origem     = new TDataGridColumn('origem',       'Origem',      'left');
        $column_fronteira  = new TDataGridColumn('fronteira',    'Fronteira',   'left');
        $column_destino    = new TDataGridColumn('destino',      'Destino',     'left');
        $column_val        = new TDataGridColumn('valor_frete',  'Valor Frete', 'right', 120);
        $column_atualizacao= new TDataGridColumn('atualizacao',  'Atualização', 'center',130);

        $column_val->setTransformer(function($value) {
            return $value ? 'R$ ' . number_format($value, 2, ',', '.') : '';
        });

        $column_atualizacao->setTransformer(function($value) {
            if (!$value) return '';
            return (new DateTime($value))->format('d/m/Y H:i');
        });

        // Ordenação
        foreach ([
            $column_id         => 'id',
            $column_tipo       => 'tipo_veiculo',
            $column_origem     => 'origem',
            $column_fronteira  => 'fronteira',
            $column_destino    => 'destino',
            $column_val        => 'valor_frete',
            $column_atualizacao=> 'atualizacao',
        ] as $col => $field) {
            $col->setAction(new TAction([$this, 'onReload'], ['order' => $field]));
            $this->datagrid->addColumn($col);
        }

        // Ações
        $action_edit = new TDataGridAction(['TabelaFreteForm', 'onEdit']);
        $action_edit->setButtonClass('btn btn-default');
        $action_edit->setLabel('Editar');
        $action_edit->setImage('far:edit blue');
        $action_edit->setField('id');
        $this->datagrid->addAction($action_edit);

        $action_del = new TDataGridAction([$this, 'onDelete']);
        $action_del->setButtonClass('btn btn-default');
        $action_del->setLabel('Excluir');
        $action_del->setImage('far:trash-alt red');
        $action_del->setField('id');
        $this->datagrid->addAction($action_del);

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->enableCounters();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($this->form);
        $vbox->add($this->datagrid);
        $vbox->add($this->pageNavigation);

        parent::add($vbox);
    }

    public function onReload($param = null)
    {
        try
        {
            TTransaction::open('sample');
            $repository = new TRepository('TabelaFrete');
            $limit = 15;

            $criteria = new TCriteria;

            if (empty($param['order'])) {
                $param['order']     = 'id';
                $param['direction'] = 'desc';
            }

            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            $filters = ['origem', 'fronteira', 'destino', 'tipo_veiculo'];
            foreach ($filters as $f) {
                $val = TSession::getValue("TabelaFreteList_filter_{$f}");
                if ($val) {
                    $criteria->add($val);
                }
            }

            $objects = $repository->load($criteria, false);
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
            $this->loaded = true;
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onSearch()
    {
        $data = $this->form->getData();
        TSession::setValue('TabelaFreteList_filter_data', $data);

        $campos = ['origem', 'fronteira', 'destino', 'tipo_veiculo'];
        foreach ($campos as $campo) {
            if (!empty($data->$campo)) {
                TSession::setValue("TabelaFreteList_filter_{$campo}",
                    new TFilter($campo, 'like', "%{$data->$campo}%"));
            } else {
                TSession::setValue("TabelaFreteList_filter_{$campo}", null);
            }
        }

        $this->onReload();
    }

    public function onClear()
    {
        TSession::setValue('TabelaFreteList_filter_data', null);
        foreach (['origem', 'fronteira', 'destino', 'tipo_veiculo'] as $campo) {
            TSession::setValue("TabelaFreteList_filter_{$campo}", null);
        }
        $this->form->clear();
        $this->onReload();
    }

    public static function onDelete($param)
    {
        $action = new TAction([__CLASS__, 'Delete']);
        $action->setParameters($param);
        new TQuestion('Deseja realmente excluir o registro?', $action);
    }

    public static function Delete($param)
    {
        try
        {
            TTransaction::open('sample');
            $object = new TabelaFrete($param['key']);
            $object->delete();
            TTransaction::close();

            $posAction = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', 'Registro excluído', $posAction);
        }
        catch (Exception $e)
        {
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
