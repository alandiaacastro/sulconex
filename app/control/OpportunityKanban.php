<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Database\TTransaction;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Container\THBox;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TXMLBreadCrumb;

class OpportunityKanban extends TPage
{
    private $kanbanContainer;
    private $summaryContainer;
    private $filterContainer;
    private $loaded = false;

    public function __construct()
    {
        parent::__construct();

        $box = new TVBox;
        $box->style = 'width: 100%';

        if (is_file('menu.xml')) {
            $box->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        }

        // ---- Toolbar ----
        $actionForm = new TForm('form_opportunity_kanban_actions');

        $btnNew = new TButton('btn_new');
        $btnNew->setLabel('Nova Oportunidade');
        $btnNew->setImage('fa:plus green');
        $btnNew->setAction(new TAction(['OpportunityForm', 'onEdit']));

        $btnReload = new TButton('btn_reload');
        $btnReload->setLabel('Atualizar');
        $btnReload->setImage('fa:sync blue');
        $btnReload->setAction(new TAction([$this, 'onReload']));

        $btnList = new TButton('btn_list');
        $btnList->setLabel('Lista / Painel');
        $btnList->setImage('fa:list-alt orange');
        $btnList->setAction(new TAction(['OpportunityList', 'onReload']));

        $btnPropostas = new TButton('btn_propostas');
        $btnPropostas->setLabel('Propostas');
        $btnPropostas->setImage('fa:file-text purple');
        $btnPropostas->setAction(new TAction(['PropostaList', 'onReload']));

        $btnDash = new TButton('btn_dash');
        $btnDash->setLabel('Dashboard CRM');
        $btnDash->setImage('fa:chart-bar indigo');
        $btnDash->setAction(new TAction(['CrmDashboard', 'onReload']));

        $toolbar = new THBox;
        $toolbar->style = 'display:flex; gap:10px; flex-wrap:wrap; margin: 8px 0 12px 0;';
        $toolbar->add($btnNew);
        $toolbar->add($btnReload);
        $toolbar->add($btnList);
        $toolbar->add($btnPropostas);
        $toolbar->add($btnDash);

        $actionForm->add($toolbar);
        $actionForm->setFields([$btnNew, $btnReload, $btnList, $btnPropostas, $btnDash]);

        // ---- Filtro rápido ----
        $filterForm = new TForm('form_kanban_filter');
        $fResponsavel = new TEntry('f_responsavel');
        $fResponsavel->setSize('100%');
        $fResponsavel->setProperty('placeholder', 'Filtrar por responsável...');

        $fPrioridade = new TCombo('f_prioridade');
        $fPrioridade->addItems(['' => 'Todas prioridades', 'Alta' => '🔴 Alta', 'Media' => '🟡 Média', 'Baixa' => '🟢 Baixa']);
        $fPrioridade->setSize('100%');

        $btnFilter = new TButton('btn_filter');
        $btnFilter->setLabel('Filtrar');
        $btnFilter->setImage('fa:search blue');
        $btnFilter->setAction(new TAction([$this, 'onFilter']));

        $filterRow = new THBox;
        $filterRow->style = 'display:flex; gap:10px; align-items:flex-end; margin-bottom:10px; flex-wrap:wrap;';
        $lResp = new TLabel('Responsável:');
        $lResp->style = 'white-space:nowrap; font-size:12px; color:#64748b; margin-bottom:4px; display:block;';
        $wResp = new TVBox; $wResp->style = 'width:220px;'; $wResp->add($lResp); $wResp->add($fResponsavel);
        $lPrio = new TLabel('Prioridade:');
        $lPrio->style = 'white-space:nowrap; font-size:12px; color:#64748b; margin-bottom:4px; display:block;';
        $wPrio = new TVBox; $wPrio->style = 'width:180px;'; $wPrio->add($lPrio); $wPrio->add($fPrioridade);
        $filterRow->add($wResp);
        $filterRow->add($wPrio);
        $filterRow->add($btnFilter);

        $filterForm->add($filterRow);
        $filterForm->setFields([$fResponsavel, $fPrioridade, $btnFilter]);

        $this->summaryContainer = new TElement('div');
        $this->kanbanContainer  = new TElement('div');

        $box->add($actionForm);
        $box->add($filterForm);
        $box->add($this->summaryContainer);
        $box->add($this->kanbanContainer);

        parent::add($box);
    }

