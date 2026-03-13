<?php
/**
 * OpportunityKanban — Painel Kanban do CRM com os 7 estágios do pipeline.
 * Clicar em um card abre o OpportunityView (tela de detalhe).
 */
class OpportunityKanban extends TPage
{
    private $kanbanContainer;
    private $loaded = false;
    private $formButtons;

    // Os 7 estágios — mesma definição usada no Form e no View
    const STAGES = [
        'PROSPECTAR'            => 'Prospectar',
        'QUALIFICAR'            => 'Qualificar',
        'LEVANTAR_NECESSIDADES' => 'Levantar necessidades',
        'ELABORAR_PROPOSTA'     => 'Elaborar proposta',
        'FOLLOWUP'              => 'FollowUp',
        'INICIAR_NEGOCIACAO'    => 'Iniciar negociação',
        'NEGOCIACAO_FINALIZADA' => 'Negociação finalizada',
    ];

    const STAGE_COLORS = [
        'PROSPECTAR'            => '#6c757d',
        'QUALIFICAR'            => '#17a2b8',
        'LEVANTAR_NECESSIDADES' => '#3498db',
        'ELABORAR_PROPOSTA'     => '#fd7e14',
        'FOLLOWUP'              => '#e83e8c',
        'INICIAR_NEGOCIACAO'    => '#6f42c1',
        'NEGOCIACAO_FINALIZADA' => '#28a745',
    ];

    public function __construct()
    {
        parent::__construct();

        TPage::include_css('app/resources/css/kanban_custom.css');

        $this->kanbanContainer = new TElement('div');
        $this->kanbanContainer->class = 'kanban-wrapper p-4';

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(new TXMLBreadCrumb('menu.xml', __CLASS__));

        $this->formButtons = new TForm('form_global_actions');
        $this->formButtons->setProperty('style', 'padding-bottom: 10px;');

        $btnNew = new TButton('btn_new');
        $btnNew->setLabel('Nova Oportunidade');
        $btnNew->setImage('fa:plus green');
        $btnNew->setAction(new TAction(['OpportunityForm', 'onEdit']));

        $btnReload = new TButton('btn_reload');
        $btnReload->setLabel('Atualizar');
        $btnReload->setImage('fa:sync-alt blue');
        $btnReload->setAction(new TAction([$this, 'onReload']));

        $btnRow = new THBox();
        $btnRow->add($btnNew);
        $btnRow->add($btnReload);
        $btnRow->style = 'display: flex; gap: 10px;';

        $this->formButtons->add($btnRow);
        $this->formButtons->setFields([$btnNew, $btnReload]);

        $container->add($this->formButtons);
        $container->add($this->kanbanContainer);

        parent::add($container);
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');

            $kanban = new TKanban;
            $kanban->setStageHeight('80vh');

            // Ação de visualizar (abre OpportunityView)
            $viewAction = new TAction(['OpportunityView', 'onView']);
            $viewAction->setParameter('key', '{id}');
            $kanban->addItemAction('Ver detalhes', $viewAction, 'fas:eye blue');

            // Ação de editar
            $editAction = new TAction(['OpportunityForm', 'onEdit']);
            $editAction->setParameter('id', '{id}');
            $kanban->addItemAction('Editar', $editAction, 'far:edit orange');

            // Ação de excluir
            $deleteAction = new TAction([$this, 'onDelete']);
            $deleteAction->setParameter('id', '{id}');
            $kanban->addItemAction('Excluir', $deleteAction, 'far:trash-alt red');

            // Drag-and-drop atualiza o estágio
            $kanban->setItemDropAction(new TAction([$this, 'onUpdateItemDrop']));

            // Adiciona as 7 colunas/estágios
            foreach (self::STAGES as $stageId => $stageTitle) {
                $kanban->addStage($stageId, $stageTitle);
            }

            // Carrega oportunidades
            $repo  = new TRepository('Opportunity');
            $items = $repo->load(new TCriteria());

            if ($items) {
                foreach ($items as $item) {
                    $stage = $item->status ?? 'PROSPECTAR';
                    $color = self::STAGE_COLORS[$stage] ?? '#6c757d';

                    $stageName = self::STAGES[$stage] ?? $stage;
                    $valor = !empty($item->valor)
                        ? '<div style="color:#888;font-size:11px;margin-top:4px;"><i class="fas fa-dollar-sign"></i> R$ ' . number_format((float)$item->valor, 2, ',', '.') . '</div>'
                        : '';

                    $content = "
                    <div style='background:{$color}; padding:4px 8px; border-top-left-radius:6px; border-top-right-radius:6px; font-weight:bold; font-size:13px; color:#fff;'>
                        {$item->company_name}
                    </div>
                    <div class='kanban-card-content' style='padding:8px;'>
                        <div style='display:flex; align-items:center; gap:6px; margin-bottom:4px;'>
                            <i class='fas fa-user-circle' style='color:{$color};'></i>
                            <b style='font-size:12px; color:#333;'>{$item->responsible_name}</b>
                        </div>";

                    if (!empty($item->vendedor)) {
                        $content .= "<div style='display:flex; align-items:center; gap:6px; margin-bottom:4px; color:#555; font-size:12px;'>
                            <i class='fas fa-briefcase' style='color:{$color};'></i>
                            {$item->vendedor}
                        </div>";
                    }

                    if (!empty($item->email)) {
                        $content .= "<div style='display:flex; align-items:center; gap:6px; margin-bottom:4px; color:#555; font-size:12px;'>
                            <i class='fas fa-envelope' style='color:{$color};'></i>
                            {$item->email}
                        </div>";
                    }

                    $content .= $valor;

                    if (!empty($item->data_esperada_fechamento)) {
                        $dt = date('d/m/Y', strtotime($item->data_esperada_fechamento));
                        $content .= "<div style='color:#888; font-size:11px; margin-top:4px;'>
                            <i class='far fa-calendar-alt'></i> Prev. fechamento: <b>{$dt}</b>
                        </div>";
                    }

                    $content .= "</div>";

                    $kanban->addItem($item->id, $stage, '', $content, $color);
                }
            }

            $this->kanbanContainer->clearChildren();
            $this->kanbanContainer->add($kanban);

            TTransaction::close();
            $this->loaded = true;

        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao carregar Kanban: ' . $e->getMessage());
        }
    }

    public static function onUpdateItemDrop($param)
    {
        try {
            TTransaction::open('sample');

            $opp = new Opportunity($param['id']);
            $oldStatus = $opp->status;
            $opp->status = $param['stage_id'];
            $opp->store();

            // Registra movimentação no histórico
            $stages = self::STAGES;
            $hist = new OpportunityHistory;
            $hist->opportunity_id = $opp->id;
            $hist->evento = 'Negociação movimentada para ' . ($stages[$param['stage_id']] ?? $param['stage_id']);
            $hist->data_evento = date('Y-m-d H:i:s');
            $hist->store();

            TTransaction::close();
            TToast::show('success', 'Estágio atualizado!', 'bottom right');

        } catch (Exception $e) {
            TTransaction::rollback();
            TToast::show('error', 'Erro: ' . $e->getMessage(), 'bottom right');
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
            TToast::show('error', 'Erro: ' . $e->getMessage(), 'bottom right');
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
