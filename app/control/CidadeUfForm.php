<?php

class CidadeUfForm extends TStandardForm
{
    protected $form;

    public function __construct($param)
    {
        parent::__construct();
        parent::setTargetContainer('adianti_right_panel');
        parent::setDatabase('default');
        parent::setActiveRecord('CidadeUf');

        $this->form = new BootstrapFormBuilder('form_CidadeUf');
        $this->form->setFormTitle('Cidade / UF');
        $this->form->enableClientValidation();

        $id   = new TEntry('id');
        $nome = new TEntry('nome');
        $uf   = new TEntry('uf');

        $id->setEditable(false);
        $nome->setSize('100%');
        $uf->setSize('100%');
        $uf->setMaxLength(20);
        $uf->setTip('Ex: RS, SP, ARGENTINA, CHILE, URUGUAI, PARAGUAI');

        $nome->addValidation('Nome', new TRequiredValidator);
        $uf->addValidation('UF / País', new TRequiredValidator);

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Nome da Cidade')], [$nome]);
        $this->form->addFields([new TLabel('UF / País')], [$uf]);

        $btn = $this->form->addAction(_t('Save'), new TAction([$this, 'onSave']), 'far:save');
        $btn->class = 'btn btn-sm btn-primary';
        $this->form->addActionLink(_t('Clear'), new TAction([$this, 'onEdit']), 'fa:eraser red');
        $this->form->addHeaderActionLink(_t('Close'), new TAction([$this, 'onClose']), 'fa:times red');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);

        parent::add($container);
    }

    public static function onClose($param)
    {
        TScript::create("Template.closeRightPanel()");
    }
}
