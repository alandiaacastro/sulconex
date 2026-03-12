<?php
/**
 * CaixaForm - Formulário de lançamento manual no caixa
 */
class CaixaForm extends TPage
{
    protected $form;
    private static $form_name = 'form_caixa';

    public function __construct($param = null)
    {
        parent::__construct();

        Caixa::createTableIfNotExists();

        $this->form = new TForm(self::$form_name);

        // Campos
        $id         = new TEntry('id');
        $data       = new TDate('data_lancamento');
        $descricao  = new TEntry('descricao');
        $tipo       = new TCombo('tipo');
        $valor      = new TNumeric('valor', 2, ',', '.');
        $categoria  = new TCombo('categoria');
        $status     = new TCombo('status');
        $observacao = new TText('observacao');

        // Configurações
        $id->setEditable(false);

        $data->setMask('dd/mm/yyyy');
        $data->setDatabaseMask('yyyy-mm-dd');

        $tipo->addItems([
            'ENTRADA' => 'ENTRADA (Recebimento)',
            'SAIDA'   => 'SAÍDA (Pagamento)',
        ]);

        $categoria->addItems([
            'MANUAL'   => 'Manual',
            'FATURA'   => 'Fatura (A Receber)',
            'CONTRATO' => 'Contrato/Carta Frete (A Pagar)',
            'EXTRATO'  => 'Extrato Bancário',
        ]);

        $status->addItems([
            'PENDENTE'    => 'Pendente',
            'CONCILIADO'  => 'Conciliado',
        ]);

        $descricao->setSize('100%');
        $observacao->setSize('100%', 60);

        // Validações
        $data->addValidation('Data Lançamento', new TRequiredValidator);
        $descricao->addValidation('Descrição', new TRequiredValidator);
        $tipo->addValidation('Tipo', new TRequiredValidator);
        $valor->addValidation('Valor', new TRequiredValidator);

        // Layout
        $form_builder = new BootstrapFormBuilder('form_caixa_inner');
        $form_builder->setFieldSizes('100%');
        $form_builder->setFormTitle('Lançamento no Caixa');

        $form_builder->addFields([new TLabel('ID')], [$id], [new TLabel('Data (*)', 'red')], [$data]);
        $form_builder->addFields([new TLabel('Tipo (*)', 'red')], [$tipo], [new TLabel('Categoria')], [$categoria]);
        $form_builder->addFields([new TLabel('Descrição (*)', 'red')], [$descricao]);
        $form_builder->addFields([new TLabel('Valor R$ (*)', 'red')], [$valor], [new TLabel('Status')], [$status]);
        $form_builder->addFields([new TLabel('Observação')], [$observacao]);

        // Botões
        $btn_save  = TButton::create('save',  [$this, 'onSave'],  'Salvar',   'fa:save green');
        $btn_clear = TButton::create('clear', [$this, 'onClear'], 'Limpar',   'fa:eraser red');
        $btn_list  = new TActionLink('Listagem', new TAction(['CaixaList', 'onReload']), null, null, null, 'fa:table blue');
        $btn_list->class = 'btn btn-default';

        $buttons_box = new THBox;
        $buttons_box->add($btn_save);
        $buttons_box->add($btn_clear);
        $buttons_box->add($btn_list);

        $panel = new TPanelGroup('Caixa Financeiro - Lançamento');
        $panel->add($form_builder);
        $panel->addFooter($buttons_box);
        $this->form->add($panel);

        $this->form->setFields([
            $id, $data, $descricao, $tipo, $valor, $categoria, $status, $observacao,
            $btn_save, $btn_clear
        ]);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', 'CaixaList'));
        $container->add($this->form);
        parent::add($container);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            $this->form->validate();
            $caixa = $this->form->getData('Caixa');

            $valor_num = (float) str_replace(',', '.', str_replace('.', '', $caixa->valor));
            if ($valor_num <= 0) {
                throw new Exception('O valor deve ser maior que zero.');
            }

            if (empty($caixa->categoria)) {
                $caixa->categoria = 'MANUAL';
            }
            if (empty($caixa->status)) {
                $caixa->status = 'PENDENTE';
            }

            $caixa->store();
            $this->form->setData($caixa);
            TTransaction::close();
            new TMessage('info', 'Lançamento salvo com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onClear($param)
    {
        $this->form->clear(true);
    }

    public function onEdit($param)
    {
        try {
            if (isset($param['key'])) {
                TTransaction::open('sample');
                $caixa = new Caixa($param['key']);
                $this->form->setData($caixa);
                TTransaction::close();
            } else {
                $this->form->clear(true);
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
