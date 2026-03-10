<?php
use Adianti\Base\AdiantiStandardListTrait;
use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Control\TWindow;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TFilter;
use Adianti\Widget\Dialog\TMessage;

class ConhecimentoSeekWindow extends TPage
{
    use AdiantiStandardListTrait;

    protected $form;
    protected $datagrid;
    protected $pageNavigation;
    
    public function __construct()
    {
        parent::__construct();
        
        // --- ETAPA IMPORTANTE ---
        // Substitua 'communication' pelo nome do seu arquivo .ini da pasta app/config/
        // Ex: se o arquivo for 'banco.ini', use $this->setDatabase('banco');
        $this->setDatabase('communication');
        
        $this->setActiveRecord('Conhecimento');
        $this->setDefaultOrder('id', 'asc');
        $this->setLimit(10);
        $this->setCriteriaName('conhecimento_seek');

        $this->addFilterField('numero_crt', 'like', 'numero_crt_filter');

        $this->form = new BootstrapFormBuilder('form_search_conhecimento_seek');
        $this->form->setFormTitle('Buscar Conhecimento');

        $numero_crt_filter = new TEntry('numero_crt_filter');
        $this->form->addFields([new TLabel('Nº CRT:')], [$numero_crt_filter]);
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search');

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';

        $this->datagrid->addColumn( new TDataGridColumn('id', 'ID', 'center', '10%') );
        $this->datagrid->addColumn( new TDataGridColumn('numero_crt', 'Nº CRT', 'left', '40%') );
        $this->datagrid->addColumn( new TDataGridColumn('{cliente->nome}', 'Cliente', 'left', '50%') );

        $action = new TDataGridAction([$this, 'onSelect']);
        $action->setField('id');
        $this->datagrid->addAction($action, 'Selecionar', 'fa:check-circle-o green');
        
        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        
        $panel = new TPanelGroup();
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($panel);

        parent::add($container);
    }
    
    public static function onSelect($param)
    {
        try
        {
            $database = 'communication'; // Certifique-se que o nome do banco está correto aqui também
            if (isset($param['key']))
            {
                $key = $param['key'];
                TTransaction::open($database);

                $conhecimento = new Conhecimento($key);
                
                if ($conhecimento && $conhecimento->cliente)
                {
                    $cliente = $conhecimento->cliente;
                    
                    $data = new stdClass;
                    $data->conhecimento_id = $conhecimento->id;
                    $data->numero_crt = $conhecimento->numero_crt;
                    $data->pessoa_id = $cliente->id;
                    $data->fatura_cliente = $cliente->nome;
                    $data->cnpj = $cliente->cnpj;
                    $data->inscricaoestadual = $cliente->inscricao_estadual;
                    $data->endereco = $cliente->endereco;
                    $data->numero = $cliente->numero;
                    $data->cep = $cliente->cep;
                    $data->bairro = $cliente->bairro;
                    
                    if ($cliente->cidade)
                    {
                       $data->cidade = $cliente->cidade->nome;
                       if ($cliente->cidade->estado)
                       {
                           $data->uf = $cliente->cidade->estado->sigla;
                       }
                    }
                    
                    TForm::sendData('form_FaturaForm', $data, true);
                    TWindow::closeWindow();
                }
                else
                {
                    new TMessage('error', 'Cliente não encontrado para este conhecimento.');
                }
                
                TTransaction::close();
            }
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}