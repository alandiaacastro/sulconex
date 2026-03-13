<?php
$db=new PDO('sqlite:app/database/sulconex81.db');
foreach($db->query("SELECT id, tipo_veiculo FROM tabela_fretes ORDER BY id") as $r){
 echo $r['id'], ' | [', $r['tipo_veiculo'], "]\n";
}
