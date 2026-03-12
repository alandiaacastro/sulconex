<?php


use Exception;
use stdClass;

/**
 * SiscomexTransmissionService
 * Serviço de transmissão de MIC/DTA para o Portal Único Siscomex
 *
 * Responsabilidades:
 * - Validar dados pré-envio
 * - Gerar XML conforme layout Receita Federal
 * - Assinar XML digitalmente com certificado A1
 * - Enviar via webservice SOAP
 * - Tratar respostas (protocolo, erros)
 * - Registrar status em banco de dados
 */
class SiscomexTransmissionService
{
    /**
     * Realiza transmissão completa de MIC/DTA
     *
     * @param int $conhecimento_id ID do conhecimento/CRT
     * @param array $items Array com chaves NF-e: [['chave' => '...', 'valor_frete' => 0], ...]
     * @param string|null $cert_password Senha do certificado (se não usar config)
     * @return stdClass Resultado: ['success' => bool, 'protocolo' => '...', 'erros' => []]
     */
    public static function transmitMicDta($conhecimento_id, $items, $cert_password = null)
    {
        $result = new stdClass();
        $result->success = false;
        $result->protocolo = null;
        $result->erros = array();

        try {
            // 1. Validar e carregar conhecimento
            $conhecimento = new Conhecimento($conhecimento_id);
            if (!$conhecimento->id) {
                throw new Exception("Conhecimento não encontrado: {$conhecimento_id}");
            }

            // 2. Carregar ou criar transmissão
            $transmissao = self::getOrCreateTransmission($conhecimento_id);

            // 3. Carregar certificado
            $config = (object) include 'app/config/cct.php';
            if (!$config->enabled) {
                throw new Exception("Integração CCT não está habilitada");
            }

            $cert_path = $config->certificate['path'] . $config->certificate['file'];
            $password = $cert_password ?? $config->certificate['password'];

            if (!file_exists($cert_path)) {
                throw new Exception("Certificado não encontrado em: {$cert_path}");
            }

            // 4. Validar certificado
            if ($config->validation['check_certificate_expiration']) {
                self::validateCertificateExpiration($cert_path, $password, $config);
            }

            // 5. Validar dados
            self::validateTransmissionData($conhecimento, $items);

            // 6. Gerar XML
            $xml = MicDtaXmlBuilder::buildMicDta($conhecimento_id, $items);

            // 7. Assinar XML
            $certificate = DigitalCertificateService::loadFromFile($cert_path, $password);
            $xml_assinado = self::signXML($xml, $certificate);

            // 8. Enviar para Siscomex
            $response = self::sendToSiscomex($xml_assinado, $config);

            // 9. Tratar resposta
            $response_parsed = self::parseSiscomexResponse($response);

            // 10. Registrar resultado
            if ($response_parsed->success) {
                $transmissao->markAsAccepted($response_parsed->protocolo);
                $result->success = true;
                $result->protocolo = $response_parsed->protocolo;
            } else {
                $transmissao->markAsRejected($response_parsed->erros);
                $result->erros = $response_parsed->erros;
            }

            // Persistir transmissão
            $transmissao->xml_enviado = substr($xml, 0, 65535); // Limitar tamanho
            $transmissao->resposta_siscomex = json_encode($response_parsed);
            $transmissao->store();

            // Log
            if ($config->logging['enabled']) {
                self::logTransmission($conhecimento_id, $transmissao->status, $response_parsed, $xml);
            }

            return $result;

        } catch (Exception $e) {
            // Log do erro
            $result->erros[] = "Erro ao transmitir: " . $e->getMessage();

            // Tentar registrar erro em banco de dados
            try {
                $transmissao = self::getOrCreateTransmission($conhecimento_id);
                $transmissao->status = 'erro';
                $transmissao->resposta_siscomex = json_encode(['erro' => $e->getMessage()]);
                $transmissao->store();
            } catch (Exception $db_e) {
                // Ignorar erro de persistência
            }

            return $result;
        }
    }

    /**
     * Obtém ou cria uma transmissão para um conhecimento
     */
    private static function getOrCreateTransmission($conhecimento_id)
    {
        // Tentar carregar existente
        try {
            $transmissoes = CctTransmissao::where('conhecimento_id', '=', $conhecimento_id)
                ->orderBy('id', 'desc')
                ->getObjects();

            if (!empty($transmissoes)) {
                return $transmissoes[0];
            }
        } catch (Exception $e) {
            // Continuar para criar nova
        }

        // Criar nova
        $transmissao = new CctTransmissao();
        $transmissao->conhecimento_id = $conhecimento_id;
        $transmissao->status = CctTransmissao::STATUS_PENDENTE;
        $transmissao->tentativas = 0;

        return $transmissao;
    }

