<?php

use Adianti\Control\TAction;
use Adianti\Control\TPage;
use Adianti\Database\TTransaction;
use Adianti\Registry\TSession;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TQuestion;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TText;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Widget\Wrapper\TQuickGrid;

class SolicitacaoCargaList extends TPage
{
    protected $form;
    protected $datagrid;
    protected $pageNavigation;
    protected $loaded;

    use \Adianti\Base\AdiantiStandardListTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('sample');
        $this->setActiveRecord('SolicitacaoCarga');
        $this->setDefaultOrder('id', 'desc');

        $this->addFilterField('status', '=', 'status');

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

        $this->form = new BootstrapFormBuilder('form_search_solicitacao');
        $this->form->setFormTitle('Solicitacoes de Carga');

        $status = new TCombo('status');
        $status->addItems(SolicitacaoCarga::getStatusLabels());

        $this->form->addFields([new TLabel('Status')], [$status]);
        $this->form->setData(TSession::getValue($this->activeRecord . '_filter_data'));
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');

        $this->datagrid = new BootstrapDatagridWrapper(new TQuickGrid);
        $this->datagrid->style = 'width: 100%';

        $this->datagrid->addQuickColumn('ID', 'id', 'center', '5%');
        $col_motorista = $this->datagrid->addQuickColumn('Motorista', 'motorista_id', 'left', '15%');
        $col_carga = $this->datagrid->addQuickColumn('Carga', 'carga_disponivel_id', 'left', '20%');
        $col_veiculo = $this->datagrid->addQuickColumn('Veiculo', 'veiculo_id', 'left', '12%');
        $col_data = $this->datagrid->addQuickColumn('Data', 'created_at', 'center', '12%');
        $col_disp = $this->datagrid->addQuickColumn('Disponibilidade', 'data_disponibilidade', 'center', '10%');
        $col_status = $this->datagrid->addQuickColumn('Status', 'status', 'center', '10%');
        $this->datagrid->addQuickColumn('Resposta', 'resposta_admin', 'left', '12%');

        $col_motorista->setTransformer(function ($value) {
            static $cache = [];
            if (!isset($cache[$value])) {
                try {
                    TTransaction::open('sample');
                    $m = new Motorista($value);
                    $cache[$value] = htmlspecialchars($m->nome ?? '');
                    TTransaction::close();
                } catch (Exception $e) {
                    $cache[$value] = "#{$value}";
                }
            }
            return $cache[$value];
        });

        $col_carga->setTransformer(function ($value) {
            static $cache = [];
            if (!isset($cache[$value])) {
                try {
                    TTransaction::open('sample');
                    $c = new CargaDisponivel($value);
                    $cache[$value] = htmlspecialchars($c->origem . ' &rarr; ' . $c->destino);
                    TTransaction::close();
                } catch (Exception $e) {
                    $cache[$value] = "#{$value}";
                }
            }
            return $cache[$value];
        });

