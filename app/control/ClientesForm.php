<?php

class ClientesForm extends TPage
{
    protected $form;

    private static $database = 'sample';
    private static $activeRecord = 'Clientes';
    private static $primaryKey = 'id';
    private static $formName = 'form_Clientes';

    public function __construct()
    {
        parent::__construct();

        Clientes::ensureSchema();

        $this->form = new BootstrapFormBuilder(self::$formName);
        $this->form->setFormTitle('Cadastro de Clientes');
        $this->form->setFieldSizes('100%');

        // Campos do formulário
        $id                 = new TEntry('id');
        $nome               = new TEntry('nome');
        $email              = new TEntry('email');
        $telefone           = new TEntry('telefone');
        $endereco           = new TEntry('endereco');
        $cidade             = new TEntry('cidade');
        $estado             = new TCombo('estado');
        $cep                = new TEntry('cep');
        $cnpj               = new TEntry('cnpj');
        $inscricao_estadual = new TEntry('inscricao_estadual');
        $atividade          = new TEntry('atividade');
        $emissao_crt        = new TText('emissao_crt');
        $tipo               = new TCheckGroup('tipo');

        // Configurações
        $id->setEditable(false);
        $estado->addItems([
    // 🇧🇷 Estados do Brasil
    'AC' => 'AC', 'AL' => 'AL', 'AP' => 'AP', 'AM' => 'AM', 'BA' => 'BA',
    'CE' => 'CE', 'DF' => 'DF', 'ES' => 'ES', 'GO' => 'GO', 'MA' => 'MA',
    'MT' => 'MT', 'MS' => 'MS', 'MG' => 'MG', 'PA' => 'PA', 'PB' => 'PB',
    'PR' => 'PR', 'PE' => 'PE', 'PI' => 'PI', 'RJ' => 'RJ', 'RN' => 'RN',
    'RS' => 'RS', 'RO' => 'RO', 'RR' => 'RR', 'SC' => 'SC', 'SP' => 'SP',
    'SE' => 'SE', 'TO' => 'TO',

    // 🇦🇷 🇨🇱 🇵🇾 🇺🇾 Países Mercosul
    'AR' => 'Argentina',
    'CH' => 'Chile',
    'PY' => 'Paraguai',
    'UY' => 'Uruguai',
]);

        // Tipo/classificação
        $tipo->addItems([
            'EXPORTADOR'    => 'Exportador',
            'IMPORTADOR'    => 'Importador',
            'CONSIGNATARIO' => 'Consignatário',
            'NOTIFICAR'     => 'Notificar',
        ]);
        $tipo->setLayout('horizontal');

        // Máscaras
        $cnpj->setMask('99.999.999/9999-99');
        $cep->setMask('99999-999');
        $telefone->setMask('(99)99999-9999');

        // Adiciona os campos
        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Nome')], [$nome]);
        $this->form->addFields([new TLabel('E-mail')], [$email]);
        $this->form->addFields([new TLabel('Telefone')], [$telefone]);
        $this->form->addFields([new TLabel('Endereço')], [$endereco]);
        $this->form->addFields([new TLabel('Cidade')], [$cidade], [new TLabel('Estado')], [$estado]);
        $this->form->addFields([new TLabel('CEP')], [$cep]);
        $this->form->addFields([new TLabel('CNPJ')], [$cnpj]);
        $this->form->addFields([new TLabel('Inscrição Estadual')], [$inscricao_estadual]);
        $this->form->addFields([new TLabel('Atividade')], [$atividade]);
        $this->form->addFields([new TLabel('Emissão CRT')], [$emissao_crt]);
        $this->form->addFields([new TLabel('Classificação')], [$tipo]);

        // Botões
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Voltar', new TAction(['ClientesList', 'onReload']), 'fa:arrow-left blue');

        // Container
        $container = new TVBox;
        $container->style = 'width: 100%';
      //  $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $container->add($this->form);

        parent::add($container);
    }

    public function onSave($param = null)
    {
        try {
            TTransaction::open(self::$database);

            $data = $this->form->getData();
            $this->form->validate();

            $object = new Clientes();
            $object->fromArray((array) $data);
            $object->store();

            $this->form->setData($object);

            TTransaction::close();

            new TMessage('info', 'Registro salvo com sucesso');
            TApplication::loadPage('ClientesList', 'onReload');

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onEdit($param)
    {
        try {
            TTransaction::open(self::$database);

            if (isset($param['key'])) {
                $object = new Clientes($param['key']);
                $this->form->setData($object);
            } else {
                $this->form->clear();
            }

            TTransaction::close();

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onClear($param)
    {
        $this->form->clear();
    }
}
?>




