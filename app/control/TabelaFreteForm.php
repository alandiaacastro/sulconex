<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TUniqueSearch;
use Adianti\Database\TRepository;
use Adianti\Widget\Container\TVBox;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Widget\Dialog\TMessage;

class TabelaFreteForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_TabelaFrete');
        $this->form->setFormTitle('Cadastro de Frete (Rota)');

        $id           = new THidden('id');
        $tipo_veiculo = new TEntry('tipo_veiculo');

        // Carregar cidades do banco para autocomplete
        $cidades = [];
        TTransaction::open('default');
        $repository = new TRepository('CidadeUf');
        $lista = $repository->load(null, false);
        if ($lista) {
            foreach ($lista as $c) {
                $label = $c->nome . ',' . $c->uf;
                $cidades[$label] = $label;
            }
        }
        TTransaction::close();

        $origem    = new TUniqueSearch('origem');
        $fronteira = new TUniqueSearch('fronteira');
        $destino   = new TUniqueSearch('destino');

        $origem->addItems($cidades);
        $fronteira->addItems($cidades);
        $destino->addItems($cidades);

        $origem->setMinLength(2);
        $fronteira->setMinLength(2);
        $destino->setMinLength(2);

        $origem->setTip('Digite o nome da cidade (ex: Uruguaiana,RS)');
        $fronteira->setTip('Fronteira / Aduana (ex: Paso de los Libres,ARGENTINA)');
        $destino->setTip('Digite o nome da cidade de destino');

        $valor_frete = new TNumeric('valor_frete', 2, ',', '.', true);

        $tipo_veiculo->setTip('Ex: GERAL, CARRETA SIDER, TRUCK');

        $this->form->addFields([new TLabel('ID')],                  [$id]);
        $this->form->addFields([new TLabel('Tipo Veículo')],         [$tipo_veiculo]);
        $this->form->addFields([new TLabel('Origem')],               [$origem]);
        $this->form->addFields([new TLabel('Fronteira / Aduana')],   [$fronteira]);
        $this->form->addFields([new TLabel('Destino')],              [$destino]);
        $this->form->addFields([new TLabel('Valor Frete (R$)')],     [$valor_frete]);

        $id->setSize('10%');
        $tipo_veiculo->setSize('40%');
        $origem->setSize('100%');
        $fronteira->setSize('100%');
        $destino->setSize('100%');
        $valor_frete->setSize('40%');

        $btn = $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save');
        $btn->class = 'btn btn-sm btn-primary';
        $this->form->addAction('Voltar', new TAction(['TabelaFreteList', 'onReload']), 'fa:arrow-left blue');

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($this->form);

        parent::add($vbox);
    }

    public function onSave($param = null)
    {
        try
        {
            TTransaction::open('sample');
            $data = $this->form->getData();
            $this->form->validate();

            $object = new TabelaFrete();
            $object->fromArray((array) $data);

            $object->tipo_veiculo = mb_strtoupper((string)$object->tipo_veiculo, 'UTF-8');
            $object->origem       = mb_strtoupper((string)$object->origem, 'UTF-8');
            $object->fronteira    = mb_strtoupper((string)$object->fronteira, 'UTF-8');
            $object->destino      = mb_strtoupper((string)$object->destino, 'UTF-8');
            $object->atualizacao  = date('Y-m-d H:i:s');

            $object->store();
            $data->id = $object->id;
            $this->form->setData($data);

            TTransaction::close();

            new TMessage('info', 'Registro salvo com sucesso!');
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onEdit($param)
    {
        try
        {
            if (isset($param['key']))
            {
                $key = $param['key'];
                TTransaction::open('sample');
                $object = new TabelaFrete($key);
                $this->form->setData($object);
                TTransaction::close();
            }
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
