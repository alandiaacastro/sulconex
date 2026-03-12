<?php

namespace Adianti\Service\Security;

use Exception;
use stdClass;

/**
 * DigitalCertificateService
 * Gerenciador de certificados digitais X.509 (A1)
 *
 * Responsabilidades:
 * - Carregar certificado PKCS#12 (.pfx) com senha
 * - Extrair chave privada e certificado X.509
 * - Validar data de expiração
 * - Recuperar informações do certificado
 * - Assinar dados com a chave privada
 */
class DigitalCertificateService
{
    /**
     * Carrega um certificado PKCS#12 (.pfx) de um arquivo
     *
     * @param string $pfx_path Caminho completo do arquivo .pfx
     * @param string $password Senha do certificado
     * @return stdClass Objeto com propriedades: certificate, privateKey, info
     * @throws Exception Se houver erro ao carregar ou extrair
     */
    public static function loadFromFile($pfx_path, $password)
    {
        // Validar arquivo existe
        if (!file_exists($pfx_path)) {
            throw new Exception("Arquivo de certificado não encontrado: {$pfx_path}");
        }

        // Ler conteúdo do arquivo
        $pfx_data = file_get_contents($pfx_path);
        if ($pfx_data === false) {
            throw new Exception("Erro ao ler arquivo de certificado: {$pfx_path}");
        }

        // Extrair certificado e chave privada do PKCS#12
        $certs = array();
        $private_key = null;

        $result = openssl_pkcs12_read($pfx_data, $certs, $password);
        if (!$result) {
            throw new Exception("Erro ao extrair certificado PKCS#12. Verifique a senha.");
        }

        // Validar que extraiu corretamente
        if (!isset($certs['cert']) || !isset($certs['pkey'])) {
            throw new Exception("Estrutura inválida do certificado PKCS#12");
        }

        // Preparar resposta
        $certificate_obj = new stdClass();
        $certificate_obj->certificate = $certs['cert'];
        $certificate_obj->privateKey = $certs['pkey'];
        $certificate_obj->info = self::getCertificateInfo($certs['cert']);

        return $certificate_obj;
    }

    /**
     * Extrai informações do certificado X.509
     *
     * @param string $certificate Certificado em formato PEM
     * @return stdClass Objeto com: cn, o, c, serial, not_before, not_after, thumbprint
     * @throws Exception Se houver erro ao extrair informações
     */
    public static function getCertificateInfo($certificate)
    {
        try {
            // Parse do certificado
            $cert_data = openssl_x509_parse($certificate);
            if ($cert_data === false) {
                throw new Exception("Erro ao fazer parse do certificado");
            }

            $info = new stdClass();

            // Extrair informações do Subject
            if (isset($cert_data['subject'])) {
                $info->cn = $cert_data['subject']['CN'] ?? null;
                $info->o = $cert_data['subject']['O'] ?? null;
                $info->c = $cert_data['subject']['C'] ?? null;
            }

            // Serial do certificado
            $info->serial = isset($cert_data['serialNumber']) ?
                strtoupper(dechex($cert_data['serialNumber'])) : null;

            // Datas de validade
            $info->not_before = isset($cert_data['validFrom_time_t']) ?
                date('Y-m-d H:i:s', $cert_data['validFrom_time_t']) : null;
            $info->not_after = isset($cert_data['validTo_time_t']) ?
                date('Y-m-d H:i:s', $cert_data['validTo_time_t']) : null;

            // Calcular fingerprint (SHA1)
            $info->fingerprint_sha1 = openssl_x509_fingerprint($certificate, 'sha1');
            $info->fingerprint_sha256 = openssl_x509_fingerprint($certificate, 'sha256');

            // Issuer
            if (isset($cert_data['issuer'])) {
                $info->issuer_cn = $cert_data['issuer']['CN'] ?? null;
                $info->issuer_o = $cert_data['issuer']['O'] ?? null;
            }

            return $info;

        } catch (Exception $e) {
            throw new Exception("Erro ao extrair informações do certificado: " . $e->getMessage());
        }
    }

