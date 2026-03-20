<?php

use Adianti\Widget\Form\TEntry;

class ConhecimentoForm extends TPage
{
    protected $form;
    private $isReadOnly = false;

    public function __construct($param)
    {
        parent::__construct();

        // Criacao do formulario com nome 'form_Conhecimento'
        $this->form = new BootstrapFormBuilder('form_Conhecimento');
        $this->form->setFormTitle('Conhecimento de Transporte');
        $this->form->setFieldSizes('100%');
        //  Campo oculto ID
        $id = new THidden('id');
        $this->form->addFields([$id]);
        // 1 INFORMAES GERAIS
                $panel_geral = new TPanelGroup('Informacoes Gerais');
        // Criao dos campos
        $permisso = new TEntry('permisso');
        $numero                                  = new TEntry('numero');
        $data_transportador_assinatura = new TDate('data_transportador_assinatura');
        $data_transportador_assinatura->setMask('dd/mm/yyyy');
        $fatura_crt                              = new TEntry('fatura_crt');
        //  $pais_destino                              = new TEntry('pais_destino');
        $status_crt_id                           = new TDBCombo('status_crt_id', 'sample', 'StatusCRT', 'id', 'nome');
        $copiacrt                                = new TCheckButton('copiacrt');
        $copiacrt->setIndexValue('1');
        $copiacrt->setUseSwitch(true, 'blue');
        //   $nome_transportador                        = new TText('nome_transportador');
        ///   $nome_transportador->setSize('60%', 120);
        // Alterado para campo oculto
        $logotransporte = new THidden('logotransporte');
        $table = new TTable;
        $table->width = '100%';
        $row = $table->addRow();
        // Vinao duplica a visualizacao)
        $this->form->addFields([new TLabel('Copiar CRT')], [$copiacrt]);
        // Coluna 1: Permissao
        $cell = $row->addCell('');
        $cell->add(new TLabel('Permissao'));
        $cell->add($permisso);
        // Coluna 2: Pas Destino
        // $cell = $row->addCell('');
        //  $cell->add(new TLabel(' 2-Pas Destino'));
        // $cell->add($pais_destino);
        // Coluna 3: Numero CRT
        $cell = $row->addCell('');
        $cell->add(new TLabel('Numero CRT'));
        $cell->add($numero);
        // Coluna 4: Data CRT
        $cell = $row->addCell('');
        $cell->add(new TLabel('Data CRT'));
        $cell->add($data_transportador_assinatura);
        // Coluna 5: Fatura
        $cell = $row->addCell('');
        $cell->add(new TLabel('Fatura'));
        $cell->add($fatura_crt);
        // Coluna 6: Situacao
        $cell = $row->addCell('');
        $cell->add(new TLabel('Situacao'));
        $cell->add($status_crt_id);
        // Linha do Transportador e Logo (campo oculto)
        $row = $table->addRow();
                
        $panel_geral->add($table);
        $this->form->addContent([$panel_geral]);
        //  REMETENTE/DESTINATRIO
                $panel_remetente_destinatario = new TPanelGroup('Remetente / Destinatario');
        $this->form->addContent([$panel_remetente_destinatario]);
        // Remetente - busca na tabela Clientes
        $remetente_id = new TDBUniqueSearch('remetente_id', 'sample', 'Clientes', 'id', 'nome');
        $remetente_id->setMinLength(2);
        $remetente_id->setChangeAction(new TAction(['ConhecimentoForm', 'onClienteChange']));
        $remetente_id->setSize('100%');
        $nome_remetente     = new THidden('nome_remetente');
        $endereco_remetente = new TText('endereco_remetente');
        // Destinatario - busca na tabela Clientes
        $destinatario_id = new TDBUniqueSearch('destinatario_id', 'sample', 'Clientes', 'id', 'nome');
        $destinatario_id->setMinLength(2);
        $destinatario_id->setChangeAction(new TAction(['ConhecimentoForm', 'onClienteChangeDestinatario']));
        $destinatario_id->setSize('100%');
      $nome_destinatario     = new THidden('nome_destinatario');
        $endereco_destinatario = new TText('endereco_destinatario');
        // Layout em tabela para Remetente e Destinatario
        $table = new TTable;
        $table->width = '100%';

        $row = $table->addRow();
        $row->addCell(new TLabel('Remetente'))->style = 'width: 10%';
        $row->addCell($remetente_id)->style = 'width: 40%';
        $row->addCell(new TLabel('Destinatario'))->style = 'width: 10%';
        $row->addCell($destinatario_id)->style = 'width: 40%';

        // Linha 3 - Enderecos
        $row = $table->addRow();
        $row->addCell(new TLabel('Endereco'));
        $row->addCell($endereco_remetente)->style = 'width: 40%';
        $row->addCell(new TLabel('Endereco'));
        $row->addCell($endereco_destinatario)->style = 'width: 40%';

        $panel_remetente_destinatario->add($table);

        // 4 CONSIGNATRIO 5 NOTIFICAR
                $panel_consig_notify = new TPanelGroup('Consignatario / Notificar');
        $this->form->addContent([$panel_consig_notify]);

        // Consignatario - busca na tabela Clientes
        $consignatario_id = new TDBUniqueSearch('consignatario_id', 'sample', 'Clientes', 'id', 'nome');
        $consignatario_id->setMinLength(2);
        $consignatario_id->setChangeAction(new TAction(['ConhecimentoForm', 'onClienteChangeConsignatario']));
        $consignatario_id->setSize('100%');
        $nome_consignatario     = new TEntry('nome_consignatario');
        $endereco_consignatario = new TText('endereco_consignatario');

        // Notificar - busca na tabela Clientes
        $notificar_id = new TDBUniqueSearch('notificar_id', 'sample', 'Clientes', 'id', 'nome');
        $notificar_id->setMinLength(2);
        $notificar_id->setChangeAction(new TAction(['ConhecimentoForm', 'onClienteChangeNotificar']));
        $notificar_id->setSize('100%');
        $notificar_nome     = new TEntry('notificar_nome');
        $notificar_endereco = new TText('notificar_endereco');

        // Tabela para Consignatario e Notificar
        $table_consig_notify = new TTable;
        $table_consig_notify->width = '100%';

        // Linha 1 - IDs
        $row = $table_consig_notify->addRow();
        $row->addCell(new TLabel('6 Consig'))->style = 'width: 10%';
        $row->addCell($consignatario_id)->style = 'width: 40%';
        $row->addCell(new TLabel('9 Notificar'))->style = 'width: 10%';
        $row->addCell($notificar_id)->style = 'width: 40%';


        // Linha 3 - Enderecos
        $row = $table_consig_notify->addRow();
        $row->addCell(new TLabel('Endereco'));
        $row->addCell($endereco_consignatario);
        $row->addCell(new TLabel('Endereco'));
        $row->addCell($notificar_endereco);

        $panel_consig_notify->add($table_consig_notify);

        //  LOCAIS / PESO E CUBAGEM
                $panel_locais = new TPanelGroup('Locais, peso e cubagem');
        $this->form->addContent([$panel_locais]);

        $local_emissao          = new TEntry('local_emissao');
        $local_responsabilidade = new TEntry('local_responsabilidade');
        $local_entrega          = new TEntry('local_entrega');

        $local_emissao->setSize('100%');
        $local_responsabilidade->setSize('100%');
        $local_entrega->setSize('100%');

        // Tabela - Locais
        $table_locais = new TTable;
        $table_locais->width = '100%';

        // Linha 1 - Labels (Locais)
        $row = $table_locais->addRow();
        $cell = $row->addCell(new TLabel('Emissao'));
        $cell->style = 'width: 33%; text-align: left;';
        $cell = $row->addCell(new TLabel('Responsabilidade'));
        $cell->style = 'width: 33%; text-align: left;';
        $cell = $row->addCell(new TLabel('Entrega'));
        $cell->style = 'width: 34%; text-align: left;';

        // Linha 2 - Inputs (Locais)
        $row = $table_locais->addRow();
        $row->addCell($local_emissao);
        $row->addCell($local_responsabilidade);
        $row->addCell($local_entrega);

        $panel_locais->add($table_locais);

        // 7 CARGA
                $panel_carga = new TPanelGroup('Carga');
        $this->form->addContent([$panel_carga]);

        $descricao_mercadoria = new TText('descricao_mercadoria');
        $descricao_mercadoria->setSize('100%', null);
        $descricao_mercadoria->setProperty('style', 'height:250px !important; resize: none;');

        $peso_bruto_kg = new TNumeric('peso_bruto_kg', 3, ',', '.', null, true);
        $peso_liq_kg   = new TNumeric('peso_liq_kg', 3, ',', '.', null, true);
        $volume_m3     = new TEntry('volume_m3');

        $peso_bruto_kg->setSize('100%');
        $peso_liq_kg->setSize('100%');
        $volume_m3->setSize('100%');

        $incoterm   = new TEntry('incoterm');
        $incoterm16 = new TEntry('incoterm16');

        $incoterm->setSize('100%');
        $incoterm16->setSize('100%');

        $moeda_valor_mercadorias = new TEntry('moeda_valor_mercadorias');
        $valor_mercadorias       = new TNumeric('valor_mercadorias', 2, ',', '.', null, true);

        $moeda_valor_mercadorias->setSize('100%');
        $valor_mercadorias->setSize('100%');

        $valor_declarado = new TNumeric('valor_declarado', 2, ',', '.', null, true);
        $valor_declarado->setSize('100%');

        $left_box = new TVBox;
        $left_box->style = 'width: 100%;';
        $left_box->add(new TLabel('Descricao'));
        $left_box->add($descricao_mercadoria);

        $right_box = new TVBox;
        $right_box->style = 'width: 100%;';

        $table_pesos = new TTable;
        $table_pesos->width = '100%';
        $row = $table_pesos->addRow();
        $row->addCell(new TLabel('Peso Bruto (kg)'))->style = 'width: 50%; padding-right: 5px;';
        $row->addCell(new TLabel('Peso Liquido (kg)'))->style = 'width: 50%; padding-left: 5px;';
        $row = $table_pesos->addRow();
        $row->addCell($peso_bruto_kg)->style = 'width: 50%; padding-right: 5px;';
        $row->addCell($peso_liq_kg)->style = 'width: 50%; padding-left: 5px;';
        $right_box->add($table_pesos);

        $table_volume_incoterm = new TTable;
        $table_volume_incoterm->width = '100%';
        $row = $table_volume_incoterm->addRow();
        $row->addCell(new TLabel('Vol (m3)'))->style = 'width: 30%; padding-right: 5px;';
        $row->addCell(new TLabel(' Incoterm'))->style = 'width: 70%; padding-left: 5px;';
        $row = $table_volume_incoterm->addRow();
        $row->addCell($volume_m3)->style = 'width: 30%; padding-right: 5px;';
        $row->addCell($incoterm)->style = 'width: 70%; padding-left: 5px;';
        $right_box->add($table_volume_incoterm);

        $table_valores = new TTable;
        $table_valores->width = '100%';
        $row = $table_valores->addRow();
        $row->addCell(new TLabel(' Moeda'))->style = 'width: 50%; padding-right: 5px;';
        $row->addCell(new TLabel(' Valor Mercadoria'))->style = 'width: 50%; padding-left: 5px;';
        $row = $table_valores->addRow();
        $row->addCell($moeda_valor_mercadorias)->style = 'width: 50%; padding-right: 5px;';
        $row->addCell($valor_mercadorias)->style = 'width: 50%; padding-left: 5px;';
        $right_box->add($table_valores);

        $table_extras = new TTable;
        $table_extras->width = '100%';
        $row = $table_extras->addRow();
        $row->addCell(new TLabel(' Incoterm (16)'))->style = 'width: 50%; padding-right: 5px;';
        $row->addCell(new TLabel(' Valor Declarado'))->style = 'width: 50%; padding-left: 5px;';
        $row = $table_extras->addRow();
        $row->addCell($incoterm16)->style = 'width: 50%; padding-right: 5px;';
        $row->addCell($valor_declarado)->style = 'width: 50%; padding-left: 5px;';
        $right_box->add($table_extras);

        $table_layout = new TTable;
        $table_layout->width = '100%';
        $table_layout->style = 'table-layout: fixed;';

        $row = $table_layout->addRow();
        $row->addCell($left_box)->style  = 'width: 65%; vertical-align: top;';
        $row->addCell($right_box)->style = 'width: 35%; vertical-align: top; padding-left: 15px;';

        $panel_carga->add($table_layout);



        // 8 Incoterm, Custos e Gastos
                $panel_custos = new TPanelGroup('Incoterm, Custos e Gastos');
        $this->form->addContent([$panel_custos]);

        $textogasto1           = new TEntry('textogasto1');
        $textogasto2           = new TEntry('textogasto2');
        $textogasto3           = new TEntry('textogasto3');

        $custoremetente1       = new TNumeric('custoremetente1', 2, ',', '.', null, true);
        $custoremetente2       = new TNumeric('custoremetente2', 2, ',', '.', null, true);
        $custoremetente3       = new TNumeric('custoremetente3', 2, ',', '.', null, true);

        $custodestino1         = new TNumeric('custodestino1', 2, ',', '.', null, true);
        $custodestino2         = new TNumeric('custodestino2', 2, ',', '.', null, true);
        $custodestino3         = new TNumeric('custodestino3', 2, ',', '.', null, true);

        $total_custo_remetente    = new TNumeric('total_custo_remetente', 2, ',', '.', null, true);
        $total_custo_destinatario = new TNumeric('total_custo_destinatario', 2, ',', '.', null, true);

        $custoremetente1->setExitAction(new TAction([$this, 'onAtualizaTotais']));
        $custoremetente2->setExitAction(new TAction([$this, 'onAtualizaTotais']));
        $custoremetente3->setExitAction(new TAction([$this, 'onAtualizaTotais']));

        $custodestino1->setExitAction(new TAction([$this, 'onAtualizaTotais']));
        $custodestino2->setExitAction(new TAction([$this, 'onAtualizaTotais']));
        $custodestino3->setExitAction(new TAction([$this, 'onAtualizaTotais']));

        $gastosmoeda = new TCombo('gastosmoeda');
        $gastosmoeda->addItems([
            'BRL' => 'R$ - Real (Brasil)',
            'ARS' => 'AR$ - Peso Argentino',
            'PYG' => ' - Guarani (Paraguai)',
            'UYU' => 'U$U - Peso Uruguaio',
            'CLP' => 'CLP$ - Peso Chileno',
            'EUR' => ' - Euro',
            'USD' => 'US$ - Dlar Americano'
        ]);

        $documentos_anexos = new TText('documentos_anexos');
        $documentos_anexos->setSize('100%', null);
        $documentos_anexos->setProperty('style', 'height:400px; resize: none;');

        $fields = [
            $textogasto1, $textogasto2, $textogasto3,
            $custoremetente1, $custoremetente2, $custoremetente3,
            $custodestino1, $custodestino2, $custodestino3,
            $total_custo_remetente, $total_custo_destinatario,
            $gastosmoeda
        ];

        foreach ($fields as $f) {
            $f->setSize('100%');
        }

        $table_layout = new TTable;
        $table_layout->width = '100%';
        $table_layout->style = 'table-layout: fixed;';

        $left_box = new TVBox;
        $left_box->style = 'width: 100%;';

        $table_gastos = new TTable;
        $table_gastos->width = '100%';

        $row = $table_gastos->addRow();
        $row->addCell(new TLabel('Descricao'))->style = 'width: 50%';
        $row->addCell(new TLabel('Remetente'))->style = 'width: 25%';
        $row->addCell(new TLabel('Destinatario'))->style = 'width: 25%';

        for ($i = 1; $i <= 3; $i++) {
            $row = $table_gastos->addRow();
            $row->addCell(${"textogasto{$i}"})->style = 'padding-right: 5px;';
            $row->addCell(${"custoremetente{$i}"})->style = 'padding-left: 5px; padding-right: 5px;';
            $row->addCell(${"custodestino{$i}"})->style = 'padding-left: 5px;';
        }

        $row = $table_gastos->addRow();
        $row->addCell($gastosmoeda)->style = 'padding-top: 10px;';
        $row->addCell($total_custo_remetente)->style = 'padding-top: 10px; padding-left: 5px;';
        $row->addCell($total_custo_destinatario)->style = 'padding-top: 10px; padding-left: 5px;';

        $left_box->add(new TLabel(' Gastos a Pagar'));
        $left_box->add($table_gastos);

        $right_box = new TVBox;
        $right_box->add(new TLabel(' Documentos Anexos'));
        $right_box->style = 'width: 100%;';
        $right_box->add(new TLabel('Du-e e Despachante'));
        $right_box->add($documentos_anexos);

        $row = $table_layout->addRow();
        $row->addCell($left_box)->style = 'width: 65%; vertical-align: top;';
        $row->addCell($right_box)->style = 'width: 35%; vertical-align: top; padding-left: 15px;';

        $panel_custos->add($table_layout);

                $panel_frete_volumes = new TPanelGroup('Detalhes do Frete e Volumes');
        $this->form->addContent([$panel_frete_volumes]);

        // Tabela para Moeda Frete, Frete Externo, Valor Reembolso, Quantidade de Volumes e Especie Vol.
        $table_frete_volumes = new TTable;
        $table_frete_volumes->width = '100%';

        // Adicionando os rtulos e campos lado a lado na mesma linha
        $row = $table_frete_volumes->addRow();

        // Criando os campos
        $moeda_frete_externo = new TEntry('moeda_frete_externo');
        $valor_frete_externo = new TNumeric('valor_frete_externo', 2, ',', '.', null, true);
        $valor_reembolso = new TNumeric('valor_reembolso', 2, ',', '.', null, true);
        $quantidade_volumes = new TEntry('quantidade_volumes');
        $especie_vol = new TEntry('especie_vol');

        // Adicionando os rtulos (em uma linha)
        $row->addCell(new TLabel(' Moeda Frete'))->style = 'width: 20%; padding-left: 5px;';
        $row->addCell(new TLabel(' Frete Externo'))->style = 'width: 20%; padding-left: 5px; padding-right: 5px;';
        $row->addCell(new TLabel(' Valor Reembolso'))->style = 'width: 20%; padding-right: 5px;';
        $row->addCell(new TLabel(' Qtde Volumes'))->style = 'width: 20%; padding-right: 5px;';
        $row->addCell(new TLabel('Especie Vol.'))->style = 'width: 20%; padding-left: 5px;';

        // Adicionando os campos (na mesma linha)
        $row = $table_frete_volumes->addRow();
        $row->addCell($moeda_frete_externo)->style = 'padding-left: 5px;';
        $row->addCell($valor_frete_externo)->style = 'padding-left: 5px; padding-right: 5px;';
        $row->addCell($valor_reembolso)->style = 'padding-right: 5px;';
        $row->addCell($quantidade_volumes)->style = 'padding-right: 5px;';
        $row->addCell($especie_vol)->style = 'padding-left: 5px;';

        // Adicionando a tabela ao painel
        $panel_frete_volumes->add($table_frete_volumes);

        // Painel agrupado
        $panel_obs = new TPanelGroup('18 INSTRUÇÕES 22 OBSERVACOES');
        $panel_obs->style = 'margin-top: 20px';
        $this->form->addContent([$panel_obs]);

        // Container de layout responsivo
        $container = new TElement('div');
        $container->{'class'} = 'row';

        // Campo 1: Instrucoes Alfandega
        $instrucoes_alfandega = new TText('instrucoes_alfandega');
        $instrucoes_alfandega->setSize('100%', 80);
        $col1 = new TElement('div');
        $col1->{'class'} = 'col-sm-6';
        $col1->add(new TLabel(' Instrucoes Alfandega'));
        $col1->add($instrucoes_alfandega);

        // Campo 2: Observacoes
        $observacoes = new TText('observacoes');
        $observacoes->setSize('100%', 80);
        $col2 = new TElement('div');
        $col2->{'class'} = 'col-sm-6';
        $col2->add(new TLabel(' Observacoes'));
        $col2->add($observacoes);

        // Adiciona as colunas ao container
        $container->add($col1);
        $container->add($col2);

        // Adiciona o container ao painel
        $panel_obs->add($container);

        // 14 DOCUMENTAO & ASSINATURA
                $panel_docs = new TPanelGroup('Documentos & Assinatura');
        $panel_docs->style = 'margin-top: 20px';
        $this->form->addContent([$panel_docs]);

        // Container de layout responsivo
        $container_docs = new TElement('div');
        $container_docs->{'class'} = 'row';

        // --- CAMPO EXISTENTE: Assinatura ---
        $assinatura_nome = new TEntry('assinatura_nome');
        $assinatura_nome->setSize('100%');

        $col_assinatura = new TElement('div');
        $col_assinatura->{'class'} = 'col-sm-6'; // Ajustado para 6 colunas
        $col_assinatura->add(new TLabel(' Assinatura'));
        $col_assinatura->add($assinatura_nome);

        // --- NOVO CAMPO: Pagador ---
        $pagador_id = new TDBUniqueSearch('pagador_id', 'sample', 'Clientes', 'id', 'nome');
        $pagador_id->setMinLength(2);
        $pagador_id->setSize('100%');

        $col_pagador = new TElement('div');
        $col_pagador->{'class'} = 'col-sm-6'; // Ocupa as 6 colunas restantes
        $col_pagador->add(new TLabel(' Pagador'));
        $col_pagador->add($pagador_id);


        // Adiciona as colunas ao container
        $container_docs->add($col_assinatura);
        $container_docs->add($col_pagador); // Adiciona a nova coluna

        // Adiciona o container ao painel
        $panel_docs->add($container_docs);

        
        // Botes de ao
        $this->form->addAction(' Salvar', new TAction([$this, 'onSave']), 'fa:save')->class = 'btn btn-primary';

        $this->form->addActionLink(' Voltar', new TAction(['ConhecimentoList', 'onReload']), 'fa:arrow-left green');

        // Registro de todos os campos no formulrio
        $this->form->setFields([
            $id, $permisso, $numero, $data_transportador_assinatura, $fatura_crt,
            $remetente_id, $nome_remetente, $endereco_remetente,
            $destinatario_id, $nome_destinatario, $endereco_destinatario,
            $consignatario_id, $nome_consignatario, $endereco_consignatario,
            $notificar_id, $notificar_nome, $notificar_endereco,
            $local_emissao, $local_responsabilidade, $local_entrega,
            $quantidade_volumes, $descricao_mercadoria, $peso_bruto_kg, $peso_liq_kg, $volume_m3, $especie_vol,
            $incoterm, $incoterm16, $valor_mercadorias, $moeda_valor_mercadorias, $valor_declarado, $valor_reembolso,
            $valor_frete_externo, $moeda_frete_externo,
            $custoremetente1, $custoremetente2, $custoremetente3,
            $custodestino1, $custodestino2, $custodestino3,
            $total_custo_remetente, $total_custo_destinatario, $gastosmoeda,
            $textogasto1, $textogasto2, $textogasto3,
            $logotransporte,
            $instrucoes_alfandega, $observacoes,
            $documentos_anexos, $copiacrt, $assinatura_nome, $status_crt_id,
            $pagador_id // <<< CORREO APLICADA AQUI
        ]);

        // Container final
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }

