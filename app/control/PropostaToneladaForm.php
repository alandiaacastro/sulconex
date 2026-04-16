<?php

class PropostaToneladaForm extends TPage
{
    protected $form;
    private static $database = 'sample';
    private static $formName = 'form_PropostaTonelada';

    public function __construct($param = null)
    {
        parent::__construct();
        Conhecimento::ensureSchema();
        PropostaTonelada::ensureSchema();

        $this->form = new BootstrapFormBuilder(self::$formName);
        $this->form->setFormTitle('Proposta por Tonelada');

        $id = new THidden('id');
        $numero_proposta = new TEntry('numero_proposta');
        $cliente_id = new TDBUniqueSearch('cliente_id', self::$database, 'Clientes', 'id', 'nome');
        $data_proposta = new TDate('data_proposta');
        $validade = new TDate('validade');
        $status = new TCombo('status');
        $origem = new TEntry('origem');
        $fronteira = new TEntry('fronteira');
        $destino = new TEntry('destino');
        $tipo_veiculo = new TEntry('tipo_veiculo');
        $descricao_mercadoria = new TEntry('descricao_mercadoria');
        $toneladas = new TNumeric('toneladas', 3, ',', '.');
        $valor_frete_base = new TNumeric('valor_frete_base', 2, ',', '.');
        $valor_por_ton = new TNumeric('valor_por_ton', 2, ',', '.');
        $valor_total = new TNumeric('valor_total', 2, ',', '.');
        $observacoes = new TText('observacoes');

        $numero_proposta->setEditable(false);
        $valor_total->setEditable(false);

        $cliente_id->setMinLength(0);
        $cliente_id->setMask('{nome}');

        $data_proposta->setMask('dd/mm/yyyy');
        $data_proposta->setDatabaseMask('yyyy-mm-dd');
        $validade->setMask('dd/mm/yyyy');
        $validade->setDatabaseMask('yyyy-mm-dd');

        $status->addItems([
            'Em Analise' => 'Em Analise',
            'Aprovada' => 'Aprovada',
            'Rejeitada' => 'Rejeitada',
        ]);

        foreach ([
            $numero_proposta, $cliente_id, $data_proposta, $validade, $status,
            $origem, $fronteira, $destino, $tipo_veiculo, $descricao_mercadoria,
            $toneladas, $valor_frete_base, $valor_por_ton, $valor_total, $observacoes
        ] as $field) {
            if (method_exists($field, 'setSize')) {
                $field->setSize('100%');
            }
        }

        $observacoes->setSize('100%', 80);
        $toneladas->setExitAction(new TAction([__CLASS__, 'onUpdateTotal']));
        $valor_frete_base->setExitAction(new TAction([__CLASS__, 'onUpdateTotal']));
        $valor_por_ton->setExitAction(new TAction([__CLASS__, 'onUpdateTotal']));

        $this->form->addFields([$id]);

        $row = $this->form->addFields(
            [new TLabel('Numero')], [$numero_proposta],
            [new TLabel('Status')], [$status]
        );
        $row->layout = ['col-sm-4', 'col-sm-2', 'col-sm-2', 'col-sm-4'];

        $row = $this->form->addFields(
            [new TLabel('Cliente (*)', 'red')], [$cliente_id]
        );
        $row->layout = ['col-sm-12'];

        $row = $this->form->addFields(
            [new TLabel('Data Proposta')], [$data_proposta],
            [new TLabel('Validade')], [$validade]
        );
        $row->layout = ['col-sm-3', 'col-sm-3', 'col-sm-3', 'col-sm-3'];

        $this->form->addContent(['<h4>Trecho e Operacao</h4><hr>']);
        $row = $this->form->addFields(
            [new TLabel('Origem')], [$origem],
            [new TLabel('Fronteira')], [$fronteira],
            [new TLabel('Destino')], [$destino]
        );
        $row->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];

        $row = $this->form->addFields(
            [new TLabel('Tipo Veiculo')], [$tipo_veiculo],
            [new TLabel('Mercadoria')], [$descricao_mercadoria]
        );
        $row->layout = ['col-sm-4', 'col-sm-8'];

