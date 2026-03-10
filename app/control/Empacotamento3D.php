<?php
/**
 * Empacotamento3D.php
 *
 * Classe de empacotamento que calcula a posição de caixas e bobinas dentro de um contêiner
 * usando algoritmo heightâ€map otimizado.
 * Inclui opções para:
 * - Ordenação por volume ou ordem de entrada.
 * - Permitir/Não permitir empilhamento.
 * - Permitir/Não permitir rotação de caixas.
 * - Lógica aprimorada para priorizar empilhamento no "carregamento ordenado".
 * - Adicionada funcionalidade de Peso Bruto.
 */

class Empacotamento3D
{
    private $W; // Largura do Contêiner (X)
    private $D; // Profundidade/Comprimento do Contêiner (Y)
    private $H; // Altura do Contêiner (Z)
    private $items = [];           // Itens originais: ['id_original_instance', 'tipo', 'w_orig', 'd_orig', 'h_orig', 'peso_bruto_orig']
    private $placedItems = [];     // Itens posicionados: ['x','y','z','w','d','h','tipo', 'id_original_instance', 'peso_bruto']
    
    // Opções de empacotamento
    private $sortByVolumeDesc = true; 
    private $allow_stacking = true; 
    private $allow_rotation = true; 

    private $log = []; // Propriedade para armazenar os logs
    private static $itemCounter = 0; 
    private $lastPlacedItemDetails = null; // Para ajudar na priorização de empilhamento ordenado

    public function __construct(float $W, float $D, float $H)
    {
        $this->W = $W;
        $this->D = $D;
        $this->H = $H;
        self::$itemCounter = 0; 
        $this->addLog("INFO", "Contêiner (geral) inicializado: W={$W}, D={$D}, H={$H}.");
    }

    private function addLog(string $type, string $msg)
    {
        $timestamp = date('Y-m-d H:i:s');
        $this->log[] = "[{$timestamp}] {$type}: {$msg}";
    }

    public function getLogFormatted(): array
    {
        return $this->log;
    }

    public function addItem(string $tipo, int $q, float $w, float $d, float $h, float $peso_bruto)
    {
        if (!in_array($tipo, ['caixa','bobina'], true)) {
            $this->addLog("WARNING", "Tipo inválido: {$tipo}. Item ignorado.");
            return;
        }
        if ($q < 1 || $w <= 1e-6 || $h <= 1e-6 || ($tipo === 'caixa' && $d <= 1e-6) || $peso_bruto < 0) {
            $this->addLog("WARNING", "Par�metros inv�lidos para item [$tipo]: q={$q}, w={$w}, d={$d}, h={$h}, peso={$peso_bruto}. Item ignorado.");
            return;
        }
        
        for ($i = 0; $i < $q; $i++) {
            self::$itemCounter++;
            $this->items[] = [
                'id_original_instance' => self::$itemCounter,
                'tipo' => $tipo,
                'w_orig' => $w,
                'd_orig' => $d, 
                'h_orig' => $h,
                'peso_bruto_orig' => $peso_bruto,
            ];
        }
        $this->addLog("INFO", "Adicionado {$q}Ã— {$tipo} (Base: {$w}Ã—{$d}Ã—{$h}, Peso: {$peso_bruto}kg). Inst. IDs: " . (self::$itemCounter - $q + 1) . " a " . self::$itemCounter);
    }

    public function setSortByVolumeDesc(bool $flag)
    {
        $this->sortByVolumeDesc = $flag;
        $logMsg = $flag ? "Ordenação por volume decrescente ATIVADA." : "Ordenação por volume decrescente DESATIVADA (ordem de entrada).";
        $this->addLog("INFO", $logMsg);
    }

    public function setAllowStacking(bool $flag)
    {
        $this->allow_stacking = $flag;
        $logMsg = $flag ? "Empilhamento de itens PERMITIDO." : "Empilhamento de itens DESATIVADO (somente no piso).";
        $this->addLog("INFO", $logMsg);
    }

    public function setAllowRotation(bool $flag)
    {
        $this->allow_rotation = $flag;
        $logMsg = $flag ? "Rotação de caixas PERMITIDA." : "Rotação de caixas DESATIVADA (usar orientação original).";
        $this->addLog("INFO", $logMsg);
    }
    