    /**
     * Valida se o certificado não está expirado
     */
    private static function validateCertificateExpiration($cert_path, $password, $config)
    {
        try {
            $certificate = DigitalCertificateService::loadFromFile($cert_path, $password);

            if (!DigitalCertificateService::validateExpiration($certificate->certificate)) {
                throw new Exception("Certificado digital expirou");
            }

            // Validar dias mínimos de validade
            $min_days = $config->validation['min_days_valid'] ?? 30;
            if ($certificate->info->not_after) {
                $now = new \DateTime();
                $expiry = new \DateTime($certificate->info->not_after);
                $diff = $now->diff($expiry);

                if ($diff->days < $min_days) {
                    throw new Exception("Certificado vence em {$diff->days} dias (mínimo: {$min_days})");
                }
            }

        } catch (Exception $e) {
            throw new Exception("Erro ao validar certificado: " . $e->getMessage());
        }
    }

    /**
     * Valida dados da transmissão
     */
    private static function validateTransmissionData(Conhecimento $conhecimento, $items)
    {
        // Validar items (NF-es)
        if (empty($items) || !is_array($items)) {
            throw new Exception("Nenhuma NF-e informada para transmissão");
        }

        // Validar que conhecimento tem dados mínimos
        if (empty($conocimiento->numero)) {
            throw new Exception("Conhecimento sem número CRT");
        }

        // Validar NF-es
        foreach ($items as $index => $item) {
            if (empty($item['chave'])) {
                throw new Exception("NF-e {$index} sem chave de acesso");
            }

            // Validar formato da chave
            $chave = preg_replace('/\D/', '', $item['chave']);
            if (strlen($chave) !== 44) {
                throw new Exception("Chave NF-e {$index} inválida (tem " . strlen($chave) . " dígitos, esperado 44)");
            }
        }
    }

    /**
     * Assina o XML com a chave privada do certificado
     *
     * @param string $xml XML a assinar
     * @param object $certificate Certificado com chave privada
     * @return string XML assinado (pode ser envelope SOAP ou XML-DSig)
     */
    private static function signXML($xml, $certificate)
    {
        try {
            // Para Siscomex, normalmente é necessário assinar o conteúdo do XML
            // Vamos usar uma assinatura detached (assinatura separada)

            $signature = DigitalCertificateService::signData(
                $xml,
                $certificate,
                'sha256'
            );

            // Retornar XML com assinatura anexada
            // Estrutura simplificada - adaptar conforme layout exato do Siscomex
            $signed_xml = $xml . "\n<!-- Assinatura -->\n<assinatura>" . $signature . "</assinatura>";

            return $signed_xml;

        } catch (Exception $e) {
            throw new Exception("Erro ao assinar XML: " . $e->getMessage());
        }
    }

    /**
     * Envia XML assinado para Siscomex via SOAP
     *
     * @param string $xml_assinado
     * @param object $config
     * @return string Resposta SOAP bruta
     */
    private static function sendToSiscomex($xml_assinado, $config)
    {
        try {
            // Selecionar endpoint
            $environment = $config->environment ?? 'homolog';
            if (!isset($config->endpoints[$environment])) {
                throw new Exception("Ambiente não configurado: {$environment}");
            }

            $endpoint = $config->endpoints[$environment];

            // Construir envelope SOAP
            // Nota: Adaptar namespace e estrutura conforme especificação real do Siscomex
            $soap_request = self::buildSOAPEnvelope($xml_assinado);

            // Configurar requisição cURL
            $ch = curl_init($endpoint);

            if (!$ch) {
                throw new Exception("Erro ao inicializar cURL");
            }

            // Opções cURL
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            // Headers SOAP
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ""',
                'Connection: close'
            ));

