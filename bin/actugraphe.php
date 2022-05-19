<?php

/* Forçage de sous-module? */

$Type = '';
if($argv[1] == '-t')
{
	$Type = ucfirst($argv[2]);
	array_splice($argv, 1, 2);
}

require_once dirname(__FILE__).'/../app/app.php';
require_once R.'/app/import/'.$Type.'Source.php';

$app = new App();
$cs = $app->classe($Type.'Source');
$s = new $cs($app);
$s->lancer($argv);

?>
