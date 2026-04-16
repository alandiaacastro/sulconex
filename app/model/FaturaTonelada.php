<?php

class FaturaTonelada extends TRecord
{
    const TABLENAME  = 'fatura_tonelada';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    private $clientekey;
    private $conhecimentokey;

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('pessoa_id');
        parent::addAttribute('conhecimento_id');
        parent::addAttribute('numero_fatura');
        parent::addAttribute('numero_crt');
        parent::addAttribute('fatura_cliente');
        parent::addAttribute('emissao');
        parent::addAttribute('vencimento');
        parent::addAttribute('prazo');
        parent::addAttribute('taxa');
        parent::addAttribute('nota_fiscal');
        parent::addAttribute('texto_observacao');
        parent::addAttribute('descricao1');
        parent::addAttribute('valor1');
        parent::addAttribute('descricao2');
        parent::addAttribute('valor2');
        parent::addAttribute('descricao3');
        parent::addAttribute('valor3');
        parent::addAttribute('valor_fatura');
        parent::addAttribute('valor_extenso');
        parent::addAttribute('pagamento');
        parent::addAttribute('tipo_baixa');
        parent::addAttribute('desconto_banco');
        parent::addAttribute('ORIGEM');
        parent::addAttribute('DESTINO');
        parent::addAttribute('REMETENTE');
        parent::addAttribute('DESTINATARIO');
        parent::addAttribute('PESO_BRUTO');
        parent::addAttribute('PRODUTO');
        parent::addAttribute('toneladas_carga_total');
        parent::addAttribute('toneladas_ja_faturadas');
        parent::addAttribute('toneladas_faturadas');
        parent::addAttribute('toneladas_saldo');
        parent::addAttribute('valor_por_ton');
    }

    public function get_clientekey()
    {
        if (empty($this->clientekey) && !empty($this->pessoa_id)) {
            $this->clientekey = new Clientes($this->pessoa_id);
        }

        return $this->clientekey;
    }

    public function get_conhecimentokey()
    {
        if (empty($this->conhecimentokey) && !empty($this->conhecimento_id)) {
            $this->conhecimentokey = new Conhecimento($this->conhecimento_id);
        }

        return $this->conhecimentokey;
    }

    public static function ensureSchema(): void
    {
        $openedHere = false;

        if (!TTransaction::get()) {
            TTransaction::open('sample');
            $openedHere = true;
        }

        try {
            $conn = TTransaction::get();
            $conn->exec(
                "CREATE TABLE IF NOT EXISTS fatura_tonelada (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    pessoa_id INTEGER,
                    conhecimento_id INTEGER,
                    numero_fatura TEXT,
                    numero_crt TEXT,
                    fatura_cliente TEXT,
                    emissao DATE,
                    vencimento DATE,
                    prazo INTEGER,
                    taxa REAL,
                    nota_fiscal TEXT,
                    texto_observacao TEXT,
                    descricao1 TEXT,
                    valor1 REAL,
                    descricao2 TEXT,
                    valor2 REAL,
                    descricao3 TEXT,
                    valor3 REAL,
                    valor_fatura REAL,
                    valor_extenso TEXT,
                    pagamento DATE,
                    tipo_baixa TEXT,
                    desconto_banco REAL DEFAULT 0,
                    ORIGEM TEXT,
                    DESTINO TEXT,
                    REMETENTE TEXT,
                    DESTINATARIO TEXT,
                    PESO_BRUTO NUMERIC,
                    PRODUTO TEXT,
                    toneladas_carga_total REAL DEFAULT 0,
                    toneladas_ja_faturadas REAL DEFAULT 0,
                    toneladas_faturadas REAL DEFAULT 0,
                    toneladas_saldo REAL DEFAULT 0,
                    valor_por_ton REAL DEFAULT 0
                )"
            );

            $columns = $conn->query("PRAGMA table_info(fatura_tonelada)")->fetchAll(PDO::FETCH_COLUMN, 1);
            $missingColumns = [
                'tipo_baixa'            => "ALTER TABLE fatura_tonelada ADD COLUMN tipo_baixa TEXT",
                'desconto_banco'        => "ALTER TABLE fatura_tonelada ADD COLUMN desconto_banco REAL DEFAULT 0",
                'toneladas_carga_total' => "ALTER TABLE fatura_tonelada ADD COLUMN toneladas_carga_total REAL DEFAULT 0",
                'toneladas_ja_faturadas'=> "ALTER TABLE fatura_tonelada ADD COLUMN toneladas_ja_faturadas REAL DEFAULT 0",
                'toneladas_faturadas'   => "ALTER TABLE fatura_tonelada ADD COLUMN toneladas_faturadas REAL DEFAULT 0",
                'toneladas_saldo'       => "ALTER TABLE fatura_tonelada ADD COLUMN toneladas_saldo REAL DEFAULT 0",
                'valor_por_ton'         => "ALTER TABLE fatura_tonelada ADD COLUMN valor_por_ton REAL DEFAULT 0",
            ];

            foreach ($missingColumns as $column => $sql) {
                if (!in_array($column, $columns, true)) {
                    $conn->exec($sql);
                }
            }

            // Índice para acelerar consultas por vencimento/pagamento
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_fatura_tonelada_venc_pag ON fatura_tonelada(vencimento, pagamento)");

            if ($openedHere) {
                TTransaction::close();
            }
        } catch (Exception $e) {
            if ($openedHere) {
                TTransaction::rollback();
            }
            throw $e;
        }
    }

    public static function getResumoToneladas(int $conhecimentoId, ?int $ignoreId = null): array
    {
        $openedHere = false;

        if (!TTransaction::get()) {
            TTransaction::open('sample');
            $openedHere = true;
        }

        try {
            Conhecimento::ensureSchema();
            self::ensureSchema();

            $conhecimento = new Conhecimento($conhecimentoId);
            $conn = TTransaction::get();

            $sql = "SELECT COALESCE(SUM(COALESCE(toneladas_faturadas, 0)), 0)
                      FROM fatura_tonelada
                     WHERE conhecimento_id = :conhecimento_id";
            $params = [':conhecimento_id' => $conhecimentoId];

            if ($ignoreId) {
                $sql .= " AND id <> :id";
                $params[':id'] = $ignoreId;
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            $toneladasTotal = $conhecimento->getToneladasCalculadas();
            $toneladasFaturadas = (float) $stmt->fetchColumn();
            $saldo = max(0, $toneladasTotal - $toneladasFaturadas);
            $valorPorTon = (float) ($conhecimento->valor_por_ton ?? 0);

            $result = [
                'total' => $toneladasTotal,
                'faturadas' => $toneladasFaturadas,
                'saldo' => $saldo,
                'valor_por_ton' => $valorPorTon,
            ];

            if ($openedHere) {
                TTransaction::close();
            }

            return $result;
        } catch (Exception $e) {
            if ($openedHere) {
                TTransaction::rollback();
            }
            throw $e;
        }
    }
}
