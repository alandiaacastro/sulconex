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
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TUniqueSearch;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

class TabelaFreteList extends TPage
{
    private const DATABASE   = 'sample';
    private const PAGE_LIMIT = 15;
    private const FILTER_MAP = [
        'tipo_veiculo' => 'f_tipo_veiculo',
        'tipo'         => 'f_tipo',
        'origem'       => 'f_origem',
        'destino'      => 'f_destino',
        'valor_min'    => 'f_valor_min',
    ];

    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded;

    private static function filterKey(string $name): string
    {
        return 'TabelaFreteList_filter_' . $name;
    }

    private static function getFilters(): array
    {
        $filters = [];

        foreach (array_keys(self::FILTER_MAP) as $name) {
            $filters[$name] = TSession::getValue(self::filterKey($name));
        }

        return $filters;
    }

    private static function getFormData(): object
    {
        $filters = self::getFilters();

        return (object) [
            'f_tipo_veiculo' => $filters['tipo_veiculo'],
            'f_tipo'         => $filters['tipo'],
            'f_origem'       => $filters['origem'],
            'f_destino'      => $filters['destino'],
            'f_valor_min'    => $filters['valor_min'],
        ];
    }

    private static function storeFilters(array $param): void
    {
        foreach (self::FILTER_MAP as $name => $field) {
            TSession::setValue(self::filterKey($name), $param[$field] ?? null);
        }
    }

    private static function clearStoredFilters(): void
    {
        foreach (array_keys(self::FILTER_MAP) as $name) {
            TSession::setValue(self::filterKey($name), null);
        }
    }

    private static function applyFilters(TCriteria $criteria, array $filters): void
    {
        if (!empty($filters['tipo_veiculo'])) {
            $criteria->add(new TFilter('tipo_veiculo', '=', TabelaFrete::normalizeUpper($filters['tipo_veiculo'])));
        }

        if (!empty($filters['tipo'])) {
            $criteria->add(new TFilter('tipo', '=', TabelaFrete::normalizeUpper($filters['tipo'])));
        }

        if (!empty($filters['origem'])) {
            $criteria->add(new TFilter('origem', 'like', '%' . TabelaFrete::normalizeUpper($filters['origem']) . '%'));
        }

        if (!empty($filters['destino'])) {
            $criteria->add(new TFilter('destino', 'like', '%' . TabelaFrete::normalizeUpper($filters['destino']) . '%'));
        }

        if (!empty($filters['valor_min'])) {
            $criteria->add(new TFilter('valor_frete', '>=', TabelaFrete::parseMoney($filters['valor_min'])));
        }
    }