    /**
     * Valida se o certificado está dentro do período de validade
     *
     * @param string|stdClass $certificate Certificado em formato PEM ou objeto com .certificate
     * @return bool True se válido, False se expirado
     * @throws Exception Se houver erro na validação
     */
    public static function validateExpiration($certificate)
    {
        // Se for objeto, extrair propriedade certificate
        if (is_object($certificate)) {
            if (isset($certificate->certificate)) {
                $certificate = $certificate->certificate;
            } else {
                throw new Exception("Objeto de certificado inválido");
            }
        }

        try {
            $cert_data = openssl_x509_parse($certificate);
            if ($cert_data === false) {
                throw new Exception("Erro ao fazer parse do certificado");
            }

            $now = time();
            $valid_from = $cert_data['validFrom_time_t'] ?? null;
            $valid_to = $cert_data['validTo_time_t'] ?? null;

            if ($valid_from === null || $valid_to === null) {
                throw new Exception("Datas de validade não encontradas no certificado");
            }

            // Validar se está dentro do período
            if ($now < $valid_from) {
                return false; // Certificado ainda não é válido
            }

            if ($now > $valid_to) {
                return false; // Certificado expirado
            }

            return true; // Certificado válido

        } catch (Exception $e) {
            throw new Exception("Erro ao validar expiração: " . $e->getMessage());
        }
    }

    /**
     * Assina um conteúdo usando a chave privada do certificado
     *
     * @param string $data Dados a serem assinados
     * @param string|stdClass $certificate Certificado (PEM) ou objeto com .privateKey
     * @param string $algorithm Algoritmo (sha256, sha1, sha512)
     * @return string Assinatura em base64
     * @throws Exception Se houver erro na assinatura
     */
    public static function signData($data, $certificate, $algorithm = 'sha256')
    {
        // Se for objeto, extrair propriedade privateKey
        if (is_object($certificate)) {
            if (!isset($certificate->privateKey)) {
                throw new Exception("Objeto de certificado sem chave privada");
            }
            $private_key = $certificate->privateKey;
        } else {
            $private_key = $certificate;
        }

        try {
            $signature = '';

            // Mapear nomes de algoritmos
            $algo_map = array(
                'sha256' => OPENSSL_ALGO_SHA256,
                'sha1' => OPENSSL_ALGO_SHA1,
                'sha512' => OPENSSL_ALGO_SHA512,
                'md5' => OPENSSL_ALGO_MD5
            );

            $openssl_algo = $algo_map[$algorithm] ?? OPENSSL_ALGO_SHA256;

            // Criar assinatura
            $result = openssl_sign($data, $signature, $private_key, $openssl_algo);
            if (!$result) {
                throw new Exception("Erro ao criar assinatura digital");
            }

            // Retornar assinatura em base64
            return base64_encode($signature);

        } catch (Exception $e) {
            throw new Exception("Erro ao assinar dados: " . $e->getMessage());
        }
    }

    /**
     * Verifica uma assinatura usando o certificado público
     *
     * @param string $data Dados originais
     * @param string $signature Assinatura em base64
     * @param string|stdClass $certificate Certificado (PEM) ou objeto com .certificate
     * @param string $algorithm Algoritmo (sha256, sha1, sha512)
     * @return int 1 se válida, 0 se inválida, -1 se erro
     * @throws Exception Se houver erro na verificação
     */
    public static function verifySignature($data, $signature, $certificate, $algorithm = 'sha256')
    {
        // Se for objeto, extrair propriedade certificate
        if (is_object($certificate)) {
            if (!isset($certificate->certificate)) {
                throw new Exception("Objeto de certificado sem certificado público");
            }
            $certificate = $certificate->certificate;
        }

        try {
            // Decodificar assinatura de base64
            $signature_bin = base64_decode($signature);
            if ($signature_bin === false) {
                return -1; // Erro ao decodificar
            }

            // Mapear nomes de algoritmos
            $algo_map = array(
                'sha256' => OPENSSL_ALGO_SHA256,
                'sha1' => OPENSSL_ALGO_SHA1,
                'sha512' => OPENSSL_ALGO_SHA512,
                'md5' => OPENSSL_ALGO_MD5
            );

            $openssl_algo = $algo_map[$algorithm] ?? OPENSSL_ALGO_SHA256;

            // Verificar assinatura
            $result = openssl_verify($data, $signature_bin, $certificate, $openssl_algo);

            return $result; // 1 válida, 0 inválida, -1 erro

        } catch (Exception $e) {
            throw new Exception("Erro ao verificar assinatura: " . $e->getMessage());
        }
    }

