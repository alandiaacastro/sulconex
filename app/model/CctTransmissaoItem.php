<?php



/**
 * CctTransmissaoItem
 * Modelo de persistência para items (NF-es) de uma transmissão MIC/DTA
 *
 * @property int $id
 * @property int $cct_transmissao_id
 * @property string $chave_acesso_nfe (44 dígitos)
 * @property float $valor_frete
 * @property int $ordem
 * @property \DateTime $created_at
 */
class CctTransmissaoItem extends TRecord
{
    const TABLENAME = 'cct_transmissao_items';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'serial';

    public function __construct($id = null)
    {
        parent::__construct($id);

        // Definir atributos
        $this->addAttribute('cct_transmissao_id');
        $this->addAttribute('chave_acesso_nfe');
        $this->addAttribute('valor_frete');
        $this->addAttribute('ordem');
        $this->addAttribute('created_at');
    }

    /**
     * Carrega o item
     */
    public function onLoad($data)
    {
        $this->created_at = isset($data['created_at']) ?
            new \DateTime($data['created_at']) : null;
    }

    /**
     * Retorna a transmissão pai
     */
    public function get_transmissao()
    {
        return new CctTransmissao($this->cct_transmissao_id);
    }

    /**
     * Valida o formato da chave NF-e (deve ter 44 dígitos)
     *
     * @return bool
     */
    public function validateNFeKey()
    {
        return preg_match('/^\d{44}$/', $this->chave_acesso_nfe) === 1;
    }

    /**
     * Calcula o dígito verificador da chave NF-e (RFC padrão)
     * Esta é uma validação adicional
     *
     * @param string $chave Chave a validar
     * @return bool True se válida
     */
    public static function validateNFeKeyChecksum($chave)
    {
        if (!preg_match('/^\d{44}$/', $chave)) {
            return false;
        }

        // Extrair dígito verificador
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
     * Retorna array com dados do item
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'chave_nfe' => $this->chave_acesso_nfe,
            'valor_frete' => number_format($this->valor_frete, 2, ',', '.'),
            'ordem' => $this->ordem,
        ];
    }
}
?>