        $this->form->addContent(['<h4>Composicao Comercial</h4><hr>']);
        $row = $this->form->addFields(
            [new TLabel('Toneladas')], [$toneladas],
            [new TLabel('Valor Base (R$)')], [$valor_frete_base],
            [new TLabel('Valor por Ton. (R$)')], [$valor_por_ton]
        );
        $row->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];

        $row = $this->form->addFields([new TLabel('Valor Total')], [$valor_total]);
        $row->layout = ['col-sm-4', 'col-sm-8'];

        $row = $this->form->addFields([new TLabel('Observacoes')], [$observacoes]);
        $row->layout = ['col-sm-2', 'col-sm-10'];

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addActionLink('Listagem', new TAction(['PropostaToneladaList', 'onReload']), 'fa:table blue');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', 'PropostaToneladaList'));
        $container->add($this->form);

        parent::add($container);
    }

    public function onSave($param = null)
    {
        try {
            $this->form->validate();
            $data = $this->form->getData();

            if (empty($data->cliente_id)) {
                throw new Exception('Informe o cliente da proposta.');
            }

            $data->data_proposta = self::normalizeDateToDb($data->data_proposta ?? null);
            $data->validade = self::normalizeDateToDb($data->validade ?? null);

            TTransaction::open(self::$database);

            $proposta = !empty($data->id) ? new PropostaTonelada($data->id) : new PropostaTonelada;
            $proposta->fromArray((array) $data);
            $proposta->toneladas = self::toFloat($data->toneladas ?? 0);
            $proposta->valor_frete_base = self::toFloat($data->valor_frete_base ?? 0);
            $proposta->valor_por_ton = self::toFloat($data->valor_por_ton ?? 0);
            $proposta->valor_total = $proposta->valor_frete_base + ($proposta->toneladas * $proposta->valor_por_ton);
            $proposta->store();

            if (empty($proposta->numero_proposta)) {
                $proposta->numero_proposta = 'PT-' . str_pad((string) $proposta->id, 4, '0', STR_PAD_LEFT) . '/' . date('Y');
                $proposta->store();
            }

            $data->id = $proposta->id;
            $data->numero_proposta = $proposta->numero_proposta;
            $data->valor_total = number_format((float) $proposta->valor_total, 2, ',', '.');
            $data->data_proposta = self::normalizeDateToView($proposta->data_proposta);
            $data->validade = self::normalizeDateToView($proposta->validade);
            $this->form->setData($data);

            TTransaction::close();
            new TMessage('info', 'Proposta por tonelada salva com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public function onEdit($param)
    {
        try {
            if (!empty($param['key'])) {
                TTransaction::open(self::$database);
                $proposta = new PropostaTonelada($param['key']);
                TTransaction::close();

                $proposta->toneladas = number_format((float) $proposta->toneladas, 3, ',', '.');
                $proposta->valor_frete_base = number_format((float) $proposta->valor_frete_base, 2, ',', '.');
                $proposta->valor_por_ton = number_format((float) $proposta->valor_por_ton, 2, ',', '.');
                $proposta->valor_total = number_format((float) $proposta->valor_total, 2, ',', '.');
                $proposta->data_proposta = self::normalizeDateToView($proposta->data_proposta);
                $proposta->validade = self::normalizeDateToView($proposta->validade);
                $this->form->setData($proposta);
                return;
            }

            $data = new stdClass;
            $data->status = 'Em Analise';
            $data->data_proposta = date('d/m/Y');
            $data->validade = date('d/m/Y', strtotime('+15 days'));

            $this->form->setData($data);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public function onClear()
    {
        $this->form->clear(true);
        $data = new stdClass;
        $data->status = 'Em Analise';
        $data->data_proposta = date('d/m/Y');
        $data->validade = date('d/m/Y', strtotime('+15 days'));
        $this->form->setData($data);
    }

    public static function onUpdateTotal($param)
    {
        $toneladas = self::toFloat($param['toneladas'] ?? 0);
        $valorBase = self::toFloat($param['valor_frete_base'] ?? 0);
        $valorTon = self::toFloat($param['valor_por_ton'] ?? 0);
        $total = $valorBase + ($toneladas * $valorTon);

        $obj = new stdClass;
        $obj->valor_total = number_format($total, 2, ',', '.');
        TForm::sendData(self::$formName, $obj);
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

    private static function normalizeDateToDb(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat('!' . $format, $value);
            if ($dt && $dt->format($format) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        return $value;
    }

    private static function normalizeDateToView(?string $value): ?string
    {
        if (empty($value)) {
            return $value;
        }

        $dt = DateTime::createFromFormat('!Y-m-d', $value);
        return $dt ? $dt->format('d/m/Y') : $value;
    }
}
