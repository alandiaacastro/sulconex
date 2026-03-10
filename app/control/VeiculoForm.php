<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Wrapper\TDBUniqueSearch;

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
        
        $antt_consulta_trator_id = new TDBUniqueSearch('antt_consulta_trator_id', 'sample', 'AnttConsulta', 'id', 'placa');
        $antt_consulta_trator_id->setMask('Placa: {placa} | Tipo: {tipo} | Modelo: {marca} | Ano: {ano}');
        $antt_consulta_trator_id->setMinLength(1);

        $antt_consulta_semi_reboque_id = new TDBUniqueSearch('antt_consulta_semi_reboque_id', 'sample', 'AnttConsulta', 'id', 'placa');
        $antt_consulta_semi_reboque_id->setMask('Placa: {placa} | Tipo: {tipo} | Modelo: {marca} | Ano: {ano}');
        $antt_consulta_semi_reboque_id->setMinLength(1);

        $motorista_id = new TDBUniqueSearch('motorista_id', 'sample', 'Motorista', 'id', 'nome');
        $motorista_id->setMask('<b>Nome:</b> {nome} | <b>CPF:</b> {cpf} | <b>CNH:</b> {cnh_numero}');
        $motorista_id->setMinLength(1);

        $id->setEditable(FALSE);
        $id->setSize('20%');
        $antt_consulta_trator_id->setSize('100%');
        $antt_consulta_semi_reboque_id->setSize('100%');
        $motorista_id->setSize('100%');

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addContent(['<h4>Dados do Veículo</h4><hr>']);
        $this->form->addFields([new TLabel('Placa Trator', '#FF0000')], [$antt_consulta_trator_id]);
        $this->form->addFields([new TLabel('Placa Semi-Reboque')], [$antt_consulta_semi_reboque_id]);
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
            TTransaction::open('sample');
            $this->form->validate();
            $data = $this->form->getData();
            $antt_trator = new AnttConsulta($data->antt_consulta_trator_id);
            $veiculo = new Veiculo;
            $veiculo->fromArray((array) $data);
            $veiculo->modelo = $antt_trator->marca;
            $veiculo->ano_fabricacao = $antt_trator->ano;
            $veiculo->placa_trator = $antt_trator->placa;
            $veiculo->store();
            $this->form->setData($veiculo);
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
                $this->form->setData($veiculo);
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