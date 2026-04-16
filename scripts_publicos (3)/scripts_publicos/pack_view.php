<?php
/**
 * pack_view.php
 *
 * Página principal para empacotamento 3D de Caixas e Bobinas.
 * Utiliza a classe Empacotamento3D.
 * Inclui todas as funcionalidades: formulário, opções, CSV, loading, resultados na mesma página.
 * Adicionada funcionalidade de Peso Bruto.
 * Permite renderizar contêiner 3D vazio se as dimensões forem válidas.
 */

// Configurações de execução e erros
ini_set('max_execution_time', 300);
set_time_limit(300);
ini_set('memory_limit', '256M');
ini_set('display_errors', 1); // Set to 0 in production
ini_set('display_startup_errors', 1); // Set to 0 in production
error_reporting(E_ALL); // Consider E_ALL & ~E_DEPRECATED & ~E_STRICT in production

require_once __DIR__ . '/Empacotamento3D.php';

// This function generates the entire HTML page content
function renderPage(
    array $placedItems,
    array $containerDims, // Expected: ['largura' => float, 'profundidade' => float, 'altura' => float] or empty
    array $formData,      // Essentially $_POST, used to repopulate the form
    array $logMessages,
    array $errorMessages,
    int $total_volumes_input,
    int $total_embarcados,
    int $total_faltantes,
    float $volume_container_total,
    float $volume_itens_total,
    float $peso_total_itens_embarcados
) {
    // Form field values with defaults
    $f_cont_largura      = htmlspecialchars($formData['cont_largura'] ?? '2.40');
    $f_cont_profundidade = htmlspecialchars($formData['cont_profundidade'] ?? '13.50');
    $f_cont_altura       = htmlspecialchars($formData['cont_altura'] ?? '2.80');

    // Checkbox states
    $f_carregamento_ordenado = !empty($formData['carregamento_ordenado']);
    $f_nao_empilhar = !empty($formData['nao_empilhar']);
    // Default 'permitir_rotacao' to true if not a POST request or if not set in POST
    $f_permitir_rotacao = ($_SERVER['REQUEST_METHOD'] !== 'POST') ? true : !empty($formData['permitir_rotacao']);


    // Item arrays from form data, defaulting to empty arrays if not set
    $form_items_tipos = (isset($formData['tipo']) && is_array($formData['tipo'])) ? $formData['tipo'] : [];
    $form_items_quantidades = (isset($formData['quantidade']) && is_array($formData['quantidade'])) ? $formData['quantidade'] : [];
    $form_items_larguras = (isset($formData['largura']) && is_array($formData['largura'])) ? $formData['largura'] : [];
    $form_items_profundidades = (isset($formData['profundidade']) && is_array($formData['profundidade'])) ? $formData['profundidade'] : [];
    $form_items_alturas = (isset($formData['altura']) && is_array($formData['altura'])) ? $formData['altura'] : [];
    $form_items_pesos_brutos = (isset($formData['peso_bruto']) && is_array($formData['peso_bruto'])) ? $formData['peso_bruto'] : [];

    $num_form_items = count($form_items_tipos);

    // Determine if sections should be shown
    $action_is_generate = ($_POST['action'] ?? '') === 'Gerar Visualização 3D e Resultados';

    // Show results if generation was attempted, no critical errors, AND (items were input OR items were successfully placed)
    $show_results_section = ($action_is_generate && empty($errorMessages) && ($total_volumes_input > 0 || !empty($placedItems)));

    // Can we initialize 3D view? (Valid container dimensions are key)
    $can_init_3d = !empty($containerDims) &&
                   isset($containerDims['largura'], $containerDims['profundidade'], $containerDims['altura']) &&
                   $containerDims['largura'] > 0 &&
                   $containerDims['profundidade'] > 0 &&
                   $containerDims['altura'] > 0;

    // Load Three.js libs if we can init 3D or if we are generating results (even if no items, to show empty container)
    $load_three_js_libs = $can_init_3d;

    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Empacotamento 3D - Otimizado</title>
        <style>
            body { margin: 0; font-family: Arial, sans-serif; padding-bottom: 50px; background-color: #f4f7f6; color: #333; }
            .content-wrapper { max-width: 1100px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
            h1, h2 { text-align: center; margin-top: 20px; margin-bottom: 20px; color: #2c3e50; }
            form { margin-bottom: 30px; }
            fieldset { margin-bottom: 20px; border: 1px solid #ccc; padding: 15px; border-radius: 5px; background-color: #fdfdfd; }
            legend { font-weight: bold; color: #34495e; padding: 0 10px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed;}
            th, td { padding: 10px 8px; border: 1px solid #ddd; text-align: center; word-wrap: break-word; font-size: 0.9em;}
            th { background-color: #e8f0f2; color: #34495e; }
            td input[type="number"], td select { width: 95% !important; box-sizing: border-box; padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-size:0.95em; }
            .acoes { text-align: center; margin-bottom: 20px; margin-top:15px; }
            button, input[type="submit"], input[type="button"] {
                padding: 10px 18px; font-size: 14px; cursor:pointer; margin: 5px; border-radius: 5px; border: none;
                background-color: #3498db; color: white; transition: background-color 0.3s ease;
            }
            button:hover, input[type="submit"]:hover, input[type="button"]:hover { background-color: #2980b9; }
            button.removeRowBtn { background-color: #e74c3c; }
            button.removeRowBtn:hover { background-color: #c0392b; }
            #threeContainer { width: 100%; height: 70vh; display: block; border: 1px solid #ccc; margin-top: 20px; background-color: #ecf0f1; border-radius:5px; }
            .campo-container { margin-bottom: 15px; }
            .campo-container label { display: block; margin-bottom: 5px; font-weight: normal; color: #555; }
            .campo-container input[type="number"], .campo-container input[type="file"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: auto; min-width:120px; }
            .campo-container input[type="checkbox"] + label { display: inline; margin-left: 8px; font-weight: normal;}
            .error-messages, .log-messages-info { margin-bottom: 15px; padding: 12px; border-radius: 4px; font-size: 0.95em; }
            .error-messages { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb;}
            .error-messages ul, .log-messages-info ul { margin: 5px 0 0 0; padding-left: 20px; list-style-type: disc; }
            .log-messages-info { color: #0c5460; background-color: #d1ecf1; border: 1px solid #bee5eb;}
            .log-container { margin-top:20px; padding:15px; border:1px solid #eee; background-color:#f9f9f9; max-height:350px; overflow-y:auto; font-family:monospace; font-size:0.85em; border-radius:5px; }
            .log-container h3 { margin-top:0; color: #34495e; }
            .log-entry { margin-bottom:6px; padding-bottom:6px; border-bottom:1px dotted #ccc; white-space: pre-wrap; word-break: break-all; }
            .log-entry:last-child { border-bottom: none; }
            .log-entry.INFO { color: #2c3e50; } .log-entry.WARNING { color: #e67e22; font-weight:bold; } .log-entry.ERROR { color: #c0392b; font-weight:bold; } .log-entry.DEBUG { color: #27ae60; }
            .summary-stats { margin-top: 15px; margin-bottom: 20px; padding: 15px; background-color: #eaf2f8; border-left: 4px solid #3498db; border-radius: 4px; }
            .summary-stats p { margin: 8px 0; font-size: 0.95em; }
            .summary-stats strong { color: #2c3e50; }
            #loadingOverlay {
                position: fixed; width: 100%; height: 100%;
                top: 0; left: 0; right: 0; bottom: 0;
                background-color: rgba(0,0,0,0.75);
                z-index: 10000; color: white; text-align: center;
                display: flex; flex-direction: column; justify-content: center; align-items: center;
            }
            #loadingOverlay .spinner {
                border: 8px solid #f3f3f3; border-top: 8px solid #3498db; border-radius: 50%;
                width: 60px; height: 60px; animation: spin 1.2s linear infinite; margin-bottom: 20px;
            }
            #loadingOverlay p { font-size: 1.2em; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

            .csv-import-section {
                display: flex;
                align-items: flex-start;
                gap: 20px;
                margin-bottom: 20px;
            }
            .csv-import-section fieldset {
                flex-grow: 1;
                margin-bottom: 0;
            }
            .csv-image-container {
                flex-shrink: 0;
                width: 200px;
                text-align: center;
            }
            .csv-image-container img {
                width: 100%;
                max-width: 180px;
                height: auto;
                border: 1px solid #ddd;
                border-radius: 4px;
                background-color: #fff;
                padding: 5px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .csv-image-container p {
                font-size: 0.85em;
                color: #555;
                margin-top: 8px;
            }

            .container-dimensoes-flex {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                align-items: flex-start;
            }

            .container-dimensoes-flex .campo-container {
                flex: 1;
                min-width: 130px;
                margin-bottom: 0;
            }

            .container-dimensoes-flex .campo-container input[type="number"] {
                width: 100%;
                box-sizing: border-box;
            }
        </style>
        <?php if ($load_three_js_libs): ?>
            <script src="https://cdn.jsdelivr.net/npm/three@0.135.0/build/three.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/three@0.135.0/examples/js/controls/OrbitControls.js"></script>
        <?php endif; ?>
    </head>
    <body>
    <div id="loadingOverlay" style="display:none;">
        <div class="spinner"></div>
        <p>Calculando Empacotamento... Por favor, aguarde.</p>
    </div>

    <div class="content-wrapper">
        <h1>Formulário de Empacotamento 3D</h1>

        <?php if (!empty($errorMessages)): ?>
            <div class="error-messages">
                <strong>Erros Encontrados:</strong>
                <ul><?php foreach ($errorMessages as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <?php
        $csv_import_logs_display = [];
        if (($_POST['action'] ?? '') === 'Importar CSV de Itens' && !empty($logMessages)) {
            foreach ($logMessages as $log) {
                if (strpos($log, "[CSV]") === 0) {
                    $csv_import_logs_display[] = htmlspecialchars(preg_replace('/^\[CSV\]\s*(INFO|WARNING|ERROR):\s*/', '', $log));
                }
            }
        } ?>
        <?php if (!empty($csv_import_logs_display)): ?>
             <div class="log-messages-info">
                <strong>Resultado da Importação CSV:</strong>
                <ul><?php foreach ($csv_import_logs_display as $log_msg): ?><li><?= $log_msg ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" id="packingForm">
            <fieldset>
                <legend>Dimensões do Contêiner</legend>
                <div class="container-dimensoes-flex">
                    <div class="campo-container">
                        <label for="cont_largura">Largura (X) (m):</label>
                        <div><input type="number" id="cont_largura" name="cont_largura" step="any" min="0.001" value="<?= $f_cont_largura ?>" required></div>
                    </div>
                    <div class="campo-container">
                        <label for="cont_profundidade">Comprimento (Y) (m):</label>
                        <div><input type="number" id="cont_profundidade" name="cont_profundidade" step="any" min="0.001" value="<?= $f_cont_profundidade ?>" required></div>
                    </div>
                    <div class="campo-container">
                        <label for="cont_altura">Altura (Z) (m):</label>
                        <div><input type="number" id="cont_altura" name="cont_altura" step="any" min="0.001" value="<?= $f_cont_altura ?>" required></div>
                    </div>
                </div>
            </fieldset>

            <div class="csv-import-section">
                <fieldset>
                    <legend>Importar Itens de CSV</legend>
                    <p style="font-size:0.9em; color:#444;">Formato CSV (cabeçalho opcional): <code>Tipo,Quantidade,Largura,Altura,Profundidade,PesoBruto</code><br>Para Bobinas, Profundidade é opcional (será igual à Largura); PesoBruto é a última coluna esperada.</p>
                    <div class="campo-container">
                        <label for="csv_file_items">Arquivo CSV:</label>
                        <input type="file" name="csv_file_items" id="csv_file_items" accept=".csv">
                    </div>
                    <div class="acoes">
                        <input type="submit" name="action" value="Importar CSV de Itens">
                    </div>
                </fieldset>
                <div class="csv-image-container">
                    <img src="MEDIDAS.jpeg" alt="Ilustração das Medidas: Largura, Altura, Profundidade do item">
                    <p>Dimensões do Item</p>
                </div>
            </div>
            <fieldset>
                <legend>Itens para Empacotar (Caixas/Bobinas)</legend>
                <table id="itensTable">
                    <thead><tr><th>Tipo</th><th>Qtd.</th><th>Largura (X)</th><th>Prof. (Y)</th><th>Altura (Z)</th><th>Peso Bruto (kg)</th><th>Remover</th></tr></thead>
                    <tbody>
                        <?php if ($num_form_items > 0): ?>
                            <?php for ($i = 0; $i < $num_form_items; $i++): ?>
                                <?php
                                $tipo_val = htmlspecialchars($form_items_tipos[$i] ?? 'caixa');
                                $qtd_val = htmlspecialchars($form_items_quantidades[$i] ?? '1');
                                $larg_val = htmlspecialchars($form_items_larguras[$i] ?? '1.00');
                                $alt_val = htmlspecialchars($form_items_alturas[$i] ?? '1.00');
                                $peso_val = htmlspecialchars($form_items_pesos_brutos[$i] ?? '0.0');

                                $prof_readonly_attr = (strtolower($tipo_val) === 'bobina') ? 'readonly' : '';
                                if (strtolower($tipo_val) === 'bobina') {
                                    $prof_val = $larg_val;
                                } else {
                                    $prof_val = htmlspecialchars($form_items_profundidades[$i] ?? '1.00');
                                }
                                ?>
                                <tr>
                                    <td>
                                        <select name="tipo[]" class="tipoSelect">
                                            <option value="caixa" <?= (strtolower($tipo_val) === 'caixa') ? 'selected' : '' ?>>Caixa</option>
                                            <option value="bobina" <?= (strtolower($tipo_val) === 'bobina') ? 'selected' : '' ?>>Bobina</option>
                                        </select>
                                    </td>
                                    <td><input type="number" name="quantidade[]" value="<?= $qtd_val ?>" min="1" step="1" required></td>
                                    <td><input type="number" name="largura[]" step="any" min="0.001" value="<?= $larg_val ?>" required class="larguraInput"></td>
                                    <td><input type="number" name="profundidade[]" step="any" min="0.001" value="<?= $prof_val ?>" required class="profundidadeInput" <?= $prof_readonly_attr ?>></td>
                                    <td><input type="number" name="altura[]" step="any" min="0.001" value="<?= $alt_val ?>" required></td>
                                    <td><input type="number" name="peso_bruto[]" step="any" min="0" value="<?= $peso_val ?>" required></td>
                                    <td><button type="button" class="removeRowBtn">✖</button></td>
                                </tr>
                            <?php endfor; ?>
                        <?php else: ?>
                            <tr>
                                <td>
                                    <select name="tipo[]" class="tipoSelect">
                                        <option value="caixa" selected>Caixa</option><option value="bobina">Bobina</option>
                                    </select>
                                </td>
                                <td><input type="number" name="quantidade[]" value="1" min="1" step="1" required></td>
                                <td><input type="number" name="largura[]" step="any" min="0.001" value="1.00" required class="larguraInput"></td>
                                <td><input type="number" name="profundidade[]" step="any" min="0.001" value="1.00" required class="profundidadeInput"></td>
                                <td><input type="number" name="altura[]" step="any" min="0.001" value="1.00" required></td>
                                <td><input type="number" name="peso_bruto[]" step="any" min="0" value="1.0" required></td>
                                <td><button type="button" class="removeRowBtn">✖</button></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="acoes">
                    <input type="button" id="adicionarItemBtn" value="Adicionar Item Manualmente">
                </div>
            </fieldset>

            <fieldset>
                <legend>Opções de Empacotamento</legend>
                <div class="campo-container" style="margin-bottom: 8px;">
                    <input type="checkbox" id="carregamento_ordenado" name="carregamento_ordenado" value="1" <?= $f_carregamento_ordenado ? 'checked' : '' ?>>
                    <label for="carregamento_ordenado">Seguir ordem da lista (não ordenar por volume)</label>
                </div>
                <div class="campo-container" style="margin-bottom: 8px;">
                    <input type="checkbox" id="nao_empilhar" name="nao_empilhar" value="1" <?= $f_nao_empilhar ? 'checked' : '' ?>>
                    <label for="nao_empilhar">Não empilhar itens (somente no piso)</label>
                </div>
                <div class="campo-container" style="margin-bottom: 8px;">
                    <input type="checkbox" id="permitir_rotacao" name="permitir_rotacao" value="1" <?= $f_permitir_rotacao ? 'checked' : '' ?>>
                    <label for="permitir_rotacao">Permitir rotação de itens (caixas)</label>
                </div>
            </fieldset>

            <div class="acoes">
                <input type="submit" name="action" value="Gerar Visualização 3D e Resultados" id="submitPacking">
            </div>
        </form> {/* */}

        {/* */}
        <?php if ($show_results_section): ?>
            <hr style="border: none; border-top: 1px solid #ccc; margin: 30px 0;">
            <h2>Resultados do Empacotamento</h2>
            <div class="summary-stats">
                <p><strong>Total de volumes para empacotar:</strong> <?= htmlspecialchars($total_volumes_input) ?></p>
                <p><strong>Total de volumes embarcados:</strong> <?= htmlspecialchars($total_embarcados) ?></p>
                <p><strong>Total de volumes faltantes:</strong> <?= htmlspecialchars($total_faltantes) ?></p>
                <p><strong>Metragem cúbica do contêiner:</strong> <?= number_format($volume_container_total, 3) ?> m³</p>
                <p><strong>Peso Bruto Total Embarcado:</strong> <?= number_format($peso_total_itens_embarcados, 2) ?> kg</p>
                <p><strong>Metragem cúbica dos volumes embarcados:</strong> <?= number_format($volume_itens_total, 3) ?> m³</p>
                <?php if ($volume_container_total > 1e-6 && $volume_itens_total >= 0): ?>
                    <p><strong>Utilização de volume:</strong> <?= number_format(($volume_itens_total / $volume_container_total) * 100, 2) ?>%</p>
                <?php endif; ?>
            </div>

            <?php if ($can_init_3d): ?>
                <div id="threeContainer"></div>
                <?php if (empty($placedItems) && $action_is_generate): ?>
                    <p style="text-align:center; color: #777; margin: 20px 0;">Nenhum volume foi embarcado. Visualizando contêiner vazio.</p>
                <?php endif; ?>
            <?php elseif ($action_is_generate && empty($errorMessages)): ?>
                 <p style="text-align:center; color: #777; margin: 20px 0;">Dimensões do contêiner inválidas ou não fornecidas. Não é possível renderizar a visualização 3D.</p>
            <?php endif; ?>

            <?php
            $packing_logs_display = [];
            if ($action_is_generate && !empty($logMessages)) {
                foreach($logMessages as $log) { if (strpos($log, "[CSV]") === false) { $packing_logs_display[] = $log; } }
            }?>
            <?php if(!empty($packing_logs_display)): ?>
            <div class="log-container">
                <h3>Log do Empacotamento:</h3>
                <?php foreach($packing_logs_display as $log):
                    $log_parts = explode("]: ", $log, 2); $prefix = $log_parts[0] ?? ''; $message = $log_parts[1] ?? $log;
                    $type = "INFO";
                    if (strpos($prefix, "] WARNING:") !== false) $type = "WARNING";
                    elseif (strpos($prefix, "] ERROR:") !== false) $type = "ERROR";
                    elseif (strpos($prefix, "] DEBUG:") !== false) $type = "DEBUG";
                ?> <div class="log-entry <?= $type ?>"><?= htmlspecialchars($log) ?></div> <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php endif; // end $show_results_section ?>
        {/* */}

        <?php if ($load_three_js_libs && $can_init_3d): // Script only if libs should be loaded AND we can init 3D ?>
            <script>
                // Ensure this script block runs after the DOM is fully loaded
                document.addEventListener('DOMContentLoaded', function() {
                    const placedItemsData  = <?= json_encode($placedItems, JSON_NUMERIC_CHECK) ?>;
                    const containerDimsData = <?= json_encode($containerDims, JSON_NUMERIC_CHECK) ?>;
                    const colorPalette = [0xE6194B,0x3cb44b,0xffe119,0x4363d8,0xf58231,0x911eb4,0x42d4f4,0xf032e6,0xbfef45,0xfabed4,0x469990,0xdcbeff,0x9A6324,0xffd8b1,0x800000,0xaaffc3,0x808000,0x000075,0x008080,0xFF5733,0x581845,0x0C70F2 ];
                    let scene, camera, renderer, controls;

                    function initThree() {
                        console.log("JS: initThree() called. Container:", containerDimsData, "Items:", placedItemsData.length);
                        scene = new THREE.Scene(); scene.background = new THREE.Color(0xecf0f1); // Light grey background
                        const tc = document.getElementById('threeContainer');
                        if (!tc || tc.clientWidth === 0 || tc.clientHeight === 0) {
                            console.warn("JS: #threeContainer not found or has zero dimensions.");
                            return;
                        }
                        camera = new THREE.PerspectiveCamera(50, tc.clientWidth / tc.clientHeight, 0.1, 2000);
                        const maxDim = Math.max(containerDimsData.largura, containerDimsData.altura, containerDimsData.profundidade);
                        // Position camera to see the whole container
                        camera.position.set(
                            containerDimsData.largura / 2 + maxDim * 0.8,
                            containerDimsData.altura / 2 + maxDim * 0.8,
                            containerDimsData.profundidade / 2 + maxDim * 1.2
                        );
                        camera.lookAt(new THREE.Vector3(containerDimsData.largura/2, containerDimsData.altura/2, containerDimsData.profundidade/2));

                        renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
                        renderer.setSize(tc.clientWidth, tc.clientHeight);
                        renderer.setPixelRatio(window.devicePixelRatio);
                        tc.innerHTML = ''; // Clear previous canvas if any
                        tc.appendChild(renderer.domElement);

                        controls = new THREE.OrbitControls(camera, renderer.domElement);
                        controls.target.set(containerDimsData.largura/2, containerDimsData.altura/2, containerDimsData.profundidade/2);
                        controls.enableDamping = true;
                        controls.dampingFactor = 0.1;
                        controls.screenSpacePanning = false;
                        controls.minDistance = maxDim / 5;
                        controls.maxDistance = maxDim * 5;
                        controls.update();

                        scene.add(new THREE.AmbientLight(0xffffff,0.9));
                        const dl=new THREE.DirectionalLight(0xffffff,0.6);
                        dl.position.set(containerDimsData.largura,containerDimsData.altura*2,containerDimsData.profundidade*1.5).normalize();
                        scene.add(dl);
                        const dl2 = new THREE.DirectionalLight(0xffffff, 0.3);
                        dl2.position.set(-containerDimsData.largura, -containerDimsData.altura*0.5, -containerDimsData.profundidade*0.5).normalize();
                        scene.add(dl2);

                        createContainerWireframe();
                        createPackedItems();
                        animate();
                    }

                    function createContainerWireframe() {
                        const geo = new THREE.BoxGeometry(containerDimsData.largura, containerDimsData.altura, containerDimsData.profundidade);
                        const edges = new THREE.EdgesGeometry(geo);
                        const lineMat = new THREE.LineBasicMaterial({ color: 0x555555, linewidth: 1.5 });
                        const wireframe = new THREE.LineSegments(edges, lineMat);
                        wireframe.position.set(containerDimsData.largura/2, containerDimsData.altura/2, containerDimsData.profundidade/2);
                        scene.add(wireframe);
                    }

                    function createPackedItems() {
                        if (!placedItemsData || placedItemsData.length === 0) { console.log("JS: No items to draw."); return; }
                        placedItemsData.forEach((pl) => {
                            const w=pl.w, d=pl.d, h=pl.h;
                            const itemGeoW = w;
                            const itemGeoH = h;
                            const itemGeoD = d;

                            const posX = pl.x + itemGeoW/2;
                            const posY = pl.z + itemGeoH/2;
                            const posZ = pl.y + itemGeoD/2;

                            const itemColorHex = colorPalette[pl.id_original_instance % colorPalette.length];
                            const mat=new THREE.MeshLambertMaterial({
                                color:itemColorHex,
                                opacity:0.9,
                                transparent:true,
                                side: THREE.DoubleSide
                            });
                            let geo;
                            if (pl.tipo === 'caixa') {
                                geo = new THREE.BoxGeometry(itemGeoW, itemGeoH, itemGeoD);
                            } else { // bobina
                                const radius = itemGeoW/2;
                                geo = new THREE.CylinderGeometry(radius,radius,itemGeoH,32);
                            }
                            const itemMesh=new THREE.Mesh(geo,mat);
                            itemMesh.position.set(posX,posY,posZ);
                            scene.add(itemMesh);

                            const edges = new THREE.EdgesGeometry(geo);
                            const lineMat = new THREE.LineBasicMaterial({ color: 0x111111, linewidth: 1, transparent: true, opacity: 0.4 });
                            const wireframe = new THREE.LineSegments(edges, lineMat);
                            wireframe.position.copy(itemMesh.position);
                            wireframe.rotation.copy(itemMesh.rotation);
                            scene.add(wireframe);
                        });
                    }

                    function animate() {
                        if(!renderer || !scene || !camera) return;
                        requestAnimationFrame(animate);
                        controls.update();
                        renderer.render(scene,camera);
                    }

                    window.addEventListener('resize', () => {
                        const tc=document.getElementById('threeContainer');
                        if(camera&&renderer&&tc&&tc.clientWidth>0&&tc.clientHeight>0){
                            camera.aspect=tc.clientWidth/tc.clientHeight;
                            camera.updateProjectionMatrix();
                            renderer.setSize(tc.clientWidth,tc.clientHeight);
                        }
                    });

                    const threeContainerDiv = document.getElementById('threeContainer');
                    if (threeContainerDiv && containerDimsData && typeof containerDimsData.largura !== 'undefined' && containerDimsData.largura > 0) {
                        console.log("JS: DOMContentLoaded - Attempting to initThree()...");
                        initThree();
                    } else {
                        console.warn("JS: DOMContentLoaded - Conditions for initThree() not met. ContainerDiv:", threeContainerDiv, "Dims:", containerDimsData);
                    }
                });
            </script>
        <?php endif; ?>

        <script>
        // Form manipulation and loading overlay script
        document.addEventListener('DOMContentLoaded', function() {
            function updateRowInteractivity(rowElement) {
                const tipoSelect = rowElement.querySelector('.tipoSelect');
                const larguraInput = rowElement.querySelector('.larguraInput');
                const profundidadeInput = rowElement.querySelector('.profundidadeInput');
                const removeBtn = rowElement.querySelector('.removeRowBtn');

                function handleTipoChange() {
                    if (!tipoSelect || !profundidadeInput || !larguraInput) return;
                    if (tipoSelect.value === 'bobina') {
                        profundidadeInput.value = larguraInput.value;
                        profundidadeInput.readOnly = true;
                    } else {
                        profundidadeInput.readOnly = false;
                    }
                }

                if (tipoSelect) {
                    tipoSelect.removeEventListener('change', handleTipoChange);
                    tipoSelect.addEventListener('change', handleTipoChange);
                    handleTipoChange();
                }
                if (larguraInput && tipoSelect && profundidadeInput) {
                    if (larguraInput._bobinaLarguraListener) {
                        larguraInput.removeEventListener('input', larguraInput._bobinaLarguraListener);
                    }
                    larguraInput._bobinaLarguraListener = function() {
                        if (tipoSelect.value === 'bobina') {
                            profundidadeInput.value = larguraInput.value;
                        }
                    };
                    larguraInput.addEventListener('input', larguraInput._bobinaLarguraListener);
                }
                if (removeBtn) {
                    removeBtn.removeEventListener('click', handleRemoveRow);
                    removeBtn.addEventListener('click', handleRemoveRow);
                }
            }

            function handleRemoveRow(event) {
                const row = event.target.closest('tr');
                const tbody = row.parentElement;
                if (tbody.rows.length > 1) {
                    row.remove();
                } else {
                    const sel = row.querySelector('.tipoSelect');
                    if(sel) sel.value = 'caixa';
                    row.querySelector('input[name="quantidade[]"]').value = '1';
                    row.querySelector('input[name="largura[]"]').value = '1.00';
                    const profInput = row.querySelector('input[name="profundidade[]"]');
                    profInput.value = '1.00';
                    profInput.readOnly = false;
                    row.querySelector('input[name="altura[]"]').value = '1.00';
                    row.querySelector('input[name="peso_bruto[]"]').value = '1.0';
                    updateRowInteractivity(row);
                }
            }

            const adicionarItemBtn = document.getElementById('adicionarItemBtn');
            if (adicionarItemBtn) {
                adicionarItemBtn.addEventListener('click', function() {
                    const tableBody = document.querySelector('#itensTable tbody');
                    const newRowHTML = `
                        <tr>
                            <td><select name="tipo[]" class="tipoSelect"><option value="caixa" selected>Caixa</option><option value="bobina">Bobina</option></select></td>
                            <td><input type="number" name="quantidade[]" value="1" min="1" step="1" required></td>
                            <td><input type="number" name="largura[]" step="any" min="0.001" value="1.00" required class="larguraInput"></td>
                            <td><input type="number" name="profundidade[]" step="any" min="0.001" value="1.00" required class="profundidadeInput"></td>
                            <td><input type="number" name="altura[]" step="any" min="0.001" value="1.00" required></td>
                            <td><input type="number" name="peso_bruto[]" step="any" min="0" value="1.0" required></td>
                            <td><button type="button" class="removeRowBtn">✖</button></td>
                        </tr>`;
                    tableBody.insertAdjacentHTML('beforeend', newRowHTML);
                    const addedRow = tableBody.lastElementChild;
                    if (addedRow) updateRowInteractivity(addedRow);
                });
            }

            document.querySelectorAll('#itensTable tbody tr').forEach(row => {
                updateRowInteractivity(row);
            });

            const packingForm = document.getElementById('packingForm');
            const loadingOverlay = document.getElementById('loadingOverlay');

            if (packingForm && loadingOverlay) {
                packingForm.addEventListener('submit', function(event) {
                    let triggerElement = event.submitter || (document.activeElement && document.activeElement.form === packingForm ? document.activeElement : null);

                    if (triggerElement && triggerElement.name === 'action' && triggerElement.value === 'Gerar Visualização 3D e Resultados') {
                        let formIsValidForLoading = true;
                        const contL = document.getElementById('cont_largura');
                        const contP = document.getElementById('cont_profundidade');
                        const contA = document.getElementById('cont_altura');
                        if (!contL || contL.value.trim() === '' || parseFloat(contL.value) <= 0 ||
                            !contP || contP.value.trim() === '' || parseFloat(contP.value) <= 0 ||
                            !contA || contA.value.trim() === '' || parseFloat(contA.value) <= 0) {
                            formIsValidForLoading = false;
                        }

                        const itemRows = packingForm.querySelectorAll('#itensTable tbody tr');
                        if (itemRows.length === 0) {
                             formIsValidForLoading = false;
                        } else {
                            let hasValidItem = false;
                            itemRows.forEach(row => {
                                const qtdInput = row.querySelector('input[name="quantidade[]"]');
                                const largInput = row.querySelector('input[name="largura[]"]');
                                const altInput = row.querySelector('input[name="altura[]"]');
                                const pesoInput = row.querySelector('input[name="peso_bruto[]"]');
                                if(qtdInput && parseInt(qtdInput.value, 10) > 0 &&
                                   largInput && parseFloat(largInput.value) > 0 &&
                                   altInput && parseFloat(altInput.value) > 0 &&
                                   pesoInput && parseFloat(pesoInput.value) >= 0
                                ) { hasValidItem = true; }
                            });
                            if(!hasValidItem) formIsValidForLoading = false;
                        }

                        if (formIsValidForLoading) {
                            if(loadingOverlay) loadingOverlay.style.display = 'flex';
                        }
                    }
                });
            }
        });
        </script>
    </div>
    </body>
    </html>
    <?php
}

// --- LÓGICA PRINCIPAL DO SCRIPT ---
$placedItems = [];
$containerDims = [];
$logMessages = [];
$errorMessages = [];
$submittedData = $_POST;

$total_volumes_input_calculado = 0;
$total_embarcados_calculado = 0;
$total_faltantes_calculado = 0;
$volume_container_calculado = 0.0;
$volume_itens_embarcados_calculado = 0.0;
$peso_total_itens_embarcados_calculado = 0.0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (isset($_POST['quantidade']) && is_array($_POST['quantidade'])) {
        foreach ($_POST['quantidade'] as $q_item) { $total_volumes_input_calculado += intval($q_item); }
    }
    $cont_larg_s = filter_var($_POST['cont_largura'] ?? 0, FILTER_VALIDATE_FLOAT);
    $cont_prof_s = filter_var($_POST['cont_profundidade'] ?? 0, FILTER_VALIDATE_FLOAT);
    $cont_alt_s  = filter_var($_POST['cont_altura'] ?? 0, FILTER_VALIDATE_FLOAT);

    if ($cont_larg_s && $cont_larg_s > 0 && $cont_prof_s && $cont_prof_s > 0 && $cont_alt_s && $cont_alt_s > 0) {
        $volume_container_calculado = $cont_larg_s * $cont_prof_s * $cont_alt_s;
        $containerDims = ['largura' => $cont_larg_s, 'profundidade' => $cont_prof_s, 'altura' => $cont_alt_s];
    } else {
        if ($action === 'Gerar Visualização 3D e Resultados'){
            if (!($cont_larg_s && $cont_larg_s > 0)) $errorMessages[]="Largura do contêiner inválida ou não fornecida.";
            if (!($cont_prof_s && $cont_prof_s > 0)) $errorMessages[]="Comprimento do contêiner inválido ou não fornecido.";
            if (!($cont_alt_s && $cont_alt_s > 0)) $errorMessages[]="Altura do contêiner inválida ou não fornecida.";
        }
    }


    if ($action === 'Importar CSV de Itens') {
        if (isset($_FILES['csv_file_items']) && $_FILES['csv_file_items']['error'] == UPLOAD_ERR_OK) {
            $csv_file_path = $_FILES['csv_file_items']['tmp_name'];
            $csv_filename = $_FILES['csv_file_items']['name'];
            $logMessages[] = "[CSV] INFO: Tentando importar: " . htmlspecialchars($csv_filename);

            if (strtolower(pathinfo($csv_filename, PATHINFO_EXTENSION)) !== 'csv') {
                $errorMessages[] = "Arquivo inválido (não é .csv).";
            } else {
                $imported_tipos = []; $imported_quantidades = []; $imported_larguras = [];
                $imported_profundidades = []; $imported_alturas = []; $imported_pesos_brutos = [];
                $rowCount = 0; $headerSkipped = false; $importedCount = 0; $skippedCount = 0;

                if (($handle = fopen($csv_file_path, "r")) !== FALSE) {
                    while (($csv_row_data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $rowCount++;
                        if (count($csv_row_data) < 1 || (count($csv_row_data) == 1 && trim($csv_row_data[0]) == '') ) { $skippedCount++; continue; }

                        if (!$headerSkipped && $rowCount == 1) {
                            $first_cell_lower = strtolower(trim($csv_row_data[0] ?? ''));
                            if (in_array($first_cell_lower, ['tipo', 'tipos', 'item', 'desc', 'caixa', 'bobina']) ||
                               (!is_numeric($first_cell_lower) && isset($csv_row_data[1]) && !is_numeric(trim($csv_row_data[1])) ) ) {
                                $logMessages[] = "[CSV] INFO: Cabeçalho CSV detectado e ignorado: " . htmlspecialchars(implode(",", $csv_row_data));
                                $headerSkipped = true; continue;
                            }
                        }

                        $is_bobina_type = isset($csv_row_data[0]) && strtolower(trim($csv_row_data[0])) === 'bobina';
                        $min_cols = $is_bobina_type ? 5 : 6;

                        if (count($csv_row_data) < $min_cols) {
                            $logMessages[] = "[CSV] WARNING: Linha #{$rowCount} (" . htmlspecialchars(implode(",", $csv_row_data)) . ") não contém colunas suficientes (" . count($csv_row_data) . " de min {$min_cols} esperadas), pulando.";
                            $skippedCount++; continue;
                        }

                        $tipo_csv = strtolower(trim($csv_row_data[0]??''));
                        $q_csv_str = trim($csv_row_data[1]??'');
                        $l_csv_str = str_replace(',', '.', trim($csv_row_data[2]??''));
                        $h_csv_str = str_replace(',', '.', trim($csv_row_data[3]??''));

                        $p_csv_str = ''; $peso_csv_str = '';
                        if ($is_bobina_type) {
                            $peso_csv_str = str_replace(',', '.', trim($csv_row_data[4]??''));
                        } else {
                            $p_csv_str = str_replace(',', '.', trim($csv_row_data[4]??''));
                            $peso_csv_str = str_replace(',', '.', trim($csv_row_data[5]??''));
                        }

                        $q_csv = filter_var($q_csv_str, FILTER_VALIDATE_INT);
                        $l_csv = filter_var($l_csv_str, FILTER_VALIDATE_FLOAT);
                        $h_csv = filter_var($h_csv_str, FILTER_VALIDATE_FLOAT);
                        $p_csv = $is_bobina_type ? $l_csv : filter_var($p_csv_str, FILTER_VALIDATE_FLOAT);
                        $peso_csv = filter_var($peso_csv_str, FILTER_VALIDATE_FLOAT);

                        $valid_row=true; $row_errors = [];
                        if(!in_array($tipo_csv,['caixa','bobina'])) $row_errors[]="Tipo '{$csv_row_data[0]}' inválido.";
                        if($q_csv===false||$q_csv<=0) $row_errors[]="Qtd '{$q_csv_str}' inválida.";
                        if($l_csv===false||$l_csv<=0) $row_errors[]="Larg '{$l_csv_str}' inválida.";
                        if($h_csv===false||$h_csv<=0) $row_errors[]="Alt '{$h_csv_str}' inválida.";
                        if(!$is_bobina_type && ($p_csv===false||$p_csv<=0)) $row_errors[]="Prof '{$p_csv_str}' (Caixa) inválida.";
                        if($peso_csv===false||$peso_csv<0) $row_errors[]="Peso '{$peso_csv_str}' inválido.";

                        if (!empty($row_errors)) {
                            $valid_row = false;
                            $logMessages[] = "[CSV] WARNING: L#{$rowCount} (" . htmlspecialchars(implode(",", $csv_row_data)) . ") ignorada: " . implode("; ", $row_errors);
                        }

                        if($valid_row){
                            $imported_tipos[]=$tipo_csv; $imported_quantidades[]=$q_csv; $imported_larguras[]=$l_csv;
                            $imported_profundidades[]=$p_csv; $imported_alturas[]=$h_csv; $imported_pesos_brutos[]=$peso_csv;
                            $importedCount++;
                        } else {$skippedCount++;}
                    }
                    fclose($handle);

                    if($importedCount > 0){
                        $_POST['tipo'] = $submittedData['tipo'] = $imported_tipos;
                        $_POST['quantidade'] = $submittedData['quantidade'] = $imported_quantidades;
                        $_POST['largura'] = $submittedData['largura'] = $imported_larguras;
                        $_POST['profundidade'] = $submittedData['profundidade'] = $imported_profundidades;
                        $_POST['altura'] = $submittedData['altura'] = $imported_alturas;
                        $_POST['peso_bruto'] = $submittedData['peso_bruto'] = $imported_pesos_brutos;
                        $logMessages[]="[CSV] INFO: {$importedCount} tipos de itens importados e carregados no formulário.";
                        $total_volumes_input_calculado = 0;
                        foreach($imported_quantidades as $q) { $total_volumes_input_calculado += intval($q); }
                    } else if(empty($errorMessages)){
                        $errorMessages[]="Nenhum item válido encontrado no arquivo CSV para importar.";
                    }
                    if($skippedCount>0){$logMessages[]="[CSV] WARNING: {$skippedCount} linhas do CSV foram ignoradas devido a erros ou formato incorreto.";}
                } else {$errorMessages[]="Erro ao abrir arquivo CSV. Verifique as permissões.";}
            }
        } else { $errorMessages[] = "Nenhum arquivo CSV foi enviado ou houve um erro no upload."; }

        $placedItems=[];
        $total_embarcados_calculado = 0;
        $volume_itens_embarcados_calculado = 0.0;
        $peso_total_itens_embarcados_calculado = 0.0;
        $total_faltantes_calculado = $total_volumes_input_calculado;

    } elseif ($action === 'Gerar Visualização 3D e Resultados') {
        $csv_logs = [];
        foreach($logMessages as $log_item) { if (strpos($log_item, "[CSV]") === 0) { $csv_logs[] = $log_item; }}
        $logMessages = $csv_logs;

        $tipos=$_POST['tipo']??[]; $quantidades=$_POST['quantidade']??[];
        $larguras=$_POST['largura']??[]; $profundidades=$_POST['profundidade']??[];
        $alturas=$_POST['altura']??[]; $pesos_brutos=$_POST['peso_bruto']??[];
        $numItemsInput = count($tipos);

        if($numItemsInput === 0 && empty($errorMessages)) {
             $errorMessages[]="Nenhum item para empacotar. Adicione itens manualmente ou importe via CSV.";
        }

        for($i=0; $i < $numItemsInput; $i++){
            $itemNum=$i+1;
            $t=trim($tipos[$i]??'');
            $q_val=$quantidades[$i]??0; $q=filter_var($q_val,FILTER_VALIDATE_INT);
            $lw_val=$larguras[$i]??0; $lw=filter_var($lw_val,FILTER_VALIDATE_FLOAT);
            $pd_val=$profundidades[$i]??0;
            $ht_val=$alturas[$i]??0; $ht=filter_var($ht_val,FILTER_VALIDATE_FLOAT);
            $pb_val=$pesos_brutos[$i]??0; $pb=filter_var($pb_val,FILTER_VALIDATE_FLOAT);

            if(!in_array(strtolower($t),['caixa','bobina'])) $errorMessages[]="Item #{$itemNum}: tipo '{$t}' inválido.";
            if($q===false||$q<1) $errorMessages[]="Item #{$itemNum}: quantidade '{$q_val}' inválida.";
            if($lw===false||$lw<=0) $errorMessages[]="Item #{$itemNum}: largura '{$lw_val}' inválida.";
            if($ht===false||$ht<=0) $errorMessages[]="Item #{$itemNum}: altura '{$ht_val}' inválida.";

            if(strtolower($t)==='caixa'){
                $pd_caixa=filter_var($pd_val,FILTER_VALIDATE_FLOAT);
                if($pd_caixa===false||$pd_caixa<=0) $errorMessages[]="Item #{$itemNum}(Caixa): profundidade '{$pd_val}' inválida.";
            }
            if($pb===false||$pb<0) $errorMessages[]="Item #{$itemNum}: peso bruto '{$pb_val}' inválido.";
        }

        if (empty($errorMessages)) {
            $packing = new Empacotamento3D($containerDims['largura'], $containerDims['profundidade'], $containerDims['altura']);
            $packing->setSortByVolumeDesc(!empty($_POST['carregamento_ordenado']) ? false : true);
            $packing->setAllowStacking(empty($_POST['nao_empilhar']));
            $packing->setAllowRotation(!empty($_POST['permitir_rotacao']));

            for ($i=0; $i < $numItemsInput; $i++) {
                $current_tipo = trim($tipos[$i]);
                $current_largura = floatval($larguras[$i]);
                $current_profundidade_input = floatval($profundidades[$i]);

                $actual_profundidade_for_packing = (strtolower($current_tipo) === 'bobina') ? $current_largura : $current_profundidade_input;

                $packing->addItem(
                    $current_tipo,
                    intval($quantidades[$i]),
                    $current_largura,
                    $actual_profundidade_for_packing,
                    floatval($alturas[$i]),
                    floatval($pesos_brutos[$i] ?? 0)
                );
            }

            $packing->generatePacking();
            $placedItems = $packing->getPlacedItems();
            $logMessages = array_merge($logMessages, $packing->getLogFormatted());

            $total_embarcados_calculado = count($placedItems);
            $volume_itens_embarcados_calculado = $packing->getPlacedItemsVolume();
            $peso_total_itens_embarcados_calculado = $packing->getPlacedItemsWeight();
            $total_faltantes_calculado = $total_volumes_input_calculado - $total_embarcados_calculado;
        } else {
            $placedItems = [];
            $total_embarcados_calculado = 0;
            $volume_itens_embarcados_calculado = 0.0;
            $peso_total_itens_embarcados_calculado = 0.0;
            $total_faltantes_calculado = $total_volumes_input_calculado;
        }
    }
}

renderPage(
    $placedItems,
    $containerDims,
    $_POST,
    $logMessages,
    $errorMessages,
    $total_volumes_input_calculado,
    $total_embarcados_calculado,
    $total_faltantes_calculado,
    $volume_container_calculado,
    $volume_itens_embarcados_calculado,
    $peso_total_itens_embarcados_calculado
);
?>