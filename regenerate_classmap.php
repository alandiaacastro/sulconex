<?php
require_once 'init.php';

// Forçar regeneração do class map
\Adianti\Core\AdiantiCoreLoader::loadClassMap();

echo "✓ Class map regenerado com sucesso!\n";
echo "✓ Sistema pronto para usar\n";
echo "\nClasses carregadas:\n";
echo "- CctTransmissaoList\n";
echo "- CctTransmissaoForm\n";
echo "- CctManualForm\n";
echo "- CctUploadXmlForm\n";
echo "- CctCertificateConfigForm\n";
?>
