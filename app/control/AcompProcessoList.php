<?php

class AcompProcessoList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;

    use Adianti\Base\AdiantiStandardListTrait {
        onReload as traitOnReload;
    }

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('sample');
        $this->setActiveRecord('AcompProcesso');
        $this->setDefaultOrder('id', 'desc');
        $this->addFilterField('numero_processo', 'like', 'numero_processo');
        $this->addFilterField('exportador', 'like', 'exportador');
        $this->addFilterField('importador', 'like', 'importador');
        $this->addFilterField('crt', 'like', 'crt');

        $this->form = new BootstrapFormBuilder('form_search_acomp_processo');
        $this->form->setFormTitle('Acompanhamento Manual - Processos e Rastreio');

        $numero_processo = new TEntry('numero_processo');
        $exportador = new TEntry('exportador');
        $importador = new TEntry('importador');
        $crt = new TEntry('crt');

        $numero_processo->setSize('100%');
        $exportador->setSize('100%');
        $importador->setSize('100%');
        $crt->setSize('100%');

        $this->form->addFields([new TLabel('No processo')], [$numero_processo], [new TLabel('CRT')], [$crt]);
        $this->form->addFields([new TLabel('Exportador')], [$exportador], [new TLabel('Importador')], [$importador]);

        $this->form->setData(TSession::getValue($this->activeRecord . '_filter_data'));
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addActionLink('Novo processo', new TAction(['AcompProcessoForm', 'onEdit']), 'fa:plus green');

        $this->datagrid = new BootstrapDatagridWrapper(new TQuickGrid);
        $this->datagrid->style = 'width:100%';

        $this->datagrid->addQuickColumn('ID', 'id', 'center', '5%');
        $this->datagrid->addQuickColumn('No BR/AR', 'numero_processo', 'left', '14%');
        $this->datagrid->addQuickColumn('Exportador', 'exportador', 'left', '19%');
        $this->datagrid->addQuickColumn('Importador', 'importador', 'left', '19%');
        $this->datagrid->addQuickColumn('CRT', 'crt', 'left', '11%');

        $col_data = $this->datagrid->addQuickColumn('Data coleta', 'data_coleta', 'center', '10%');
        $col_data->setTransformer(function ($value) {
            if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy');
            }
            return $value ?: '-';
        });

        $col_etapa = $this->datagrid->addQuickColumn('Etapa', 'etapa', 'center', '12%');
        $col_etapa->setTransformer(function ($value) {
            $label = trim((string) $value);
            if ($label === '') {
                $label = 'COLETA';
            }

            $class = 'info';
            $v = strtolower($label);
            if (strpos($v, 'entrega') !== false || strpos($v, 'final') !== false) {
                $class = 'success';
            } elseif (strpos($v, 'aduana') !== false || strpos($v, 'transito') !== false) {
                $class = 'warning';
            }

            return '<span class="label label-' . $class . '">' . strtoupper(htmlspecialchars($label)) . '</span>';
        });

        $act_edit = new TDataGridAction(['AcompProcessoForm', 'onEdit']);
        $act_view = new TDataGridAction(['AcompProcessoView', 'onShow']);

        $act_evt = new TDataGridAction(['AcompEventoList', 'onReload']);
        $act_evt->setParameter('processo_id', '{id}');

        $act_track = new TDataGridAction(['AcompEventoList', 'onReload']);
        $act_track->setParameter('processo_id', '{id}');

        $this->datagrid->addQuickAction('Editar', $act_edit, 'id', 'fa:edit blue');
        $this->datagrid->addQuickAction('Visualizar', $act_view, 'id', 'fa:eye');
        $this->datagrid->addQuickAction('Status', $act_evt, 'id', 'fa:list-ol orange');
        $this->datagrid->addQuickAction('Rastreio', $act_track, 'id', 'fa:crosshairs green');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $panel = new TPanelGroup;
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', get_class($this)));
        $box->add($this->form);
        $box->add($panel);

        parent::add($box);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            AcompProcesso::ensureTables();
            TTransaction::close();
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $ee) {
            }
        }

        $this->traitOnReload($param);
    }
}