    private function getItemOrientations(array $item_instance): array
    {
        $tipoOriginal = $item_instance['tipo'];
        $w = $item_instance['w_orig'];
        $d = $item_instance['d_orig']; 
        $h = $item_instance['h_orig'];
        $peso_bruto_orig = $item_instance['peso_bruto_orig'];
        $orientations = [];
        $id_original_instance = $item_instance['id_original_instance'];

        if ($tipoOriginal === 'bobina') {
            $orientations[] = ['w' => $w, 'd' => $w, 'h' => $h, 'tipo_orientacao' => 'bobina', 'id_original_instance' => $id_original_instance, 'peso_bruto_orig' => $peso_bruto_orig];
            return $orientations;
        }

        if (!$this->allow_rotation) {
            $orientations[] = ['w' => $w, 'd' => $d, 'h' => $h, 'tipo_orientacao' => 'caixa', 'id_original_instance' => $id_original_instance, 'peso_bruto_orig' => $peso_bruto_orig];
            return $orientations;
        }

        $dims_permutations = [
            [$w, $d, $h], [$d, $w, $h],
            [$w, $h, $d], [$h, $w, $d], 
            [$d, $h, $w], [$h, $d, $w]  
        ];
        $seen_rotations_keys = [];
        foreach ($dims_permutations as $p_dims) {
            list($pw, $pd, $ph) = $p_dims;
            if ($pw <= 1e-6 || $pd <= 1e-6 || $ph <= 1e-6) continue; 

            $key_str = number_format($pw, 6) . '-' . number_format($pd, 6) . '-' . number_format($ph, 6);

            if (!isset($seen_rotations_keys[$key_str])) {
                 $orientations[] = ['w' => $pw, 'd' => $pd, 'h' => $ph, 'tipo_orientacao' => 'caixa', 'id_original_instance' => $id_original_instance, 'peso_bruto_orig' => $peso_bruto_orig];
                 $seen_rotations_keys[$key_str] = true;
            }
        }
        return $orientations;
    }

