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
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Widget\Wrapper\TQuickGrid;

class ContratoList extends TPage
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
        $this->setActiveRecord('Contrato');
        $this->setDefaultOrder('id', 'desc');
        $this->addFilterField('permisso_id', '=', 'permisso_id');
        $this->addFilterField('emissao', '>=', 'emissao_de', function($value) { return TDate::convertToMask($value, 'dd/mm/yyyy', 'yyyy-mm-dd'); });
        $this->addFilterField('emissao', '<=', 'emissao_ate', function($value) { return TDate::convertToMask($value, 'dd/mm/yyyy', 'yyyy-mm-dd'); });
        
        $this->form = new BootstrapFormBuilder('form_search_contrato');
        $this->form->setFormTitle('Listagem de Contratos');
        $permisso_id = new TDBUniqueSearch('permisso_id', 'sample', 'Permisso', 'id', 'transportadora');
        $emissao_de = new TDate('emissao_de');
        $emissao_ate = new TDate('emissao_ate');
        $emissao_de->setMask('dd/mm/yyyy');
        $emissao_ate->setMask('dd/mm/yyyy');
        $this->form->addFields([new TLabel('Contratante')], [$permisso_id]);
        $this->form->addFields([new TLabel('Emissão de')], [$emissao_de], [new TLabel('até')], [$emissao_ate]);
        $this->form->setData(TSession::getValue($this->activeRecord.'_filter_data'));
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addActionLink('Novo', new TAction(['ContratoForm', 'onClear']), 'fa:plus green');

        $this->datagrid = new BootstrapDatagridWrapper(new TQuickGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->addQuickColumn('Nº', 'id', 'center');
        $this->datagrid->addQuickColumn('Contratante', '{permisso->transportadora}', 'left');
        $this->datagrid->addQuickColumn('Veículo', '{veiculo->placa_trator}', 'left');
        $this->datagrid->addQuickColumn('Motorista', '{veiculo->motorista->nome}', 'left');
        $col_emissao = $this->datagrid->addQuickColumn('Emissão', 'emissao', 'center');
        $col_frete = $this->datagrid->addQuickColumn('Frete', 'frete1', 'right');
        $col_pago = $this->datagrid->addQuickColumn('id', 'Pagamento', 'center');
        $col_emissao->setTransformer(function($value){ return TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy'); });
        $col_frete->setTransformer(function($value){ return is_numeric($value) ? 'R$ ' . number_format((float) $value, 2, ',', '.') : $value; });
        $col_pago->setTransformer(function($id) {
            // Carrega todos de uma vez (cache estático por requisição)
            static $cache = null;
            if ($cache === null) {
                $cache = ['adt' => [], 'saldo' => []];
                try {
                    TTransaction::open('sample');
                    $rows = TTransaction::get()->query(
                        "SELECT referencia_id, referencia_tipo FROM caixa
                         WHERE referencia_tipo IN ('contrato_adt','contrato_saldo')"
                    )->fetchAll(\PDO::FETCH_ASSOC);
                    TTransaction::close();
                    foreach ($rows as $r) {
                        $key = $r['referencia_tipo'] === 'contrato_adt' ? 'adt' : 'saldo';
                        $cache[$key][] = (string) $r['referencia_id'];
                    }
                } catch (Exception $e) { /* silencia */ }
            }
            $adt = in_array((string)$id, $cache['adt']);
            $sal = in_array((string)$id, $cache['saldo']);
            if ($adt && $sal) {
                return "<span class='badge' style='background:#198754;color:#fff;font-size:.78rem;padding:4px 7px'>&#10003; Pago ADT+Saldo</span>";
            } elseif ($sal) {
                return "<span class='badge' style='background:#0d6efd;color:#fff;font-size:.78rem;padding:4px 7px'>Pago Saldo</span>";
            } elseif ($adt) {
                return "<span class='badge' style='background:#fd7e14;color:#fff;font-size:.78rem;padding:4px 7px'>Pago ADT</span>";
            }
            return "<span class='badge' style='background:#dc3545;color:#fff;font-size:.78rem;padding:4px 7px'>N&#227;o Pago</span>";
        });
        
        $action_edit      = new TDataGridAction(['ContratoForm', 'onEdit']);
        $action_del       = new TDataGridAction([$this, 'onDelete']);
        $action_pdf       = new TDataGridAction([$this, 'onGenerateReport']);
        $action_baixa_adt = new TDataGridAction([$this, 'onBaixaAdtConfirm']);
        $action_baixa_sal = new TDataGridAction([$this, 'onBaixaSaldoConfirm']);

        $this->datagrid->addQuickAction('Editar',       $action_edit,      'id', 'fa:edit blue');
        $this->datagrid->addQuickAction('Excluir',      $action_del,       'id', 'fa:trash red');
        $this->datagrid->addQuickAction('Imprimir',     $action_pdf,       'id', 'fa:file-pdf');
        $this->datagrid->addQuickAction('Baixa ADT',    $action_baixa_adt, 'id', 'fa:money-bill-wave orange');
        $this->datagrid->addQuickAction('Baixa Saldo',  $action_baixa_sal, 'id', 'fa:check-circle green');
        
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
    
    public function onGenerateReport($param)
    {
        ContratoRelatorio::onGenerate($param);
    }

    // ── BAIXA ADIANTAMENTO ────────────────────────────────────────────────

    public function onBaixaAdtConfirm($param)
    {
        try {
            TTransaction::open('sample');
            $contrato = new Contrato($param['key']);
            $valor    = (float) ($contrato->adt1 ?? 0);
            TTransaction::close();

            if ($valor <= 0) {
                new TMessage('info', 'Adiantamento não informado neste contrato.');
                return;
            }

            // Verifica duplicidade
            TTransaction::open('sample');
            $conn = TTransaction::get();
            $existe = $conn->query(
                "SELECT COUNT(*) FROM caixa WHERE referencia_id={$param['key']} AND referencia_tipo='contrato_adt'"
            )->fetchColumn();
            TTransaction::close();

            if ($existe > 0) {
                new TMessage('info', 'Adiantamento deste contrato já foi baixado no Caixa.');
                return;
            }

            $fmt = 'R$ ' . number_format($valor, 2, ',', '.');
            new TQuestion(
                "Confirma baixa do <b>Adiantamento</b> de <b>{$fmt}</b> no Caixa?",
                new TAction([$this, 'onBaixaAdt'], ['key' => $param['key']])
            );
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onBaixaAdt($param)
    {
        try {
            TTransaction::open('sample');
            $contrato = new Contrato($param['key']);
            $valor    = (float) ($contrato->adt1 ?? 0);

            if ($valor <= 0) {
                TTransaction::close();
                new TMessage('info', 'Adiantamento não informado.');
                return;
            }

            $motorista = '';
            try { $motorista = $contrato->get_veiculo()->motorista->nome ?? ''; } catch (Exception $e) {}
            $crt = $contrato->conhecimento_numero ?? '';

            $caixa = new Caixa;
            $caixa->data_lancamento  = date('Y-m-d');
            $caixa->descricao        = "ADT Carta Frete #{$contrato->id}"
                                     . ($crt       ? " CRT {$crt}" : '')
                                     . ($motorista ? " - {$motorista}" : '');
            $caixa->tipo             = 'SAIDA';
            $caixa->valor            = $valor;
            $caixa->categoria        = 'CONTRATO';
            $caixa->referencia_id    = $contrato->id;
            $caixa->referencia_tipo  = 'contrato_adt';
            $caixa->status           = 'CONCILIADO';
            $caixa->store();

            TTransaction::close();
            new TMessage('info', 'Adiantamento baixado no Caixa com sucesso!');
            $this->onReload($param);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    // ── BAIXA SALDO ───────────────────────────────────────────────────────

    public function onBaixaSaldoConfirm($param)
    {
        try {
            TTransaction::open('sample');
            $contrato = new Contrato($param['key']);
            $valor    = (float) ($contrato->saldo1 ?? 0);
            TTransaction::close();

            if ($valor <= 0) {
                new TMessage('info', 'Saldo não informado ou zerado neste contrato.');
                return;
            }

            // Verifica duplicidade
            TTransaction::open('sample');
            $conn = TTransaction::get();
            $existe = $conn->query(
                "SELECT COUNT(*) FROM caixa WHERE referencia_id={$param['key']} AND referencia_tipo='contrato_saldo'"
            )->fetchColumn();
            TTransaction::close();

            if ($existe > 0) {
                new TMessage('info', 'Saldo deste contrato já foi baixado no Caixa.');
                return;
            }

            $fmt = 'R$ ' . number_format($valor, 2, ',', '.');
            new TQuestion(
                "Confirma baixa do <b>Saldo</b> de <b>{$fmt}</b> no Caixa?",
                new TAction([$this, 'onBaixaSaldo'], ['key' => $param['key']])
            );
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onBaixaSaldo($param)
    {
        try {
            TTransaction::open('sample');
            $contrato = new Contrato($param['key']);
            $valor    = (float) ($contrato->saldo1 ?? 0);

            if ($valor <= 0) {
                TTransaction::close();
                new TMessage('info', 'Saldo não informado ou zerado.');
                return;
            }

            $motorista = '';
            try { $motorista = $contrato->get_veiculo()->motorista->nome ?? ''; } catch (Exception $e) {}
            $crt = $contrato->conhecimento_numero ?? '';

            $caixa = new Caixa;
            $caixa->data_lancamento  = date('Y-m-d');
            $caixa->descricao        = "Saldo Carta Frete #{$contrato->id}"
                                     . ($crt       ? " CRT {$crt}" : '')
                                     . ($motorista ? " - {$motorista}" : '');
            $caixa->tipo             = 'SAIDA';
            $caixa->valor            = $valor;
            $caixa->categoria        = 'CONTRATO';
            $caixa->referencia_id    = $contrato->id;
            $caixa->referencia_tipo  = 'contrato_saldo';
            $caixa->status           = 'CONCILIADO';
            $caixa->store();

            // Marca contrato como pago
            $contrato->pago = 'S';
            $contrato->dta_efet_pg = date('Y-m-d');
            $contrato->store();

            TTransaction::close();
            new TMessage('info', 'Saldo baixado no Caixa com sucesso!');
            $this->onReload($param);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}