<?php

class PropostaToneladaList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded = false;

    public function __construct()
    {
        parent::__construct();
        PropostaTonelada::ensureSchema();

        $this->form = new BootstrapFormBuilder('form_search_proposta_tonelada');
        $this->form->setFormTitle('Propostas por Tonelada');

        $numero = new TEntry('numero_proposta');
        $status = new TCombo('status');
        $cliente_id = new TDBUniqueSearch('cliente_id', 'sample', 'Clientes', 'id', 'nome');
        $data_de = new TDate('data_de');
        $data_ate = new TDate('data_ate');

        $status->addItems([
            '' => 'Todos',
            'Em Analise' => 'Em Analise',
            'Aprovada' => 'Aprovada',
            'Rejeitada' => 'Rejeitada',
        ]);
        $cliente_id->setMinLength(0);
        $cliente_id->setMask('{nome}');
        $data_de->setMask('dd/mm/yyyy');
        $data_de->setDatabaseMask('yyyy-mm-dd');
        $data_ate->setMask('dd/mm/yyyy');
        $data_ate->setDatabaseMask('yyyy-mm-dd');

        foreach ([$numero, $status, $cliente_id, $data_de, $data_ate] as $field) {
            $field->setSize('100%');
        }

        $this->form->addFields([new TLabel('Numero')], [$numero], [new TLabel('Status')], [$status]);
        $this->form->addFields([new TLabel('Cliente')], [$cliente_id], [new TLabel('Data de')], [$data_de], [new TLabel('ate')], [$data_ate]);

        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser gray');
        $this->form->addActionLink('Nova Proposta', new TAction(['PropostaToneladaForm', 'onEdit']), 'fa:plus green');

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';

        $colNumero = new TDataGridColumn('numero_proposta', 'Numero', 'left', '12%');
        $colCliente = new TDataGridColumn('cliente_id', 'Cliente', 'left', '25%');
        $colCrt = new TDataGridColumn('conhecimento_id', 'CRT', 'center', '10%');
        $colTrecho = new TDataGridColumn('origem', 'Trecho', 'left', '21%');
        $colTon = new TDataGridColumn('toneladas', 'Toneladas', 'right', '9%');
        $colValorTon = new TDataGridColumn('valor_por_ton', 'R$/Ton', 'right', '9%');
        $colTotal = new TDataGridColumn('valor_total', 'Valor Total', 'right', '10%');
        $colStatus = new TDataGridColumn('status', 'Status', 'center', '10%');

        $colCliente->setTransformer(function ($value) {
            if (empty($value)) {
                return '';
            }
            try {
                return (new Clientes($value))->nome ?? '';
            } catch (Exception $e) {
                return '';
            }
        });

        $colTrecho->setTransformer(function ($value, $obj) {
            return trim(($obj->origem ?? '') . ' -> ' . ($obj->destino ?? ''));
        });

        $colCrt->setTransformer(function ($value) {
            if (empty($value)) {
                return '-';
            }

            try {
                return (new Conhecimento($value))->numero ?? '-';
            } catch (Exception $e) {
                return '-';
            }
        });

        $colTon->setTransformer(function ($value) {
            return number_format((float) $value, 3, ',', '.');
        });

        $colValorTon->setTransformer(function ($value) {
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        });

        $colTotal->setTransformer(function ($value) {
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        });

        $colStatus->setTransformer(function ($value) {
            $cores = [
                'Em Analise' => '#f59e0b',
                'Aprovada' => '#198754',
                'Rejeitada' => '#dc3545',
            ];
            $cor = $cores[$value] ?? '#6c757d';
            return "<span class='badge' style='background:{$cor};color:#fff'>{$value}</span>";
        });

        $this->datagrid->addColumn($colNumero);
        $this->datagrid->addColumn($colCliente);
        $this->datagrid->addColumn($colCrt);
        $this->datagrid->addColumn($colTrecho);
        $this->datagrid->addColumn($colTon);
        $this->datagrid->addColumn($colValorTon);
        $this->datagrid->addColumn($colTotal);
        $this->datagrid->addColumn($colStatus);

        $actionEdit = new TDataGridAction(['PropostaToneladaForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($actionEdit, 'Editar', 'fa:edit blue');

        $actionAprovar = new TDataGridAction([$this, 'onAprovar'], ['key' => '{id}']);
        $this->datagrid->addAction($actionAprovar, 'Aprovar / Gerar CRT', 'fa:check-circle green');

        $actionAbrirCrt = new TDataGridAction([$this, 'onAbrirCrt'], ['key' => '{id}']);
        $actionAbrirCrt->setDisplayCondition(function ($obj) {
            return !empty($obj->conhecimento_id);
        });
        $this->datagrid->addAction($actionAbrirCrt, 'Abrir CRT', 'fa:book blue');

        $actionDelete = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($actionDelete, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        $panel = new TPanelGroup('Listagem');
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width:100%';
        $container->add(new TXMLBreadCrumb('menu.xml', get_class($this)));
        $container->add($this->form);
        $container->add($panel);

        parent::add($container);
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();
        TSession::setValue(__CLASS__ . '_filter_data', $data);
        $this->onReload(['offset' => 0, 'first_page' => 1]);
    }

    public static function onClear($param = null)
    {
        TSession::setValue(__CLASS__ . '_filter_data', null);
        TApplication::loadPage(__CLASS__, 'onReload');
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');

            $repo = new TRepository('PropostaTonelada');
            $criteria = new TCriteria;
            $limit = 20;
            $param = is_array($param) ? $param : [];
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);
            $criteria->setProperty('order', 'id desc');

            $filterData = TSession::getValue(__CLASS__ . '_filter_data');
            if ($filterData) {
                $this->form->setData($filterData);

                if (!empty($filterData->numero_proposta)) {
                    $criteria->add(new TFilter('numero_proposta', 'like', '%' . $filterData->numero_proposta . '%'));
                }
                if (!empty($filterData->status)) {
                    $criteria->add(new TFilter('status', '=', $filterData->status));
                }
                if (!empty($filterData->cliente_id)) {
                    $criteria->add(new TFilter('cliente_id', '=', $filterData->cliente_id));
                }
                if (!empty($filterData->data_de)) {
                    $criteria->add(new TFilter('data_proposta', '>=', TDate::convertToMask($filterData->data_de, 'dd/mm/yyyy', 'yyyy-mm-dd')));
                }
                if (!empty($filterData->data_ate)) {
                    $criteria->add(new TFilter('data_proposta', '<=', TDate::convertToMask($filterData->data_ate, 'dd/mm/yyyy', 'yyyy-mm-dd')));
                }
            }

            $this->datagrid->clear();
            $items = $repo->load($criteria);
            if ($items) {
                foreach ($items as $item) {
                    $this->datagrid->addItem($item);
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
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onDelete($param)
    {
        $action = new TAction([__CLASS__, 'doDelete']);
        $action->setParameters($param);
        new TQuestion('Confirma exclusao desta proposta?', $action);
    }

    public static function onAprovar($param)
    {
        try {
            TTransaction::open('sample');
            $proposta = new PropostaTonelada($param['key']);

            if (!empty($proposta->conhecimento_id)) {
                $proposta->status = 'Aprovada';
                $proposta->store();
                TTransaction::close();
                TApplication::loadPage('ConhecimentoForm', 'onEdit', ['key' => $proposta->conhecimento_id]);
                return;
            }

            $form = new BootstrapFormBuilder('form_aprovar_proposta_tonelada');
            $form->setProperty('style', 'width:100%');

            $proposta_id = new THidden('proposta_id');
            $proposta_id->setValue($proposta->id);
            $permisso_id = new TDBCombo('permisso_id', 'sample', 'Permisso', 'id', 'permisso');
            $permisso_id->enableSearch();
            $permisso_id->setSize('100%');

            $form->addFields([$proposta_id]);
            $form->addFields([new TLabel('Permissao para gerar o CRT')], [$permisso_id]);
            $form->addAction('Gerar CRT', new TAction([__CLASS__, 'doAprovarGerarCrt']), 'fa:check green');
            $form->addAction('Cancelar', new TAction([__CLASS__, 'closeGenerateCrtWindow']), 'fa:times red');

            new TInputDialog('APROVAR PROPOSTA E GERAR CRT', $form);
            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function doAprovarGerarCrt($param)
    {
        try {
            if (empty($param['permisso_id'])) {
                throw new Exception('Selecione a permissao para gerar o CRT.');
            }

            TTransaction::open('sample');
            $proposta = new PropostaTonelada($param['proposta_id']);
            $permissao = new Permisso($param['permisso_id']);

            $novoNumero = (int) $permissao->numerocrt + 1;
            $permissao->numerocrt = $novoNumero;
            $permissao->store();

            $crt = new Conhecimento;
            $crt->permisso_id = $permissao->id;
            $crt->numero = $permissao->permisso . str_pad($novoNumero, 5, '0', STR_PAD_LEFT);
            $crt->status_crt_id = 1;
            $crt->pagador_id = $proposta->cliente_id;
            $crt->local_emissao = $proposta->origem;
            $crt->local_responsabilidade = $proposta->fronteira;
            $crt->local_entrega = $proposta->destino;
            $crt->descricao_mercadoria = $proposta->descricao_mercadoria;
            $crt->peso_bruto_kg = ((float) $proposta->toneladas) * 1000;
            $crt->valor_por_ton = $proposta->valor_por_ton;
            $crt->tipo_cobranca = 'POR_TONELADA';
            $crt->store();

            $proposta->status = 'Aprovada';
            $proposta->conhecimento_id = $crt->id;
            $proposta->store();

            TTransaction::close();
            TWindow::closeWindow('form_aprovar_proposta_tonelada');
            new TMessage('info', 'CRT gerado a partir da proposta com sucesso!', new TAction(['ConhecimentoForm', 'onEdit'], ['key' => $crt->id]));
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onAbrirCrt($param)
    {
        try {
            TTransaction::open('sample');
            $proposta = new PropostaTonelada($param['key']);
            TTransaction::close();

            if (empty($proposta->conhecimento_id)) {
                new TMessage('warning', 'Esta proposta ainda nao gerou CRT.');
                return;
            }

            TApplication::loadPage('ConhecimentoForm', 'onEdit', ['key' => $proposta->conhecimento_id]);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function closeGenerateCrtWindow()
    {
        TWindow::closeWindow('form_aprovar_proposta_tonelada');
    }

    public static function doDelete($param)
    {
        try {
            TTransaction::open('sample');
            $proposta = new PropostaTonelada($param['key']);
            $proposta->delete();
            TTransaction::close();
            new TMessage('info', 'Proposta excluida com sucesso!', new TAction([__CLASS__, 'onReload']));
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
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
