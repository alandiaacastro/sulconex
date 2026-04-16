<?php
/**
 * PackingFormView.php
 *
 * Página principal para empacotamento 3D de Caixas e Bobinas usando componentes nativos do Adianti 8.1.
 */
class PackingFormView extends TPage
{
    protected $form;
    protected $form_table;
    protected $items_container_table;
    protected $results_panel;
    protected $three_container;
    protected $log_output_area;
    protected $stats_output_area;

    private $item_rows_data = [];

    public function __construct($param)
    {
        parent::__construct($param);

        ini_set('max_execution_time', 300);
        set_time_limit(300);
        ini_set('memory_limit', '256M');

        // --- Cria o formulário nativo ---
        $this->form = new TForm('form_packing_3d');
        $this->form_table = new TTable;
        $this->form_table->width = '100%';
        $this->form->add($this->form_table);

        // Cabeçalho
        $row = $this->form_table->addRow();
        $cell = $row->addCell(new TLabel('<b>Formulário de Empacotamento 3D</b>'));
        $cell->colspan = 2;

        // --- Dimensões do Contêiner ---
        $row = $this->form_table->addRow();
        $cell = $row->addCell(new TLabel('<br><b>Dimensões do Contêiner</b>'));
        $cell->colspan = 2;

        $cont_largura      = new TNumeric('cont_largura', 2, '.', ',', false, true);
        $cont_profundidade = new TNumeric('cont_profundidade', 2, '.', ',', false, true);
        $cont_altura       = new TNumeric('cont_altura', 2, '.', ',', false, true);

        $cont_largura->setValue('2.40');
        $cont_profundidade->setValue('13.50');
        $cont_altura->setValue('2.80');

        $cont_largura->addValidation('Largura do Contêiner', new TRequiredValidator);
        $cont_largura->addValidation('Largura do Contêiner', new TMinValueValidator, [0.001]);

        $cont_profundidade->addValidation('Comprimento do Contêiner', new TRequiredValidator);
        $cont_profundidade->addValidation('Comprimento do Contêiner', new TMinValueValidator, [0.001]);

        $cont_altura->addValidation('Altura do Contêiner', new TRequiredValidator);
        $cont_altura->addValidation('Altura do Contêiner', new TMinValueValidator, [0.001]);

        // Linha Largura
        $row = $this->form_table->addRow();
        $row->addCell(new TLabel('Largura (X) (m):'));
        $row->addCell($cont_largura);

        // Linha Comprimento
        $row = $this->form_table->addRow();
        $row->addCell(new TLabel('Comprimento (Y) (m):'));
        $row->addCell($cont_profundidade);

        // Linha Altura
        $row = $this->form_table->addRow();
        $row->addCell(new TLabel('Altura (Z) (m):'));
        $row->addCell($cont_altura);

        // --- Importar Itens de CSV ---
        $row = $this->form_table->addRow();
        $cell = $row->addCell(new TLabel('<br><b>Importar Itens de CSV</b>'));
        $cell->colspan = 2;

        $csv_file_items = new TFile('csv_file_items');
        $csv_file_items->setTip('Formato CSV (cabeçalho opcional): Tipo,Quantidade,Largura,Altura,Profundidade,PesoBruto');
        $row = $this->form_table->addRow();
        $row->addCell(new TLabel('Arquivo CSV:'));
        $row->addCell($csv_file_items);

        $btn_import_csv = TButton::create('btn_import_csv', [$this, 'onImportCSV'], 'Importar CSV', 'fa:upload');
        $btn_import_csv->class = 'btn btn-primary btn-sm';
        $row = $this->form_table->addRow();
        $cell = $row->addCell('');
        $cell->add($btn_import_csv);

        // --- Itens para Empacotar ---
        $row = $this->form_table->addRow();
        $cell = $row->addCell(new TLabel('<br><b>Itens para Empacotar (Caixas/Bobinas)</b>'));
        $cell->colspan = 2;

        $this->items_container_table = new TTable;
        $this->items_container_table->width = '100%';
        $row = $this->form_table->addRow();
        $cell = $row->addCell($this->items_container_table);
        $cell->colspan = 2;

        // Botão "Adicionar Item Manualmente"
        $btn_add_item = TButton::create('btn_add_item', [$this, 'onAddItemRow'], 'Adicionar Item Manualmente', 'fa:plus');
        $btn_add_item->class = 'btn btn-success btn-sm';
        $row = $this->form_table->addRow();
        $cell = $row->addCell('');
        $cell->add($btn_add_item);

        // --- Opções de Empacotamento ---
        $row = $this->form_table->addRow();
        $cell = $row->addCell(new TLabel('<br><b>Opções de Empacotamento</b>'));
        $cell->colspan = 2;

        $carregamento_ordenado = new TCheckButton('carregamento_ordenado');
        $carregamento_ordenado->setLabel('Seguir ordem da lista (não ordenar por volume)');
        $carregamento_ordenado->setIndexValue('1');

        $nao_empilhar = new TCheckButton('nao_empilhar');
        $nao_empilhar->setLabel('Não empilhar itens (somente no piso)');
        $nao_empilhar->setIndexValue('1');

        $permitir_rotacao = new TCheckButton('permitir_rotacao');
        $permitir_rotacao->setLabel('Permitir rotação de itens (caixas)');
        $permitir_rotacao->setIndexValue('1');
        $permitir_rotacao->setValue('1');

        // Linha Carregamento Ordenado
        $row = $this->form_table->addRow();
        $cell = $row->addCell('');
        $cell->add($carregamento_ordenado);
        $cell->colspan = 2;

        // Linha Não Empilhar
        $row = $this->form_table->addRow();
        $cell = $row->addCell('');
        $cell->add($nao_empilhar);
        $cell->colspan = 2;

        // Linha Permitir Rotação
        $row = $this->form_table->addRow();
        $cell = $row->addCell('');
        $cell->add($permitir_rotacao);
        $cell->colspan = 2;

        // --- Botão "Gerar Visualização 3D e Resultados" ---
        $btn_generate = TButton::create('btn_generate', [$this, 'onGenerate'], 'Gerar Visualização 3D e Resultados', 'fa:cubes');
        $btn_generate->class = 'btn btn-info btn-sm';
        $btn_generate->addFunction("Adianti.blockUI('Calculando Empacotamento... Aguarde.')");
        $row = $this->form_table->addRow();
        $cell = $row->addCell('');
        $cell->add($btn_generate);

        // Registra campos no formulário
        $this->form->setFields([
            $cont_largura,
            $cont_profundidade,
            $cont_altura,
            $csv_file_items,
            $btn_import_csv,
            $btn_add_item,
            $carregamento_ordenado,
            $nao_empilhar,
            $permitir_rotacao,
            $btn_generate
        ]);

        // Adiciona o formulário à página
        parent::add($this->form);

        // --- Painel de Resultados (oculto inicialmente) ---
        $this->results_panel = new TPanelGroup('Resultados do Empacotamento');
        $this->results_panel->style = 'margin-top: 20px;';
        $this->results_panel->hide();

        $this->stats_output_area = new TElement('div');
        $this->stats_output_area->id = 'stats_output_area_div';
        $this->results_panel->add($this->stats_output_area);

        $this->three_container = new TElement('div');
        $this->three_container->id = 'threeContainer';
        $this->three_container->style = "width:100%; height:70vh; border:1px solid #ccc; background-color:#ecf0f1; margin-top:15px; border-radius:5px;";
        $this->results_panel->add($this->three_container);

        $this->log_output_area = new TElement('div');
        $this->log_output_area->id = 'log_output_area_div';
        $this->results_panel->add($this->log_output_area);

        parent::add($this->results_panel);

        // Importa CSS e JS necessários
        TStyle::importFromFile('app/resources/packing_view.css');
        TScript::importFromFile('https://cdn.jsdelivr.net/npm/three@0.135.0/build/three.min.js', false, false, true);
        TScript::importFromFile('https://cdn.jsdelivr.net/npm/three@0.135.0/examples/js/controls/OrbitControls.js', false, false, true);

        // Se não houver itens em sessão, cria uma linha vazia por JavaScript
        if (empty($this->item_rows_data) && empty($param['preserve_items'])) {
            TScript::create("
                $(document).ready(function() {
                    if ($('#itens_table_body_container').find('tr').length === 0) {
                        addItemRowToTable();
                        }
                    }
                });
            ");
        }

        $this->injectItemManipulationScript();
    }

