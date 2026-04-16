<?php

class MotoristaForm extends TPage
{
    private $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_Motorista');
        $this->form->setFormTitle('Cadastro de Motorista');
        $this->form->setFieldSizes('100%');

        $portalUrl = htmlspecialchars(PortalMotoristaSupportService::buildAbsoluteApplicationUrl('portal-motorista/'), ENT_QUOTES, 'UTF-8');

        $id = new TEntry('id');
        $nome = new TEntry('nome');
        $cpf = new TEntry('cpf');
        $rg_numero = new TEntry('rg_numero');
        $rg_emissor = new TEntry('rg_emissor');
        $rg_uf = new TEntry('rg_uf');
        $data_nascimento = new TDate('data_nascimento');
        $local_nascimento = new TEntry('local_nascimento');
        $filiacao_pai = new TEntry('filiacao_pai');
        $filiacao_mae = new TEntry('filiacao_mae');
        $telefone = new TEntry('telefone');
        $email = new TEntry('email');
        $system_user_id = new TDBCombo('system_user_id', 'permission', 'SystemUser', 'id', 'name', 'name');
        $cnh_numero = new TEntry('cnh_numero');
        $data_emissao_cnh = new TDate('data_emissao_cnh');
        $data_validade_cnh = new TDate('data_validade_cnh');
        $categoria = new TEntry('categoria');
        $registro_num = new TEntry('registro_num');
        $senha_portal = new TPassword('senha_portal');
        $senha_portal2 = new TPassword('senha_portal2');

        $id->setEditable(false);
        $cpf->setMask('999.999.999-99');
        $data_nascimento->setMask('dd/mm/yyyy');
        $data_emissao_cnh->setMask('dd/mm/yyyy');
        $data_validade_cnh->setMask('dd/mm/yyyy');
        $telefone->setMask('(99)99999-9999');
        $email->setSize('100%');

        $nome->addValidation('Nome', new TRequiredValidator);
        $cpf->addValidation('CPF', new TRequiredValidator);
        $cnh_numero->addValidation('CNH', new TRequiredValidator);

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Nome')], [$nome]);
        $this->form->addFields([new TLabel('CPF')], [$cpf]);
        $this->form->addFields([new TLabel('RG')], [$rg_numero], [new TLabel('Emissor')], [$rg_emissor], [new TLabel('UF')], [$rg_uf]);
        $this->form->addFields([new TLabel('Data Nascimento')], [$data_nascimento], [new TLabel('Local Nascimento')], [$local_nascimento]);
        $this->form->addFields([new TLabel('Filiacao Pai')], [$filiacao_pai]);
        $this->form->addFields([new TLabel('Filiacao Mae')], [$filiacao_mae]);
        $this->form->addFields([new TLabel('Telefone')], [$telefone], [new TLabel('Email')], [$email]);
        $this->form->addFields([new TLabel('Usuario do Sistema')], [$system_user_id]);
        $this->form->addFields([new TLabel('CNH')], [$cnh_numero], [new TLabel('Categoria')], [$categoria]);
        $this->form->addFields([new TLabel('Registro CNH')], [$registro_num]);
        $this->form->addFields([new TLabel('Emissao CNH')], [$data_emissao_cnh], [new TLabel('Validade CNH')], [$data_validade_cnh]);
        $this->form->addContent(['<hr><b>Acesso ao Portal</b>']);
        $this->form->addContent(["
            <div style='margin:-4px 0 14px;padding:12px 14px;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px'>
                <div style='font-weight:700;color:#1D4ED8;margin-bottom:6px'>Link do motorista para acesso</div>
                <a href='{$portalUrl}' target='_blank' rel='noopener noreferrer' style='color:#1D4ED8;font-weight:700;word-break:break-all;text-decoration:none'>{$portalUrl}</a>
                <div style='margin-top:6px;font-size:12px;color:#475569'>Use esse link para o motorista entrar no portal e concluir o primeiro acesso.</div>
            </div>
        "]);
        $this->form->addFields([new TLabel('Senha do Portal')], [$senha_portal], [new TLabel('Confirmar Senha')], [$senha_portal2]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Voltar', new TAction(['MotoristaList', 'onReload']), 'fa:arrow-left blue');

        $vbox = new TVBox;
        $vbox->style = 'width:100%';
        $vbox->add($this->form);

        parent::add($vbox);
    }

    public function onSave($param = null)
    {
        try {
            TTransaction::open('sample');
            Motorista::ensureTables();

            $this->form->validate();
            $data = $this->form->getData();

            if (!empty($data->senha_portal) || !empty($data->senha_portal2)) {
                if ($data->senha_portal !== $data->senha_portal2) {
                    throw new Exception('As senhas do portal nao conferem.');
                }
            }

            $data->data_nascimento = TDate::convertToMask($data->data_nascimento, 'dd/mm/yyyy', 'yyyy-mm-dd');
            $data->data_emissao_cnh = TDate::convertToMask($data->data_emissao_cnh, 'dd/mm/yyyy', 'yyyy-mm-dd');
            $data->data_validade_cnh = TDate::convertToMask($data->data_validade_cnh, 'dd/mm/yyyy', 'yyyy-mm-dd');

            $object = new Motorista;
            $existing = null;

            if (!empty($data->id)) {
                $object = new Motorista($data->id);
                $existing = new Motorista($data->id);
            }

            $object->fromArray((array) $data);

            if (!empty($data->senha_portal)) {
                $object->senha_portal = password_hash($data->senha_portal, PASSWORD_DEFAULT);
                $object->senha_portal_temporaria = 0;
            } elseif (empty($data->id)) {
                $object->senha_portal = null;
                $object->senha_portal_temporaria = 0;
            } else {
                $object->senha_portal = $existing ? $existing->senha_portal : $object->senha_portal;
                $object->senha_portal_temporaria = $existing ? (int) $existing->senha_portal_temporaria : 0;
            }

            unset($object->senha_portal2);

            $object->store();

            TTransaction::close();

            new TMessage('info', 'Registro salvo com sucesso');
            TApplication::gotoPage('MotoristaList', 'onReload');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onEdit($param)
    {
        try {
            TTransaction::open('sample');
            Motorista::ensureTables();

            if (isset($param['id'])) {
                $object = new Motorista($param['id']);
                $object->data_nascimento = TDate::convertToMask($object->data_nascimento, 'yyyy-mm-dd', 'dd/mm/yyyy');
                $object->data_emissao_cnh = TDate::convertToMask($object->data_emissao_cnh, 'yyyy-mm-dd', 'dd/mm/yyyy');
                $object->data_validade_cnh = TDate::convertToMask($object->data_validade_cnh, 'yyyy-mm-dd', 'dd/mm/yyyy');
                $object->senha_portal = null;
                $object->senha_portal2 = null;

                $this->form->setData($object);
            } else {
                $this->form->clear();
            }

            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }
}

?>