    public function generatePacking()
    {
        $this->placedItems = []; 
        $this->lastPlacedItemDetails = null; 
        
        $this->addLog("INFO", "Iniciando empacotamento... Empilhar: " . ($this->allow_stacking ? "ON" : "OFF") . 
                                 ", Rotacionar: " . ($this->allow_rotation ? "ON" : "OFF") .
                                 ", Ordenar por Volume: " . ($this->sortByVolumeDesc ? "ON" : "OFF"));
        
        $W_cont = $this->W; $D_cont = $this->D; $H_cont = $this->H;
        
        $itemsToPack = $this->items; 
        if (empty($itemsToPack)) { 
            $this->addLog("WARNING", "Nenhum item para empacotar."); 
            return; 
        }

        if ($this->sortByVolumeDesc) { 
            usort($itemsToPack, function($a, $b) {
                $volA_d = ($a['tipo'] === 'bobina') ? $a['w_orig'] : $a['d_orig'];
                $volB_d = ($b['tipo'] === 'bobina') ? $b['w_orig'] : $b['d_orig'];
                $volA = $a['w_orig'] * $volA_d * $a['h_orig'];
                $volB = $b['w_orig'] * $volB_d * $b['h_orig'];
                if (abs($volA - $volB) < 1e-6) return 0;
                return ($volA > $volB) ? -1 : 1; 
            });
            $this->addLog("INFO", "Itens (instÃ¢ncias) ordenados por volume original decrescente.");
        } else {
            $this->addLog("INFO", "Empacotando na ordem de entrada das instÃ¢ncias (carregamento ordenado).");
        }

        $totalItemsToPack = count($itemsToPack);
        $this->addLog("INFO", "Total de instÃ¢ncias para empacotar: {$totalItemsToPack}.");

        $candidatePositions = [[0.0, 0.0]]; 
        $placedItemsSnapshot = []; 
        $this->addLog("DEBUG", "Número inicial de posições candidatas: " . count($candidatePositions));

        $computeSupportHeight = function($xPos, $yPos, $wItem, $dItem) use (&$placedItemsSnapshot) {
            $supportZ = 0.0;
            foreach ($placedItemsSnapshot as $pi) {
                $overlapX = !($xPos + $wItem <= $pi['x'] + 1e-6 || $pi['x'] + $pi['w'] <= $xPos + 1e-6);
                $overlapY = !($yPos + $dItem <= $pi['y'] + 1e-6 || $pi['y'] + $pi['d'] <= $yPos + 1e-6);
                if ($overlapX && $overlapY) {
                    $supportZ = max($supportZ, $pi['z'] + $pi['h']);
                }
            }
            return $supportZ;
        };

        $packedCount = 0;
        foreach ($itemsToPack as $item_idx => $itemOriginalInstance) {
            $idInstance = $itemOriginalInstance['id_original_instance'];
            $tipoOriginalItem = $itemOriginalInstance['tipo'];
            $pesoOriginalItem = $itemOriginalInstance['peso_bruto_orig'];
            $dimsOrigStr = "{$itemOriginalInstance['w_orig']}Ã—{$itemOriginalInstance['d_orig']}Ã—{$itemOriginalInstance['h_orig']}";
            $itemIdentifier = "Inst.ID {$idInstance} ({$tipoOriginalItem} orig: {$dimsOrigStr}, Peso: {$pesoOriginalItem}kg)";
            
            $this->addLog("DEBUG", "Processando item ".($item_idx+1)."/".$totalItemsToPack.": {$itemIdentifier}. Posições candidatas XY: " . count($candidatePositions));
            if (count($candidatePositions) > 1000) { 
                 $this->addLog("WARNING", "Número de posições candidatas (".count($candidatePositions).") muito alto (>1000). Pode causar lentidão.");
            }

            $bestPlacementOverallForThisInstance = null;
            $allPossiblePlacementsForThisInstance = []; 

            $orientations = $this->getItemOrientations($itemOriginalInstance);
            if (count($orientations) > 1 && $tipoOriginalItem === 'caixa') {
                 $this->addLog("DEBUG", "  Testando " . count($orientations) . " orientações para {$itemIdentifier}");
            }
            
            foreach ($orientations as $orientedItem) {
                $w_oriented = $orientedItem['w'];
                $d_oriented = $orientedItem['d'];
                $h_oriented = $orientedItem['h'];

                if (!$this->sortByVolumeDesc && $this->allow_stacking) {
                    foreach ($placedItemsSnapshot as $prevItem) {
                        if ($prevItem['tipo'] === $tipoOriginalItem &&
                            abs($w_oriented - $prevItem['w']) < 1e-6 &&
                            abs($d_oriented - $prevItem['d']) < 1e-6) {
                            
                            $cx_stack = $prevItem['x'];
                            $cy_stack = $prevItem['y'];
                            $zBaseForStack = $prevItem['z'] + $prevItem['h'];

                            if ($zBaseForStack + $h_oriented <= $H_cont + 1e-6) {
                                $actualSupportAtStackPos = $computeSupportHeight($cx_stack, $cy_stack, $w_oriented, $d_oriented);
                                if (abs($actualSupportAtStackPos - $zBaseForStack) < 1e-6) {
                                    $allPossiblePlacementsForThisInstance[] = [ 
                                        'x' => $cx_stack, 'y' => $cy_stack, 'z_base' => $zBaseForStack,
                                        'w' => $w_oriented, 'd' => $d_oriented, 'h' => $h_oriented,
                                        'tipo_original_item' => $tipoOriginalItem, 
                                        'id_original_instance' => $idInstance,
                                        'peso_bruto_orig' => $pesoOriginalItem,
                                        'is_preferred_stack' => true 
                                    ];
                                }
                            }
                        }
                    }
                }

                foreach ($candidatePositions as $candPos) {
                    list($cx, $cy) = $candPos;
                    if ($cx + $w_oriented > $W_cont + 1e-6 || $cy + $d_oriented > $D_cont + 1e-6) continue;
                    
                    $zBaseCalculated = $computeSupportHeight($cx, $cy, $w_oriented, $d_oriented);
                    
                    $zBaseToUse = $zBaseCalculated;
                    if (!$this->allow_stacking) { 
                        if ($zBaseCalculated > 1e-6) continue; 
                        $zBaseToUse = 0.0; 
                    }

                    if ($zBaseToUse + $h_oriented > $H_cont + 1e-6) continue; 
                    
                    $allPossiblePlacementsForThisInstance[] = [ 
                        'x' => $cx, 'y' => $cy, 'z_base' => $zBaseToUse,
                        'w' => $w_oriented, 'd' => $d_oriented, 'h' => $h_oriented,
                        'tipo_original_item' => $tipoOriginalItem, 
                        'id_original_instance' => $idInstance,
                        'peso_bruto_orig' => $pesoOriginalItem,
                        'is_preferred_stack' => false 
                    ];
                }
            } 

            if (!empty($allPossiblePlacementsForThisInstance)) {
                $allPossiblePlacementsForThisInstance = array_map("unserialize", array_unique(array_map("serialize", $allPossiblePlacementsForThisInstance)));
                $isOrderedLoading = !$this->sortByVolumeDesc;
                $lastItemDetailsForSort = $this->lastPlacedItemDetails;

                usort($allPossiblePlacementsForThisInstance, function($a, $b) use ($isOrderedLoading, $lastItemDetailsForSort) {
                    if ($isOrderedLoading && $lastItemDetailsForSort !== null &&
                        isset($a['tipo_original_item']) && $a['tipo_original_item'] === $lastItemDetailsForSort['tipo'] && 
                        isset($a['w']) && abs($a['w'] - $lastItemDetailsForSort['w']) < 1e-6 &&      
                        isset($a['d']) && abs($a['d'] - $lastItemDetailsForSort['d']) < 1e-6) {      
                        
                        $a_is_direct_stack = (isset($a['x']) && abs($a['x'] - $lastItemDetailsForSort['x']) < 1e-6 &&
                                              isset($a['y']) && abs($a['y'] - $lastItemDetailsForSort['y']) < 1e-6 &&
                                              isset($a['z_base']) && abs($a['z_base'] - ($lastItemDetailsForSort['z'] + $lastItemDetailsForSort['h'])) < 1e-6);
                        
                        $b_is_direct_stack = (isset($b['tipo_original_item']) && $b['tipo_original_item'] === $lastItemDetailsForSort['tipo'] &&
                                              isset($b['w']) && abs($b['w'] - $lastItemDetailsForSort['w']) < 1e-6 &&
                                              isset($b['d']) && abs($b['d'] - $lastItemDetailsForSort['d']) < 1e-6 &&
                                              isset($b['x']) && abs($b['x'] - $lastItemDetailsForSort['x']) < 1e-6 &&
                                              isset($b['y']) && abs($b['y'] - $lastItemDetailsForSort['y']) < 1e-6 &&
                                              isset($b['z_base']) && abs($b['z_base'] - ($lastItemDetailsForSort['z'] + $lastItemDetailsForSort['h'])) < 1e-6);

                        if ($a_is_direct_stack && !$b_is_direct_stack) return -1; 
                        if (!$a_is_direct_stack && $b_is_direct_stack) return 1;  
                    }
                    
                    if (abs($a['z_base'] - $b['z_base']) > 1e-6) return ($a['z_base'] < $b['z_base']) ? -1 : 1;
                    if (abs($a['y'] - $b['y']) > 1e-6) return ($a['y'] < $b['y']) ? -1 : 1;
                    if (abs($a['x'] - $b['x']) > 1e-6) return ($a['x'] < $b['x']) ? -1 : 1;
                    return 0;
                });
                $bestPlacementOverallForThisInstance = $allPossiblePlacementsForThisInstance[0];
            }

            if ($bestPlacementOverallForThisInstance !== null) {
                $finalPlacementData = $bestPlacementOverallForThisInstance;
                $placedItem = [
                    'x' => $finalPlacementData['x'], 'y' => $finalPlacementData['y'], 'z' => $finalPlacementData['z_base'],
                    'w' => $finalPlacementData['w'], 'd' => $finalPlacementData['d'], 'h' => $finalPlacementData['h'],
                    'tipo' => $finalPlacementData['tipo_original_item'], 
                    'id_original_instance' => $finalPlacementData['id_original_instance'],
                    'peso_bruto' => $finalPlacementData['peso_bruto_orig'] 
                ];
                $this->placedItems[] = $placedItem;
                $placedItemsSnapshot[] = $placedItem; 
                $packedCount++;
                
                if (!$this->sortByVolumeDesc) {
                    $this->lastPlacedItemDetails = $placedItem;
                } else {
                    $this->lastPlacedItemDetails = null;
                }
                
                $newCand_Base = [$placedItem['x'], $placedItem['y']]; 
                $newCand_X = [$placedItem['x'] + $placedItem['w'], $placedItem['y']];
                $newCand_Y = [$placedItem['x'], $placedItem['y'] + $placedItem['d']];
                $candidatePositions[] = $newCand_Base; 
                if ($newCand_X[0] < $W_cont - 1e-6) $candidatePositions[] = $newCand_X;
                if ($newCand_Y[1] < $D_cont - 1e-6) $candidatePositions[] = $newCand_Y;
                $candidatePositions = array_map('unserialize', array_unique(array_map('serialize', $candidatePositions)));
                usort($candidatePositions, function($a, $b){ 
                    if(abs($a[1] - $b[1]) > 1e-6) return ($a[1] < $b[1]) ? -1 : 1; // Sort by Y primarily
                    if(abs($a[0] - $b[0]) > 1e-6) return ($a[0] < $b[0]) ? -1 : 1; // Then by X
                    return 0;
                });
                
                $logMsg = "{$itemIdentifier} POSICIONADO como {$placedItem['tipo']} com dims {$placedItem['w']}Ã—{$placedItem['d']}Ã—{$placedItem['h']} em ({$placedItem['x']},{$placedItem['y']},{$placedItem['z']}).";
                if ($finalPlacementData['is_preferred_stack']) $logMsg .= " (Empilhamento preferencial em coluna).";
                
                $orientationChanged = false;
                if ($tipoOriginalItem === 'caixa' && $this->allow_rotation) { 
                    $dimsOrigSet = [$itemOriginalInstance['w_orig'], $itemOriginalInstance['d_orig'], $itemOriginalInstance['h_orig']]; sort($dimsOrigSet, SORT_NUMERIC);
                    $dimsPlacedSet = [$placedItem['w'], $placedItem['d'], $placedItem['h']]; sort($dimsPlacedSet, SORT_NUMERIC);
                    
                    // Check if the sorted sets of dimensions are different
                    if (count(array_diff_assoc($dimsOrigSet, $dimsPlacedSet)) > 0 || count(array_diff_assoc($dimsPlacedSet, $dimsOrigSet)) > 0 ){
                         $orientationChanged = true;
                    } else { // If sorted sets are same, check if the specific orientation changed
                        if( abs($itemOriginalInstance['w_orig'] - $placedItem['w']) > 1e-6 ||
                            abs($itemOriginalInstance['d_orig'] - $placedItem['d']) > 1e-6 ||
                            abs($itemOriginalInstance['h_orig'] - $placedItem['h']) > 1e-6){
                            $orientationChanged = true;
                        }
                    }
                }
                if ($orientationChanged) $logMsg .= " (Orientação rotacionada).";
                $this->addLog("INFO", $logMsg);

            } else {
                $this->addLog("WARNING", "Sem espaço: {$itemIdentifier} não pôde ser colocado.");
                 if (!$this->sortByVolumeDesc) { 
                    $this->lastPlacedItemDetails = null;
                 }
            }
        } 

        $this->addLog("INFO", "Empacotamento (geral) conclu�do: {$packedCount}/{$totalItemsToPack} inst�ncias posicionadas.");
        $this->addLog("INFO", "Volume do contêiner: " . number_format($this->W * $this->D * $this->H, 3) . " m³.");
        $this->addLog("INFO", "Volume dos itens posicionados: " . number_format($this->getPlacedItemsVolume(), 3) . " m³.");
        $this->addLog("INFO", "Utilização do volume: " . number_format($this->getVolumeUtilization(), 2) . "%.");
        $this->addLog("INFO", "Peso bruto total dos itens posicionados: " . number_format($this->getPlacedItemsWeight(), 2) . " kg.");
    }

