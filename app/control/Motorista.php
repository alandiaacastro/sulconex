<?php

class Motorista extends TRecord
{
    const TABLENAME  = 'motorista';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    private static $schemaChecked = false;

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        $this->addAttribute('cnh_numero');
        $this->addAttribute('data_emissao_cnh');
        $this->addAttribute('data_validade_cnh');
        $this->addAttribute('categoria');
        $this->addAttribute('registro_num');
        $this->addAttribute('nome');
        $this->addAttribute('data_nascimento');
        $this->addAttribute('local_nascimento');
        $this->addAttribute('cpf');
        $this->addAttribute('rg_numero');
        $this->addAttribute('rg_emissor');
        $this->addAttribute('rg_uf');
        $this->addAttribute('filiacao_pai');
        $this->addAttribute('filiacao_mae');
        $this->addAttribute('system_user_id');
        $this->addAttribute('telefone');
        $this->addAttribute('email');
    }

    public function get_nome_completo()
    {
        return $this->nome;
    }

    /**
     * Retorna o motorista vinculado ao system_user logado
     */
    public static function getBySystemUser($userId)
    {
        $repository = new TRepository('Motorista');
        $criteria = new TCriteria;
        $criteria->add(new TFilter('system_user_id', '=', $userId));
        $objects = $repository->load($criteria, FALSE);

        return $objects ? $objects[0] : null;
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

        $newCols = [
            'system_user_id' => 'INTEGER',
            'telefone'       => 'TEXT',
            'email'          => 'TEXT',
        ];

        try {
            foreach ($newCols as $name => $type) {
                if (!self::tableHasColumn($conn, 'motorista', $name)) {
                    $conn->exec("ALTER TABLE motorista ADD COLUMN $name $type");
                }
            }
        } catch (Exception $e) {
            // best-effort
        }

        self::$schemaChecked = true;
    }

    private static function schemaIsUpToDate($conn): bool
    {
        $required = ['system_user_id', 'telefone', 'email'];
        foreach ($required as $col) {
            if (!self::tableHasColumn($conn, 'motorista', $col)) {
                return false;
            }
        }
        return true;
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
