<?php
/**
 * Clientes Active Record
 */
class Clientes extends TRecord
{
    const TABLENAME  = 'clientes';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // 'max' se gera na aplicação, 'serial' se auto_increment no banco

    private static $schemaEnsured = false;

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        if (!self::$schemaEnsured) {
            self::$schemaEnsured = true;
            self::ensureSchema();
        }
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('nome');
        parent::addAttribute('email');
        parent::addAttribute('telefone');
        parent::addAttribute('endereco');
        parent::addAttribute('cidade');
        parent::addAttribute('estado');
        parent::addAttribute('cep');
        parent::addAttribute('cnpj');
        parent::addAttribute('inscricao_estadual');
        parent::addAttribute('atividade');
        parent::addAttribute('emissao_crt');
        parent::addAttribute('tipo');
    }

    /**
     * Garante que colunas novas existam na tabela (migração incremental).
     */
    public static function ensureSchema(): void
    {
        try {
            TTransaction::open('sample');
            $conn = TTransaction::get();

            $rows = $conn->query("PRAGMA table_info(clientes)")->fetchAll(PDO::FETCH_ASSOC);
            $cols = array_column($rows, 'name');
            if (!in_array('tipo', $cols)) {
                $conn->exec("ALTER TABLE clientes ADD COLUMN tipo TEXT DEFAULT NULL");
            }

            TTransaction::close();
        } catch (Exception $e) {
            try { TTransaction::close(); } catch (Exception $e2) {}
        }
    }
}
?>