<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Widget\Wrapper\TQuickGrid;

class ContratoList extends TPage
{
    protected $form;
    protected $datagrid;
    protected $pageNavigation;
    protected $loaded;

    use \Adianti\Base\AdiantiStandardListTrait;

    public function __construct()
    {
        parent::__construct();
        $this->setDatabase('sample');
        $this->setActiveRecord('Contrato');
        $this->setDefaultOrder('id', 'desc');
        $this->addFilterField('permisso_id', '=', 'permisso_id');
        $this->addFilterField('emissao', '>=', 'emissao_de', function($value) { return TDate::convertToMask($value, 'dd/mm/yyyy', 'yyyy-mm-dd'); });
        $this->addFilterField('emissao', '<=', 'emissao_ate', function($value) { return TDate::convertToMask($value, 'dd/mm/yyyy', 'yyyy-mm-dd'); });
        
        $this->form = new BootstrapFormBuilder('form_search_contrato');
        $this->form->setFormTitle('Listagem de Contratos');
        $permisso_id = new TDBUniqueSearch('permisso_id', 'sample', 'Permisso', 'id', 'transportadora');
        $emissao_de = new TDate('emissao_de');
        $emissao_ate = new TDate('emissao_ate');
        $emissao_de->setMask('dd/mm/yyyy');
        $emissao_ate->setMask('dd/mm/yyyy');
        $this->form->addFields([new TLabel('Contratante')], [$permisso_id]);
        $this->form->addFields([new TLabel('Emissão de')], [$emissao_de], [new TLabel('até')], [$emissao_ate]);
        $this->form->setData(TSession::getValue($this->activeRecord.'_filter_data'));
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addActionLink('Novo', new TAction(['ContratoForm', 'onClear']), 'fa:plus green');

        $this->datagrid = new BootstrapDatagridWrapper(new TQuickGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->addQuickColumn('Nº', 'id', 'center');
        $this->datagrid->addQuickColumn('Contratante', '{permisso->transportadora}', 'left');
        $this->datagrid->addQuickColumn('Veículo', '{veiculo->placa_trator}', 'left');
        $this->datagrid->addQuickColumn('Motorista', '{veiculo->motorista->nome}', 'left');
        $col_emissao = $this->datagrid->addQuickColumn('Emissão', 'emissao', 'center');
        $col_frete = $this->datagrid->addQuickColumn('Frete', 'frete1', 'right');
        $col_pago = $this->datagrid->addQuickColumn('pago', 'Pago', 'center');
        $col_emissao->setTransformer(function($value){ return TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy'); });
        $col_frete->setTransformer(function($value){ return is_numeric($value) ? 'R$ ' . number_format((float) $value, 2, ',', '.') : $value; });
        $col_pago->setTransformer(function($value){ return $value == 'S' ? '<span class="label label-success">Sim</span>' : '<span class="label label-danger">Não</span>'; });
        
        $action_edit = new TDataGridAction(['ContratoForm', 'onEdit']);
        $action_del = new TDataGridAction([$this, 'onDelete']);
        $action_pdf = new TDataGridAction([$this, 'onGenerateReport']);
        
        $this->datagrid->addQuickAction('Editar', $action_edit, 'id', 'fa:edit blue');
        $this->datagrid->addQuickAction('Excluir', $action_del, 'id', 'fa:trash red');
        $this->datagrid->addQuickAction('Imprimir', $action_pdf, 'id', 'fa:file-pdf');
        
        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $panel = new TPanelGroup;
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($panel);
        parent::add($container);
    }
    
    public function onGenerateReport($param)
    {
        ContratoRelatorio::onGenerate($param);
    }
}