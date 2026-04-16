<?php

class CidadeUfList extends TStandardList
{
    protected $form;
    protected $datagrid;
    protected $pageNavigation;
    protected $formgrid;
    protected $deleteButton;
    protected $transformCallback;

    public function __construct()
    {
        parent::__construct();

        parent::setDatabase('default');
        parent::setActiveRecord('CidadeUf');
        parent::setDefaultOrder('uf', 'asc');
        parent::addFilterField('nome', 'like', 'nome');
        parent::addFilterField('uf',   'like', 'uf');
        parent::setLimit(TSession::getValue(__CLASS__ . '_limit') ?? 20);
        parent::setAfterSearchCallback([$this, 'onAfterSearch']);

        // Formulário de busca
        $this->form = new BootstrapFormBuilder('form_search_CidadeUf');
        $this->form->setFormTitle('Cidades');

        $nome = new TEntry('nome');
        $uf   = new TEntry('uf');

        $nome->setSize('100%');
        $uf->setSize('100%');
        $uf->setTip('Ex: RS, SP, ARGENTINA...');

        $this->form->addFields([new TLabel('Cidade')], [$nome]);
        $this->form->addFields([new TLabel('UF / País')], [$uf]);

        $this->form->setData(TSession::getValue('CidadeUf_filter_data'));

        $btn = $this->form->addAction(_t('Find'), new TAction([$this, 'onSearch']), 'fa:search');
        $btn->class = 'btn btn-sm btn-primary';

        // DataGrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->setHeight(320);

        $col_id   = new TDataGridColumn('id',   'ID',          'center', 60);
        $col_nome = new TDataGridColumn('nome',  'Cidade',      'left');
        $col_uf   = new TDataGridColumn('uf',    'UF / País',   'center', 140);

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_nome);
        $this->datagrid->addColumn($col_uf);

        // Ordenação
        $col_id->setAction(new TAction([$this, 'onReload'], ['order' => 'id']));
        $col_nome->setAction(new TAction([$this, 'onReload'], ['order' => 'nome']));
        $col_uf->setAction(new TAction([$this, 'onReload'], ['order' => 'uf']));

        // Ações
        $action_edit = new TDataGridAction(['CidadeUfForm', 'onEdit'], ['register_state' => 'false']);
        $action_edit->setButtonClass('btn btn-default');
        $action_edit->setLabel(_t('Edit'));
        $action_edit->setImage('far:edit blue');
        $action_edit->setField('id');
        $this->datagrid->addAction($action_edit);

        $action_del = new TDataGridAction([$this, 'onDelete']);
        $action_del->setButtonClass('btn btn-default');
        $action_del->setLabel(_t('Delete'));
        $action_del->setImage('far:trash-alt red');
        $action_del->setField('id');
        $this->datagrid->addAction($action_del);

        $this->datagrid->createModel();

        // Paginação
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->enableCounters();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth($this->datagrid->getWidth());

        $panel = new TPanelGroup;
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        // Busca rápida no header
        $btnf = TButton::create('find', [$this, 'onSearch'], '', 'fa:search');
        $btnf->style = 'height: 37px; margin-right:4px;';

        $form_search = new TForm('form_search_nome');
        $form_search->style = 'float:left;display:flex';
        $form_search->add($nome, true);
        $form_search->add($btnf, true);

        $panel->addHeaderWidget($form_search);
        $panel->addHeaderActionLink('', new TAction(['CidadeUfForm', 'onEdit'], ['register_state' => 'false']), 'fa:plus');
        $this->filter_label = $panel->addHeaderActionLink(_t('Filters'), new TAction([$this, 'onShowCurtainFilters']), 'fa:filter');

        // Export
        $dropdown = new TDropDown(_t('Export'), 'fa:list');
        $dropdown->style = 'height:37px';
        $dropdown->setPullSide('right');
        $dropdown->setButtonClass('btn btn-default waves-effect dropdown-toggle');
        $dropdown->addAction(_t('Save as CSV'), new TAction([$this, 'onExportCSV'], ['register_state' => 'false', 'static' => '1']), 'fa:table fa-fw blue');
        $dropdown->addAction(_t('Save as PDF'), new TAction([$this, 'onExportPDF'], ['register_state' => 'false', 'static' => '1']), 'far:file-pdf fa-fw red');
        $panel->addHeaderWidget($dropdown);

        // Limite por página
        $dropdown_limit = new TDropDown(TSession::getValue(__CLASS__ . '_limit') ?? '20', '');
        $dropdown_limit->style = 'height:37px';
        $dropdown_limit->setPullSide('right');
        $dropdown_limit->setButtonClass('btn btn-default waves-effect dropdown-toggle');
        $dropdown_limit->addAction(20,  new TAction([$this, 'onChangeLimit'], ['register_state' => 'false', 'static' => '1', 'limit' => '20']));
        $dropdown_limit->addAction(50,  new TAction([$this, 'onChangeLimit'], ['register_state' => 'false', 'static' => '1', 'limit' => '50']));
        $dropdown_limit->addAction(100, new TAction([$this, 'onChangeLimit'], ['register_state' => 'false', 'static' => '1', 'limit' => '100']));
        $dropdown_limit->addAction(500, new TAction([$this, 'onChangeLimit'], ['register_state' => 'false', 'static' => '1', 'limit' => '500']));
        $panel->addHeaderWidget($dropdown_limit);

        if (TSession::getValue(get_class($this) . '_filter_counter') > 0)
        {
            $this->filter_label->class = 'btn btn-primary';
            $this->filter_label->setLabel(_t('Filters') . ' (' . TSession::getValue(get_class($this) . '_filter_counter') . ')');
        }

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($panel);

        parent::add($container);
    }

    public function onAfterSearch($datagrid, $options)
    {
        if (TSession::getValue(get_class($this) . '_filter_counter') > 0)
        {
            $this->filter_label->class = 'btn btn-primary';
            $this->filter_label->setLabel(_t('Filters') . ' (' . TSession::getValue(get_class($this) . '_filter_counter') . ')');
        }
        else
        {
            $this->filter_label->class = 'btn btn-default';
            $this->filter_label->setLabel(_t('Filters'));
        }

        if (!empty(TSession::getValue(get_class($this) . '_filter_data')))
        {
            $obj = new stdClass;
            $obj->nome = TSession::getValue(get_class($this) . '_filter_data')->nome ?? '';
            TForm::sendData('form_search_nome', $obj);
        }
    }

    public static function onChangeLimit($param)
    {
        TSession::setValue(__CLASS__ . '_limit', $param['limit']);
        AdiantiCoreApplication::loadPage(__CLASS__, 'onReload');
    }

    public static function onShowCurtainFilters($param = null)
    {
        try
        {
            $page = new TPage;
            $page->setTargetContainer('adianti_right_panel');
            $page->setProperty('override', 'true');
            $page->setPageName(__CLASS__);

            $btn_close = new TButton('closeCurtain');
            $btn_close->onClick = "Template.closeRightPanel();";
            $btn_close->setLabel(_t('Close'));
            $btn_close->setImage('fas:times red');

            $embed = new self;
            $embed->form->addHeaderWidget($btn_close);

            $page->add($embed->form);
            $page->setIsWrapped(true);
            $page->show();
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }
    }
}
