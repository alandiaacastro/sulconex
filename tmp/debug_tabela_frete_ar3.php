<?php
require_once 'init.php';
TTransaction::open('sample');
$repo = new TRepository('TabelaFrete');
$criteria = new TCriteria();
$items = $repo->load($criteria);
foreach ((array)$items as $it) {
    echo 'id=', $it->id, ' tipo_veiculo=[', (string)($it->tipo_veiculo ?? ''), '] origem=[', (string)($it->origem ?? ''), "]\n";
}
TTransaction::close();