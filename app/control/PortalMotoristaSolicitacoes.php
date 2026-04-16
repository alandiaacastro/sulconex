<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;

class PortalMotoristaSolicitacoes extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;
    private $statsContainer;
    private $loaded;

    public function __construct()
    {
        parent::__construct();


        // Verifica autenticação do portal
        if (!TSession::getValue('portal_motorista_logged') && !TSession::getValue('logged')) {
            AdiantiCoreApplication::gotoPage('PortalMotoristaLogin');
            return;
        }

        // Garante tabelas
        try {
            TTransaction::open('sample');
            CargaDisponivel::ensureTables();
            SolicitacaoCarga::ensureTables();
            Motorista::ensureTables();
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
        }

        // Stats container
        $this->statsContainer = new TElement('div');
        $this->statsContainer->class = 'portal-stats';

        // Filtro
        $this->form = new BootstrapFormBuilder('form_minhas_solicitacoes');
        $this->form->setFormTitle('Minhas Solicitacoes');

        $status = new TCombo('status');
        $status->addItems(SolicitacaoCarga::getStatusLabels());

        $this->form->addFields([new TLabel('Status')], [$status]);
        $this->form->addAction('Filtrar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addAction('Limpar', new TAction([$this, 'onClearFilter']), 'fa:eraser red');

        $filterData = TSession::getValue('PortalMotoristaSolicitacoes_filter_data');
        if ($filterData) {
            $this->form->setData($filterData);
        }

        // Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';

        $col_id     = new TDataGridColumn('id', 'ID', 'center', '5%');
        $col_carga  = new TDataGridColumn('carga_disponivel_id', 'Carga (Rota)', 'left', '22%');
        $col_veic   = new TDataGridColumn('veiculo_id', 'Veiculo', 'left', '12%');
        $col_data   = new TDataGridColumn('created_at', 'Data Solicitacao', 'center', '13%');
        $col_disp   = new TDataGridColumn('data_disponibilidade', 'Disponibilidade', 'center', '12%');
        $col_status = new TDataGridColumn('status', 'Status', 'center', '10%');
        $col_resp   = new TDataGridColumn('resposta_admin', 'Resposta', 'left', '18%');

        $col_carga->setTransformer(function ($value) {
            static $cache = [];
            if (!isset($cache[$value])) {
                try {
                    TTransaction::open('sample');
                    $c = new CargaDisponivel($value);
                    $cache[$value] = htmlspecialchars($c->origem) . ' &rarr; ' . htmlspecialchars($c->destino);
                    TTransaction::close();
                } catch (Exception $e) {
                    $cache[$value] = "#{$value}";
                }
            }
            return $cache[$value];
        });

        $col_veic->setTransformer(function ($value) {
            if (empty($value)) return '-';
            static $cache = [];
            if (!isset($cache[$value])) {
                try {
                    TTransaction::open('sample');
                    $v = new Veiculo($value);
                    $cache[$value] = htmlspecialchars($v->placa_trator ?? '');
                    TTransaction::close();
                } catch (Exception $e) {
                    $cache[$value] = "#{$value}";
                }
            }
            return $cache[$value];
        });

        $col_data->setTransformer(function ($value) {
            $ts = strtotime((string) $value);
            return $ts ? date('d/m/Y H:i', $ts) : $value;
        });

        $col_disp->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '-';
        });

        $col_status->setTransformer(function ($value) {
            $colors = [
                'pendente'   => '#fd7e14',
                'em_analise' => '#0d6efd',
                'aprovado'   => '#198754',
                'recusado'   => '#dc3545',
                'cancelado'  => '#6c757d',
            ];
            $labels = SolicitacaoCarga::getStatusLabels();
            $color = $colors[$value] ?? '#6c757d';
            $label = $labels[$value] ?? ucfirst($value);
            return "<span class='badge' style='background:{$color};color:#fff;font-size:.78rem;padding:4px 8px'>{$label}</span>";
        });

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_carga);
        $this->datagrid->addColumn($col_veic);
        $this->datagrid->addColumn($col_data);
        $this->datagrid->addColumn($col_disp);
        $this->datagrid->addColumn($col_status);
        $this->datagrid->addColumn($col_resp);

        $action_cancelar = new TDataGridAction([$this, 'onCancelarConfirm'], ['key' => '{id}']);
        $this->datagrid->addAction($action_cancelar, 'Cancelar', 'fa:times-circle red');
        $action_cancelar->setDisplayCondition(function ($object) {
            return $object->status === SolicitacaoCarga::STATUS_PENDENTE;
        });

        $this->datagrid->createModel();

        // Paginacao
        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $panel = new TPanelGroup;
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $contentWrap = new TElement('div');
        $contentWrap->class = 'portal-page-content';
        $contentWrap->add($this->statsContainer);
        $contentWrap->add($this->form);
        $contentWrap->add($panel);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add(PortalMotoristaHelper::buildNav('solicitacoes'));
        $container->add($contentWrap);
        parent::add($container);
    }

    public function onSearch($param = null)
    {
        $data = $this->form->getData();
        TSession::setValue('PortalMotoristaSolicitacoes_filter_data', $data);
        TSession::setValue('PortalMotoristaSolicitacoes_filter_status', !empty($data->status) ? $data->status : null);
        $this->form->setData($data);
        $this->onReload();
    }

    public function onClearFilter($param = null)
    {
        TSession::setValue('PortalMotoristaSolicitacoes_filter_data', null);
        TSession::setValue('PortalMotoristaSolicitacoes_filter_status', null);
        $this->form->clear(true);
        $this->onReload();
    }

    public function onReload($param = null)
    {
        try {
            TTransaction::open('sample');
            SolicitacaoCarga::ensureTables();

            $motorista = Motorista::getPortalMotorista();
            if (!$motorista) {
                TTransaction::close();
                $this->statsContainer->clearChildren();
                $this->datagrid->clear();
                new TMessage('info', 'Seu usuario nao esta vinculado a nenhum motorista. Contacte o administrador.');
                $this->loaded = true;
                return;
            }

            // Stats
            $this->buildStats($motorista->id);

            // Criteria
            $repository = new TRepository('SolicitacaoCarga');
            $criteria = new TCriteria;
            $criteria->add(new TFilter('motorista_id', '=', $motorista->id));

            $statusFilter = TSession::getValue('PortalMotoristaSolicitacoes_filter_status');
            if ($statusFilter) {
                $criteria->add(new TFilter('status', '=', $statusFilter));
            }

            $criteria->setProperty('order', 'id desc');
            $criteria->setProperty('limit', 15);

            $offset = $param['offset'] ?? 0;
            $criteria->setProperty('offset', $offset);

            $objects = $repository->load($criteria, FALSE);
            $count   = $repository->count($criteria);

            $this->datagrid->clear();
            if ($objects) {
                foreach ($objects as $obj) {
                    $this->datagrid->addItem($obj);
                }
            }

            $this->pageNavigation->setCount($count);
            $this->pageNavigation->setProperties($param);
            $this->pageNavigation->setLimit(15);

            TTransaction::close();
            $this->loaded = true;
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', $e->getMessage());
        }
    }

    private function buildStats($motoristaId)
    {
        $this->statsContainer->clearChildren();

        $conn = TTransaction::get();

        $sql = "SELECT status, COUNT(*) as total FROM solicitacao_carga WHERE motorista_id = ? GROUP BY status";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$motoristaId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = ['pendente' => 0, 'em_analise' => 0, 'aprovado' => 0, 'recusado' => 0];
        $total = 0;
        foreach ($rows as $r) {
            $counts[$r['status']] = (int) $r['total'];
            $total += (int) $r['total'];
        }

        $stats = [
            ['label' => 'Total', 'value' => $total, 'class' => 'stat-total'],
            ['label' => 'Pendentes', 'value' => $counts['pendente'] + $counts['em_analise'], 'class' => 'stat-pendente'],
            ['label' => 'Aprovadas', 'value' => $counts['aprovado'], 'class' => 'stat-aprovado'],
            ['label' => 'Recusadas', 'value' => $counts['recusado'], 'class' => 'stat-recusado'],
        ];

        foreach ($stats as $s) {
            $card = new TElement('div');
            $card->class = "portal-stat-card {$s['class']}";
            $card->add("<div class='stat-number'>{$s['value']}</div>");
            $card->add("<div class='stat-label'>{$s['label']}</div>");
            $this->statsContainer->add($card);
        }
    }

    public function onCancelarConfirm($param)
    {
        $action = new TAction([$this, 'onCancelar'], ['key' => $param['key']]);
        new TQuestion('Deseja realmente cancelar esta solicitacao?', $action);
    }

    public function onCancelar($param)
    {
        try {
            TTransaction::open('sample');
            SolicitacaoCarga::ensureTables();

            $solicitacao = new SolicitacaoCarga($param['key']);
            $solicitacao->status = SolicitacaoCarga::STATUS_CANCELADO;
            $solicitacao->store();

            TTransaction::close();
            new TMessage('info', 'Solicitacao cancelada com sucesso.');
            $this->onReload($param);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
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
