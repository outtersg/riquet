<?php

require_once R.'/app/import/Source.php';
require_once R.'/app/import/JiraApi.php';
require_once R.'/app/import/JiraImport.php';

class JiraSource extends Source
{
	public function __construct($app, $courtCircuiterLaBase = false)
	{
$classe = $app->classe('JiraApi');
$cji = $app->classe('JiraImport');
		if(isset($GLOBALS['argv']))
fprintf(STDERR, "Extraction via [ %s -> %s ]\n", $classe, $cji);
		$ji = new $cji($courtCircuiterLaBase);
		if(!$courtCircuiterLaBase)
		$ji->bdd = $app->bdd;
		// Si on court-circuite la base, les liens dénichés doivent être émis au fur et à mesure et non pas en une fois à la fin,
		// car le court-circuitage de base indique que l'on est pilotés directement par un Parcours, dont le principe même est de découvrir le graphe en suivant les liens.
		$j = new $classe($app->config['jira'], $app->config['jira.idmdp'], $ji, $courtCircuiterLaBase);
		$this->import = $j;
		$this->persisteur = $ji;
	}
}

?>
