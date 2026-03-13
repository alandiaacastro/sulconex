<?php
/**
 * OpportunityView — Tela de detalhe de uma negociação (CRM)
 * Exibe: pipeline stepper, cards de info, timeline de histórico e abas (Atividades, Arquivos, Observações).
 */
class OpportunityView extends TPage
{
    // Definição dos 7 estágios do pipeline na ordem correta
    const STAGES = [
        'PROSPECTAR'            => 'Prospectar',
        'QUALIFICAR'            => 'Qualificar',
        'LEVANTAR_NECESSIDADES' => 'Levantar necessidades',
        'ELABORAR_PROPOSTA'     => 'Elaborar proposta',
        'FOLLOWUP'              => 'FollowUp (Cobrar feed...)',
        'INICIAR_NEGOCIACAO'    => 'Iniciar negociação',
        'NEGOCIACAO_FINALIZADA' => 'Negociação finalizada',
    ];

    // Cor de cada estágio (para badges no timeline)
    const STAGE_COLORS = [
        'PROSPECTAR'            => '#6c757d',
        'QUALIFICAR'            => '#17a2b8',
        'LEVANTAR_NECESSIDADES' => '#3498db',
        'ELABORAR_PROPOSTA'     => '#fd7e14',
        'FOLLOWUP'              => '#e83e8c',
        'INICIAR_NEGOCIACAO'    => '#6f42c1',
        'NEGOCIACAO_FINALIZADA' => '#28a745',
    ];

    // Ícones do timeline por tipo de evento
    const TIPO_ICONS = [
        'stage_change' => ['icon' => 'fas fa-arrow-right', 'bg' => '#3498db'],
        'note'         => ['icon' => 'fas fa-sticky-note', 'bg' => '#fd7e14'],
        'email'        => ['icon' => 'fas fa-envelope',    'bg' => '#6f42c1'],
        'file'         => ['icon' => 'fas fa-paperclip',   'bg' => '#17a2b8'],
        'call'         => ['icon' => 'fas fa-phone',       'bg' => '#28a745'],
        'task'         => ['icon' => 'fas fa-tasks',       'bg' => '#e83e8c'],
    ];

    public function __construct($param = null)
    {
        parent::__construct();

        TPage::include_css('app/resources/css/opportunity_view.css');

        $key = $param['key'] ?? $_GET['key'] ?? null;

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', 'OpportunityKanban'));

        if (!$key) {
            $vbox->add($this->buildAlert('Nenhuma negociação selecionada.'));
            parent::add($vbox);
            return;
        }

        try {
            TTransaction::open('sample');

            $opp = new Opportunity($key);

            // Carrega histórico de estágios (opportunity_history)
            $crit = new TCriteria;
            $crit->add(new TFilter('opportunity_id', '=', $key));
            $crit->setProperty('order', 'data_evento ASC');
            $history = (new TRepository('OpportunityHistory'))->load($crit) ?? [];

            // Carrega atividades (opportunity_activity)
            $crit2 = new TCriteria;
            $crit2->add(new TFilter('opportunity_id', '=', $key));
            $crit2->setProperty('order', 'created_at ASC');
            $activities = (new TRepository('OpportunityActivity'))->load($crit2) ?? [];

            TTransaction::close();

            // Monta a página
            $vbox->add($this->buildHeader($opp));
            $vbox->add($this->buildPipeline($opp->status));
            $vbox->add($this->buildInfoGrid($opp));
            $vbox->add($this->buildBody($opp, $history, $activities));

        } catch (Exception $e) {
            TTransaction::rollback();
            $vbox->add($this->buildAlert('Erro ao carregar negociação: ' . $e->getMessage()));
        }

        parent::add($vbox);
    }

