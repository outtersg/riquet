<?php

require_once dirname(__FILE__).'/../app/app.php';
require_once R.'/app/import/ServiceNowImport.php';

$app = new App();
$classe = $app->classe('ServiceNowImport');
fprintf(STDERR, "Import via la classe %s\n", $classe);
$sni = new $classe();
$sni->pondre($argv[1]);

?>
