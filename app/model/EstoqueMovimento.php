<?php

use Adianti\Database\TRecord;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Widget\Form\TDate;

/**
 * EstoqueMovimento
 * Movimentação de carga (entrada/saída) vinculada ao EstoqueManifesto
 *
 * Colunas originais: id, manifesto_id, tipo, peso_kg, bobinas, data_movimento, observacao, created_at
 * Colunas expandidas: motorista_nome, veiculo_cavalo, veiculo_carreta, numero_ordem,
 *                     tipo_carga, xml_nfe, chave_nfe, danfe, valor_total,
 *                     data_emissao, status, updated_at, fornecedor_cnpj, fornecedor_nome
 */
class EstoqueMovimento extends TRecord
{
    const TABLENAME  = 'estoque_movimento';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    const TIPO_ENTRADA = 'entrada';
    const TIPO_SAIDA   = 'saida';

    const STATUS_PENDENTE   = 'pendente';
    const STATUS_CONFIRMADO = 'confirmado';
    const STATUS_CANCELADO  = 'cancelado';

    private $manifesto_obj;

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('manifesto_id');
        parent::addAttribute('tipo');
        parent::addAttribute('peso_kg');
        parent::addAttribute('bobinas');
        parent::addAttribute('data_movimento');
        parent::addAttribute('data_saida');
        parent::addAttribute('observacao');
        parent::addAttribute('created_at');
        parent::addAttribute('motorista_nome');
        parent::addAttribute('veiculo_cavalo');
        parent::addAttribute('veiculo_carreta');
        parent::addAttribute('motorista_saida_nome');
        parent::addAttribute('veiculo_saida_cavalo');
        parent::addAttribute('veiculo_saida_carreta');
        parent::addAttribute('numero_ordem');
        parent::addAttribute('tipo_carga');
        parent::addAttribute('xml_nfe');
        parent::addAttribute('chave_nfe');
        parent::addAttribute('danfe');
        parent::addAttribute('valor_total');
        parent::addAttribute('data_emissao');
        parent::addAttribute('status');
        parent::addAttribute('updated_at');
        parent::addAttribute('fornecedor_cnpj');
        parent::addAttribute('fornecedor_nome');
        parent::addAttribute('tipo_volume');
        parent::addAttribute('quantidade');
        parent::addAttribute('peso_bruto_kg');
        parent::addAttribute('peso_liquido_kg');
    }

    public function get_manifesto()
    {
        if (empty($this->manifesto_id)) return null;
        if (!$this->manifesto_obj) {
            $this->manifesto_obj = new EstoqueManifesto($this->manifesto_id);
        }
        return $this->manifesto_obj;
    }

    public function get_importador()
    {
        $manifesto = $this->get_manifesto();
        if (!$manifesto || empty($manifesto->importador_id)) return null;
        return new Clientes($manifesto->importador_id);
    }

    public function get_exportador()
    {
        $manifesto = $this->get_manifesto();
        if (!$manifesto || empty($manifesto->exportador_id)) return null;
        return new Clientes($manifesto->exportador_id);
    }

    /**
     * Retorna DANFEs do manifesto vinculado
     */
    public function get_danfes_lista()
    {
        if (!empty($this->danfe)) return $this->danfe;
        $manifesto = $this->get_manifesto();
        if (!$manifesto) return '—';

        $criteria = new TCriteria();
        $criteria->add(new TFilter('manifesto_id', '=', $manifesto->id));
        $danfes = EstoqueManifestoDanfe::getObjects($criteria);
        if (!$danfes) return '—';
        return implode(' / ', array_map(fn($d) => $d->danfe_codigo, $danfes));
    }

    public function get_tipo_label()
    {
        return $this->tipo === self::TIPO_ENTRADA
            ? '<span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i>Entrada</span>'
            : '<span class="badge bg-warning text-dark"><i class="fas fa-arrow-up me-1"></i>Saída</span>';
    }

    public function get_status_label()
    {
        $map = [
            self::STATUS_PENDENTE   => '<span class="badge bg-secondary">Pendente</span>',
            self::STATUS_CONFIRMADO => '<span class="badge bg-success">Confirmado</span>',
            self::STATUS_CANCELADO  => '<span class="badge bg-danger">Cancelado</span>',
        ];
        return $map[$this->status] ?? '<span class="badge bg-light text-dark">' . ($this->status ?? '') . '</span>';
    }

    public function get_data_formatada()
    {
        if (empty($this->data_movimento)) return '';
        return TDate::convertToMask($this->data_movimento, 'yyyy-mm-dd', 'dd/mm/yyyy');
    }

    public function get_peso_formatado()
    {
        return number_format((float)($this->peso_kg ?? 0), 3, '.', ',');
    }

    public function onBeforeDelete($object)
    {
        return true;
    }
}
