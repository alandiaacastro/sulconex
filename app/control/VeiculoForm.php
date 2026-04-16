<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Validator\TRequiredValidator;
use Adianti\Wrapper\BootstrapFormBuilder;

class VeiculoForm extends TPage
{
    protected $form;

    public function __construct($param)
    {
        parent::__construct($param);
        parent::setTargetContainer('adianti_right_panel');

        $this->form = new BootstrapFormBuilder('form_veiculo');
        $this->form->setFormTitle('Cadastro de Veículo');

        $id = new TEntry('id');
        
        $placa_trator = new TEntry('placa_trator');
        $placa_trator->setMaxLength(8);
        $placa_trator->forceUpperCase();

        $placa_semi = new TEntry('placa_semi');
        $placa_semi->setMaxLength(8);
        $placa_semi->forceUpperCase();

        $motorista_id = new TDBUniqueSearch('motorista_id', 'sample', 'Motorista', 'id', 'nome');
        $motorista_id->setMask('<b>Nome:</b> {nome} | <b>CPF:</b> {cpf} | <b>CNH:</b> {cnh_numero}');
        $motorista_id->setMinLength(1);
        $motorista_id->addValidation('Motorista', new TRequiredValidator);

        $id->setEditable(FALSE);
        $id->setSize('20%');
        $placa_trator->setSize('100%');
        $placa_semi->setSize('100%');
        $motorista_id->setSize('100%');

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addContent(['<h4>Dados do Veículo</h4><hr>']);
        $this->form->addFields([new TLabel('Placa Trator', '#FF0000')], [$placa_trator]);
        $this->form->addFields([new TLabel('Placa Semi-Reboque')], [$placa_semi]);
        $this->form->addContent(['<h4>Motorista</h4><hr>']);
        $this->form->addFields([new TLabel('Motorista', '#FF0000')], [$motorista_id]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addActionLink('Listagem', new TAction(['VeiculoList', 'onReload']), 'fa:table blue');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }
    
    public function onSave($param)
    {
        try
        {
            $this->form->validate();
            $data = $this->form->getData();
            $placaTrator = strtoupper(trim((string) ($data->placa_trator ?? '')));
            $placaSemi   = strtoupper(trim((string) ($data->placa_semi ?? '')));
            if ($placaTrator === '') {
                throw new Exception('Informe a placa do trator.');
            }
            if (empty($data->motorista_id)) {
                throw new Exception('Informe o motorista.');
            }

            require_once __DIR__ . '/ANTTService.php';
            $resTrator = ANTTService::onConsulta(['placa' => $placaTrator]);
            if (empty($resTrator['success'])) {
                throw new Exception($resTrator['mensagem'] ?? 'Falha ao consultar a placa do trator na ANTT.');
            }

            TTransaction::open('sample');
            $veiculo = !empty($data->id) ? new Veiculo($data->id) : new Veiculo;
            $veiculo->motorista_id = $data->motorista_id;
            $veiculo->antt_consulta_trator_id = $resTrator['id'] ?? null;
            $veiculo->modelo = $resTrator['dados']['marca'] ?? null;
            $veiculo->ano_fabricacao = $resTrator['dados']['ano'] ?? null;
            $veiculo->placa_trator = $placaTrator;

            if ($placaSemi !== '') {
                $resSemi = ANTTService::onConsulta(['placa' => $placaSemi]);
                if (empty($resSemi['success'])) {
                    throw new Exception($resSemi['mensagem'] ?? 'Falha ao consultar a placa do semi-reboque na ANTT.');
                }
                $veiculo->antt_consulta_semi_reboque_id = $resSemi['id'] ?? null;
            } else {
                $veiculo->antt_consulta_semi_reboque_id = null;
            }
            $veiculo->store();
            $data->id = $veiculo->id;
            $this->form->setData($data);
            TTransaction::close();
            new TMessage('info', 'Veículo salvo com sucesso!');
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
            $this->form->setData($this->form->getData());
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
                $veiculo = new Veiculo($key);
                $data = $veiculo;
                if (!empty($veiculo->antt_consulta_trator_id) && $veiculo->antt_consulta_trator) {
                    $data->placa_trator = $veiculo->antt_consulta_trator->placa;
                }
                if (!empty($veiculo->antt_consulta_semi_reboque_id) && $veiculo->antt_consulta_semi_reboque) {
                    $data->placa_semi = $veiculo->antt_consulta_semi_reboque->placa;
                }
                $this->form->setData($data);
                TTransaction::close();
            }
            else
            {
                $this->onClear($param);
            }
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onClear($param)
    {
        $this->form->clear(true);
    }
}
