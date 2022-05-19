<?php

require_once R.'/app/import/Source.php';
require_once R.'/app/import/JiraApi.php';
require_once R.'/app/import/JiraImport.php';

class JiraSource extends Source
{
	public function __construct($app)
	{
$classe = $app->classe('JiraApi');
$cji = $app->classe('JiraImport');
		if(isset($GLOBALS['argv']))
fprintf(STDERR, "Extraction via [ %s -> %s ]\n", $classe, $cji);
$ji = new $cji();
$j = new $classe($app->config['jira'], $app->config['jira.idmdp'], $ji);
		$this->import = $j;
	}
}

?>