    // ─────────────────────────────────────────────
    //  Seção: Header (título + botões)
    // ─────────────────────────────────────────────
    private function buildHeader($opp)
    {
        $el = new TElement('div');
        $el->class = 'opp-header';

        $title = new TElement('h4');
        $title->add("Negociação #{$opp->id} — {$opp->company_name}");

        // Botão Enviar email
        $btnEmail = new TElement('a');
        $btnEmail->class = 'btn btn-sm btn-outline-primary';
        $btnEmail->href  = 'javascript:void(0)';
        $btnEmail->{'onclick'} = "Engine.call('EmailComposerView','onLoadFromOpportunity',{opportunity_id:{$opp->id}})";
        $btnEmail->add('<i class="fas fa-envelope me-1"></i> Enviar email');

        // Botão Editar
        $btnEdit = new TElement('a');
        $btnEdit->class = 'btn btn-sm btn-outline-warning';
        $btnEdit->href  = 'javascript:void(0)';
        $btnEdit->{'onclick'} = "Engine.call('OpportunityForm','onEdit',{key:{$opp->id}})";
        $btnEdit->add('<i class="fas fa-edit me-1"></i> Editar');

        // Botão Excluir
        $btnDel = new TElement('a');
        $btnDel->class = 'btn btn-sm btn-outline-danger';
        $btnDel->href  = 'javascript:void(0)';
        $btnDel->{'onclick'} = "Engine.call('OpportunityKanban','onDelete',{id:{$opp->id}})";
        $btnDel->add('<i class="fas fa-trash me-1"></i> Excluir');

        $actions = new TElement('div');
        $actions->class = 'opp-header-actions';
        $actions->add($btnEmail);
        $actions->add($btnEdit);
        $actions->add($btnDel);

        $el->add($title);
        $el->add($actions);

        return $el;
    }

    // ─────────────────────────────────────────────
    //  Seção: Pipeline stepper
    // ─────────────────────────────────────────────
    private function buildPipeline($currentStatus)
    {
        $stageKeys = array_keys(self::STAGES);
        $currentIdx = array_search($currentStatus, $stageKeys);
        if ($currentIdx === false) $currentIdx = -1;

        $wrapper = new TElement('div');
        $wrapper->class = 'opp-pipeline';

        foreach (self::STAGES as $key => $label) {
            $idx = array_search($key, $stageKeys);
            $step = new TElement('div');
            $step->class = 'opp-step';

            if ($idx < $currentIdx) {
                $step->class .= ' done';
            } elseif ($idx === $currentIdx) {
                $step->class .= ' active';
            }

            $step->add(htmlspecialchars($label));
            $wrapper->add($step);
        }

        return $wrapper;
    }

    // ─────────────────────────────────────────────
    //  Seção: Grid de informações
    // ─────────────────────────────────────────────
    private function buildInfoGrid($opp)
    {
        $grid = new TElement('div');
        $grid->class = 'opp-info-grid';

        $stageName = self::STAGES[$opp->status] ?? $opp->status ?? '—';
        $stageColor = self::STAGE_COLORS[$opp->status] ?? '#3498db';

        $badge = "<span class='opp-stage-badge' style='background:{$stageColor}'>" . htmlspecialchars($stageName) . "</span>";

        $valor = !empty($opp->valor)
            ? 'R$ ' . number_format((float)$opp->valor, 2, ',', '.')
            : '—';

        $items = [
            ['Cliente',                  htmlspecialchars($opp->company_name ?? '—')],
            ['Vendedor',                 htmlspecialchars($opp->vendedor ?? '—')],
            ['Etapa',                    $badge],
            ['Data de início',           $this->fmtDate($opp->data_inicio)],
            ['Data esperada fechamento', $this->fmtDate($opp->data_esperada_fechamento)],
            ['Data de fechamento',       $this->fmtDate($opp->closing_date)],
            ['Origem do contato',        htmlspecialchars($opp->origem_contato ?? '—')],
            ['Valor estimado',           $valor],
            ['Responsável',              htmlspecialchars($opp->responsible_name ?? '—')],
        ];

        foreach ($items as [$label, $value]) {
            $item = new TElement('div');
            $item->class = 'opp-info-item';

            $lbl = new TElement('div');
            $lbl->class = 'opp-info-label';
            $lbl->add($label);

            $val = new TElement('div');
            $val->class = 'opp-info-value';
            $val->add($value);

            $item->add($lbl);
            $item->add($val);
            $grid->add($item);
        }

        return $grid;
    }

