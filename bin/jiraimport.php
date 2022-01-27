<?php

require_once dirname(__FILE__).'/../app/app.php';
require_once R.'/app/import/JiraApi.php';
require_once R.'/app/import/JiraImport.php';

$app = new App();
$classe = $app->classe('JiraApi');
$cji = $app->classe('JiraImport');
fprintf(STDERR, "Extraction via [ %s -> %s ]\n", $classe, $cji);
$ji = new $cji();
$j = new $classe($app->config['jira'], $app->config['jira.idmdp'], $ji);
$j->lancer($argv);

?>
