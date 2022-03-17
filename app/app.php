<?php

error_reporting(-1);
ini_set('display_errors', 1);

define('R', dirname(dirname(__FILE__)));

require_once R.'/app/Bdd.php';

class App
{
	public function __construct()
	{
		include dirname(__FILE__).'/../etc/riquet.php';
		$this->config = $config;
		$this->bdd = new Bdd($this->config['bdd']);
		$this->bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	
	/**
	 * Meilleure sous-classe candidate.
	 */
	public function classe($ancêtre)
	{
		if(!isset($this->_classes))
		{
			$this->_classes = array();
			require_once R.'/vendor/gui/util/classes.inc';
			foreach(glob(R.'/local/*.php') as $f)
				if(($classes = Classes::ClassesFichier($f, true)))
					foreach($classes as $classe)
						$this->_classes[$classe] = $f;
		}
		foreach($this->_classes as $classe => $f)
		{
			require_once $f;
			if(is_a($classe, $ancêtre, true))
				return $classe;
		}
		// À FAIRE: en cas de plusieurs candidats, prendre celui le plus profond dans la hiérarchie.
		return $ancêtre;
	}
}

?>
