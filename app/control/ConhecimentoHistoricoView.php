<?php

use Adianti\Database\TTransaction;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;

class ConhecimentoHistoricoView extends TPage
{
    public function __construct($param = [])
    {
        parent::__construct();

        $pkvalue = $param['pkvalue'] ?? $param['filter_pkvalue'] ?? null;

        // Titulo com numero do CRT
        $title = 'Historico de Alteracoes';
        if ($pkvalue) {
            try {
                TTransaction::open('sample');
                $crt   = new Conhecimento((int) $pkvalue);
                $title .= ' - CRT ' . ($crt->numero ?? '#' . $pkvalue);
                TTransaction::close();
            } catch (Exception $e) {
                TTransaction::rollback();
            }
        }

        // Carrega registros do banco de log
        $logs = [];
        try {
            TTransaction::open('log');
            $criteria = new TCriteria;
            if ($pkvalue) {
                $criteria->add(new TFilter('tablename', '=', 'conhecimento'));
                $criteria->add(new TFilter('pkvalue',   '=', (string) $pkvalue));
            }
            $criteria->setProperty('order', 'logdate DESC');
            $criteria->setProperty('limit', 500);
            $logs = SystemChangeLog::getObjects($criteria) ?? [];
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
            new TMessage('error', 'Erro ao carregar historico: ' . $e->getMessage());
        }

        // ── DATAGRID ──────────────────────────────────────────────────────
        $datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $datagrid->style = 'width: 100%';

        $colCheck = new TDataGridColumn('id', 'Sel.', 'center', '40px');
        $colCheck->setTransformer(function ($value) {
            return "<input type='radio' name='hist_radio' class='hist-cb' value='{$value}' style='width:16px;height:16px;cursor:pointer'>";
        });

        $colDate  = new TDataGridColumn('logdate',    'Data/Hora',  'center', '15%');
        $colLogin = new TDataGridColumn('login',      'Usuario',    'center', '10%');
        $colField = new TDataGridColumn('columnname', 'Campo', 'left', '20%');
        $colOp    = new TDataGridColumn('operation',  'Operacao',   'center', '10%');
        $colOld   = new TDataGridColumn('oldvalue',   'Onde se le', 'left',   '20%');
        $colNew   = new TDataGridColumn('newvalue',   'Leia-se',    'left',   '20%');

        $colOp->setTransformer(function ($value) {
            $map = ['created' => 'success', 'changed' => 'info', 'deleted' => 'danger'];
            $c   = $map[$value] ?? 'secondary';
            return "<span class='label label-{$c}' style='font-size:11px;text-shadow:none'>{$value}</span>";
        });

        $datagrid->addColumn($colCheck);
        $datagrid->addColumn($colDate);
        $datagrid->addColumn($colLogin);
        $datagrid->addColumn($colField);
        $datagrid->addColumn($colOp);
        $datagrid->addColumn($colOld);
        $datagrid->addColumn($colNew);
        $datagrid->createModel();

        foreach ($logs as $log) {
            $datagrid->addItem($log);
        }

        // ── BOTOES ────────────────────────────────────────────────────────
        $pkJs = (int) $pkvalue;

        // Campo livre: item 1.1 do relatorio
        $labelItem = new TElement('label');
        $labelItem->style = 'font-weight:bold;font-size:13px;align-self:center;white-space:nowrap';
        $labelItem->add('1.1 Descricao:');

        $inputItem = new TElement('input');
        $inputItem->type        = 'text';
        $inputItem->id          = 'hist_item11';
        $inputItem->placeholder = 'Ex: MIC campo 3, Peso bruto...';
        $inputItem->style       = 'width:300px;height:30px;padding:4px 8px;border:1px solid #ccc;border-radius:4px;font-size:13px';

        $btnCarta = new TElement('button');
        $btnCarta->type    = 'button';
        $btnCarta->class   = 'btn btn-warning btn-sm';
        $btnCarta->onclick = "__hist_gerar_carta({$pkJs})";
        $btnCarta->add('<i class="fa fa-file-alt"></i> Gerar Carta de Correcao');

        $btnVoltar = new TElement('button');
        $btnVoltar->type    = 'button';
        $btnVoltar->class   = 'btn btn-secondary btn-sm';
        $btnVoltar->onclick = "__adianti_load_page('index.php?class=ConhecimentoList&method=onReload')";
        $btnVoltar->add('<i class="fa fa-arrow-left"></i> Retorno');

        $toolbar = new TElement('div');
        $toolbar->style = 'margin-bottom:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap';
        $toolbar->add($labelItem);
        $toolbar->add($inputItem);
        $toolbar->add($btnVoltar);
        $toolbar->add($btnCarta);

        // ── JAVASCRIPT ────────────────────────────────────────────────────
        TScript::create("
            function __hist_gerar_carta(crtId) {
                var selected = document.querySelector('.hist-cb:checked');
                if (!selected) {
                    adianti_message('warning', 'Selecione um registro para emitir a Carta de Correcao.');
                    return;
                }
                var item11 = encodeURIComponent(document.getElementById('hist_item11').value.trim());
                __adianti_load_page('index.php?class=ConhecimentoHistoricoView&method=onGerarCarta&crt_id=' + crtId + '&selected_ids=' + selected.value + '&item11=' + item11);
            }
        ");

        // ── LAYOUT ────────────────────────────────────────────────────────
        $panel = new TPanelGroup($title);
        $panel->add($toolbar);
        $panel->add($datagrid);

        $container = new TVBox;
        $container->style = 'width: 100%';
        $container->add($panel);

        parent::add($container);
    }

    /**
     * Recebe os IDs selecionados e gera a Carta de Correcao
     */
    public static function onGerarCarta($param)
    {
        try {
            $crtId       = (int) ($param['crt_id'] ?? 0);
            $selectedIds = array_filter(array_map('intval', explode(',', $param['selected_ids'] ?? '')));

            if (!$crtId || empty($selectedIds)) {
                new TMessage('warning', 'Selecione ao menos um campo para emitir a Carta de Correcao.');
                return;
            }

            // Carrega os registros de log selecionados
            TTransaction::open('log');
            $changes = [];
            foreach ($selectedIds as $lid) {
                $log = new SystemChangeLog($lid);
                if ($log->id) {
                    $changes[] = $log;
                }
            }
            TTransaction::close();

            if (empty($changes)) {
                new TMessage('error', 'Nenhum registro encontrado para os IDs selecionados.');
                return;
            }

            // Carrega o CRT e a Permissao
            TTransaction::open('sample');
            $crt      = new Conhecimento($crtId);
            $permisso = $crt->permisso_id ? new Permisso($crt->permisso_id) : null;
            TTransaction::close();

            $assinaturaNome = $param['assinatura_nome'] ?? '';
            $item11         = $param['item11'] ?? '';
            CartaCorrecaoPDF::gerarComChanges($crt, $permisso, $changes, $assinaturaNome, $item11);
            TScript::create("setTimeout(function(){ __adianti_load_page('index.php?class=ConhecimentoList&method=onReload'); }, 1500);");

        } catch (Exception $e) {
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
            new TMessage('error', $e->getMessage());
        }
    }
}
