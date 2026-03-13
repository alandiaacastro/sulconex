<?php
/**
 * OpportunityForm — Formulário de cadastro/edição de Oportunidade (CRM)
 */
class OpportunityForm extends TPage
{
    protected $form;

    // Os 7 estágios do pipeline
    const STAGES = [
        'PROSPECTAR'            => 'Prospectar',
        'QUALIFICAR'            => 'Qualificar',
        'LEVANTAR_NECESSIDADES' => 'Levantar necessidades',
        'ELABORAR_PROPOSTA'     => 'Elaborar proposta',
        'FOLLOWUP'              => 'FollowUp (Cobrar feedback)',
        'INICIAR_NEGOCIACAO'    => 'Iniciar negociação',
        'NEGOCIACAO_FINALIZADA' => 'Negociação finalizada',
    ];

    const ORIGENS = [
        'Indicacao'     => 'Indicação',
        'Site'          => 'Site',
        'Linkedin'      => 'LinkedIn',
        'Feira'         => 'Feira / Evento',
        'Prospecção'    => 'Prospecção ativa',
        'Outro'         => 'Outro',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_Opportunity');
        $this->form->setFormTitle('Cadastro de Oportunidade');
        $this->form->setProperty('style', 'width: 100%');

        // ── Campos básicos ──
        $id              = new TEntry('id');
        $company_name    = new TEntry('company_name');
        $status          = new TCombo('status');
        $responsible_name = new TEntry('responsible_name');
        $vendedor        = new TEntry('vendedor');
        $phone           = new TEntry('phone');
        $email           = new TEntry('email');
        $position        = new TEntry('position');
        $origem_contato  = new TCombo('origem_contato');
        $valor           = new TEntry('valor');
        $data_inicio              = new TDate('data_inicio');
        $data_esperada_fechamento = new TDate('data_esperada_fechamento');
        $closing_date             = new TDate('closing_date');
        $notes           = new TText('notes');

        // ── Configurações ──
        $status->addItems(self::STAGES);
        $status->setSize('100%');
        $status->setDefaultOption('Selecione');

        $origem_contato->addItems(self::ORIGENS);
        $origem_contato->setSize('100%');
        $origem_contato->setDefaultOption('Selecione');

        $id->setEditable(false);

        foreach ([$id, $company_name, $responsible_name, $vendedor, $phone, $email, $position, $valor, $notes] as $f) {
            $f->setSize('100%');
        }

        foreach ([$data_inicio, $data_esperada_fechamento, $closing_date] as $d) {
            $d->setSize('100%');
            $d->setMask('dd/mm/yyyy');
        }

        // ── Validações ──
        $company_name->addValidation('Empresa', new TRequiredValidator);
        $status->addValidation('Estágio', new TRequiredValidator);
        $responsible_name->addValidation('Responsável', new TRequiredValidator);
        $email->addValidation('E-mail', new TEmailValidator);

        // ── Layout do formulário ──
        $this->form->addFields(
            [new TLabel('ID')], [$id],
            [new TLabel('Empresa <font color="red">*</font>')], [$company_name]
        );

        $this->form->addFields(
            [new TLabel('Estágio <font color="red">*</font>')], [$status],
            [new TLabel('Origem do contato')], [$origem_contato]
        );

        $this->form->addFields(
            [new TLabel('Responsável <font color="red">*</font>')], [$responsible_name],
            [new TLabel('Vendedor')], [$vendedor]
        );

        $this->form->addFields(
            [new TLabel('Telefone')], [$phone],
            [new TLabel('E-mail')], [$email]
        );

        $this->form->addFields(
            [new TLabel('Cargo')], [$position],
            [new TLabel('Valor estimado (R$)')], [$valor]
        );

        $this->form->addFields(
            [new TLabel('Data de início')], [$data_inicio],
            [new TLabel('Data esperada fechamento')], [$data_esperada_fechamento]
        );

        $this->form->addFields(
            [new TLabel('Data de fechamento')], [$closing_date],
            [], []
        );

        $this->form->addFields([new TLabel('Observações')], [$notes]);

        // ── Ações ──
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addAction('Voltar', new TAction(['OpportunityKanban', 'onReload']), 'fa:arrow-left blue');

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        parent::add($container);
    }

    public function onSave($param = null)
    {
        try {
            TTransaction::open('sample');

            $data = $this->form->getData();
            $this->form->validate();

            $object = new Opportunity;
            $object->fromArray((array) $data);

            // Converte datas de dd/mm/yyyy para yyyy-mm-dd
            foreach (['data_inicio', 'data_esperada_fechamento', 'closing_date'] as $df) {
                if (!empty($object->$df)) {
                    $object->$df = TDate::date2us($object->$df);
                }
            }

            // Se data de fechamento preenchida → finalizada
            if (!empty($object->closing_date)) {
                $object->status = 'NEGOCIACAO_FINALIZADA';
            }

            $object->store();

            // Registra no histórico
            $hist = new OpportunityHistory;
            $hist->opportunity_id = $object->id;
            $hist->evento = 'Negociação ' . ($data->id ? 'atualizada' : 'criada') . ' — estágio: ' . (self::STAGES[$object->status] ?? $object->status);
            $hist->data_evento = date('Y-m-d H:i:s');
            $hist->store();

            $this->form->setData($object);
            TTransaction::close();

            new TMessage('info', 'Registro salvo com sucesso!', new TAction(['OpportunityKanban', 'onReload']));

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

                // Converte datas para exibição dd/mm/yyyy
                foreach (['data_inicio', 'data_esperada_fechamento', 'closing_date'] as $df) {
                    if (!empty($object->$df)) {
                        $object->$df = TDate::date2br($object->$df);
                    }
                }

                $this->form->setData($object);
                TTransaction::close();
            } else {
                $this->form->clear();
                $obj = new StdClass;
                $obj->status = 'PROSPECTAR';
                $obj->data_inicio = date('d/m/Y');
                $this->form->setData($obj);
            }
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao carregar: ' . $e->getMessage());
        }
    }
}
