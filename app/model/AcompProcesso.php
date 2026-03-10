<?php

class AcompProcesso extends TRecord
{
    const TABLENAME = 'acomp_processo';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'serial';

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('numero_processo');
        parent::addAttribute('local_coleta');
        parent::addAttribute('data_coleta');
        parent::addAttribute('previsao_entrega');
        parent::addAttribute('transit_time_dias');
        parent::addAttribute('exportador');
        parent::addAttribute('importador');
        parent::addAttribute('produto');
        parent::addAttribute('crt');
        parent::addAttribute('fatura');
        parent::addAttribute('etapa');
        parent::addAttribute('peso_bruto');
        parent::addAttribute('cubagem');
        parent::addAttribute('mapa_url');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public static function ensureTables(): void
    {
        $conn = TTransaction::get();

        $conn->exec("CREATE TABLE IF NOT EXISTS acomp_processo (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            numero_processo TEXT,
            local_coleta TEXT,
            data_coleta TEXT,
            previsao_entrega TEXT,
            transit_time_dias INTEGER,
            exportador TEXT,
            importador TEXT,
            produto TEXT,
            crt TEXT,
            fatura TEXT,
            etapa TEXT,
            peso_bruto REAL,
            cubagem REAL,
            mapa_url TEXT,
            created_at TEXT,
            updated_at TEXT
        )");

        // Backfill schema changes for existing installs (SQLite)
        try {
            $stmt = $conn->query('PRAGMA table_info(acomp_processo)');
            $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            $colnames = array_map(static fn ($c) => $c['name'], $cols ?: []);

            if (!in_array('etapa', $colnames, true)) {
                $conn->exec("ALTER TABLE acomp_processo ADD COLUMN etapa TEXT");
            }
        } catch (Exception $e) {
            // ignore (best-effort backfill)
        }

        $conn->exec("UPDATE acomp_processo SET etapa = 'coleta' WHERE etapa IS NULL OR etapa = ''");

        $conn->exec("CREATE TABLE IF NOT EXISTS acomp_evento (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            processo_id INTEGER NOT NULL,
            data_evento TEXT NOT NULL,
            demora TEXT,
            status_texto TEXT NOT NULL,
            franquia TEXT,
            ordem INTEGER,
            created_at TEXT,
            FOREIGN KEY (processo_id) REFERENCES acomp_processo(id) ON DELETE CASCADE
        )");

        $conn->exec("CREATE INDEX IF NOT EXISTS idx_acomp_evento_processo ON acomp_evento(processo_id)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_acomp_evento_data ON acomp_evento(data_evento)");
    }

    public function onBeforeStore($object)
    {
        $now = date('Y-m-d H:i:s');
        if (empty($object->created_at)) {
            $object->created_at = $now;
        }
        if (empty($object->etapa)) {
            $object->etapa = 'coleta';
        }
        $object->updated_at = $now;
    }
}
