<?php
class ClientesForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        // Criação do formulário
        $this->form = new BootstrapFormBuilder('form_clientes');
        $this->form->setFormTitle('📝 Cadastro de Clientes');

        // Campos
        $id     = new TEntry('id');
        $nome   = new TEntry('nome');
        $email  = new TEntry('email');
        $telefone = new TEntry('telefone');
        $endereco = new TEntry('endereco');
        $cidade = new TEntry('cidade');
        $estado = new TEntry('estado');
        $cep = new TEntry('cep');
        $cnpj = new TEntry('cnpj');
        $ie   = new TEntry('inscricao_estadual');
        $atividade = new TEntry('atividade');
        $emissao_crt = new TText('emissao_crt');

        $id->setEditable(FALSE);

        // Adiciona campos ao form
        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Nome*')], [$nome]);
        $this->form->addFields([new TLabel('Email')], [$email]);
        $this->form->addFields([new TLabel('Telefone')], [$telefone]);
        $this->form->addFields([new TLabel('Endereço')], [$endereco]);
        $this->form->addFields([new TLabel('Cidade')], [$cidade]);
        $this->form->addFields([new TLabel('Estado')], [$estado]);
        $this->form->addFields([new TLabel('CEP')], [$cep]);
        $this->form->addFields([new TLabel('CNPJ')], [$cnpj]);
        $this->form->addFields([new TLabel('Inscrição Estadual')], [$ie]);
        $this->form->addFields([new TLabel('Atividade')], [$atividade]);
        $this->form->addFields([new TLabel('Emissão CRT')], [$emissao_crt]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Voltar', new TAction(['ClientesList', 'onReload']), 'fa:arrow-left blue');

        parent::add($this->form);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('sample');

            $data = $this->form->getData();
            $this->form->validate(); // Validação se quiser

            $cliente = new Clientes;
            $cliente->fromArray((array) $data);
            $cliente->store();

            $this->form->setData($cliente);

            TTransaction::close();
            new TMessage('info', 'Cliente salvo com sucesso!');

        } catch (Exception $e) {
            $this->form->setData($this->form->getData());
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onEdit($param)
    {
        try {
            TTransaction::open('sample');

            if (isset($param['id'])) {
                $cliente = new Clientes($param['id']);
                $this->form->setData($cliente);
            }

            TTransaction::close();

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}

