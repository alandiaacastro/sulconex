<?php

class SolicitacaoCarga extends TRecord
{
    const TABLENAME  = 'solicitacao_carga';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    const STATUS_PENDENTE   = 'pendente';
    const STATUS_EM_ANALISE = 'em_analise';
    const STATUS_APROVADO   = 'aprovado';
    const STATUS_RECUSADO   = 'recusado';
    const STATUS_CANCELADO  = 'cancelado';

    private static $schemaChecked = false;

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('carga_disponivel_id');
        parent::addAttribute('motorista_id');
        parent::addAttribute('veiculo_id');
        parent::addAttribute('mensagem');
        parent::addAttribute('data_disponibilidade');
        parent::addAttribute('status');
        parent::addAttribute('resposta_admin');
        parent::addAttribute('respondido_por');
        parent::addAttribute('respondido_em');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public static function getStatusLabels()
    {
        return [
            self::STATUS_PENDENTE   => 'Pendente',
            self::STATUS_EM_ANALISE => 'Em Analise',
            self::STATUS_APROVADO   => 'Aprovado',
            self::STATUS_RECUSADO   => 'Recusado',
            self::STATUS_CANCELADO  => 'Cancelado',
        ];
    }

    public function get_carga_disponivel()
    {
        if (!empty($this->carga_disponivel_id)) {
            return new CargaDisponivel($this->carga_disponivel_id);
        }
        return null;
    }

    public function get_motorista()
    {
        if (!empty($this->motorista_id)) {
            return new Motorista($this->motorista_id);
        }
        return null;
    }

    public function get_veiculo()
    {
        if (!empty($this->veiculo_id)) {
            return new Veiculo($this->veiculo_id);
        }
        return null;
    }

    public function onBeforeStore($object)
    {
        $now = date('Y-m-d H:i:s');
        if (empty($object->created_at)) {
            $object->created_at = $now;
        }
        if (empty($object->status)) {
            $object->status = self::STATUS_PENDENTE;
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

        $conn->exec("CREATE TABLE IF NOT EXISTS solicitacao_carga (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            carga_disponivel_id INTEGER NOT NULL,
            motorista_id INTEGER NOT NULL,
            veiculo_id INTEGER,
            mensagem TEXT,
            data_disponibilidade TEXT,
            status TEXT DEFAULT 'pendente',
            resposta_admin TEXT,
            respondido_por INTEGER,
            respondido_em TEXT,
            created_at TEXT,
            updated_at TEXT,
            FOREIGN KEY (carga_disponivel_id) REFERENCES carga_disponivel(id),
            FOREIGN KEY (motorista_id) REFERENCES motorista(id)
        )");

        $conn->exec("CREATE INDEX IF NOT EXISTS idx_solic_carga ON solicitacao_carga(carga_disponivel_id)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_solic_motorista ON solicitacao_carga(motorista_id)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_solic_status ON solicitacao_carga(status)");

        self::$schemaChecked = true;
    }

    private static function schemaIsUpToDate($conn): bool
    {
        return self::tableExists($conn, 'solicitacao_carga');
    }

    private static function tableExists($conn, string $table): bool
    {
        $stmt = $conn->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
