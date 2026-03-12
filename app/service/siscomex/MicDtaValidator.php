<?php


use Exception;

/**
 * MicDtaValidator
 * Validador de dados para transmissão MIC/DTA ao Siscomex
 *
 * Valida:
 * - Formato de chaves NF-e
 * - Dados de veículo
 * - Dados de motorista
 * - Dados de exportador/importador
 */
class MicDtaValidator
{
    /**
     * Valida uma ou múltiplas chaves de acesso NF-e
     *
     * @param string|array $keys Chave ou array de chaves
     * @return array ['valid' => bool, 'errors' => []]
     */
    public static function validateNFeKeys($keys)
    {
        $errors = array();

        if (!is_array($keys)) {
            $keys = array($keys);
        }

        if (empty($keys)) {
            $errors[] = "Nenhuma chave de NF-e informada";
        }

        foreach ($keys as $index => $key) {
            if (empty($key)) {
                $errors[] = "Chave {$index} está vazia";
                continue;
            }

            // Remover caracteres não numéricos
            $key_clean = preg_replace('/\D/', '', $key);

            // Validar formato (44 dígitos)
            if (strlen($key_clean) !== 44) {
                $errors[] = "Chave {$index} inválida: deve ter 44 dígitos (tem " . strlen($key_clean) . ")";
                continue;
            }

            // Validar dígito verificador
            if (!self::validateNFeKeyChecksum($key_clean)) {
                $errors[] = "Chave {$index} com dígito verificador inválido";
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Calcula e valida o dígito verificador da chave NF-e
     *
     * Algoritmo RFC padrão (módulo 11)
     *
     * @param string $chave Chave com 44 dígitos
     * @return bool
     */
    public static function validateNFeKeyChecksum($chave)
    {
        if (!preg_match('/^\d{44}$/', $chave)) {
            return false;
        }

        // Extrair dígito verificador (último dígito)
        $dv = intval(substr($chave, -1));

        // Calcular dígito verificador
        $multiplicador = 2;
        $soma = 0;

        // Processar dígitos de trás para frente (exceto o DV)
        for ($i = 42; $i >= 0; $i--) {
            $soma += intval(substr($chave, $i, 1)) * $multiplicador;
            $multiplicador++;
            if ($multiplicador > 9) {
                $multiplicador = 2;
            }
        }

        $resto = $soma % 11;
        $dv_calculado = $resto === 0 ? 0 : 11 - $resto;

        return $dv === $dv_calculado;
    }

    /**
     * Valida dados de veículo
     *
     * @param object $vehicle Objeto com placa, antt, etc
     * @return array ['valid' => bool, 'errors' => []]
     */
    public static function validateVehicleData($vehicle)
    {
        $errors = array();

        if (!$vehicle) {
            $errors[] = "Dados do veículo não informados";
        } else {
            // Validar placa
            $placa = $vehicle->placa_trator ?? null;
            if (empty($placa)) {
                $errors[] = "Placa do trator não informada";
            } elseif (!self::validatePlate($placa)) {
                $errors[] = "Placa do trator inválida: {$placa}";
            }

            // Validar ANTT (opcional mas recomendado)
            if (isset($vehicle->antt_consulta_id) && !empty($vehicle->antt_consulta_id)) {
                // ANTT informado, fazer validações
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Valida formato de placa de veículo brasileiro
     *
     * Formatos aceitos:
     * - AAA-1111 (padrão antigo)
     * - AAA1111 (padrão antigo sem hífen)
     * - AAA1A11 (padrão Mercosul)
     * - AAA-1A11 (padrão Mercosul com hífen)
     *
     * @param string $plate Placa a validar
     * @return bool
     */
    public static function validatePlate($plate)
    {
        if (empty($plate)) {
            return false;
        }

        // Remover hífens e espaços
        $plate = strtoupper(preg_replace('/[-\s]/', '', $plate));

        // Validar padrões
        // Padrão antigo: AAA1111 ou AAA1D11 (Mercosul)
        if (preg_match('/^[A-Z]{3}\d{4}$/', $plate)) {
            return true;
        }

        // Padrão Mercosul: AAA1A11
        if (preg_match('/^[A-Z]{3}\d[A-Z]\d{2}$/', $plate)) {
            return true;
        }

        return false;
    }

    /**
     * Valida dados de motorista
     *
     * @param object $driver Objeto com cpf, cnh, etc
     * @return array ['valid' => bool, 'errors' => []]
     */
    public static function validateDriverData($driver)
    {
        $errors = array();

        if (!$driver) {
            $errors[] = "Dados do motorista não informados";
        } else {
            // Validar CPF
            $cpf = $driver->cpf ?? null;
            if (empty($cpf)) {
                $errors[] = "CPF do motorista não informado";
            } elseif (!self::validateCPF($cpf)) {
                $errors[] = "CPF do motorista inválido: {$cpf}";
            }

            // Validar CNH
            $cnh = $driver->cnh_numero ?? null;
            if (empty($cnh)) {
                $errors[] = "Número da CNH não informado";
            } elseif (!self::validateCNH($cnh)) {
                $errors[] = "Número da CNH inválido: {$cnh}";
            }

            // Validar categoria da CNH
            $categoria = $driver->categoria ?? null;
            if (empty($categoria)) {
                $errors[] = "Categoria da CNH não informada";
            } elseif (!in_array($categoria, array('A', 'B', 'C', 'D', 'E'))) {
                $errors[] = "Categoria da CNH inválida: {$categoria}";
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Valida CPF
     *
     * @param string $cpf CPF a validar
     * @return bool
     */
    public static function validateCPF($cpf)
    {
        // Remover caracteres não numéricos
        $cpf = preg_replace('/\D/', '', $cpf);

        // Deve ter 11 dígitos
        if (strlen($cpf) !== 11) {
            return false;
        }

        // CPF não pode ter todos os dígitos iguais
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // Validar dígito verificador
        $digit1 = self::calculateCPFDigit($cpf, 0, 9);
        $digit2 = self::calculateCPFDigit($cpf, 1, 10);

        return intval($cpf[9]) === $digit1 && intval($cpf[10]) === $digit2;
    }

    /**
     * Calcula dígito verificador do CPF
     */
    private static function calculateCPFDigit($cpf, $start, $length)
    {
        $sum = 0;
        $multiplier = $length + 1;

        for ($i = $start; $i < $length; $i++) {
            $sum += intval($cpf[$i]) * $multiplier;
            $multiplier--;
        }

        $remainder = $sum % 11;
        return $remainder < 2 ? 0 : 11 - $remainder;
    }

    /**
     * Valida CNH (números apenas, deve ter entre 9 e 12 dígitos)
     *
     * @param string $cnh CNH a validar
     * @return bool
     */
    public static function validateCNH($cnh)
    {
        // Remover caracteres não numéricos
        $cnh = preg_replace('/\D/', '', $cnh);

        // CNH deve ter entre 9 e 12 dígitos
        return strlen($cnh) >= 9 && strlen($cnh) <= 12;
    }

    /**
     * Valida dados de exportador/importador
     *
     * @param object $person Objeto com cnpj, nome, etc
     * @param string $type 'exporter' ou 'importer'
     * @return array ['valid' => bool, 'errors' => []]
     */
    public static function validatePersonData($person, $type = 'exporter')
    {
        $errors = array();
        $typeName = $type === 'exporter' ? 'Exportador' : 'Importador';

        if (!$person) {
            $errors[] = "{$typeName} não informado";
        } else {
            // CNPJ é obrigatório para exportador
            if ($type === 'exporter') {
                $cnpj = $person->cnpj ?? null;
                if (empty($cnpj)) {
                    $errors[] = "CNPJ do {$typeName} não informado";
                } elseif (!self::validateCNPJ($cnpj)) {
                    $errors[] = "CNPJ do {$typeName} inválido: {$cnpj}";
                }
            } else {
                // Para importador, CNPJ é opcional (pode ser do exterior)
                $cnpj = $person->cnpj ?? null;
                if (!empty($cnpj) && !self::validateCNPJ($cnpj)) {
                    $errors[] = "CNPJ do {$typeName} inválido: {$cnpj}";
                }
            }

            // Nome é obrigatório
            $nome = $person->nome ?? null;
            if (empty($nome)) {
                $errors[] = "Nome do {$typeName} não informado";
            } elseif (strlen($nome) < 3) {
                $errors[] = "Nome do {$typeName} muito curto";
            }

            // Endereço é obrigatório
            $endereco = $person->endereco ?? null;
            if (empty($endereco)) {
                $errors[] = "Endereço do {$typeName} não informado";
            }

            // Cidade é obrigatória
            $cidade = $person->cidade ?? null;
            if (empty($cidade)) {
                $errors[] = "Cidade do {$typeName} não informada";
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Valida CNPJ
     *
     * @param string $cnpj CNPJ a validar
     * @return bool
     */
    public static function validateCNPJ($cnpj)
    {
        // Remover caracteres não numéricos
        $cnpj = preg_replace('/\D/', '', $cnpj);

        // Deve ter 14 dígitos
        if (strlen($cnpj) !== 14) {
            return false;
        }

        // CNPJ não pode ter todos os dígitos iguais
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        // Validar dígitos verificadores
        $digit1 = self::calculateCNPJDigit($cnpj, 0, 12, 5);
        $digit2 = self::calculateCNPJDigit($cnpj, 0, 13, 6);

        return intval($cnpj[12]) === $digit1 && intval($cnpj[13]) === $digit2;
    }

    /**
     * Calcula dígito verificador do CNPJ
     */
    private static function calculateCNPJDigit($cnpj, $start, $end, $mult_start)
    {
        $sum = 0;
        $multiplier = $mult_start;

        for ($i = $start; $i < $end; $i++) {
            $sum += intval($cnpj[$i]) * $multiplier;
            $multiplier--;
            if ($multiplier < 2) {
                $multiplier = 9;
            }
        }

        $remainder = $sum % 11;
        return $remainder < 2 ? 0 : 11 - $remainder;
    }

    /**
     * Valida todos os dados necessários para transmissão
     *
     * @param array $data Array com todos os dados para validar
     * @return array ['valid' => bool, 'errors' => []]
     */
    public static function validateAll($data)
    {
        $all_errors = array();

        // Validar NF-es
        $nfe_result = self::validateNFeKeys($data['nfe_keys'] ?? array());
        $all_errors = array_merge($all_errors, $nfe_result['errors']);

        // Validar Veículo
        $vehicle_result = self::validateVehicleData($data['vehicle'] ?? null);
        $all_errors = array_merge($all_errors, $vehicle_result['errors']);

        // Validar Motorista
        $driver_result = self::validateDriverData($data['driver'] ?? null);
        $all_errors = array_merge($all_errors, $driver_result['errors']);

        // Validar Exportador
        $exporter_result = self::validatePersonData($data['exporter'] ?? null, 'exporter');
        $all_errors = array_merge($all_errors, $exporter_result['errors']);

        // Validar Importador
        $importer_result = self::validatePersonData($data['importer'] ?? null, 'importer');
        $all_errors = array_merge($all_errors, $importer_result['errors']);

        return array(
            'valid' => empty($all_errors),
            'errors' => $all_errors
        );
    }
}
?>
