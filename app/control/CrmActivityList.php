<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\THBox;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Dialog\TToast;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TText;
use Adianti\Wrapper\BootstrapFormBuilder;

class CrmActivityList extends TPage
{
    private $container;
    private $loaded = false;
    private $opportunityId;

    public function __construct()
    {
        parent::__construct();

        $box = new TVBox;
        $box->style = 'width:100%';

        $this->container = new TElement('div');
        $box->add($this->container);

        parent::add($box);
    }

    public static function onLoad($param = null)
    {
        $oppId = (int)($param['opportunity_id'] ?? 0);
        if (!$oppId) {
            new TMessage('error', 'Oportunidade não informada');
            return;
        }
        TApplication::loadPage(__CLASS__, 'onRender', ['opportunity_id' => $oppId]);
    }

    public function onRender($param = null)
    {
        $oppId = (int)($param['opportunity_id'] ?? 0);
        if (!$oppId) return;

        try {
            TTransaction::open('sample');

            $opp = new Opportunity($oppId);

            $criteria = new TCriteria;
            $criteria->add(new TFilter('opportunity_id', '=', $oppId));
            $criteria->setProperty('order', 'data_atividade desc');

            $repo = new TRepository('OpportunityActivity');
            $atividades = $repo->load($criteria) ?: [];

            TTransaction::close();

            $companyName = htmlspecialchars((string)($opp->company_name ?? ''));

            // Formulário de nova atividade
            $form = new BootstrapFormBuilder('form_crm_activity');
            $form->setFormTitle("Histórico de Atividades — {$companyName}");

            $hidOppId = new THidden('opportunity_id');
            $tipo     = new TCombo('tipo');
            $descricao = new TText('descricao');
            $data_atividade = new TDate('data_atividade');
            $usuario  = new TEntry('usuario');

            $tipo->addItems([
                'Ligação'    => '📞 Ligação',
                'E-mail'     => '✉️ E-mail',
                'Reunião'    => '🤝 Reunião',
                'Visita'     => '🚗 Visita',
                'WhatsApp'   => '💬 WhatsApp',
                'Follow-up'  => '🔔 Follow-up',
                'Proposta'   => '📋 Proposta enviada',
                'Negociação' => '💰 Negociação',
                'Outro'      => '📝 Outro',
            ]);
            $tipo->setSize('100%');
            $tipo->addValidation('Tipo', new TRequiredValidator);

            $descricao->setSize('100%', 70);
            $descricao->setProperty('placeholder', 'Descreva o que foi tratado...');
            $descricao->addValidation('Descrição', new TRequiredValidator);

            $data_atividade->setSize('100%');
            $data_atividade->setMask('dd/mm/yyyy');
            $data_atividade->setDatabaseMask('yyyy-mm-dd');

            $usuario->setSize('100%');
            $usuario->setProperty('placeholder', 'Seu nome...');

            $hidOppId->setValue($oppId);

            $form->addFields([new TLabel('Tipo <font color="red">*</font>')], [$tipo], [new TLabel('Data')], [$data_atividade], [new TLabel('Usuário')], [$usuario]);
            $form->addFields([new TLabel('Descrição <font color="red">*</font>')], [$descricao]);

            $form->addAction('Registrar Atividade', new TAction([$this, 'onSave']), 'fa:save green');
            $form->addAction('Voltar ao Kanban', new TAction(['OpportunityKanban', 'onReload']), 'fa:arrow-left blue');

            // Pré-preenche data com hoje
            $obj = new StdClass;
            $obj->opportunity_id  = $oppId;
            $obj->data_atividade  = date('d/m/Y');
            $form->setData($obj);

            // Timeline de atividades
            $timelineHtml = $this->buildTimeline($atividades, $oppId);

            $this->container->clearChildren();
            $this->container->add($form);
            $this->container->add($timelineHtml);

            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function buildTimeline(array $atividades, $oppId)
    {
        if (empty($atividades)) {
            return "<div style='padding:20px;text-align:center;color:#94a3b8;font-size:14px;margin-top:16px;'>Nenhuma atividade registrada ainda.</div>";
        }

        $tipoIcons = [
            'Ligação'    => '📞',
            'E-mail'     => '✉️',
            'Reunião'    => '🤝',
            'Visita'     => '🚗',
            'WhatsApp'   => '💬',
            'Follow-up'  => '🔔',
            'Proposta'   => '📋',
            'Negociação' => '💰',
            'Outro'      => '📝',
        ];

        $tipoColors = [
            'Ligação'    => '#dbeafe;color:#1e40af',
            'E-mail'     => '#dcfce7;color:#15803d',
            'Reunião'    => '#f3e8ff;color:#7c3aed',
            'Visita'     => '#fef9c3;color:#92400e',
            'WhatsApp'   => '#dcfce7;color:#15803d',
            'Follow-up'  => '#fee2e2;color:#991b1b',
            'Proposta'   => '#e0e7ff;color:#4338ca',
            'Negociação' => '#fef3c7;color:#b45309',
            'Outro'      => '#f1f5f9;color:#475569',
        ];

        $items = '';
        foreach ($atividades as $at) {
            $tipo  = htmlspecialchars((string)($at->tipo ?? 'Outro'));
            $icon  = $tipoIcons[$at->tipo] ?? '📝';
            $color = $tipoColors[$at->tipo] ?? '#f1f5f9;color:#475569';
            $desc  = nl2br(htmlspecialchars((string)($at->descricao ?? '')));
            $data  = '';
            if (!empty($at->data_atividade)) {
                try {
                    $data = TDate::convertToMask($at->data_atividade, 'yyyy-mm-dd', 'dd/mm/yyyy');
                } catch (Exception $e) {
                    $data = $at->data_atividade;
                }
            }
            $usuario = htmlspecialchars((string)($at->usuario ?? ''));
            $userHtml = $usuario ? " <span style='color:#94a3b8'>· {$usuario}</span>" : '';

            $deleteParam = json_encode(['id' => $at->id, 'opportunity_id' => $oppId]);
            $deleteAction = TAction::serialize(['CrmActivityList', 'onDeleteActivity'], ['id' => $at->id, 'opportunity_id' => $oppId]);

            $items .= <<<HTML
<div style="display:flex;gap:14px;margin-bottom:14px;">
  <div style="flex-shrink:0;width:36px;height:36px;border-radius:50%;background:{$color};display:flex;align-items:center;justify-content:center;font-size:16px;margin-top:2px;">{$icon}</div>
  <div style="flex:1;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;box-shadow:0 1px 4px rgba(15,23,42,.05);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
      <span style="font-size:12px;font-weight:700;padding:2px 8px;border-radius:999px;background:{$color}">{$tipo}</span>
      <span style="font-size:11px;color:#94a3b8;">{$data}{$userHtml}</span>
    </div>
    <div style="font-size:13px;color:#0f172a;line-height:1.5">{$desc}</div>
  </div>
</div>
HTML;
        }

        return <<<HTML
<div style="margin-top:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;">
  <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:14px;border-bottom:1px solid #e2e8f0;padding-bottom:8px;">
    📋 Histórico de Atividades ({{ count }})
  </div>
  {$items}
</div>
HTML;
    }

    public function onSave($param = null)
    {
        try {
            $oppId = (int)($param['opportunity_id'] ?? 0);
            if (!$oppId) throw new Exception('Oportunidade não informada');

            $form = new BootstrapFormBuilder('form_crm_activity');
            $form->addField(new TCombo('tipo'));
            $form->addField(new TText('descricao'));
            $form->addField(new TDate('data_atividade'));
            $form->addField(new TEntry('usuario'));
            $form->validate();

            $data = $this->getFormData($param);

            TTransaction::open('sample');

            $activity = new OpportunityActivity;
            $activity->opportunity_id = $oppId;
            $activity->tipo           = $param['tipo'] ?? '';
            $activity->descricao      = $param['descricao'] ?? '';
            $activity->data_atividade = !empty($param['data_atividade'])
                ? TDate::convertToMask($param['data_atividade'], 'dd/mm/yyyy', 'yyyy-mm-dd')
                : date('Y-m-d');
            $activity->usuario        = $param['usuario'] ?? '';
            $activity->created_at     = date('Y-m-d H:i:s');
            $activity->store();

            TTransaction::close();

            TToast::show('success', 'Atividade registrada!', 'bottom right');
            TApplication::loadPage(__CLASS__, 'onRender', ['opportunity_id' => $oppId]);

        } catch (Exception $e) {
            try { TTransaction::rollback(); } catch (Exception $e2) {}
            new TMessage('error', 'Erro ao salvar: ' . $e->getMessage());
        }
    }

    private function getFormData($param)
    {
        return (object)$param;
    }

    public static function onDeleteActivity($param)
    {
        $action = new TAction([__CLASS__, 'doDeleteActivity']);
        $action->setParameters($param);
        new TQuestion('Excluir esta atividade?', $action);
    }

    public static function doDeleteActivity($param)
    {
        try {
            TTransaction::open('sample');
            $a = new OpportunityActivity((int)$param['id']);
            $oppId = $a->opportunity_id;
            $a->delete();
            TTransaction::close();
            TToast::show('success', 'Atividade excluída.', 'bottom right');
            TApplication::loadPage(__CLASS__, 'onRender', ['opportunity_id' => $oppId]);
        } catch (Exception $e) {
            TTransaction::rollback();
            TToast::show('error', $e->getMessage(), 'bottom right');
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onRender($_GET);
        }
        parent::show();
    }
}
