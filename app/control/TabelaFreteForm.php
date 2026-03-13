<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TNumeric;
use Adianti\Wrapper\BootstrapFormBuilder;

class TabelaFreteForm extends TPage
{
    protected $form;

    private static $database = 'sample';
    private static $activeRecord = 'TabelaFrete';
    private static $formName = 'form_TabelaFrete';

    public function __construct($param = null)
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder(self::$formName);
        $this->form->setFormTitle('Tabela de Fretes');

        $id = new TEntry('id');
        $origem = new TEntry('origem');
        $destino = new TEntry('destino');
        $tipo_veiculo = new TEntry('tipo_veiculo');
        $valor_frete = new TNumeric('valor_frete', 2, ',', '.', true);

        $id->setEditable(false);
        $id->setSize('20%');
        $origem->setSize('100%');
        $destino->setSize('100%');
        $tipo_veiculo->setSize('100%');
        $valor_frete->setSize('100%');

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Origem', '#FF0000')], [$origem]);
        $this->form->addFields([new TLabel('Destino', '#FF0000')], [$destino]);
        $this->form->addFields([new TLabel('Tipo de Veiculo', '#FF0000')], [$tipo_veiculo]);
        $this->form->addFields([new TLabel('Valor do Frete (R$)', '#FF0000')], [$valor_frete]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addActionLink('Listagem', new TAction(['TabelaFreteList', 'onReload']), 'fa:table blue');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);

        parent::add($container);
    }

    public function onSave($param = null): void
    {
        try {
            TTransaction::open(self::$database);

            $this->form->validate();
            $data = $this->form->getData();

            $origem = self::normalizeRoutePoint((string) ($data->origem ?? ''));
            $destino = self::normalizeRoutePoint((string) ($data->destino ?? ''));
            $tipoVeiculo = self::normalizeRoutePoint((string) ($data->tipo_veiculo ?? ''));

            if ($origem === '' || $destino === '' || $tipoVeiculo === '') {
                throw new Exception('Informe origem, destino e tipo de veiculo para cadastrar o frete.');
            }

            $object = !empty($data->id) ? new TabelaFrete($data->id) : new TabelaFrete;
            $object->origem = $origem;
            $object->destino = $destino;
            $object->tipo_veiculo = $tipoVeiculo;
            $object->valor_frete = self::toFloat($data->valor_frete ?? 0);
            $object->updated_at = date('Y-m-d H:i:s');
            if (empty($object->created_at)) {
                $object->created_at = date('Y-m-d H:i:s');
            }

            $object->store();

            $data->id = $object->id;
            $data->origem = $object->origem;
            $data->destino = $object->destino;
            $data->tipo_veiculo = $object->tipo_veiculo;
            $data->valor_frete = number_format((float) $object->valor_frete, 2, ',', '.');
            $this->form->setData($data);

            TTransaction::close();
            new TMessage('info', 'Frete cadastrado com sucesso.');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            $this->form->setData($this->form->getData());
            try {
                TTransaction::rollback();
            } catch (Exception $ee) {
            }
        }
    }

    public function onEdit($param = null): void
    {
        try {
            $param = is_array($param) ? $param : [];

            if (!empty($param['key'])) {
                TTransaction::open(self::$database);
                $object = new TabelaFrete($param['key']);

                $data = new stdClass();
                $data->id = $object->id;
                $data->origem = $object->origem;
                $data->destino = $object->destino;
                $data->tipo_veiculo = $object->tipo_veiculo;
                $data->valor_frete = number_format((float) $object->valor_frete, 2, ',', '.');

                $this->form->setData($data);
                TTransaction::close();
            } else {
                $this->onClear();
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            try {
                TTransaction::rollback();
            } catch (Exception $ee) {
            }
        }
    }

    public function onClear($param = null): void
    {
        $this->form->clear(true);
    }

    private static function normalizeRoutePoint(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        return mb_strtoupper((string) $value, 'UTF-8');
    }

    private static function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return (float) str_replace(',', '.', str_replace('.', '', (string) $value));
    }
}