    /**
     * Chama onShow apenas para preservar itens
     */
    public static function onAddItemRow($param)
    {
        AdiantiCoreApplication::loadPage(__CLASS__, 'onShow', ['preserve_items' => 1]);
    }

    /**
     * Injeta JavaScript para manipular dinamicamente as linhas de itens.
     */
    private function injectItemManipulationScript()
    {
        $script = <<<'JS'
        function addItemRowToTable(data = null) {
            var table = $('#itens_table_body_container');
            if (!table.length) return;

            var rowCount = table.find('tr').length;
            var rowId = 'item_row_' + rowCount;

            var tipoVal    = data ? data.tipo : 'caixa';
            var qtdVal     = data ? data.quantidade : '1';
            var largVal    = data ? data.largura : '1.00';
            var profVal    = data ? data.profundidade : '1.00';
            var altVal     = data ? data.altura : '1.00';
            var pesoVal    = data ? data.peso_bruto : '1.0';

            var html  = '<tr id="'+rowId+'">';
            html +=   '<td><select name="tipo[]" class="form-control">';
            html +=     '<option value="caixa" '+ (tipoVal === 'caixa' ? 'selected' : '') +'>Caixa</option>';
            html +=     '<option value="bobina" '+ (tipoVal === 'bobina' ? 'selected' : '') +'>Bobina</option>';
            html +=   '</select></td>';
            html +=   '<td><input type="number" name="quantidade[]" class="form-control" value="'+qtdVal+'" min="1" required></td>';
            html +=   '<td><input type="text" name="largura[]" class="form-control numeric_mask_item" value="'+largVal+'" required></td>';
            html +=   '<td><input type="text" name="profundidade[]" class="form-control numeric_mask_item" value="'+profVal+'" required></td>';
            html +=   '<td><input type="text" name="altura[]" class="form-control numeric_mask_item" value="'+altVal+'" required></td>';
            html +=   '<td><input type="text" name="peso_bruto[]" class="form-control numeric_mask_item_allow_zero" value="'+pesoVal+'" required></td>';
            html +=   '<td><button type="button" class="btn btn-danger btn-sm remove_item_btn">✖</button></td>';
            html += '</tr>';

            table.append(html);

            // Máscaras numéricas
            $('#' + rowId).find('.numeric_mask_item').each(function(){
                $(this).keypress(function(e){
                    return Adianti.NumericMask(this, 3, ',', '.', e, 0.001);
                }).keyup(function(e){
                    Adianti.NumericMask(this, 3, ',', '.', e, 0.001);
                });
            });
            $('#' + rowId).find('.numeric_mask_item_allow_zero').each(function(){
                $(this).keypress(function(e){
                    return Adianti.NumericMask(this, 3, ',', '.', e, 0);
                }).keyup(function(e){
                    Adianti.NumericMask(this, 3, ',', '.', e, 0);
                });
            });

            // Se tipo "bobina", fixa profundidade = largura
            $('#' + rowId + ' select[name="tipo[]"]').change(function(){
                var tipo = $(this).val();
                var linha = $(this).closest('tr');
                var larguraInput = linha.find('input[name="largura[]"]');
                var profundidadeInput = linha.find('input[name="profundidade[]"]');
                if (tipo === 'bobina') {
                    profundidadeInput.val(larguraInput.val()).prop('readonly', true);
                } else {
                    profundidadeInput.prop('readonly', false);
                }
            }).trigger('change');

            // Atualiza profundidade ao digitar largura se bobina
            $('#' + rowId).find('input[name="largura[]"]').on('input', function(){
                var linha = $(this).closest('tr');
                var tipo = linha.find('select[name="tipo[]"]').val();
                if (tipo === 'bobina') {
                    linha.find('input[name="profundidade[]"]').val($(this).val());
                }
            });

            // Remove linha
            $('#' + rowId).find('.remove_item_btn').click(function(){
                var table = $('#itens_table_body_container');
                if (table.find('tr').length > 1) {
                    $(this).closest('tr').remove();
                } else {
                    var linha = $(this).closest('tr');
                    linha.find('select[name="tipo[]"]').val('caixa');
                    linha.find('input[name="quantidade[]"]').val('1');
                    linha.find('input[name="largura[]"]').val('1.00');
                    linha.find('input[name="profundidade[]"]').val('1.00');
                    linha.find('input[name="altura[]"]').val('1.00');
                    linha.find('input[name="peso_bruto[]"]').val('1.0');
                    linha.find('select[name="tipo[]"]').trigger('change');
                }
            });
        }

        // Define a função global se não existir
        if (typeof addItemRowToTable === 'undefined') {
            window.addItemRowToTable = addItemRowToTable;
        }

        // Botão "Adicionar Item Manualmente"
        $('button[name="btn_add_item"]').click(function(){
            addItemRowToTable();
        });
JS;
        TScript::create($script);
    }

