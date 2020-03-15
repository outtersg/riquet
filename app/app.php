<?php

error_reporting(-1);
ini_set('display_errors', 1);

class App
{
	public function __construct()
	{
		include dirname(__FILE__).'/../etc/riquet.php';
		$this->config = $config;
		$this->bdd = new PDO($this->config['bdd']);
		$this->bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
}

?>
