<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Form\TFormSeparator;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Base\TElement;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Database\TTransaction;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Database\TRepository;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Dialog\TToast;
use Adianti\Widget\Util\TBreadCrumb;

/**
 * PropostaForm
 * Compativel com Adianti Framework 8.1+ / PHP 8.3+
 * Layout sem TNotebook - secoes separadas por TFormSeparator
 */
class PropostaForm extends TPage
{
    protected $form;
    private static $database     = 'sample';
    private static $activeRecord = 'Proposta';
    private static $formName     = 'form_Proposta';

    public function __construct($param = null)
    {
        parent::__construct();

        // -------------------------------------------------------
        // CRIACAO DO FORMULARIO
        // -------------------------------------------------------
        $this->form = new BootstrapFormBuilder(self::$formName);
        $this->form->setFormTitle('Proposta de Frete Internacional');
        $this->form->setClientValidation(true);
        $this->form->setProperty('style', 'margin-bottom:0');

        // -------------------------------------------------------
        // CAMPOS - IDENTIFICACAO
        // -------------------------------------------------------
        $id = new THidden('id');

        $cliente_id = new TDBUniqueSearch('cliente_id', self::$database, 'Clientes', 'id', 'nome');
        $cliente_id->setMinLength(2);
        $cliente_id->setMask('{nome} - {cnpj}');
        $cliente_id->setSize('100%');

        $Cotacao_ID = new TEntry('Cotacao_ID');
        $Cotacao_ID->setEditable(false);
        $Cotacao_ID->setProperty('style', 'background:#f0f0f0; font-weight:bold; color:#333');
        $Cotacao_ID->setSize('100%');

        $Data_Cotacao = new TDate('Data_Cotacao');
        $Data_Cotacao->setSize('100%');
        $Data_Cotacao->setMask('dd/mm/yyyy');
        $Data_Cotacao->setDatabaseMask('yyyy-mm-dd');
        $Data_Cotacao->setExitAction(new TAction([$this, 'ontrinta']));

        $Data_Validade_Cotacao = new TDate('Data_Validade_Cotacao');
        $Data_Validade_Cotacao->setSize('100%');
        $Data_Validade_Cotacao->setMask('dd/mm/yyyy');
        $Data_Validade_Cotacao->setDatabaseMask('yyyy-mm-dd');

        $Situacao = new TCombo('Situacao');
        $Situacao->setSize('100%');
        $Situacao->addItems([
            'Em Analise' => 'Em Analise',
            'Aprovada'   => 'Aprovada',
            'Rejeitada'  => 'Rejeitada',
        ]);

        // -------------------------------------------------------
        // CAMPOS - LOGISTICA
        // -------------------------------------------------------
        $Mercadoria = new TEntry('Descricao_Mercadoria');
        $Mercadoria->setSize('100%');

        $FOB_Valor = new TNumeric('FOB_Mercadoria_Valor', 2, ',', '.', true);
        $FOB_Valor->setSize('100%');

        $Tempo_Transito = new TEntry('Tempo_Transito');
        $Tempo_Transito->setSize('100%');

        // Carregar cidades para autocomplete de origem/destino
        $cidades = [];
        try {
            TTransaction::open('default');
            $listaCidades = (new TRepository('CidadeUf'))->load(null, false);
            if ($listaCidades) {
                foreach ($listaCidades as $c) {
                    $label = mb_strtoupper($c->nome . ',' . $c->uf, 'UTF-8');
                    $cidades[$label] = $label;
                }
            }
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
        }

        $Local_Coleta = new TUniqueSearch('Local_Coleta');
        $Local_Coleta->setSize('100%');
        $Local_Coleta->setMinLength(2);
        $Local_Coleta->addItems($cidades);
        $Local_Coleta->setTip('Local de coleta / Rota 1 — ex: URUGUAIANA,RS');

        $Local_Entrega = new TUniqueSearch('Local_Entrega');
        $Local_Entrega->setSize('100%');
        $Local_Entrega->setMinLength(2);
        $Local_Entrega->addItems($cidades);
        $Local_Entrega->setTip('Local de entrega / Rota 2 — ex: BUENOS AIRES,ARGENTINA');

        $Aduana = new TCombo('Aduana_Fronteira');
        $Aduana->enableSearch();
        $Aduana->setSize('100%');

        try {
            TTransaction::open(self::$database);
            $conn = TTransaction::get();
            $result = $conn->query("SELECT DISTINCT fronteira FROM tabela_fretes WHERE fronteira IS NOT NULL AND fronteira != '' ORDER BY fronteira");
            $fronteiras = [];
            foreach ($result as $row) {
                if (!empty(trim($row['fronteira']))) {
                    // Armazena a string real no value do combo para não alterar a estrutura de Proposta.
                    $fronteiras[$row['fronteira']] = $row['fronteira'];
                }
            }
            $Aduana->addItems($fronteiras);
            TTransaction::close();
        } catch (Exception $e) {
            TTransaction::rollback();
        }

        $Equipamento = new TEntry('Tipo_Equipamento');
        $Equipamento->setSize('100%');

        $actionReloadRotas = new TAction([$this, 'onReloadRotas']);
        $Local_Coleta->setChangeAction($actionReloadRotas);
        $Aduana->setChangeAction($actionReloadRotas);
        $Local_Entrega->setChangeAction($actionReloadRotas);
        $Equipamento->setExitAction($actionReloadRotas);

        // A busca automatica de fretes foi substituida por selecao manual nas combos.


        // -------------------------------------------------------
        // CAMPOS - CUSTOS OPERACIONAIS (dinamicos) E COMBOS
        // -------------------------------------------------------
        $frete_origem_id = new TDBCombo('frete_origem_id', self::$database, 'TabelaFrete', 'id', 'Origem: {origem} > Fronteira: {fronteira} > Destino: {destino} - R$ {valor_frete}');
        $frete_origem_id->enableSearch();
        $frete_origem_id->setSize('100%');
        $frete_origem_id->setChangeAction(new TAction([$this, 'onChangeFreteOrigem']));

        $frete_destino_id = new TDBCombo('frete_destino_id', self::$database, 'TabelaFrete', 'id', 'Origem: {origem} > Fronteira: {fronteira} > Destino: {destino} - R$ {valor_frete}');
        $frete_destino_id->enableSearch();
        $frete_destino_id->setSize('100%');
        $frete_destino_id->setChangeAction(new TAction([$this, 'onChangeFreteDestino']));

        $lista_custos = self::getCostFieldsLabels();

        $actionCalcularCusto = new TAction([$this, 'ontotaldespesas']);
        $campos_custo_obj    = [];

        foreach ($lista_custos as $nome_campo => $label_campo) {
            $campo = new TNumeric($nome_campo, 2, ',', '.', true);
            $campo->setSize('100%');
            $campo->setExitAction($actionCalcularCusto);
            $campos_custo_obj[] = ['label' => $label_campo, 'field' => $campo];
        }

        $Total_Custo = new TNumeric('Custo_Total_Operacao_Valor', 2, ',', '.', true);
        $Total_Custo->setSize('100%');
        $Total_Custo->setEditable(false);
        $Total_Custo->setProperty('style', 'background-color:#fff0f0; border-left:4px solid #e53e3e; font-weight:bold; color:#c53030;');

        // -------------------------------------------------------
        // CAMPOS - FINANCEIRO
        // -------------------------------------------------------
        $actionCalcFat = new TAction([$this, 'onFaturamento']);

        $Valor_Faturamento = new TNumeric('Faturamento_Valor_1', 2, ',', '.', true);
        $Valor_Faturamento->setSize('100%');
        $Valor_Faturamento->setExitAction($actionCalcFat);

        $Faturamento_Dolar = new TNumeric('fat_dolar', 2, ',', '.', true);
        $Faturamento_Dolar->setSize('100%');
        $Faturamento_Dolar->setEditable(false);
        $Faturamento_Dolar->setProperty('style', 'background-color:#eee;');

        $Taxa_Dolar = new TNumeric('Taxa_Dolar', 4, ',', '.', true);
        $Taxa_Dolar->setSize('100%');
        $Taxa_Dolar->setExitAction($actionCalcFat);

        $Aliq_Imposto = new TNumeric('Percentual_Impostos_FOB', 2, ',', '.', true);
        $Aliq_Imposto->setSize('100%');
        $Aliq_Imposto->setExitAction($actionCalcFat);

        $Valor_Impostos_Calc = new TNumeric('Impostos_Operacao_Valor', 2, ',', '.', true);
        $Valor_Impostos_Calc->setSize('100%');
        $Valor_Impostos_Calc->setEditable(false);
        $Valor_Impostos_Calc->setProperty('style', 'background-color:#eee;');

        $Taxa_Swift = new TNumeric('taxa_swift', 2, ',', '.', true);
        $Taxa_Swift->setSize('100%');
        $Taxa_Swift->setExitAction($actionCalcFat);

        $Valor_Swift_Calc = new TNumeric('valor_swift', 2, ',', '.', true);
        $Valor_Swift_Calc->setSize('100%');
        $Valor_Swift_Calc->setEditable(false);
        $Valor_Swift_Calc->setProperty('style', 'background-color:#eee;');

        $Aliq_Seguro = new TNumeric('Percentual_Seguro_FOB', 2, ',', '.', true);
        $Aliq_Seguro->setSize('100%');
        $Aliq_Seguro->setExitAction($actionCalcFat);

        $Valor_Seguro_Calc = new TNumeric('valor_seguro', 2, ',', '.', true);
        $Valor_Seguro_Calc->setSize('100%');
        $Valor_Seguro_Calc->setEditable(false);
        $Valor_Seguro_Calc->setProperty('style', 'background-color:#eee;');

        $FOB_Valor->setExitAction($actionCalcFat);

        $Fat_Liquido_Reais = new TNumeric('fat_liquido_reais', 2, ',', '.', true);
        $Fat_Liquido_Reais->setSize('100%');
        $Fat_Liquido_Reais->setEditable(false);
        $Fat_Liquido_Reais->setProperty('style', 'background-color:#ebf4ff; border-left:4px solid #2e6da4; font-weight:bold; color:#1a3a5c;');

        $Resultado_Final = new TNumeric('resultado_final', 2, ',', '.', true);
        $Resultado_Final->setSize('100%');
        $Resultado_Final->setEditable(false);
        $Resultado_Final->setProperty('style', 'background-color:#f0fff4; border-left:4px solid #38a169; font-weight:bold; color:#276749;');

        $Resultado_Dolar = new TNumeric('resultado_dolar', 2, ',', '.', true);
        $Resultado_Dolar->setSize('100%');
        $Resultado_Dolar->setEditable(false);
        $Resultado_Dolar->setProperty('style', 'background-color:#eee;');

        $Margem_Percentual = new TNumeric('margem_percentual', 2, ',', '.', true);
        $Margem_Percentual->setSize('100%');
        $Margem_Percentual->setEditable(false);
        $Margem_Percentual->setProperty('style', 'background-color:#eee;');

        // -------------------------------------------------------
        // CAMPO - OBSERVACOES
        // -------------------------------------------------------
        $observacoes = new TText('observacoes');
        $observacoes->setSize('100%', 80);

        // -------------------------------------------------------
        // BOTOES DE ACAO INTERNA
        // -------------------------------------------------------
        $button_rota = new TButton('button_rota');
        $button_rota->setAction(new TAction([$this, 'onMapa']), 'Ver Mapa');
        $button_rota->setImage('fas:map-marked-alt #ffffff');
        $button_rota->addStyleClass('btn-info');
        $button_rota->style = 'margin-right: 2px; height: 32px; padding-top: 5px;';

        $button_rota->style = 'margin-right: 2px; height: 32px; padding-top: 5px;';



        $button_despesas = new TButton('button_despesas');
        $button_despesas->setAction(new TAction([$this, 'ontotaldespesas']), 'Calcular Custos');
        $button_despesas->setImage('fas:calculator #ffffff');
        $button_despesas->addStyleClass('btn-warning');

        $button_fatliquido = new TButton('button_fatliquido');
        $button_fatliquido->setAction(new TAction([$this, 'onFaturamento']), 'Processar Financeiro');
        $button_fatliquido->setImage('fas:sync-alt #ffffff');
        $button_fatliquido->addStyleClass('btn-primary');

        // -------------------------------------------------------
        // LAYOUT - SECAO: IDENTIFICACAO
        // -------------------------------------------------------
        $this->form->addContent([new TFormSeparator('Identificacao da Proposta')]);

        // Label acima do campo: label e campo no mesmo array
        $this->form->addFields(
            [new TLabel('Cliente'), $cliente_id]
        );

        $row_id = $this->form->addFields(
            [new TLabel('N. Cotacao'),  $Cotacao_ID],
            [new TLabel('Emissao'),     $Data_Cotacao],
            [new TLabel('Validade'),    $Data_Validade_Cotacao],
            [new TLabel('Situacao'),    $Situacao]
        );
        $row_id->layout = ['col-sm-3', 'col-sm-3', 'col-sm-3', 'col-sm-3'];

        // -------------------------------------------------------
        // LAYOUT - SECAO: LOGISTICA
        // -------------------------------------------------------
        $this->form->addContent([new TFormSeparator('Logistica')]);

        $row_merc = $this->form->addFields(
            [new TLabel('Mercadoria'),    $Mercadoria],
            [new TLabel('FOB (USD)'),     $FOB_Valor],
            [new TLabel('Transit Time'),  $Tempo_Transito]
        );
        $row_merc->layout = ['col-sm-6', 'col-sm-3', 'col-sm-3'];

        $row_rota = $this->form->addFields(
            [new TLabel('Local de Origem'),   $Local_Coleta],
            [new TLabel('Local de Destino'),  $Local_Entrega],
            [new TLabel('Apoio'),             $button_rota]
        );
        $row_rota->layout = ['col-sm-6', 'col-sm-4', 'col-sm-2'];

        $row_adu = $this->form->addFields(
            [new TLabel('Aduana / Fronteira'),   $Aduana],
            [new TLabel('Tipo de Equipamento'),  $Equipamento]
        );
        $row_adu->layout = ['col-sm-6', 'col-sm-6'];

        $row_rota1 = $this->form->addFields([new TLabel('Rota 1 (Frete Origem)'), $frete_origem_id]);
        $row_rota1->layout = ['col-sm-12'];

        $row_rota2 = $this->form->addFields([new TLabel('Rota 2 (Frete Destino)'), $frete_destino_id]);
        $row_rota2->layout = ['col-sm-12'];

        // -------------------------------------------------------
        // LAYOUT - SECAO: CUSTOS OPERACIONAIS
        // -------------------------------------------------------
        $this->form->addContent([new TFormSeparator('Custos Operacionais')]);

        // Grupos de 4 campos por linha - label acima do campo
        $grupos_custo = array_chunk($campos_custo_obj, 4);

        foreach ($grupos_custo as $grupo) {
            while (count($grupo) < 4) {
                $vazio = new TEntry('_vazio_' . uniqid());
                $vazio->setEditable(false);
                $vazio->setProperty('style', 'visibility:hidden');
                $grupo[] = ['label' => '', 'field' => $vazio];
            }

            $row_custo = $this->form->addFields(
                [new TLabel($grupo[0]['label']), $grupo[0]['field']],
                [new TLabel($grupo[1]['label']), $grupo[1]['field']],
                [new TLabel($grupo[2]['label']), $grupo[2]['field']],
                [new TLabel($grupo[3]['label']), $grupo[3]['field']]
            );
            $row_custo->layout = ['col-sm-3', 'col-sm-3', 'col-sm-3', 'col-sm-3'];
        }

        $row_total = $this->form->addFields(
            [new TLabel('&nbsp;'),        $button_despesas],
            [new TLabel('CUSTO TOTAL'),   $Total_Custo]
        );
        $row_total->layout = ['col-sm-3', 'col-sm-9'];

        // -------------------------------------------------------
        // LAYOUT - SECAO: ANALISE FINANCEIRA
        // -------------------------------------------------------
        $this->form->addContent([new TFormSeparator('Analise Financeira')]);

        $row_fat = $this->form->addFields(
            [new TLabel('Faturamento (R$)'),   $Valor_Faturamento],
            [new TLabel('Taxa Dolar'),         $Taxa_Dolar],
            [new TLabel('Faturamento (USD)'),  $Faturamento_Dolar]
          
        );
        $row_fat->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];

