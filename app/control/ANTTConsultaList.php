<?php

class ANTTConsultaList extends TPage
{
    private $form;      // formulário de busca
    private $datagrid;  // listagem de resultados

    public function __construct()
    {
        parent::__construct();
        
        // ** Formulário de filtro **
        $this->form = new BootstrapFormBuilder('form_ANTTConsulta');
        $this->form->setFormTitle('Consulta realizadas ANTT');
        $this->form->setFieldSizes('100%');
        
        // Campo Placa
        $lblPlaca = new TLabel('Placa:');
        $entryPlaca = new TEntry('placa');
        $entryPlaca->setSize('100%');
        
        // Adiciona campos ao formulário
        $this->form->addFields([$lblPlaca, $entryPlaca]);
        
        // Botão Pesquisar
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search');
        // Botão Limpar (reseta o filtro)
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser');
        
        // ** Datagrid de resultados **
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->setHeight(320);
        
        // Colunas da lista
        $col_id            = new TDataGridColumn('id',               'ID',               'right',   '4%');
        $col_placa         = new TDataGridColumn('placa',            'Placa',            'left',   '10%');
        $col_tipo          = new TDataGridColumn('tipo',             'Tipo',             'left',   '10%');
        $col_marca         = new TDataGridColumn('marca',            'Marca',            'left',   '10%');
     //   $col_carroceria    = new TDataGridColumn('carroceria',       'Carroceria',       'left',   '10%');
        $col_eixos         = new TDataGridColumn('eixos',            'Eixos',            'left',    '5%');
        $col_chassi_motor  = new TDataGridColumn('chassi_motor',     'Chassi/Motor',     'left',   '15%');
        $col_ano           = new TDataGridColumn('ano',              'Ano',              'center',  '5%');
     //   $col_ccu           = new TDataGridColumn('ccu',              'CCU',              'left',    '8%');
        $col_cnpj          = new TDataGridColumn('cnpj',             'CNPJ',             'left',   '15%');
        $col_razao_social  = new TDataGridColumn('razao_social',     'Razão Social',     'left',   '15%');
      //  $col_nome_fantasia = new TDataGridColumn('nome_fantasia',    'Nome Fantasia',    'left',   '15%');
      //  $col_endereco      = new TDataGridColumn('endereco',         'Endereço',         'left',   '20%');
      //  $col_bairro        = new TDataGridColumn('bairro',           'Bairro',           'left',   '10%');
      //  $col_cidade        = new TDataGridColumn('cidade',           'Cidade',           'left',   '10%');
        $col_pais_origem   = new TDataGridColumn('pais_origem',      'País Origem',      'left',   '10%');
       // $col_situacao      = new TDataGridColumn('situacao_licencas','Situação',         'left',   '10%');
        $col_data_consulta = new TDataGridColumn('data_consulta',    'Data Consulta',    'center', '10%');
        
        // Adiciona colunas no datagrid
        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_placa);
        $this->datagrid->addColumn($col_tipo);
        $this->datagrid->addColumn($col_marca);
     //   $this->datagrid->addColumn($col_carroceria);
        $this->datagrid->addColumn($col_eixos);
        $this->datagrid->addColumn($col_chassi_motor);
        $this->datagrid->addColumn($col_ano);
       // $this->datagrid->addColumn($col_ccu);
        $this->datagrid->addColumn($col_cnpj);
        $this->datagrid->addColumn($col_razao_social);
      //  $this->datagrid->addColumn($col_nome_fantasia);
      //  $this->datagrid->addColumn($col_endereco);
      //  $this->datagrid->addColumn($col_bairro);
       // $this->datagrid->addColumn($col_cidade);
       // $this->datagrid->addColumn($col_pais_origem);
      //  $this->datagrid->addColumn($col_situacao);
        $this->datagrid->addColumn($col_data_consulta);
        
        // Ações de editar e excluir (usando TDataGridAction)
        $action_edit = new TDataGridAction([$this, 'onEdit'],   ['id'=>'{id}']);
        $action_del  = new TDataGridAction([$this, 'onDelete'], ['id'=>'{id}']);
        $this->datagrid->addAction($action_edit, 'Editar',  'far:edit blue fa-fw');
        $this->datagrid->addAction($action_del,  'Excluir', 'far:trash-alt red fa-fw');
        
        // Cria modelo (necessário após definir colunas e ações):contentReference[oaicite:3]{index=3}
        $this->datagrid->createModel();
        
        // Layout: form acima, lista abaixo
        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($this->form);
        $vbox->add($this->datagrid);
        parent::add($vbox);
        