    public function getPlacedItemsVolume(): float {
        $totalVolumeItems = 0.0;
        $this->addLog("DEBUG", "[getPlacedItemsVolume] Iniciando. Número de placedItems: " . count($this->placedItems)); 

        foreach ($this->placedItems as $idx => $item) {
            $item_vol = 0.0;
            $item_w = $item['w'] ?? 0.0; 
            $item_d = $item['d'] ?? 0.0; 
            $item_h = $item['h'] ?? 0.0;
            $item_tipo = $item['tipo'] ?? 'desconhecido';

            if (!is_numeric($item_w) || !is_numeric($item_d) || !is_numeric($item_h)) {
                $this->addLog("DEBUG", "[getPlacedItemsVolume]   Item #{$idx} (Tipo: {$item_tipo}) tem dimensões não numéricas. W:".gettype($item_w).", D:".gettype($item_d).", H:".gettype($item_h));
                continue; 
            }

            if (strtolower($item_tipo) === 'bobina') { 
                if ($item_w > 1e-6 && $item_h > 1e-6) { 
                    $radius = $item_w / 2.0; 
                    $item_vol = M_PI * $radius * $radius * $item_h;
                } else {
                     $this->addLog("DEBUG", "[getPlacedItemsVolume]   Item #{$idx} (Bobina) com dimensão W ou H zerada/inválida. W:{$item_w}, H:{$item_h}");
                }
            } elseif (strtolower($item_tipo) === 'caixa') { 
                if ($item_w > 1e-6 && $item_d > 1e-6 && $item_h > 1e-6) {
                    $item_vol = $item_w * $item_d * $item_h; 
                } else {
                    $this->addLog("DEBUG", "[getPlacedItemsVolume]   Item #{$idx} (Caixa) com alguma dimensão zerada/inválida. W:{$item_w}, D:{$item_d}, H:{$item_h}");
                }
            } else {
                 $this->addLog("DEBUG", "[getPlacedItemsVolume]   Item #{$idx} com tipo desconhecido: {$item_tipo}");
            }
            
            $this->addLog("DEBUG", "[getPlacedItemsVolume]   Item #{$idx} (Tipo: {$item_tipo}, W:{$item_w}, D:{$item_d}, H:{$item_h}) -> Vol Calculado: {$item_vol}");
            $totalVolumeItems += $item_vol;
        }
        $this->addLog("DEBUG", "[getPlacedItemsVolume] Finalizou. Volume Total Acumulado: {$totalVolumeItems}");
        return (float)$totalVolumeItems; 
    }

