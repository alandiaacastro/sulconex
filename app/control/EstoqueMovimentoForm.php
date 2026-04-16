<?php

use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Database\TTransaction;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Base\TScript;
use Adianti\Widget\Container\TVBox;
use Adianti\Widget\Container\TPanelGroup;
use Adianti\Widget\Dialog\TMessage;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TText;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Form\TRadioGroup;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Wrapper\TDBUniqueSearch;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Wrapper\BootstrapDatagridWrapper;
use Adianti\Widget\Datagrid\TDataGrid;
use Adianti\Widget\Datagrid\TDataGridColumn;
use Adianti\Registry\TSession;
use Adianti\Validator\TRequiredValidator;

/**
 * EstoqueMovimentoForm
 * Lancamento de Entrada de Estoque de Carga
 *
 * Fluxo: Manifesto (CRT + Importador + Exportador + NF) + dados de carga
 */
class EstoqueMovimentoForm extends TPage
{
    private BootstrapFormBuilder $form;
    private $dg_itens;

    public function __construct($param = null)
    {
        parent::__construct();

        try {
            $this->form = new BootstrapFormBuilder('form_estoque_mov');
            $this->form->setFormTitle('Lancar Entrada de Estoque');
            $this->form->setProperty('style', 'margin:0');

            // IDs e campos de controle ocultos
            $this->form->addFields([
                new THidden('id'),
                new THidden('manifesto_id'),
                new THidden('tipo'),
                new THidden('status'),
                new THidden('data_emissao'),
                new THidden('observacao'),
                new THidden('valor_total'),
                new THidden('fornecedor_nome'),
                new THidden('fornecedor_cnpj')
            ]);

            $this->buildManifestoSection();
            $this->buildCargaSection();
            $this->buildEntradaSaidaSection();
            $this->buildItensGrid();
            $this->buildActions();

            $vbox = new TVBox();
            $vbox->setProperty('style', 'display:block; width:100%');
            $vbox->add($this->buildPageHeader());
            $vbox->add($this->form);

            parent::add($vbox);

            if (!empty($param['key'])) {
                $this->onLoad(['key' => $param['key']]);
            } else {
                $data = new \stdClass();
                $data->tipo   = 'entrada';
                $data->status = 'confirmado';
                $data->data_movimento = date('d/m/Y');
                $this->form->setData($data);
            }

        } catch (\Exception $e) {
            new TMessage('error', $e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // HEADER com cards informativos
    // ════════════════════════════════════════════════════════════════════

    private function buildPageHeader(): TElement
    {
        $row = new TElement('div');
        $row->class = 'row g-3 mb-3';

        $cards = [
            ['icon' => 'fas fa-arrow-circle-down', 'border' => 'border-success', 'icon_class' => 'text-success',
             'label' => 'Entrada',  'desc' => 'Registra chegada de carga ao terminal'],
        ];

        foreach ($cards as $c) {
            $col  = new TElement('div'); $col->class  = 'col-md-12';
            $card = new TElement('div'); $card->class = 'card h-100 border-start border-4 ' . $c['border'];
            $body = new TElement('div'); $body->class = 'card-body py-3 d-flex align-items-center gap-3';
            $icon = new TElement('i');  $icon->class  = $c['icon'] . ' fa-2x ' . $c['icon_class'];
            $txt  = new TElement('div');
            $h    = new TElement('strong'); $h->add($c['label']);
            $d    = new TElement('div');    $d->class = 'text-muted small'; $d->add($c['desc']);
            $txt->add($h); $txt->add($d);
            $body->add($icon); $body->add($txt);
            $card->add($body); $col->add($card); $row->add($col);
        }
        return $row;
    }

    // ════════════════════════════════════════════════════════════════════
    // SEÇÃO 1 – UPLOAD XML NF-e
    // ════════════════════════════════════════════════════════════════════

    private function buildXmlSection(): void
    {
        $sep = new TElement('div');
        $sep->class = 'card border-info mb-2 bg-info bg-opacity-10';
        $head = new TElement('div');
        $head->class = 'card-header py-2 d-flex align-items-center gap-2 bg-info text-white';
        $head->add('<i class="fas fa-file-code fa-fw"></i> <strong>📂 Importar XML da NF-e</strong>
                    <span class="ms-2 badge bg-white text-info small">Preenchimento automático</span>');
        $sep->add($head);

        $this->form->addContent([$sep]);

        $xml_file = new TFile('xml_file');
        $xml_file->setAllowedExtensions(['xml']);
        $xml_file->setTip('Selecione o arquivo XML da NF-e');

        $btn_imp = new TButton('btn_importar');
        $btn_imp->setLabel('Importar XML');
        $btn_imp->setImage('fas:file-import');
        $btn_imp->class = 'btn btn-info btn-sm';
        $btn_imp->setAction(new TAction([$this, 'onImportarXml']));

        $alert = '<div class="alert alert-info py-2 small mt-1 mb-0">
            <i class="fas fa-info-circle me-1"></i>
            Após importar: <strong>DANFE, Fornecedor, Importador, Peso e Itens</strong> são preenchidos automaticamente.
        </div>';

        $this->form->addFields(
            [new TLabel('<i class="fas fa-upload me-1"></i> Arquivo XML (.xml)')],
            [$xml_file, $btn_imp]
        );
        $this->form->addContent([$alert]);
        $this->form->addContent(['<hr class="my-2">']);
    }

    // ════════════════════════════════════════════════════════════════════
    // SEÇÃO 2 – TIPO + DATA + STATUS
    // ════════════════════════════════════════════════════════════════════

    private function buildMovimentoSection(): void
    {
        $hdr = '<div class="card-header py-2 d-flex align-items-center gap-2 bg-dark text-white">
                    <i class="fas fa-exchange-alt fa-fw"></i><strong>Dados do Movimento</strong>
                </div>';
        $this->form->addContent([$hdr]);

        $tipo = new TRadioGroup('tipo');
        $tipo->addItems([
            'entrada' => '<span class="text-success fw-bold"><i class="fas fa-arrow-down"></i> ENTRADA</span>',
            'saida'   => '<span class="text-warning fw-bold"><i class="fas fa-arrow-up"></i> SAÍDA</span>',
        ]);
        $tipo->setValue('entrada');
        $tipo->setLayout('horizontal');
        $tipo->addValidation('Tipo', new TRequiredValidator);

        $data_mov = new TDate('data_movimento');
        $data_mov->setMask('dd/mm/yyyy');
        $data_mov->setValue(date('d/m/Y'));
        $data_mov->addValidation('Data Movimento', new TRequiredValidator);

        $data_emi = new TDate('data_emissao');
        $data_emi->setMask('dd/mm/yyyy');

        $status = new TCombo('status');
        $status->addItems([
            'confirmado' => '✅ Confirmado',
            'pendente'   => '⏳ Pendente',
            'cancelado'  => '❌ Cancelado',
        ]);
        $status->setValue('confirmado');

        $this->form->addFields(
            [new TLabel('<i class="fas fa-exchange-alt me-1 text-primary"></i> Tipo *')],
            [$tipo]
        );
        $this->form->addFields(
            [new TLabel('<i class="fas fa-calendar me-1"></i> Data Movimento *'),  [$data_mov]],
            [new TLabel('<i class="fas fa-calendar-alt me-1"></i> Data Emissão NF'), [$data_emi]],
            [new TLabel('<i class="fas fa-check-circle me-1"></i> Status'),           [$status]]
        );
        $this->form->addContent(['<hr class="my-2">']);
    }

    // ════════════════════════════════════════════════════════════════════
    // SEÇÃO 3 – MANIFESTO (CRT + Importador + Exportador + DANFE)
    // ════════════════════════════════════════════════════════════════════

    private function buildManifestoSection(): void
    {
        $hdr = '<div class="card-header py-2 d-flex align-items-center gap-2 bg-success text-white">
                    <i class="fas fa-building fa-fw"></i><strong>Manifesto - CRT / Importador / Exportador / NF</strong>
                </div>';
        $this->form->addContent([$hdr]);

        $conhecimento_id = new TDBUniqueSearch('conhecimento_id', 'sample', 'Conhecimento', 'id', 'numero');
        $conhecimento_id->setMinLength(1);
        $conhecimento_id->setOperator('like');
        $conhecimento_id->setMask('{numero}');
        $conhecimento_id->setProperty('placeholder', 'Selecione um numero CRT da tabela Conhecimento...');
        $conhecimento_id->addValidation('CRT', new TRequiredValidator);
        $conhecimento_id->setChangeAction(new TAction([__CLASS__, 'onSelectManifesto']));

        $crt_codigo = new TEntry('crt_codigo');
        $crt_codigo->setEditable(false);

        $importador_id = new TDBUniqueSearch('importador_id', 'sample', 'Clientes', 'id', 'nome');
        $importador_id->setMinLength(1);
        $importador_id->setOperator('like');
        $importador_id->setEditable(false);

        $exportador_id = new TDBUniqueSearch('exportador_id', 'sample', 'Clientes', 'id', 'nome');
        $exportador_id->setMinLength(1);
        $exportador_id->setOperator('like');
        $exportador_id->setEditable(false);

        $danfe = new TEntry('danfe');
        $danfe->setMaxLength(100);
        $danfe->setProperty('placeholder', 'Numero da nota fiscal');

        $this->form->addFields(
            [new TLabel('<i class="fas fa-search me-1 text-success"></i> <strong>CRT</strong>')],
            [$conhecimento_id]
        );
        $this->form->addFields(
            [new TLabel('<i class="fas fa-book me-1 text-success"></i> CRT selecionado')],
            [$crt_codigo]
        );
        $this->form->addFields(
            [new TLabel('<i class="fas fa-industry me-1 text-success"></i> Importador (via CRT)')],
            [$importador_id]
        );
        $this->form->addFields(
            [new TLabel('<i class="fas fa-store me-1"></i> Exportador (via CRT)')],
            [$exportador_id]
        );
        $this->form->addFields(
            [new TLabel('<i class="fas fa-barcode me-1 text-primary"></i> Numero da nota fiscal')],
            [$danfe]
        );
        $this->form->addContent(['<hr class="my-2">']);
    }

    public static function onSelectManifesto($param): void
    {
        try {
            $data = new \stdClass();

            if (empty($param['conhecimento_id'])) {
                $data->conhecimento_id = '';
                $data->manifesto_id = '';
                $data->crt_codigo = '';
                $data->importador_id = '';
                $data->exportador_id = '';
                TForm::sendData('form_estoque_mov', $data, false, true);
                return;
            }

            TTransaction::open('sample');
            EstoqueManifesto::ensureTables();
            $conhecimento = new Conhecimento((int) $param['conhecimento_id']);
            if (empty($conhecimento->id)) {
                throw new \Exception('CRT nao encontrado na tabela Conhecimento.');
            }

            $crtCodigo = trim((string) ($conhecimento->numero ?? ''));
            $crtNormalizado = EstoqueManifesto::normalizeCode($crtCodigo);

            $data->conhecimento_id = (int) $conhecimento->id;
            $data->crt_codigo = $crtCodigo;
            $data->importador_id = (int) ($conhecimento->destinatario_id ?? 0);
            $data->exportador_id = (int) ($conhecimento->remetente_id ?? 0);
            $data->manifesto_id = '';

            $conn = TTransaction::get();
            $stmt = $conn->prepare("SELECT id FROM estoque_manifesto WHERE crt_normalizado = ? LIMIT 1");
            $stmt->execute([$crtNormalizado]);
            $manifestoId = (int) $stmt->fetchColumn();

            $danfeAtual = trim((string)($param['danfe'] ?? ''));

            if ($manifestoId > 0 && $danfeAtual === '') {
                $data->manifesto_id = $manifestoId;
                $criteria = new TCriteria();
                $criteria->add(new TFilter('manifesto_id', '=', $manifestoId));
                $danfes = EstoqueManifestoDanfe::getObjects($criteria);
                $data->danfe = $danfes ? implode(' / ', array_map(fn($d) => (string) $d->danfe_codigo, $danfes)) : '';
            } else {
                $data->manifesto_id = $manifestoId > 0 ? $manifestoId : '';
            }

            TTransaction::close();
            TForm::sendData('form_estoque_mov', $data, false, true);
        } catch (\Exception $e) {
            try { TTransaction::rollback(); } catch (\Exception $ignore) {}
            new TMessage('error', $e->getMessage());
        }
    }

    private function buildCargaSection(): void
    {
        $hdr = '<div class="card-header py-2 d-flex align-items-center gap-2 bg-primary text-white">
                    <i class="fas fa-boxes fa-fw"></i><strong>Dados da Mercadoria</strong>
                </div>';
        $this->form->addContent([$hdr]);

        $tipo_carga = new TEntry('tipo_carga');
        $tipo_carga->setMaxLength(60);
        $tipo_carga->setProperty('placeholder', 'Tipo de mercadoria');
        $tipo_carga->setProperty('class', 'text-uppercase');
        $tipo_carga->addValidation('Tipo de mercadoria', new TRequiredValidator);

        $quantidade = new TNumeric('quantidade', 3, '.', ',', true);
        $quantidade->setProperty('placeholder', '0.000');
        $quantidade->addValidation('Quantidade', new TRequiredValidator);

        $tipo_volume = new TEntry('tipo_volume');
        $tipo_volume->setMaxLength(30);
        $tipo_volume->setProperty('placeholder', 'PALLET, CAIXA, BOBINA...');
        $tipo_volume->setProperty('class', 'text-uppercase');
        $tipo_volume->addValidation('Tipo de volume', new TRequiredValidator);

        $peso_bruto_kg = new TNumeric('peso_bruto_kg', 3, '.', ',', true);
        $peso_bruto_kg->setProperty('placeholder', '0.000');
        $peso_bruto_kg->addValidation('Peso bruto', new TRequiredValidator);

        $peso_liquido_kg = new TNumeric('peso_liquido_kg', 3, '.', ',', true);
        $peso_liquido_kg->setProperty('placeholder', '0.000');
        $peso_liquido_kg->addValidation('Peso liquido', new TRequiredValidator);

        $this->form->addFields(
            [new TLabel('<i class="fas fa-tag me-1"></i> Tipo mercadoria')], [$tipo_carga],
            [new TLabel('<i class="fas fa-boxes me-1 text-success"></i> Quantidade')], [$quantidade],
            [new TLabel('<i class="fas fa-box-open me-1"></i> Tipo de volume')], [$tipo_volume]
        );
        $this->form->addFields(
            [new TLabel('<i class="fas fa-weight-hanging me-1 text-success"></i> Peso bruto (kg)')], [$peso_bruto_kg],
            [new TLabel('<i class="fas fa-balance-scale me-1 text-primary"></i> Peso liquido (kg)')], [$peso_liquido_kg]
        );
        $this->form->addContent(['<hr class="my-2">']);
    }

    private function buildEntradaSaidaSection(): void
    {
        $hdrEntrada = '<div class="card-header py-2 d-flex align-items-center gap-2 bg-dark text-white">
                    <i class="fas fa-calendar-plus fa-fw"></i><strong>Entrada em Estoque / Transporte de Entrada</strong>
                </div>';
        $this->form->addContent([$hdrEntrada]);

        $data_mov = new TDate('data_movimento');
        $data_mov->setMask('dd/mm/yyyy');
        $data_mov->setDatabaseMask('yyyy-mm-dd');
        $data_mov->addValidation('Data entrada', new TRequiredValidator);
        $data_mov->setValue(date('d/m/Y'));
        $data_mov->setSize('100%');

        $motorista_nome = new TEntry('motorista_nome');
        $motorista_nome->setMaxLength(120);
        $motorista_nome->setProperty('placeholder', 'Motorista da entrada');
        $motorista_nome->setSize('100%');

        $veiculo_cavalo = new TEntry('veiculo_cavalo');
        $veiculo_cavalo->setMaxLength(12);
        $veiculo_cavalo->setProperty('placeholder', 'Placa cavalo (entrada)');
        $veiculo_cavalo->setProperty('class', 'text-uppercase fw-bold');
        $veiculo_cavalo->setSize('100%');

        $veiculo_carreta = new TEntry('veiculo_carreta');
        $veiculo_carreta->setMaxLength(12);
        $veiculo_carreta->setProperty('placeholder', 'Placa carreta (entrada)');
        $veiculo_carreta->setProperty('class', 'text-uppercase fw-bold');
        $veiculo_carreta->setSize('100%');

        $this->form->addFields(
            [new TLabel('<i class="fas fa-calendar-check me-1 text-success"></i> Data entrada *')]
        );
        $this->form->addFields(
            [$data_mov]
        );
        $rowEntradaLabels = $this->form->addFields(
            [new TLabel('<i class="fas fa-truck me-1 text-primary"></i> Placa trator entrada')],
            [new TLabel('<i class="fas fa-trailer me-1 text-primary"></i> Placa semi/carreta entrada')],
            [new TLabel('<i class="fas fa-user me-1"></i> Motorista entrada')]
        );
        $rowEntradaLabels->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];
        $rowEntrada = $this->form->addFields(
            [$veiculo_cavalo],
            [$veiculo_carreta],
            [$motorista_nome]
        );
        $rowEntrada->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];

        $hdrSaida = '<div class="card-header py-2 d-flex align-items-center gap-2 bg-secondary text-white mt-2">
                    <i class="fas fa-truck-loading fa-fw"></i><strong>Saida / Transporte de Saida</strong>
                </div>';
        $this->form->addContent([$hdrSaida]);

        $data_saida = new TDate('data_saida');
        $data_saida->setMask('dd/mm/yyyy');
        $data_saida->setDatabaseMask('yyyy-mm-dd');
        $data_saida->setSize('100%');


        $veiculo_saida_cavalo = new TEntry('veiculo_saida_cavalo');
        $veiculo_saida_cavalo->setMaxLength(12);
        $veiculo_saida_cavalo->setProperty('placeholder', 'Placa cavalo (saida)');
        $veiculo_saida_cavalo->setProperty('class', 'text-uppercase fw-bold');
        $veiculo_saida_cavalo->setSize('100%');

        $veiculo_saida_carreta = new TEntry('veiculo_saida_carreta');
        $veiculo_saida_carreta->setMaxLength(12);
        $veiculo_saida_carreta->setProperty('placeholder', 'Placa carreta (saida)');
        $veiculo_saida_carreta->setProperty('class', 'text-uppercase fw-bold');
        $veiculo_saida_carreta->setSize('100%');


        $motorista_saida_nome = new TEntry('motorista_saida_nome');
        $motorista_saida_nome->setMaxLength(120);
        $motorista_saida_nome->setProperty('placeholder', 'Motorista da saida');
        $motorista_saida_nome->setSize('100%');

        $this->form->addFields(
            [new TLabel('<i class="fas fa-calendar-day me-1"></i> Data saida')]
        );
        $this->form->addFields(
            [$data_saida]
        );
        $rowSaidaLabels = $this->form->addFields(
            [new TLabel('<i class="fas fa-truck me-1 text-primary"></i> Placa trator saida')],
            [new TLabel('<i class="fas fa-trailer me-1 text-primary"></i> Placa semi/carreta saida')],
            [new TLabel('<i class="fas fa-user me-1"></i> Motorista saida')]
        );
        $rowSaidaLabels->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];
        $rowSaida = $this->form->addFields(
            [$veiculo_saida_cavalo],
            [$veiculo_saida_carreta],
            [$motorista_saida_nome]
        );
        $rowSaida->layout = ['col-sm-4', 'col-sm-4', 'col-sm-4'];
        $this->form->addContent(['<hr class="my-2">']);
    }

private function buildItensGrid(): void
    {
        $this->dg_itens = new BootstrapDatagridWrapper(new TDataGrid());
        $this->dg_itens->style = 'width:100%;font-size:0.82rem';
        $this->dg_itens->disableDefaultClick();

        $cols = [
            new TDataGridColumn('numero_item',    'Nº',          'center', '4%'),
            new TDataGridColumn('codigo',         'Código',       'left',   '8%'),
            new TDataGridColumn('descricao',      'Descrição',    'left',   '32%'),
            new TDataGridColumn('ncm',            'NCM',          'center', '8%'),
            new TDataGridColumn('cfop',           'CFOP',         'center', '6%'),
            new TDataGridColumn('unidade',        'UN',           'center', '5%'),
            new TDataGridColumn('quantidade',     'Qtd',          'right',  '8%'),
            new TDataGridColumn('valor_unitario', 'Vl.Unit.',     'right',  '10%'),
            new TDataGridColumn('valor_total',    'Vl.Total',     'right',  '10%'),
        ];

        $cols[6]->setTransformer(fn($v) => number_format((float)$v, 4, ',', '.'));
        $cols[7]->setTransformer(fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.'));
        $cols[8]->setTransformer(fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.'));

        foreach ($cols as $col) {
            $this->dg_itens->addColumn($col);
        }
        $this->dg_itens->createModel();
    }

    private function buildItensPanel(): TElement
    {
        $panel = new TElement('div');
        $panel->class = 'card border-0 shadow-sm mb-3';

        $header = new TElement('div');
        $header->class = 'card-header py-2 d-flex align-items-center gap-2 bg-secondary text-white';
        $header->add('<i class="fas fa-list-ul fa-fw"></i> <strong>Itens da Nota Fiscal</strong>
                      <span class="ms-auto badge bg-white text-secondary">extraídos do XML</span>');

        $body = new TElement('div');
        $body->class = 'card-body p-0';

        $emptyMsg = new TElement('div');
        $emptyMsg->class = 'text-center text-muted py-4';
        $emptyMsg->add('<i class="fas fa-box-open fa-2x mb-2 d-block text-muted"></i>
                        Itens da nota fiscal vinculados ao movimento.');

        $body->add($emptyMsg);
        $body->add($this->dg_itens);

        $panel->add($header);
        $panel->add($body);
        return $panel;
    }

    // ════════════════════════════════════════════════════════════════════
    // OBSERVAÇÃO
    // ════════════════════════════════════════════════════════════════════

    private function buildObsSection(): void
    {
        $obs = new TText('observacao');
        $obs->setSize('100%', 70);
        $obs->setProperty('placeholder', 'Observações...');

        $valor_total = new TNumeric('valor_total', 2, '.', ',', true);
        $valor_total->setProperty('placeholder', '0,00');

        $this->form->addFields(
            [new TLabel('<i class="fas fa-dollar-sign me-1 text-success"></i> Valor Total NF (R$)')], [$valor_total],
            [new TLabel('<i class="fas fa-comment me-1"></i> Observações')],                           [$obs]
        );
    }

    // ════════════════════════════════════════════════════════════════════
    // BOTÕES
    // ════════════════════════════════════════════════════════════════════

    private function buildActions(): void
    {
        $this->form->addAction('Salvar', new TAction([$this, 'onSalvar']), 'fas:save green');
        $this->form->addAction('Novo',   new TAction([$this, 'onNovo']),   'fas:plus blue');
        $this->form->addAction('Voltar', new TAction(['EstoqueView', 'onReload']), 'fas:arrow-left grey');
    }

    // ════════════════════════════════════════════════════════════════════
    // ACTION: IMPORTAR XML NF-e
    // ════════════════════════════════════════════════════════════════════

    public function onImportarXml($param)
    {
        try {
            $xmlContent = null;

            if (!empty($_FILES['xml_file']['tmp_name'])) {
                $xmlContent = file_get_contents($_FILES['xml_file']['tmp_name']);
            } else {
                $fileField = $this->form->getField('xml_file');
                $fileName  = trim((string) $fileField->getValue());

                if ($fileName !== '') {
                    $tmpPath = 'tmp/' . $fileName;
                    if (file_exists($tmpPath)) {
                        $xmlContent = file_get_contents($tmpPath);
                    }
                }
            }

            if (empty($xmlContent)) {
                throw new \Exception('Nenhum arquivo XML selecionado.');
            }

            $parser = new NFeXmlParser();
            $parser->loadFromString($xmlContent);
            $nfe = $parser->parse();

            $data = $this->form->getData();

            // Cabeçalho
            $data->danfe       = $nfe->cabecalho->danfe;
            $data->data_emissao = $nfe->cabecalho->data_emissao
                ? TDate::convertToMask($nfe->cabecalho->data_emissao, 'yyyy-mm-dd', 'dd/mm/yyyy')
                : '';

            // Fornecedor (emitente)
            $data->fornecedor_nome = $nfe->emitente->nome;
            $data->fornecedor_cnpj = $nfe->emitente->cnpj_formatado ?? '';

            // Transporte
            if (!empty($nfe->transp->placa)) {
                $data->veiculo_cavalo = strtoupper($nfe->transp->placa);
            }

            // Peso e volume
            if ($nfe->transp->peso_bruto > 0) {
                $data->peso_bruto_kg = $nfe->transp->peso_bruto;
            }
            if ($nfe->transp->peso_liquido > 0) {
                $data->peso_liquido_kg = $nfe->transp->peso_liquido;
            }
            if ($nfe->transp->quantidade_vol > 0) {
                $data->quantidade = (float)$nfe->transp->quantidade_vol;
            }
            if (!empty($nfe->transp->especie)) {
                $data->tipo_volume = strtoupper((string)$nfe->transp->especie);
            }
            if (!empty($nfe->itens[0]->descricao)) {
                $data->tipo_carga = strtoupper((string)$nfe->itens[0]->descricao);
            }

            $data->valor_total = $nfe->total->valor_nf;

            $this->form->setData($data);

            // Salva na sessão para usar no onSalvar
            TSession::setValue('estoque_xml_nfe',   $xmlContent);
            TSession::setValue('estoque_nfe_itens', $nfe->itens);

            // Popula grid de itens
            $this->dg_itens->clear();
            foreach ($nfe->itens as $item) {
                $obj = new \stdClass();
                $obj->numero_item   = $item->numero_item;
                $obj->codigo        = $item->codigo;
                $obj->descricao     = $item->descricao;
                $obj->ncm           = $item->ncm;
                $obj->cfop          = $item->cfop;
                $obj->unidade       = $item->unidade;
                $obj->quantidade    = $item->quantidade;
                $obj->valor_unitario = $item->valor_unit;
                $obj->valor_total   = $item->valor_total;
                $this->dg_itens->addItem($obj);
            }

            $qtd = count($nfe->itens);
            $vt  = number_format($nfe->total->valor_nf, 2, ',', '.');

            new TMessage('info',
                "✅ <strong>XML importado!</strong><br>"
              . "<table class='table table-sm mt-2 mb-0'>"
              . "<tr><td>📄 DANFE</td><td><strong>{$nfe->cabecalho->danfe}</strong></td></tr>"
              . "<tr><td>🏭 Fornecedor</td><td>{$nfe->emitente->nome}</td></tr>"
              . "<tr><td>📦 Itens</td><td><strong>{$qtd}</strong></td></tr>"
              . "<tr><td>💰 Valor Total</td><td>R$ {$vt}</td></tr>"
              . "</table>"
            );

        } catch (\Exception $e) {
            new TMessage('error', 'Erro ao importar XML:<br>' . $e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // ACTION: SALVAR
    // ════════════════════════════════════════════════════════════════════

    public function onSalvar($param)
    {
        try {
            $this->form->validate();
            $data = $this->form->getData();
            $data->tipo = 'entrada';

            $danfeInformado = trim((string)($data->danfe ?? ($param['danfe'] ?? '')));
            if ($danfeInformado === '') {
                throw new \Exception('O campo Numero da nota fiscal e obrigatorio.');
            }
            $data->danfe = $danfeInformado;

            TTransaction::open('sample');

            // ── 1. Manifesto (via CRT da tabela Conhecimento) ─────────────
            if (empty($data->conhecimento_id)) {
                throw new \Exception('Selecione um CRT da tabela Conhecimento para continuar.');
            }

            $conhecimento = new Conhecimento((int) $data->conhecimento_id);
            if (empty($conhecimento->id)) {
                throw new \Exception('CRT selecionado nao encontrado em Conhecimento.');
            }

            $crtCodigo = trim((string) ($conhecimento->numero ?? ''));
            if ($crtCodigo === '') {
                throw new \Exception('Conhecimento selecionado sem numero CRT.');
            }

            $exportadorId = (int) ($conhecimento->remetente_id ?? 0);
            $importadorId = (int) ($conhecimento->destinatario_id ?? 0);
            if ($exportadorId <= 0 || $importadorId <= 0) {
                throw new \Exception('CRT sem remetente/destinatario definidos para vincular exportador/importador.');
            }

            $crtNorm = EstoqueManifesto::normalizeCode($crtCodigo);
            $conn = TTransaction::get();
            $findManifesto = $conn->prepare("SELECT id FROM estoque_manifesto WHERE crt_normalizado = ? LIMIT 1");
            $findManifesto->execute([$crtNorm]);
            $manifestoId = (int) $findManifesto->fetchColumn();

            if ($manifestoId > 0) {
                $manifesto = new EstoqueManifesto($manifestoId);
                $manifesto->crt_codigo = $crtCodigo;
                $manifesto->exportador_id = $exportadorId;
                $manifesto->importador_id = $importadorId;
                $manifesto->updated_at = date('Y-m-d H:i:s');
                $manifesto->store();
            } else {
                $manifesto = new EstoqueManifesto();
                $manifesto->crt_codigo = $crtCodigo;
                $manifesto->crt_normalizado = $crtNorm;
                $manifesto->exportador_id = $exportadorId;
                $manifesto->importador_id = $importadorId;
                $manifesto->created_at = date('Y-m-d H:i:s');
                $manifesto->updated_at = date('Y-m-d H:i:s');
                $manifesto->store();
            }

            $data->manifesto_id = (int) $manifesto->id;
            $data->crt_codigo = (string) $manifesto->crt_codigo;
            $data->importador_id = (int) $manifesto->importador_id;
            $data->exportador_id = (int) $manifesto->exportador_id;

            // ── 2. DANFE(s) no manifesto ──────────────────────────────────
            if (!empty($data->danfe)) {
                $danfes = array_map('trim', explode('/', $data->danfe));
                foreach ($danfes as $dNum) {
                    if (!$dNum) continue;
                    $dNorm = EstoqueManifesto::normalizeCode($dNum);

                    $conn = TTransaction::get();
                    $exists = $conn->prepare("SELECT id FROM estoque_manifesto_danfe WHERE danfe_normalizado=? LIMIT 1");
                    $exists->execute([$dNorm]);
                    if (!$exists->fetch()) {
                        $danfeRec = new EstoqueManifestoDanfe();
                        $danfeRec->manifesto_id      = $manifesto->id;
                        $danfeRec->danfe_codigo       = $dNum;
                        $danfeRec->danfe_normalizado  = $dNorm;
                        $danfeRec->created_at         = date('Y-m-d H:i:s');
                        $danfeRec->store();
                    }
                }
            }

            // ── 3. Movimento ──────────────────────────────────────────────
            $mov = empty($data->id) ? new EstoqueMovimento() : new EstoqueMovimento($data->id);
            $pesoBruto = (float) str_replace(',', '.', $data->peso_bruto_kg ?? $data->peso_kg ?? 0);
            $pesoLiquido = (float) str_replace(',', '.', $data->peso_liquido_kg ?? 0);
            $quantidade = (float) str_replace(',', '.', $data->quantidade ?? $data->bobinas ?? 0);

            $mov->manifesto_id    = $manifesto->id;
            $mov->tipo            = 'entrada';
            $mov->peso_kg         = $pesoBruto;
            $mov->bobinas         = (int) round($quantidade);
            $mov->peso_bruto_kg   = $pesoBruto;
            $mov->peso_liquido_kg = $pesoLiquido;
            $mov->quantidade      = $quantidade;
            $mov->tipo_volume     = strtoupper((string) ($data->tipo_volume ?? ''));
            $mov->data_movimento  = $data->data_movimento
                ? TDate::convertToMask($data->data_movimento, 'dd/mm/yyyy', 'yyyy-mm-dd')
                : date('Y-m-d');
            $mov->data_saida      = $data->data_saida
                ? TDate::convertToMask($data->data_saida, 'dd/mm/yyyy', 'yyyy-mm-dd')
                : null;
            $mov->observacao      = $data->observacao;
            $mov->motorista_nome  = $data->motorista_nome;
            $mov->veiculo_cavalo  = strtoupper($data->veiculo_cavalo ?? '');
            $mov->veiculo_carreta = strtoupper($data->veiculo_carreta ?? '');
            $mov->motorista_saida_nome = $data->motorista_saida_nome;
            $mov->veiculo_saida_cavalo = strtoupper($data->veiculo_saida_cavalo ?? '');
            $mov->veiculo_saida_carreta = strtoupper($data->veiculo_saida_carreta ?? '');
            $mov->tipo_carga      = strtoupper((string) ($data->tipo_carga ?? ''));
            $mov->danfe           = $data->danfe;
            $mov->data_emissao    = $data->data_emissao
                ? TDate::convertToMask($data->data_emissao, 'dd/mm/yyyy', 'yyyy-mm-dd')
                : null;
            $mov->fornecedor_nome = $data->fornecedor_nome;
            $mov->fornecedor_cnpj = $data->fornecedor_cnpj ?? '';
            $mov->valor_total     = (float) str_replace(',', '.', $data->valor_total ?? 0);
            $mov->status          = $data->status ?? 'confirmado';
            $mov->updated_at      = date('Y-m-d H:i:s');

            $xmlNfe = TSession::getValue('estoque_xml_nfe');
            if ($xmlNfe) $mov->xml_nfe = $xmlNfe;
            if (empty($mov->created_at)) $mov->created_at = date('Y-m-d H:i:s');

            $mov->store();

            // ── 4. Itens NF-e ─────────────────────────────────────────────
            $itensNfe = TSession::getValue('estoque_nfe_itens');
            if ($itensNfe && is_array($itensNfe)) {
                // Remove itens antigos se edição
                if (!empty($data->id)) {
                    $crit = new TCriteria();
                    $crit->add(new TFilter('estoque_movimento_id', '=', $mov->id));
                    $old = EstoqueMovimentoItem::getObjects($crit);
                    if ($old) foreach ($old as $oi) $oi->delete();
                }
                foreach ($itensNfe as $itemNfe) {
                    $it                       = new EstoqueMovimentoItem();
                    $it->estoque_movimento_id = $mov->id;
                    $it->numero_item          = $itemNfe->numero_item;
                    $it->codigo_produto       = $itemNfe->codigo;
                    $it->descricao            = $itemNfe->descricao;
                    $it->ncm                  = $itemNfe->ncm;
                    $it->cfop                 = $itemNfe->cfop;
                    $it->unidade              = $itemNfe->unidade;
                    $it->quantidade           = $itemNfe->quantidade;
                    $it->valor_unitario       = $itemNfe->valor_unit;
                    $it->valor_total          = $itemNfe->valor_total;
                    $it->store();
                }
                TSession::setValue('estoque_xml_nfe',   null);
                TSession::setValue('estoque_nfe_itens', null);
            }

            TTransaction::close();

            new TMessage('info',
                "Entrada registrada!<br>"
              . "ID: <strong>#{$mov->id}</strong>"
              . ($mov->danfe ? " | DANFE: <strong>{$mov->danfe}</strong>" : ''),
                new TAction(['EstoqueView', 'onReload'])
            );

        } catch (\Exception $e) {
            try { TTransaction::rollback(); } catch (\Exception $ignore) {}
            new TMessage('error', $e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // ACTION: CARREGAR EDIÇÃO
    // ════════════════════════════════════════════════════════════════════

    public function onLoad($param)
    {
        try {
            if (empty($param['key'])) return;
            TTransaction::open('sample');
            EstoqueManifesto::ensureTables();
            $mov = new EstoqueMovimento($param['key']);

            $data = (object)$mov->toArray();

            // Datas para display
            if (!empty($data->data_movimento)) {
                $data->data_movimento = TDate::convertToMask($data->data_movimento, 'yyyy-mm-dd', 'dd/mm/yyyy');
            }
            if (!empty($data->data_emissao)) {
                $data->data_emissao = TDate::convertToMask($data->data_emissao, 'yyyy-mm-dd', 'dd/mm/yyyy');
            }
            if (!empty($data->data_saida)) {
                $data->data_saida = TDate::convertToMask($data->data_saida, 'yyyy-mm-dd', 'dd/mm/yyyy');
            }

            // Compatibilidade: preenche novos campos com base no legado, se necessario
            if (!isset($data->peso_bruto_kg) || $data->peso_bruto_kg === null) {
                $data->peso_bruto_kg = $data->peso_kg ?? 0;
            }
            if (!isset($data->quantidade) || $data->quantidade === null) {
                $data->quantidade = $data->bobinas ?? 0;
            }
            if (empty($data->tipo_carga)) {
                $data->tipo_carga = '';
            }

            // Dados do manifesto
            $manifesto = $mov->get_manifesto();
            if ($manifesto) {
                $data->crt_codigo    = $manifesto->crt_codigo;
                $data->importador_id = $manifesto->importador_id;
                $data->exportador_id = $manifesto->exportador_id;
                $data->manifesto_id  = $manifesto->id;
                $data->conhecimento_id = '';
                if (!empty($manifesto->crt_codigo)) {
                    $conn = TTransaction::get();
                    $stmt = $conn->prepare("SELECT id FROM conhecimento WHERE numero = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([(string) $manifesto->crt_codigo]);
                    $conhId = (int) $stmt->fetchColumn();
                    if ($conhId > 0) {
                        $data->conhecimento_id = $conhId;
                    }
                }
                // DANFEs
                if (empty($data->danfe)) {
                    $data->danfe = $mov->get_danfes_lista();
                }
            }

            $this->form->setData($data);

            // Itens
            $items = EstoqueMovimentoItem::getObjects(
                (new TCriteria())->add(new TFilter('estoque_movimento_id', '=', $mov->id))
            );
            if ($items) {
                $this->dg_itens->clear();
                foreach ($items as $it) $this->dg_itens->addItem($it);
            }

            TTransaction::close();
        } catch (\Exception $e) {
            try { TTransaction::rollback(); } catch (\Exception $ignore) {}
            new TMessage('error', $e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // ACTION: LIMPAR / NOVO
    // ════════════════════════════════════════════════════════════════════

    public function onNovo($param)
    {
        $this->form->clear();
        $this->dg_itens->clear();
        TSession::setValue('estoque_xml_nfe',   null);
        TSession::setValue('estoque_nfe_itens', null);

        $data = new \stdClass();
        $data->tipo   = 'entrada';
        $data->status = 'confirmado';
        $data->data_movimento = date('d/m/Y');
        $this->form->setData($data);
    }
}





