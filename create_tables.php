<?php
require_once 'init.php';

use Adianti\Database\TTransaction;
use Adianti\Database\TDatabase;
use PDO;

TTransaction::open('default');

try {
    $connection = TDatabase::get();

    // Criar tabela cct_transmissao
    $sql = "CREATE TABLE IF NOT EXISTS cct_transmissao (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        conhecimento_id INTEGER NOT NULL,
        status TEXT DEFAULT 'pendente',
        data_transmissao DATETIME,
        protocolo_siscomex TEXT,
        xml_enviado TEXT,
        resposta_siscomex TEXT,
        tentativas INTEGER DEFAULT 0,
        proxima_tentativa DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(conhecimento_id) REFERENCES conhecimento(id)
    )";
    $connection->execute($sql);
    echo "✓ Tabela cct_transmissao criada/verificada\n";

    // Criar tabela cct_transmissao_items
    $sql = "CREATE TABLE IF NOT EXISTS cct_transmissao_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cct_transmissao_id INTEGER NOT NULL,
        chave_acesso_nfe VARCHAR(44) NOT NULL,
        valor_frete DECIMAL(12,2),
        ordem INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(cct_transmissao_id) REFERENCES cct_transmissao(id)
    )";
    $connection->execute($sql);
    echo "✓ Tabela cct_transmissao_items criada/verificada\n";

    // Verificar e adicionar campos a conhecimento se não existirem
    $sql = "PRAGMA table_info(conhecimento)";
    $result = $connection->query($sql);
    $columns = array();
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['name'];
    }

    if (!in_array('cct_transmissao_id', $columns)) {
        $sql = "ALTER TABLE conhecimento ADD COLUMN cct_transmissao_id INTEGER";
        $connection->execute($sql);
        echo "✓ Campo cct_transmissao_id adicionado a conhecimento\n";
    }

    if (!in_array('status_transmissao_mic', $columns)) {
        $sql = "ALTER TABLE conhecimento ADD COLUMN status_transmissao_mic VARCHAR(20) DEFAULT 'não_iniciado'";
        $connection->execute($sql);
        echo "✓ Campo status_transmissao_mic adicionado a conhecimento\n";
    }

    if (!in_array('protocolo_siscomex', $columns)) {
        $sql = "ALTER TABLE conhecimento ADD COLUMN protocolo_siscomex VARCHAR(255)";
        $connection->execute($sql);
        echo "✓ Campo protocolo_siscomex adicionado a conhecimento\n";
    }

    // Verificar e adicionar campo a fatura se não existir
    $sql = "PRAGMA table_info(fatura)";
    $result = $connection->query($sql);
    $columns = array();
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['name'];
    }

    if (!in_array('chave_acesso_nfe', $columns)) {
        $sql = "ALTER TABLE fatura ADD COLUMN chave_acesso_nfe VARCHAR(44)";
        $connection->execute($sql);
        echo "✓ Campo chave_acesso_nfe adicionado a fatura\n";
    }

    echo "\n✓ Todas as tabelas e campos foram criados/verificados com sucesso!\n";

} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    http_response_code(500);
}

TTransaction::close();
?>
