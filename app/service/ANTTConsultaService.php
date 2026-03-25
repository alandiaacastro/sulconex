<?php
/**
 * Serviço de proxy para consulta ANTT
 * @author Claude
 */
class ANTTConsultaService
{
    /**
     * URL base da consulta ANTT
     */
    const URL_BASE = 'https://appweb1.antt.gov.br/scff/conPlaca.asp';
    
    /**
     * URL para fazer a consulta de placa
     */
    const URL_CONSULTA = 'https://appweb1.antt.gov.br/scff/Servlet/ConPlaca';
    
    /**
     * Carrega a página inicial da consulta ANTT
     */
    public static function carregarPagina()
    {
        try {
            // Configuração do cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::URL_BASE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            
            // Executar a requisição
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Fechar a conexão cURL
            curl_close($ch);
            
            // Verificar se a requisição foi bem-sucedida
            if ($httpCode !== 200) {
                throw new Exception("Erro ao acessar o site da ANTT. Código HTTP: $httpCode");
            }
            
            // Devolver o HTML da página
            echo $response;
        } catch (Exception $e) {
            echo "<!DOCTYPE html><html><body><div class='erro'>Erro: " . $e->getMessage() . "</div></body></html>";
        }
    }
    
    /**
     * Consulta uma placa no sistema da ANTT
     * @param $param Parâmetros da requisição
     */
    public static function consultarPlaca($param)
    {
        try {
            // Obter a placa a ser consultada
            if (empty($param['placa'])) {
                throw new Exception("Placa não informada");
            }
            
            $placa = trim($param['placa']);
            
            // Validar o formato da placa (básico)
            if (!preg_match('/^[A-Za-z0-9-]{7,8}$/', $placa)) {
                throw new Exception("Formato de placa inválido");
            }
            
            // Preparar os dados para enviar ao site da ANTT
            $postFields = [
                'placa' => $placa
            ];
            
            // Configuração do cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::URL_CONSULTA);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            
            // Executar a requisição
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Fechar a conexão cURL
            curl_close($ch);
            
            // Verificar se a requisição foi bem-sucedida
            if ($httpCode !== 200) {
                throw new Exception("Erro ao consultar placa. Código HTTP: $httpCode");
            }
            
            // Processar a resposta do site da ANTT
            return self::processarResposta($response, $placa);
        } catch (Exception $e) {
            // Retornar o erro em formato JSON
            return json_encode([
                'success' => false,
                'mensagem' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Processa a resposta da consulta e extrai os dados
     * @param string $html HTML da resposta
     * @param string $placa Placa consultada
     * @return string JSON com os dados extraídos
     */
    private static function processarResposta($html, $placa)
    {
        // Criar um objeto DOMDocument para manipular o HTML
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);
        
        // Inicializar array de dados
        $dados = ['placa' => $placa];
        
        // Verificar se temos um resultado válido
        if (strpos($html, 'RNTRC') === false) {
            // Verificar mensagens de erro específicas da ANTT
            $erros = $xpath->query("//font[@color='red']");
            $msgErro = "Nenhum resultado encontrado para a placa informada";
            
            foreach ($erros as $erro) {
                $msgErro = trim($erro->textContent);
                break;
            }
            
            return json_encode([
                'success' => false,
                'mensagem' => $msgErro
            ]);
        }
        
        // === Extrair dados da tabela de informações ===
        $tabelas = $xpath->query("//table[@class='texto']");
        if ($tabelas->length == 0) {
            $tabelas = $xpath->query("//table");
        }
        
        // Processar as tabelas encontradas
        foreach ($tabelas as $tabela) {
            $linhas = $xpath->query(".//tr", $tabela);
            
            foreach ($linhas as $linha) {
                $celulas = $xpath->query(".//td", $linha);
                
                if ($celulas->length >= 2) {
                    $chave = trim($celulas->item(0)->textContent);
                    $valor = trim($celulas->item(1)->textContent);
                    
                    if (strpos($chave, ':') !== false || 
                        preg_match('/(rntrc|cpf|cnpj|transportador|situacao|validade)/i', $chave)) {
                        
                        $chave_limpa = self::normalizarChave($chave);
                        
                        if (!empty($chave_limpa)) {
                            $dados[$chave_limpa] = $valor;
                        }
                    }
                }
            }
        }
        
        // Utilizar métodos alternativos se necessário
        if (count($dados) <= 1) { // Se só temos a placa
            // Extrair por expressões regulares
            $padroes = [
                'rntrc' => '/RNTRC:?\s*([0-9]+)/i',
                'cpf_cnpj' => '/(?:CPF|CNPJ):?\s*([0-9.\/\-]+)/i',
                'situacao' => '/Situa[cç][aã]o:?\s*([^\r\n<]+)/i',
                'validade' => '/Validade:?\s*([0-9\/]+)/i',
                'tipo_transportador' => '/Tipo\s+de\s+Transportador:?\s*([^\r\n<]+)/i',
                'nome_razao_social' => '/(?:Nome|Raz[aã]o\s+Social):?\s*([^\r\n<]+)/i'
            ];
            
            foreach ($padroes as $chave => $padrao) {
                if (preg_match($padrao, $html, $matches) && isset($matches[1])) {
                    $dados[$chave] = trim($matches[1]);
                }
            }
        }
        
        // Verificar se conseguimos extrair dados
        if (count($dados) <= 1) {
            return json_encode([
                'success' => false,
                'mensagem' => "Não foi possível extrair dados da consulta."
            ]);
        }
        
        // Adicionar timestamp da captura
        $dados['data_captura'] = date('d/m/Y H:i:s');
        
        // Retornar os dados em formato JSON
        return json_encode([
            'success' => true,
            'dados' => $dados
        ]);
    }
    
    /**
     * Normaliza a chave para ser usada como índice no array
     * @param string $chave Texto original da chave
     * @return string Chave normalizada
     */
    private static function normalizarChave($chave) 
    {
        // Remover dois-pontos, pontos e outros caracteres especiais
        $limpa = preg_replace('/[:.()\[\]]/', '', $chave);
        // Converter para lowercase
        $limpa = strtolower($limpa);
        // Substituir espaços por underscores
        $limpa = preg_replace('/\s+/', '_', $limpa);
        // Remover caracteres não alfanuméricos ou underscore
        $limpa = preg_replace('/[^a-z0-9_]/', '', $limpa);
        
        return $limpa;
    }
}