<?php

class AcompEventoList extends TPage
{
    private $datagrid;
    private $pageNavigation;
    private $processo_id;

    public function __construct($param = null)
    {
        parent::__construct();

        $this->processo_id = $param['processo_id'] ?? null;

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';

        $col_data = new TDataGridColumn('data_evento', 'Data', 'left', '18%');
        $col_demora = new TDataGridColumn('demora', 'Demora', 'left', '10%');
        $col_status = new TDataGridColumn('status_texto', 'Status', 'left', '52%');
        $col_franquia = new TDataGridColumn('franquia', 'Franquias', 'left', '15%');

        $col_data->setTransformer(function ($value) {
            $ts = strtotime((string) $value);
            return $ts ? date('d/m/Y H:i', $ts) : $value;
        });

        $this->datagrid->addColumn($col_data);
        $this->datagrid->addColumn($col_demora);
        $this->datagrid->addColumn($col_status);
        $this->datagrid->addColumn($col_franquia);

        $act_edit = new TDataGridAction(['AcompEventoForm', 'onEdit']);
        $act_edit->setField('id');
        $act_edit->setParameter('processo_id', '{processo_id}');
        $this->datagrid->addAction($act_edit, 'Editar', 'fa:edit blue');

        $act_del = new TDataGridAction([$this, 'onDelete']);
        $act_del->setField('id');
        $act_del->setParameter('processo_id', '{processo_id}');
        $this->datagrid->addAction($act_del, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $panel = new TPanelGroup('Status do processo');
        $panel->addHeaderActionLink('Novo status', new TAction(['AcompEventoForm', 'onEdit'], ['processo_id' => $this->processo_id]), 'fa:plus green');
        $panel->addHeaderActionLink('Visualizar processo', new TAction(['AcompProcessoView', 'onShow'], ['key' => $this->processo_id]), 'fa:eye blue');
        $panel->addHeaderActionLink('Voltar processos', new TAction(['AcompProcessoKanban', 'onReload']), 'fa:list');

        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', 'AcompProcessoKanban'));
        $box->add($panel);

        parent::add($box);
    }

    public function onReload($param = null)
    {
        try {
            if (!empty($param['processo_id'])) {
                $this->processo_id = $param['processo_id'];
            }

            if (empty($this->processo_id)) {
                throw new Exception('Processo nao informado para listar status.');
            }

            TTransaction::open('sample');
            AcompProcesso::ensureTables();

            $repo = new TRepository('AcompEvento');
            $criteria = new TCriteria;
            $criteria->add(new TFilter('processo_id', '=', $this->processo_id));
            $criteria->setProperty('order', 'data_evento');
            $criteria->setProperty('direction', 'asc');

            $limit = 30;
            $criteria->setProperties($param);
            $criteria->setProperty('limit', $limit);

            $this->datagrid->clear();
            $objs = $repo->load($criteria, false);
            if ($objs) {
                foreach ($objs as $obj) {
                    $this->datagrid->addItem($obj);
                }
            }

            $criteria->resetProperties();
            $total = $repo->count($criteria);
            $this->pageNavigation->setCount($total);
            $this->pageNavigation->setProperties(array_merge((array) $param, ['processo_id' => $this->processo_id]));
            $this->pageNavigation->setLimit($limit);

            TTransaction::close();
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $ee) {
            }
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onDelete($param)
    {
        $action = new TAction([__CLASS__, 'delete']);
        $action->setParameters($param);
        new TQuestion('Deseja excluir este status?', $action);
    }

    public static function delete($param)
    {
        try {
            TTransaction::open('sample');
            AcompProcesso::ensureTables();

            $obj = new AcompEvento($param['key']);
            $processo_id = $obj->processo_id;
            $obj->delete();

            TTransaction::close();
            new TMessage('info', 'Status excluido com sucesso.', new TAction([__CLASS__, 'onReload'], ['processo_id' => $processo_id]));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }
    public function onShow($param = null)
    {
        $this->onReload($param);
    }
}