    public function getVolumeUtilization(): float {
        $containerVolume = $this->W * $this->D * $this->H;
        if (abs($containerVolume) < 1e-6) return 0.0; 
        $placedVolume = $this->getPlacedItemsVolume();
        if (abs($placedVolume) < 1e-6 && abs($containerVolume) < 1e-6) return 0.0; // Avoid division by zero if container is 0
        if (abs($containerVolume) < 1e-6) return 0.0; // Should be caught by first check, but for safety
        return ($placedVolume / $containerVolume) * 100.0;
    }

    public function getPlacedItemsWeight(): float {
        $totalWeightItems = 0.0;
        $this->addLog("DEBUG", "[getPlacedItemsWeight] Iniciando. Número de placedItems: " . count($this->placedItems));
        foreach ($this->placedItems as $idx => $item) {
            $item_peso = $item['peso_bruto'] ?? 0.0;
            if (!is_numeric($item_peso)) {
                $this->addLog("DEBUG", "[getPlacedItemsWeight]   Item #{$idx} (ID Orig: {$item['id_original_instance']}) tem peso não numérico: ".gettype($item_peso));
                continue;
            }
            $this->addLog("DEBUG", "[getPlacedItemsWeight]   Item #{$idx} (ID Orig: {$item['id_original_instance']}, Tipo: {$item['tipo']}) -> Peso: {$item_peso}");
            $totalWeightItems += (float)$item_peso;
        }
        $this->addLog("DEBUG", "[getPlacedItemsWeight] Finalizou. Peso Total Acumulado: {$totalWeightItems}");
        return (float)$totalWeightItems;
    }

    public function getPlacedItems(): array { return $this->placedItems; }
}