    public static function onFilter($param = null)
    {
        TSession::setValue('kanban_filter_responsavel', $param['f_responsavel'] ?? '');
        TSession::setValue('kanban_filter_prioridade',  $param['f_prioridade']  ?? '');
        TApplication::loadPage(__CLASS__, 'onReload', $param);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');

            $criteria = new TCriteria;
            $criteria->add(new TFilter('status', '!=', 'PERDIDO'));

            $fResp = TSession::getValue('kanban_filter_responsavel') ?? '';
            $fPrio = TSession::getValue('kanban_filter_prioridade')  ?? '';

            if (!empty($fResp)) {
                $criteria->add(new TFilter('responsible_name', 'like', "%{$fResp}%"));
            }
            if (!empty($fPrio)) {
                $criteria->add(new TFilter('prioridade', '=', $fPrio));
            }

            $repo  = new TRepository('Opportunity');
            $items = $repo->load($criteria) ?: [];

            $kanban = new TKanban;
            $kanban->setStageHeight('74vh');

            $editAction = new TAction(['OpportunityForm', 'onEdit']);
            $editAction->setParameter('id', '{id}');
            $kanban->addItemAction('Editar', $editAction, 'far:edit blue');

            $historyAction = new TAction([__CLASS__, 'onOpenHistory']);
            $historyAction->setParameter('id', '{id}');
            $kanban->addItemAction('Histórico', $historyAction, 'fa:history teal');

            $proposalAction = new TAction([__CLASS__, 'onGenerateProposta']);
            $proposalAction->setParameter('id', '{id}');
            $kanban->addItemAction('Gerar Proposta', $proposalAction, 'fa:file-text purple');

            $lostAction = new TAction([__CLASS__, 'onMarkLost']);
            $lostAction->setParameter('id', '{id}');
            $kanban->addItemAction('Marcar como Perdido', $lostAction, 'fa:times-circle red');

            $deleteAction = new TAction([__CLASS__, 'onDelete']);
            $deleteAction->setParameter('id', '{id}');
            $kanban->addItemAction('Excluir', $deleteAction, 'far:trash-alt red');

            $kanban->setItemDropAction(new TAction([__CLASS__, 'onUpdateItemDrop']));

            $stages = [
                'QUALIFICACAO' => 'Qualificação',
                'PROPOSTA'     => 'Proposta',
                'NEGOCIACAO'   => 'Negociação',
                'FECHAMENTO'   => 'Fechamento',
            ];

            $stageColors = [
                'QUALIFICACAO' => '#3b82f6',
                'PROPOSTA'     => '#6366f1',
                'NEGOCIACAO'   => '#f59e0b',
                'FECHAMENTO'   => '#22c55e',
            ];

            $statusCount = array_fill_keys(array_keys($stages), 0);
            $statusValue = array_fill_keys(array_keys($stages), 0.0);

            foreach ($stages as $id => $title) {
                $kanban->addStage($id, $title);
            }

            $today = date('Y-m-d');

            foreach ($items as $item) {
                $stage = self::normalizeStatus($item->status);
                $statusCount[$stage]++;
                $val = (float)($item->valor_estimado ?? 0);
                $statusValue[$stage] += $val;

                $color = $stageColors[$stage];

                $company     = htmlspecialchars((string)($item->company_name     ?: 'Sem empresa'),    ENT_QUOTES);
                $responsible = htmlspecialchars((string)($item->responsible_name ?: '—'),              ENT_QUOTES);
                $email       = htmlspecialchars((string)($item->email            ?: ''),               ENT_QUOTES);
                $phone       = htmlspecialchars((string)($item->phone            ?: ''),               ENT_QUOTES);

                // Badge de prioridade
                $prioBadge = '';
                if (!empty($item->prioridade)) {
                    $prioColors = ['Alta' => '#fee2e2;color:#991b1b', 'Media' => '#fef9c3;color:#92400e', 'Baixa' => '#dcfce7;color:#15803d'];
                    $prioIcons  = ['Alta' => '🔴', 'Media' => '🟡', 'Baixa' => '🟢'];
                    $pc = $prioColors[$item->prioridade] ?? '#f1f5f9;color:#475569';
                    $pi = $prioIcons[$item->prioridade] ?? '⚪';
                    $prioBadge = "<span style='font-size:10px;padding:2px 7px;border-radius:999px;background:{$pc};font-weight:700'>{$pi} {$item->prioridade}</span>";
                }

                // Valor
                $valStr = $val > 0 ? 'R$ ' . number_format($val, 2, ',', '.') : '';
                $valHtml = $val > 0 ? "<div style='font-size:12px;font-weight:700;color:#065f46;margin-top:6px;'>💰 {$valStr}</div>" : '';

                // Próximo contato
                $proximoHtml = '';
                if (!empty($item->proximo_contato)) {
                    $dt = $item->proximo_contato;
                    $isOverdue = $dt < $today;
                    $dtFmt = TDate::convertToMask($dt, 'yyyy-mm-dd', 'dd/mm/yyyy');
                    $dtColor = $isOverdue ? '#991b1b' : '#475569';
                    $dtIcon  = $isOverdue ? '⚠️' : '📅';
                    $proximoHtml = "<div style='font-size:11px;color:{$dtColor};margin-top:4px;'>{$dtIcon} Contato: {$dtFmt}</div>";
                }

                // Closing date
                $closingHtml = '';
                if (!empty($item->closing_date)) {
                    $dtFmt = TDate::convertToMask($item->closing_date, 'yyyy-mm-dd', 'dd/mm/yyyy');
                    $closingHtml = "<div style='font-size:11px;color:#1d4ed8;margin-top:2px;'>🏁 Fechar: {$dtFmt}</div>";
                }

                // Origem
                $origemHtml = '';
                if (!empty($item->origem_lead)) {
                    $origemHtml = "<div style='font-size:10px;color:#94a3b8;margin-top:3px;'>Origem: {$item->origem_lead}</div>";
                }

                $emailHtml = $email ? "<div style='font-size:11px;color:#475569;'>✉️ {$email}</div>" : '';
                $phoneHtml = $phone ? "<div style='font-size:11px;color:#475569;'>📞 {$phone}</div>" : '';

                $content = "
                <div style='background:{$color};color:#fff;padding:8px 10px;border-radius:8px 8px 0 0;font-weight:700;font-size:13px;display:flex;justify-content:space-between;align-items:center;'>
                    <span>{$company}</span>
                    {$prioBadge}
                </div>
                <div style='padding:10px;background:#fff;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;'>
                    <div style='font-size:12px;color:#64748b;margin-bottom:1px;'>Responsável</div>
                    <div style='font-size:13px;font-weight:600;color:#0f172a;margin-bottom:6px;'>👤 {$responsible}</div>
                    {$emailHtml}
                    {$phoneHtml}
                    {$valHtml}
                    {$closingHtml}
                    {$proximoHtml}
                    {$origemHtml}
                </div>";

                $kanban->addItem($item->id, $stage, '', $content, $color);
            }

            TTransaction::close();

            $total      = count($items);
            $totalValue = array_sum($statusValue);
            $active     = $total - $statusCount['FECHAMENTO'];
            $won        = $statusCount['FECHAMENTO'];
            $wonValue   = $statusValue['FECHAMENTO'];

            $this->summaryContainer->clearChildren();
            $this->summaryContainer->add($this->buildSummaryHtml($total, $active, $won, $statusCount, $totalValue, $wonValue, $statusValue));

            $this->kanbanContainer->clearChildren();
            $this->kanbanContainer->add($this->getKanbanCss());
            $this->kanbanContainer->add($kanban);

            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao carregar funil: ' . $e->getMessage());
        }
    }

    private function buildSummaryHtml($total, $active, $won, array $statusCount, $totalValue, $wonValue, array $statusValue)
    {
        $fmtVal = fn($v) => $v > 0 ? 'R$ ' . number_format($v, 0, ',', '.') : '—';

        $qual = (int)$statusCount['QUALIFICACAO'];
        $prop = (int)$statusCount['PROPOSTA'];
        $nego = (int)$statusCount['NEGOCIACAO'];

        $totalValFmt = $fmtVal($totalValue);
        $wonValFmt   = $fmtVal($wonValue);
        $negoValFmt  = $fmtVal($statusValue['NEGOCIACAO'] ?? 0);

        $taxaConv = $total > 0 ? number_format(($won / $total) * 100, 1) : '0.0';

        return <<<HTML
<style>
  .opp-kpi-grid { display:grid; grid-template-columns:repeat(8,minmax(0,1fr)); gap:10px; margin-bottom:12px; }
  .opp-kpi { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:10px 12px; box-shadow:0 2px 8px rgba(15,23,42,.06); }
  .opp-kpi .label { font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
  .opp-kpi .value { font-size:20px; font-weight:700; color:#0f172a; margin-top:2px; }
  .opp-kpi .sub { font-size:10px; color:#94a3b8; margin-top:2px; }
  @media (max-width:1500px){ .opp-kpi-grid { grid-template-columns:repeat(4,minmax(0,1fr)); } }
  @media (max-width:900px){ .opp-kpi-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>
<div class="opp-kpi-grid">
  <div class="opp-kpi"><div class="label">Total Leads</div><div class="value">{$total}</div><div class="sub">No funil ativo</div></div>
  <div class="opp-kpi"><div class="label">Valor Total Funil</div><div class="value" style="color:#1d4ed8;font-size:16px">{$totalValFmt}</div><div class="sub">Soma estimada</div></div>
  <div class="opp-kpi"><div class="label">Ativos</div><div class="value" style="color:#2563eb">{$active}</div><div class="sub">Em andamento</div></div>
  <div class="opp-kpi"><div class="label">Fechados</div><div class="value" style="color:#16a34a">{$won}</div><div class="sub">{$wonValFmt}</div></div>
  <div class="opp-kpi"><div class="label">Negociação R$</div><div class="value" style="color:#b45309;font-size:15px">{$negoValFmt}</div><div class="sub">{$nego} oport.</div></div>
  <div class="opp-kpi"><div class="label">Taxa Conversão</div><div class="value" style="color:#7c3aed">{$taxaConv}%</div><div class="sub">Leads → Fechados</div></div>
  <div class="opp-kpi"><div class="label">Qualificação</div><div class="value">{$qual}</div><div class="sub">Topo do funil</div></div>
  <div class="opp-kpi"><div class="label">Proposta</div><div class="value">{$prop}</div><div class="sub">Aguardando retorno</div></div>
</div>
HTML;
    }

    private function getKanbanCss()
    {
        return <<<HTML
<style>
  .kanban-stage { background:#f8fafc !important; border-radius:12px; padding:10px !important; border:1px solid #e2e8f0; }
  .kanban-stage-header { font-weight:700 !important; color:#0f172a !important; text-transform:uppercase; font-size:11px !important; letter-spacing:.05em; }
  .kanban-item { border-radius:10px !important; box-shadow:0 2px 8px rgba(15,23,42,.06) !important; overflow:hidden; }
</style>
HTML;
    }

    private static function normalizeStatus($status)
    {
        $value = strtoupper(trim((string)$status));

        $map = [
            'QUALIFICACAO' => 'QUALIFICACAO',
            'PROPOSTA'     => 'PROPOSTA',
            'NEGOCIACAO'   => 'NEGOCIACAO',
            'FECHAMENTO'   => 'FECHAMENTO',
            'PROSPECCAO'   => 'QUALIFICACAO',
            'PROSPECAO'    => 'QUALIFICACAO',
            'ANALISE'      => 'PROPOSTA',
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
            TToast::show('success', 'Status atualizado!', 'bottom right');
        } catch (Exception $e) {
            TTransaction::rollback();
            TToast::show('error', 'Erro: ' . $e->getMessage(), 'bottom right');
        }
    }

    public static function onGenerateProposta($param)
    {
        try {
            if (empty($param['id'])) {
                throw new Exception('Oportunidade não informada');
            }
            TTransaction::open('sample');
            $opp = new Opportunity((int)$param['id']);
            $opp->status = 'PROPOSTA';
            $opp->store();
            TTransaction::close();

            TApplication::loadPage('PropostaForm', 'onEdit', [
                'opportunity_id'      => $opp->id,
                'opportunity_company' => $opp->company_name,
                'opportunity_contact' => $opp->responsible_name,
                'opportunity_email'   => $opp->email,
                'opportunity_phone'   => $opp->phone,
                'opportunity_notes'   => $opp->notes,
            ]);
        } catch (Exception $e) {
            try { TTransaction::rollback(); } catch (Exception $e2) {}
            TToast::show('error', 'Não foi possível gerar proposta: ' . $e->getMessage(), 'bottom right');
        }
    }

    public static function onOpenHistory($param)
    {
        TApplication::loadPage('CrmActivityList', 'onLoad', ['opportunity_id' => $param['id']]);
    }

    public static function onMarkLost($param)
    {
        $actionYes = new TAction([__CLASS__, 'doMarkLost']);
        $actionYes->setParameters($param);
        new TQuestion('Marcar esta oportunidade como PERDIDA?', $actionYes);
    }

    public static function doMarkLost($param)
    {
        try {
            TTransaction::open('sample');
            $opp = new Opportunity($param['id']);
            $opp->status = 'PERDIDO';
            $opp->store();
            TTransaction::close();
            TToast::show('info', 'Oportunidade marcada como perdida.', 'bottom right');
            TApplication::loadPage(__CLASS__, 'onReload');
        } catch (Exception $e) {
            TTransaction::rollback();
            TToast::show('error', $e->getMessage(), 'bottom right');
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
            TToast::show('success', 'Oportunidade excluída!', 'bottom right');
            TApplication::loadPage(__CLASS__, 'onReload');
        } catch (Exception $e) {
            TTransaction::rollback();
            TToast::show('error', $e->getMessage(), 'bottom right');
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