        $col_veiculo->setTransformer(function ($value) {
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

        $col_data->setTransformer(function ($value, $object) {
            $ts = strtotime((string) $value);
            if (!$ts) return $value;
            $dataFormatada = date('d/m/Y H:i', $ts);
            // Badge SLA somente para pendentes/em_analise
            if (!in_array($object->status, ['pendente', 'em_analise'])) {
                return $dataFormatada;
            }
            $diffSec = time() - $ts;
            $diffDias = $diffSec / 86400;
            if ($diffDias >= 2) {
                $label = (int) $diffDias . 'd';
                $bg = '#dc3545';
            } elseif ($diffDias >= 1) {
                $label = (int) $diffDias . 'd';
                $bg = '#ffc107';
            } else {
                $horas = max(1, (int) ($diffSec / 3600));
                $label = $horas . 'h';
                $bg = '#198754';
            }
            $txtColor = ($bg === '#ffc107') ? '#000' : '#fff';
            return "{$dataFormatada} <span class='badge' style='background:{$bg};color:{$txtColor};font-size:.7rem;padding:2px 6px;margin-left:4px'>{$label}</span>";
        });

        $col_disp->setTransformer(function ($value) {
            return $value ? TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy') : '';
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

        $action_aprovar = new TDataGridAction([$this, 'onAprovarConfirm']);
        $action_recusar = new TDataGridAction([$this, 'onRecusarConfirm']);

        $action_whatsapp = new TDataGridAction([$this, 'onWhatsApp']);

        $this->datagrid->addQuickAction('Aprovar', $action_aprovar, 'id', 'fa:check-circle green');
        $this->datagrid->addQuickAction('Recusar', $action_recusar, 'id', 'fa:times-circle red');
        $this->datagrid->addQuickAction('WhatsApp', $action_whatsapp, 'id', 'fab:whatsapp green');

        // Oculta botoes para ja respondidos
        $action_aprovar->setDisplayCondition(function ($object) {
            return in_array($object->status, ['pendente', 'em_analise']);
        });
        $action_recusar->setDisplayCondition(function ($object) {
            return in_array($object->status, ['pendente', 'em_analise']);
        });

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));

        $panel = new TPanelGroup;
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($this->form);
        $container->add($panel);
        parent::add($container);
    }

    public function onAprovarConfirm($param)
    {
        new TQuestion(
            'Confirma <b>APROVAR</b> esta solicitacao?',
            new TAction([$this, 'onAprovar'], ['key' => $param['key']])
        );
    }

    public function onAprovar($param)
    {
        try {
            TTransaction::open('sample');

            $solicitacao = new SolicitacaoCarga($param['key']);
            $solicitacao->status = SolicitacaoCarga::STATUS_APROVADO;
            $solicitacao->respondido_por = TSession::getValue('userid');
            $solicitacao->respondido_em = date('Y-m-d H:i:s');
            $solicitacao->store();

            // Reserva a carga
            $carga = new CargaDisponivel($solicitacao->carga_disponivel_id);
            $carga->status = CargaDisponivel::STATUS_RESERVADA;
            $carga->store();

            $motorista = new Motorista($solicitacao->motorista_id);
            $telefone = $motorista->telefone ?? '';

            TTransaction::close();

            if (!empty($telefone)) {
                $msg = "Ola {$motorista->nome}, sua solicitacao para a carga {$carga->origem} → {$carga->destino} foi *APROVADA*! Entre em contato para combinar os detalhes.";
                $url = self::buildWhatsAppUrl($telefone, $msg);
                new TMessage('info', "Solicitacao aprovada! Carga reservada.<br><br><a href='{$url}' target='_blank' style='background:#25D366;color:#fff;padding:8px 16px;border-radius:5px;text-decoration:none;font-weight:bold'><i class='fab fa-whatsapp'></i> Notificar Motorista via WhatsApp</a>");
            } else {
                new TMessage('info', 'Solicitacao aprovada! Carga reservada.<br><small>(Motorista sem telefone cadastrado)</small>');
            }

            $this->onReload($param);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onRecusarConfirm($param)
    {
        $form = new BootstrapFormBuilder('form_recusa');
        $form->setFormTitle('Motivo da Recusa');

        $resposta = new TText('resposta_admin');
        $resposta->setSize('100%', 80);

        $form->addFields([new TLabel('Resposta ao motorista')], [$resposta]);
        $form->addAction('Recusar', new TAction([$this, 'onRecusar'], ['key' => $param['key']]), 'fa:times red');

        $window = TWindow::create('Recusar Solicitacao', 500, 250);
        $window->add($form);
        $window->show();
    }

    public function onRecusar($param)
    {
        try {
            TTransaction::open('sample');

            $solicitacao = new SolicitacaoCarga($param['key']);
            $solicitacao->status = SolicitacaoCarga::STATUS_RECUSADO;
            $solicitacao->resposta_admin = $param['resposta_admin'] ?? '';
            $solicitacao->respondido_por = TSession::getValue('userid');
            $solicitacao->respondido_em = date('Y-m-d H:i:s');
            $solicitacao->store();

            $carga = new CargaDisponivel($solicitacao->carga_disponivel_id);
            $motorista = new Motorista($solicitacao->motorista_id);
            $telefone = $motorista->telefone ?? '';

            TTransaction::close();

            if (!empty($telefone)) {
                $resposta = !empty($solicitacao->resposta_admin) ? " Motivo: {$solicitacao->resposta_admin}" : '';
                $msg = "Ola {$motorista->nome}, infelizmente sua solicitacao para a carga {$carga->origem} → {$carga->destino} foi *recusada*.{$resposta} Fique atento a novas cargas disponiveis!";
                $url = self::buildWhatsAppUrl($telefone, $msg);
                new TMessage('info', "Solicitacao recusada.<br><br><a href='{$url}' target='_blank' style='background:#25D366;color:#fff;padding:8px 16px;border-radius:5px;text-decoration:none;font-weight:bold'><i class='fab fa-whatsapp'></i> Notificar Motorista via WhatsApp</a>");
            } else {
                new TMessage('info', 'Solicitacao recusada.');
            }

            $this->onReload($param);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Abre WhatsApp para enviar mensagem ao motorista
     */
    public function onWhatsApp($param)
    {
        try {
            TTransaction::open('sample');
            SolicitacaoCarga::ensureTables();

            $solicitacao = new SolicitacaoCarga($param['key']);
            $carga = new CargaDisponivel($solicitacao->carga_disponivel_id);
            $motorista = new Motorista($solicitacao->motorista_id);
            $telefone = $motorista->telefone ?? '';

            TTransaction::close();

            if (empty($telefone)) {
                new TMessage('warning', "Motorista <b>{$motorista->nome}</b> nao possui telefone cadastrado.<br>Cadastre o telefone na tela de Motoristas.");
                return;
            }

            $statusLabels = SolicitacaoCarga::getStatusLabels();
            $statusText = $statusLabels[$solicitacao->status] ?? $solicitacao->status;

            $msg = "Ola {$motorista->nome}! Referente a sua solicitacao de carga {$carga->origem} → {$carga->destino}. Status: *{$statusText}*. ";
            if ($solicitacao->status === SolicitacaoCarga::STATUS_APROVADO) {
                $msg .= "Sua carga foi aprovada! Vamos combinar os detalhes?";
            } elseif ($solicitacao->status === SolicitacaoCarga::STATUS_PENDENTE) {
                $msg .= "Estamos analisando sua solicitacao. Em breve retornaremos.";
            }

            $url = self::buildWhatsAppUrl($telefone, $msg);
            TScript::create("window.open('{$url}', '_blank')");
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Monta URL wa.me com telefone e mensagem
     */
    public static function buildWhatsAppUrl($telefone, $mensagem)
    {
        // Remove tudo que nao e digito
        $fone = preg_replace('/\D/', '', $telefone);

        // Se nao tem codigo pais, assume Brasil (55)
        if (strlen($fone) <= 11) {
            $fone = '55' . $fone;
        }

        return 'https://wa.me/' . $fone . '?text=' . rawurlencode($mensagem);
    }
}
