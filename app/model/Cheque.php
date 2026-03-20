<?php
class Cheque extends TRecord
{
    const TABLENAME  = 'cheque';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('numero_cheque');
        parent::addAttribute('banco');
        parent::addAttribute('recebedor');
        parent::addAttribute('valor');
        parent::addAttribute('data_emissao');
        parent::addAttribute('data_vencimento');
        parent::addAttribute('data_compensacao');
        parent::addAttribute('status');
        parent::addAttribute('observacao');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    /**
     * Cria a tabela se nao existir e adiciona colunas faltantes
     */
    public static function createTableIfNotExists()
    {
        $conn = TTransaction::get();

        $conn->exec("
            CREATE TABLE IF NOT EXISTS cheque (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                numero_cheque    TEXT NOT NULL,
                banco            TEXT,
                recebedor        TEXT NOT NULL,
                valor            REAL NOT NULL DEFAULT 0,
                data_emissao     TEXT,
                data_vencimento  TEXT NOT NULL,
                data_compensacao TEXT,
                status           TEXT DEFAULT 'PENDENTE',
                observacao       TEXT,
                created_at       TEXT,
                updated_at       TEXT
            )
        ");

        // Migrar colunas caso a tabela ja exista sem alguma
        $cols = [];
        $pragma = $conn->query("PRAGMA table_info(cheque)");
        while ($row = $pragma->fetch(\PDO::FETCH_ASSOC)) {
            $cols[] = $row['name'];
        }
        $missing = [
            'data_compensacao' => 'TEXT',
            'observacao'       => 'TEXT',
            'created_at'       => 'TEXT',
            'updated_at'       => 'TEXT',
        ];
        foreach ($missing as $col => $type) {
            if (!in_array($col, $cols)) {
                $conn->exec("ALTER TABLE cheque ADD COLUMN {$col} {$type}");
            }
        }
    }

    /**
     * Hook antes de salvar
     */
    public function onBeforeSave()
    {
        $now = date('Y-m-d H:i:s');
        if (empty($this->id) || !$this->created_at) {
            $this->created_at = $now;
        }
        $this->updated_at = $now;
    }
}