        // Carrega dados iniciais
        $this->onReload();
    }
    
    /**
     * Executado ao clicar em 'Buscar' - aplica filtro por placa
     */
    public function onSearch($param)
    {
        $data = $this->form->getData(); // obtém dados do formulário
        if (!empty($data->placa)) {
            // Define critério de filtro para 'placa' (LIKE)
            $criteria = new TCriteria;
            $criteria->add(new TFilter('placa', 'like', "%{$data->placa}%"));
            TSession::setValue('ANTTConsulta_filter', $criteria);
        } else {
            TSession::setValue('ANTTConsulta_filter', null);
        }
        // Mantém o form preenchido
        $this->form->setData($data);
        // Recarrega datagrid (offset 0 e página 1, sem paginação implementada aqui)
        $this->onReload(['offset'=>0, 'first_page'=>1]);
    }
    
    /**
     * Limpa o filtro e recarrega lista completa
     */
    public function onClear($param)
    {
        $this->form->clear();
        TSession::setValue('ANTTConsulta_filter', null);
        $this->onReload();
    }
    
    /**
     * Carrega os dados no datagrid (executado ao iniciar e após filtros)
     */
    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            $repository = new TRepository('AnttConsulta');
            $criteria = TSession::getValue('ANTTConsulta_filter') ?: new TCriteria;
            $objects = $repository->load($criteria);
            
            $this->datagrid->clear();
            if ($objects) {
                foreach ($objects as $object) {
                    $this->datagrid->addItem($object);
                }
            }
            TTransaction::close();
        }
        catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }
    
    /**
     * Abre janela para editar o registro (em modal):contentReference[oaicite:4]{index=4}
     */
    public function onEdit($param)
    {
        try {
            TTransaction::open('sample');
            $object = new AnttConsulta($param['id']);
            TTransaction::close();
            
            // Cria formulário de edição
            $form = new BootstrapFormBuilder('form_edit_ANTTConsulta');
            $form->setFormTitle('Editar Consulta ANTT');
            $form->setFieldSizes('100%');
            
            // Campos (inclui todos para edição)
            $id = new TEntry('id');     $id->setEditable(FALSE);
            $placa = new TEntry('placa');
            $tipo  = new TEntry('tipo');
            $marca = new TEntry('marca');
            $carroceria = new TEntry('carroceria');
            $eixos = new TEntry('eixos');
            $chassi_motor = new TEntry('chassi_motor');
            $ano = new TEntry('ano');
            $ccu = new TEntry('ccu');
            $cnpj = new TEntry('cnpj');
            $razao_social = new TEntry('razao_social');
            $nome_fantasia = new TEntry('nome_fantasia');
            $endereco = new TEntry('endereco');
            $bairro = new TEntry('bairro');
            $cidade = new TEntry('cidade');
            $pais_origem = new TEntry('pais_origem');
            $situacao = new TEntry('situacao_licencas');
            $data_consulta = new TEntry('data_consulta');
            
            // Adiciona campos ao formulário
            $form->addFields([new TLabel('ID:'), $id]);
            $form->addFields([new TLabel('Placa:'), $placa]);
            $form->addFields([new TLabel('Tipo:'), $tipo]);
            $form->addFields([new TLabel('Marca:'), $marca]);
            $form->addFields([new TLabel('Carroceria:'), $carroceria]);
            $form->addFields([new TLabel('Eixos:'), $eixos]);
            $form->addFields([new TLabel('Chassi/Motor:'), $chassi_motor]);
            $form->addFields([new TLabel('Ano:'), $ano]);
            $form->addFields([new TLabel('CCU:'), $ccu]);
            $form->addFields([new TLabel('CNPJ:'), $cnpj]);
            $form->addFields([new TLabel('Razão Social:'), $razao_social]);
            $form->addFields([new TLabel('Nome Fantasia:'), $nome_fantasia]);
            $form->addFields([new TLabel('Endereço:'), $endereco]);
            $form->addFields([new TLabel('Bairro:'), $bairro]);
            $form->addFields([new TLabel('Cidade:'), $cidade]);
            $form->addFields([new TLabel('País Origem:'), $pais_origem]);
            $form->addFields([new TLabel('Situação:'), $situacao]);
            $form->addFields([new TLabel('Data Consulta:'), $data_consulta]);
            
            // Botão Salvar
            $form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save blue');
            
            // Preenche o formulário com os dados do objeto
            $form->setData($object);
            
            // Abre janela modal com o formulário de edição
            $window = TWindow::create('Editar', 0.6, 0.7);
            $window->add($form);
            $window->show();
        }
        catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }
    
    /**
     * Salva os dados editados no banco
     */
    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            $object = new AnttConsulta($param['id']);  // carrega o registro existente
            $object->placa             = $param['placa'];
            $object->tipo              = $param['tipo'];
            $object->marca             = $param['marca'];
            $object->carroceria        = $param['carroceria'];
            $object->eixos             = $param['eixos'];
            $object->chassi_motor      = $param['chassi_motor'];
            $object->ano               = $param['ano'];
            $object->ccu               = $param['ccu'];
            $object->cnpj              = $param['cnpj'];
            $object->razao_social      = $param['razao_social'];
            $object->nome_fantasia     = $param['nome_fantasia'];
            $object->endereco          = $param['endereco'];
            $object->bairro            = $param['bairro'];
            $object->cidade            = $param['cidade'];
            $object->pais_origem       = $param['pais_origem'];
            $object->situacao_licencas = $param['situacao_licencas'];
            $object->data_consulta     = $param['data_consulta'];
            $object->store();
            TTransaction::close();
            
            new TMessage('info', 'Registro salvo com sucesso');
            $this->onReload(); // atualiza a listagem
        }
        catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }
    
    /**
     * Exclui registro selecionado
     */
    public function onDelete($param)
    {
        try {
            TTransaction::open('sample');
            $object = new AnttConsulta($param['id']);
            $object->delete();
            TTransaction::close();
            
            new TMessage('info', 'Registro excluído com sucesso');
            $this->onReload();
        }
        catch (Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }
    
    /**
     * Garante que onReload seja chamado antes de mostrar a página
     */
    public function show()
    {
        $this->onReload();
        parent::show();
    }
}
?>
