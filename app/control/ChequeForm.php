<?php
class ChequeForm extends TPage
{
    protected $form;
    private static $form_name = 'form_cheque';

    public function __construct($param = null)
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder(self::$form_name);
        $this->form->setFormTitle('<i class="fa fa-money-check"></i> Emissao de Cheque');

        // Campos
        $id              = new TEntry('id');
        $numero_cheque   = new TEntry('numero_cheque');
        $banco           = new TEntry('banco');
        $recebedor       = new TEntry('recebedor');
        $valor           = new TNumeric('valor', 2, ',', '.');
        $data_emissao    = new TDate('data_emissao');
        $data_vencimento = new TDate('data_vencimento');
        $data_compensacao = new TDate('data_compensacao');
        $status          = new TCombo('status');
        $observacao      = new TText('observacao');

        // Configuracoes
        $id->setEditable(false);
        $numero_cheque->setProperty('placeholder', 'Ex: CH-21');
        $banco->setProperty('placeholder', 'Ex: Banco do Brasil, Itau, Bradesco...');
        $recebedor->setProperty('placeholder', 'Nome de quem recebe o cheque');
        $valor->setProperty('style', 'text-align:right;font-weight:600');
        $observacao->setSize('100%', 60);

        $data_emissao->setMask('dd/mm/yyyy');
        $data_emissao->setDatabaseMask('yyyy-mm-dd');
        $data_vencimento->setMask('dd/mm/yyyy');
        $data_vencimento->setDatabaseMask('yyyy-mm-dd');
        $data_compensacao->setMask('dd/mm/yyyy');
        $data_compensacao->setDatabaseMask('yyyy-mm-dd');

        $status->addItems([
            'PENDENTE'   => 'Pendente (Aguardando Vencimento)',
            'COMPENSADO' => 'Compensado pelo Banco',
            'BAIXADO'    => 'Baixado no Caixa',
            'DEVOLVIDO'  => 'Devolvido',
        ]);
        $status->setValue('PENDENTE');
        $status->setEditable(false);

        $all_fields = [$id, $numero_cheque, $banco, $recebedor,
                        $valor, $data_emissao, $data_vencimento, $data_compensacao,
                        $status, $observacao];
        foreach ($all_fields as $f) {
            if (method_exists($f, 'setSize')) {
                $f->setSize('100%');
            }
        }

        // Layout
        $row = $this->form->addFields([new TLabel('ID')], [$id], [new TLabel('Numero Cheque (*)', 'red')], [$numero_cheque]);
        $row->layout = ['col-sm-1', 'col-sm-2', 'col-sm-2', 'col-sm-7'];

        $row = $this->form->addFields([new TLabel('Banco')], [$banco]);
        $row->layout = ['col-sm-1', 'col-sm-11'];

        $row = $this->form->addFields([new TLabel('Recebedor (*)', 'red')], [$recebedor]);
        $row->layout = ['col-sm-1', 'col-sm-11'];

        $row = $this->form->addFields(
            [new TLabel('Valor (*)', 'red')], [$valor],
            [new TLabel('Emissao')], [$data_emissao],
            [new TLabel('Vencimento (*)', 'red')], [$data_vencimento]
        );
        $row->layout = ['col-sm-1', 'col-sm-3', 'col-sm-1', 'col-sm-3', 'col-sm-1', 'col-sm-3'];

        $row = $this->form->addFields([new TLabel('Compensacao')], [$data_compensacao], [new TLabel('Status')], [$status]);
        $row->layout = ['col-sm-1', 'col-sm-3', 'col-sm-1', 'col-sm-7'];

        $row = $this->form->addFields([new TLabel('Observacao')], [$observacao]);
        $row->layout = ['col-sm-1', 'col-sm-11'];

        // Botoes
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addActionLink('Listagem', new TAction(['ChequeList', 'onReload']), 'fa:table blue');

        // Container
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', 'ChequeList'));
        $container->add($this->form);

        parent::add($container);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');
            Cheque::createTableIfNotExists();

            $this->form->validate();
            $cheque = $this->form->getData('Cheque');

            if (empty($cheque->numero_cheque)) {
                throw new Exception('Informe o numero do cheque.');
            }
            if (empty($cheque->recebedor)) {
                throw new Exception('Informe o recebedor do cheque.');
            }
            if (empty($cheque->data_vencimento)) {
                throw new Exception('Informe a data de vencimento.');
            }

            $cheque->valor = self::parseMoneyValue($cheque->valor);

            if ($cheque->valor <= 0) {
                throw new Exception('Informe um valor valido para o cheque.');
            }

            if (empty($cheque->status)) {
                $cheque->status = 'PENDENTE';
            }

            if (empty($cheque->data_emissao)) {
                $cheque->data_emissao = date('Y-m-d');
            }

            $cheque->store();
            $this->form->setData($cheque);

            TTransaction::close();
            new TMessage('info', 'Cheque salvo com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onEdit($param)
    {
        try {
            if (isset($param['key'])) {
                TTransaction::open('sample');
                Cheque::createTableIfNotExists();
                $cheque = new Cheque($param['key']);
                $this->form->setData($cheque);
                TTransaction::close();
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onClear($param)
    {
        $this->form->clear(true);
    }

    private static function parseMoneyValue($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $value = str_replace(['R$', ' '], '', $value);

        // Formato BR: 7.000,00
        if (strpos($value, ',') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
            return (float) $value;
        }

        // Formato EN: 7000.00
        return (float) $value;
    }
}
