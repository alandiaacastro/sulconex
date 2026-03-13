<?php

use Adianti\Database\TRecord;

class EstoqueManifesto extends TRecord
{
    const TABLENAME  = 'estoque_manifesto';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('crt_codigo');
        parent::addAttribute('crt_normalizado');
        parent::addAttribute('exportador_id');
        parent::addAttribute('importador_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public static function ensureTables(): void
    {
        $conn = TTransaction::get();

        $conn->exec("
            CREATE TABLE IF NOT EXISTS estoque_manifesto (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                crt_codigo TEXT NOT NULL,
                crt_normalizado TEXT NOT NULL UNIQUE,
                exportador_id INTEGER NOT NULL,
                importador_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $conn->exec("
            CREATE TABLE IF NOT EXISTS estoque_manifesto_danfe (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                manifesto_id INTEGER NOT NULL,
                danfe_codigo TEXT NOT NULL,
                danfe_normalizado TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(manifesto_id, danfe_normalizado),
                FOREIGN KEY(manifesto_id) REFERENCES estoque_manifesto(id)
            )
        ");

        // Trava global: uma DANFE nao pode existir em manifestos diferentes.
        // Se houver dado legado duplicado, mantemos apenas a validacao em nivel de aplicacao.
        $dupCount = (int) $conn->query("
            SELECT COUNT(*) FROM (
                SELECT danfe_normalizado
                FROM estoque_manifesto_danfe
                GROUP BY danfe_normalizado
                HAVING COUNT(*) > 1
            ) t
        ")->fetchColumn();

        if ($dupCount === 0) {
            $conn->exec("
                CREATE UNIQUE INDEX IF NOT EXISTS idx_estoque_danfe_global
                ON estoque_manifesto_danfe (danfe_normalizado)
            ");
        }

        $conn->exec("
            CREATE TABLE IF NOT EXISTS estoque_movimento (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                manifesto_id INTEGER NOT NULL,
                tipo TEXT NOT NULL,
                peso_kg REAL NOT NULL DEFAULT 0,
                bobinas INTEGER NOT NULL DEFAULT 0,
                data_movimento TEXT NOT NULL,
                observacao TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(manifesto_id) REFERENCES estoque_manifesto(id)
            )
        ");

        // Evolucao de schema: garante colunas usadas pelo modulo de estoque
        $existing = [];
        $cols = $conn->query("PRAGMA table_info(estoque_movimento)")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            $existing[$col['name']] = true;
        }

        $requiredCols = [
            'motorista_nome' => 'TEXT',
            'veiculo_cavalo' => 'TEXT',
            'veiculo_carreta' => 'TEXT',
            'motorista_saida_nome' => 'TEXT',
            'veiculo_saida_cavalo' => 'TEXT',
            'veiculo_saida_carreta' => 'TEXT',
            'numero_ordem' => 'TEXT',
            'tipo_carga' => 'TEXT',
            'xml_nfe' => 'TEXT',
            'chave_nfe' => 'TEXT',
            'danfe' => 'TEXT',
            'valor_total' => 'REAL DEFAULT 0',
            'data_emissao' => 'TEXT',
            'data_saida' => 'TEXT',
            'status' => "TEXT DEFAULT 'confirmado'",
            'updated_at' => 'TEXT',
            'fornecedor_cnpj' => 'TEXT',
            'fornecedor_nome' => 'TEXT',
            // Novos campos de negocio solicitados
            'tipo_volume' => 'TEXT',
            'quantidade' => 'REAL DEFAULT 0',
            'peso_bruto_kg' => 'REAL DEFAULT 0',
            'peso_liquido_kg' => 'REAL DEFAULT 0',
        ];

        foreach ($requiredCols as $name => $type) {
            if (empty($existing[$name])) {
                $conn->exec("ALTER TABLE estoque_movimento ADD COLUMN {$name} {$type}");
            }
        }
    }

    public static function normalizeCode(string $value): string
    {
        $value = strtoupper(trim($value));
        return preg_replace('/[^A-Z0-9]/', '', $value);
    }
}
