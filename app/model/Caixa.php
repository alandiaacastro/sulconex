<?php
/**
 * Caixa Active Record
 * LanÃ§amentos financeiros: entradas, saÃ­das, contas a receber (faturas) e a pagar (contratos)
 */
class Caixa extends TRecord
{
    const TABLENAME = 'caixa';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'serial';

    private $faturakey;
    private $contratokey;

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('data_lancamento');
        parent::addAttribute('descricao');
        parent::addAttribute('tipo');           // ENTRADA | SAIDA
        parent::addAttribute('valor');
        parent::addAttribute('categoria');      // FATURA | CONTRATO | EXTRATO | MANUAL
        parent::addAttribute('referencia_id');
        parent::addAttribute('referencia_tipo'); // fatura | contrato
        parent::addAttribute('status');         // PENDENTE | CONCILIADO
        parent::addAttribute('ofx_fitid');
        parent::addAttribute('observacao');
        parent::addAttribute('tipo_baixa');
        parent::addAttribute('desconto_banco');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public function onBeforeSave($object)
    {
        $now = date('Y-m-d H:i:s');
        if (empty($object->id)) {
            $object->created_at = $now;
        }
        $object->updated_at = $now;
    }

    public function get_faturakey()
    {
        if (empty($this->faturakey) && !empty($this->referencia_id) && $this->referencia_tipo === 'fatura') {
            $this->faturakey = new Fatura($this->referencia_id);
        }
        return $this->faturakey;
    }

    public function get_contratokey()
    {
        if (empty($this->contratokey) && !empty($this->referencia_id) && $this->referencia_tipo === 'contrato') {
            $this->contratokey = new Contrato($this->referencia_id);
        }
        return $this->contratokey;
    }

    /**
     * Cria a tabela caixa se nÃ£o existir
     */
    public static function createTableIfNotExists()
    {
        TTransaction::open('sample');
        $conn = TTransaction::get();

        // Migrate old 'data' column to 'data_lancamento' if needed
        $cols = $conn->query("PRAGMA table_info(caixa)")->fetchAll(\PDO::FETCH_COLUMN, 1);
        if (!empty($cols) && in_array('data', $cols) && !in_array('data_lancamento', $cols)) {
            $conn->exec("ALTER TABLE caixa RENAME COLUMN data TO data_lancamento");
        }

        $conn->exec("
            CREATE TABLE IF NOT EXISTS caixa (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                data_lancamento TEXT NOT NULL,
                descricao TEXT NOT NULL,
                tipo TEXT NOT NULL,
                valor REAL NOT NULL DEFAULT 0,
                categoria TEXT,
                referencia_id INTEGER,
                referencia_tipo TEXT,
                status TEXT DEFAULT 'PENDENTE',
                ofx_fitid TEXT,
                observacao TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ");

        // Ensure columns for old databases created before recent fields
        $cols = $conn->query("PRAGMA table_info(caixa)")->fetchAll(\PDO::FETCH_COLUMN, 1);
        $missingColumns = [
            'categoria'       => "ALTER TABLE caixa ADD COLUMN categoria TEXT",
            'referencia_id'   => "ALTER TABLE caixa ADD COLUMN referencia_id INTEGER",
            'referencia_tipo' => "ALTER TABLE caixa ADD COLUMN referencia_tipo TEXT",
            'status'          => "ALTER TABLE caixa ADD COLUMN status TEXT DEFAULT 'PENDENTE'",
            'ofx_fitid'       => "ALTER TABLE caixa ADD COLUMN ofx_fitid TEXT",
            'observacao'      => "ALTER TABLE caixa ADD COLUMN observacao TEXT",
            'tipo_baixa'      => "ALTER TABLE caixa ADD COLUMN tipo_baixa TEXT",
            'desconto_banco'  => "ALTER TABLE caixa ADD COLUMN desconto_banco REAL DEFAULT 0",
            'created_at'      => "ALTER TABLE caixa ADD COLUMN created_at TEXT",
            'updated_at'      => "ALTER TABLE caixa ADD COLUMN updated_at TEXT"
        ];

        foreach ($missingColumns as $column => $sql) {
            if (!in_array($column, $cols, true)) {
                $conn->exec($sql);
            }
        }

        TTransaction::close();
    }
}

