<?php

class AcompProcesso extends TRecord
{
    const TABLENAME = 'acomp_processo';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'serial';

    // â”€â”€ Stage constants â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        const STAGE_COLETA          = 'coleta';
    const STAGE_TRANSITO_BRASIL = 'transito_brasil';
    const STAGE_ARMAZENAGEM     = 'armazenagem';
    const STAGE_ADUANA_BRASIL   = 'aduana_brasil';
    const STAGE_TRANSITO_EXT    = 'transito_ext';
    const STAGE_ADUANA_DESTINO  = 'aduana_destino';
    const STAGE_ENTREGA         = 'entrega';

    // Compatibilidade com codigos antigos
    const STAGE_TRANSITO_BR  = 'transito_brasil';
    const STAGE_ADUANA       = 'aduana_brasil';
    const STAGE_TRANSITO_INT = 'transito_ext';

    private static $stageLabels = [
        'coleta'          => 'Coleta',
        'transito_brasil' => 'Transito Brasil',
        'armazenagem'     => 'Armazenagem',
        'aduana_brasil'   => 'Aduana Brasil',
        'transito_ext'    => 'Transito Ext',
        'aduana_destino'  => 'Aduana Destino',
        'entrega'         => 'Entrega',
    ];

    private static $transitStages = [
        'transito_brasil',
        'armazenagem',
        'aduana_brasil',
        'transito_ext',
        'aduana_destino',
    ];

    /**
     * Normaliza um texto de status/evento para um cÃ³digo de estÃ¡gio interno.
     */
    public static function normalizeStageCode(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[Ã¡Ã Ã£Ã¢Ã¤]/u', 'a', $s);
        $s = preg_replace('/[Ã©Ã¨ÃªÃ«]/u', 'e', $s);
        $s = preg_replace('/[Ã­Ã¬Ã®Ã¯]/u', 'i', $s);
        $s = preg_replace('/[Ã³Ã²ÃµÃ´Ã¶]/u', 'o', $s);
        $s = preg_replace('/[ÃºÃ¹Ã»Ã¼]/u', 'u', $s);

        // JÃ¡ Ã© um cÃ³digo interno?
        if (isset(self::$stageLabels[$s])) {
            return $s;
        }

        // Compatibilidade com codigos antigos persistidos
        if ($s === 'transito_br') {
            return self::STAGE_TRANSITO_BRASIL;
        }
        if ($s === 'aduana') {
            return self::STAGE_ADUANA_BRASIL;
        }
        if ($s === 'transito_int') {
            return self::STAGE_TRANSITO_EXT;
        }

        if (strpos($s, 'entrega') !== false || strpos($s, 'entreg') !== false) {
            return self::STAGE_ENTREGA;
        }
        if (strpos($s, 'aduana destino') !== false || strpos($s, 'destino') !== false) {
            return self::STAGE_ADUANA_DESTINO;
        }
        if (strpos($s, 'aduana brasil') !== false || strpos($s, 'aduana br') !== false) {
            return self::STAGE_ADUANA_BRASIL;
        }
        if (strpos($s, 'armazen') !== false || strpos($s, 'patio') !== false) {
            return self::STAGE_ARMAZENAGEM;
        }
        if (strpos($s, 'transito ext') !== false || strpos($s, 'transito int') !== false) {
            return self::STAGE_TRANSITO_EXT;
        }
        if (strpos($s, 'transito brasil') !== false || strpos($s, 'transito br') !== false) {
            return self::STAGE_TRANSITO_BRASIL;
        }
        if (strpos($s, 'aduana') !== false || strpos($s, 'aguard') !== false || strpos($s, 'despacho') !== false) {
            return self::STAGE_ADUANA_BRASIL;
        }
        if (strpos($s, 'transito') !== false || strpos($s, 'transit') !== false) {
            return self::STAGE_TRANSITO_BRASIL;
        }
        if (strpos($s, 'coleta') !== false || strpos($s, 'retir') !== false) {
            return self::STAGE_COLETA;
        }

        return '';
    }

    /**
     * Retorna o rÃ³tulo legÃ­vel de um cÃ³digo de estÃ¡gio.
     */
    public static function stageLabel(string $stage): string
    {
        return self::$stageLabels[$stage] ?? ucfirst($stage);
    }

    /**
     * Verifica se o estÃ¡gio indica carga em trÃ¢nsito (nÃ£o coleta nem entrega final).
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
        parent::addAttribute('oculto_kanban');
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
            oculto_kanban INTEGER DEFAULT 0,
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
                'oculto_kanban' => 'INTEGER DEFAULT 0',
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
            $conn->exec("UPDATE acomp_processo SET oculto_kanban = 0 WHERE oculto_kanban IS NULL");
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

        $requiredProcessoColumns = ['etapa', 'local_entrega', 'aduana_origem', 'aduana_destino', 'cubagem', 'mapa_url', 'oculto_kanban'];
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
        if ($object->oculto_kanban === '' || $object->oculto_kanban === null) {
            $object->oculto_kanban = 0;
        }
        $object->updated_at = $now;
    }
}