            // Dados
            curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_request);

            // Timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, $config->request['timeout'] ?? 30);

            // SSL
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $config->request['ssl_verify'] ?? false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $config->request['ssl_verify'] ?? false);

            // User-Agent
            curl_setopt($ch, CURLOPT_USERAGENT, $config->request['user_agent'] ?? 'SulconexMIC/1.0');

            // Enviar requisição
            $response = curl_exec($ch);

            // Verificar erros
            if ($response === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new Exception("Erro cURL: {$error}");
            }

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Verificar código HTTP
            if ($http_code < 200 || $http_code >= 300) {
                throw new Exception("HTTP {$http_code}: Erro ao enviar para Siscomex");
            }

            return $response;

        } catch (Exception $e) {
            throw new Exception("Erro ao enviar para Siscomex: " . $e->getMessage());
        }
    }

    /**
     * Constrói envelope SOAP para envio
     *
     * @param string $xml_assinado
     * @return string Envelope SOAP
     */
    private static function buildSOAPEnvelope($xml_assinado)
    {
        // Template SOAP básico - adaptar conforme especificação do Siscomex
        $soap = <<<SOAP
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope
    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:cct="http://www.siscomex.gov.br/cct"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <soap:Body>
        <cct:enviarMicDta>
            <xmlManifesto>
{$xml_assinado}
            </xmlManifesto>
        </cct:enviarMicDta>
    </soap:Body>
</soap:Envelope>
SOAP;

        return $soap;
    }

    /**
     * Faz parsing da resposta SOAP do Siscomex
     *
     * @param string $response Resposta SOAP
     * @return stdClass ['success' => bool, 'protocolo' => '...', 'erros' => []]
     */
    private static function parseSiscomexResponse($response)
    {
        $result = new stdClass();
        $result->success = false;
        $result->protocolo = null;
        $result->erros = array();

        try {
            // Carregar XML da resposta
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadXML($response);
            libxml_clear_errors();

            $xpath = new \DOMXPath($dom);

            // Registrar namespaces
            $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xpath->registerNamespace('cct', 'http://www.siscomex.gov.br/cct');

            // Procurar por sucesso
            // Adaptar XPath conforme estrutura real da resposta
            $status_nodes = $xpath->query("//cct:statusMic | //statusMic | //*[contains(name(), 'status')]");

            foreach ($status_nodes as $node) {
                $status = trim($node->nodeValue);

                if (strtolower($status) === 'aceito' || strtolower($status) === 'approved' || $status === '002') {
                    $result->success = true;

                    // Procurar pelo protocolo
                    $proto_nodes = $xpath->query("//cct:protocolo | //protocolo | //*[contains(name(), 'protocolo')]");
                    if ($proto_nodes->length > 0) {
                        $result->protocolo = trim($proto_nodes->item(0)->nodeValue);
                    }
                    break;
                }
            }

            // Se não aceitou, procurar por erros
            if (!$result->success) {
                $error_nodes = $xpath->query("//cct:erro | //cct:mensagem | //erro | //mensagem | //*[contains(name(), 'erro')]");

                foreach ($error_nodes as $node) {
                    $erro = trim($node->nodeValue);
                    if (!empty($erro)) {
                        $result->erros[] = $erro;
                    }
                }

                // Se não encontrou erros, marcar como falha genérica
                if (empty($result->erros)) {
                    $result->erros[] = "Transmissão rejeitada pelo Siscomex";
                }
            }

            return $result;

        } catch (Exception $e) {
            $result->erros[] = "Erro ao processar resposta do Siscomex: " . $e->getMessage();
            return $result;
        }
    }

    /**
     * Registra transmissão em log
     */
    private static function logTransmission($conhecimento_id, $status, $response_parsed, $xml)
    {
        try {
            $config = (object) include 'app/config/cct.php';

            // Usar SystemRequestLogService se disponível
            if (class_exists('\Adianti\Service\Log\SystemRequestLogService')) {
                $request_data = array(
                    'conhecimento_id' => $conhecimento_id,
                    'xml_tamanho' => strlen($xml),
                    'xml' => $config->logging['include_xml'] ? substr($xml, 0, 10000) : null
                );

                $response_data = array(
                    'status' => $status,
                    'protocolo' => $response_parsed->protocolo,
                    'erros' => $response_parsed->erros,
                    'resposta_completa' => $config->logging['include_response'] ?
                        json_encode($response_parsed) : null
                );

                \Adianti\Service\Log\SystemRequestLogService::log(
                    'SiscomexTransmissionService',
                    'transmitMicDta',
                    $request_data,
                    $response_data,
                    0
                );
            }

        } catch (Exception $e) {
            // Ignorar erros de logging
        }
    }

    /**
     * Retenta envio de transmissão rejeitada
     *
     * @param int $transmissao_id ID da transmissão
     * @return stdClass Resultado da retentativa
     */
    public static function retryTransmission($transmissao_id)
    {
        $result = new stdClass();
        $result->success = false;
        $result->erros = array();

        try {
            $transmissao = new CctTransmissao($transmissao_id);
            if (!$transmissao->id) {
                throw new Exception("Transmissão não encontrada");
            }

            // Verificar se pode retentar
            if (!$transmissao->canRetry()) {
                throw new Exception("Número máximo de tentativas atingido");
            }

            // Carregar items
            $items = array();
            foreach ($transmissao->get_items() as $item) {
                $items[] = array(
                    'chave' => $item->chave_acesso_nfe,
                    'valor_frete' => $item->valor_frete
                );
            }

            // Reenviar
            return self::transmitMicDta($transmissao->conhecimento_id, $items);

        } catch (Exception $e) {
            $result->erros[] = "Erro ao retentar transmissão: " . $e->getMessage();
            return $result;
        }
    }
}
?>
