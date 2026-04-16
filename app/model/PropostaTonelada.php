<?php

class PropostaTonelada extends TRecord
{
    const TABLENAME  = 'proposta_tonelada';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    public function __construct($id = null, $callObjectLoad = true)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('numero_proposta');
        parent::addAttribute('cliente_id');
        parent::addAttribute('conhecimento_id');
        parent::addAttribute('data_proposta');
        parent::addAttribute('validade');
        parent::addAttribute('status');
        parent::addAttribute('origem');
        parent::addAttribute('fronteira');
        parent::addAttribute('destino');
        parent::addAttribute('tipo_veiculo');
        parent::addAttribute('descricao_mercadoria');
        parent::addAttribute('toneladas');
        parent::addAttribute('valor_frete_base');
        parent::addAttribute('valor_por_ton');
        parent::addAttribute('valor_total');
        parent::addAttribute('observacoes');
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
                "CREATE TABLE IF NOT EXISTS proposta_tonelada (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    numero_proposta TEXT,
                    cliente_id INTEGER,
                    conhecimento_id INTEGER,
                    data_proposta DATE,
                    validade DATE,
                    status TEXT,
                    origem TEXT,
                    fronteira TEXT,
                    destino TEXT,
                    tipo_veiculo TEXT,
                    descricao_mercadoria TEXT,
                    toneladas REAL DEFAULT 0,
                    valor_frete_base REAL DEFAULT 0,
                    valor_por_ton REAL DEFAULT 0,
                    valor_total REAL DEFAULT 0,
                    observacoes TEXT
                )"
            );

            $columns = $conn->query("PRAGMA table_info(proposta_tonelada)")->fetchAll(PDO::FETCH_COLUMN, 1);
            $missingColumns = [
                'numero_proposta'   => "ALTER TABLE proposta_tonelada ADD COLUMN numero_proposta TEXT",
                'cliente_id'        => "ALTER TABLE proposta_tonelada ADD COLUMN cliente_id INTEGER",
                'conhecimento_id'   => "ALTER TABLE proposta_tonelada ADD COLUMN conhecimento_id INTEGER",
                'data_proposta'     => "ALTER TABLE proposta_tonelada ADD COLUMN data_proposta DATE",
                'validade'          => "ALTER TABLE proposta_tonelada ADD COLUMN validade DATE",
                'status'            => "ALTER TABLE proposta_tonelada ADD COLUMN status TEXT",
                'origem'            => "ALTER TABLE proposta_tonelada ADD COLUMN origem TEXT",
                'fronteira'         => "ALTER TABLE proposta_tonelada ADD COLUMN fronteira TEXT",
                'destino'           => "ALTER TABLE proposta_tonelada ADD COLUMN destino TEXT",
                'tipo_veiculo'      => "ALTER TABLE proposta_tonelada ADD COLUMN tipo_veiculo TEXT",
                'descricao_mercadoria' => "ALTER TABLE proposta_tonelada ADD COLUMN descricao_mercadoria TEXT",
                'toneladas'         => "ALTER TABLE proposta_tonelada ADD COLUMN toneladas REAL DEFAULT 0",
                'valor_frete_base'  => "ALTER TABLE proposta_tonelada ADD COLUMN valor_frete_base REAL DEFAULT 0",
                'valor_por_ton'     => "ALTER TABLE proposta_tonelada ADD COLUMN valor_por_ton REAL DEFAULT 0",
                'valor_total'       => "ALTER TABLE proposta_tonelada ADD COLUMN valor_total REAL DEFAULT 0",
                'observacoes'       => "ALTER TABLE proposta_tonelada ADD COLUMN observacoes TEXT",
            ];

            foreach ($missingColumns as $column => $sql) {
                if (!in_array($column, $columns, true)) {
                    $conn->exec($sql);
                }
            }

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
}
