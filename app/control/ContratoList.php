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
use Adianti\Widget\Dialog\TInputDialog;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\THidden;
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
        Contrato::addColumnsIfNotExists();
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
        $this->form->addFields([new TLabel('Emissao de')], [$emissao_de], [new TLabel('ate')], [$emissao_ate]);
        $this->form->setData(TSession::getValue($this->activeRecord.'_filter_data'));
        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addActionLink('Novo', new TAction(['ContratoForm', 'onClear']), 'fa:plus green');

        $this->datagrid = new BootstrapDatagridWrapper(new TQuickGrid);
        $this->datagrid->style = 'width: 100%';
        $this->datagrid->addQuickColumn('No', 'id', 'center');
        $this->datagrid->addQuickColumn('Contratante', '{permisso->transportadora}', 'left');
        $this->datagrid->addQuickColumn('Veiculo', '{veiculo->placa_trator}', 'left');
        $this->datagrid->addQuickColumn('Motorista', '{veiculo->motorista->nome}', 'left');
        $col_emissao = $this->datagrid->addQuickColumn('Emissao', 'emissao', 'center');
        $col_frete = $this->datagrid->addQuickColumn('Frete', 'frete1', 'right');
        $col_adt = $this->datagrid->addQuickColumn('Adto', 'adt1', 'right');
        $col_pagto_saldo = $this->datagrid->addQuickColumn('Pagto Saldo', 'dta_efet_pg', 'center');
        $col_emissao->setTransformer(function($value){ return TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy'); });
        $col_frete->setTransformer(function($value){ return is_numeric($value) ? 'R$ ' . number_format((float) $value, 2, ',', '.') : $value; });
        $col_adt->setTransformer(function($value){ return is_numeric($value) ? 'R$ ' . number_format((float) $value, 2, ',', '.') : $value; });
        $col_pagto_saldo->setTransformer(function($value) {
            if (empty($value) || $value === '0000-00-00') {
                return '-';
            }
            return TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy');
        });

        $action_edit      = new TDataGridAction(['ContratoForm', 'onEdit']);
        $action_del       = new TDataGridAction([$this, 'onDelete']);
        $action_pdf       = new TDataGridAction([$this, 'onGenerateReport']);
        $action_rastreio  = new TDataGridAction(['PortalMotoristaRastreioAdmin', 'onReload'], ['contrato_id' => '{id}']);
        $action_baixa_adt = new TDataGridAction([$this, 'onBaixaAdtConfirm']);
        $action_baixa_sal = new TDataGridAction([$this, 'onBaixaSaldoConfirm']);

        $this->datagrid->addQuickAction('Editar',       $action_edit,      'id', 'fa:edit blue');
        $this->datagrid->addQuickAction('Excluir',      $action_del,       'id', 'fa:trash red');
        $this->datagrid->addQuickAction('Imprimir',     $action_pdf,       'id', 'fa:file-pdf');
        $this->datagrid->addQuickAction('GPS',          $action_rastreio,  'id', 'fa:map-marker-alt blue');
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

        TScript::create("
            (function() {
                var \$card   = \$('#form_search_contrato').closest('.card');
                var \$header = \$card.find('.card-header').first();
                var \$body   = \$card.find('.card-body').first();
                if (!\$header.length || !\$body.length) return;

                \$header.css('cursor','pointer');
                \$header.append('<span style=\"float:right;margin-left:8px\"><i class=\"fa fa-chevron-up\" id=\"cont-filter-icon\"></i></span>');

                \$header.on('click', function() {
                    \$body.slideToggle(180);
                    \$('#cont-filter-icon').toggleClass('fa-chevron-up fa-chevron-down');
                });
            })();
        ");
    }

    public function onGenerateReport($param)
    {
        ContratoRelatorio::onGenerate($param);
    }

    // BAIXA ADIANTAMENTO

    public function onBaixaAdtConfirm($param)
    {
        $this->abrirDialogPagamento((array) $param, 'adt');
    }

    public function onBaixaAdt($param)
    {
        try {
            TTransaction::open('sample');
            $contratoId = (int) ($param['key'] ?? 0);
            if (self::pagamentoJaRegistrado($contratoId, 'adt')) {
                TTransaction::close();
                new TMessage('info', 'Adiantamento deste contrato ja foi registrado anteriormente.');
                return;
            }

            $contrato = new Contrato($param['key']);
            $valor    = (float) ($contrato->adt1 ?? 0);

            if ($valor <= 0) {
                TTransaction::close();
                new TMessage('info', 'Adiantamento nao informado.');
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

            self::registrarChequeContrato($contrato, 'adt', $valor);

            TTransaction::close();
            new TMessage('info', 'Adiantamento baixado no Caixa com sucesso!');
            $this->onReload($param);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    // BAIXA SALDO

    public function onBaixaSaldoConfirm($param)
    {
        $this->abrirDialogPagamento((array) $param, 'saldo');
    }

    public function onBaixaSaldo($param)
    {
        try {
            TTransaction::open('sample');
            $contratoId = (int) ($param['key'] ?? 0);
            if (self::pagamentoJaRegistrado($contratoId, 'saldo')) {
                TTransaction::close();
                new TMessage('info', 'Saldo deste contrato ja foi registrado anteriormente.');
                return;
            }

            $contrato = new Contrato($param['key']);
            $valor    = (float) ($contrato->saldo1 ?? 0);

            if ($valor <= 0) {
                TTransaction::close();
                new TMessage('info', 'Saldo nao informado ou zerado.');
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

            self::registrarChequeContrato($contrato, 'saldo', $valor);

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

    private function abrirDialogPagamento(array $param, string $tipo): void
    {
        try {
            $contratoId = (int) ($param['key'] ?? 0);
            if ($contratoId <= 0) {
                throw new Exception('Contrato invalido.');
            }

            TTransaction::open('sample');
            $contrato = new Contrato($contratoId);
            $valor = ($tipo === 'adt') ? (float) ($contrato->adt1 ?? 0) : (float) ($contrato->saldo1 ?? 0);

            if ($valor <= 0) {
                TTransaction::close();
                new TMessage('info', $tipo === 'adt' ? 'Adiantamento nao informado neste contrato.' : 'Saldo nao informado ou zerado neste contrato.');
                return;
            }

            if (self::pagamentoJaRegistrado($contratoId, $tipo)) {
                TTransaction::close();
                new TMessage('info', 'Pagamento ja registrado para este contrato/tipo.');
                return;
            }

            $pagDefault = self::detectarPagamentoPadrao((string) ($contrato->pagamento ?? ''));
            $venc = !empty($contrato->vencimento) ? $contrato->vencimento : date('Y-m-d');
            TTransaction::close();

            $form = new BootstrapFormBuilder('form_pagamento_contrato');
            $form->setFormTitle($tipo === 'adt' ? 'Pagamento Adiantamento' : 'Pagamento Saldo');

            $fTipo = new TCombo('tipo_pagamento');
            $fTipo->addItems(['DINHEIRO' => 'Dinheiro', 'PIX' => 'Pix', 'CHEQUE' => 'Cheque']);
            $fTipo->setValue($pagDefault);
            $fTipo->setSize('100%');

            $fBanco = new TEntry('banco');
            $fBanco->setSize('100%');
            $fNumero = new TEntry('numero_cheque');
            $fNumero->setSize('100%');
            $fVenc = new TDate('data_vencimento');
            $fVenc->setMask('dd/mm/yyyy');
            $fVenc->setDatabaseMask('yyyy-mm-dd');
            $fVenc->setSize('100%');
            $fVenc->setValue($venc);

            $fKey = new THidden('key');
            $fKey->setValue($contratoId);
            $fTipoBaixa = new THidden('tipo_baixa');
            $fTipoBaixa->setValue($tipo);

            $form->addFields([new TLabel('Forma de pagamento')], [$fTipo]);
            $form->addFields([new TLabel('Banco (somente cheque)')], [$fBanco]);
            $form->addFields([new TLabel('Numero cheque (somente cheque)')], [$fNumero]);
            $form->addFields([new TLabel('Vencimento (somente cheque)')], [$fVenc]);
            $form->addFields([$fKey], [$fTipoBaixa]);

            $form->addAction('Confirmar', new TAction([$this, 'onProcessarBaixaPagamento']), 'fa:check green');
            $form->addAction('Cancelar', new TAction([$this, 'onReload']), 'fa:times red');

            new TInputDialog('Pagamento do Contrato', $form);
            TScript::create("
                (function() {
                    var toggle = function() {
                        var isCheque = $('select[name=\"tipo_pagamento\"]').val() === 'CHEQUE';
                        var rows = $('input[name=\"banco\"], input[name=\"numero_cheque\"], input[name=\"data_vencimento\"]').closest('tr');
                        if (isCheque) { rows.show(); } else { rows.hide(); }
                    };
                    $(document).off('change.pagcontrato', 'select[name=\"tipo_pagamento\"]').on('change.pagcontrato', 'select[name=\"tipo_pagamento\"]', toggle);
                    toggle();
                })();
            ");
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    public function onProcessarBaixaPagamento($param)
    {
        try {
            $contratoId = (int) ($param['key'] ?? 0);
            $tipo = (string) ($param['tipo_baixa'] ?? '');
            $tipoPagamento = strtoupper(trim((string) ($param['tipo_pagamento'] ?? '')));

            if (!in_array($tipo, ['adt', 'saldo'])) {
                throw new Exception('Tipo de baixa invalido.');
            }
            if (!in_array($tipoPagamento, ['DINHEIRO', 'PIX', 'CHEQUE'])) {
                throw new Exception('Forma de pagamento invalida.');
            }

            TTransaction::open('sample');
            $contrato = new Contrato($contratoId);
            $valor = ($tipo === 'adt') ? (float) ($contrato->adt1 ?? 0) : (float) ($contrato->saldo1 ?? 0);

            if ($valor <= 0) {
                throw new Exception($tipo === 'adt' ? 'Adiantamento nao informado.' : 'Saldo nao informado ou zerado.');
            }
            if (self::pagamentoJaRegistrado($contratoId, $tipo)) {
                throw new Exception('Pagamento ja registrado para este contrato/tipo.');
            }

            if ($tipoPagamento === 'CHEQUE') {
                $banco = trim((string) ($param['banco'] ?? ''));
                $numero = trim((string) ($param['numero_cheque'] ?? ''));
                $vencimento = trim((string) ($param['data_vencimento'] ?? ''));
                if ($banco === '' || $numero === '' || $vencimento === '') {
                    throw new Exception('Para cheque informe banco, numero e vencimento.');
                }
                self::registrarChequeManual($contrato, $tipo, $valor, $banco, $numero, $vencimento);
                $msg = ($tipo === 'adt') ? 'Adiantamento enviado para listagem de cheques.' : 'Saldo enviado para listagem de cheques.';
            } else {
                self::registrarCaixaManual($contrato, $tipo, $valor, $tipoPagamento);
                $msg = ($tipo === 'adt') ? 'Adiantamento baixado no Caixa com sucesso!' : 'Saldo baixado no Caixa com sucesso!';
            }

            if ($tipo === 'saldo') {
                $contrato->dta_efet_pg = date('Y-m-d');
                $contrato->store();
            }

            TTransaction::close();
            new TMessage('info', $msg);
            $this->onReload(['offset' => 0, 'first_page' => 1]);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            if (TTransaction::get()) {
                TTransaction::rollback();
            }
        }
    }

    private static function registrarCaixaManual(Contrato $contrato, string $tipo, float $valor, string $forma): void
    {
        $motorista = '';
        try { $motorista = $contrato->get_veiculo()->motorista->nome ?? ''; } catch (Exception $e) {}
        $crt = $contrato->conhecimento_numero ?? '';
        $prefixo = ($tipo === 'adt') ? 'ADT' : 'Saldo';

        $caixa = new Caixa;
        $caixa->data_lancamento = date('Y-m-d');
        $caixa->descricao = "{$prefixo} Carta Frete #{$contrato->id} ({$forma})"
                          . ($crt ? " CRT {$crt}" : '')
                          . ($motorista ? " - {$motorista}" : '');
        $caixa->tipo = 'SAIDA';
        $caixa->valor = $valor;
        $caixa->categoria = 'CONTRATO';
        $caixa->referencia_id = $contrato->id;
        $caixa->referencia_tipo = ($tipo === 'adt') ? 'contrato_adt' : 'contrato_saldo';
        $caixa->status = 'CONCILIADO';
        $caixa->store();
    }

    private static function registrarChequeManual(Contrato $contrato, string $tipo, float $valor, string $banco, string $numero, string $vencimento): void
    {
        Cheque::createTableIfNotExists();
        $tag = self::tagReferencia($contrato->id, $tipo);
        $tipoTag = ($tipo === 'adt') ? 'ADT' : 'SALDO';

        $motorista = '';
        try { $motorista = $contrato->get_veiculo()->motorista->nome ?? ''; } catch (Exception $e) {}
        $recebedor = trim($motorista) !== '' ? $motorista : 'Motorista do contrato #' . $contrato->id;
        $crt = trim((string) ($contrato->conhecimento_numero ?? ''));

        $cheque = new Cheque;
        $cheque->numero_cheque = $numero;
        $cheque->banco = $banco;
        $cheque->recebedor = $recebedor;
        $cheque->valor = $valor;
        $cheque->data_emissao = date('Y-m-d');
        $cheque->data_vencimento = $vencimento;
        $cheque->status = 'PENDENTE';
        $cheque->observacao = "Pagamento {$tipoTag} da Carta Frete #{$contrato->id}"
                            . ($crt ? " (CRT {$crt})" : '')
                            . " {$tag}";
        $cheque->store();
    }

    private static function detectarPagamentoPadrao(string $pagamento): string
    {
        $p = strtolower($pagamento);
        if (strpos($p, 'cheque') !== false) return 'CHEQUE';
        if (strpos($p, 'pix') !== false) return 'PIX';
        return 'DINHEIRO';
    }

    private static function tagReferencia(int $contratoId, string $tipo): string
    {
        $tipoTag = ($tipo === 'adt') ? 'ADT' : 'SALDO';
        return "[CONTRATO:{$contratoId}:{$tipoTag}]";
    }

    private static function pagamentoJaRegistrado(int $contratoId, string $tipo): bool
    {
        $refTipo = ($tipo === 'adt') ? 'contrato_adt' : 'contrato_saldo';
        $existsCaixa = TTransaction::get()->query(
            "SELECT COUNT(*) FROM caixa WHERE referencia_id={$contratoId} AND referencia_tipo=" . TTransaction::get()->quote($refTipo)
        )->fetchColumn();

        Cheque::createTableIfNotExists();
        $tag = self::tagReferencia($contratoId, $tipo);
        $existsCheque = TTransaction::get()->query(
            "SELECT COUNT(*) FROM cheque WHERE observacao LIKE " . TTransaction::get()->quote("%{$tag}%")
        )->fetchColumn();

        return ((int)$existsCaixa > 0) || ((int)$existsCheque > 0);
    }

    private static function registrarChequeContrato(Contrato $contrato, string $tipo, float $valor): void
    {
        if ($valor <= 0) {
            return;
        }

        $pagamento = (string) ($contrato->pagamento ?? '');
        if (!self::pagamentoUsaCheque($pagamento, $tipo)) {
            return;
        }

        Cheque::createTableIfNotExists();

        $tipoTag = ($tipo === 'adt') ? 'ADT' : 'SALDO';
        $refTag  = "[CONTRATO:{$contrato->id}:{$tipoTag}]";

        $exists = TTransaction::get()->query(
            "SELECT id FROM cheque WHERE observacao LIKE " . TTransaction::get()->quote("%{$refTag}%") . " LIMIT 1"
        )->fetchColumn();

        if ($exists) {
            return;
        }

        $motorista = '';
        try {
            $motorista = $contrato->get_veiculo()->motorista->nome ?? '';
        } catch (Exception $e) {
        }

        $recebedor = trim($motorista) !== '' ? $motorista : 'Motorista do contrato #' . $contrato->id;
        $vencimento = !empty($contrato->vencimento) ? $contrato->vencimento : date('Y-m-d');
        $crt = trim((string) ($contrato->conhecimento_numero ?? ''));

        $cheque = new Cheque;
        $cheque->numero_cheque = 'AUTO-CF-' . $contrato->id . '-' . $tipoTag;
        $cheque->banco = 'A DEFINIR';
        $cheque->recebedor = $recebedor;
        $cheque->valor = $valor;
        $cheque->data_emissao = date('Y-m-d');
        $cheque->data_vencimento = $vencimento;
        $cheque->status = 'PENDENTE';
        $cheque->observacao = "Gerado automaticamente na baixa de {$tipoTag} da Carta Frete #{$contrato->id}"
                            . ($crt ? " (CRT {$crt})" : '')
                            . " {$refTag}";
        $cheque->store();
    }

    private static function pagamentoUsaCheque(string $pagamento, string $tipo): bool
    {
        $p = mb_strtolower($pagamento, 'UTF-8');
        if (trim($p) === '' || strpos($p, 'cheque') === false) {
            return false;
        }

        $hasAdt   = (strpos($p, 'adt') !== false) || (strpos($p, 'adiant') !== false);
        $hasSaldo = (strpos($p, 'saldo') !== false);

        if ($tipo === 'adt') {
            return $hasAdt || (!$hasAdt && !$hasSaldo);
        }

        return $hasSaldo || (!$hasAdt && !$hasSaldo);
    }
}

