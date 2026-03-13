<?php

class AcompProcesso extends TRecord
{
    const TABLENAME = 'acomp_processo';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'serial';

    // ── Stage constants ──────────────────────────────────────────────
    const STAGE_COLETA       = 'coleta';
    const STAGE_TRANSITO_BR  = 'transito_br';
    const STAGE_ADUANA       = 'aduana';
    const STAGE_TRANSITO_INT = 'transito_int';
    const STAGE_ARMAZENAGEM  = 'armazenagem';
    const STAGE_ENTREGA      = 'entrega';

    private static $stageLabels = [
        'coleta'       => 'Coleta',
        'transito_br'  => 'Trânsito BR',
        'aduana'       => 'Aduana',
        'transito_int' => 'Trânsito INT',
        'armazenagem'  => 'Armazenagem',
        'entrega'      => 'Entregue',
    ];

    private static $transitStages = [
        'transito_br',
        'aduana',
        'transito_int',
        'armazenagem',
    ];

    /**
     * Normaliza um texto de status/evento para um código de estágio interno.
     */
    public static function normalizeStageCode(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[áàãâä]/u', 'a', $s);
        $s = preg_replace('/[éèêë]/u', 'e', $s);
        $s = preg_replace('/[íìîï]/u', 'i', $s);
        $s = preg_replace('/[óòõôö]/u', 'o', $s);
        $s = preg_replace('/[úùûü]/u', 'u', $s);

        // Já é um código interno?
        if (isset(self::$stageLabels[$s])) {
            return $s;
        }

        if (strpos($s, 'entrega') !== false || strpos($s, 'entreg') !== false) {
            return self::STAGE_ENTREGA;
        }
        if (strpos($s, 'aduana') !== false || strpos($s, 'aguard') !== false || strpos($s, 'despacho') !== false) {
            return self::STAGE_ADUANA;
        }
        if (strpos($s, 'armazen') !== false || strpos($s, 'patio') !== false) {
            return self::STAGE_ARMAZENAGEM;
        }
        if (strpos($s, 'transito') !== false || strpos($s, 'transit') !== false) {
            return self::STAGE_TRANSITO_BR;
        }
        if (strpos($s, 'coleta') !== false || strpos($s, 'retir') !== false) {
            return self::STAGE_COLETA;
        }

        return '';
    }

    /**
     * Retorna o rótulo legível de um código de estágio.
     */
    public static function stageLabel(string $stage): string
    {
        return self::$stageLabels[$stage] ?? ucfirst($stage);
    }

    /**
     * Verifica se o estágio indica carga em trânsito (não coleta nem entrega final).
     */
    public static function isTransitStage(string $stage): bool
    {
        return in_array($stage, self::$transitStages, true);
    }

    private static $schemaChecked = false;

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('numero_processo');
        parent::addAttribute('local_coleta');
        parent::addAttribute('local_entrega');
        parent::addAttribute('data_coleta');
        parent::addAttribute('previsao_entrega');
        parent::addAttribute('transit_time_dias');
        parent::addAttribute('aduana_origem');
        parent::addAttribute('aduana_destino');
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
        if (self::$schemaChecked) {
            return;
        }

        $conn = TTransaction::get();

        if (self::schemaIsUpToDate($conn)) {
            self::$schemaChecked = true;
            return;
        }

        $conn->exec("CREATE TABLE IF NOT EXISTS acomp_processo (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            numero_processo TEXT,
            local_coleta TEXT,
            local_entrega TEXT,
            data_coleta TEXT,
            previsao_entrega TEXT,
            transit_time_dias INTEGER,
            aduana_origem TEXT,
            aduana_destino TEXT,
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
            $processoCols = [
                'etapa' => 'TEXT',
                'local_entrega' => 'TEXT',
                'aduana_origem' => 'TEXT',
                'aduana_destino' => 'TEXT',
                'cubagem' => 'REAL',
                'mapa_url' => 'TEXT',
            ];

            foreach ($processoCols as $name => $type) {
                if (!self::tableHasColumn($conn, 'acomp_processo', $name)) {
                    $conn->exec("ALTER TABLE acomp_processo ADD COLUMN $name $type");
                }
            }
        } catch (Exception $e) {
            // ignore (best-effort backfill)
        }

        try {
            $missingEtapa = (int) $conn->query("SELECT COUNT(1) FROM acomp_processo WHERE etapa IS NULL OR etapa = ''")->fetchColumn();
            if ($missingEtapa > 0) {
                $conn->exec("UPDATE acomp_processo SET etapa = 'coleta' WHERE etapa IS NULL OR etapa = ''");
            }
        } catch (Exception $e) {
            // ignore (best-effort backfill)
        }

        $conn->exec("CREATE TABLE IF NOT EXISTS acomp_evento (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            processo_id INTEGER NOT NULL,
            data_evento TEXT NOT NULL,
            demora TEXT,
            localizacao TEXT,
            status_texto TEXT NOT NULL,
            franquia TEXT,
            ordem INTEGER,
            created_at TEXT,
            FOREIGN KEY (processo_id) REFERENCES acomp_processo(id) ON DELETE CASCADE
        )");

        try {
            if (!self::tableHasColumn($conn, 'acomp_evento', 'localizacao')) {
                $conn->exec("ALTER TABLE acomp_evento ADD COLUMN localizacao TEXT");
            }
            if (!self::tableHasColumn($conn, 'acomp_evento', 'imagem')) {
                $conn->exec("ALTER TABLE acomp_evento ADD COLUMN imagem TEXT");
            }
        } catch (Exception $e) {
            // ignore (best-effort backfill)
        }

        $conn->exec("CREATE INDEX IF NOT EXISTS idx_acomp_evento_processo ON acomp_evento(processo_id)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_acomp_evento_data ON acomp_evento(data_evento)");

        self::$schemaChecked = true;
    }

    private static function schemaIsUpToDate($conn): bool
    {
        if (!self::tableExists($conn, 'acomp_processo') || !self::tableExists($conn, 'acomp_evento')) {
            return false;
        }

        $requiredProcessoColumns = ['etapa', 'local_entrega', 'aduana_origem', 'aduana_destino', 'cubagem', 'mapa_url'];
        foreach ($requiredProcessoColumns as $column) {
            if (!self::tableHasColumn($conn, 'acomp_processo', $column)) {
                return false;
            }
        }

        if (!self::tableHasColumn($conn, 'acomp_evento', 'localizacao')) {
            return false;
        }

        if (!self::tableHasColumn($conn, 'acomp_evento', 'imagem')) {
            return false;
        }

        return true;
    }

    private static function tableExists($conn, string $table): bool
    {
        $stmt = $conn->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private static function tableHasColumn($conn, string $table, string $column): bool
    {
        $stmt = $conn->query("PRAGMA table_info($table)");
        $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($cols as $col) {
            if (($col['name'] ?? null) === $column) {
                return true;
            }
        }

        return false;
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
