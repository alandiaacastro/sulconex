<?php

class EnlastreForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_enlastre');
        $this->form->setFormTitle('Cadastro de Enlastre');

        $id             = new TEntry('id');
        $permisso_id    = new TEntry('permisso_id');
        $permisso       = new TEntry('permisso');
        $numeroenlastre = new TEntry('numeroenlastre');
        $trator         = new TEntry('trator');
        $semi           = new TEntry('semi');
        $motorista      = new TEntry('motorista');

        $id->setEditable(false);

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Permisso ID')], [$permisso_id]);
        $this->form->addFields([new TLabel('Permisso')], [$permisso]);
        $this->form->addFields([new TLabel('Número Enlastre')], [$numeroenlastre]);
        $this->form->addFields([new TLabel('Trator')], [$trator]);
        $this->form->addFields([new TLabel('Semi')], [$semi]);
        $this->form->addFields([new TLabel('Motorista')], [$motorista]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Voltar', new TAction(['EnlastreList', 'onReload']), 'fa:arrow-left');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);

        parent::add($container);
    }

    public function onEdit($param)
    {
        try {
            if (isset($param['id'])) {
                TTransaction::open('sample');
                $object = new Enlastre($param['id']);
                $this->form->setData($object);
                TTransaction::close();
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');

            $data = $this->form->getData();
            $this->form->validate();

            $object = new Enlastre;
            $object->fromArray((array) $data);
            $object->store();

            $this->form->setData($object);

            TTransaction::close();

            new TMessage('info', 'Registro salvo com sucesso');
            TApplication::loadPage('EnlastreList');
        } catch (Exception $e) {
            $this->form->setData((object) $param);
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}