    /**
     * Criptografa dados usando a chave pública do certificado
     *
     * @param string $data Dados a serem criptografados
     * @param string|stdClass $certificate Certificado ou objeto com .certificate
     * @return string Dados criptografados em base64
     * @throws Exception Se houver erro
     */
    public static function encryptData($data, $certificate)
    {
        // Se for objeto, extrair propriedade certificate
        if (is_object($certificate)) {
            if (!isset($certificate->certificate)) {
                throw new Exception("Objeto de certificado inválido");
            }
            $certificate = $certificate->certificate;
        }

        try {
            $encrypted = '';
            $result = openssl_public_encrypt($data, $encrypted, $certificate);
            if (!$result) {
                throw new Exception("Erro ao criptografar dados");
            }

            return base64_encode($encrypted);

        } catch (Exception $e) {
            throw new Exception("Erro ao criptografar: " . $e->getMessage());
        }
    }

    /**
     * Descriptografa dados usando a chave privada
     *
     * @param string $encrypted_data Dados criptografados em base64
     * @param string|stdClass $certificate Certificado ou objeto com .privateKey
     * @return string Dados descriptografados
     * @throws Exception Se houver erro
     */
    public static function decryptData($encrypted_data, $certificate)
    {
        // Se for objeto, extrair propriedade privateKey
        if (is_object($certificate)) {
            if (!isset($certificate->privateKey)) {
                throw new Exception("Objeto de certificado sem chave privada");
            }
            $private_key = $certificate->privateKey;
        } else {
            $private_key = $certificate;
        }

        try {
            $encrypted_bin = base64_decode($encrypted_data);
            if ($encrypted_bin === false) {
                throw new Exception("Erro ao decodificar dados criptografados");
            }

            $decrypted = '';
            $result = openssl_private_decrypt($encrypted_bin, $decrypted, $private_key);
            if (!$result) {
                throw new Exception("Erro ao descriptografar dados");
            }

            return $decrypted;

        } catch (Exception $e) {
            throw new Exception("Erro ao descriptografar: " . $e->getMessage());
        }
    }

    /**
     * Exporta o certificado em um arquivo temporário (útil para usar com cURL)
     *
     * @param string $certificate Certificado em formato PEM
     * @param string|null $temp_dir Diretório para arquivo temp (default: sys_get_temp_dir())
     * @return string Caminho do arquivo temporário
     * @throws Exception Se houver erro
     */
    public static function exportToTempFile($certificate, $temp_dir = null)
    {
        if ($temp_dir === null) {
            $temp_dir = sys_get_temp_dir();
        }

        try {
            $temp_file = tempnam($temp_dir, 'cert_');
            if ($temp_file === false) {
                throw new Exception("Erro ao criar arquivo temporário");
            }

            if (!file_put_contents($temp_file, $certificate)) {
                throw new Exception("Erro ao escrever certificado em arquivo temporário");
            }

            return $temp_file;

        } catch (Exception $e) {
            throw new Exception("Erro ao exportar certificado: " . $e->getMessage());
        }
    }

    /**
     * Remove arquivo temporário de certificado
     *
     * @param string $temp_file Caminho do arquivo temporário
     * @return bool True se removido, False caso contrário
     */
    public static function removeTempFile($temp_file)
    {
        if (file_exists($temp_file)) {
            return unlink($temp_file);
        }
        return false;
    }
}
?>