        $row_tax = $this->form->addFields(
            [new TLabel('Impostos %'),     $Aliq_Imposto],
            [new TLabel('Vlr. Impostos'),  $Valor_Impostos_Calc],
            [new TLabel('Swift %'),        $Taxa_Swift],
            [new TLabel('Vlr. Swift'),     $Valor_Swift_Calc],
            [new TLabel('Seguro %'),       $Aliq_Seguro],
            [new TLabel('Vlr. Seguro'),    $Valor_Seguro_Calc]
        );
        $row_tax->layout = ['col-sm-2', 'col-sm-2', 'col-sm-2', 'col-sm-2', 'col-sm-2', 'col-sm-2'];

        $row_res = $this->form->addFields(
            [new TLabel('Fat. Liquido (R$)'),  $Fat_Liquido_Reais],
            [new TLabel('Margem (R$)'),        $Resultado_Final],
            [new TLabel('Resultado (USD)'),    $Resultado_Dolar],
            [new TLabel('Margem %'),           $Margem_Percentual]
        );
        $row_res->layout = ['col-sm-3', 'col-sm-3', 'col-sm-3', 'col-sm-3'];

        $row_btn_fat = $this->form->addFields(
            [new TLabel('&nbsp;'), $button_fatliquido]
        );
        $row_btn_fat->layout = ['col-sm-12'];