    /**
     * Ao esttica para carregar os dados da tabela Permissaox ao selecionar um permisso
     */
    public static function onPermissaoChange($param)
    {
        try {
            TTransaction::log("Parmetros recebidos (Permissao): " . json_encode($param));
            $obj = new stdClass;
        
            if (!empty($param['permisso'])) {
                TTransaction::open('sample');
                $permissox = new Permissaox($param['permisso']);

                if ($permissox) {
                    // Preenche os campos com os dados da Permissaox
                    $obj->nome_transportador = $permissox->transportadora ?? '';
                    $obj->pais_destino = $permissox->pais_destino ?? '';
                    // Se houver logo, envia como JSON (ex.: {"fileName": "imagem.png"})
                    $obj->logotransporte = !empty($permissox->logo)
                        ? json_encode(['fileName' => basename($permissox->logo)])
                        : '';
                
                    TForm::sendData('form_Conhecimento', $obj);
                
                    TTransaction::log("Dados enviados: Transportadora=" . $obj->nome_transportador .
                                        ", Pas Destino=" . $obj->pais_destino .
                                        ", Logo=" . $obj->logotransporte);
                } else {
                    // Se o registro no for encontrado, limpa os campos
                    $obj->nome_transportador = '';
                    $obj->pais_destino = '';
                    $obj->logotransporte = '';
                    TForm::sendData('form_Conhecimento', $obj);
                    new TMessage('warning', 'Registro no encontrado para ID: ' . $param['permisso']);
                }
                
                TTransaction::close();
            } else {
                // Se o campo permisso no estiver preenchido, limpa os campos
                $obj->nome_transportador = '';
                $obj->pais_destino = '';
                $obj->logotransporte = '';
                TForm::sendData('form_Conhecimento', $obj);
            }
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar dados do Permissao: ' . $e->getMessage());
            TTransaction::log("Erro: " . $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Salvar dados do formulrio
     */
    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            $this->form->validate();

            $data = $this->form->getData();

            if (!empty($data->data_transportador_assinatura)) {
                $data->data_transportador_assinatura = TDate::date2us($data->data_transportador_assinatura);
            }

            $this->normalizeNumericFieldsForStorage($data);

            $data->copiarcrt = in_array($data->copiacrt, ['1', 1, true], true) ? '1' : '0';

            if (!empty($data->id)) {
                $object = new Conhecimento($data->id);
                if ($this->isConhecimentoEntregue($object)) {
                    throw new Exception('CRT com status ENTREGUE esta bloqueado para alteracoes. Somente visualizacao.');
                }
            } else {
                $object = new Conhecimento;
            }

            $object->fromArray((array) $data);
            $object->store();

            $data->id = $object->id;
            $this->form->setData($data);

            TTransaction::close();
            new TMessage('info', 'Registro salvo com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao salvar: ' . $e->getMessage());
            $this->form->setData($this->form->getData());
            TTransaction::rollback();
        }
    }

    /**
     * Limpar formulrio
     */
    public function onClear($param)
    {
        $this->form->clear(TRUE);
    }

    /**
     * Editar registro existente
     */
    public function onEdit($param)
    {
        try {
            if (isset($param['key'])) {
                TTransaction::open('sample');
                $object = new Conhecimento($param['key']);

                if ($object->copiacrt == '1') {
                    $object->copiacrt = '1';
                } else {
                    $object->copiacrt = null;
                }
                
                // <<< CORREO APLICADA AQUI
                if (!empty($object->data_transportador_assinatura)) {
                    $object->data_transportador_assinatura = TDate::convertToMask($object->data_transportador_assinatura, 'yyyy-mm-dd', 'dd/mm/yyyy');
                }

                $this->formatNumericFieldsForDisplay($object);
                
                $this->form->setData($object);

                if ($this->isConhecimentoEntregue($object)) {
                    $this->setFormReadOnlyMode();
                    new TMessage('info', 'CRT com status ENTREGUE: modo somente visualizacao.');
                }

                TTransaction::close();
            } else {
                $this->form->clear(TRUE);
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Ao esttica para carregar os dados do cliente para o Remetente
     */
    public static function onClienteChange($param)
    {
        try {
            TTransaction::log("Parmetros recebidos (Remetente): " . json_encode($param));
            if (!empty($param['remetente_id'])) {
                TTransaction::open('sample');
                $cliente = new Clientes($param['remetente_id']);
                if ($cliente) {
                    $obj = new stdClass;
                    $obj->nome_remetente = $cliente->nome;
                    $obj->endereco_remetente = $cliente->emissao_crt ?? '';
                    TForm::sendData('form_Conhecimento', $obj);
                    TTransaction::log("Dados enviados (Remetente): Nome=" . $cliente->nome . ", Endereco=" . ($cliente->emissao_crt ?? ''));
                } else {
                    $obj = new stdClass;
                    $obj->nome_remetente = '';
                    $obj->endereco_remetente = '';
                    TForm::sendData('form_Conhecimento', $obj);
                    new TMessage('warning', 'Cliente no encontrado para ID: ' . $param['remetente_id']);
                }
                TTransaction::close();
            }
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar dados do cliente: ' . $e->getMessage());
            TTransaction::log("Erro: " . $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Ao esttica para carregar os dados do cliente para o Destinatario
     */
    public static function onClienteChangeDestinatario($param)
    {
        try {
            TTransaction::log("Parmetros recebidos (Destinatario): " . json_encode($param));
            if (!empty($param['destinatario_id'])) {
                TTransaction::open('sample');
                $cliente = new Clientes($param['destinatario_id']);
                if ($cliente) {
                    $obj = new stdClass;
                    $obj->nome_destinatario = $cliente->nome;
                    $obj->endereco_destinatario = $cliente->emissao_crt ?? '';
                    TForm::sendData('form_Conhecimento', $obj);
                    TTransaction::log("Dados enviados (Destinatario): Nome=" . $cliente->nome . ", Endereco=" . ($cliente->emissao_crt ?? ''));
                } else {
                    $obj = new stdClass;
                    $obj->nome_destinatario = '';
                    $obj->endereco_destinatario = '';
                    TForm::sendData('form_Conhecimento', $obj);
                    new TMessage('warning', 'Cliente no encontrado para ID: ' . $param['destinatario_id']);
                }
                TTransaction::close();
            }
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar dados do cliente: ' . $e->getMessage());
            TTransaction::log("Erro: " . $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Ao esttica para carregar os dados do cliente para o Consignatario
     */
    public static function onClienteChangeConsignatario($param)
    {
        try {
            TTransaction::log("Parmetros recebidos (Consignatario): " . json_encode($param));
            if (!empty($param['consignatario_id'])) {
                TTransaction::open('sample');
                $cliente = new Clientes($param['consignatario_id']);
                if ($cliente) {
                    $obj = new stdClass;
                    $obj->nome_consignatario = $cliente->nome;
                    $obj->endereco_consignatario = $cliente->emissao_crt ?? '';
                    TForm::sendData('form_Conhecimento', $obj);
                    TTransaction::log("Dados enviados (Consignatario): Nome=" . $cliente->nome . ", Endereco=" . ($cliente->emissao_crt ?? ''));
                } else {
                    $obj = new stdClass;
                    $obj->nome_consignatario = '';
                    $obj->endereco_consignatario = '';
                    TForm::sendData('form_Conhecimento', $obj);
                    new TMessage('warning', 'Cliente no encontrado para ID: ' . $param['consignatario_id']);
                }
                TTransaction::close();
            }
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar dados do cliente: ' . $e->getMessage());
            TTransaction::log("Erro: " . $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Ao esttica para carregar os dados do cliente para o Notificar
     */
    public static function onClienteChangeNotificar($param)
    {
        try {
            TTransaction::log("Parmetros recebidos (Notificar): " . json_encode($param));
            if (!empty($param['notificar_id'])) {
                TTransaction::open('sample');
                $cliente = new Clientes($param['notificar_id']);
                if ($cliente) {
                    $obj = new stdClass;
                    $obj->notificar_nome = $cliente->nome;
                    $obj->notificar_endereco = $cliente->emissao_crt ?? '';
                    TForm::sendData('form_Conhecimento', $obj);
                    TTransaction::log("Dados enviados (Notificar): Nome=" . $cliente->nome . ", Endereco=" . ($cliente->emissao_crt ?? ''));
                } else {
                    $obj = new stdClass;
                    $obj->notificar_nome = '';
                    $obj->notificar_endereco = '';
                    TForm::sendData('form_Conhecimento', $obj);
                    new TMessage('warning', 'Cliente no encontrado para ID: ' . $param['notificar_id']);
                }
                TTransaction::close();
            }
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar dados do cliente: ' . $e->getMessage());
            TTransaction::log("Erro: " . $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Exporta o CRT atual como arquivo XML para download.
     */
    public static function onExportXml($param)
    {
        try {
            $id = $param['id'] ?? null;
            if (empty($id)) {
                new TMessage('warning', 'Salve o registro antes de exportar o XML.');
                return;
            }

            TTransaction::open('sample');
            $crt = new Conhecimento($id);
            TTransaction::close();

            if (empty($crt->id)) {
                throw new Exception('Registro não encontrado.');
            }

            $path = CrtXmlExporter::exportToFile($crt);
            TPage::openFile($path);
        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onAtualizaTotais($param)
    {
        $custoremetente1 = (float) str_replace(',', '.', str_replace('.', '', $param['custoremetente1'] ?? 0));
        $custoremetente2 = (float) str_replace(',', '.', str_replace('.', '', $param['custoremetente2'] ?? 0));
        $custoremetente3 = (float) str_replace(',', '.', str_replace('.', '', $param['custoremetente3'] ?? 0));

        $custodestino1 = (float) str_replace(',', '.', str_replace('.', '', $param['custodestino1'] ?? 0));
        $custodestino2 = (float) str_replace(',', '.', str_replace('.', '', $param['custodestino2'] ?? 0));
        $custodestino3 = (float) str_replace(',', '.', str_replace('.', '', $param['custodestino3'] ?? 0));

        $totalRemetente = $custoremetente1 + $custoremetente2 + $custoremetente3;
        $totalDestinatario = $custodestino1 + $custodestino2 + $custodestino3;

        TForm::sendData('form_Conhecimento', [
            'total_custo_remetente'    => number_format($totalRemetente, 2, ',', '.'),
            'total_custo_destinatario' => number_format($totalDestinatario, 2, ',', '.'),
        ]);
    }

    private function isConhecimentoEntregue(Conhecimento $conhecimento): bool
    {
        try {
            $statusNome = (string) ($conhecimento->status_crt->nome ?? '');
        } catch (Exception $e) {
            $statusNome = '';
        }

        $statusNome = strtoupper(trim($statusNome));
        return strpos($statusNome, 'ENTREG') !== false;
    }

    private function setFormReadOnlyMode(): void
    {
        $this->isReadOnly = true;

        foreach ((array) $this->form->getFields() as $field) {
            if (is_object($field) && !($field instanceof THidden) && method_exists($field, 'setEditable')) {
                $field->setEditable(false);
            }
        }

        TScript::create("$('#form_Conhecimento').closest('.panel,.card').find('.btn.btn-primary').hide();");
    }

    private function normalizeNumericFieldsForStorage($data): void
    {
        $fields = $this->getNumericFieldScaleMap();
        foreach ($fields as $field => $decimals) {
            if (property_exists($data, $field)) {
                $data->{$field} = self::formatNumberForStorage($data->{$field}, $decimals);
            }
        }
    }

    private function formatNumericFieldsForDisplay($data): void
    {
        $fields = $this->getNumericFieldScaleMap();
        foreach ($fields as $field => $decimals) {
            if (isset($data->{$field})) {
                $data->{$field} = self::formatNumberForDisplay($data->{$field}, $decimals);
            }
        }
    }

    private function getNumericFieldScaleMap(): array
    {
        return [
            'peso_bruto_kg' => 3,
            'peso_liq_kg' => 3,
            'valor_mercadorias' => 2,
            'valor_declarado' => 2,
            'valor_reembolso' => 2,
            'valor_frete_externo' => 2,
            'custoremetente1' => 2,
            'custoremetente2' => 2,
            'custoremetente3' => 2,
            'custodestino1' => 2,
            'custodestino2' => 2,
            'custodestino3' => 2,
            'total_custo_remetente' => 2,
            'total_custo_destinatario' => 2,
        ];
    }

    private static function parseNumericValue($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $text = preg_replace('/[^0-9,.\-]/', '', $text);
        if ($text === '' || $text === '-' || $text === ',' || $text === '.') {
            return null;
        }

        $lastComma = strrpos($text, ',');
        $lastDot = strrpos($text, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSep = ($lastComma > $lastDot) ? ',' : '.';
            $thousandSep = ($decimalSep === ',') ? '.' : ',';
            $text = str_replace($thousandSep, '', $text);
            $text = str_replace($decimalSep, '.', $text);
        } elseif ($lastComma !== false) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } else {
            $text = str_replace(',', '', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private static function formatNumberForStorage($value, int $decimals): ?string
    {
        $number = self::parseNumericValue($value);
        if ($number === null) {
            $raw = trim((string) $value);
            return $raw === '' ? null : $raw;
        }

        return number_format($number, $decimals, '.', '');
    }

    private static function formatNumberForDisplay($value, int $decimals): string
    {
        $number = self::parseNumericValue($value);
        if ($number === null) {
            return trim((string) $value);
        }

        return number_format($number, $decimals, ',', '.');
    }
}
