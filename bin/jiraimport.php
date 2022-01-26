<?php

require_once dirname(__FILE__).'/../app/app.php';
require_once R.'/app/import/JiraApi.php';

$app = new App();
$classe = $app->classe('JiraApi');
fprintf(STDERR, "Extraction via la classe %s\n", $classe);
$j = new $classe($app->config['jira'], $app->config['jira.idmdp']);
$j->lancer($argv);

?>
