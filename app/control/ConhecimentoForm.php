<?php
class ConhecimentoForm extends TPage
{
    protected $form;

    public function __construct($param)
    {
        parent::__construct();

        // Criação do formulário com nome 'form_Conhecimento'
        $this->form = new BootstrapFormBuilder('form_Conhecimento');
        $this->form->setFormTitle('📦 Conhecimento de Transporte');
        $this->form->setFieldSizes('100%');

        // ✅ Campo oculto ID
        $id = new THidden('id');
        $this->form->addFields([$id]);

        // 1️⃣ INFORMAÇÕES GERAIS
        $panel_geral = new TPanelGroup('1️⃣ Informações Gerais');

        // Criação dos campos
        $permisso = new TDBUniqueSearch('permisso', 'sample', 'Permissox', 'id', 'permisso');
        $permisso->setMinLength(2);
        $permisso->setSize('100%');
        $permisso->setChangeAction(new TAction(['ConhecimentoForm', 'onPermissoChange']));

        $numero                        = new TEntry('numero');
        $data_transportador_assinatura = new TDate('data_transportador_assinatura');
        $data_transportador_assinatura->setMask('dd/mm/yyyy');
        $fatura_crt                    = new TEntry('fatura_crt');
        $pais_destino                  = new TEntry('pais_destino');
        $status_crt_id                 = new TDBCombo('status_crt_id', 'sample', 'StatusCRT', 'id', 'nome');
        $copiacrt                      = new TCheckButton('copiacrt');
        $copiacrt->setIndexValue('1');
        $copiacrt->setUseSwitch(true, 'blue');
        $nome_transportador            = new TText('nome_transportador');
        $nome_transportador->setSize('100%', 120);
        // Alterado para campo oculto
        $logotransporte = new TEntry('logotransporte');

        // Organiza os campos em uma única linha com labels acima dos inputs
        $table = new TTable;
        $table->width = '100%';
        $row = $table->addRow();

        // Vincula o campo ao formulário para salvar corretamente (não duplica a visualização)
        $this->form->addFields([new TLabel('Copiar CRT')], [$copiacrt]);

        // Coluna 1: Permisso
        $cell = $row->addCell('');
        $cell->add(new TLabel('📌 2-Permisso'));
        $cell->add($permisso);

        // Coluna 2: País Destino
        $cell = $row->addCell('');
        $cell->add(new TLabel('🌍 2-País Destino'));
        $cell->add($pais_destino);

        // Coluna 3: Número CRT
        $cell = $row->addCell('');
        $cell->add(new TLabel('📃 2-Número CRT'));
        $cell->add($numero);

        // Coluna 4: Data CRT
        $cell = $row->addCell('');
        $cell->add(new TLabel('🗓️ Data CRT'));
        $cell->add($data_transportador_assinatura);

        // Coluna 5: Fatura
        $cell = $row->addCell('');
        $cell->add(new TLabel('🧾 fatura_crt'));
        $cell->add($fatura_crt);

        // Coluna 6: Situação
        $cell = $row->addCell('');
        $cell->add(new TLabel('📄 Situação'));
        $cell->add($status_crt_id);

        // Linha do Transportador e Logo (campo oculto)
        $row = $table->addRow();
        // Coluna 1: Transportador
        $cell = $row->addCell('');
        $cell->colspan = 4; // Ocupa 4 colunas
        $cell->add(new TLabel('🚚 Transportador'));
        $cell->add($nome_transportador);

        $panel_geral->add($table);
        $this->form->addContent([$panel_geral]);

        // 🏷️ REMETENTE/DESTINATÁRIO
        $panel_remetente_destinatario = new TPanelGroup('🏷️ Remetente / Destinatário');
        $this->form->addContent([$panel_remetente_destinatario]);

        // Remetente - busca na tabela Clientes
        $remetente_id = new TDBUniqueSearch('remetente_id', 'sample', 'Clientes', 'id', 'nome');
        $remetente_id->setMinLength(2);
        $remetente_id->setChangeAction(new TAction(['ConhecimentoForm', 'onClienteChange']));
        $remetente_id->setSize('100%');
        $nome_remetente     = new TEntry('nome_remetente');
        $endereco_remetente = new TText('endereco_remetente');

        // Destinatário - busca na tabela Clientes
        $destinatario_id = new TDBUniqueSearch('destinatario_id', 'sample', 'Clientes', 'id', 'nome');
        $destinatario_id->setMinLength(2);
        $destinatario_id->setChangeAction(new TAction(['ConhecimentoForm', 'onClienteChangeDestinatario']));
        $destinatario_id->setSize('100%');
        $nome_destinatario     = new TEntry('nome_destinatario');
        $endereco_destinatario = new TText('endereco_destinatario');

        // Layout em tabela para Remetente e Destinatário
        $table = new TTable;
        $table->width = '100%';

        // Linha 1 - IDs
        $row = $table->addRow();
        $row->addCell(new TLabel('🔍'))->style = 'width: 10%';
        $row->addCell($remetente_id)->style = 'width: 40%';
        $row->addCell(new TLabel('🔍'))->style = 'width: 10%';
        $row->addCell($destinatario_id)->style = 'width: 40%';

        // Linha 2 - Nomes
        $row = $table->addRow();
        $row->addCell(new TLabel('1️⃣ Remetente'));
        $row->addCell($nome_remetente);
        $row->addCell(new TLabel('4️⃣ Destinatário'));
        $row->addCell($nome_destinatario);

        // Linha 3 - Endereços
        $row = $table->addRow();
        $row->addCell(new TLabel('📫 Endereço'));
        $row->addCell($endereco_remetente)->style = 'width: 40%';
        $row->addCell(new TLabel('📫 Endereço'));
        $row->addCell($endereco_destinatario)->style = 'width: 40%';

        $panel_remetente_destinatario->add($table);

        // 4️⃣ CONSIGNATÁRIO 5️⃣ NOTIFICAR
        $panel_consig_notify = new TPanelGroup('🏷️ Consignatário / Notificar');
        $this->form->addContent([$panel_consig_notify]);

        // Consignatário - busca na tabela Clientes
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

        // Tabela para Consignatário e Notificar
        $table_consig_notify = new TTable;
        $table_consig_notify->width = '100%';

        // Linha 1 - IDs
        $row = $table_consig_notify->addRow();
        $row->addCell(new TLabel('🔍'))->style = 'width: 10%';
        $row->addCell($consignatario_id)->style = 'width: 40%';
        $row->addCell(new TLabel('🔍'))->style = 'width: 10%';
        $row->addCell($notificar_id)->style = 'width: 40%';

        // Linha 2 - Nomes
        $row = $table_consig_notify->addRow();
        $row->addCell(new TLabel('6️⃣ Consig.'));
        $row->addCell($nome_consignatario);
        $row->addCell(new TLabel('9️⃣ Notificar'));
        $row->addCell($notificar_nome);

        // Linha 3 - Endereços
        $row = $table_consig_notify->addRow();
        $row->addCell(new TLabel('📫 Endereço'));
        $row->addCell($endereco_consignatario);
        $row->addCell(new TLabel('📫 Endereço'));
        $row->addCell($notificar_endereco);

        $panel_consig_notify->add($table_consig_notify);

        // 📦 LOCAIS / PESO E CUBAGEM 
        $panel_locais = new TPanelGroup('📦 Locais, peso e cubagem');
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
        $cell = $row->addCell(new TLabel('5️⃣ Emissão'));
        $cell->style = 'width: 33%; text-align: left;';
        $cell = $row->addCell(new TLabel('7️⃣ Responsabilidade'));
        $cell->style = 'width: 33%; text-align: left;';
        $cell = $row->addCell(new TLabel('8️⃣ Entrega'));
        $cell->style = 'width: 34%; text-align: left;';

        // Linha 2 - Inputs (Locais)
        $row = $table_locais->addRow();
        $row->addCell($local_emissao);
        $row->addCell($local_responsabilidade);
        $row->addCell($local_entrega);

        $panel_locais->add($table_locais);

        // 7️⃣ CARGA
        $panel_carga = new TPanelGroup('7️⃣ Carga');
        $this->form->addContent([$panel_carga]);

        $descricao_mercadoria = new TText('descricao_mercadoria');
        $descricao_mercadoria->setSize('100%', null);
        $descricao_mercadoria->setProperty('style', 'height:300px !important; resize: none;');

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
        $left_box->add(new TLabel('📝 Descrição da Mercadoria'));
        $left_box->add($descricao_mercadoria);

        $right_box = new TVBox;
        $right_box->style = 'width: 100%;';

        $table_pesos = new TTable;
        $table_pesos->width = '100%';
        $row = $table_pesos->addRow();
        $row->addCell(new TLabel('⚖️ Peso Bruto (kg)'))->style = 'width: 50%; padding-right: 5px;';
        $row->addCell(new TLabel('⚖️ Peso Líquido (kg)'))->style = 'width: 50%; padding-left: 5px;';
        $row = $table_pesos->addRow();
        $row->addCell($peso_bruto_kg)->style = 'width: 50%; padding-right: 5px;';
        $row->addCell($peso_liq_kg)->style = 'width: 50%; padding-left: 5px;';
        $right_box->add($table_pesos);

        $table_volume_incoterm = new TTable;
        $table_volume_incoterm->width = '100%';
        $row = $table_volume_incoterm->addRow();
        $row->addCell(new TLabel('📐 Vol(m³)'))->style = 'width: 30%; padding-right: 5px;';
        $row->addCell(new TLabel('💰 Incoterm'))->style = 'width: 70%; padding-left: 5px;';
        $row = $table_volume_incoterm->addRow();
        $row->addCell($volume_m3)->style = 'width: 30%; padding-right: 5px;';
        $row->addCell($incoterm)->style = 'width: 70%; padding-left: 5px;';
        $right_box->add($table_volume_incoterm);

        $table_valores = new TTable;
        $table_valores->width = '100%';
        $row = $table_valores->addRow();
        $row->addCell(new TLabel('💱 Moeda'))->style = 'width: 50%; padding-right: 5px;';
        $row->addCell(new TLabel('💲 Valor Mercadoria'))->style = 'width: 50%; padding-left: 5px;';
        $row = $table_valores->addRow();
        $row->addCell($moeda_valor_mercadorias)->style = 'width: 50%; padding-right: 5px;';
        $row->addCell($valor_mercadorias)->style = 'width: 50%; padding-left: 5px;';
        $right_box->add($table_valores);

        $table_extras = new TTable;
        $table_extras->width = '100%';
        $row = $table_extras->addRow();
        $row->addCell(new TLabel('📘 Incoterm (16)'))->style = 'width: 50%; padding-right: 5px;';
        $row->addCell(new TLabel('📄 Valor Declarado'))->style = 'width: 50%; padding-left: 5px;';
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

        // 8️⃣ Incoterm, Custos e Gastos
        $panel_custos = new TPanelGroup('8️⃣ Incoterm, Custos e Gastos');
        $this->form->addContent([$panel_custos]);

        $quantidade_volumes       = new TEntry('quantidade_volumes');
        $especie_vol              = new TEntry('especie_vol');
        $valor_reembolso          = new TNumeric('valor_reembolso', 2, ',', '.', null, true);
        $valor_frete_externo      = new TNumeric('valor_frete_externo', 2, ',', '.', null, true);
        $moeda_frete_externo      = new TEntry('moeda_frete_externo');

        $textogasto1              = new TEntry('textogasto1');
        $textogasto2              = new TEntry('textogasto2');
        $textogasto3              = new TEntry('textogasto3');

        $custoremetente1          = new TNumeric('custoremetente1', 2, ',', '.', null, true);
        $custoremetente2          = new TNumeric('custoremetente2', 2, ',', '.', null, true);
        $custoremetente3          = new TNumeric('custoremetente3', 2, ',', '.', null, true);

        $custodestino1            = new TNumeric('custodestino1', 2, ',', '.', null, true);
        $custodestino2            = new TNumeric('custodestino2', 2, ',', '.', null, true);
        $custodestino3            = new TNumeric('custodestino3', 2, ',', '.', null, true);

        $total_custo_remetente    = new TNumeric('total_custo_remetente', 2, ',', '.', null, true);
        $total_custo_destinatario = new TNumeric('total_custo_destinatario', 2, ',', '.', null, true);

        $gastosmoeda = new TCombo('gastosmoeda');
        $gastosmoeda->addItems([
            'BRL' => 'R$ - Real (Brasil)',
            'ARS' => 'AR$ - Peso Argentino',
            'PYG' => '₲ - Guarani (Paraguai)',
            'UYU' => 'U$U - Peso Uruguaio',
            'CLP' => 'CLP$ - Peso Chileno',
            'EUR' => '€ - Euro',
            'USD' => 'US$ - Dólar Americano'
        ]);

        $documentos_anexos = new TText('documentos_anexos');
        $documentos_anexos->setSize('100%', null);
        $documentos_anexos->setProperty('style', 'height:200px; resize: none;');

        $fields = [
            $quantidade_volumes, $especie_vol,
            $valor_reembolso, $valor_frete_externo, $moeda_frete_externo,
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
        $row->addCell(new TLabel('<b>🧾 Descrição</b>'))->style = 'width: 50%';
        $row->addCell(new TLabel('💸 Remetente'))->style = 'width: 25%';
        $row->addCell(new TLabel('💰 Destinatário'))->style = 'width: 25%';

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

        $left_box->add(new TLabel('💰 Gastos a Pagar'));
        $left_box->add($table_gastos);

        $table_frete = new TTable;
        $table_frete->width = '100%';
        $row = $table_frete->addRow();
        $row->addCell(new TLabel('💱 Moeda Frete'))->style = 'width: 34%; padding-left: 5px;';
        $row->addCell(new TLabel('🚚 Frete Externo'))->style = 'width: 33%; padding-left: 5px; padding-right: 5px;';
        $row->addCell(new TLabel('💵 Valor Reembolso'))->style = 'width: 33%; padding-right: 5px;';

        $row = $table_frete->addRow();
        $row->addCell($moeda_frete_externo)->style = 'padding-left: 5px;';
        $row->addCell($valor_frete_externo)->style = 'padding-left: 5px; padding-right: 5px;';
        $row->addCell($valor_reembolso)->style = 'padding-right: 5px;';
        $left_box->add($table_frete);

        $table_volumes = new TTable;
        $table_volumes->width = '100%';
        $row = $table_volumes->addRow();
        $row->addCell(new TLabel('📦 Qtde Volumes'))->style = 'width: 50%; padding-right: 5px;';
        $row->addCell(new TLabel('📦 Espécie Vol.'))->style = 'width: 50%; padding-left: 5px;';
        $row = $table_volumes->addRow();
        $row->addCell($quantidade_volumes)->style = 'padding-right: 5px;';
        $row->addCell($especie_vol)->style = 'padding-left: 5px;';
        $left_box->add($table_volumes);

        $right_box = new TVBox;
        $right_box->add(new TLabel('📎 Documentos Anexos'));
        $right_box->style = 'width: 100%;';
        $right_box->add(new TLabel('🗒️Du-e e Despachante'));
        $right_box->add($documentos_anexos);

        $row = $table_layout->addRow();
        $row->addCell($left_box)->style = 'width: 65%; vertical-align: top;';
        $row->addCell($right_box)->style = 'width: 35%; vertical-align: top; padding-left: 15px;';

        $panel_custos->add($table_layout);

        // 1️⃣1️⃣ TRANSPORTE & PAGADOR
        $panel_transporte = new TPanelGroup('1️⃣1️⃣ Transporte & Pagador');
        $this->form->addContent([$panel_transporte]);

        $porteador    = new TEntry('porteador');
        $pagador_id   = new TEntry('pagador_id');
        $nome_pagador = new TEntry('nome_pagador');

        $this->form->addFields(
            [new TLabel('🚛 Porteador')], [$porteador]
        );
        $this->form->addFields(
            [new TLabel('💳 Pagador ID')], [$pagador_id],
            [new TLabel('💳 Nome Pagador')], [$nome_pagador]
        );

        // 1️⃣2️⃣ FATURAS & TAXAS
        $panel_faturas = new TPanelGroup('1️⃣2️⃣ Faturas & Taxas');
        $this->form->addContent([$panel_faturas]);

        $valorfaturausd = new TEntry('valorfaturausd');
        $valor_fatbr    = new TEntry('valor_fatbr');
        $fatura_usd     = new TEntry('fatura_usd');
        $fatura_brl     = new TEntry('fatura_brl');
        $taxadolar      = new TEntry('taxadolar');

        $this->form->addFields(
            [new TLabel('💵 Valor Fatura USD')], [$valorfaturausd],
            [new TLabel('💴 Valor Fatura BRL')], [$valor_fatbr]
        );
        $this->form->addFields(
            [new TLabel('💱 Taxa Dólar')], [$taxadolar]
        );
        $this->form->addFields(
            [new TLabel('📄 Fatura USD')], [$fatura_usd],
            [new TLabel('📄 Fatura BRL')], [$fatura_brl]
        );
        // 1️⃣3️⃣ OBSERVAÇÕES & INSTRUÇÕES
        $panel_obs = new TPanelGroup('1️⃣3️⃣ Observações & Instruções');
        $this->form->addContent([$panel_obs]);

        $observacoes         = new TText('observacoes');
        $instrucoes_alfandega = new TText('instrucoes_alfandega');

        $this->form->addFields(
            [new TLabel('📝 Observações')], [$observacoes]
        );
        $this->form->addFields(
            [new TLabel('🛃 Instruções Alfândega')], [$instrucoes_alfandega]
        );

        // 1️⃣4️⃣ DOCUMENTAÇÃO & ASSINATURA
        $panel_docs = new TPanelGroup('1️⃣4️⃣ Documentos & Assinatura');
        $this->form->addContent([$panel_docs]);

        $assinatura_nome = new TEntry('assinatura_nome');
        $faturado        = new TEntry('faturado');

        $this->form->addFields(
            [new TLabel('🖋️ Assinatura')], [$assinatura_nome],
            [new TLabel('✅ Faturado')], [$faturado],
           );
        $this->form->addFields([new TLabel('Logotransporte')], [$logotransporte]);


        // Botões de ação
        $this->form->addAction('💾 Salvar', new TAction([$this, 'onSave']), 'fa:save')->class = 'btn btn-primary';
        $this->form->addActionLink('🔙 Voltar', new TAction(['ConhecimentoList', 'onReload']), 'fa:arrow-left green');

        // Registro de todos os campos no formulário
        $this->form->setFields([
            $id, $permisso, $numero, $data_transportador_assinatura, $fatura_crt, $pais_destino, 
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
            $nome_transportador, $logotransporte, $porteador, $pagador_id, $nome_pagador,
            $valorfaturausd, $valor_fatbr, $fatura_usd, $fatura_brl, $taxadolar,
            $observacoes, $instrucoes_alfandega,
            $documentos_anexos, $copiacrt, $assinatura_nome, $faturado, $status_crt_id
        ]);

        // Container final
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }

    /**
     * Ação estática para carregar os dados da tabela Permissox ao selecionar um permisso
     */
    public static function onPermissoChange($param)
    {
        try {
            TTransaction::log("Parâmetros recebidos (Permisso): " . json_encode($param));
            $obj = new stdClass;
    
            if (!empty($param['permisso'])) {
                TTransaction::open('sample');
                $permissox = new Permissox($param['permisso']);
    
                if ($permissox) {
                    $obj->nome_transportador = !empty($permissox->transportadora) ? $permissox->transportadora : '';
                    $obj->pais_destino       = !empty($permissox->pais_destino) ? $permissox->pais_destino : '';
                    $obj->logotransporte     = !empty($permissox->logo) ? $permissox->logo : '';
    
                    TTransaction::log("Dados enviados: Transportadora={$obj->nome_transportador}, País Destino={$obj->pais_destino}, logotransporte={$obj->logotransporte}");
                    TForm::sendData('form_Conhecimento', $obj);
                } else {
                    $obj->nome_transportador = '';
                    $obj->pais_destino       = '';
                    $obj->logotransporte     = '';
                    TForm::sendData('form_Conhecimento', $obj);
                    new TMessage('warning', 'Registro não encontrado para ID: ' . $param['permisso']);
                }
    
                TTransaction::close();
            } else {
                $obj->nome_transportador = '';
                $obj->pais_destino       = '';
                $obj->logotransporte     = '';
                TForm::sendData('form_Conhecimento', $obj);
            }
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar dados do Permisso: ' . $e->getMessage());
            TTransaction::log("Erro: " . $e->getMessage());
            TTransaction::rollback();
        }
    }
    
    
    /**
     * Salvar dados do formulário
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

            // Ajusta o valor do campo de cópia do CRT
            $data->copiarcrt = in_array($data->copiacrt, ['1', 1, true], true) ? '1' : '0';

            if (!empty($data->id)) {
                $object = new Conhecimento($data->id);
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
     * Limpar formulário
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

                // Corrige a atribuição do campo de cópia do CRT:
                // No banco o campo é "copiarcrt" e no formulário o campo é "copiacrt"
                if ($object->copiarcrt == '1') {
                    $object->copiacrt = '1';
                } else {
                    $object->copiacrt = null;
                }

                $this->form->setData($object);
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
     * Ação estática para carregar os dados do cliente para o Remetente
     */
    public static function onClienteChange($param)
    {
        try {
            TTransaction::log("Parâmetros recebidos (Remetente): " . json_encode($param));
            if (!empty($param['remetente_id'])) {
                TTransaction::open('sample');
                $cliente = new Clientes($param['remetente_id']);
                if ($cliente) {
                    $obj = new stdClass;
                    $obj->nome_remetente    = $cliente->nome;
                    $obj->endereco_remetente = $cliente->emissao_crt ?? '';
                    TForm::sendData('form_Conhecimento', $obj);
                    TTransaction::log("Dados enviados (Remetente): Nome=" . $cliente->nome . ", Endereço=" . ($cliente->emissao_crt ?? ''));
                } else {
                    $obj = new stdClass;
                    $obj->nome_remetente    = '';
                    $obj->endereco_remetente = '';
                    TForm::sendData('form_Conhecimento', $obj);
                    new TMessage('warning', 'Cliente não encontrado para ID: ' . $param['remetente_id']);
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
     * Ação estática para carregar os dados do cliente para o Destinatário
     */
    public static function onClienteChangeDestinatario($param)
    {
        try {
            TTransaction::log("Parâmetros recebidos (Destinatário): " . json_encode($param));
            if (!empty($param['destinatario_id'])) {
                TTransaction::open('sample');
                $cliente = new Clientes($param['destinatario_id']);
                if ($cliente) {
                    $obj = new stdClass;
                    $obj->nome_destinatario    = $cliente->nome;
                    $obj->endereco_destinatario = $cliente->emissao_crt ?? '';
                    TForm::sendData('form_Conhecimento', $obj);
                    TTransaction::log("Dados enviados (Destinatário): Nome=" . $cliente->nome . ", Endereço=" . ($cliente->emissao_crt ?? ''));
                } else {
                    $obj = new stdClass;
                    $obj->nome_destinatario    = '';
                    $obj->endereco_destinatario = '';
                    TForm::sendData('form_Conhecimento', $obj);
                    new TMessage('warning', 'Cliente não encontrado para ID: ' . $param['destinatario_id']);
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
     * Ação estática para carregar os dados do cliente para o Consignatário
     */
    public static function onClienteChangeConsignatario($param)
    {
        try {
            TTransaction::log("Parâmetros recebidos (Consignatário): " . json_encode($param));
            if (!empty($param['consignatario_id'])) {
                TTransaction::open('sample');
                $cliente = new Clientes($param['consignatario_id']);
                if ($cliente) {
                    $obj = new stdClass;
                    $obj->nome_consignatario    = $cliente->nome;
                    $obj->endereco_consignatario = $cliente->emissao_crt ?? '';
                    TForm::sendData('form_Conhecimento', $obj);
                    TTransaction::log("Dados enviados (Consignatário): Nome=" . $cliente->nome . ", Endereço=" . ($cliente->emissao_crt ?? ''));
                } else {
                    $obj = new stdClass;
                    $obj->nome_consignatario    = '';
                    $obj->endereco_consignatario = '';
                    TForm::sendData('form_Conhecimento', $obj);
                    new TMessage('warning', 'Cliente não encontrado para ID: ' . $param['consignatario_id']);
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
     * Ação estática para carregar os dados do cliente para o Notificar
     */
    public static function onClienteChangeNotificar($param)
    {
        try {
            TTransaction::log("Parâmetros recebidos (Notificar): " . json_encode($param));
            if (!empty($param['notificar_id'])) {
                TTransaction::open('sample');
                $cliente = new Clientes($param['notificar_id']);
                if ($cliente) {
                    $obj = new stdClass;
                    $obj->notificar_nome    = $cliente->nome;
                    $obj->notificar_endereco = $cliente->emissao_crt ?? '';
                    TForm::sendData('form_Conhecimento', $obj);
                    TTransaction::log("Dados enviados (Notificar): Nome=" . $cliente->nome . ", Endereço=" . ($cliente->emissao_crt ?? ''));
                } else {
                    $obj = new stdClass;
                    $obj->notificar_nome    = '';
                    $obj->notificar_endereco = '';
                    TForm::sendData('form_Conhecimento', $obj);
                    new TMessage('warning', 'Cliente não encontrado para ID: ' . $param['notificar_id']);
                }
                TTransaction::close();
            }
        } catch (Exception $e) {
            new TMessage('error', 'Erro ao carregar dados do cliente: ' . $e->getMessage());
            TTransaction::log("Erro: " . $e->getMessage());
            TTransaction::rollback();
        }
    }
}
?>
