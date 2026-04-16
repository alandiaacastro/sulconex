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
        self::ensureSchemaIfPossible();
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('titulo');
        parent::addAttribute('frete_id');
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
        $fallback = [
            'ABERTA' => 'Aberta',
            'SIDER'  => 'Sider',
            'TRUCK'  => 'Truck',
            'BAU'    => 'Bau',
        ];

        try {
            $items = TabelaFrete::loadTipoVeiculoOptions('sample');
            if (!empty($items)) {
                return $items;
            }
        } catch (Exception $e) {
        }

        return $fallback;
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

    public function get_tabela_frete()
    {
        if (!empty($this->frete_id)) {
            return new TabelaFrete($this->frete_id);
        }
        return null;
    }

    public function getAduanaDestinoDisplay(): string
    {
        $value = trim((string) ($this->aduana_destino ?: $this->aduana_origem));
        if ($value === '') {
            return '';
        }

        $items = self::getAduanaDestinoItems();
        return (string) ($items[$value] ?? $value);
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
        self::syncAduanaData($object);
        self::syncFreteData($object);

        $now = date('Y-m-d H:i:s');
        if (empty($object->created_at)) {
            $object->created_at = $now;
        }
        if (empty($object->status)) {
            $object->status = self::STATUS_DISPONIVEL;
        }
        $object->updated_at = $now;
    }

    public static function ensureTables($connection = null): void
    {
        if (self::$schemaChecked) {
            return;
        }

        try {
            $openedTransaction = false;
            if ($connection === null) {
                TTransaction::open('sample');
                $connection = TTransaction::get();
                $openedTransaction = true;
            }

            if (self::schemaIsUpToDate($connection)) {
                self::$schemaChecked = true;
                if ($openedTransaction) {
                    TTransaction::close();
                }
                return;
            }

            $connection->exec("CREATE TABLE IF NOT EXISTS carga_disponivel (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo TEXT NOT NULL,
                frete_id INTEGER,
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

            $connection->exec("CREATE INDEX IF NOT EXISTS idx_carga_disp_status ON carga_disponivel(status)");
            $connection->exec("CREATE INDEX IF NOT EXISTS idx_carga_disp_origem ON carga_disponivel(origem)");
            $connection->exec("CREATE INDEX IF NOT EXISTS idx_carga_disp_frete ON carga_disponivel(frete_id)");

            $newCols = [
                'frete_id' => 'INTEGER',
                'aduana_origem' => 'TEXT',
                'aduana_destino' => 'TEXT',
                'quantidade' => 'INTEGER DEFAULT 1',
                'localizacao_maps' => 'TEXT',
            ];

            foreach ($newCols as $name => $type) {
                if (!self::tableHasColumn($connection, 'carga_disponivel', $name)) {
                    $connection->exec("ALTER TABLE carga_disponivel ADD COLUMN $name $type");
                }
            }

            self::$schemaChecked = true;

            if ($openedTransaction) {
                TTransaction::close();
            }
        } catch (Exception $e) {
            try {
                TTransaction::rollback();
            } catch (Exception $rollbackException) {
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
                self::ensureTables($connection);
            }
        } catch (Exception $e) {
            // schema will be ensured by callers that explicitly open a transaction
        }
    }

    private static function schemaIsUpToDate($conn): bool
    {
        if (!self::tableExists($conn, 'carga_disponivel')) {
            return false;
        }
        if (!self::tableHasColumn($conn, 'carga_disponivel', 'aduana_origem') ||
            !self::tableHasColumn($conn, 'carga_disponivel', 'frete_id') ||
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

    private static function syncFreteData($object): void
    {
        $conn = TTransaction::get();

        if (empty($object->frete_id) && !empty($object->origem) && !empty($object->destino)) {
            $object->frete_id = TabelaFrete::findLatestRouteIdByData(
                $conn,
                (string) $object->origem,
                (string) $object->destino,
                (string) ($object->tipo_veiculo ?? ''),
                isset($object->valor_frete) ? (float) $object->valor_frete : null
            );
        }

        if (!empty($object->frete_id)) {
            $frete = new TabelaFrete((int) $object->frete_id);

            if (!empty($frete->id)) {
                $object->frete_id = (int) $frete->id;
                $object->origem = (string) $frete->origem;
                $object->destino = (string) $frete->destino;
                $object->valor_frete = (float) $frete->valor_frete;
                if (!empty($frete->tipo_veiculo)) {
                    $object->tipo_veiculo = (string) $frete->tipo_veiculo;
                }
            }
        }

        if (!empty($object->origem)) {
            $object->origem = TabelaFrete::normalizeUpper($object->origem);
        }
        if (!empty($object->destino)) {
            $object->destino = TabelaFrete::normalizeUpper($object->destino);
        }
    }

    private static function syncAduanaData($object): void
    {
        $aduanaDestino = trim((string) ($object->aduana_destino ?? ''));
        $aduanaOrigem = trim((string) ($object->aduana_origem ?? ''));

        if ($aduanaDestino === '' && $aduanaOrigem !== '') {
            $aduanaDestino = $aduanaOrigem;
        }

        $object->aduana_destino = $aduanaDestino !== '' ? $aduanaDestino : null;
        $object->aduana_origem = null;
    }
}
