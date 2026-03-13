<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

class TabelaFreteList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;

    use Adianti\Base\AdiantiStandardListTrait {
        onReload as traitOnReload;
    }

    public function __construct($param = null)
    {
        parent::__construct();

        $this->setDatabase('sample');
        $this->setActiveRecord('TabelaFrete');
        $this->setDefaultOrder('origem', 'asc');
        $this->addFilterField('origem', 'like', 'origem');
        $this->addFilterField('destino', 'like', 'destino');
        $this->addFilterField('tipo_veiculo', 'like', 'tipo_veiculo');

        $this->form = new BootstrapFormBuilder('form_search_tabela_frete');
        $this->form->setFormTitle('Tabela de Fretes');

        $origem = new TEntry('origem');
        $destino = new TEntry('destino');
        $tipo_veiculo = new TEntry('tipo_veiculo');

        $origem->setSize('100%');
        $destino->setSize('100%');
        $tipo_veiculo->setSize('100%');

        $this->form->addFields([new TLabel('Origem')], [$origem], [new TLabel('Destino')], [$destino], [new TLabel('Tipo Veiculo')], [$tipo_veiculo]);

        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addActionLink('Novo', new TAction(['TabelaFreteForm', 'onEdit']), 'fa:plus green');

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';

        $col_id = new TDataGridColumn('id', 'ID', 'right', '70');
        $col_origem = new TDataGridColumn('origem', 'Origem', 'left');
        $col_destino = new TDataGridColumn('destino', 'Destino', 'left');
        $col_tipo_veiculo = new TDataGridColumn('tipo_veiculo', 'Tipo Veiculo', 'left', '180');
        $col_valor = new TDataGridColumn('valor_frete', 'Valor Frete', 'right', '160');
        $col_atualizacao = new TDataGridColumn('updated_at', 'Data Atualizacao Frete', 'center', '180');

        $col_valor->setTransformer(function ($value) {
            return 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
        });

        $col_atualizacao->setTransformer(function ($value, $object) {
            $raw = (string) ($value ?: ($object->created_at ?? ''));
            if ($raw === '') {
                return '';
            }

            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $raw);
            if (!$dt) {
                $dt = DateTime::createFromFormat('Y-m-d', $raw);
            }

            return $dt ? $dt->format('d/m/Y H:i') : $raw;
        });

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_origem);
        $this->datagrid->addColumn($col_destino);
        $this->datagrid->addColumn($col_tipo_veiculo);
        $this->datagrid->addColumn($col_valor);
        $this->datagrid->addColumn($col_atualizacao);

        $act_edit = new TDataGridAction(['TabelaFreteForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($act_edit, 'Editar', 'fa:edit blue');

        $act_del = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($act_del, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        $panel = new TPanelGroup;
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add($this->form);
        $box->add($panel);

        parent::add($box);
    }

    public function onReload($param = null)
    {
        $this->traitOnReload($param);
    }
}
