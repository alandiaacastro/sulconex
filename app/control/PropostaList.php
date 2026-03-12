<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Widget\Datagrid\TDataGridAction;
use Adianti\Widget\Datagrid\TPageNavigation;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Database\TTransaction;

class PropostaList extends TPage
{
    private $form;
    private $datagrid;
    private $pageNavigation;

    use Adianti\Base\AdiantiStandardListTrait {
        onReload as traitOnReload;
    }

    public function __construct($param = null)
    {
        parent::__construct();

        $this->setDatabase('sample');
        $this->setActiveRecord('Proposta');
        $this->setDefaultOrder('id', 'desc');
        $this->addFilterField('Cotacao_ID', 'like', 'Cotacao_ID');
        $this->addFilterField('Situacao', '=', 'Situacao');
        $this->addFilterField('cliente_id', '=', 'cliente_id');

        $this->form = new BootstrapFormBuilder('form_search_proposta');
        $this->form->setFormTitle('PROPOSTAS DE FRETE INTERNACIONAL');

        $cotacao_id = new TEntry('Cotacao_ID');
        $situacao   = new TCombo('Situacao');
        $cliente_id = new TDBUniqueSearch('cliente_id', 'sample', 'Clientes', 'id', 'nome');

        $cotacao_id->setSize('100%');
        $situacao->setSize('100%');
        $cliente_id->setSize('100%');
        $cliente_id->setMinLength(2);
        $cliente_id->setMask('{nome}');

        $situacao->addItems([
            ''           => 'Todos',
            'Em Analise' => 'Em Analise',
            'Aprovada'   => 'Aprovada',
            'Rejeitada'  => 'Rejeitada',
        ]);

        $this->form->addFields([new TLabel('No cotacao')], [$cotacao_id], [new TLabel('Situacao')], [$situacao]);
        $this->form->addFields([new TLabel('Cliente')], [$cliente_id]);

        $this->form->setData(TSession::getValue($this->activeRecord . '_filter_data'));

        $this->form->addAction('Buscar', new TAction([$this, 'onSearch']), 'fa:search blue');
        $this->form->addActionLink('Nova Proposta', new TAction(['PropostaForm', 'onEdit']), 'fa:plus green');

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->style = 'width:100%';

        $col_id       = new TDataGridColumn('id', 'ID', 'right', '60');
        $col_cotacao  = new TDataGridColumn('Cotacao_ID', 'Cotacao', 'left', '110');
        $col_cliente  = new TDataGridColumn('cliente_id', 'Cliente', 'left', '260');
        $col_emissao  = new TDataGridColumn('Data_Cotacao', 'Emissao', 'center', '110');
        $col_validade = new TDataGridColumn('Data_Validade_Cotacao', 'Validade', 'center', '110');
        $col_situacao = new TDataGridColumn('Situacao', 'Situacao', 'left', '130');
        $col_fat      = new TDataGridColumn('Faturamento_Valor_1', 'Faturamento', 'right', '130');
        $col_res      = new TDataGridColumn('resultado_final', 'Resultado', 'right', '130');

        $col_cliente->setTransformer(function ($value) {
            static $cache = [];

            if (empty($value)) {
                return '';
            }

            if (isset($cache[$value])) {
                return $cache[$value];
            }

            try {
                TTransaction::open('sample');
                $cliente = new Clientes($value);
                $nome = (string) ($cliente->nome ?? '');
                TTransaction::close();
                $cache[$value] = $nome;
                return $nome;
            } catch (Exception $e) {
                try {
                    TTransaction::rollback();
                } catch (Exception $ee) {
                }
                return '';
            }
        });

        $fmtDate = function ($value) {
            if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return TDate::convertToMask($value, 'yyyy-mm-dd', 'dd/mm/yyyy');
            }
            return $value;
        };
        $col_emissao->setTransformer($fmtDate);
        $col_validade->setTransformer($fmtDate);

        $col_situacao->setTransformer(function ($value) {
            if (empty($value)) {
                return '';
            }

            $colors = [
                'Em Analise' => ['bg' => '#fef3c7', 'bd' => '#fde68a', 'fg' => '#92400e'],
                'Aprovada'   => ['bg' => '#d1fae5', 'bd' => '#6ee7b7', 'fg' => '#065f46'],
                'Rejeitada'  => ['bg' => '#fee2e2', 'bd' => '#fca5a5', 'fg' => '#991b1b'],
            ];

            $c = $colors[$value] ?? ['bg' => '#f3f4f6', 'bd' => '#d1d5db', 'fg' => '#374151'];
            $label = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

            return "<span style='display:inline-block;padding:3px 10px;border-radius:999px;border:1px solid {$c['bd']};background:{$c['bg']};color:{$c['fg']};font-weight:600;font-size:11px'>{$label}</span>";
        });

        $col_fat->setTransformer(function ($value) {
            if ($value === null || $value === '') {
                return '';
            }
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        });

        $col_res->setTransformer(function ($value) {
            if ($value === null || $value === '') {
                return '';
            }
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        });

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_cotacao);
        $this->datagrid->addColumn($col_cliente);
        $this->datagrid->addColumn($col_emissao);
        $this->datagrid->addColumn($col_validade);
        $this->datagrid->addColumn($col_situacao);
        $this->datagrid->addColumn($col_fat);
        $this->datagrid->addColumn($col_res);

        $act_edit = new TDataGridAction(['PropostaForm', 'onEdit'], ['key' => '{id}']);
        $this->datagrid->addAction($act_edit, 'Editar', 'fa:edit blue');
        $act_print_proposta = new TDataGridAction(['PropostaRelatorio', 'onImprimir'], ['key' => '{id}']);
        $this->datagrid->addAction($act_print_proposta, 'Imprimir proposta', 'fa:file-pdf red');

        $act_print_cotacao = new TDataGridAction(['CotacaoPDFView', 'onGenerate'], ['key' => '{id}']);
        $this->datagrid->addAction($act_print_cotacao, 'Imprimir cotacao', 'fa:file-pdf blue');
        $act_del = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($act_del, 'Excluir', 'fa:trash red');

        $this->datagrid->createModel();

        $this->pageNavigation = new TPageNavigation;
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->setWidth('100%');

        $panel = new TPanelGroup;
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $box = new TVBox;
        $box->style = 'width:100%';
        $box->add(new TXMLBreadCrumb('menu.xml', get_class($this)));
        $box->add($this->form);
        $box->add($panel);

        parent::add($box);
    }

    public function onReload($param = null)
    {
        $this->traitOnReload($param);
    }
}
