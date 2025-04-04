<?php

class PermissoxForm extends TPage
{
    protected $form;
    private static $database     = 'sample';
    private static $activeRecord = 'Permissox';
    private static $primaryKey   = 'id';
    private static $formName     = 'PermissoxForm';

    public function __construct()
    {
        parent::__construct();

        // Criação do formulário
        $this->form = new BootstrapFormBuilder(self::$formName);
        $this->form->setFormTitle('Cadastro de Permisso CRT');

        // Campos do formulário
        $id             = new TEntry('id');
        $permisso       = new TEntry('permisso');
        $pais_destino   = new TEntry('pais_destino');
        $numerocrt      = new TEntry('numerocrt');
        $transportadora = new TText('transportadora');
        $logo           = new TFile('logo'); // Campo de imagem

        // Configurações dos campos
        $id->setEditable(false);
        $permisso->setMaxLength(6);
        $pais_destino->setMaxLength(10);
        $transportadora->setSize('40%');
        $transportadora->setProperty('style', 'height: 100px');

        // Configurações do TFile (Logo)
        $logo->setSize('200', '100'); // Tamanho visual no formulário
        $logo->setAllowedExtensions(['jpg', 'png']); // Tipos permitidos

        // Adicionando campos ao formulário
        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Permisso')], [$permisso]);
        $this->form->addFields([new TLabel('País Destino')], [$pais_destino]);
        $this->form->addFields([new TLabel('Número CRT')], [$numerocrt]);
        $this->form->addFields([new TLabel('Transportadora')], [$transportadora]);
        $this->form->addFields([new TLabel('🖼️ Logo Recortado')], [$logo]);

        // Ações do formulário
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fas:save green');
        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fas:eraser red');
        $this->form->addAction('Voltar', new TAction(['PermissoxList', 'onReload']), 'fas:arrow-left');

        // Container com breadcrumb
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

            // Valida e obtém os dados do formulário
            $this->form->validate();
            $data = $this->form->getData();

            // Verifica se foi feito upload de imagem
            if (!empty($data->logo) && file_exists($data->logo)) {
                $extensao = pathinfo($data->logo, PATHINFO_EXTENSION);
                $nome_arquivo = uniqid('logo_') . '.' . $extensao;
                $destino = 'app/images/' . $nome_arquivo;

                // Copia o arquivo do tmp/ para app/images/
                copy($data->logo, $destino);

                // Define apenas o nome no campo logo
                $data->logo = $nome_arquivo;
            }

            // Cria objeto e armazena
            $object = new Permissox;
            $object->fromArray((array) $data);
            $object->store();

            // Atualiza o formulário
            $data->id = $object->id;
            $this->form->setData($data);

            TTransaction::close();
            new TMessage('info', 'Registro salvo com sucesso!');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onEdit($param)
    {
        try {
            TTransaction::open(self::$database);

            if (isset($param['id'])) {
                $object = new Permissox($param['id']);
                $this->form->setData($object);

                // Mostra o caminho da logo atual, se houver
                if (!empty($object->logo)) {
                    $caminhoLogo = 'app/images/' . $object->logo;
                    $labelLogo   = new TLabel("Arquivo atual: " . $caminhoLogo);
                    $this->form->addFields([$labelLogo]);
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
        $this->form->clear();
    }

    public function onShow($param = null)
    {
        if (!empty($param['id'])) {
            $this->onEdit($param);
        }
    }
}
