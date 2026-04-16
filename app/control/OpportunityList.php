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
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Util\TXMLBreadCrumb;
use Adianti\Wrapper\BootstrapDatagridWrapper;

class OpportunityList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $loaded = false;

    private $database     = 'sample';
    private $activeRecord = 'Opportunity';

    public function __construct()
    {
        parent::__construct();

        // ---- Toolbar de ações ----
        $btnNovo = new TButton('btn_novo');
        $btnNovo->setLabel('Nova Oportunidade');
        $btnNovo->setImage('fa:plus green');
        $btnNovo->setAction(new TAction(['OpportunityForm', 'onEdit']));

        $btnKanban = new TButton('btn_kanban');
        $btnKanban->setLabel('Kanban');
        $btnKanban->setImage('fa:columns blue');
        $btnKanban->setAction(new TAction(['OpportunityKanban', 'onReload']));

        $btnDash = new TButton('btn_dash');
        $btnDash->setLabel('Dashboard CRM');
        $btnDash->setImage('fa:chart-bar orange');
        $btnDash->setAction(new TAction(['CrmDashboard', 'onReload']));

        $btnPropostas = new TButton('btn_propostas');
        $btnPropostas->setLabel('Propostas');
        $btnPropostas->setImage('fa:file-text purple');
        $btnPropostas->setAction(new TAction(['PropostaList', 'onReload']));

        $toolbarForm = new TForm('form_opp_toolbar');
        $toolbarForm->setFields([$btnNovo, $btnKanban, $btnDash, $btnPropostas]);

        $toolbarRow = new THBox;
        $toolbarRow->style = 'display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;';
        $toolbarRow->add($btnNovo);
        $toolbarRow->add($btnKanban);
        $toolbarRow->add($btnDash);
        $toolbarRow->add($btnPropostas);
        $toolbarForm->add($toolbarRow);

        // ---- Filtros ----
        $this->form = new TForm('form_search_Opportunity');

        $company_name = new TEntry('company_name');
        $responsible  = new TEntry('responsible_name');
        $status       = new TCombo('status');
        $prioridade   = new TCombo('prioridade');
        $origem_lead  = new TCombo('origem_lead');
        $proximo_de   = new TDate('proximo_de');
        $proximo_ate  = new TDate('proximo_ate');

        foreach ([$company_name, $responsible, $status, $prioridade, $origem_lead] as $w) {
            $w->setSize('100%');
        }
        $company_name->setProperty('placeholder', 'Empresa...');
        $responsible->setProperty('placeholder', 'Responsável...');

        $status->addItems([
            ''             => 'Todos os status',
            'QUALIFICACAO' => 'Qualificação',
            'PROPOSTA'     => 'Proposta',
            'NEGOCIACAO'   => 'Negociação',
            'FECHAMENTO'   => 'Fechamento',
            'PERDIDO'      => 'Perdido',
        ]);
        $prioridade->addItems([
            ''      => 'Todas',
            'Alta'  => '🔴 Alta',
            'Media' => '🟡 Média',
            'Baixa' => '🟢 Baixa',
        ]);
        $origem_lead->addItems([
            ''          => 'Todas as origens',
            'Site'      => 'Site / Landing Page',
            'Indicacao' => 'Indicação',
            'LinkedIn'  => 'LinkedIn',
            'Feira'     => 'Feira / Evento',
            'ColdCall'  => 'Cold Call',
            'WhatsApp'  => 'WhatsApp',
            'Parceiro'  => 'Parceiro',
            'Outro'     => 'Outro',
        ]);
        foreach ([$proximo_de, $proximo_ate] as $d) {
            $d->setSize('100%');
            $d->setMask('dd/mm/yyyy');
            $d->setDatabaseMask('yyyy-mm-dd');
        }

        $btnSearch = new TButton('btn_search');
        $btnSearch->setLabel('Buscar');
        $btnSearch->setImage('fa:search blue');
        $btnSearch->setAction(new TAction([$this, 'onSearch']), 'Buscar');

        $btnClear = new TButton('btn_clear');
        $btnClear->setLabel('Limpar');
        $btnClear->setImage('fa:eraser gray');
        $btnClear->setAction(new TAction([$this, 'onClear']), 'Limpar');

        $this->form->setFields([$company_name, $responsible, $status, $prioridade, $origem_lead, $proximo_de, $proximo_ate, $btnSearch, $btnClear]);

        // Grid de filtros 3 colunas
        $filterGrid = new TElement('div');
        $filterGrid->style = 'display:grid;grid-template-columns:repeat(3,1fr);gap:10px 16px;';

        $mkField = function ($label, $widget) {
            $wrap = new TElement('div');
            $lbl = new TElement('label');
            $lbl->style = 'font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:3px;';
            $lbl->add($label);
            $wrap->add($lbl);
            $wrap->add($widget);
            return $wrap;
        };

        $filterGrid->add($mkField('Empresa',        $company_name));
        $filterGrid->add($mkField('Responsável',    $responsible));
        $filterGrid->add($mkField('Status',         $status));
        $filterGrid->add($mkField('Prioridade',     $prioridade));
        $filterGrid->add($mkField('Origem do Lead', $origem_lead));

        // Próximo contato De/Até num slot
        $dateRow = new TElement('div');
        $dateRow->style = 'display:grid;grid-template-columns:1fr 1fr;gap:6px;';
        $d1 = new TElement('div');
        $d1->add('<div style="font-size:10px;color:#94a3b8;margin-bottom:2px">De</div>');
        $d1->add($proximo_de);
        $d2 = new TElement('div');
        $d2->add('<div style="font-size:10px;color:#94a3b8;margin-bottom:2px">Até</div>');
        $d2->add($proximo_ate);
        $dateRow->add($d1);
        $dateRow->add($d2);
        $filterGrid->add($mkField('Próximo Contato', $dateRow));

        $btnRowEl = new TElement('div');
        $btnRowEl->style = 'display:flex;gap:8px;margin-top:14px;';
        $btnRowEl->add($btnSearch);
        $btnRowEl->add($btnClear);

        $this->form->add($filterGrid);
        $this->form->add($btnRowEl);

        // Painel colapsável via HTML puro
        $collapseId = 'opp-filter-body';
        $filterPanel = new TElement('div');
        $filterPanel->style = 'margin-bottom:14px;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;';

        $filterHeader = new TElement('div');
        $filterHeader->style = 'display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:#f8fafc;cursor:pointer;border-bottom:1px solid #e2e8f0;';
        $filterHeader->{'onclick'} = "var el=document.getElementById('{$collapseId}'); var ic=this.querySelector('.fi'); if(el.style.display==='none'){el.style.display='block';ic.textContent='▲';}else{el.style.display='none';ic.textContent='▼';}";
        $filterHeader->add('<span style="font-size:13px;font-weight:600;color:#1e40af;">🔍 Filtros de Busca</span>');
        $filterHeader->add('<span class="fi" style="font-size:11px;color:#94a3b8;transition:all .2s">▲</span>');

        $filterBody = new TElement('div');
        $filterBody->id = $collapseId;
        $filterBody->style = 'padding:16px;background:#fff;display:block;';
        $filterBody->add($this->form);

        $filterPanel->add($filterHeader);
        $filterPanel->add($filterBody);

        // ---- DataGrid ----
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';

        $col_id = new TDataGridColumn('id', '#', 'center', '45');

        $col_company = new TDataGridColumn('company_name', 'Empresa', 'left', '20%');
        $col_company->setTransformer(function ($v) {
            return "<span style='font-weight:600;color:#0f172a'>" . htmlspecialchars((string)$v) . "</span>";
        });

        $col_resp  = new TDataGridColumn('responsible_name', 'Responsável', 'left', '13%');
        $col_phone = new TDataGridColumn('phone', 'Telefone', 'left', '11%');
        $col_email = new TDataGridColumn('email', 'E-mail', 'left', '15%');

        $col_valor = new TDataGridColumn('valor_estimado', 'Valor', 'right', '10%');
        $col_valor->setTransformer(function ($v) {
            if (!$v) return '<span style="color:#cbd5e1">—</span>';
            return '<span style="color:#065f46;font-weight:600">R$&nbsp;' . number_format((float)$v, 2, ',', '.') . '</span>';
        });

        $col_prio = new TDataGridColumn('prioridade', 'Prio.', 'center', '7%');
        $col_prio->setTransformer(function ($v) {
            if (!$v) return '<span style="color:#cbd5e1">—</span>';
            $map = [
                'Alta'  => ['#fee2e2', '#991b1b', '🔴'],
                'Media' => ['#fef9c3', '#92400e', '🟡'],
                'Baixa' => ['#dcfce7', '#15803d', '🟢'],
            ];
            [$bg, $fg, $icon] = $map[$v] ?? ['#f1f5f9', '#475569', ''];
            return "<span style='font-size:10px;padding:2px 7px;border-radius:999px;background:{$bg};color:{$fg};font-weight:700'>{$icon} {$v}</span>";
        });

        $col_prox = new TDataGridColumn('proximo_contato', 'Próx. Contato', 'center', '10%');
        $col_prox->setTransformer(function ($v) {
            if (!$v) return '<span style="color:#cbd5e1">—</span>';
            $fmt = TDate::convertToMask($v, 'yyyy-mm-dd', 'dd/mm/yyyy');
            if ($v < date('Y-m-d')) {
                return "<span style='color:#991b1b;font-weight:700'>⚠️ {$fmt}</span>";
            }
            return "<span style='color:#475569'>📅 {$fmt}</span>";
        });

        $col_status = new TDataGridColumn('status', 'Status', 'center', '11%');
        $col_status->setTransformer(function ($v) {
            $map = [
                'QUALIFICACAO' => ['#dbeafe', '#1d4ed8', 'Qualificação'],
                'PROPOSTA'     => ['#e0e7ff', '#4338ca', 'Proposta'],
                'NEGOCIACAO'   => ['#fef3c7', '#b45309', 'Negociação'],
                'FECHAMENTO'   => ['#dcfce7', '#15803d', 'Fechamento'],
                'PERDIDO'      => ['#fee2e2', '#991b1b', 'Perdido'],
            ];
            [$bg, $fg, $label] = $map[$v] ?? ['#f1f5f9', '#475569', $v ?: '—'];
            return "<span style='font-size:11px;padding:3px 10px;border-radius:999px;background:{$bg};color:{$fg};font-weight:700;display:inline-block'>{$label}</span>";
        });

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_company);
        $this->datagrid->addColumn($col_resp);
        $this->datagrid->addColumn($col_phone);
        $this->datagrid->addColumn($col_email);
        $this->datagrid->addColumn($col_valor);
        $this->datagrid->addColumn($col_prio);
        $this->datagrid->addColumn($col_prox);
        $this->datagrid->addColumn($col_status);

        $actEdit = new TDataGridAction(['OpportunityForm', 'onEdit'], ['key' => '{id}']);
        $actEdit->setLabel('Editar'); $actEdit->setImage('far:edit blue');
        $this->datagrid->addAction($actEdit);

        $actHistory = new TDataGridAction(['CrmActivityList', 'onLoad'], ['opportunity_id' => '{id}']);
        $actHistory->setLabel('Histórico'); $actHistory->setImage('fa:history teal');
        $this->datagrid->addAction($actHistory);

        $actEmail = new TDataGridAction(['EmailComposerView', 'onLoadFromOpportunity'], ['opportunity_id' => '{id}']);
        $actEmail->setLabel('Compor E-mail'); $actEmail->setImage('fas:envelope green');
        $this->datagrid->addAction($actEmail);

        $actProposal = new TDataGridAction([$this, 'onCreateProposal'], ['key' => '{id}']);
        $actProposal->setLabel('Gerar Proposta'); $actProposal->setImage('fa:file-text purple');
        $this->datagrid->addAction($actProposal);

        $actDelete = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $actDelete->setLabel('Excluir'); $actDelete->setImage('far:trash-alt red');
        $this->datagrid->addAction($actDelete);

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setLimit(15);

        $gridPanel = new TPanelGroup('Leads / Oportunidades');
        $gridPanel->add($this->datagrid);
        $gridPanel->addFooter($this->pageNavigation);

        // ---- Monta página ----
        $box = new TVBox;
        $box->style = 'width:100%';
        if (is_file('menu.xml')) {
            $box->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        }
        $box->add($toolbarForm);
        $box->add($filterPanel);
        $box->add($gridPanel);

        parent::add($box);
    }

    public function onSearch($param = null)
    {
        TSession::setValue('opportunity_filter', $param);
        $this->onReload($param);
    }

    public static function onClear($param = null)
    {
        TSession::setValue('opportunity_filter', []);
        TApplication::loadPage(__CLASS__, 'onReload');
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open($this->database);
            $repository = new TRepository($this->activeRecord);

            $limit  = 15;
            $saved  = TSession::getValue('opportunity_filter') ?? [];
            $param  = (!empty($param) ? $param : $saved);
            $page   = isset($param['page']) ? (int)$param['page'] : 1;
            $offset = ($page - 1) * $limit;

            if ($saved) {
                $this->form->setData((object)$saved);
            }

            $criteria = new TCriteria;
            if (!empty($param['company_name'])) {
                $criteria->add(new TFilter('company_name', 'like', "%{$param['company_name']}%"));
            }
            if (!empty($param['responsible_name'])) {
                $criteria->add(new TFilter('responsible_name', 'like', "%{$param['responsible_name']}%"));
            }
            if (!empty($param['status'])) {
                $criteria->add(new TFilter('status', '=', $param['status']));
            }
            if (!empty($param['prioridade'])) {
                $criteria->add(new TFilter('prioridade', '=', $param['prioridade']));
            }
            if (!empty($param['origem_lead'])) {
                $criteria->add(new TFilter('origem_lead', '=', $param['origem_lead']));
            }
            if (!empty($param['proximo_de'])) {
                $de = TDate::convertToMask($param['proximo_de'], 'dd/mm/yyyy', 'yyyy-mm-dd');
                $criteria->add(new TFilter('proximo_contato', '>=', $de));
            }
            if (!empty($param['proximo_ate'])) {
                $ate = TDate::convertToMask($param['proximo_ate'], 'dd/mm/yyyy', 'yyyy-mm-dd');
                $criteria->add(new TFilter('proximo_contato', '<=', $ate));
            }

            $total = $repository->count($criteria);

            $criteria->setProperty('limit',  $limit);
            $criteria->setProperty('offset', $offset);
            $criteria->setProperty('order',  'id desc');

            $this->datagrid->clear();
            $items = $repository->load($criteria);
            if ($items) {
                foreach ($items as $item) {
                    $this->datagrid->addItem($item);
                }
            }

            $this->pageNavigation->setCount($total);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setPage($page);

            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function onDelete($param)
    {
        if (!empty($param['key'])) {
            $action = new TAction([$this, 'confirmDelete']);
            $action->setParameters(['key' => $param['key']]);
            new TQuestion('Deseja excluir este registro?', $action);
        }
    }

    public function onCreateProposal($param)
    {
        try {
            if (empty($param['key'])) throw new Exception('Oportunidade não informada');
            TTransaction::open($this->database);
            $opp = new Opportunity((int)$param['key']);
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
            new TMessage('error', 'Não foi possível gerar proposta: ' . $e->getMessage());
        }
    }

    public function confirmDelete($param)
    {
        try {
            TTransaction::open($this->database);
            $obj = new Opportunity($param['key']);
            $obj->delete();
            TTransaction::close();
            $this->onReload();
            new TMessage('info', 'Registro excluído com sucesso');
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    public function show()
    {
        if (!$this->loaded) {
            $this->onReload($_GET ?? []);
        }
        parent::show();
    }
}