    public function __construct()
    {
        parent::__construct();

        $cidades = TabelaFrete::loadCidadeOptions();

        $this->form = new BootstrapFormBuilder('form_search_TabelaFrete');
        $this->form->setFormTitle('Tabela de Fretes - Listagem');

        $tipoVeiculo = new TDBCombo('f_tipo_veiculo', self::DATABASE, 'TipoVeiculo', 'nome', '{nome}', 'nome asc');
        $tipoVeiculo->setSize('100%');
        $tipoVeiculo->enableSearch();

        $tipo = new TCombo('f_tipo');
        $tipo->addItems(['' => 'Todos'] + array_combine(TabelaFrete::TIPOS, TabelaFrete::TIPOS));
        $tipo->setSize('100%');

        $origem = new TUniqueSearch('f_origem');
        $origem->addItems($cidades);
        $origem->setMinLength(2);
        $origem->setSize('100%');

        $destino = new TUniqueSearch('f_destino');
        $destino->addItems($cidades);
        $destino->setMinLength(2);
        $destino->setSize('100%');

        $valorMin = new TNumeric('f_valor_min', 2, ',', '.', true);
        $valorMin->setSize('100%');
        $valorMin->setTip('Filtra por frete minimo');

        $row = $this->form->addFields(
            [new TLabel('Tipo Veiculo'), $tipoVeiculo],
            [new TLabel('Tipo'), $tipo],
            [new TLabel('Frete min. (R$)'), $valorMin]
        );
        $row->layout = ['col-sm-5', 'col-sm-2', 'col-sm-2'];

        $row = $this->form->addFields(
            [new TLabel('Origem'), $origem],
            [new TLabel('Destino'), $destino]
        );
        $row->layout = ['col-sm-6', 'col-sm-6'];

        $searchButton = $this->form->addAction('Buscar na lista', new TAction([$this, 'onSearch']), 'fa:search');
        $searchButton->class = 'btn btn-sm btn-info';

        $reloadButton = $this->form->addAction('Atualizar', new TAction([$this, 'onReload']), 'fa:sync');
        $reloadButton->class = 'btn btn-sm btn-default';

        $clearButton = $this->form->addAction('Limpar filtros', new TAction([$this, 'onClearFilters']), 'fa:eraser red');
        $clearButton->class = 'btn btn-sm btn-default';

        $newButton = $this->form->addAction(
            'Novo cadastro',
            new TAction(['TabelaFreteForm', 'onEdit'], ['register_state' => 'false', 'target_container' => 'adianti_right_panel']),
            'fa:plus green'
        );
        $newButton->class = 'btn btn-sm btn-primary';

        $exportButton = $this->form->addAction('Exportar XLS', new TAction(['TabelaFreteImportExport', 'onExport']), 'fa:file-excel green');
        $exportButton->class = 'btn btn-sm btn-outline-success';

        $importButton = $this->form->addAction('Importar CSV', new TAction(['TabelaFreteImportExport', 'onOpenImport']), 'fa:file-upload blue');
        $importButton->class = 'btn btn-sm btn-outline-primary';

        $this->form->setData(self::getFormData());

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->setHeight(360);
        $this->datagrid->disableDefaultClick();

        $columnId          = new TDataGridColumn('id', 'ID', 'center', 50);
        $columnTipoVeiculo = new TDataGridColumn('tipo_veiculo', 'Veiculo', 'left', 130);
        $columnTipo        = new TDataGridColumn('tipo', 'Tipo', 'center', 70);
        $columnOrigem      = new TDataGridColumn('origem', 'Origem', 'left');
        $columnDestino     = new TDataGridColumn('destino', 'Destino', 'left');
        $columnFrete       = new TDataGridColumn('valor_frete', 'Frete (R$)', 'right', 120);
        $columnAtualizacao = new TDataGridColumn('atualizacao', 'Atualizacao', 'center', 130);

        $columnFrete->setDataProperty('style', 'text-align:right');
        $columnFrete->setTransformer(function ($value) {
            return $value ? number_format((float) $value, 2, ',', '.') : '0,00';
        });

        $inlineEdit = new TDataGridAction([$this, 'onSaveInlineFrete']);
        $inlineEdit->setField('id');
        $columnFrete->setEditAction($inlineEdit);

        $columnAtualizacao->setTransformer(function ($value) {
            return TabelaFrete::formatAtualizacao($value);
        });

        foreach ([
            [$columnId, 'id'],
            [$columnTipoVeiculo, 'tipo_veiculo'],
            [$columnTipo, 'tipo'],
            [$columnOrigem, 'origem'],
            [$columnDestino, 'destino'],
            [$columnFrete, 'valor_frete'],
            [$columnAtualizacao, 'atualizacao'],
        ] as [$column, $field]) {
            $column->setAction(new TAction([$this, 'onReload'], ['order' => $field]));
            $this->datagrid->addColumn($column);
        }

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
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($this->datagrid);
        $container->add($this->pageNavigation);

        parent::add($container);
    }

    public function onReload($param = null)
    {
        $param = is_array($param) ? $param : [];

        try {
            TTransaction::open(self::DATABASE);

            $repository = new TRepository('TabelaFrete');
            $criteria   = new TCriteria;

            if (empty($param['order'])) {
                $param['order'] = 'id';
                $param['direction'] = 'desc';
            }

            $criteria->setProperties($param);
            $criteria->setProperty('limit', self::PAGE_LIMIT);

            self::applyFilters($criteria, self::getFilters());

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
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onSearch($param = null)
    {
        $param = is_array($param) ? $param : [];

        self::storeFilters($param);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public function onClearFilters($param = null): void
    {
        self::clearStoredFilters();
        $this->form->setData(self::getFormData());
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public static function onSaveInlineFrete($param = null): void
    {
        $param = is_array($param) ? $param : [];

        try {
            $id    = $param['key'] ?? null;
            $value = $param['value'] ?? null;

            if (!$id || $value === null) {
                return;
            }

            $frete = TabelaFrete::parseMoney($value);
            if ($frete <= 0) {
                throw new Exception('Informe um valor de frete maior que zero.');
            }

            TTransaction::open(self::DATABASE);
            $object = new TabelaFrete($id);
            $object->valor_frete = $frete;
            $object->atualizacao = date('Y-m-d H:i:s');
            $object->store();
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onDelete($param)
    {
        $action = new TAction([__CLASS__, 'onConfirmDelete']);
        $action->setParameters($param);

        new TQuestion('Deseja realmente excluir o registro?', $action);
    }

    public static function onConfirmDelete($param)
    {
        try {
            TTransaction::open(self::DATABASE);
            $object = new TabelaFrete($param['key']);
            $object->delete();
            TTransaction::close();

            $reloadAction = new TAction([__CLASS__, 'onReload']);
            new TMessage('info', 'Registro excluido', $reloadAction);
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
