<?php
class FaturaForm extends TPage
{
    protected $form;
    private static $form_name = 'form_fatura';
    private static $spelloutFormatter;

    /**
     * Constructor method
     */
    public function __construct($param = null)
    {
        parent::__construct();
        Conhecimento::ensureSchema();
        Fatura::ensureSchema();

        // Cria o formulario principal (UNICO - sem forms aninhados)
        $this->form = new BootstrapFormBuilder(self::$form_name);
        $this->form->setFormTitle('<i class="fa fa-file-text-o"></i> Cadastro de Fatura');

        // === CAMPOS DO FORMULARIO ===
        $id               = new TEntry('id');
        $numero_crt       = new TDBUniqueSearch('conhecimento_id', 'sample', 'Conhecimento', 'id', 'numero');
        $fatura_cliente   = new TEntry('fatura_cliente');
        $pessoa_id        = new TDBUniqueSearch('pessoa_id', 'sample', 'Clientes', 'id', 'nome');

        $cliente_cnpj     = new TEntry('cliente_cnpj');
        $cliente_ie       = new TEntry('cliente_ie');
        $cliente_endereco = new TEntry('cliente_endereco');

        $data_emissao    = new TDate('emissao');
        $prazo_dias      = new TNumeric('prazo', 0, ',', '.');
        $data_vencimento = new TDate('vencimento');
        $taxa            = new TNumeric('taxa', 2, ',', '.');
        $nota_fiscal     = new TEntry('nota_fiscal');

        $origem       = new TEntry('ORIGEM');
        $destino      = new TEntry('DESTINO');
        $remetente    = new TEntry('REMETENTE');
        $destinatario = new TEntry('DESTINATARIO');
        $peso_bruto   = new TNumeric('PESO_BRUTO', 2, ',', '.');
        $produto      = new TEntry('PRODUTO');

        $descricao1 = new TEntry('descricao1');
        $valor1     = new TNumeric('valor1', 2, ',', '.');
        $descricao2 = new TEntry('descricao2');
        $valor2     = new TNumeric('valor2', 2, ',', '.');
        $descricao3 = new TEntry('descricao3');
        $valor3     = new TNumeric('valor3', 2, ',', '.');

        $valor_extenso    = new TEntry('valor_extenso');
        $valor_fatura     = new TNumeric('valor_fatura', 2, ',', '.');
        $pagamento        = new TDate('pagamento');
        $tipo_baixa       = new TCombo('tipo_baixa');
        $desconto_banco   = new TNumeric('desconto_banco', 2, ',', '.');
        $valor_liquido    = new TNumeric('valor_liquido', 2, ',', '.');
        $texto_observacao = new TText('texto_observacao');

        // === CONFIGURACOES DE CAMPOS ===
        $id->setEditable(false);
        $remetente->setProperty('readonly', '1');
        $destinatario->setProperty('readonly', '1');
        $origem->setProperty('readonly', '1');
        $destino->setProperty('readonly', '1');
        $valor_extenso->setProperty('readonly', '1');
        $cliente_cnpj->setProperty('readonly', '1');
        $cliente_ie->setProperty('readonly', '1');
        $cliente_endereco->setProperty('readonly', '1');

        $data_emissao->setMask('dd/mm/yyyy');
        $data_emissao->setDatabaseMask('yyyy-mm-dd');
        $data_vencimento->setMask('dd/mm/yyyy');
        $data_vencimento->setDatabaseMask('yyyy-mm-dd');
        $pagamento->setMask('dd/mm/yyyy');
        $pagamento->setDatabaseMask('yyyy-mm-dd');
        $texto_observacao->setSize('100%', 50);
        $fatura_cliente->setProperty('placeholder', 'Ex.: 09CL73405-26');
        $nota_fiscal->setProperty('placeholder', 'Numero/serie da nota fiscal');
        $texto_observacao->setProperty('placeholder', 'Informacoes adicionais para cobranca, banco ou cliente');
        $desconto_banco->setProperty('style', 'text-align:right');
        $valor_fatura->setProperty('style', 'text-align:right;font-weight:600');
        $valor_liquido->setProperty('style', 'text-align:right;font-weight:600;background:#f8fbff');

        $tipo_baixa->addItems([
            ''                      => 'Recebimento Normal',
            'BAIXA ANTECIPADO BANCO'=> 'Baixa Antecipada Banco',
        ]);
        $tipo_baixa->setValue('');
        $tipo_baixa->setChangeAction(new TAction([__CLASS__, 'onChangeTipoBaixa']));

        $desconto_banco->setValue('0,00');
        $desconto_banco->setExitAction(new TAction([__CLASS__, 'onUpdateDesconto']));
        $valor_liquido->setProperty('readonly', '1');
        $valor_liquido->setValue('0,00');

        $pessoa_id->setMinLength(2);
        $pessoa_id->setMask('{nome} - {cnpj}');
        $numero_crt->setMinLength(0);
        $numero_crt->setMask('{numero}');

        $all_fields = [
            $id, $numero_crt, $fatura_cliente, $pessoa_id, $cliente_cnpj, $cliente_ie, $cliente_endereco,
            $data_emissao, $prazo_dias, $data_vencimento, $taxa, $nota_fiscal, $origem, $destino,
            $remetente, $destinatario, $peso_bruto, $produto, $descricao1, $valor1, $descricao2,
            $valor2, $descricao3, $valor3, $valor_extenso, $valor_fatura, $pagamento,
            $tipo_baixa, $desconto_banco, $valor_liquido, $texto_observacao
        ];
        foreach ($all_fields as $field) {
            if (method_exists($field, 'setSize')) {
                $field->setSize('100%');
            }
        }

        // === ACOES DINAMICAS ===
        $pessoa_id->setChangeAction(new TAction([__CLASS__, 'onSelectCliente']));
        $numero_crt->setChangeAction(new TAction([__CLASS__, 'onExitCRT']));
        $prazo_dias->setExitAction(new TAction([__CLASS__, 'onCalculaVencimento']));

        $valor1->setExitAction(new TAction([__CLASS__, 'onUpdateTotal']));
        $valor2->setExitAction(new TAction([__CLASS__, 'onUpdateTotal']));
        $valor3->setExitAction(new TAction([__CLASS__, 'onUpdateTotal']));

        // === DADOS GERAIS ===
        $this->form->addContent(['<h4 style="margin:0 0 10px">Dados Gerais</h4><hr style="margin-top:0">']);

        $row = $this->form->addFields([new TLabel('ID')], [$id], [new TLabel('CRT')], [$numero_crt]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $this->form->addFields([new TLabel('Fatura Cliente')], [$fatura_cliente]);
        $row->layout = ['col-sm-2', 'col-sm-10'];
        $row = $this->form->addFields([new TLabel('Cliente (*)', 'red')], [$pessoa_id]);
        $row->layout = ['col-sm-2', 'col-sm-10'];
        $row = $this->form->addFields([new TLabel('CNPJ')], [$cliente_cnpj], [new TLabel('IE')], [$cliente_ie]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $this->form->addFields([new TLabel('Endereco')], [$cliente_endereco]);
        $row->layout = ['col-sm-2', 'col-sm-10'];
        $row = $this->form->addFields([new TLabel('Emissao (*)', 'red')], [$data_emissao], [new TLabel('Prazo (Dias)')], [$prazo_dias]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $this->form->addFields([new TLabel('Vencimento')], [$data_vencimento], [new TLabel('Taxa')], [$taxa]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $this->form->addFields([new TLabel('Nota Fiscal')], [$nota_fiscal]);
        $row->layout = ['col-sm-2', 'col-sm-10'];

        $this->form->addContent(['<h4>Dados do CRT</h4><hr>']);
        $row = $this->form->addFields([new TLabel('Origem')], [$origem], [new TLabel('Destino')], [$destino]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $this->form->addFields([new TLabel('Remetente')], [$remetente], [new TLabel('Destinatario')], [$destinatario]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $this->form->addFields([new TLabel('Produto')], [$produto], [new TLabel('Peso Bruto (kg)')], [$peso_bruto]);
        $row->layout = ['col-sm-2', 'col-sm-6', 'col-sm-2', 'col-sm-2'];

        // === VALORES E OBSERVACOES ===
        $this->form->addContent(['<h4 style="margin:18px 0 10px">Valores e Observacoes</h4><hr style="margin-top:0">']);

        $this->form->addContent(['<div class="alert alert-info" style="margin:0 0 10px;padding:8px 12px"><i class="fa fa-info-circle"></i> O valor liquido e calculado automaticamente a partir do desconto bancario.</div>']);

        $this->form->addContent(['<h5><i class="fa fa-list-alt"></i> Itens da Fatura</h5><hr style="margin-top:4px">']);
        $row = $this->form->addFields([new TLabel('Descricao 1')], [$descricao1], [new TLabel('Valor 1')], [$valor1]);
        $row->layout = ['col-sm-1', 'col-sm-7', 'col-sm-1', 'col-sm-3'];
        $row = $this->form->addFields([new TLabel('Descricao 2')], [$descricao2], [new TLabel('Valor 2')], [$valor2]);
        $row->layout = ['col-sm-1', 'col-sm-7', 'col-sm-1', 'col-sm-3'];
        $row = $this->form->addFields([new TLabel('Descricao 3')], [$descricao3], [new TLabel('Valor 3')], [$valor3]);
        $row->layout = ['col-sm-1', 'col-sm-7', 'col-sm-1', 'col-sm-3'];

        $this->form->addContent(['<h5 style="margin-top:14px"><i class="fa fa-calculator"></i> Totais e Observacoes</h5><hr style="margin-top:4px">']);
        $row = $this->form->addFields([new TLabel('Valor por Extenso')], [$valor_extenso]);
        $row->layout = ['col-sm-3', 'col-sm-9'];
        $row = $this->form->addFields([new TLabel('Valor Total Fatura')], [$valor_fatura]);
        $row->layout = ['col-sm-3', 'col-sm-9'];
        $row = $this->form->addFields([new TLabel('Data Pagamento')], [$pagamento]);
        $row->layout = ['col-sm-3', 'col-sm-9'];

        $this->form->addContent(['<hr><h6 style="color:#555;margin:4px 0 8px"><i class="fa fa-university"></i> Recebimento</h6>']);
        $row = $this->form->addFields([new TLabel('Tipo de Baixa')], [$tipo_baixa]);
        $row->layout = ['col-sm-3', 'col-sm-9'];
        $row = $this->form->addFields(
            [new TLabel('Desconto Banco (R$)')], [$desconto_banco],
            [new TLabel('Valor Liquido (R$)')], [$valor_liquido]
        );
        $row->layout = ['col-sm-3', 'col-sm-3', 'col-sm-3', 'col-sm-3'];

        $row = $this->form->addFields([new TLabel('Observacoes')], [$texto_observacao]);
        $row->layout = ['col-sm-3', 'col-sm-9'];

        // === BOTOES ===
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Baixar no Caixa', new TAction([$this, 'onBaixarCaixa']), 'fa:money orange');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addActionLink('Listagem', new TAction(['FaturaList', 'onReload']), 'fa:table blue');

        // === CONTAINER FINAL ===
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', 'FaturaList'));
        $container->add($this->form);

        // CSS personalizado
        TScript::create("
            var s = document.createElement('style');
            s.textContent = '#form_fatura .panel-heading { font-weight: 600; letter-spacing: .2px; } ' +
                '#form_fatura .panel { border-radius: 8px; overflow: hidden; } ' +
                '#form_fatura .tfield, #form_fatura .tcombo { border-radius: 6px; } ' +
                '#form_fatura textarea[name=texto_observacao] { min-height: 72px; resize: vertical; }';
            document.head.appendChild(s);
        ");

        // Calculo automatico client-side: valor_fatura - desconto_banco = valor_liquido
        TScript::create("
            (function() {
                var retries = 0;
                function parseBR(v) {
                    if (!v) return 0;
                    return parseFloat(String(v).replace(/\\./g,'').replace(',','.')) || 0;
                }
                function fmtBR(n) {
                    return n.toFixed(2).replace('.',',').replace(/\\B(?=(\\d{3})+(?!\\d))/g,'.');
                }
                function bindCalc() {
                    var fEl = document.querySelector('[name=valor_fatura]');
                    var dEl = document.querySelector('[name=desconto_banco]');
                    var lEl = document.querySelector('[name=valor_liquido]');
                    if (!fEl || !dEl || !lEl) {
                        retries++;
                        if (retries < 15) setTimeout(bindCalc, 150);
                        return;
                    }
                    if (fEl.dataset.faturaCalcBound === '1') return;
                    var calcLiquido = function() {
                        var liquido = Math.max(0, parseBR(fEl.value) - parseBR(dEl.value));
                        lEl.value = fmtBR(liquido);
                    };
                    ['valor_fatura','desconto_banco'].forEach(function(nm) {
                        var el = document.querySelector('[name=' + nm + ']');
                        if (el) {
                            el.addEventListener('input',  calcLiquido);
                            el.addEventListener('change', calcLiquido);
                            el.addEventListener('blur',   calcLiquido);
                        }
                    });
                    calcLiquido();
                    fEl.dataset.faturaCalcBound = '1';
                }
                bindCalc();
            })();
        ");

        parent::add($container);
    }

    /**
     * Salva o registro
     */
    public function onSave($param)
    {
        try {
            Fatura::ensureSchema();
            TTransaction::open('sample');
            $this->form->validate();
            $fatura = $this->form->getData('Fatura');

            if (empty($fatura->id)) {
                $incomingId = $param['id'] ?? ($param['key'] ?? null);
                if (!empty($incomingId)) {
                    $fatura->id = (int) $incomingId;
                }
            }

            $fatura->tipo_baixa = $param['tipo_baixa'] ?? ($fatura->tipo_baixa ?? null);
            $fatura->texto_observacao = $param['texto_observacao'] ?? ($fatura->texto_observacao ?? null);
            if ($fatura->tipo_baixa === 'Baixa Antecipada Banco') {
                $fatura->tipo_baixa = 'BAIXA ANTECIPADO BANCO';
            }

            if (!empty($fatura->conhecimento_id)) {
                $conhecimento = new Conhecimento($fatura->conhecimento_id);
                if (!empty($conhecimento->id)) {
                    if (strtoupper(trim((string) ($conhecimento->tipo_cobranca ?? 'FRETE_FIXO'))) === 'POR_TONELADA') {
                        throw new Exception('Este CRT esta configurado para cobranca por tonelada. Use a tela de Fatura por Tonelada.');
                    }
                    $fatura->numero_crt = $conhecimento->numero ?? $fatura->numero_crt;
                    $fatura->ORIGEM = $conhecimento->local_emissao ?? $fatura->ORIGEM;
                    $fatura->DESTINO = $conhecimento->local_entrega ?? $fatura->DESTINO;
                    $fatura->REMETENTE = $conhecimento->remetente->nome ?? $fatura->REMETENTE;
                    $fatura->DESTINATARIO = $conhecimento->destinatario->nome ?? $fatura->DESTINATARIO;
                    $fatura->PESO_BRUTO = $conhecimento->peso_bruto_kg ?? $fatura->PESO_BRUTO;
                    $fatura->PRODUTO = $conhecimento->descricao_mercadoria ?? $fatura->PRODUTO;
                }
            }

            if (!empty($fatura->emissao) && !empty($fatura->vencimento)) {
                $emissaoTs = strtotime($fatura->emissao);
                $vencTs = strtotime($fatura->vencimento);
                if ($emissaoTs && $vencTs && $emissaoTs > $vencTs) {
                    throw new Exception('A Data de Emissao nao pode ser maior que a Data de Vencimento.');
                }
            }

            $descontoRaw = $param['desconto_banco'] ?? ($fatura->desconto_banco ?? 0);
            $fatura->desconto_banco = self::toFloat($descontoRaw);
            if (empty($fatura->tipo_baixa)) {
                $fatura->tipo_baixa  = null;
                $fatura->desconto_banco = 0;
            }

            $valor1 = self::toFloat($fatura->valor1 ?? 0);
            $valor2 = self::toFloat($fatura->valor2 ?? 0);
            $valor3 = self::toFloat($fatura->valor3 ?? 0);
            $total = $valor1 + $valor2 + $valor3;

            if ($fatura->valor_fatura === null || $fatura->valor_fatura === '') {
                $fatura->valor_fatura = $total;
            }

            if ($fatura->valor_extenso === null || $fatura->valor_extenso === '') {
                $fatura->valor_extenso = self::toExtenso($total);
            }

            $fatura->store();
            $this->form->setData($fatura);
            TTransaction::close();
            new TMessage('info', 'Fatura salva com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Salva a fatura e envia o valor liquido ao Caixa
     */
    public function onBaixarCaixa($param)
    {
        try {
            Fatura::ensureSchema();
            // Primeiro salva a fatura
            $this->onSave($param);

            TTransaction::open('sample');
            Caixa::createTableIfNotExists();
            $conn = TTransaction::get();

            $fatura = $this->form->getData('Fatura');
            $fatura_id = (int) ($fatura->id ?? 0);
            if ($fatura_id <= 0) {
                throw new Exception('Salve a fatura antes de baixar no Caixa.');
            }

            // Recarrega do banco para pegar dados atualizados
            $fatura = new Fatura($fatura_id);

            $valor_original = (float) ($fatura->valor_fatura ?? 0);
            if ($valor_original <= 0) {
                throw new Exception('Fatura sem valor definido.');
            }

            $is_antecipada = ($fatura->tipo_baixa === 'BAIXA ANTECIPADO BANCO');
            $desconto      = $is_antecipada ? (float) ($fatura->desconto_banco ?? 0) : 0.0;
            $valor_liquido = max(0.0, $valor_original - $desconto);

            $data = !empty($fatura->pagamento)   ? $fatura->pagamento
                  : (!empty($fatura->vencimento) ? $fatura->vencimento
                  : (!empty($fatura->emissao)    ? $fatura->emissao : date('Y-m-d')));

            $cliente = '';
            try { $cliente = $fatura->clientekey->nome ?? ''; } catch (Exception $e) {}

            $num       = $fatura->numero_fatura ?? $fatura->id;
            $descricao = "Fatura #{$num}" . ($cliente ? " - {$cliente}" : '');
            if ($is_antecipada) {
                $descricao .= ' [BAIXA ANTECIPADO BANCO]';
            }

            // Verifica se ja existe no caixa
            $caixa_id = $conn->query(
                "SELECT id FROM caixa WHERE referencia_tipo='fatura' AND referencia_id={$fatura_id}"
            )->fetchColumn();

            $caixa = $caixa_id ? new Caixa($caixa_id) : new Caixa;

            if (!$caixa_id) {
                $caixa->tipo            = 'ENTRADA';
                $caixa->categoria       = 'FATURA';
                $caixa->referencia_id   = $fatura->id;
                $caixa->referencia_tipo = 'fatura';
            }

            $caixa->data_lancamento = $data;
            $caixa->descricao       = $descricao;
            $caixa->valor           = $is_antecipada ? $valor_liquido : $valor_original;
            $caixa->tipo_baixa      = $is_antecipada ? $fatura->tipo_baixa : null;
            $caixa->desconto_banco  = $desconto;
            $caixa->status          = !empty($fatura->pagamento) ? 'CONCILIADO' : 'PENDENTE';
            $caixa->store();

            TTransaction::close();

            $valorFmt = 'R$ ' . number_format($caixa->valor, 2, ',', '.');
            $statusFmt = $caixa->status === 'CONCILIADO' ? 'Conciliado' : 'Pendente';
            $msg = $caixa_id
                ? "Lancamento no Caixa atualizado! Valor: {$valorFmt} ({$statusFmt})"
                : "Fatura baixada no Caixa com sucesso! Valor: {$valorFmt} ({$statusFmt})";

            if ($is_antecipada && $desconto > 0) {
                $descontoFmt = 'R$ ' . number_format($desconto, 2, ',', '.');
                $msg .= "\nDesconto banco: {$descontoFmt}";
            }

            new TMessage('info', $msg);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Limpa o formulario
     */
    public function onClear($param)
    {
        $this->form->clear(true);
    }

    /**
     * Carrega o formulario para edicao
     */
    public function onEdit($param)
    {
        try {
            Fatura::ensureSchema();
            if (isset($param['key'])) {
                TTransaction::open('sample');
                $fatura = new Fatura($param['key']);

                // Calcula valor liquido e injeta no objeto antes do setData
                $valorFat = (float) ($fatura->valor_fatura  ?? 0);
                $desconto = (float) ($fatura->desconto_banco ?? 0);
                $fatura->valor_liquido = max(0, $valorFat - $desconto);

                $this->form->setData($fatura);

                // Envia apenas campos auxiliares (nao-numericos) via sendData
                $extra_data = new stdClass;
                if (!empty($fatura->pessoa_id)) {
                    $cliente = new Clientes($fatura->pessoa_id);
                    $extra_data->cliente_cnpj     = $cliente->cnpj ?? '';
                    $extra_data->cliente_ie        = $cliente->inscricao_estadual ?? '';
                    $extra_data->cliente_endereco  = $cliente->endereco ?? '';
                }
                $extra_data->tipo_baixa = $fatura->tipo_baixa ?? '';
                TForm::sendData(self::$form_name, $extra_data);

                TTransaction::close();
            } elseif (!empty($param['conhecimento_id'])) {
                TTransaction::open('sample');
                $conhecimento = new Conhecimento($param['conhecimento_id']);

                if (strtoupper(trim((string) ($conhecimento->tipo_cobranca ?? 'FRETE_FIXO'))) === 'POR_TONELADA') {
                    TTransaction::close();
                    AdiantiCoreApplication::loadPage('FaturaToneladaForm', 'onEdit', ['conhecimento_id' => $param['conhecimento_id']]);
                    return;
                }

                $fatura = new stdClass;
                $fatura->conhecimento_id = $conhecimento->id;
                $fatura->numero_crt = $conhecimento->numero ?? '';
                $fatura->fatura_cliente = $conhecimento->fatura_crt ?? '';
                $fatura->ORIGEM = $conhecimento->local_emissao ?? '';
                $fatura->DESTINO = $conhecimento->local_entrega ?? '';
                $fatura->REMETENTE = $conhecimento->remetente->nome ?? '';
                $fatura->DESTINATARIO = $conhecimento->destinatario->nome ?? '';
                $fatura->PESO_BRUTO = $conhecimento->peso_bruto_kg ?? '';
                $fatura->PRODUTO = $conhecimento->descricao_mercadoria ?? '';
                $fatura->pessoa_id = $conhecimento->pagador_id ?: $conhecimento->remetente_id;
                $fatura->emissao = date('Y-m-d');

                $this->form->setData($fatura);

                $extra_data = new stdClass;
                if (!empty($fatura->pessoa_id)) {
                    $cliente = new Clientes($fatura->pessoa_id);
                    $extra_data->cliente_cnpj = $cliente->cnpj ?? '';
                    $extra_data->cliente_ie = $cliente->inscricao_estadual ?? '';
                    $extra_data->cliente_endereco = $cliente->endereco ?? '';
                }
                TForm::sendData(self::$form_name, $extra_data);

                TTransaction::close();
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Acao estatica ao selecionar um cliente.
     */
    public static function onSelectCliente($param)
    {
        if (isset($param['pessoa_id']) && !empty($param['pessoa_id'])) {
            try {
                TTransaction::open('sample');
                $cliente = new Clientes($param['pessoa_id']);

                $data_to_send = new stdClass;
                $data_to_send->cliente_cnpj     = $cliente->cnpj;
                $data_to_send->cliente_ie       = $cliente->inscricao_estadual;
                $data_to_send->cliente_endereco = $cliente->endereco;
                TForm::sendData(self::$form_name, $data_to_send);

                TTransaction::close();
            } catch (Exception $e) {
                new TMessage('error', $e->getMessage());
                TTransaction::rollback();
            }
        }
    }

    /**
     * Acao estatica ao sair do campo CRT.
     */
    public static function onExitCRT($param)
    {
        if (isset($param['conhecimento_id']) && !empty($param['conhecimento_id'])) {
            try {
                TTransaction::open('sample');
                $conhecimento = new Conhecimento($param['conhecimento_id']);

                if ($conhecimento) {
                    if (strtoupper(trim((string) ($conhecimento->tipo_cobranca ?? 'FRETE_FIXO'))) === 'POR_TONELADA') {
                        TTransaction::close();
                        new TMessage('info', 'Este CRT esta configurado para cobranca por tonelada. Abrindo a tela especifica.');
                        AdiantiCoreApplication::loadPage('FaturaToneladaForm', 'onEdit', ['conhecimento_id' => $conhecimento->id]);
                        return;
                    }

                    $data_to_send = new stdClass;
                    $data_to_send->ORIGEM       = $conhecimento->local_emissao;
                    $data_to_send->DESTINO      = $conhecimento->local_entrega;
                    $data_to_send->REMETENTE    = $conhecimento->remetente->nome;
                    $data_to_send->DESTINATARIO = $conhecimento->destinatario->nome;
                    $data_to_send->PESO_BRUTO   = $conhecimento->peso_bruto_kg;
                    $data_to_send->PRODUTO      = $conhecimento->descricao_mercadoria;

                    TForm::sendData(self::$form_name, $data_to_send);
                    self::onSelectCliente(['pessoa_id' => $conhecimento->remetente_id]);
                } else {
                    new TMessage('info', 'Nenhum Conhecimento encontrado com este numero.');
                }

                TTransaction::close();
            } catch (Exception $e) {
                new TMessage('error', 'Erro ao buscar CRT: ' . $e->getMessage());
                TTransaction::rollback();
            }
        }
    }

    /**
     * Acao estatica para calcular a data de vencimento a partir do prazo.
     */
    public static function onCalculaVencimento($param)
    {
        if (!empty($param['emissao']) && isset($param['prazo']))
        {
            try
            {
                $data_emissao = TDate::convertToMask($param['emissao'], 'dd/mm/yyyy', 'yyyy-mm-dd');
                $prazo_dias = (int) $param['prazo'];
                $data_vencimento = new DateTime($data_emissao);
                $data_vencimento->add(new DateInterval("P{$prazo_dias}D"));

                $obj = new stdClass;
                $obj->vencimento = $data_vencimento->format('d/m/Y');
                TForm::sendData(self::$form_name, $obj);
            }
            catch(Exception $e)
            {
                new TMessage('error', $e->getMessage());
            }
        }
    }

    /**
     * Acao estatica para atualizar o total e o valor por extenso.
     */
    public static function onUpdateTotal($param)
    {
        $valor1 = isset($param['valor1']) ? floatval(str_replace(',', '.', str_replace('.', '', $param['valor1']))) : 0;
        $valor2 = isset($param['valor2']) ? floatval(str_replace(',', '.', str_replace('.', '', $param['valor2']))) : 0;
        $valor3 = isset($param['valor3']) ? floatval(str_replace(',', '.', str_replace('.', '', $param['valor3']))) : 0;

        $total = $valor1 + $valor2 + $valor3;
        $extenso = self::toExtenso($total);

        $desconto = isset($param['desconto_banco'])
            ? floatval(str_replace(',', '.', str_replace('.', '', $param['desconto_banco'])))
            : 0;

        $data = new stdClass;
        $data->valor_fatura  = number_format($total, 2, ',', '.');
        $data->valor_extenso = $extenso;
        $data->valor_liquido = number_format(max(0, $total - $desconto), 2, ',', '.');

        TForm::sendData(self::$form_name, $data);
    }

    /**
     * Ao mudar tipo de baixa: limpa desconto se voltou para Normal
     */
    public static function onChangeTipoBaixa($param)
    {
        $tipo = $param['tipo_baixa'] ?? '';
        $data = new stdClass;
        if ($tipo !== 'BAIXA ANTECIPADO BANCO') {
            $data->desconto_banco = '0,00';
        }
        $valorFat = isset($param['valor_fatura'])
            ? floatval(str_replace(',', '.', str_replace('.', '', $param['valor_fatura'])))
            : 0;
        $desconto = ($tipo === 'BAIXA ANTECIPADO BANCO' && isset($param['desconto_banco']))
            ? floatval(str_replace(',', '.', str_replace('.', '', $param['desconto_banco'])))
            : 0;
        $data->valor_liquido = number_format(max(0, $valorFat - $desconto), 2, ',', '.');
        TForm::sendData(self::$form_name, $data);
    }

    /**
     * Ao sair do campo desconto: recalcula valor liquido
     */
    public static function onUpdateDesconto($param)
    {
        $valorFat = isset($param['valor_fatura'])
            ? floatval(str_replace(',', '.', str_replace('.', '', $param['valor_fatura'])))
            : 0;
        $desconto = isset($param['desconto_banco'])
            ? floatval(str_replace(',', '.', str_replace('.', '', $param['desconto_banco'])))
            : 0;
        $data = new stdClass;
        $data->valor_liquido = number_format(max(0, $valorFat - $desconto), 2, ',', '.');
        TForm::sendData(self::$form_name, $data);
    }

    private static function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $clean = str_replace('.', '', (string) $value);
        $clean = str_replace(',', '.', $clean);
        return (float) $clean;
    }

    private static function toExtenso(float $valor): string
    {
        if (!self::$spelloutFormatter instanceof NumberFormatter) {
            self::$spelloutFormatter = new NumberFormatter('pt_BR', NumberFormatter::SPELLOUT);
        }
        $reais = floor($valor);
        $centavos = round(($valor - $reais) * 100);
        $extenso = ucfirst(self::$spelloutFormatter->format($reais)) . ' reais';
        if ($centavos > 0) {
            $extenso .= ' e ' . self::$spelloutFormatter->format($centavos) . ' centavos';
        }
        return $extenso;
    }
}
