<?php
class FaturaForm extends TPage
{
    protected $form;
    private static $form_name = 'form_fatura';

    /**
     * Constructor method
     */
    public function __construct($param = null)
    {
        parent::__construct();

        // Cria o formulario principal
        $this->form = new TForm(self::$form_name);

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

        $pessoa_id->setMinLength(0);
        $pessoa_id->setMask('{nome}');
        $numero_crt->setMinLength(0);
        $numero_crt->setMask('{numero}');

        $all_fields = [
            $id, $numero_crt, $fatura_cliente, $pessoa_id, $cliente_cnpj, $cliente_ie, $cliente_endereco,
            $data_emissao, $prazo_dias, $data_vencimento, $taxa, $nota_fiscal, $origem, $destino,
            $remetente, $destinatario, $peso_bruto, $produto, $descricao1, $valor1, $descricao2,
            $valor2, $descricao3, $valor3, $valor_extenso, $valor_fatura, $pagamento, $texto_observacao
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

        // === CRIACAO DE ABAS (NOTEBOOK) ===
        $notebook = new TNotebook;

        // --- ABA 1: DADOS GERAIS ---
        $page1_vbox = new TVBox;
        $page1_vbox->style = 'width: 100%';
        $notebook->appendPage('Dados Gerais', $page1_vbox);
        $form_gerais = new BootstrapFormBuilder('form_gerais_interno');
        $form_gerais->setFieldSizes('100%');
        $page1_vbox->add($form_gerais);

        $row = $form_gerais->addFields([new TLabel('ID')], [$id], [new TLabel('CRT')], [$numero_crt]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $form_gerais->addFields([new TLabel('Fatura Cliente')], [$fatura_cliente]);
        $row->layout = ['col-sm-2', 'col-sm-10'];
        $row = $form_gerais->addFields([new TLabel('Cliente (*)', 'red')], [$pessoa_id]);
        $row->layout = ['col-sm-2', 'col-sm-10'];
        $row = $form_gerais->addFields([new TLabel('CNPJ')], [$cliente_cnpj], [new TLabel('IE')], [$cliente_ie]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $form_gerais->addFields([new TLabel('Endereco')], [$cliente_endereco]);
        $row->layout = ['col-sm-2', 'col-sm-10'];
        $row = $form_gerais->addFields([new TLabel('Emissao (*)', 'red')], [$data_emissao], [new TLabel('Prazo (Dias)')], [$prazo_dias]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $form_gerais->addFields([new TLabel('Vencimento')], [$data_vencimento], [new TLabel('Taxa')], [$taxa]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $form_gerais->addFields([new TLabel('Nota Fiscal')], [$nota_fiscal]);
        $row->layout = ['col-sm-2', 'col-sm-10'];
        $form_gerais->addContent(['<h4>Dados do CRT</h4><hr>']);
        $row = $form_gerais->addFields([new TLabel('Origem')], [$origem], [new TLabel('Destino')], [$destino]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $form_gerais->addFields([new TLabel('Remetente')], [$remetente], [new TLabel('Destinatario')], [$destinatario]);
        $row->layout = ['col-sm-2', 'col-sm-4', 'col-sm-2', 'col-sm-4'];
        $row = $form_gerais->addFields([new TLabel('Produto')], [$produto], [new TLabel('Peso Bruto')], [$peso_bruto]);
        $row->layout = ['col-sm-6', 'col-sm-3', 'col-sm-3', 'col-sm-0'];

        // --- ABA 2: VALORES E OBSERVACOES ---
        $page2_vbox = new TVBox;
        $page2_vbox->style = 'width: 100%';
        $notebook->appendPage('Valores e Observacoes', $page2_vbox);

        $panel_itens = new TPanelGroup('Itens da Fatura');
        $table_itens = new TTable;
        $table_itens->style = 'width:100%';
        $table_itens->addSection('thead');
        $row_head = $table_itens->addRow();
        $row_head->addCell(new TLabel('<b>Descricao</b>'))->style = 'width:75%';
        $row_head->addCell(new TLabel('<b>Valor</b>'))->style = 'width:25%';
        $table_itens->addRowSet($descricao1, $valor1);
        $table_itens->addRowSet($descricao2, $valor2);
        $table_itens->addRowSet($descricao3, $valor3);
        $panel_itens->add($table_itens);
        $page2_vbox->add($panel_itens);

        $panel_totais = new TPanelGroup('Totais e Observacoes');
        $form_totais = new BootstrapFormBuilder('form_totais_interno');
        $form_totais->setFieldSizes('100%');
        $row = $form_totais->addFields([new TLabel('Valor por Extenso')], [$valor_extenso]);
        $row->layout = ['col-sm-3', 'col-sm-9'];
        $row = $form_totais->addFields([new TLabel('Valor Total Fatura')], [$valor_fatura]);
        $row->layout = ['col-sm-3', 'col-sm-9'];
        $row = $form_totais->addFields([new TLabel('Data Pagamento')], [$pagamento]);
        $row->layout = ['col-sm-3', 'col-sm-9'];
        $row = $form_totais->addFields([new TLabel('Observacoes')], [$texto_observacao]);
        $row->layout = ['col-sm-3', 'col-sm-9'];
        $panel_totais->add($form_totais);
        $page2_vbox->add($panel_totais);

        // === BOTOES ===
        $btn_save  = TButton::create('save', [$this, 'onSave'], 'Salvar', 'fa:save green');
        $btn_clear = TButton::create('clear', [$this, 'onClear'], 'Limpar', 'fa:eraser red');
        $btn_list  = new TActionLink('Listagem', new TAction(['FaturaList', 'onReload']), null, null, null, 'fa:table blue');
        $btn_list->class = 'btn btn-default';

        $buttons_box = new THBox;
        $buttons_box->add($btn_save);
        $buttons_box->add($btn_clear);
        $buttons_box->add($btn_list);

        // === PAINEL PRINCIPAL ===
        $panel_main = new TPanelGroup('Cadastro de Fatura');
        $panel_main->add($notebook);
        $panel_main->addFooter($buttons_box);
        $this->form->add($panel_main);

        // Registra todos os campos no formulario principal
        $this->form->setFields([
            $id, $numero_crt, $fatura_cliente, $pessoa_id, $cliente_cnpj, $cliente_ie, $cliente_endereco,
            $data_emissao, $prazo_dias, $data_vencimento, $taxa, $nota_fiscal,
            $origem, $destino, $remetente, $destinatario, $peso_bruto, $produto,
            $descricao1, $valor1, $descricao2, $valor2, $descricao3, $valor3,
            $valor_extenso, $valor_fatura, $pagamento, $texto_observacao,
            $btn_save, $btn_clear
        ]);

        // === CONTAINER FINAL ===
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', 'FaturaList'));

        $container->add($this->form);
        parent::add($container);
    }

    /**
     * Salva o registro
     */
    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            $this->form->validate();
            $fatura = $this->form->getData('Fatura');

            if (!empty($fatura->conhecimento_id)) {
                $conhecimento = new Conhecimento($fatura->conhecimento_id);
                if (!empty($conhecimento->id)) {
                    $fatura->numero_crt = $conhecimento->numero ?? $fatura->numero_crt;
                    $fatura->ORIGEM = $conhecimento->local_emissao ?? $fatura->ORIGEM;
                    $fatura->DESTINO = $conhecimento->local_entrega ?? $fatura->DESTINO;
                    $fatura->REMETENTE = $conhecimento->remetente->nome ?? $fatura->REMETENTE;
                    $fatura->DESTINATARIO = $conhecimento->destinatario->nome ?? $fatura->DESTINATARIO;
                    $fatura->PESO_BRUTO = $conhecimento->peso_bruto_kg ?? $fatura->PESO_BRUTO;
                    $fatura->PRODUTO = $conhecimento->prod ?? $fatura->PRODUTO;
                }
            }

            if (!empty($fatura->emissao) && !empty($fatura->vencimento)) {
                $emissaoTs = strtotime($fatura->emissao);
                $vencTs = strtotime($fatura->vencimento);
                if ($emissaoTs && $vencTs && $emissaoTs > $vencTs) {
                    throw new Exception('A Data de Emissao nao pode ser maior que a Data de Vencimento.');
                }
            }

            $valor1 = self::toFloat($fatura->valor1 ?? 0);
            $valor2 = self::toFloat($fatura->valor2 ?? 0);
            $valor3 = self::toFloat($fatura->valor3 ?? 0);
            $total = $valor1 + $valor2 + $valor3;

            if ($fatura->valor_fatura === null || $fatura->valor_fatura === '') {
                $fatura->valor_fatura = $total;
            }

            if ($fatura->valor_extenso === null || $fatura->valor_extenso === '') {
                $formatter = new NumberFormatter('pt_BR', NumberFormatter::SPELLOUT);
                $reais = floor($total);
                $centavos = round(($total - $reais) * 100);
                $extenso = ucfirst($formatter->format($reais)) . ' reais';
                if ($centavos > 0) {
                    $extenso .= ' e ' . $formatter->format($centavos) . ' centavos';
                }
                $fatura->valor_extenso = $extenso;
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
            if (isset($param['key'])) {
                TTransaction::open('sample');
                $fatura = new Fatura($param['key']);
                $this->form->setData($fatura);

                if (!empty($fatura->pessoa_id)) {
                    $cliente = new Clientes($fatura->pessoa_id);
                    $data_to_send = new stdClass;
                    $data_to_send->cliente_cnpj = $cliente->cnpj ?? '';
                    $data_to_send->cliente_ie = $cliente->inscricao_estadual ?? '';
                    $data_to_send->cliente_endereco = $cliente->endereco ?? '';
                    TForm::sendData(self::$form_name, $data_to_send);
                }
                TTransaction::close();
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Acao estatica ao selecionar um cliente.
     * Preenche os dados de CNPJ, IE e Endereco.
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
     * Busca os dados do conhecimento e preenche o formulario.
     */
    public static function onExitCRT($param)
    {
        if (isset($param['conhecimento_id']) && !empty($param['conhecimento_id'])) {
            try {
                TTransaction::open('sample');
                $conhecimento = new Conhecimento($param['conhecimento_id']);

                if ($conhecimento) {
                    $data_to_send = new stdClass;
                    $data_to_send->ORIGEM       = $conhecimento->local_emissao;
                    $data_to_send->DESTINO      = $conhecimento->local_entrega;
                    $data_to_send->REMETENTE    = $conhecimento->remetente->nome;
                    $data_to_send->DESTINATARIO = $conhecimento->destinatario->nome;
                    $data_to_send->PESO_BRUTO   = $conhecimento->peso_bruto_kg;
                    $data_to_send->PRODUTO      = $conhecimento->prod;

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

        $formatter = new NumberFormatter('pt_BR', NumberFormatter::SPELLOUT);
        $reais = floor($total);
        $centavos = round(($total - $reais) * 100);

        $extenso = ucfirst($formatter->format($reais)) . ' reais';
        if ($centavos > 0) {
            $extenso .= ' e ' . $formatter->format($centavos) . ' centavos';
        }

        $data = new stdClass;
        $data->valor_fatura = number_format($total, 2, ',', '.');
        $data->valor_extenso = $extenso;

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
}



