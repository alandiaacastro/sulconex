<?php

class PermissoForm extends TPage
{
    protected $form;

    private static $database     = 'sample';
    private static $activeRecord = 'Permisso';
    private static $primaryKey   = 'id';
    private static $formName     = 'PermissoForm';

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder(self::$formName);
        $this->form->setFormTitle('Cadastro de Permissão CRT');
        $this->form->setFieldSizes('100%');

        $this->createFields();
        $this->addFormActions();

        $container = new TVBox;
        $container->style = 'width: 100%';
       // $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);

        parent::add($container);
    }

    private function createFields()
    {
        $id               = new TEntry('id');
        $permisso         = new TEntry('permisso');
        $pais_destino     = new TEntry('pais_destino');
        $numerocrt        = new TEntry('numerocrt');
        $numeroenlastre   = new TEntry('numeroenlastre');
        $cnpj             = new TEntry('cnpj');
        $transportadora   = new TEntry('transportadora');
        $dados_documentos = new TText('dados_documentos');
        $logo             = new TFile('logo');

        $id->setEditable(false);
        $permisso->addValidation('Permissão', new TRequiredValidator);
        $pais_destino->addValidation('País Destino', new TRequiredValidator);
        $dados_documentos->setSize('100%', 100);
        $logo->setAllowedExtensions(['jpg','jpeg','png','gif']);
        $logo->setSize('100%');

        $this->form->addFields(
            [new TLabel('ID'), $id],
            [new TLabel('Permissão*'), $permisso],
            [new TLabel('País Destino*'), $pais_destino],
            [new TLabel('CNPJ'), $cnpj],
            [new TLabel('Transportadora'), $transportadora]
        )->layout = ['col-sm-1','col-sm-2','col-sm-2','col-sm-2','col-sm-5'];

        $this->form->addFields(
            [new TLabel('Dados Documentos'), $dados_documentos],
            [new TLabel('Número Enlastre'), $numeroenlastre],
            [new TLabel('Número CRT'), $numerocrt]
        )->layout = ['col-sm-8', 'col-sm-2', 'col-sm-2'];

        $this->form->addFields(
            [new TLabel('Logo (máx. 2MB)'), $logo]
        )->layout = ['col-sm-12'];

        $this->form->setFields([
            $id, $permisso, $pais_destino, $numerocrt, $numeroenlastre,
            $cnpj, $transportadora, $dados_documentos, $logo
        ]);
    }

    private function addFormActions()
    {
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fas:save blue');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fas:eraser red');
        $this->form->addAction('Voltar', new TAction(['PermissoList', 'onReload']), 'fas:arrow-left gray');
    }

    public function onSave($param)
    {
        try {
            TTransaction::open(self::$database);

            $this->form->validate();
            $data = $this->form->getData();

            $object = new Permisso;
            $object->fromArray((array) $data);

            $logo = $this->form->getField('logo')->getValue();
            if ($logo) {
                $source_file = 'tmp/' . $logo;
                $target_path = 'app/images/logos/' . $logo;
                if (file_exists($source_file)) {
                    rename($source_file, $target_path);
                    $object->logo = $logo;
                }
            }

            $object->store();
            $data->id = $object->id;

            $this->form->setData($data);
            TTransaction::close();

            new TMessage('info', 'Registro salvo com sucesso!');
        } catch (Exception $e) {
            $this->form->setData($this->form->getData());
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onEdit($param)
    {
        try {
            TTransaction::open(self::$database);

            if (isset($param['id'])) {
                $object = new Permisso($param['id']);
                $this->form->setData($object);

                if ($object->logo && file_exists('app/images/logos/' . $object->logo)) {
                    $preview = new TImage('app/images/logos/' . $object->logo);
                    $preview->style = 'max-width:180px; max-height:100px; margin:10px';
                    $this->form->addContent([$preview]);
                }
            }

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onClear($param)
    {
        $this->form->clear(true);
    }

    public function onShow($param = null)
    {
        if (!empty($param['id'])) {
            $this->onEdit($param);
        }
    }
}
