<?php

class CargaDisponivel extends TRecord
{
    const TABLENAME  = 'carga_disponivel';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    const STATUS_DISPONIVEL = 'disponivel';
    const STATUS_RESERVADA  = 'reservada';
    const STATUS_ENCERRADA  = 'encerrada';
    const STATUS_CANCELADA  = 'cancelada';

    private static $schemaChecked = false;

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('titulo');
        parent::addAttribute('origem');
        parent::addAttribute('destino');
        parent::addAttribute('tipo_carga');
        parent::addAttribute('tipo_veiculo');
        parent::addAttribute('aduana_origem');
        parent::addAttribute('aduana_destino');
        parent::addAttribute('peso_estimado_kg');
        parent::addAttribute('volume_m3');
        parent::addAttribute('valor_frete');
        parent::addAttribute('quantidade');
        parent::addAttribute('data_coleta');
        parent::addAttribute('data_entrega_prevista');
        parent::addAttribute('descricao');
        parent::addAttribute('localizacao_maps');
        parent::addAttribute('observacoes');
        parent::addAttribute('status');
        parent::addAttribute('conhecimento_id');
        parent::addAttribute('created_by');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public static function getStatusLabels()
    {
        return [
            self::STATUS_DISPONIVEL => 'Disponivel',
            self::STATUS_RESERVADA  => 'Reservada',
            self::STATUS_ENCERRADA  => 'Encerrada',
            self::STATUS_CANCELADA  => 'Cancelada',
        ];
    }

    public static function getTipoCargaItems()
    {
        return [
            'bobinas'     => 'Bobinas',
            'pallets'     => 'Pallets',
            'amarrados'   => 'Amarrados',
            'granel'      => 'Granel',
            'perigosa'    => 'Perigosa',
            'refrigerada' => 'Refrigerada',
            'geral'       => 'Carga Geral',
        ];
    }

    public static function getTipoVeiculoItems()
    {
        return [
            'ABERTA' => 'Aberta',
            'SIDER'  => 'Sider',
            'TRUCK'  => 'Truck',
            'BAU'    => 'Bau',
        ];
    }

    public static function getAduanaOrigemItems()
    {
        return [
            'Foz do Iguacu'  => 'Foz do Iguacu',
            'Uruguaiana'     => 'Uruguaiana',
            'Dionisio Cerqueira' => 'Dionisio Cerqueira',
            'Sao Borja'      => 'Sao Borja',
            'Jaguarao'       => 'Jaguarao',
            'Santana do Livramento' => 'Santana do Livramento',
            'Chuí'           => 'Chui',
            'Corumba'        => 'Corumba',
            'Ponta Pora'     => 'Ponta Pora',
            'Guaira'         => 'Guaira',
            'Paranagua'      => 'Paranagua',
            'Santos'         => 'Santos',
        ];
    }

    public static function getAduanaDestinoItems()
    {
        return [
            'Paso de los Libres' => 'Paso de los Libres, AR',
            'Puerto Iguazu'      => 'Puerto Iguazu, AR',
            'Buenos Aires'       => 'Buenos Aires, AR',
            'Mendoza'            => 'Mendoza, AR',
            'Montevideo'         => 'Montevideo, UY',
            'Rivera'             => 'Rivera, UY',
            'Asuncion'           => 'Asuncion, PY',
            'Ciudad del Este'    => 'Ciudad del Este, PY',
            'Santiago'           => 'Santiago, CL',
            'Valparaiso'         => 'Valparaiso, CL',
            'Lima'               => 'Lima, PE',
            'La Paz'             => 'La Paz, BO',
            'Santa Cruz'         => 'Santa Cruz, BO',
        ];
    }

    public function get_conhecimento()
    {
        if (!empty($this->conhecimento_id)) {
            return new Conhecimento($this->conhecimento_id);
        }
        return null;
    }

    /**
     * Conta solicitacoes pendentes para esta carga
     */
    public function countSolicitacoesPendentes()
    {
        $repo = new TRepository('SolicitacaoCarga');
        $criteria = new TCriteria;
        $criteria->add(new TFilter('carga_disponivel_id', '=', $this->id));
        $criteria->add(new TFilter('status', '=', SolicitacaoCarga::STATUS_PENDENTE));
        return $repo->count($criteria);
    }

    public function onBeforeStore($object)
    {
        $now = date('Y-m-d H:i:s');
        if (empty($object->created_at)) {
            $object->created_at = $now;
        }
        if (empty($object->status)) {
            $object->status = self::STATUS_DISPONIVEL;
        }
        $object->updated_at = $now;
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

        $conn->exec("CREATE TABLE IF NOT EXISTS carga_disponivel (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL,
            origem TEXT NOT NULL,
            destino TEXT NOT NULL,
            tipo_carga TEXT,
            tipo_veiculo TEXT,
            peso_estimado_kg REAL,
            volume_m3 REAL,
            valor_frete REAL,
            data_coleta TEXT,
            data_entrega_prevista TEXT,
            descricao TEXT,
            observacoes TEXT,
            status TEXT DEFAULT 'disponivel',
            conhecimento_id INTEGER,
            created_by INTEGER,
            created_at TEXT,
            updated_at TEXT
        )");

        $conn->exec("CREATE INDEX IF NOT EXISTS idx_carga_disp_status ON carga_disponivel(status)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_carga_disp_origem ON carga_disponivel(origem)");

        // Backfill new columns
        $newCols = ['aduana_origem' => 'TEXT', 'aduana_destino' => 'TEXT', 'quantidade' => 'INTEGER DEFAULT 1', 'localizacao_maps' => 'TEXT'];
        try {
            foreach ($newCols as $name => $type) {
                if (!self::tableHasColumn($conn, 'carga_disponivel', $name)) {
                    $conn->exec("ALTER TABLE carga_disponivel ADD COLUMN $name $type");
                }
            }
        } catch (Exception $e) {
            // best-effort
        }

        self::$schemaChecked = true;
    }

    private static function schemaIsUpToDate($conn): bool
    {
        if (!self::tableExists($conn, 'carga_disponivel')) {
            return false;
        }
        if (!self::tableHasColumn($conn, 'carga_disponivel', 'aduana_origem') ||
            !self::tableHasColumn($conn, 'carga_disponivel', 'quantidade') ||
            !self::tableHasColumn($conn, 'carga_disponivel', 'localizacao_maps')) {
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
}
