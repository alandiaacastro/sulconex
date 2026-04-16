<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TXMLBreadCrumb;

class LostDealForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new TForm('form_LostDeal');
        $this->form->class = 'tform';

        $company_name = new TEntry('company_name');
        $phone        = new TEntry('phone');
        $reason       = new TEntry('reason');

        $company_name->setMaxLength(255);
        $phone->setMaxLength(50);
        $reason->setMaxLength(500);

        $company_name->addValidation('Empresa', new TRequiredValidator);

        $table = new TTable;
        $table->style = 'width:100%';

        $row = $table->addRow();
        $row->addCell(new TLabel('Empresa: <span style="color:red">*</span>', null, null, null))->style = 'width:130px;text-align:right;padding-right:8px;';
        $row->addCell($company_name)->style = 'width:400px;';

        $row2 = $table->addRow();
        $row2->addCell(new TLabel('Telefone:'))->style = 'text-align:right;padding-right:8px;';
        $row2->addCell($phone);

        $row3 = $table->addRow();
        $row3->addCell(new TLabel('Motivo:'))->style = 'text-align:right;padding-right:8px;';
        $row3->addCell($reason);

        $btnSave = new TButton('btn_save');
        $btnSave->setLabel('Salvar');
        $btnSave->setImage('fa:save green');
        $btnSave->setAction(new TAction([$this, 'onSave']));

        $btnClear = new TButton('btn_clear');
        $btnClear->setLabel('Limpar');
        $btnClear->setImage('fa:eraser red');
        $btnClear->setAction(new TAction([$this, 'onClear']));

        $this->form->setFields([$company_name, $phone, $reason, $btnSave, $btnClear]);
        $this->form->add($table);

        $footer = new TVBox;
        $footer->add($btnSave);
        $footer->add($btnClear);

        $panel = new TPanelGroup('Negócio Perdido');
        $panel->add($this->form);
        $panel->addFooter($footer);

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', 'LostDealList'));
        $box->add($panel);

        parent::add($box);
    }

    public function onEdit($param = null)
    {
        try {
            if (!empty($param['key'])) {
                TTransaction::open('sample');
                $obj = new LostDeal($param['key']);
                $this->form->setData($obj);
                TTransaction::close();
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onSave($param = null)
    {
        try {
            $this->form->validate();
            $data = $this->form->getData();

            TTransaction::open('sample');
            $obj = new LostDeal($data->id ?? null);
            $obj->company_name = $data->company_name;
            $obj->phone        = $data->phone;
            $obj->reason       = $data->reason;
            $obj->store();
            TTransaction::close();

            new TMessage('info', 'Registro salvo com sucesso!', new TAction(['LostDealList', 'onReload']));
            $this->form->clear();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onClear($param = null)
    {
        $this->form->clear();
    }
}