    /**
     * Importa CSV e adiciona linhas de item na tabela.
     */
    public function onImportCSV($param)
    {
        AdiantiCoreApplication::loadPage(__CLASS__, 'onShow', ['preserve_items' => 1] + $param);

        try {
            TTransaction::openFake();
            $data = $this->form->getData();

            if (empty($data->csv_file_items)) {
                throw new Exception("Nenhum arquivo CSV enviado ou erro no upload.");
            }

            $uploaded_filename = $data->csv_file_items;
            $extension = strtolower(pathinfo($uploaded_filename, PATHINFO_EXTENSION));
            if ($extension !== 'csv') {
                throw new Exception("Formato de arquivo inválido. Apenas arquivos .csv são permitidos. Extensão encontrada: .{$extension}");
            }

            $path = 'tmp/' . $data->csv_file_items;
            if (!file_exists($path)) {
                throw new Exception("Arquivo CSV não encontrado no servidor: {$path}");
            }

            $logMessages = [];
            $errorMessages = [];
            $imported_items = [];

            $logMessages[] = "[CSV] INFO: Tentando importar: " . htmlspecialchars($data->csv_file_items);

            $rowCount = 0;
            $headerSkipped = false;
            $importedCount = 0;
            $skippedCount = 0;

            if (($handle = fopen($path, "r")) !== FALSE) {
                while (($csv_row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $rowCount++;
                    if (count($csv_row) < 1 || (count($csv_row) == 1 && trim($csv_row[0]) == '')) {
                        $skippedCount++;
                        continue;
                    }

                    if (!$headerSkipped && $rowCount == 1) {
                        $first = strtolower(trim($csv_row[0] ?? ''));
                        if (
                            in_array($first, ['tipo','tipos','item','desc','caixa','bobina']) ||
                            (isset($csv_row[1]) && !is_numeric(trim($csv_row[1])))
                        ) {
                            $logMessages[] = "[CSV] INFO: Cabeçalho detectado e ignorado: " . htmlspecialchars(implode(",", $csv_row));
                            $headerSkipped = true;
                            continue;
                        }
                    }

                    $isBobina = isset($csv_row[0]) && strtolower(trim($csv_row[0])) === 'bobina';
                    $minCols  = $isBobina ? 5 : 6;

                    if (count($csv_row) < $minCols) {
                        $logMessages[] = "[CSV] WARNING: L#{$rowCount} não tem colunas suficientes (" . count($csv_row) . " de {$minCols}), pulando.";
                        $skippedCount++;
                        continue;
                    }

                    $tipoCsv = strtolower(trim($csv_row[0] ?? ''));
                    $qCsvStr = trim($csv_row[1] ?? '');
                    $lCsvStr = str_replace(',', '.', trim($csv_row[2] ?? ''));
                    $hCsvStr = str_replace(',', '.', trim($csv_row[3] ?? ''));

                    if ($isBobina) {
                        $pCsvStr    = $lCsvStr;
                        $pesoCsvStr = str_replace(',', '.', trim($csv_row[4] ?? ''));
                    } else {
                        $pCsvStr    = str_replace(',', '.', trim($csv_row[4] ?? ''));
                        $pesoCsvStr = str_replace(',', '.', trim($csv_row[5] ?? ''));
                    }

                    $qCsv  = filter_var($qCsvStr, FILTER_VALIDATE_INT);
                    $lCsv  = filter_var($lCsvStr, FILTER_VALIDATE_FLOAT);
                    $hCsv  = filter_var($hCsvStr, FILTER_VALIDATE_FLOAT);
                    $pCsv  = filter_var($pCsvStr, FILTER_VALIDATE_FLOAT);
                    $pesoCsv = filter_var($pesoCsvStr, FILTER_VALIDATE_FLOAT);

                    $valid = true;
                    $errors = [];

                    if (!in_array($tipoCsv, ['caixa','bobina'])) {
                        $errors[] = "Tipo '{$csv_row[0]}' inválido.";
                    }
                    if ($qCsv === false || $qCsv <= 0) {
                        $errors[] = "Qtd '{$qCsvStr}' inválida.";
                    }
                    if ($lCsv === false || $lCsv <= 0) {
                        $errors[] = "Larg '{$lCsvStr}' inválida.";
                    }
                    if ($hCsv === false || $hCsv <= 0) {
                        $errors[] = "Alt '{$hCsvStr}' inválida.";
                    }
                    if (!$isBobina && ($pCsv === false || $pCsv <= 0)) {
                        $errors[] = "Prof '{$pCsvStr}' inválida.";
                    }
                    if ($pesoCsv === false || $pesoCsv < 0) {
                        $errors[] = "Peso '{$pesoCsvStr}' inválido.";
                    }

                    if (!empty($errors)) {
                        $valid = false;
                        $logMessages[] = "[CSV] WARNING: L#{$rowCount} ignorada: " . implode("; ", $errors);
                    }

                    if ($valid) {
                        $imported_items[] = [
                            'tipo'         => $tipoCsv,
                            'quantidade'   => $qCsv,
                            'largura'      => number_format($lCsv, 3, ',', ''),
                            'profundidade' => number_format($pCsv, 3, ',', ''),
                            'altura'       => number_format($hCsv, 3, ',', ''),
                            'peso_bruto'   => number_format($pesoCsv, 3, ',', '')
                        ];
                        $importedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
                fclose($handle);
            } else {
                $errorMessages[] = "Erro ao abrir arquivo CSV. Verifique permissões.";
            }

            $csv_display = "";
            foreach ($logMessages as $log) {
                $csv_display .= htmlspecialchars($log) . "<br>";
            }
            if (!empty($errorMessages)) {
                $csv_display .= "<b style='color:red;'>ERROS:</b><br>";
                foreach ($errorMessages as $err) {
                    $csv_display .= "<b style='color:red;'>" . htmlspecialchars($err) . "</b><br>";
                }
            }

            if ($importedCount > 0) {
                new TMessage('info', "{$importedCount} itens importados.<br>{$csv_display}", null, 'Resultado CSV');
                // Limpa tabela e insere linhas via JS
                TScript::create("
                    $('#itens_table_body_container').empty();
                ");
                foreach ($imported_items as $item) {
                    TScript::create("
                        addItemRowToTable(".json_encode($item).");
                    ");
                }
                $this->item_rows_data = $imported_items;
                TSession::setValue(__CLASS__.'_item_rows_data', $this->item_rows_data);
            }
            elseif (empty($errorMessages)) {
                new TMessage('warning', "Nenhum item válido no CSV.<br>{$csv_display}", null, 'Aviso CSV');
            }
            else {
                new TMessage('error', "Falha na importação CSV:<br>{$csv_display}", null, 'Erro CSV');
            }

            if ($skippedCount > 0) {
                new TMessage('warning', "{$skippedCount} linhas ignoradas.", null, 'Aviso CSV');
            }

            TTransaction::closeFake();
        }
        catch (Exception $e) {
            TTransaction::closeFake();
            new TMessage('error', $e->getMessage());
        }

        TScript::create('Adianti.unblockUI();');
    }

    /**
     * Gera o empacotamento e exibe resultados + visualização 3D.
     */
    public function onGenerate($param)
    {
        AdiantiCoreApplication::loadPage(__CLASS__, 'onShow', ['preserve_items' => 1] + $param);

        try {
            TTransaction::openFake();
            $data = $this->form->getData();

            // Captura e valida contêiner
            $cont_larg_s = (float) str_replace(',', '.', $data->cont_largura ?? 0);
            $cont_prof_s = (float) str_replace(',', '.', $data->cont_profundidade ?? 0);
            $cont_alt_s  = (float) str_replace(',', '.', $data->cont_altura ?? 0);

            $errors = [];
            if ($cont_larg_s <= 0) { $errors[] = "Largura do contêiner inválida."; }
            if ($cont_prof_s <= 0) { $errors[] = "Comprimento do contêiner inválido."; }
            if ($cont_alt_s  <= 0) { $errors[] = "Altura do contêiner inválida."; }

            // Captura itens do HTML via POST
            $inputItems = filter_input_array(INPUT_POST, [
                'tipo'         => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
                'quantidade'   => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
                'largura'      => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
                'profundidade' => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
                'altura'       => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
                'peso_bruto'   => ['filter' => FILTER_DEFAULT, 'flags' => FILTER_REQUIRE_ARRAY],
            ]) ?: [];

            $tipos         = $inputItems['tipo']         ?? [];
            $quantidades   = $inputItems['quantidade']   ?? [];
            $larguras      = $inputItems['largura']      ?? [];
            $profundidades = $inputItems['profundidade'] ?? [];
            $alturas       = $inputItems['altura']       ?? [];
            $pesos_brutos  = $inputItems['peso_bruto']   ?? [];

            $numItems = count($tipos);
            if ($numItems === 0) {
                $errors[] = "Nenhum item para empacotar.";
            }

            $total_volumes = 0;
            // Valida cada item
            for ($i = 0; $i < $numItems; $i++) {
                $idx = $i + 1;
                $t  = strtolower(trim($tipos[$i] ?? ''));
                $q  = filter_var($quantidades[$i] ?? 0, FILTER_VALIDATE_INT);
                $l  = filter_var(str_replace(',', '.', $larguras[$i] ?? '0'), FILTER_VALIDATE_FLOAT);
                $p  = filter_var(str_replace(',', '.', $profundidades[$i] ?? '0'), FILTER_VALIDATE_FLOAT);
                $h  = filter_var(str_replace(',', '.', $alturas[$i] ?? '0'), FILTER_VALIDATE_FLOAT);
                $pb = filter_var(str_replace(',', '.', $pesos_brutos[$i] ?? '0'), FILTER_VALIDATE_FLOAT);

                $total_volumes += ($q > 0 ? $q : 0);

                if (!in_array($t, ['caixa','bobina'])) {
                    $errors[] = "Item #{$idx}: tipo '{$tipos[$i]}' inválido.";
                }
                if ($q === false || $q < 1) {
                    $errors[] = "Item #{$idx}: quantidade '{$quantidades[$i]}' inválida.";
                }
                if ($l === false || $l <= 0) {
                    $errors[] = "Item #{$idx}: largura '{$larguras[$i]}' inválida.";
                }
                if ($h === false || $h <= 0) {
                    $errors[] = "Item #{$idx}: altura '{$alturas[$i]}' inválida.";
                }
                if ($t === 'caixa' && ($p === false || $p <= 0)) {
                    $errors[] = "Item #{$idx}: profundidade '{$profundidades[$i]}' inválida.";
                }
                if ($pb === false || $pb < 0) {
                    $errors[] = "Item #{$idx}: peso '{$pesos_brutos[$i]}' inválido.";
                }
            }

            if (!empty($errors)) {
                $errHtml = '';
                foreach ($errors as $e) {
                    $errHtml .= "<p>" . htmlspecialchars($e) . "</p>";
                }
                new TMessage('error', $errHtml);
                TScript::create('Adianti.unblockUI();');
                TTransaction::closeFake();
                return;
            }

            // Instancia Empacotamento3D
            $packing = new Empacotamento3D($cont_larg_s, $cont_prof_s, $cont_alt_s);

            $packing->setSortByVolumeDesc(empty($data->carregamento_ordenado));
            $packing->setAllowStacking(empty($data->nao_empilhar));
            $packing->setAllowRotation(!empty($data->permitir_rotacao));

            // Adiciona itens
            for ($i = 0; $i < $numItems; $i++) {
                $t  = strtolower(trim($tipos[$i]));
                $q  = intval($quantidades[$i]);
                $l  = (float) str_replace(',', '.', $larguras[$i]);
                $p  = (float) str_replace(',', '.', $profundidades[$i]);
                $h  = (float) str_replace(',', '.', $alturas[$i]);
                $pb = (float) str_replace(',', '.', $pesos_brutos[$i]);

                if ($t === 'bobina') {
                    $p = $l;
                }
                $packing->addItem($t, $q, $l, $p, $h, $pb);
            }

            // Executa
            $packing->generatePacking();

            $placedItems = $packing->getPlacedItems();
            $logList     = $packing->getLogFormatted();

            $count_embarcados    = count($placedItems);
            $volume_embarcados   = $packing->getPlacedItemsVolume();
            $peso_embarcados     = $packing->getPlacedItemsWeight();
            $faltantes           = $total_volumes - $count_embarcados;
            $vol_container_calc  = $cont_larg_s * $cont_prof_s * $cont_alt_s;

            // Gera estatísticas HTML
            $stats  = "<div style='margin-bottom:10px;'>";
            $stats .= "<p><b>Total de volumes para empacotar:</b> {$total_volumes}</p>";
            $stats .= "<p><b>Total de volumes embarcados:</b> {$count_embarcados}</p>";
            $stats .= "<p><b>Total de volumes faltantes:</b> {$faltantes}</p>";
            $stats .= "<p><b>Volume do contêiner:</b> " . number_format($vol_container_calc, 3, ',', '.') . " m³</p>";
            $stats .= "<p><b>Peso bruto embarcado:</b> " . number_format($peso_embarcados, 2, ',', '.') . " kg</p>";
            $stats .= "<p><b>Volume dos itens embarcados:</b> " . number_format($volume_embarcados, 3, ',', '.') . " m³</p>";
            if ($vol_container_calc > 0) {
                $util = ($volume_embarcados / $vol_container_calc) * 100;
                $stats .= "<p><b>Utilização de volume:</b> " . number_format($util, 2, ',', '.') . "%</p>";
            }
            $stats .= "</div>";

            // Monta HTML dos logs
            $logHtml = "<div><h4>Log do Empacotamento:</h4>";
            foreach ($logList as $entry) {
                $cls = 'info';
                if (strpos($entry, 'WARNING') !== false) { $cls = 'warning'; }
                if (strpos($entry, 'ERROR')   !== false) { $cls = 'danger'; }
                $logHtml .= "<p class='text-{$cls}'>" . htmlspecialchars($entry) . "</p>";
            }
            $logHtml .= "</div>";

            // Exibe painel de resultados
            $this->results_panel->show();
            TScript::create("$('#stats_output_area_div').html(`{$stats}`);");
            TScript::create("$('#log_output_area_div').html(`{$logHtml}`);");

            // Prepara dados para Three.js
            $placedJson    = json_encode($placedItems, JSON_NUMERIC_CHECK);
            $containerJson = json_encode([
                'largura'      => $cont_larg_s,
                'profundidade' => $cont_prof_s,
                'altura'       => $cont_alt_s
            ], JSON_NUMERIC_CHECK);

            $threeJsCode = <<<'JSCODE'
            function initializeThreeJSPackingView(placedData, containerData) {
                const scene = new THREE.Scene();
                scene.background = new THREE.Color(0xecf0f1);

                const containerDiv = document.getElementById('threeContainer');
                containerDiv.innerHTML = '';

                const width  = containerDiv.clientWidth;
                const height = containerDiv.clientHeight;

                const camera = new THREE.PerspectiveCamera(50, width / height, 0.1, 2000);
                const maxDim = Math.max(containerData.largura, containerData.altura, containerData.profundidade);
                camera.position.set(
                    containerData.largura / 2 + maxDim * 0.8,
                    containerData.altura / 2  + maxDim * 0.8,
                    containerData.profundidade / 2 + maxDim * 1.2
                );
                camera.lookAt(new THREE.Vector3(containerData.largura / 2, containerData.altura / 2, containerData.profundidade / 2));

                const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
                renderer.setSize(width, height);
                renderer.setPixelRatio(window.devicePixelRatio);
                containerDiv.appendChild(renderer.domElement);

                const controls = new THREE.OrbitControls(camera, renderer.domElement);
                controls.target.set(containerData.largura / 2, containerData.altura / 2, containerData.profundidade / 2);
                controls.enableDamping = true;
                controls.dampingFactor = 0.1;
                controls.update();

                // Luz ambiente e direcional
                scene.add(new THREE.AmbientLight(0xffffff, 0.9));
                const dl1 = new THREE.DirectionalLight(0xffffff, 0.6);
                dl1.position.set(containerData.largura, containerData.altura * 2, containerData.profundidade * 1.5).normalize();
                scene.add(dl1);
                const dl2 = new THREE.DirectionalLight(0xffffff, 0.3);
                dl2.position.set(-containerData.largura, -containerData.altura * 0.5, -containerData.profundidade * 0.5).normalize();
                scene.add(dl2);

                // Wireframe do contêiner
                const boxGeo = new THREE.BoxGeometry(containerData.largura, containerData.altura, containerData.profundidade);
                const edges = new THREE.EdgesGeometry(boxGeo);
                const lineMat = new THREE.LineBasicMaterial({ color: 0x555555, linewidth: 1.5 });
                const wire = new THREE.LineSegments(edges, lineMat);
                wire.position.set(containerData.largura / 2, containerData.altura / 2, containerData.profundidade / 2);
                scene.add(wire);

                // Cores para itens
                const palette = [
                  0xE6194B,0x3cb44b,0xffe119,0x4363d8,0xf58231,
                  0x911eb4,0x42d4f4,0xf032e6,0xbfef45,0xfabed4,
                  0x469990,0xdcbeff,0x9A6324,0xffd8b1,0x800000,
                  0xaaffc3,0x808000,0x000075,0x008080,0xFF5733,
                  0x581845,0x0C70F2
                ];

                // Adiciona cada item
                placedData.forEach((pl) => {
                    const w = pl.w, d = pl.d, h = pl.h;
                    const posX = pl.x + w / 2;
                    const posY = pl.z + h / 2;
                    const posZ = pl.y + d / 2;
                    const color = palette[pl.id_original_instance % palette.length];
                    let geom;
                    if (pl.tipo === 'caixa') {
                        geom = new THREE.BoxGeometry(w, h, d);
                    } else {
                        const radius = w / 2;
                        geom = new THREE.CylinderGeometry(radius, radius, h, 32);
                    }
                    const mat = new THREE.MeshLambertMaterial({ color: color, opacity: 0.9, transparent: true });
                    const mesh = new THREE.Mesh(geom, mat);
                    mesh.position.set(posX, posY, posZ);
                    scene.add(mesh);

                    const edgeGeo = new THREE.EdgesGeometry(geom);
                    const edgeMat = new THREE.LineBasicMaterial({ color: 0x111111, linewidth: 1, transparent: true, opacity: 0.4 });
                    const wireItem = new THREE.LineSegments(edgeGeo, edgeMat);
                    wireItem.position.copy(mesh.position);
                    wireItem.rotation.copy(mesh.rotation);
                    scene.add(wireItem);
                });

                function animate() {
                    requestAnimationFrame(animate);
                    controls.update();
                    renderer.render(scene, camera);
                }
                animate();

                window.addEventListener('resize', () => {
                    const w = containerDiv.clientWidth;
                    const h = containerDiv.clientHeight;
                    camera.aspect = w / h;
                    camera.updateProjectionMatrix();
                    renderer.setSize(w, h);
                });
            }

JSCODE;

            TScript::create($threeJsCode);
            TScript::create("setTimeout(function() { initializeThreeJSPackingView({$placedJson}, {$containerJson}); }, 100);");

            TTransaction::closeFake();
        }
        catch (Exception $e) {
            TTransaction::closeFake();
            new TMessage('error', $e->getMessage());
        }

        TScript::create('Adianti.unblockUI();');
    }

    /**
     * Reconstrói as linhas de itens a partir da sessão, se existir.
     */
    public function onShow($param = null)
    {
        if (isset($param['preserve_items']) && $param['preserve_items']) {
            $this->item_rows_data = TSession::getValue(__CLASS__.'_item_rows_data');
            if (!empty($this->item_rows_data)) {
                TScript::create("
                    $('#itens_table_body_container').empty();
                ");
                foreach ($this->item_rows_data as $item) {
                    TScript::create("
                        addItemRowToTable(".json_encode($item).");
                    ");
                }
            }
        }
    }
 
}