    // ─────────────────────────────────────────────
    //  Seção: Body (timeline + tabs)
    // ─────────────────────────────────────────────
    private function buildBody($opp, $history, $activities)
    {
        $body = new TElement('div');
        $body->class = 'opp-body';

        $body->add($this->buildTimeline($history, $activities));
        $body->add($this->buildTabs($opp, $activities));

        return $body;
    }

    // ─────────────────────────────────────────────
    //  Timeline
    // ─────────────────────────────────────────────
    private function buildTimeline($history, $activities)
    {
        $panel = new TElement('div');

        $panelTitle = new TElement('h6');
        $panelTitle->style = 'font-weight:700; color:#555; margin-bottom:12px;';
        $panelTitle->add('<i class="fas fa-history me-2" style="color:#3498db"></i>Histórico');
        $panel->add($panelTitle);

        $timeline = new TElement('div');
        $timeline->class = 'opp-timeline';

        // Agrupa histórico por data
        $grouped = [];
        foreach ($history as $h) {
            $date = substr($h->data_evento, 0, 10);
            $grouped[$date][] = ['tipo' => 'stage_change', 'descricao' => $h->evento, 'hora' => substr($h->data_evento, 11, 5)];
        }
        foreach ($activities as $a) {
            $date = substr($a->created_at, 0, 10);
            $grouped[$date][] = ['tipo' => $a->tipo ?? 'note', 'descricao' => $a->titulo ?? $a->descricao, 'hora' => substr($a->created_at, 11, 5)];
        }

        // Ordena por data DESC
        krsort($grouped);

        if (empty($grouped)) {
            $empty = new TElement('p');
            $empty->style = 'color:#aaa; font-size:13px; text-align:center; margin-top:20px;';
            $empty->add('<i class="fas fa-stream"></i> Nenhum histórico ainda.');
            $timeline->add($empty);
        }

        foreach ($grouped as $date => $events) {
            $dateLbl = new TElement('div');
            $dateLbl->class = 'opp-timeline-date';
            $dateLbl->add($this->fmtDate($date));
            $timeline->add($dateLbl);

            foreach ($events as $ev) {
                $tipo  = $ev['tipo'] ?? 'note';
                $iconCfg = self::TIPO_ICONS[$tipo] ?? self::TIPO_ICONS['note'];

                $item = new TElement('div');
                $item->class = 'opp-timeline-item';

                $icon = new TElement('div');
                $icon->class = 'opp-timeline-icon';
                $icon->style = "background:{$iconCfg['bg']}";
                $icon->add("<i class='{$iconCfg['icon']}'></i>");

                $card = new TElement('div');
                $card->class = 'opp-timeline-card';

                // badge do tipo
                $typeBadge = new TElement('span');
                $typeBadge->class = 'badge-stage';
                $typeBadge->style = "background:{$iconCfg['bg']}";
                $typeBadge->add(ucfirst(str_replace('_', ' ', $tipo)));

                $time = new TElement('span');
                $time->class = 'opp-timeline-time';
                $time->add($ev['hora'] ?? '');

                $desc = new TElement('div');
                $desc->class = 'opp-timeline-desc';
                $desc->add(htmlspecialchars($ev['descricao'] ?? ''));

                $cardTop = new TElement('div');
                $cardTop->add($typeBadge);
                $cardTop->add($time);

                $card->add($cardTop);
                $card->add($desc);

                $item->add($icon);
                $item->add($card);
                $timeline->add($item);
            }
        }

        $panel->add($timeline);
        return $panel;
    }

