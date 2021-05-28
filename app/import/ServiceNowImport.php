<?php

require_once dirname(__FILE__).'/Import.php';
require_once dirname(__FILE__).'/ServiceNow.php';

class ServiceNowImport extends Import
{
	const PASSE_ID = 0;
	const PASSE_F = 1;
	const PASSE_L = 2;
	
	public function pondre($csv)
	{
		for($passe = -1; ++$passe < 3;)
		{
			$f = fopen($csv, 'rb');
			
			// En-tête.
			$corr = fgetcsv($f, 0, ',', '"', "\000");
			
			switch($passe)
			{
				case ServiceNowImport::PASSE_ID:
					$colcs = array_intersect_key(array_flip($corr), ServiceNow::$CSV); // Les colonnes qui nous intéressent.
					
					$àGarderChamps = $àGarderLiens = array();
					foreach($colcs as $colc => $rien)
						switch(substr($cols = ServiceNow::$CSV[$colc], 0, 1))
						{
							case '': break; // Ne fera pas son chemin jusqu'au SQL (en tout cas pas directement, servira sans doute dans _retraiter()).
							case '@': $àGarderLiens[$colc] = substr($cols, 1); break;
							default: $àGarderChamps[$colc] = $cols; break;
						}
					
					$ids = array();
					break;
				case ServiceNowImport::PASSE_F:
					break;
				case ServiceNowImport::PASSE_L:
					// À FAIRE: émettre un delete from l where f in (<les ID pour update>) and t in (<les types dont nous sommes maître>).
					break;
			}
			
			// Lecture du contenu.
			while(($l = fgetcsv($f, 0, ',', '"', "\000"))) // Caractère d'échappemement: on ne veut que le "" par défaut, surtout pas le \ (car certains incidents se terminent par un chemin Windows clos par un \ juste avant le " CSV).
			{
				switch($passe)
				{
					case ServiceNowImport::PASSE_ID:
						// Par convention, la première colonne est celle qui servira d'identifiant.
						$ids[$l[0]] = true;
						break;
					case ServiceNowImport::PASSE_F:
						$id = $l[0];
						$l = array_combine($corr, $l);
						$l2 = $this->_retraiter(/*&*/ $l);
						$l = array_combine($àGarderChamps, array_intersect_key($l, $àGarderChamps)); // Champs CSV -> champs SQL.
						if($l2) $l += $l2;
						$this->_pondreFiche($l);
						$ids[$id] = 0; // À FAIRE: récupérer son identifiant généré pour tirer les liens? Avantage: créations de lien plus directs. Inconvénient: un accès à la base est requis, pour réserver les ID.
						break;
					case ServiceNowImport::PASSE_L:
						break;
				}
			}
			
			switch($passe)
			{
				case ServiceNowImport::PASSE_ID:
					// À FAIRE: en mode update, on aura besoin de lister ceux des ID déjà en base (et basculant donc en update plutôt qu'insert à la passe suivante).
					break;
				case ServiceNowImport::PASSE_F:
					break;
				case ServiceNowImport::PASSE_L:
					break;
			}
			
			fclose($f);
		}
	}
	
	protected function _retraiter(& $l)
	{
	}
}

?>