        // -------------------------------------------------------
        // LAYOUT - SECAO: OBSERVACOES
        // -------------------------------------------------------
        $this->form->addContent([new TFormSeparator('Observacoes')]);
        $this->form->addFields([new TLabel('Anotacoes'), $observacoes]);
        $this->form->addFields([$id]);

        // -------------------------------------------------------
        // ACOES DO FORMULARIO
        // -------------------------------------------------------
        $this->form->addAction('Salvar Proposta', new TAction([$this, 'onSave']), 'fas:save #ffffff')
                   ->addStyleClass('btn-primary');

        $this->form->addAction('Limpar', new TAction([$this, 'onClear']), 'fas:eraser #ffffff')
                   ->addStyleClass('btn-danger');

        $this->form->addAction('Voltar', new TAction(['PropostaList', 'onReload']), 'fas:arrow-left #ffffff')
                   ->addStyleClass('btn-secondary');

        $this->form->addAction('Tabela de Fretes', new TAction([$this, 'onConsultarFretes']), 'fas:table #ffffff')
                   ->addStyleClass('btn-success');

        // -------------------------------------------------------
        // CONTAINER PRINCIPAL
        // -------------------------------------------------------
        // Re-bind dos combos de frete no carregamento inicial da página
        TScript::create("
            setTimeout(function(){
                var formName = '" . self::$formName . "';
                $('[name=frete_origem_id]').off('change.frete').on('change.frete', function(){
                    __adianti_post_data(formName, 'class=PropostaForm&method=onChangeFreteOrigem');
                });
                $('[name=frete_destino_id]').off('change.frete').on('change.frete', function(){
                    __adianti_post_data(formName, 'class=PropostaForm&method=onChangeFreteDestino');
                });
            }, 800);
        ");

        $container        = new TVBox;
        $container->style = 'width: 100%';
        $container->add(TBreadCrumb::create(['Comercial', 'Proposta de Frete']));
        $container->add($this->form);

        // -------------------------------------------------------
        // ESTILOS VISUAIS
        // -------------------------------------------------------
        $estilos = new TElement('style');
        $estilos->add('
            /* CABECALHO */
            .panel-heading {
                background: linear-gradient(135deg, #1a3a5c 0%, #2e6da4 100%) !important;
                border-radius: 8px 8px 0 0 !important;
                padding: 16px 20px !important;
                font-size: 15px;
                letter-spacing: 0.5px;
            }
            .panel-body {
                background: #f4f6f9;
                border-radius: 0 0 8px 8px;
                padding: 20px !important;
            }

            /* SEPARADORES DE SECAO */
            .form-separator {
                background: linear-gradient(90deg, #1a3a5c 0%, #2e6da4 60%, transparent 100%);
                color: #fff !important;
                font-size: 11px !important;
                font-weight: 700 !important;
                letter-spacing: 1px;
                text-transform: uppercase;
                padding: 6px 14px !important;
                border-radius: 4px;
                margin: 18px 0 10px 0 !important;
                display: block;
            }

            /* INPUTS */
            input[type=text],
            input[type=number],
            select,
            textarea {
                border-radius: 5px !important;
                border: 1px solid #cdd5df !important;
                transition: border-color 0.2s, box-shadow 0.2s;
                font-size: 13px !important;
                padding: 6px 10px !important;
                background-color: #fff !important;
            }
            input[type=text]:focus,
            input[type=number]:focus,
            select:focus,
            textarea:focus {
                border-color: #2e6da4 !important;
                box-shadow: 0 0 0 3px rgba(46,109,164,0.15) !important;
                outline: none !important;
            }

            /* LABELS */
            label {
                font-size: 11px !important;
                font-weight: 700 !important;
                color: #4a5568 !important;
                margin-bottom: 3px !important;
                text-transform: uppercase;
                letter-spacing: 0.4px;
                display: block;
            }

            /* BOTOES */
            .btn {
                border-radius: 5px !important;
                font-weight: 600 !important;
                font-size: 13px !important;
                padding: 7px 16px !important;
                transition: all 0.2s ease !important;
            }
            .btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.18) !important;
            }

            /* BREADCRUMB */
            .breadcrumb {
                background: transparent !important;
                padding: 6px 0 10px 0 !important;
                font-size: 13px;
            }

            /* LINHAS */
            .row { margin-bottom: 8px !important; }

            /* GRAFICO DE CUSTOS */
            #custo_percentual_chart {
                background: #fff;
                border: 1px solid #dce3ec;
                border-radius: 8px;
                padding: 14px;
                margin-bottom: 6px;
            }
            .custo-chart-row {
                margin-bottom: 9px;
            }
            .custo-chart-header {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                font-size: 12px;
                color: #2d3748;
                margin-bottom: 4px;
            }
            .custo-chart-track {
                width: 100%;
                height: 16px;
                border-radius: 999px;
                background: #edf2f7;
                overflow: hidden;
            }
            .custo-chart-bar {
                height: 100%;
                border-radius: 999px;
                background: linear-gradient(90deg, #2e6da4 0%, #5aa6e6 100%);
                transition: width .3s ease;
            }
            .custo-chart-empty {
                padding: 8px 2px;
                color: #718096;
                font-size: 13px;
            }
        ');
        $container->add($estilos);

        $campos_custo_js = json_encode(self::getCostFieldsLabels(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $scriptGrafico = new TElement('script');
        $scriptGrafico->add("
            (function () {
                const COST_FIELDS = {$campos_custo_js};

                function parseBrazilianNumber(value) {
                    if (value === null || value === undefined) {
                        return 0;
                    }
                    const normalized = String(value).trim().replace(/\\./g, '').replace(',', '.');
                    const number = parseFloat(normalized);
                    return isNaN(number) ? 0 : number;
                }

                function toBrl(value) {
                    try {
                        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    } catch (e) {
                        return value.toFixed(2);
                    }
                }

                function updatePropostaCostChartFromForm() {
                    const container = document.getElementById('custo_percentual_chart');
                    if (!container) {
                        return;
                    }

                    const itens = [];
                    let total = 0;

                    Object.keys(COST_FIELDS).forEach(function (field) {
                        const input = document.querySelector('[name=\"' + field + '\"]');
                        const value = parseBrazilianNumber(input ? input.value : 0);
                        if (value > 0) {
                            itens.push({ field: field, label: COST_FIELDS[field], value: value });
                            total += value;
                        }
                    });

                    if (total <= 0 || itens.length === 0) {
                        container.innerHTML = \"<div class='custo-chart-empty'>Preencha os custos para visualizar o percentual de cada item na cotacao.</div>\";
                        return;
                    }

                    itens.sort(function (a, b) { return b.value - a.value; });

                    let html = '';
                    itens.forEach(function (item) {
                        const pct = (item.value / total) * 100;
                        const pctText = pct.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
                        html += \"<div class='custo-chart-row'>\"
                              + \"<div class='custo-chart-header'><span>\" + item.label + \"</span><span>\" + pctText + \" (R$ \" + toBrl(item.value) + \")</span></div>\"
                              + \"<div class='custo-chart-track'><div class='custo-chart-bar' style='width:\" + Math.max(0, Math.min(100, pct)).toFixed(2) + \"%;'></div></div>\"
                              + \"</div>\";
                    });

                    container.innerHTML = html;
                }

                window.updatePropostaCostChartFromForm = updatePropostaCostChartFromForm;
                document.addEventListener('DOMContentLoaded', function () {
                    setTimeout(updatePropostaCostChartFromForm, 80);
                });
            })();
        ");
        $container->add($scriptGrafico);

        parent::add($container);
    }

    // -------------------------------------------------------
    // REGRAS DE NEGOCIO
    // -------------------------------------------------------

    /**
     * Soma todos os campos de custo e atualiza o total da operacao
     */
    public static function ontotaldespesas($param = null): void
    {
        $param = is_array($param) ? $param : [];
        $campos_custo = [
            'frete_origem', 'frete_destino', 'enlonamento', 'estadia_multilog',
            'repres_libres', 'repres_multilog', 'repres_uruguaiana', 'repres_uspallata',
            'repres_chile', 'armazenagem_transbordo', 'gerenciadora_risco', 'comissao_venda',
        ];

        $total = 0;
        foreach ($campos_custo as $campo) {
            $total += self::toDouble($param[$campo] ?? 0);
        }

        $dados                             = new stdClass();
        $dados->Custo_Total_Operacao_Valor = number_format($total, 2, ',', '.');
        TForm::sendData(self::$formName, $dados);
    }

    /**
     * Calcula faturamento liquido, impostos, seguro e swift
     */
    public static function onFaturamento($param = null): void
    {
        $param = is_array($param) ? $param : [];
        $fat_brl  = self::toDouble($param['Faturamento_Valor_1']    ?? 0);
        $taxa     = self::toDouble($param['Taxa_Dolar']              ?? 0);
        $imp_pct  = self::toDouble($param['Percentual_Impostos_FOB'] ?? 0);
        $seg_pct  = self::toDouble($param['Percentual_Seguro_FOB']   ?? 0);
        $swi_pct  = self::toDouble($param['taxa_swift']              ?? 0);
        $fob      = self::toDouble($param['FOB_Mercadoria_Valor']    ?? 0);

        $v_seg = ($fob * ($seg_pct / 100)) * $taxa;
        $v_imp = $fat_brl * ($imp_pct / 100);
        $v_swi = $fat_brl * ($swi_pct / 100);
        $liq   = $fat_brl - $v_seg - $v_swi - $v_imp;

        $dados                          = new stdClass();
        $dados->fat_dolar               = number_format($taxa > 0 ? $fat_brl / $taxa : 0, 2, ',', '.');
        $dados->valor_seguro            = number_format($v_seg, 2, ',', '.');
        $dados->valor_swift             = number_format($v_swi, 2, ',', '.');
        $dados->Impostos_Operacao_Valor = number_format($v_imp, 2, ',', '.');
        $dados->fat_liquido_reais       = number_format($liq,   2, ',', '.');
        TForm::sendData(self::$formName, $dados);

        $param['fat_liquido_reais'] = $dados->fat_liquido_reais;
        self::onResultados($param);
    }

    /**
     * Calcula resultado final e margem
     */
    public static function onResultados($param = null): void
    {
        $param = is_array($param) ? $param : [];
        $liq = self::toDouble($param['fat_liquido_reais']          ?? 0);
        $cus = self::toDouble($param['Custo_Total_Operacao_Valor'] ?? 0);
        $tax = self::toDouble($param['Taxa_Dolar']                 ?? 0);
        $fat = self::toDouble($param['Faturamento_Valor_1']        ?? 0);
        $res = $liq - $cus;

        $dados                    = new stdClass();
        $dados->resultado_final   = number_format($res, 2, ',', '.');
        $dados->resultado_dolar   = number_format($tax > 0 ? $res / $tax : 0, 2, ',', '.');
        $dados->margem_percentual = number_format($fat > 0 ? ($res / $fat) * 100 : 0, 2, ',', '.');
        TForm::sendData(self::$formName, $dados);
    }

    /**
     * Calcula data de validade = Data_Cotacao + 15 dias
     */
    public static function ontrinta($param = null): void
    {
        $param = is_array($param) ? $param : [];
        if (!empty($param['Data_Cotacao'])) {
            $baseDate = self::normalizeDateToDb((string) $param['Data_Cotacao']);
            if ($baseDate) {
                $dt = DateTime::createFromFormat('Y-m-d', $baseDate);
                $dt->modify('+15 days');
                $dados                        = new stdClass();
                $dados->Data_Validade_Cotacao = $dt->format('d/m/Y');
                TForm::sendData(self::$formName, $dados);
            }
        }
    }

    /**
     * Salva a proposta no banco de dados
     */
    public function onSave($param = null): void
    {
        try {
            $this->form->validate();
            $data = $this->form->getData();
            $data->Data_Cotacao          = self::normalizeDateToDb($data->Data_Cotacao ?? null);
            $data->Data_Validade_Cotacao = self::normalizeDateToDb($data->Data_Validade_Cotacao ?? null);

            TTransaction::open(self::$database);

            $proposta = !empty($data->id) ? new Proposta($data->id) : new Proposta;
            $proposta->fromArray((array) $data);

            foreach (self::getNumericFields() as $campo) {
                if (isset($data->$campo)) {
                    $proposta->$campo = self::toDouble($data->$campo);
                }
            }

            $proposta->store();

            if (empty($proposta->Cotacao_ID)) {
                $proposta->Cotacao_ID = str_pad((string) $proposta->id, 4, '0', STR_PAD_LEFT) . '/' . date('Y');
                $proposta->store();
            }

            $data->id         = $proposta->id;
            $data->Cotacao_ID = $proposta->Cotacao_ID;
            $data->Data_Cotacao = self::normalizeDateToView($data->Data_Cotacao ?? null);
            $data->Data_Validade_Cotacao = self::normalizeDateToView($data->Data_Validade_Cotacao ?? null);
            $this->form->setData($data);

            TTransaction::close();
            TToast::show('success', 'Proposta salva com sucesso!');
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            try { TTransaction::rollback(); } catch (Exception $ee) {}
        }
    }

    public function onEdit($param): void
    {
        try {
            $param = is_array($param) ? $param : [];

            if (isset($param['key'])) {
                TTransaction::open(self::$database);
                $proposta = new Proposta($param['key']);
                TTransaction::close();

                $campos_2casas = self::getDisplayNumericFields();

                $dados = new stdClass();
                foreach ((array) $proposta->toArray() as $chave => $valor) {
                    $dados->$chave = $valor;
                }

                foreach ($campos_2casas as $campo) {
                    if (isset($dados->$campo) && $dados->$campo !== null && $dados->$campo !== '') {
                        $dados->$campo = number_format((float) $dados->$campo, 2, ',', '.');
                    }
                }

                if (isset($dados->Taxa_Dolar) && $dados->Taxa_Dolar !== null && $dados->Taxa_Dolar !== '') {
                    $dados->Taxa_Dolar = number_format((float) $dados->Taxa_Dolar, 4, ',', '.');
                }
                if (isset($dados->Data_Cotacao)) {
                    $dados->Data_Cotacao = self::normalizeDateToView($dados->Data_Cotacao);
                }
                if (isset($dados->Data_Validade_Cotacao)) {
                    $dados->Data_Validade_Cotacao = self::normalizeDateToView($dados->Data_Validade_Cotacao);
                }

                $this->form->setData($dados);
                TForm::sendData(self::$formName, $dados);
                return;
            }

            $dados = new stdClass();
            $dados->Data_Cotacao = date('d/m/Y');
            $dados->Data_Validade_Cotacao = date('d/m/Y', strtotime('+15 days'));
            $dados->Situacao = 'Em Analise';

            $empresa = trim((string) ($param['opportunity_company'] ?? ''));
            $contato = trim((string) ($param['opportunity_contact'] ?? ''));
            $email = trim((string) ($param['opportunity_email'] ?? ''));
            $telefone = trim((string) ($param['opportunity_phone'] ?? ''));
            $notas = trim((string) ($param['opportunity_notes'] ?? ''));
            $opportunityId = trim((string) ($param['opportunity_id'] ?? ''));

            if ($empresa !== '') {
                $clienteId = self::findClienteIdByName($empresa);
                if (!empty($clienteId)) {
                    $dados->cliente_id = $clienteId;
                }
            }

            $obs = [];
            if ($opportunityId !== '') {
                $obs[] = 'Origem CRM - Oportunidade #' . $opportunityId;
            }
            if ($empresa !== '') {
                $obs[] = 'Empresa: ' . $empresa;
            }
            if ($contato !== '') {
                $obs[] = 'Contato: ' . $contato;
            }
            if ($email !== '') {
                $obs[] = 'E-mail: ' . $email;
            }
            if ($telefone !== '') {
                $obs[] = 'Telefone: ' . $telefone;
            }
            if ($notas !== '') {
                $obs[] = 'Notas: ' . $notas;
            }

            if (!empty($obs)) {
                $dados->observacoes = implode("\r\n", $obs);
            }

            $this->form->clear(true);
            $this->form->setData($dados);
            TForm::sendData(self::$formName, $dados);
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            try { TTransaction::rollback(); } catch (Exception $ee) {}
        }
    }

    /**
     * Script JS reutilizável para re-vincular os eventos change dos combos de frete
     * após qualquer reloadFromModel.
     */
    private static function rebindFreteComboEvents(): void
    {
        TScript::create("
            setTimeout(function(){
                var formName = '" . self::$formName . "';
                $('[name=frete_origem_id]').off('change.frete').on('change.frete', function(){
                    __adianti_post_data(formName, 'class=PropostaForm&method=onChangeFreteOrigem');
                });
                $('[name=frete_destino_id]').off('change.frete').on('change.frete', function(){
                    __adianti_post_data(formName, 'class=PropostaForm&method=onChangeFreteDestino');
                });
            }, 600);
        ");
    }

    public static function onChangeFreteOrigem($param): void
    {
        if (!empty($param['frete_origem_id'])) {
            try {
                TTransaction::open(self::$database);
                $frete = new TabelaFrete($param['frete_origem_id']);

                $obj = new stdClass;
                $obj->frete_origem = number_format((float) $frete->valor_frete, 2, ',', '.');
                TForm::sendData(self::$formName, $obj);

                $param['frete_origem'] = $obj->frete_origem;
                self::ontotaldespesas($param);

                TToast::show('success', 'Rota 1: Frete Origem = R$ ' . $obj->frete_origem);

                // Filtra Rota 2 para opções que partem do destino da Rota 1
                if (!empty($frete->destino)) {
                    $criteria2 = new TCriteria;
                    $criteria2->add(new TFilter('origem', '=', $frete->destino));

                    $destinoFinal = trim(mb_strtoupper((string) ($param['Local_Entrega'] ?? ''), 'UTF-8'));
                    $tipo         = trim(mb_strtoupper((string) ($param['Tipo_Equipamento'] ?? ''), 'UTF-8'));

                    if ($destinoFinal) {
                        $criteria2->add(new TFilter('destino', '=', $destinoFinal));
                    }
                    if ($tipo) {
                        $filter2 = new TCriteria;
                        $filter2->add(new TFilter('tipo_veiculo', '=', $tipo), TExpression::OR_OPERATOR);
                        $filter2->add(new TFilter('tipo_veiculo', '=', 'GERAL'), TExpression::OR_OPERATOR);
                        $criteria2->add($filter2);
                    }

                    TDBCombo::reloadFromModel(self::$formName, 'frete_destino_id', self::$database, 'TabelaFrete', 'id', 'Origem: {origem} > Fronteira: {fronteira} > Destino: {destino} - R$ {valor_frete}', 'id desc', $criteria2);
                }

                TTransaction::close();
                self::rebindFreteComboEvents();

            } catch (Exception $e) {
                TTransaction::rollback();
                TToast::show('error', 'Falha Rota 1: ' . $e->getMessage());
            }
        } else {
            TToast::show('info', 'Selecione uma rota na Rota 1');
        }
    }

    public static function onChangeFreteDestino($param): void
    {
        if (!empty($param['frete_destino_id'])) {
            try {
                TTransaction::open(self::$database);
                $frete = new TabelaFrete($param['frete_destino_id']);
                TTransaction::close();

                $obj = new stdClass;
                $obj->frete_destino = number_format((float) $frete->valor_frete, 2, ',', '.');
                TForm::sendData(self::$formName, $obj);

                $param['frete_destino'] = $obj->frete_destino;
                self::ontotaldespesas($param);

                TToast::show('success', 'Rota 2: Frete Destino = R$ ' . $obj->frete_destino);
            } catch (Exception $e) {
                TTransaction::rollback();
                TToast::show('error', 'Falha Rota 2: ' . $e->getMessage());
            }
        } else {
            TToast::show('info', 'Selecione uma rota na Rota 2');
        }
    }

    public static function onReloadRotas($param): void
    {
        try {
            TTransaction::open(self::$database);

            $origem  = trim(mb_strtoupper((string) ($param['Local_Coleta'] ?? ''), 'UTF-8'));
            $aduana  = trim(mb_strtoupper((string) ($param['Aduana_Fronteira'] ?? ''), 'UTF-8'));
            $destino = trim(mb_strtoupper((string) ($param['Local_Entrega'] ?? ''), 'UTF-8'));
            $tipo    = trim(mb_strtoupper((string) ($param['Tipo_Equipamento'] ?? ''), 'UTF-8'));

            // Critérios Rota 1: parte da origem e termina na aduana
            $criteria1 = new TCriteria;
            if ($origem) $criteria1->add(new TFilter('origem',    'like', '%' . $origem . '%'));
            if ($aduana) $criteria1->add(new TFilter('fronteira', 'like', '%' . $aduana . '%'));
            if ($aduana) $criteria1->add(new TFilter('destino',   'like', '%' . $aduana . '%'));
            if ($tipo) {
                $filter1 = new TCriteria;
                $filter1->add(new TFilter('tipo_veiculo', '=', $tipo),   TExpression::OR_OPERATOR);
                $filter1->add(new TFilter('tipo_veiculo', '=', 'GERAL'), TExpression::OR_OPERATOR);
                $criteria1->add($filter1);
            }

            TDBCombo::reloadFromModel(self::$formName, 'frete_origem_id', self::$database, 'TabelaFrete', 'id', 'Origem: {origem} > Fronteira: {fronteira} > Destino: {destino} - R$ {valor_frete}', 'id desc', $criteria1);

            // Critérios Rota 2: parte da aduana e termina no destino final
            $criteria2 = new TCriteria;
            if ($aduana)  $criteria2->add(new TFilter('origem',    'like', '%' . $aduana . '%'));
            if ($aduana)  $criteria2->add(new TFilter('fronteira', 'like', '%' . $aduana . '%'));
            if ($destino) $criteria2->add(new TFilter('destino',   'like', '%' . $destino . '%'));
            if ($tipo) {
                $filter2 = new TCriteria;
                $filter2->add(new TFilter('tipo_veiculo', '=', $tipo),   TExpression::OR_OPERATOR);
                $filter2->add(new TFilter('tipo_veiculo', '=', 'GERAL'), TExpression::OR_OPERATOR);
                $criteria2->add($filter2);
            }

            TDBCombo::reloadFromModel(self::$formName, 'frete_destino_id', self::$database, 'TabelaFrete', 'id', 'Origem: {origem} > Fronteira: {fronteira} > Destino: {destino} - R$ {valor_frete}', 'id desc', $criteria2);

            TTransaction::close();

            // Re-vincular eventos após reloadFromModel
            self::rebindFreteComboEvents();

        } catch (Exception $e) {
            TTransaction::rollback();
        }
    }
    private static function normalizarTrechoFrete(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        return mb_strtoupper((string) $value, 'UTF-8');
    }
    private static function findClienteIdByName(string $companyName): ?int
    {
        if (trim($companyName) === '') {
            return null;
        }

        try {
            TTransaction::open(self::$database);

            $criteria = new TCriteria();
            $criteria->add(new TFilter('nome', 'like', '%' . $companyName . '%'));
            $criteria->setProperty('limit', 1);
            $criteria->setProperty('order', 'id desc');

            $repo = new TRepository('Clientes');
            $items = $repo->load($criteria);

            TTransaction::close();

            if ($items && isset($items[0]->id)) {
                return (int) $items[0]->id;
            }
        } catch (Exception $e) {
            try { TTransaction::rollback(); } catch (Exception $ee) {}
        }

        return null;
    }
    /**
     * Limpa o formulario
     */

    private static function getCostFieldsLabels(): array
    {
        return [
            'frete_origem'           => 'Frete Origem',
            'frete_destino'          => 'Frete Destino',
            'enlonamento'            => 'Enlonamento',
            'estadia_multilog'       => 'Estadia Multilog',
            'repres_multilog'        => 'Repres. Multilog',
            'repres_uruguaiana'      => 'Repres. Uruguaiana',
            'repres_libres'          => 'Repres. Libres',
            'repres_uspallata'       => 'Repres. Uspallata',
            'repres_chile'           => 'Repres. Chile',
            'armazenagem_transbordo' => 'Armazenagem Transbordo',
            'comissao_venda'         => 'Comissao de Venda',
            'gerenciadora_risco'     => 'Gerenciadora de Risco',
        ];
    }
    private static function getNumericFields(): array
    {
        return [
            'Faturamento_Valor_1', 'Taxa_Dolar', 'Custo_Total_Operacao_Valor',
            'Percentual_Impostos_FOB', 'Percentual_Seguro_FOB', 'taxa_swift',
            'FOB_Mercadoria_Valor', 'frete_origem', 'frete_destino', 'enlonamento',
            'estadia_multilog', 'repres_libres', 'repres_multilog', 'repres_uruguaiana',
            'repres_uspallata', 'repres_chile', 'armazenagem_transbordo',
            'gerenciadora_risco', 'comissao_venda',
        ];
    }

    private static function getDisplayNumericFields(): array
    {
        return array_merge(self::getNumericFields(), [
            'fat_dolar', 'fat_liquido_reais', 'resultado_final',
            'resultado_dolar', 'margem_percentual',
            'Impostos_Operacao_Valor', 'valor_swift', 'valor_seguro',
        ]);
    }

    public function onClear(): void
    {
        $this->form->clear(true);
    }

    /**
     * Converte valor BR (1.234,56) para float
     */
    public static function toDouble(mixed $value): float
    {
        if (empty($value)) {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        return (float) str_replace(',', '.', str_replace('.', '', $value));
    }

    private static function normalizeDateToDb(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y', 'y-m-d'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat('!' . $format, $value);
            if ($dt && $dt->format($format) === $value) {
                $year = (int) $dt->format('Y');
                if ($year < 100) {
                    $dt->setDate($year + 2000, (int) $dt->format('m'), (int) $dt->format('d'));
                }
                return $dt->format('Y-m-d');
            }
        }

        return $value;
    }

    private static function normalizeDateToView(?string $value): ?string
    {
        $dbDate = self::normalizeDateToDb($value);
        if (empty($dbDate)) {
            return $dbDate;
        }

        $dt = DateTime::createFromFormat('!Y-m-d', $dbDate);
        return $dt ? $dt->format('d/m/Y') : $value;
    }
    /**
     * Exibe rota com 3 pontos no Google Maps e repovoar o formulario
     */
    public function onMapa($param = null): void
    {
        $param = is_array($param) ? $param : [];
        $dados_form = new stdClass();
        foreach ($param as $chave => $valor) {
            $dados_form->$chave = $valor;
        }
        TForm::sendData(self::$formName, $dados_form);

        $origem        = trim($param['Local_Coleta']     ?? '');
        $intermediario = trim($param['Aduana_Fronteira'] ?? '');
        $destino       = trim($param['Local_Entrega']    ?? '');

        if ($origem && $destino) {
            $url = 'https://www.google.com/maps/dir/' . urlencode($origem);
            if ($intermediario) {
                $url .= '/' . urlencode($intermediario);
            }
            $url .= '/' . urlencode($destino);

            $label_inter = $intermediario
                ? "<strong>{$intermediario}</strong>"
                : "<em style='color:#aaa'>nao informado</em>";

            new TMessage('info', "
                <div style='text-align:center; padding:10px; min-width:340px'>
                    <h4 style='margin-bottom:16px; color:#1a3a5c; font-size:15px; font-weight:700'>
                        Rota da Operacao
                    </h4>
                    <table style='width:100%; border-collapse:separate; border-spacing:0 5px; font-size:13px; margin-bottom:16px'>
                        <tr>
                            <td style='padding:8px 12px; background:#f0fff4; border-radius:6px; border-left:4px solid #38a169'>
                                <strong>Origem:</strong> {$origem}
                            </td>
                        </tr>
                        <tr>
                            <td style='padding:8px 12px; background:#fff8e1; border-radius:6px; border-left:4px solid #d97706'>
                                <strong>Aduana / Fronteira:</strong> {$label_inter}
                            </td>
                        </tr>
                        <tr>
                            <td style='padding:8px 12px; background:#fff0f0; border-radius:6px; border-left:4px solid #e53e3e'>
                                <strong>Destino:</strong> {$destino}
                            </td>
                        </tr>
                    </table>
                    <a href='{$url}' target='_blank'
                       style='display:inline-block;
                              background:linear-gradient(135deg,#1a3a5c,#2e6da4);
                              color:#fff; padding:10px 26px; border-radius:6px;
                              text-decoration:none; font-weight:700; font-size:13px;
                              box-shadow:0 3px 10px rgba(0,0,0,0.2)'>
                        Abrir no Google Maps
                    </a>
                </div>
            ");
        } else {
            new TMessage('warning', 'Preencha ao menos <strong>Local de Origem</strong> e <strong>Local de Destino</strong> para visualizar a rota.');
        }
    }

    public static function onConsultarFretes($param = null): void
    {
        TScript::create("window.open('index.php?class=TabelaFreteList', '_blank');");
    }


}
























