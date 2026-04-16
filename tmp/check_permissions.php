<?php
$db = new PDO('sqlite:app/database/permission.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
echo "TABLES:\n";
foreach ($tables as $t) echo $t, "\n";

echo "\nPROGRAM EXISTS:\n";
$stmt=$db->prepare("SELECT id,name,controller FROM system_program WHERE controller = ? OR name LIKE ?");
$stmt->execute(['TabelaFreteList','%Tabela%Frete%']);
foreach($stmt as $r){ echo implode(' | ', [$r['id'],$r['name'],$r['controller']]),"\n"; }

echo "\nGROUPS:\n";
foreach($db->query("SELECT id,name FROM system_group ORDER BY id") as $g){ echo $g['id'],' | ',$g['name'],"\n"; }
