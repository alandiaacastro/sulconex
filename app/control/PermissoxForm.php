<?php

class PermissoxForm extends TPage
{
    protected $form;
    private static $database = 'sample';
    private static $activeRecord = 'Permissox';
    private static $primaryKey = 'id';
    private static $formName = 'PermissoxForm';

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder(self::$formName);
        $this->form->setFormTitle('Cadastro de Permisso CRT');

        // Campos do formulário
        $id             = new TEntry('id');
        $permisso       = new TEntry('permisso');
        $pais_destino   = new TEntry('pais_destino');
        $numerocrt      = new TEntry('numerocrt');
        $transportadora = new TText('transportadora');
        $logo           = new TFile('logo');

        // Configurações dos campos
        $id->setEditable(false);
        $permisso->setMaxLength(6);
        $pais_destino->setMaxLength(10);
        $transportadora->setSize('40%');
        $transportadora->setProperty('style', 'height: 100px');

        $logo->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif']);
        $logo->enableFileHandling();
        $logo->setCompleteAction(new TAction([$this, 'onFileUpload']));

        // Adiciona os campos ao formulário
        $this->form->addFields([new TLabel('ID')],             [$id]);
        $this->form->addFields([new TLabel('Permisso')],       [$permisso]);
        $this->form->addFields([new TLabel('País Destino')],   [$pais_destino]);
        $this->form->addFields([new TLabel('Número CRT')],     [$numerocrt]);
        $this->form->addFields([new TLabel('Transportadora')], [$transportadora]);
        $this->form->addFields([new TLabel('🖼️ Logo (Imagem)')], [$logo]);

        // Ações do formulário
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fas:save green');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fas:eraser red');
        $this->form->addAction('Voltar', new TAction(['PermissoxList', 'onReload']), 'fas:arrow-left');

        // Container
        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);

        parent::add($container);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open(self::$database);
            $this->form->validate();
            $data = $this->form->getData();

            $object = new Permissox;
            $object->fromArray((array) $data);
            $object->store();

            $data->id = $object->id;
            $this->form->setData($data);

            TTransaction::close();
            new TMessage('info', 'Registro salvo com sucesso!');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public static function onEdit($param)
    {
        try {
            TTransaction::open(self::$database);

            if (isset($param['id'])) {
                $object = new Permissox($param['id']);

                // Prepara a imagem para exibir no campo TFile
                if (!empty($object->logo)) {
                    $path = 'app/images/' . $object->logo;
                    if (file_exists($path)) {
                        $obj = new stdClass;
                        $obj->logo = $object->logo;
                        TForm::sendData(self::$formName, $obj);
                    }
                }

                $form = new self;
                $form->form->setData($object);
                TApplication::loadPage(__CLASS__, 'onShow', ['id' => $param['id']]);
            }

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onShow($param = null)
    {
        if (!empty($param['id'])) {
            try {
                TTransaction::open(self::$database);
                $object = new Permissox($param['id']);
                $this->form->setData($object);
                TTransaction::close();
            } catch (Exception $e) {
                TTransaction::rollback();
                new TMessage('error', $e->getMessage());
            }
        }
    }

    public function onClear($param)
    {
        $this->form->clear();
    }

    public static function onFileUpload($param)
    {
        if (!empty($param['logo'])) {
            $info = json_decode(urldecode($param['logo']));

            if (isset($info->fileName) && file_exists($info->fileName)) {
                $path = 'app/images/';
                $newFile = $path . basename($info->fileName);
                @rename($info->fileName, $newFile);

                $obj = new stdClass;
                $obj->logo = basename($newFile); // Apenas o nome do arquivo
                TForm::sendData(self::$formName, $obj);
            }
        }
    }
}
