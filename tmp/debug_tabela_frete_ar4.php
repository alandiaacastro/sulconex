<?php
require_once 'init.php';
TTransaction::open('sample');
$repo = new TRepository('TabelaFrete');
$items = $repo->load(new TCriteria());
foreach ((array)$items as $it) {
    echo 'id=', $it->id, ' tipo_veiculo=[', (string)($it->tipo_veiculo ?? ''), "]\n";
}
TTransaction::close();