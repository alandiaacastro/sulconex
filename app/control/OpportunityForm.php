<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Database\TTransaction;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Wrapper\BootstrapFormBuilder;

class OpportunityForm extends TPage
{
    protected $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_Opportunity');
        $this->form->setFormTitle('Cadastro de Oportunidade');
        $this->form->setProperty('style', 'width: 100%');

        $id               = new TEntry('id');
        $company_name     = new TEntry('company_name');
        $status           = new TCombo('status');
        $responsible_name = new TEntry('responsible_name');
        $phone            = new TEntry('phone');
        $email            = new TEntry('email');
        $position         = new TEntry('position');
        $notes            = new TText('notes');
        $closing_date     = new TDate('closing_date');
        $proximo_contato  = new TDate('proximo_contato');
        $valor_estimado   = new TNumeric('valor_estimado', 2, ',', '.');
        $origem_lead      = new TCombo('origem_lead');
        $prioridade       = new TCombo('prioridade');

        $status->addItems([
            'QUALIFICACAO' => 'Qualificação',
            'PROPOSTA'     => 'Proposta',
            'NEGOCIACAO'   => 'Negociação',
            'FECHAMENTO'   => 'Fechamento',
            'PERDIDO'      => 'Perdido',
        ]);
        $status->setSize('100%');
        $status->setDefaultOption('Selecione');

        $origem_lead->addItems([
            ''            => 'Não informado',
            'Site'        => 'Site / Landing Page',
            'Indicacao'   => 'Indicação de cliente',
            'LinkedIn'    => 'LinkedIn',
            'Feira'       => 'Feira / Evento',
            'ColdCall'    => 'Cold Call / Prospecção ativa',
            'WhatsApp'    => 'WhatsApp / Redes sociais',
            'Parceiro'    => 'Parceiro comercial',
            'Outro'       => 'Outro',
        ]);
        $origem_lead->setSize('100%');

        $prioridade->addItems([
            ''      => 'Não definida',
            'Alta'  => '🔴 Alta',
            'Media' => '🟡 Média',
            'Baixa' => '🟢 Baixa',
        ]);
        $prioridade->setSize('100%');

        $id->setEditable(false);
        $id->setSize('100%');
        $company_name->setSize('100%');
        $responsible_name->setSize('100%');
        $phone->setSize('100%');
        $email->setSize('100%');
        $position->setSize('100%');
        $notes->setSize('100%', 80);
        $valor_estimado->setSize('100%');
        $valor_estimado->setProperty('placeholder', '0,00');

        $closing_date->setSize('100%');
        $closing_date->setMask('dd/mm/yyyy');
        $closing_date->setDatabaseMask('yyyy-mm-dd');

        $proximo_contato->setSize('100%');
        $proximo_contato->setMask('dd/mm/yyyy');
        $proximo_contato->setDatabaseMask('yyyy-mm-dd');

        $company_name->addValidation('Empresa', new TRequiredValidator);
        $status->addValidation('Status', new TRequiredValidator);
        $responsible_name->addValidation('Responsável', new TRequiredValidator);
        $email->addValidation('E-mail', new TEmailValidator);

        // Separador — Identificação
        $this->form->addContent(['<div style="font-weight:700;font-size:13px;color:#1e40af;border-bottom:2px solid #bfdbfe;padding-bottom:4px;margin:6px 0 10px">📋 Identificação</div>']);
        $this->form->addFields(
            [new TLabel('ID')], [$id, '20%'],
            [new TLabel('Empresa <font color="red">*</font>')], [$company_name]
        );
        $this->form->addFields(
            [new TLabel('Status <font color="red">*</font>')], [$status],
            [new TLabel('Responsável <font color="red">*</font>')], [$responsible_name]
        );
        $this->form->addFields(
            [new TLabel('Cargo')], [$position],
            [new TLabel('Prioridade')], [$prioridade]
        );

        // Separador — Contato
        $this->form->addContent(['<div style="font-weight:700;font-size:13px;color:#1e40af;border-bottom:2px solid #bfdbfe;padding-bottom:4px;margin:14px 0 10px">📞 Contato</div>']);
        $this->form->addFields(
            [new TLabel('Telefone')], [$phone],
            [new TLabel('E-mail')], [$email]
        );

        // Separador — Negócio
        $this->form->addContent(['<div style="font-weight:700;font-size:13px;color:#1e40af;border-bottom:2px solid #bfdbfe;padding-bottom:4px;margin:14px 0 10px">💰 Negócio</div>']);
        $this->form->addFields(
            [new TLabel('Valor Estimado (R$)')], [$valor_estimado],
            [new TLabel('Origem do Lead')], [$origem_lead]
        );
        $this->form->addFields(
            [new TLabel('Previsão de Fechamento')], [$closing_date],
            [new TLabel('Próximo Contato')], [$proximo_contato]
        );
        $this->form->addFields(
            [new TLabel('Observações')], [$notes]
        );

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Voltar', new TAction(['OpportunityKanban', 'onReload']), 'fa:arrow-left blue');

        $container = new TVBox;
        $container->style = 'width: 100%';
        if (is_file('menu.xml')) {
            $container->add(new TXMLBreadCrumb('menu.xml', 'OpportunityList'));
        }
        $container->add($this->form);

        parent::add($container);
    }

    public function onSave($param = null)
    {
        try {
            TTransaction::open('sample');

            $data = $this->form->getData();
            $this->form->validate();

            $object = isset($data->id) && $data->id ? new Opportunity((int)$data->id) : new Opportunity;
            $object->fromArray((array) $data);

            if (!empty($object->closing_date) && $object->status !== 'PERDIDO') {
                $object->status = 'FECHAMENTO';
            }

            if (empty($object->created_at)) {
                $object->created_at = date('Y-m-d H:i:s');
            }

            $object->store();
            $this->form->setData($object);

            TTransaction::close();

            new TMessage('info', 'Oportunidade salva com sucesso!', new TAction(['OpportunityKanban', 'onReload']));
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao salvar: ' . $e->getMessage());
        }
    }

    public function onEdit($param = null)
    {
        try {
            $id = $param['key'] ?? $param['id'] ?? null;

            if ($id) {
                TTransaction::open('sample');
                $object = new Opportunity($id);
                $this->form->setData($object);
                TTransaction::close();
            } else {
                $this->form->clear();
                $obj = new StdClass;
                $obj->status    = 'QUALIFICACAO';
                $obj->prioridade = 'Media';
                $this->form->setData($obj);

                // Pré-preenche dados de oportunidade se vindo do funil
                if (!empty($param['opportunity_company'])) {
                    $obj->company_name     = $param['opportunity_company'] ?? '';
                    $obj->responsible_name = $param['opportunity_contact']  ?? '';
                    $obj->email            = $param['opportunity_email']    ?? '';
                    $obj->phone            = $param['opportunity_phone']    ?? '';
                    $obj->notes            = $param['opportunity_notes']    ?? '';
                    $this->form->setData($obj);
                }
            }
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao carregar registro: ' . $e->getMessage());
        }
    }
}