    // ─────────────────────────────────────────────
    //  Tabs: Atividades | Arquivos | Observações
    // ─────────────────────────────────────────────
    private function buildTabs($opp, $activities)
    {
        $wrapper = new TElement('div');

        // Nav tabs
        $nav = new TElement('ul');
        $nav->class = 'nav nav-tabs opp-tabs mb-3';
        $nav->id = 'oppTabs';
        $nav->{'role'} = 'tablist';

        $tabs = [
            ['atividades', 'Atividades',   true],
            ['arquivos',   'Arquivos',      false],
            ['observacoes','Observações',   false],
        ];

        foreach ($tabs as [$id, $label, $active]) {
            $li = new TElement('li');
            $li->class = 'nav-item';
            $li->{'role'} = 'presentation';

            $btn = new TElement('button');
            $btn->class = 'nav-link' . ($active ? ' active' : '');
            $btn->id = "tab-{$id}";
            $btn->{'data-bs-toggle'} = 'tab';
            $btn->{'data-bs-target'} = "#pane-{$id}";
            $btn->{'type'} = 'button';
            $btn->{'role'} = 'tab';
            $btn->add($label);

            $li->add($btn);
            $nav->add($li);
        }

        $wrapper->add($nav);

        // Tab content
        $content = new TElement('div');
        $content->class = 'tab-content';

        $content->add($this->buildTabAtividades($opp, $activities));
        $content->add($this->buildTabArquivos($opp));
        $content->add($this->buildTabObservacoes($opp));

        $wrapper->add($content);
        return $wrapper;
    }

    private function buildTabAtividades($opp, $activities)
    {
        $pane = new TElement('div');
        $pane->class = 'tab-pane fade show active';
        $pane->id = 'pane-atividades';
        $pane->{'role'} = 'tabpanel';

        // Botão nova atividade
        $btnAdd = new TElement('a');
        $btnAdd->class = 'btn btn-sm btn-primary mb-3';
        $btnAdd->href = 'javascript:void(0)';
        $btnAdd->{'onclick'} = "Engine.call('OpportunityActivityForm','onEdit',{opportunity_id:{$opp->id}})";
        $btnAdd->add('<i class="fas fa-plus me-1"></i> Nova atividade');
        $pane->add($btnAdd);

        // Calendário semanal
        $pane->add($this->buildWeekCalendar($activities));

        return $pane;
    }

