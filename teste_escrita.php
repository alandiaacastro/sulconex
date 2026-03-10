<?php
$tmp_dir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
$test_file = $tmp_dir . DIRECTORY_SEPARATOR . 'teste_permissoes.txt';
$message = "Teste de escrita em PHP realizado em: " . date('Y-m-d H:i:s') . PHP_EOL;

echo "Tentando escrever no arquivo: " . $test_file . "<br>";

// Verifica se o diretório tmp existe
if (!is_dir($tmp_dir)) {
    echo "ERRO: O diretório tmp NÃO EXISTE em: " . $tmp_dir . "<br>";
    echo "Por favor, crie o diretório 'tmp' manualmente na raiz do seu projeto (C:\\wamp64\\www\\sulconex81\\tmp\\) e tente novamente.<br>";
} else {
    echo "Diretório tmp encontrado em: " . $tmp_dir . "<br>";
    if (!is_writable($tmp_dir)) {
        echo "AVISO: O diretório tmp PARECE NÃO TER PERMISSÃO DE ESCRITA para o PHP em: " . $tmp_dir . "<br>";
    } else {
        echo "Diretório tmp parece ter permissão de escrita.<br>";
    }

    // Tenta escrever no arquivo
    if (file_put_contents($test_file, $message, FILE_APPEND)) {
        echo "SUCESSO! O arquivo teste_permissoes.txt foi escrito/atualizado em: " . $test_file . "<br>";
        echo "Conteúdo adicionado: " . htmlspecialchars($message) . "<br>";
        echo "Verifique o arquivo para confirmar.<br>";
    } else {
        echo "FALHA AO ESCREVER! Não foi possível escrever no arquivo: " . $test_file . "<br>";
        echo "Isso indica um problema de permissão de escrita para o servidor web na pasta tmp/ ou no arquivo.<br>";
        $error = error_get_last();
        if ($error) {
            echo "Último erro do PHP: " . htmlspecialchars($error['message']) . "<br>";
        }
    }
}
?>