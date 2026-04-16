<?php
// ... (use statements e início da classe PackingController como antes) ...

class PackingController extends TPage
{
    // ... (propriedades e __construct como antes) ...

    public function onGenerate3D($param)
    {
        try
        {
            // Inicia o bloco de UI para loading, se desejar (Adianti fará isso automaticamente para AJAX)
            // TScript::create('__adianti_block_ui("Calculando...");'); // Exemplo

            $data = $this->form->getData(); // Obtém todos os dados do formulário Adianti

            // 1. Validar dimensões do contêiner
            if (empty($data->cont_largura) || !is_numeric($data->cont_largura) || (float)$data->cont_largura <= 0) {
                throw new Exception("Largura do contêiner inválida.");
            }
            if (empty($data->cont_profundidade) || !is_numeric($data->cont_profundidade) || (float)$data->cont_profundidade <= 0) {
                throw new Exception("Comprimento do contêiner inválido.");
            }
            if (empty($data->cont_altura) || !is_numeric($data->cont_altura) || (float)$data->cont_altura <= 0) {
                throw new Exception("Altura do contêiner inválida.");
            }
            
            $containerDims = [
                'largura' => (float) str_replace(',', '.', $data->cont_largura), // Garante formato float
                'profundidade' => (float) str_replace(',', '.', $data->cont_profundidade),
                'altura' => (float) str_replace(',', '.', $data->cont_altura)
            ];

            // 2. Coletar e validar itens do TFieldList
            $items_to_process = [];
            $total_volumes_input_calculado = 0;

            if (!empty($data->lista_de_itens_packing)) { // 'lista_de_itens_packing' é o nome do TFieldList
                foreach ($data->lista_de_itens_packing as $index => $item_row) {
                    // Validar cada item
                    if (empty($item_row->tipo_item)) continue; // Pula se o tipo não estiver definido

                    $quantidade = isset($item_row->quantidade_item) ? (int)$item_row->quantidade_item : 0;
                    $largura = isset($item_row->largura_item) ? (float)str_replace(',', '.', $item_row->largura_item) : 0;
                    $altura = isset($item_row->altura_item) ? (float)str_replace(',', '.', $item_row->altura_item) : 0;
                    $peso_bruto = isset($item_row->peso_bruto_item) ? (float)str_replace(',', '.', $item_row->peso_bruto_item) : 0;
                    
                    $profundidade = 0;
                    if (strtolower($item_row->tipo_item) === 'bobina') {
                        $profundidade = $largura; // Profundidade da bobina é igual à largura
                    } else {
                        $profundidade = isset($item_row->profundidade_item) ? (float)str_replace(',', '.', $item_row->profundidade_item) : 0;
                    }

                    if ($quantidade <= 0 || $largura <= 0 || $altura <= 0 || $profundidade <= 0 || $peso_bruto < 0) {
                        // new TMessage('warning', "Item #".($index+1)." na lista com dados inválidos e foi ignorado.");
                        continue; // Pula itens com dados inválidos
                    }
                    $items_to_process[] = [
                        'tipo' => $item_row->tipo_item,
                        'quantidade' => $quantidade,
                        'largura' => $largura,
                        'profundidade' => $profundidade,
                        'altura' => $altura,
                        'peso_bruto' => $peso_bruto
                    ];
                    $total_volumes_input_calculado += $quantidade;
                }
            }
            
            $placedItems = [];
            $logMessages = [];
            $volume_itens_total_calculado = 0;
            $peso_total_itens_embarcados_calculado = 0;
            $total_embarcados_calculado = 0;

            if (empty($items_to_process)) {
                 new TMessage('info', 'Nenhum item válido fornecido para empacotamento. Visualizando contêiner vazio.');
            } else {
                // 3. Instanciar e configurar sua classe Empacotamento3D
                // Certifique-se que a classe Empacotamento3D está em app/lib/ ou app/service/
                // e que o 'require_once' no topo do PackingController está correto se não usar namespace.
                $packing = new Empacotamento3D($containerDims['largura'], $containerDims['profundidade'], $containerDims['altura']);
                
                $opcoes = $data->opcoes_empacotamento ?? [];
                $packing->setSortByVolumeDesc(!(isset($opcoes) && in_array('carregamento_ordenado', $opcoes)));
                $packing->setAllowStacking(!(isset($opcoes) && in_array('nao_empilhar', $opcoes)));
                $packing->setAllowRotation(isset($opcoes) && in_array('permitir_rotacao', $opcoes));

                // 4. Adicionar itens ao empacotador
                foreach ($items_to_process as $item_spec) {
                    // A sua classe Empacotamento3D pode lidar com a quantidade internamente
                    // ou você pode precisar adicionar um item por vez em um loop aqui.
                    // Ajuste conforme a lógica da sua classe Empacotamento3D.
                     $packing->addItem(
                         $item_spec['tipo'],
                         $item_spec['quantidade'], // Se sua classe addItem espera a quantidade total
                         $item_spec['largura'],
                         $item_spec['profundidade'],
                         $item_spec['altura'],
                         $item_spec['peso_bruto']
                     );
                }
                
                // 5. Gerar o empacotamento e obter resultados
                $packing->generatePacking();
                $placedItems = $packing->getPlacedItems();
                $logMessages = $packing->getLogFormatted(); // Obter logs formatados
                $volume_itens_total_calculado = $packing->getPlacedItemsVolume();
                $peso_total_itens_embarcados_calculado = $packing->getPlacedItemsWeight();
                $total_embarcados_calculado = count($placedItems); // Ou um método da sua classe $packing->getTotalItemsPlaced();

                new TMessage('info', 'Empacotamento gerado! Verifique a visualização e os resultados.');
            }

            // 6. Exibir resultados textuais
            $total_faltantes_calculado = $total_volumes_input_calculado - $total_embarcados_calculado;
            $volume_container_calculado = $containerDims['largura'] * $containerDims['profundidade'] * $containerDims['altura'];

            $html_results = TElement::tag('h4', 'Resultados do Empacotamento:', ['style'=>'margin-top:10px;']);
            $html_results .= TElement::tag('p', "<strong>Total de volumes para empacotar:</strong> " . $total_volumes_input_calculado);
            $html_results .= TElement::tag('p', "<strong>Total de volumes embarcados:</strong> " . $total_embarcados_calculado);
            $html_results .= TElement::tag('p', "<strong>Total de volumes faltantes:</strong> " . $total_faltantes_calculado);
            $html_results .= TElement::tag('p', "<strong>Metragem cúbica do contêiner:</strong> " . number_format($volume_container_calculado, 3, ',', '.') . " m³");
            $html_results .= TElement::tag('p', "<strong>Peso Bruto Total Embarcado:</strong> " . number_format($peso_total_itens_embarcados_calculado, 2, ',', '.') . " kg");
            $html_results .= TElement::tag('p', "<strong>Metragem cúbica dos volumes embarcados:</strong> " . number_format($volume_itens_total_calculado, 3, ',', '.') . " m³");
            
            if ($volume_container_calculado > 1e-6 && $volume_itens_total_calculado >= 0){
                $utilizacao_vol = ($volume_itens_total_calculado / $volume_container_calculado) * 100;
                $html_results .= TElement::tag('p', "<strong>Utilização de volume:</strong> " . number_format($utilizacao_vol, 2, ',', '.') . "%");
            }
            
            if (!empty($logMessages)) {
               $html_results .= TElement::tag('h4', 'Log do Empacotamento:', ['style'=>'margin-top:15px;']);
               $log_content = '';
               foreach($logMessages as $log) { $log_content .= TElement::tag('div', htmlspecialchars($log), ['class'=>'log-entry-packing']); } // Adicione CSS para .log-entry-packing
               $html_results .= TElement::tag('div', $log_content, ['style'=>'max-height: 200px; overflow-y:auto; border:1px solid #eee; padding:5px; background:#f9f9f9;']);
            }
            TScript::create("$('#{$this->output_div->id}').html('".addslashes($html_results)."');");


            // 7. Passar dados para o JavaScript (Three.js)
            $js_three_init_data = "
                const placedItemsData  = ".json_encode($placedItems, JSON_NUMERIC_CHECK).";
                const containerDimsData = ".json_encode($containerDims, JSON_NUMERIC_CHECK).";
            ";
            
            // Carregar o seu script Three.js.
            // Certifique-se que o arquivo existe e o caminho está correto.
            $three_js_file_path = 'app/lib/include/packing_three_script.js';
            if (!file_exists($three_js_file_path)){
                throw new Exception("Arquivo do script Three.js não encontrado: {$three_js_file_path}");
            }
            $js_three_script_content = file_get_contents($three_js_file_path); 
            
            $final_js = $js_three_init_data . "\n" . $js_three_script_content . "\n" .
                        "if (typeof THREE !== 'undefined' && typeof initThree === 'function' && containerDimsData && containerDimsData.largura > 0) { 
                             console.log('Attempting to init Three.js from PHP action...');
                             $('#{$this->three_container_div->id}').show(); // Garante que está visível
                             initThree(); 
                         } else { 
                             console.warn('Three.js ou initThree() não definidos, ou dimensões do contêiner inválidas para init.'); 
                             $('#{$this->three_container_div->id}').html('Erro ao carregar visualização 3D ou dimensões do contêiner inválidas.'); 
                         }";
            
            TScript::create($final_js);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            // Limpar visualizações em caso de erro
            TScript::create("$('#{$this->output_div->id}').html(''); $('#{$this->three_container_div->id}').html('Erro ao gerar: ".addslashes($e->getMessage())."');");
        } finally {
            // TScript::create('__adianti_unblock_ui();'); // Desbloqueia UI se você bloqueou no início
        }
    }
    
    // ... (onImportCSV e onReload como antes) ...
}