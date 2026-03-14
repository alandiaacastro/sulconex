<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Widget\Wrapper\TQuickGrid;

class VeiculoList extends TPage
{
    protected $form;
    protected $datagrid;
    protected $pageNavigation;

    use \Adianti\Base\AdiantiStandardListTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('sample');
        $this->setActiveRecord('Veiculo');
        $this->setDefaultOrder('id', 'desc');
        
        // Filtros
        $this->addFilterField('motorista_id', '=', 'motorista_id');
        $this->addFilterField('antt_consulta_trator_id', '=', 'antt_consulta_trator_id');
        $this->addFilterField('antt_consulta_semi_reboque_id', '=', 'antt_consulta_semi_reboque_id');
        $this->addFilterField('(SELECT ac.razao_social FROM antt_consulta ac WHERE ac.id = antt_consulta_trator_id)', 'like', 'proprietario');

        // Formulário de busca
        $this->form = new BootstrapFormBuilder('form_search_veiculo');
        $this->form->setFormTitle('Listagem de Veículos');

        $motorista_id = new TDBUniqueSearch('motorista_id', 'sample', 'Motorista', 'id', 'nome');
        $motorista_id->setMinLength(1);
        $motorista_id->setMask('<b>Nome:</b> {nome} | <b>CPF:</b> {cpf}');

        $antt_consulta_trator_id = new TDBUniqueSearch('antt_consulta_trator_id', 'sample', 'AnttConsulta', 'id', 'placa');
        $antt_consulta_trator_id->setMinLength(1);
        $antt_consulta_trator_id->setMask('Placa: {placa} | Modelo: {marca} | Proprietario: {razao_social}');

        $antt_consulta_semi_reboque_id = new TDBUniqueSearch('antt_consulta_semi_reboque_id', 'sample', 'AnttConsulta', 'id', 'placa');
        $antt_consulta_semi_reboque_id->setMinLength(1);
        $antt_consulta_semi_reboque_id->setMask('Placa: {placa} | Modelo: {marca} | Proprietario: {razao_social}');

        $proprietario = new TEntry('proprietario');
        $proprietario->setSize('100%');
        
        $this->form->addFields([new TLabel('Motorista')], [$motorista_id], [new TLabel('Proprietario')], [$proprietario]);
        $this->form->addFields([new TLabel('Trator')], [$antt_consulta_trator_id], [new TLabel('Semi-Reboque')], [$antt_consulta_semi_reboque_id]);
        $this->form->setData( TSession::getValue($this->activeRecord.'_filter_data') );

        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addActionLink('Novo', new TAction(['VeiculoForm', 'onEdit']), 'fa:plus green');

        // DataGrid
        $this->datagrid = new BootstrapDatagridWrapper(new TQuickGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->setHeight(320);

        // --- INÃCIO DA ALTERAÃ‡ÃƒO DAS COLUNAS ---
        
        // Adiciona as colunas solicitadas
        $this->datagrid->addQuickColumn('Placa Trator', '{antt_consulta_trator->placa}', 'left', '10%');
        $this->datagrid->addQuickColumn('Placa Carreta', '{antt_consulta_semi_reboque->placa}', 'left', '10%');
        $this->datagrid->addQuickColumn('Motorista', '{motorista->nome}', 'left');
        $this->datagrid->addQuickColumn('Proprietário', '{proprietario->razao_social}', 'left');

        // --- FIM DA ALTERAÃ‡ÃƒO DAS COLUNAS ---

        // Ações da datagrid
        $action_edit = new TDataGridAction(['VeiculoForm', 'onEdit']);
        $action_del = new TDataGridAction([$this, 'onDelete']);
        
        $this->datagrid->addQuickAction('Editar', $action_edit, 'id', 'fa:edit blue');
        $this->datagrid->addQuickAction('Excluir', $action_del, 'id', 'fa:trash red');

        $this->datagrid->createModel();

        // Paginação
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
}
