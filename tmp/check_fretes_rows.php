<?php
$db=new PDO('sqlite:app/database/sulconex81.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach($db->query("SELECT id, origem, destino, tipo_veiculo, valor_frete FROM tabela_fretes ORDER BY id LIMIT 20") as $r){
  echo $r['id'],' | ',$r['origem'],' | ',$r['destino'],' | [',$r['tipo_veiculo'],'] | ',$r['valor_frete'],PHP_EOL;
}
