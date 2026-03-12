<?php

/**
 * Configuração CCT (Controle de Carga e Trânsito)
 * Siscomex MIC/DTA - Transmissão de Manifestos Internacionais de Carga
 */

return [
    // Habilitação da integração CCT
    'enabled' => getenv('CCT_ENABLED') ?? false,

    // Ambiente: 'homolog' (testes) ou 'prod' (produção)
    'environment' => getenv('CCT_ENV') ?? 'homolog',

    // URLs dos endpoints Siscomex
    'endpoints' => [
        'homolog' => 'https://hom1.cct.siscomex.gov.br/cctsoa/CCTDistribuidorService',
        'prod' => 'https://cct.siscomex.gov.br/cctsoa/CCTDistribuidorService',
    ],

    // Configuração de certificado digital
    'certificate' => [
        'path' => getenv('CCT_CERT_PATH') ?? 'app/certificates/cct/',
        'file' => getenv('CCT_CERT_FILE') ?? 'cct_certificate.pfx',
        // Senha do certificado (será criptografada em produção)
        'password' => getenv('CCT_CERT_PASSWORD') ?? null,
        // Usar password criptografada?
        'password_encrypted' => getenv('CCT_CERT_PASSWORD_ENCRYPTED') ?? false,
    ],

    // Configuração de requisições HTTP
    'request' => [
        // Timeout em segundos
        'timeout' => 30,
        // Verificar SSL em homologação?
        'ssl_verify' => getenv('CCT_SSL_VERIFY') ?? false,
        // User-Agent para requisições
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SulconexMIC/1.0',
    ],

    // Configuração de retry
    'retry' => [
        // Número máximo de tentativas
        'max_attempts' => 3,
        // Intervalo entre tentativas em minutos
        'interval_minutes' => 5,
    ],

    // Logging
    'logging' => [
        // Habilitar logging de requisições
        'enabled' => true,
        // Incluir XML completo no log (true/false)
        'include_xml' => true,
        // Incluir resposta completa do Siscomex (true/false)
        'include_response' => true,
    ],

    // Validações obrigatórias
    'validation' => [
        // Validar expiração do certificado?
        'check_certificate_expiration' => true,
        // Certificado deve estar válido por no mínimo X dias
        'min_days_valid' => 30,
        // Validar formato das chaves NF-e?
        'validate_nfe_keys' => true,
        // Validar formato de placa de veículo?
        'validate_plate' => true,
    ],

    // Configuração de status MIC/DTA
    'status' => [
        'não_iniciado' => 'Transmissão não iniciada',
        'pendente' => 'Pendente envio',
        'enviado' => 'Enviado para Siscomex',
        'aceito' => 'Aceito pelo Siscomex',
        'rejeitado' => 'Rejeitado pelo Siscomex',
        'cancelado' => 'Transmissão cancelada',
        'erro' => 'Erro na transmissão',
    ],

    // Mapeamento de status CRT → status MIC
    // Define qual status no Sulconex81 corresponde a qual status no Siscomex
    'status_mapping' => [
        // Status origem => Status MIC
        'novo' => '001', // Manifesto em elaboração
        'aprovado' => '002', // Manifesto aprovado
        'enviado' => '003', // Manifesto transmitido
        'entregue' => '004', // Carga entregue
        'devolvido' => '999', // Carga devolvida/cancelada
    ],

];
?>
