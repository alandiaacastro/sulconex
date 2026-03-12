<?php

namespace Adianti\Model;

use Adianti\Database\TRecord;

/**
 * CctTransmissao
 * Modelo de persistência para transmissões MIC/DTA ao Portal Único Siscomex
 *
 * @property int $id
 * @property int $conhecimento_id
 * @property string $status (pendente, enviado, aceito, rejeitado, cancelado)
 * @property \DateTime $data_transmissao
 * @property string $protocolo_siscomex
 * @property string $xml_enviado (backup do XML transmitido)
 * @property string $resposta_siscomex (resposta JSON do servidor)
 * @property int $tentativas
 * @property \DateTime $proxima_tentativa
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class CctTransmissao extends TRecord
{
    const TABLENAME = 'cct_transmissao';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'serial';

    // Status possiveis
    const STATUS_PENDENTE = 'pendente';
    const STATUS_ENVIADO = 'enviado';
    const STATUS_ACEITO = 'aceito';
    const STATUS_REJEITADO = 'rejeitado';
    const STATUS_CANCELADO = 'cancelado';

    public function __construct($id = null)
    {
        parent::__construct($id);

        // Definir atributos
        $this->addAttribute('conhecimento_id');
        $this->addAttribute('status');
        $this->addAttribute('data_transmissao');
        $this->addAttribute('protocolo_siscomex');
        $this->addAttribute('xml_enviado');
        $this->addAttribute('resposta_siscomex');
        $this->addAttribute('tentativas');
        $this->addAttribute('proxima_tentativa');
        $this->addAttribute('created_at');
        $this->addAttribute('updated_at');
    }

    /**
     * Carrega a transmissão
     */
    public function onLoad($data)
    {
        $this->data_transmissao = isset($data['data_transmissao']) ?
            new \DateTime($data['data_transmissao']) : null;

        $this->proxima_tentativa = isset($data['proxima_tentativa']) ?
            new \DateTime($data['proxima_tentativa']) : null;

        $this->created_at = isset($data['created_at']) ?
            new \DateTime($data['created_at']) : null;

        $this->updated_at = isset($data['updated_at']) ?
            new \DateTime($data['updated_at']) : null;
    }

    /**
     * Retorna o Conhecimento (CRT) relacionado
     */
    public function get_conhecimento()
    {
        return new Conhecimento($this->conhecimento_id);
    }

    /**
     * Retorna os items (NF-es) da transmissão
     *
     * @return array Array de CctTransmissaoItem
     */
    public function get_items()
    {
        return CctTransmissaoItem::where('cct_transmissao_id', '=', $this->id)
            ->orderBy('ordem', 'asc')
            ->getObjects();
    }

    /**
     * Verifica se a transmissão pode ser reenviada
     *
     * @return bool
     */
    public function canRetry()
    {
        $config = (object) include 'app/config/cct.php';
        $max_attempts = $config->retry['max_attempts'] ?? 3;

        return $this->tentativas < $max_attempts;
    }

    /**
     * Verifica se o certificado ainda está válido para esta transmissão
     *
     * @return bool
     */
    public function isCertificateValid()
    {
        try {
            $config = (object) include 'app/config/cct.php';
            $cert_path = $config->certificate['path'];
            $cert_file = $config->certificate['file'];
            $cert_password = $config->certificate['password'];

            if (!file_exists($cert_path . $cert_file)) {
                return false;
            }

            $certificate = \Adianti\Service\Security\DigitalCertificateService::loadFromFile(
                $cert_path . $cert_file,
                $cert_password
            );

            return \Adianti\Service\Security\DigitalCertificateService::validateExpiration(
                $certificate->certificate
            );

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Marca como enviado
     */
    public function markAsSent()
    {
        $this->status = self::STATUS_ENVIADO;
        $this->data_transmissao = new \DateTime();
        $this->updated_at = new \DateTime();
        $this->tentativas += 1;
    }

    /**
     * Marca como aceito
     *
     * @param string $protocolo Protocolo retornado pelo Siscomex
     */
    public function markAsAccepted($protocolo)
    {
        $this->status = self::STATUS_ACEITO;
        $this->protocolo_siscomex = $protocolo;
        $this->updated_at = new \DateTime();

        // Atualizar status no Conhecimento também
        try {
            $conhecimento = new Conhecimento($this->conhecimento_id);
            $conhecimento->status_transmissao_mic = 'aceito';
            $conhecimento->protocolo_siscomex = $protocolo;
            $conhecimento->store();
        } catch (\Exception $e) {
            // Log do erro mas não falhar
        }
    }

    /**
     * Marca como rejeitado
     *
     * @param array|string $erros Erros retornados pelo Siscomex
     */
    public function markAsRejected($erros)
    {
        $this->status = self::STATUS_REJEITADO;
        $this->resposta_siscomex = is_array($erros) ? json_encode($erros) : $erros;
        $this->updated_at = new \DateTime();

        // Agendarianumar para próxima tentativa se ainda há tentativas
        if ($this->canRetry()) {
            $config = (object) include 'app/config/cct.php';
            $interval = $config->retry['interval_minutes'] ?? 5;
            $proxima = new \DateTime();
            $proxima->add(new \DateInterval("PT{$interval}M"));
            $this->proxima_tentativa = $proxima;
        }

        // Atualizar status no Conhecimento também
        try {
            $conhecimento = new Conhecimento($this->conhecimento_id);
            $conhecimento->status_transmissao_mic = 'rejeitado';
            $conhecimento->store();
        } catch (\Exception $e) {
            // Log do erro mas não falhar
        }
    }

    /**
     * Marca como cancelado
     */
    public function markAsCanceled()
    {
        $this->status = self::STATUS_CANCELADO;
        $this->updated_at = new \DateTime();
    }

    /**
     * Retorna array com resumo da transmissão
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'conhecimento_id' => $this->conhecimento_id,
            'status' => $this->status,
            'data_transmissao' => $this->data_transmissao instanceof \DateTime ?
                $this->data_transmissao->format('Y-m-d H:i:s') : null,
            'protocolo' => $this->protocolo_siscomex,
            'tentativas' => $this->tentativas,
            'created_at' => $this->created_at instanceof \DateTime ?
                $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
?>
