<?php

class FaturaToneladaForm extends TPage
{
    protected $form;
    private static $form_name = 'form_fatura_tonelada';
    private static $spelloutFormatter;

    public function __construct($param = null)
    {
        parent::__construct();
        Conhecimento::ensureSchema();
        FaturaTonelada::ensureSchema();

        $this->form = new BootstrapFormBuilder(self::$form_name);
        $this->form->setFormTitle('<i class="fa fa-weight-hanging"></i> Fatura por Tonelada');
        if (method_exists($this->form, 'setFieldSizes')) {
            $this->form->setFieldSizes('100%');
        }
        if (method_exists($this->form, 'generateAria')) {
            $this->form->generateAria();
        }

        $label = function (string $text, bool $required = false) {
            $mark = $required ? ' (*)' : '';
            return new TLabel($text . $mark);
        };

        $section = function (string $title, string $color = '#3F5E7A') {
            $sectionLabel = new TLabel($title, $color, 12, 'B');
            $sectionLabel->style = 'text-align:left;border-bottom:1px solid #d7dde5;width:100%;margin-top:8px;padding-bottom:4px';
            return $sectionLabel;
        };

        $id               = new TEntry('id');
        $numero_fatura    = new TEntry('numero_fatura');
        $numero_fatura->setProperty('placeholder', 'Ex: 0001/2025-1 (vazio = gerar auto)');
        $conhecimento_id  = new TDBUniqueSearch('conhecimento_id', 'sample', 'Conhecimento', 'id', 'numero');
        $fatura_cliente   = new TEntry('fatura_cliente');
        $pessoa_id        = new TDBUniqueSearch('pessoa_id', 'sample', 'Clientes', 'id', 'nome');

        $cliente_cnpj     = new TEntry('cliente_cnpj');
        $cliente_ie       = new TEntry('cliente_ie');
        $cliente_endereco = new TEntry('cliente_endereco');

        $emissao          = new TDate('emissao');
        $prazo            = new TNumeric('prazo', 0, ',', '.');
        $vencimento       = new TDate('vencimento');
        $taxa             = new TNumeric('taxa', 2, ',', '.');
        $nota_fiscal      = new TEntry('nota_fiscal');

        $origem           = new TEntry('ORIGEM');
        $destino          = new TEntry('DESTINO');
        $remetente        = new TEntry('REMETENTE');
        $destinatario     = new TEntry('DESTINATARIO');
        $peso_bruto       = new TNumeric('PESO_BRUTO', 3, ',', '.');
        $produto          = new TEntry('PRODUTO');

        $toneladas_total        = new TNumeric('toneladas_carga_total', 3, ',', '.');
        $toneladas_faturadas    = new TNumeric('toneladas_faturadas', 3, ',', '.');
        $toneladas_ja_faturadas = new TNumeric('toneladas_ja_faturadas', 3, ',', '.');
        $toneladas_saldo        = new TNumeric('toneladas_saldo', 3, ',', '.');
        $valor_por_ton          = new TNumeric('valor_por_ton', 2, ',', '.');

        $descricao1       = new TEntry('descricao1');
        $valor1           = new TNumeric('valor1', 2, ',', '.');
        $valor_extenso    = new TEntry('valor_extenso');
        $valor_fatura     = new TNumeric('valor_fatura', 2, ',', '.');
        $pagamento        = new TDate('pagamento');
        $tipo_baixa       = new TCombo('tipo_baixa');
        $desconto_banco   = new TNumeric('desconto_banco', 2, ',', '.');
        $valor_liquido    = new TNumeric('valor_liquido', 2, ',', '.');
        $texto_observacao = new TText('texto_observacao');

        $id->setEditable(false);
        $origem->setProperty('readonly', '1');
        $destino->setProperty('readonly', '1');
        $remetente->setProperty('readonly', '1');
        $destinatario->setProperty('readonly', '1');

        $cliente_cnpj->setProperty('readonly', '1');
        $cliente_ie->setProperty('readonly', '1');
        $cliente_endereco->setProperty('readonly', '1');
        $toneladas_total->setProperty('readonly', '1');
        $toneladas_ja_faturadas->setProperty('readonly', '1');
        $toneladas_saldo->setProperty('readonly', '1');
        $descricao1->setProperty('readonly', '1');
        $valor1->setProperty('readonly', '1');
        $valor_extenso->setProperty('readonly', '1');
        $valor_fatura->setProperty('readonly', '1');
        $valor_liquido->setProperty('readonly', '1');

        $emissao->setMask('dd/mm/yyyy');
        $emissao->setDatabaseMask('yyyy-mm-dd');
        $vencimento->setMask('dd/mm/yyyy');
        $vencimento->setDatabaseMask('yyyy-mm-dd');
        $pagamento->setMask('dd/mm/yyyy');
        $pagamento->setDatabaseMask('yyyy-mm-dd');

        $texto_observacao->setSize('100%', 60);
        $fatura_cliente->setProperty('placeholder', 'Numero externo da fatura, se houver');
        $nota_fiscal->setProperty('placeholder', 'Numero/serie da nota fiscal');

        $tipo_baixa->addItems([
            ''                       => 'Recebimento Normal',
            'BAIXA ANTECIPADO BANCO' => 'Baixa Antecipada Banco',
        ]);
        $tipo_baixa->setChangeAction(new TAction([__CLASS__, 'onChangeTipoBaixa']));

        $conhecimento_id->setMinLength(0);
        $conhecimento_id->setMask('{numero}');
        $pessoa_id->setMinLength(2);
        $pessoa_id->setMask('{nome} - {cnpj}');

        foreach ([
            $id, $numero_fatura, $conhecimento_id, $fatura_cliente, $pessoa_id,
            $cliente_cnpj, $cliente_ie, $cliente_endereco,
            $emissao, $vencimento, $taxa, $nota_fiscal,
            $origem, $destino, $remetente, $destinatario, $peso_bruto, $produto,
            $toneladas_total, $toneladas_faturadas, $toneladas_ja_faturadas, $toneladas_saldo,
            $valor_por_ton, $descricao1, $valor1, $valor_extenso, $valor_fatura,
            $pagamento, $tipo_baixa, $desconto_banco, $valor_liquido, $texto_observacao
        ] as $field) {
            if (method_exists($field, 'setSize')) {
                $field->setSize('100%');
            }
        }

        $pessoa_id->setChangeAction(new TAction([__CLASS__, 'onSelectCliente']));
        $conhecimento_id->setChangeAction(new TAction([__CLASS__, 'onExitCRT']));

        $toneladas_faturadas->setExitAction(new TAction([__CLASS__, 'onUpdateTotal']));
        $valor_por_ton->setExitAction(new TAction([__CLASS__, 'onUpdateTotal']));
        $desconto_banco->setExitAction(new TAction([__CLASS__, 'onUpdateDesconto']));

        $desconto_banco->setValue('0,00');
        $valor_liquido->setValue('0,00');

        $this->form->addContent([$section('Dados Gerais')]);

        $row = $this->form->addFields(
            [$label('ID'), $id],
            [$label('Nº Fatura'), $numero_fatura],
            [$label('CRT', true), $conhecimento_id],
            [$label('Fatura Cliente'), $fatura_cliente],
            [$label('Emissao', true), $emissao],
            [$label('Vencimento'), $vencimento]
        );
        $row->layout = ['col-sm-1', 'col-sm-2', 'col-sm-2', 'col-sm-2', 'col-sm-3', 'col-sm-2'];

        $row = $this->form->addFields(
            [$label('Cliente', true), $pessoa_id],
            [$label('Endereco'), $cliente_endereco],
                [$label('CNPJ'), $cliente_cnpj],
            [$label('IE'), $cliente_ie]
          
        
        );
        $row->layout = ['col-sm-4','col-sm-4', 'col-sm-2', 'col-sm-2'];

        $row = $this->form->addFields(
        
               [$label('Nota Fiscal'), $nota_fiscal],
             [$label('Origem'), $origem],
             [$label('Destino'), $destino]
        );
        $row->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];

       
        $row = $this->form->addFields(
            [$label('Remetente'), $remetente],
            [$label('Destinatario'), $destinatario],
            [$label('Produto'), $produto]
        );
        $row->layout = ['col-sm-3', 'col-sm-3', 'col-sm-6'];

