<?php

use Adianti\Database\TRecord;

class Contrato extends TRecord
{
    const TABLENAME  = 'contrato';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    private $veiculo;
    private $permisso;
    private static $schemaChecked = false;

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        self::ensureSchemaIfPossible();
        parent::__construct($id, $callObjectLoad);
        
        parent::addAttribute('veiculo_id');
        parent::addAttribute('conhecimento_numero');
        parent::addAttribute('permisso_id');
        parent::addAttribute('danfeoumic');
        parent::addAttribute('emissao');
        parent::addAttribute('frete_id');
        parent::addAttribute('origem1');
        parent::addAttribute('destino1');
        parent::addAttribute('frete1');
        parent::addAttribute('adt1');
        parent::addAttribute('pis_motorista');
        parent::addAttribute('inss1');
        parent::addAttribute('irrf1');
        parent::addAttribute('sest1');
        parent::addAttribute('pis1');
        parent::addAttribute('cofins1');
        parent::addAttribute('descontos1');
        parent::addAttribute('saldo1');
        parent::addAttribute('pagamento');
        parent::addAttribute('observacoes');
        parent::addAttribute('extenso1');
        parent::addAttribute('forma');
        parent::addAttribute('vencimento');
        parent::addAttribute('dta_efet_pg');
        parent::addAttribute('pago');
        parent::addAttribute('frete_tonelada');
        parent::addAttribute('peso_tonelada');
        parent::addAttribute('valor_por_ton');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public static function addColumnsIfNotExists($connection = null)
    {
        if (self::$schemaChecked) {
            return;
        }

        $openedTransaction = false;
        if ($connection === null) {
            TTransaction::open('sample');
            $connection = TTransaction::get();
            $openedTransaction = true;
        }

        try {
            $cols = $connection->query("PRAGMA table_info(contrato)")->fetchAll(\PDO::FETCH_COLUMN, 1);
            if (!in_array('pis1', $cols, true)) {
                $connection->exec("ALTER TABLE contrato ADD COLUMN pis1 REAL DEFAULT 0");
            }
            if (!in_array('cofins1', $cols, true)) {
                $connection->exec("ALTER TABLE contrato ADD COLUMN cofins1 REAL DEFAULT 0");
            }
            if (!in_array('pis_motorista', $cols, true)) {
                $connection->exec("ALTER TABLE contrato ADD COLUMN pis_motorista TEXT DEFAULT NULL");
            }
            if (!in_array('frete_id', $cols, true)) {
                $connection->exec("ALTER TABLE contrato ADD COLUMN frete_id INTEGER");
            }
            if (!in_array('frete_tonelada', $cols, true)) {
                $connection->exec("ALTER TABLE contrato ADD COLUMN frete_tonelada TEXT DEFAULT 'N'");
            }
            if (!in_array('peso_tonelada', $cols, true)) {
                $connection->exec("ALTER TABLE contrato ADD COLUMN peso_tonelada REAL DEFAULT 0");
            }
            if (!in_array('valor_por_ton', $cols, true)) {
                $connection->exec("ALTER TABLE contrato ADD COLUMN valor_por_ton REAL DEFAULT 0");
            }

            self::$schemaChecked = true;

            if ($openedTransaction) {
                TTransaction::close();
            }
        } catch (Exception $e) {
            if ($openedTransaction) {
                TTransaction::rollback();
            }
            throw $e;
        }
    }

    private static function ensureSchemaIfPossible(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        try {
            $connection = TTransaction::get();
            if ($connection) {
                self::addColumnsIfNotExists($connection);
            }
        } catch (Exception $e) {
            // schema will be ensured by callers that explicitly open a transaction
        }
    }

    public function get_veiculo()
    {
        if (empty($this->veiculo) && !empty($this->veiculo_id)) {
            $this->veiculo = new Veiculo($this->veiculo_id);
        }
        return $this->veiculo;
    }
    
    public function get_permisso()
    {
        if (empty($this->permisso) && !empty($this->permisso_id)) {
            $this->permisso = new Permisso($this->permisso_id);
        }
        return $this->permisso;
    }

    public function get_tabela_frete()
    {
        if (!empty($this->frete_id)) {
            return new TabelaFrete($this->frete_id);
        }
        return null;
    }
}
