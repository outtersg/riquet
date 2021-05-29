<?php

define('R', dirname(dirname(__FILE__)));

require_once R.'/vendor/gui/majeur/Majeur.php';
require_once R.'/vendor/gui/majeur/MajeurSiloPdo.php';
require_once R.'/vendor/gui/majeur/MajeurListeurDossiers.php';
require_once R.'/vendor/gui/majeur/MajeurJoueurPdo.php';

require_once R.'/app/app.php';

class MajeurRiquet extends Majeur
{
	public function __construct($app)
	{
		$this->app = $app;
		
		$silo = new MajeurSiloPdo($this->app->bdd);
		
		$listeur = new MajeurListeurDossiers(dirname(__FILE__).'/maj/maj-(?P<version>[0-9][.0-9]*)(?:-[^/]*)?\.(?:sql|php)');
		
		$joueur = new MajeurJoueurPdo($this->app->bdd);
		
		parent::__construct($silo, array($listeur), array($joueur));
	}
}

$app = new App();
$m = new MajeurRiquet($app);
$m->tourner();

?>