        $rowObs = $this->form->addFields([$label('Observacoes'), $texto_observacao]);
        $rowObs->layout = ['col-sm-12'];

        $this->form->addContent([$section('Tonelagem e Valores')]);
        $this->form->addContent(['<div class="alert alert-info" style="margin:0 0 10px;padding:8px 12px">
            <i class="fa fa-info-circle"></i>
            Disponível do CRT: <strong><span id="info-saldo-disponivel">-</span> tons</strong>
            &nbsp;|&nbsp;
            Já embarcado: <strong><span id="info-embarcado">-</span> tons</strong>
            &nbsp;|&nbsp;
            Total do CRT: <strong><span id="info-total-crt">-</span> tons</strong>
        </div>']);

        $hiddenRow = $this->form->addFields(
            [null, $toneladas_total],
            [null, $toneladas_ja_faturadas],
            [null, $toneladas_saldo]
        );
        $hiddenRow->style = 'display:none';

        $row = $this->form->addFields(
            [$label('Ton. a Faturar', true), $toneladas_faturadas],
            [$label('Valor por Ton.', true), $valor_por_ton],
            [$label('Valor Total'), $valor_fatura]
        );
        $row->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];


        $row = $this->form->addFields(
            [$label('Descricao'), $descricao1],
            [$label('Valor Item'), $valor1]
        );
        $row->layout = ['col-sm-8', 'col-sm-4'];

        $row = $this->form->addFields([$label('Valor por Extenso'), $valor_extenso]);
        $row->layout = ['col-sm-12'];

        $this->form->addContent(['<div style="text-align:left;border-bottom:1px solid #d7dde5;width:100%;margin-top:8px;padding-bottom:4px;cursor:pointer;user-select:none" onclick="$(\'[data-sec=recebimento]\').toggle();var i=$(\'#recebimento-icon\');i.toggleClass(\'fa-chevron-down fa-chevron-up\');">
            <span style="color:#3F5E7A;font-size:12px;font-weight:bold">Recebimento</span>
            &nbsp;<i id="recebimento-icon" class="fa fa-chevron-down" style="color:#3F5E7A;font-size:11px"></i>
        </div>']);

        $rowRec1 = $this->form->addFields(
            [$label('Data Pagamento'), $pagamento],
            [$label('Tipo de Baixa'), $tipo_baixa],
            [$label('Desconto Banco (R$)'), $desconto_banco],
            [$label('Valor Liquido (R$)'), $valor_liquido]
        );
        $rowRec1->layout = ['col-sm-3', 'col-sm-3', 'col-sm-3', 'col-sm-3'];
        $rowRec1->{'data-sec'} = 'recebimento';
        $rowRec1->style = 'display:none';


        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addActionLink('Listagem', new TAction(['FaturaToneladaList', 'onReload']), 'fa:table blue');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', 'FaturaToneladaList'));
        $container->add($this->form);

        parent::add($container);
    }

    public function onSave($param)
    {
        try {
            Conhecimento::ensureSchema();
            FaturaTonelada::ensureSchema();

            TTransaction::open('sample');
            $this->form->validate();
            $fatura = $this->form->getData('FaturaTonelada');

            if (empty($fatura->id)) {
                $incomingId = $param['id'] ?? ($param['key'] ?? null);
                if (!empty($incomingId)) {
                    $fatura->id = (int) $incomingId;
                }
            }

            if (empty($fatura->conhecimento_id)) {
                throw new Exception('Selecione o CRT para faturamento.');
            }

            $conhecimento = new Conhecimento($fatura->conhecimento_id);
            self::validateConhecimento($conhecimento);

            $toneladas = self::toFloat($fatura->toneladas_faturadas ?? 0);
            if ($toneladas <= 0) {
                throw new Exception('Informe a quantidade de toneladas a faturar.');
            }

            $resumo = FaturaTonelada::getResumoToneladas((int) $conhecimento->id, !empty($fatura->id) ? (int) $fatura->id : null);
            if (round($toneladas, 3) > round($resumo['saldo'], 3)) {
                throw new Exception('A quantidade informada excede o saldo disponivel do CRT.');
            }

            $valorPorTon = self::toFloat($fatura->valor_por_ton ?? 0);
            if ($valorPorTon <= 0) {
                $valorPorTon = (float) ($conhecimento->valor_por_ton ?? 0);
            }
            if ($valorPorTon <= 0) {
                throw new Exception('O CRT precisa ter um valor por tonelada informado.');
            }

            $total = $toneladas * $valorPorTon;

            if (empty($fatura->pessoa_id)) {
                $fatura->pessoa_id = self::getClienteIdForConhecimento($conhecimento);
            }

            $fatura->numero_crt             = $conhecimento->numero ?? '';
            $fatura->fatura_cliente         = $fatura->fatura_cliente ?: ($conhecimento->fatura_crt ?? '');
            $fatura->ORIGEM                 = $conhecimento->local_responsabilidade ?? '';
            $fatura->DESTINO                = $conhecimento->local_entrega ?? '';
            $fatura->REMETENTE              = $conhecimento->remetente->nome ?? '';
            $fatura->DESTINATARIO           = $conhecimento->destinatario->nome ?? '';
            $fatura->PESO_BRUTO             = $conhecimento->peso_bruto_kg ?? 0;
            $fatura->PRODUTO                = $fatura->PRODUTO ?? '';
            $fatura->toneladas_carga_total  = $resumo['total'];
            $fatura->toneladas_ja_faturadas = $resumo['faturadas'];
            $fatura->toneladas_saldo        = max(0, $resumo['saldo'] - $toneladas);
            $fatura->toneladas_faturadas    = $toneladas;
            $fatura->valor_por_ton          = $valorPorTon;
            $fatura->descricao1             = self::buildDescricaoFaturaTonelada($toneladas, (string) $fatura->fatura_cliente);
            $fatura->valor1                 = $total;
            $fatura->valor2                 = null;
            $fatura->valor3                 = null;
            $fatura->descricao2             = null;
            $fatura->descricao3             = null;
            $fatura->valor_fatura           = $total;
            $fatura->valor_extenso          = self::toExtenso($total);
            $fatura->tipo_baixa             = $param['tipo_baixa'] ?? ($fatura->tipo_baixa ?? null);
            $fatura->texto_observacao       = $param['texto_observacao'] ?? ($fatura->texto_observacao ?? null);

            if ($fatura->tipo_baixa === 'Baixa Antecipada Banco') {
                $fatura->tipo_baixa = 'BAIXA ANTECIPADO BANCO';
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
                $fatura->tipo_baixa = null;
                $fatura->desconto_banco = 0;
            }

            $fatura->store();

            if (empty($fatura->numero_fatura)) {
                $ano = !empty($fatura->emissao)
                    ? date('Y', strtotime($fatura->emissao))
                    : date('Y');

                $stmt = TTransaction::get()->prepare(
                    "SELECT COUNT(*) FROM fatura_tonelada WHERE conhecimento_id = :cid AND id <= :id"
                );
                $stmt->execute([':cid' => (int) $fatura->conhecimento_id, ':id' => (int) $fatura->id]);
                $parcial = (int) $stmt->fetchColumn();

                $fatura->numero_fatura = str_pad((string) $fatura->id, 4, '0', STR_PAD_LEFT)
                    . '/' . $ano
                    . '-' . $parcial;
                $fatura->store();
            }

            $fatura->valor_liquido = max(0, (float) $fatura->valor_fatura - (float) $fatura->desconto_banco);
            $this->form->setData($fatura);
            self::atualizaAlertaTonelagem(
                self::formatNumber((float) $fatura->toneladas_carga_total, 3),
                self::formatNumber((float) $fatura->toneladas_ja_faturadas, 3),
                self::formatNumber((float) $fatura->toneladas_saldo, 3)
            );

            TTransaction::close();
            new TMessage('info', 'Fatura por tonelada salva com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public function onBaixarCaixa($param)
    {
        try {
            $this->onSave($param);

            TTransaction::open('sample');
            Caixa::createTableIfNotExists();
            $conn = TTransaction::get();

            $fatura = $this->form->getData('FaturaTonelada');
            $fatura_id = (int) ($fatura->id ?? 0);
            if ($fatura_id <= 0) {
                throw new Exception('Salve a fatura antes de baixar no Caixa.');
            }

            $fatura = new FaturaTonelada($fatura_id);
            $valor_original = (float) ($fatura->valor_fatura ?? 0);
            if ($valor_original <= 0) {
                throw new Exception('Fatura sem valor definido.');
            }

            $is_antecipada = ($fatura->tipo_baixa === 'BAIXA ANTECIPADO BANCO');
            $desconto = $is_antecipada ? (float) ($fatura->desconto_banco ?? 0) : 0.0;
            $valor_liquido = max(0.0, $valor_original - $desconto);

            $data = !empty($fatura->pagamento) ? $fatura->pagamento
                  : (!empty($fatura->vencimento) ? $fatura->vencimento
                  : (!empty($fatura->emissao) ? $fatura->emissao : date('Y-m-d')));

            $cliente = '';
            try {
                $cliente = $fatura->clientekey->nome ?? '';
            } catch (Exception $e) {
            }

            $num = $fatura->numero_fatura ?? $fatura->id;
            $descricao = "Fatura Tonelada #{$num}" . ($cliente ? " - {$cliente}" : '');

            $caixa_id = $conn->query(
                "SELECT id FROM caixa WHERE referencia_tipo='fatura_tonelada' AND referencia_id={$fatura_id}"
            )->fetchColumn();

            $caixa = $caixa_id ? new Caixa($caixa_id) : new Caixa;
            if (!$caixa_id) {
                $caixa->tipo = 'ENTRADA';
                $caixa->categoria = 'FATURA';
                $caixa->referencia_id = $fatura->id;
                $caixa->referencia_tipo = 'fatura_tonelada';
            }

            $caixa->data_lancamento = $data;
            $caixa->descricao = $descricao;
            $caixa->valor = $is_antecipada ? $valor_liquido : $valor_original;
            $caixa->tipo_baixa = $is_antecipada ? $fatura->tipo_baixa : null;
            $caixa->desconto_banco = $desconto;
            $caixa->status = !empty($fatura->pagamento) ? 'CONCILIADO' : 'PENDENTE';
            $caixa->store();

            TTransaction::close();
            new TMessage('info', 'Lancamento da fatura por tonelada enviado ao Caixa.');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public function onClear($param)
    {
        $this->form->clear(true);
        $data = new stdClass;
        $data->emissao = date('d/m/Y');
        $this->form->setData($data);
    }

    public function onEdit($param)
    {
        try {
            FaturaTonelada::ensureSchema();
            Conhecimento::ensureSchema();

            if (!empty($param['key'])) {
                TTransaction::open('sample');
                $fatura = new FaturaTonelada($param['key']);
                $conhecimento = new Conhecimento($fatura->conhecimento_id);
                self::validateConhecimento($conhecimento);

                $resumo = FaturaTonelada::getResumoToneladas((int) $conhecimento->id, (int) $fatura->id);
                $fatura->toneladas_carga_total = self::formatNumber($resumo['total'], 3);
                $fatura->toneladas_ja_faturadas = self::formatNumber($resumo['faturadas'], 3);
                $fatura->toneladas_saldo = self::formatNumber($resumo['saldo'], 3);
                $fatura->toneladas_faturadas = self::formatNumber((float) $fatura->toneladas_faturadas, 3);
                $fatura->valor_por_ton = self::formatNumber((float) $fatura->valor_por_ton, 2);
                $fatura->valor1 = self::formatNumber((float) $fatura->valor1, 2);
                $fatura->valor_fatura = self::formatNumber((float) $fatura->valor_fatura, 2);
                $fatura->desconto_banco = self::formatNumber((float) ($fatura->desconto_banco ?? 0), 2);
                $fatura->valor_liquido = self::formatNumber(max(0, (float) $fatura->valor_fatura - (float) ($fatura->desconto_banco ?? 0)), 2);

                $this->form->setData($fatura);
                self::atualizaAlertaTonelagem(
                    (string) ($fatura->toneladas_carga_total ?? '-'),
                    (string) ($fatura->toneladas_ja_faturadas ?? '-'),
                    (string) ($fatura->toneladas_saldo ?? '-')
                );

                $extra = new stdClass;
                if (!empty($fatura->pessoa_id)) {
                    $cliente = new Clientes($fatura->pessoa_id);
                    $extra->cliente_cnpj = $cliente->cnpj ?? '';
                    $extra->cliente_ie = $cliente->inscricao_estadual ?? '';
                    $extra->cliente_endereco = $cliente->endereco ?? '';
                }
                TForm::sendData(self::$form_name, $extra);
                TTransaction::close();
            } elseif (!empty($param['conhecimento_id'])) {
                TTransaction::open('sample');
                $conhecimento = new Conhecimento($param['conhecimento_id']);
                self::validateConhecimento($conhecimento);
                $draft = self::buildDraftFromConhecimento($conhecimento);
                $this->form->setData($draft);
                self::atualizaAlertaTonelagem(
                    (string) ($draft->toneladas_carga_total ?? '-'),
                    (string) ($draft->toneladas_ja_faturadas ?? '-'),
                    (string) ($draft->toneladas_saldo ?? '-')
                );

                $extra = new stdClass;
                if (!empty($draft->pessoa_id)) {
                    $cliente = new Clientes($draft->pessoa_id);
                    $extra->cliente_cnpj = $cliente->cnpj ?? '';
                    $extra->cliente_ie = $cliente->inscricao_estadual ?? '';
                    $extra->cliente_endereco = $cliente->endereco ?? '';
                }
                TForm::sendData(self::$form_name, $extra);
                TTransaction::close();
            } else {
                $data = new stdClass;
                $data->emissao = date('d/m/Y');
                $this->form->setData($data);
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public static function onSelectCliente($param)
    {
        if (isset($param['pessoa_id']) && !empty($param['pessoa_id'])) {
            try {
                TTransaction::open('sample');
                $cliente = new Clientes($param['pessoa_id']);

                $data = new stdClass;
                $data->cliente_cnpj = $cliente->cnpj ?? '';
                $data->cliente_ie = $cliente->inscricao_estadual ?? '';
                $data->cliente_endereco = $cliente->endereco ?? '';
                TForm::sendData(self::$form_name, $data);

                TTransaction::close();
            } catch (Exception $e) {
                new TMessage('error', $e->getMessage());
                if (TTransaction::get()) {
                    TTransaction::rollback();
                }
            }
        }
    }

    public static function onExitCRT($param)
    {
        if (!empty($param['conhecimento_id'])) {
            try {
                TTransaction::open('sample');
                $conhecimento = new Conhecimento($param['conhecimento_id']);
                self::validateConhecimento($conhecimento);

                $draft = self::buildDraftFromConhecimento($conhecimento);
                TForm::sendData(self::$form_name, $draft);
                self::atualizaAlertaTonelagem(
                    (string) ($draft->toneladas_carga_total ?? '-'),
                    (string) ($draft->toneladas_ja_faturadas ?? '-'),
                    (string) ($draft->toneladas_saldo ?? '-')
                );
                self::onSelectCliente(['pessoa_id' => $draft->pessoa_id ?? null]);
                TTransaction::close();
            } catch (Exception $e) {
                new TMessage('error', $e->getMessage());
                if (TTransaction::get()) {
                    TTransaction::rollback();
                }
            }
        }
    }

    public static function onCalculaVencimento($param)
    {
        if (!empty($param['emissao']) && isset($param['prazo'])) {
            try {
                $data_emissao = TDate::convertToMask($param['emissao'], 'dd/mm/yyyy', 'yyyy-mm-dd');
                $prazo_dias = (int) $param['prazo'];
                $data_vencimento = new DateTime($data_emissao);
                $data_vencimento->add(new DateInterval("P{$prazo_dias}D"));

                $obj = new stdClass;
                $obj->vencimento = $data_vencimento->format('d/m/Y');
                TForm::sendData(self::$form_name, $obj);
            } catch (Exception $e) {
                new TMessage('error', $e->getMessage());
            }
        }
    }

    public static function onUpdateTotal($param)
    {
        $toneladas = self::toFloat($param['toneladas_faturadas'] ?? 0);
        $valorPorTon = self::toFloat($param['valor_por_ton'] ?? 0);
        $total = $toneladas * $valorPorTon;
        $desconto = self::toFloat($param['desconto_banco'] ?? 0);

        $data = new stdClass;
        $data->descricao1 = $toneladas > 0
            ? self::buildDescricaoFaturaTonelada($toneladas, (string) ($param['fatura_cliente'] ?? ''))
            : '';
        $data->valor1 = self::formatNumber($total, 2);
        $data->valor_fatura = self::formatNumber($total, 2);
        $data->valor_extenso = $total > 0 ? self::toExtenso($total) : '';
        $data->valor_liquido = self::formatNumber(max(0, $total - $desconto), 2);

        TForm::sendData(self::$form_name, $data);
    }

    public static function onChangeTipoBaixa($param)
    {
        $tipo = $param['tipo_baixa'] ?? '';
        $desconto = ($tipo === 'BAIXA ANTECIPADO BANCO') ? self::toFloat($param['desconto_banco'] ?? 0) : 0;
        $valorFat = self::toFloat($param['valor_fatura'] ?? 0);

        $data = new stdClass;
        if ($tipo !== 'BAIXA ANTECIPADO BANCO') {
            $data->desconto_banco = '0,00';
        }
        $data->valor_liquido = self::formatNumber(max(0, $valorFat - $desconto), 2);
        TForm::sendData(self::$form_name, $data);
    }

    public static function onUpdateDesconto($param)
    {
        $valorFat = self::toFloat($param['valor_fatura'] ?? 0);
        $desconto = self::toFloat($param['desconto_banco'] ?? 0);

        $data = new stdClass;
        $data->valor_liquido = self::formatNumber(max(0, $valorFat - $desconto), 2);
        TForm::sendData(self::$form_name, $data);
    }

    private static function validateConhecimento(Conhecimento $conhecimento): void
    {
        if (empty($conhecimento->id)) {
            throw new Exception('CRT nao encontrado.');
        }

        $tipo = strtoupper(trim((string) ($conhecimento->tipo_cobranca ?? '')));
        if ($tipo !== 'POR_TONELADA') {
            throw new Exception('Este CRT nao esta configurado para cobranca por tonelada.');
        }
    }

    private static function getClienteIdForConhecimento(Conhecimento $conhecimento): ?int
    {
        if (!empty($conhecimento->pagador_id)) {
            return (int) $conhecimento->pagador_id;
        }

        if (!empty($conhecimento->remetente_id)) {
            return (int) $conhecimento->remetente_id;
        }

        return null;
    }

    private static function buildDraftFromConhecimento(Conhecimento $conhecimento, ?int $ignoreId = null): stdClass
    {
        $resumo = FaturaTonelada::getResumoToneladas((int) $conhecimento->id, $ignoreId);

        $data = new stdClass;
        $data->conhecimento_id = $conhecimento->id;
        $data->numero_crt = $conhecimento->numero ?? '';
        $data->fatura_cliente = $conhecimento->fatura_crt ?? '';
        $data->pessoa_id = self::getClienteIdForConhecimento($conhecimento);
        $data->emissao = date('d/m/Y');
        $data->ORIGEM = $conhecimento->local_responsabilidade ?? '';
        $data->DESTINO = $conhecimento->local_entrega ?? '';
        $data->REMETENTE = $conhecimento->remetente->nome ?? '';
        $data->DESTINATARIO = $conhecimento->destinatario->nome ?? '';
        $data->PESO_BRUTO = self::formatNumber((float) ($conhecimento->peso_bruto_kg ?? 0), 3);
        $data->PRODUTO = '';
        $data->toneladas_carga_total = self::formatNumber((float) $resumo['total'], 3);
        $data->toneladas_ja_faturadas = self::formatNumber((float) $resumo['faturadas'], 3);
        $data->toneladas_saldo = self::formatNumber((float) $resumo['saldo'], 3);
        $data->valor_por_ton = self::formatNumber((float) $resumo['valor_por_ton'], 2);
        $data->desconto_banco = '0,00';
        $data->valor_liquido = '0,00';

        return $data;
    }

    private static function atualizaAlertaTonelagem(string $total, string $embarcado, string $saldo): void
    {
        TScript::create(
            '$("#info-total-crt").text(' . json_encode($total) . ');' .
            '$("#info-embarcado").text(' . json_encode($embarcado) . ');' .
            '$("#info-saldo-disponivel").text(' . json_encode($saldo) . ');'
        );
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

    private static function formatNumber(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', '.');
    }

    private static function buildDescricaoFaturaTonelada(float $toneladas, string $faturaCliente = ''): string
    {
        $descricao = sprintf('Frete referente a %s ton transportadas', self::formatNumber($toneladas, 3));
        $faturaCliente = trim((string) preg_replace('/\s+/', ' ', $faturaCliente));

        if ($faturaCliente === '') {
            return $descricao;
        }

        $faturaCliente = trim((string) preg_replace('/^(n[ºo°.]?\s*)?fatura(\s+externa)?\s*/iu', '', $faturaCliente));

        return $faturaCliente !== ''
            ? $descricao . ' da fatura FATURA ' . $faturaCliente
            : $descricao;
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