    private function buildWeekCalendar($activities)
    {
        // Semana atual (seg→sex)
        $today = new DateTime;
        $dow = (int)$today->format('N'); // 1=seg, 7=dom
        $mondayTs = strtotime("-" . ($dow - 1) . " days", $today->getTimestamp());

        $weekDays = [];
        for ($i = 0; $i < 5; $i++) {
            $ts = $mondayTs + ($i * 86400);
            $weekDays[] = [
                'ts'    => $ts,
                'label' => ['seg','ter','qua','qui','sex'][$i] . '. ' . date('d/m', $ts),
                'isToday' => date('Y-m-d', $ts) === $today->format('Y-m-d'),
            ];
        }

        // Header com navegação
        $cal = new TElement('div');
        $cal->class = 'opp-calendar';

        $header = new TElement('div');
        $header->class = 'opp-cal-header';

        $nav = new TElement('div');
        $nav->class = 'opp-cal-nav';
        $prevBtn = new TElement('button');
        $prevBtn->add('<i class="fas fa-chevron-left"></i>');
        $nextBtn = new TElement('button');
        $nextBtn->add('<i class="fas fa-chevron-right"></i>');
        $todayBtn = new TElement('button');
        $todayBtn->class = 'btn-today';
        $todayBtn->add('Hoje');
        $nav->add($prevBtn);
        $nav->add($nextBtn);
        $nav->add($todayBtn);

        $startDate = date('d', $mondayTs);
        $endDate   = date('d', $mondayTs + 4*86400);
        $monthYear = date('M. Y', $mondayTs);

        $titleEl = new TElement('div');
        $titleEl->class = 'opp-cal-title';
        $titleEl->add("{$startDate} – {$endDate} de {$monthYear}");

        $viewBtns = new TElement('div');
        $viewBtns->class = 'opp-cal-view-btns';
        foreach (['Dia','Semana','Mês','Agenda'] as $v) {
            $b = new TElement('button');
            $b->class = ($v === 'Semana' ? 'active' : '');
            $b->add($v);
            $viewBtns->add($b);
        }

        $header->add($nav);
        $header->add($titleEl);
        $header->add($viewBtns);
        $cal->add($header);

        // Grid
        $grid = new TElement('div');
        $grid->class = 'opp-cal-grid';

        // Linha de cabeçalho dos dias
        $emptyCorner = new TElement('div');
        $emptyCorner->class = 'opp-cal-time-header';
        $grid->add($emptyCorner);

        foreach ($weekDays as $day) {
            $dh = new TElement('div');
            $dh->class = 'opp-cal-day-header';
            if ($day['isToday']) $dh->style = 'color:#3498db;';
            $dh->add($day['label']);
            $grid->add($dh);
        }

        // Linhas de horas (07:00 → 18:00)
        for ($h = 7; $h <= 18; $h++) {
            $timeLabel = new TElement('div');
            $timeLabel->class = 'opp-cal-time';
            $timeLabel->add(sprintf('%02d:00', $h));
            $grid->add($timeLabel);

            foreach ($weekDays as $day) {
                $cell = new TElement('div');
                $cell->class = 'opp-cal-cell' . ($day['isToday'] ? ' today-col' : '');
                $grid->add($cell);
            }
        }

        $cal->add($grid);
        return $cal;
    }

    private function buildTabArquivos($opp)
    {
        $pane = new TElement('div');
        $pane->class = 'tab-pane fade';
        $pane->id = 'pane-arquivos';
        $pane->{'role'} = 'tabpanel';

        $empty = new TElement('div');
        $empty->style = 'text-align:center; padding:40px; color:#aaa;';
        $empty->add('<i class="fas fa-folder-open fa-3x mb-3 d-block"></i>');
        $empty->add('Nenhum arquivo anexado.');
        $pane->add($empty);

        return $pane;
    }

    private function buildTabObservacoes($opp)
    {
        $pane = new TElement('div');
        $pane->class = 'tab-pane fade';
        $pane->id = 'pane-observacoes';
        $pane->{'role'} = 'tabpanel';

        if (!empty($opp->notes)) {
            $card = new TElement('div');
            $card->class = 'p-3 bg-light rounded';
            $card->style = 'font-size:14px; white-space:pre-wrap; line-height:1.6;';
            $card->add(htmlspecialchars($opp->notes));
            $pane->add($card);
        } else {
            $empty = new TElement('div');
            $empty->style = 'text-align:center; padding:40px; color:#aaa;';
            $empty->add('<i class="fas fa-comment-slash fa-3x mb-3 d-block"></i>');
            $empty->add('Sem observações registradas.');
            $pane->add($empty);
        }

        return $pane;
    }

    // ─────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────
    private function fmtDate($date)
    {
        if (empty($date)) return '—';
        // Suporte yyyy-mm-dd ou dd/mm/yyyy
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            return date('d/m/Y', strtotime($date));
        }
        return htmlspecialchars($date);
    }

    private function buildAlert($msg)
    {
        $el = new TElement('div');
        $el->class = 'alert alert-warning';
        $el->add($msg);
        return $el;
    }

    // ─────────────────────────────────────────────
    //  Entrada pública (chamada via TAction)
    // ─────────────────────────────────────────────
    public static function onView($param = null)
    {
        TApplication::loadPage('OpportunityView', null, $param);
    }
}
