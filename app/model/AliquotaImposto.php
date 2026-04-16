<?php
/**
 * AliquotaImposto - Tabela de alíquotas de impostos configuráveis
 * Permite alterar as alíquotas conforme mudanças na legislação.
 */
class AliquotaImposto extends TRecord
{
    const TABLENAME  = 'aliquotas_impostos';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'serial';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('codigo');
        parent::addAttribute('descricao');
        parent::addAttribute('aliquota');
        parent::addAttribute('updated_at');
    }

    /**
     * Cria a tabela e popula os valores padrão se não existir
     */
    public static function createTableIfNotExists()
    {
        TTransaction::open('sample');
        $conn = TTransaction::get();

        $conn->exec("
            CREATE TABLE IF NOT EXISTS aliquotas_impostos (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo   TEXT NOT NULL UNIQUE,
                descricao TEXT NOT NULL,
                aliquota REAL NOT NULL DEFAULT 0,
                updated_at TEXT
            )
        ");

        // Insere alíquotas padrão (legislação vigente) se a tabela estiver vazia
        $count = (int) $conn->query("SELECT COUNT(*) FROM aliquotas_impostos")->fetchColumn();
        if ($count === 0) {
            $defaults = [
                ['IRRF',      'IRRF - Imposto de Renda Retido na Fonte',  0.015],
                ['SEST_SENAT','SEST/SENAT',                                0.015],
                ['PIS',       'PIS - Prog. de Integração Social',          0.0065],
                ['COFINS',    'COFINS - Contrib. p/ Financ. Seguridade',  0.03],
                ['ISF',       'Imposto sobre Faturamento',                 0.0282],
            ];
            $stmt = $conn->prepare(
                "INSERT INTO aliquotas_impostos (codigo, descricao, aliquota, updated_at) VALUES (?,?,?,?)"
            );
            foreach ($defaults as [$cod, $desc, $aliq]) {
                $stmt->execute([$cod, $desc, $aliq, date('Y-m-d H:i:s')]);
            }
        } else {
            // Garante que o ISF existe em bancos já existentes
            $conn->prepare(
                "INSERT OR IGNORE INTO aliquotas_impostos (codigo, descricao, aliquota, updated_at) VALUES (?,?,?,?)"
            )->execute(['ISF', 'Imposto sobre Faturamento', 0.0282, date('Y-m-d H:i:s')]);
        }

        TTransaction::close();
    }

    /**
     * Retorna array [codigo => aliquota] com todas as alíquotas ativas
     */
    public static function getAll(): array
    {
        TTransaction::open('sample');
        $conn = TTransaction::get();
        $rows = $conn->query("SELECT codigo, aliquota FROM aliquotas_impostos")->fetchAll(\PDO::FETCH_KEY_PAIR);
        TTransaction::close();
        return $rows;
    }
}
