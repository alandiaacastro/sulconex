<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\THBox;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Util\TXMLBreadCrumb;

class OpportunityKanban extends TPage
{
    private $kanbanContainer;
    private $summaryContainer;
    private $loaded = false;

    public function __construct()
    {
        parent::__construct();

        $box = new TVBox;
        $box->style = 'width: 100%';

        if (is_file('menu.xml')) {
            $box->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        }

        $actionForm = new TForm('form_opportunity_kanban_actions');

        $btnNew = new TButton('btn_new');
        $btnNew->setLabel('Nova Oportunidade');
        $btnNew->setImage('fa:plus green');
        $btnNew->setAction(new TAction(['OpportunityForm', 'onEdit']));

        $btnReload = new TButton('btn_reload');
        $btnReload->setLabel('Atualizar Funil');
        $btnReload->setImage('fa:sync blue');
        $btnReload->setAction(new TAction([$this, 'onReload']));

        $btnList = new TButton('btn_list');
        $btnList->setLabel('Ver Painel e Leads');
        $btnList->setImage('fa:list-alt orange');
        $btnList->setAction(new TAction(['OpportunityList', 'onReload']));

        $btnPropostas = new TButton('btn_propostas');
        $btnPropostas->setLabel('Abrir Propostas');
        $btnPropostas->setImage('fa:file-text purple');
        $btnPropostas->setAction(new TAction(['PropostaList', 'onReload']));

        $toolbar = new THBox;
        $toolbar->style = 'display:flex; gap:10px; margin: 8px 0 12px 0;';
        $toolbar->add($btnNew);
        $toolbar->add($btnReload);
        $toolbar->add($btnList);
        $toolbar->add($btnPropostas);

        $actionForm->add($toolbar);
        $actionForm->setFields([$btnNew, $btnReload, $btnList, $btnPropostas]);

        $this->summaryContainer = new TElement('div');
        $this->kanbanContainer = new TElement('div');

        $box->add($actionForm);
        $box->add($this->summaryContainer);
        $box->add($this->kanbanContainer);

        parent::add($box);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');

            $repo = new TRepository('Opportunity');
            $items = $repo->load(new TCriteria()) ?: [];

            $kanban = new TKanban;
            $kanban->setStageHeight('74vh');

            $editAction = new TAction(['OpportunityForm', 'onEdit']);
            $editAction->setParameter('id', '{id}');
            $kanban->addItemAction('Editar', $editAction, 'far:edit blue');

            $proposalAction = new TAction([__CLASS__, 'onGenerateProposta']);
            $proposalAction->setParameter('id', '{id}');
            $kanban->addItemAction('Gerar Proposta', $proposalAction, 'fa:file-text purple');

            $deleteAction = new TAction([__CLASS__, 'onDelete']);
            $deleteAction->setParameter('id', '{id}');
            $kanban->addItemAction('Excluir', $deleteAction, 'far:trash-alt red');

            $kanban->setItemDropAction(new TAction([__CLASS__, 'onUpdateItemDrop']));

            $stages = [
                'QUALIFICACAO' => 'Qualificacao',
                'PROPOSTA' => 'Proposta',
                'NEGOCIACAO' => 'Negociacao',
                'FECHAMENTO' => 'Fechamento',
            ];

            $stageColors = [
                'QUALIFICACAO' => '#3b82f6',
                'PROPOSTA' => '#6366f1',
                'NEGOCIACAO' => '#f59e0b',
                'FECHAMENTO' => '#22c55e',
            ];

            $statusCount = [
                'QUALIFICACAO' => 0,
                'PROPOSTA' => 0,
                'NEGOCIACAO' => 0,
                'FECHAMENTO' => 0,
            ];

            foreach ($stages as $id => $title) {
                $kanban->addStage($id, $title);
            }

            foreach ($items as $item) {
                $stage = self::normalizeStatus($item->status);
                $statusCount[$stage]++;
                $color = $stageColors[$stage];

                $company = htmlspecialchars((string) ($item->company_name ?: 'Sem empresa'));
                $responsible = htmlspecialchars((string) ($item->responsible_name ?: 'Sem responsavel'));
                $email = htmlspecialchars((string) ($item->email ?: 'Sem e-mail'));
                $phone = htmlspecialchars((string) ($item->phone ?: 'Sem telefone'));

                $content = "
                <div style='background: {$color}; color:#fff; padding: 8px 10px; border-radius: 8px 8px 0 0; font-weight: 700;'>
                    {$company}
                </div>
                <div style='padding: 10px; background:#fff; border:1px solid #e2e8f0; border-top:0; border-radius: 0 0 8px 8px;'>
                    <div style='font-size:12px; color:#64748b; margin-bottom:4px;'>Responsavel</div>
                    <div style='font-size:13px; font-weight:600; color:#0f172a; margin-bottom:8px;'>{$responsible}</div>
                    <div style='font-size:12px; color:#475569; margin-bottom:4px;'>{$email}</div>
                    <div style='font-size:12px; color:#475569;'>{$phone}</div>
                </div>";

                $kanban->addItem(
                    $item->id,
                    $stage,
                    '',
                    $content,
                    $color
                );
            }

            TTransaction::close();

            $total = count($items);
            $active = $total - $statusCount['FECHAMENTO'];
            $won = $statusCount['FECHAMENTO'];

            $this->summaryContainer->clearChildren();
            $this->summaryContainer->add($this->buildSummaryHtml($total, $active, $won, $statusCount));

            $this->kanbanContainer->clearChildren();
            $this->kanbanContainer->add($this->getKanbanCss());
            $this->kanbanContainer->add($kanban);

            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao carregar funil: ' . $e->getMessage());
        }
    }

    private function buildSummaryHtml($total, $active, $won, array $statusCount)
    {
        $qual = (int) $statusCount['QUALIFICACAO'];
        $prop = (int) $statusCount['PROPOSTA'];
        $nego = (int) $statusCount['NEGOCIACAO'];

        return <<<HTML
<style>
  .opp-kpi-grid { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:12px; margin-bottom: 12px; }
  .opp-kpi { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:12px; box-shadow:0 2px 8px rgba(15,23,42,.06); }
  .opp-kpi .label { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
  .opp-kpi .value { font-size:23px; font-weight:700; color:#0f172a; margin-top:2px; }
  @media (max-width: 1300px){ .opp-kpi-grid { grid-template-columns: repeat(3, minmax(0,1fr)); } }
  @media (max-width: 800px){ .opp-kpi-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
</style>
<div class="opp-kpi-grid">
  <div class="opp-kpi"><div class="label">Total Leads</div><div class="value">{$total}</div></div>
  <div class="opp-kpi"><div class="label">Ativos</div><div class="value" style="color:#2563eb">{$active}</div></div>
  <div class="opp-kpi"><div class="label">Fechados</div><div class="value" style="color:#16a34a">{$won}</div></div>
  <div class="opp-kpi"><div class="label">Qualificacao</div><div class="value">{$qual}</div></div>
  <div class="opp-kpi"><div class="label">Proposta</div><div class="value">{$prop}</div></div>
  <div class="opp-kpi"><div class="label">Negociacao</div><div class="value">{$nego}</div></div>
</div>
HTML;
    }

    private function getKanbanCss()
    {
        return <<<HTML
<style>
  .kanban-stage { background: #f8fafc !important; border-radius: 12px; padding: 10px !important; border: 1px solid #e2e8f0; }
  .kanban-stage-header { font-weight: 700 !important; color: #0f172a !important; text-transform: uppercase; font-size: 11px !important; letter-spacing: .05em; }
  .kanban-item { border-radius: 10px !important; box-shadow: 0 2px 8px rgba(15,23,42,.06) !important; overflow: hidden; }
</style>
HTML;
    }

    private static function normalizeStatus($status)
    {
        $value = strtoupper(trim((string) $status));

        $map = [
            'QUALIFICACAO' => 'QUALIFICACAO',
            'PROPOSTA' => 'PROPOSTA',
            'NEGOCIACAO' => 'NEGOCIACAO',
            'FECHAMENTO' => 'FECHAMENTO',
            'PROSPECCAO' => 'QUALIFICACAO',
            'PROSPECAO' => 'QUALIFICACAO',
            'NEGOCIACAO ' => 'NEGOCIACAO',
            'ANALISE' => 'PROPOSTA',
        ];

        return $map[$value] ?? 'QUALIFICACAO';
    }

    public static function onUpdateItemDrop($param)
    {
        try {
            TTransaction::open('sample');

            $opp = new Opportunity($param['id']);
            $opp->status = self::normalizeStatus($param['stage_id'] ?? 'QUALIFICACAO');
            $opp->store();

            TTransaction::close();
            TToast::show('success', 'Status atualizado com sucesso!', 'bottom right');
        } catch (Exception $e) {
            TTransaction::rollback();
            TToast::show('error', 'Erro ao atualizar status: ' . $e->getMessage(), 'bottom right');
        }
    }


    public static function onGenerateProposta($param)
    {
        try {
            if (empty($param['id'])) {
                throw new Exception('Oportunidade nao informada');
            }

            TTransaction::open('sample');
            $opp = new Opportunity((int) $param['id']);
            $opp->status = 'PROPOSTA';
            $opp->store();
            TTransaction::close();

            $proposalParam = [
                'opportunity_id' => $opp->id,
                'opportunity_company' => $opp->company_name,
                'opportunity_contact' => $opp->responsible_name,
                'opportunity_email' => $opp->email,
                'opportunity_phone' => $opp->phone,
                'opportunity_notes' => $opp->notes,
            ];

            TApplication::loadPage('PropostaForm', 'onEdit', $proposalParam);
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $rollbackException) {
            }
            TToast::show('error', 'Nao foi possivel gerar proposta: ' . $e->getMessage(), 'bottom right');
        }
    }
    public static function onDelete($param)
    {
        $actionYes = new TAction([__CLASS__, 'Delete']);
        $actionYes->setParameters($param);
        new TQuestion('Deseja realmente excluir esta oportunidade?', $actionYes);
    }

    public static function Delete($param)
    {
        try {
            TTransaction::open('sample');

            $opp = new Opportunity($param['id']);
            $opp->delete();

            TTransaction::close();

            TToast::show('success', 'Oportunidade excluida com sucesso!', 'bottom right');
            TApplication::loadPage(__CLASS__, 'onReload');
        } catch (Exception $e) {
            TTransaction::rollback();
            TToast::show('error', 'Erro ao excluir oportunidade: ' . $e->getMessage(), 'bottom right');
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload();
        }
        parent::show();
    }
}
