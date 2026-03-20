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
        $this->form->setFormTitle('<i class="fa fa-id-card me-1"></i> Cadastro de Permissão CRT');
        $this->form->setFieldSizes('100%');
        $this->form->enableClientValidation();
        $this->form->setProperty('style', 'max-width: 1100px; margin: 0 auto;');

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
        $dados_Documentos = new TText('dados_Documentos');
        $logo             = new TFile('logo');

        $id->setEditable(false);
        $permisso->addValidation('Permissão', new TRequiredValidator);
        $pais_destino->addValidation('País Destino', new TRequiredValidator);
        $dados_Documentos->setSize('100%', 100);
        $logo->setAllowedExtensions(['jpg','jpeg','png','gif']);
        $logo->setSize('100%');
        $logo->setProperty('accept', 'image/*');

        $permisso->setProperty('placeholder', 'Ex.: BR-00123');
        $pais_destino->setProperty('placeholder', 'Ex.: Brasil, Chile, Paraguai');
        $cnpj->setProperty('placeholder', 'Ex.: 12.345.678/0001-99');
        $transportadora->setProperty('placeholder', 'Ex.: Transportes XYZ');
        $numerocrt->setProperty('placeholder', 'Ex.: BR774000190');
        $numeroenlastre->setProperty('placeholder', 'Ex.: EN-123456');
        $dados_Documentos->setProperty('placeholder', 'Informe dados adicionais do documento, observações e regras de emissão');

        $this->form->addFields([new TFormSeparator('Identificação')]);
        $this->form->addFields(
            [new TLabel('ID'), $id],
            [new TLabel('Permissão*'), $permisso],
            [new TLabel('País Destino*'), $pais_destino]
        )->layout = ['col-sm-2','col-sm-5','col-sm-5'];

        $this->form->addFields(
            [new TLabel('CNPJ'), $cnpj],
            [new TLabel('Transportadora'), $transportadora]
        )->layout = ['col-sm-4','col-sm-8'];

        $this->form->addFields([new TFormSeparator('Documentos')]);
        $this->form->addFields(
            [new TLabel('Dados Documentos'), $dados_Documentos]
        )->layout = ['col-sm-12'];

        $this->form->addFields(
            [new TLabel('Número Enlastre'), $numeroenlastre],
            [new TLabel('Número CRT'), $numerocrt]
        )->layout = ['col-sm-6', 'col-sm-6'];

        $this->form->addFields([new TFormSeparator('Logo')]);
        $this->form->addFields(
            [new TLabel('Logo (máx. 2MB)'), $logo]
        )->layout = ['col-sm-12'];

        $this->form->setFields([
            $id, $permisso, $pais_destino, $numerocrt, $numeroenlastre,
            $cnpj, $transportadora, $dados_Documentos, $logo
        ]);
    }

    private function addFormActions()
    {
        $btnSave = $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fas:save');
        $btnSave->class = 'btn btn-sm btn-primary';

        $btnClear = $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fas:eraser');
        $btnClear->class = 'btn btn-sm btn-outline-danger';

        $btnBack = $this->form->addAction('Voltar', new TAction(['PermissoList', 'onReload']), 'fas:arrow-left');
        $btnBack->class = 'btn btn-sm btn-outline-secondary';
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
                    $preview = new TImage("app/images/logos/" . $object->logo);
                    $preview->style = "max-width:220px; max-height:120px; margin:6px 0";
                    $this->form->addFields(
                        [new TLabel("Logo atual"), $preview]
                    )->layout = ["col-sm-12"];
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






