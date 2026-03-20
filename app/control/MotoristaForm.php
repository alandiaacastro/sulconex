<?php

class MotoristaForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        // Formulário
        $this->form = new BootstrapFormBuilder('form_Motorista');
        $this->form->setFormTitle('ðŸ§‘â€âœˆï¸ Cadastro de Motorista');
        $this->form->setFieldSizes('100%');

        // Campos
        $id                = new TEntry('id');
        $nome              = new TEntry('nome');
        $cpf               = new TEntry('cpf');
        $rg_numero         = new TEntry('rg_numero');
        $rg_emissor        = new TEntry('rg_emissor');
        $rg_uf             = new TEntry('rg_uf');
        $data_nascimento   = new TDate('data_nascimento');
        $local_nascimento  = new TEntry('local_nascimento');
        $filiacao_pai      = new TEntry('filiacao_pai');
        $filiacao_mae      = new TEntry('filiacao_mae');
        $telefone          = new TEntry('telefone');
        $email             = new TEntry('email');
        $system_user_id    = new TDBCombo('system_user_id', 'permission', 'SystemUser', 'id', 'name', 'name');
        $cnh_numero        = new TEntry('cnh_numero');
        $data_emissao_cnh  = new TDate('data_emissao_cnh');
        $data_validade_cnh = new TDate('data_validade_cnh');
        $categoria         = new TEntry('categoria');
        $registro_num      = new TEntry('registro_num');

        // Configurações
        $id->setEditable(false);
        $cpf->setMask('999.999.999-99');
        $data_nascimento->setMask('dd/mm/yyyy');
        $data_emissao_cnh->setMask('dd/mm/yyyy');
        $data_validade_cnh->setMask('dd/mm/yyyy');
        $telefone->setMask('(99)99999-9999');
        $email->setSize('100%');

        // Validações
        $nome->addValidation('Nome', new TRequiredValidator);
        $cpf->addValidation('CPF', new TRequiredValidator);
        $cnh_numero->addValidation('CNH', new TRequiredValidator);

        // Organização dos campos
        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Nome')], [$nome]);
        $this->form->addFields([new TLabel('CPF')], [$cpf]);
        $this->form->addFields([new TLabel('RG')], [$rg_numero], [new TLabel('Emissor')], [$rg_emissor], [new TLabel('UF')], [$rg_uf]);
        $this->form->addFields([new TLabel('Data Nascimento')], [$data_nascimento], [new TLabel('Local Nascimento')], [$local_nascimento]);
        $this->form->addFields([new TLabel('Filiação Pai')], [$filiacao_pai]);
        $this->form->addFields([new TLabel('Filiação Mãe')], [$filiacao_mae]);
        $this->form->addFields([new TLabel('Telefone')], [$telefone], [new TLabel('Email')], [$email]);
        $this->form->addFields([new TLabel('Usuario do Sistema')], [$system_user_id]);
        $this->form->addFields([new TLabel('CNH')], [$cnh_numero], [new TLabel('Categoria')], [$categoria]);
        $this->form->addFields([new TLabel('Registro CNH')], [$registro_num]);
        $this->form->addFields([new TLabel('Emissão CNH')], [$data_emissao_cnh], [new TLabel('Validade CNH')], [$data_validade_cnh]);

        // Ações
        $this->form->addAction('ðŸ’¾ Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('ðŸ”™ Voltar', new TAction(['MotoristaList', 'onReload']), 'fa:arrow-left blue');

        // Layout
        $vbox = new TVBox;
        $vbox->style = 'width:100%';
        $vbox->add($this->form);

        parent::add($vbox);
    }

    /**
     * Salva os dados
     */
   public function onSave($param = null)
{
    try
    {
        TTransaction::open('sample');
        Motorista::ensureTables();

        $this->form->validate();
        $data = $this->form->getData();

        // Conversão das datas para o formato do banco (YYYY-MM-DD)
        $data->data_nascimento   = TDate::convertToMask($data->data_nascimento, 'dd/mm/yyyy', 'yyyy-mm-dd');
        $data->data_emissao_cnh  = TDate::convertToMask($data->data_emissao_cnh, 'dd/mm/yyyy', 'yyyy-mm-dd');
        $data->data_validade_cnh = TDate::convertToMask($data->data_validade_cnh, 'dd/mm/yyyy', 'yyyy-mm-dd');

        $object = new Motorista;
        $object->fromArray((array) $data);
        $object->store();

        TTransaction::close();

        new TMessage('info', 'Registro salvo com sucesso');

        // ðŸ”¥ Redireciona para a lista de motoristas apÃ³s salvar
        TApplication::gotoPage('MotoristaList', 'onReload');
    }
    catch (Exception $e)
    {
        TTransaction::rollback();
        new TMessage('error', $e->getMessage());
    }
}
    /**
     * Carrega os dados para edição
     */
    public function onEdit($param)
    {
        try
        {
            TTransaction::open('sample');
            Motorista::ensureTables();

            if (isset($param['id']))
            {
                $object = new Motorista($param['id']);

                // Conversão das datas do banco (yyyy-mm-dd) para o formulário (dd/mm/yyyy)
                $object->data_nascimento   = TDate::convertToMask($object->data_nascimento, 'yyyy-mm-dd', 'dd/mm/yyyy');
                $object->data_emissao_cnh  = TDate::convertToMask($object->data_emissao_cnh, 'yyyy-mm-dd', 'dd/mm/yyyy');
                $object->data_validade_cnh = TDate::convertToMask($object->data_validade_cnh, 'yyyy-mm-dd', 'dd/mm/yyyy');

                $this->form->setData($object);
            }
            else
            {
                $this->form->clear();
            }

            TTransaction::close();
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }
}

?>

