<?php

require_once dirname(__FILE__).'/Import.php';
require_once dirname(__FILE__).'/ServiceNow.php';

class ServiceNowImport extends Import
{
	const PASSE_ID = 0;
	const PASSE_F = 1;
	const PASSE_L = 2;
	
	protected $_typeFiche = 't'; // Tâche.
	
	public function champsCsvSql()
	{
		return ServiceNow::$CSV;
	}
	
	/**
	 * Retourne, parmi une liste de colonnes CSV issues de ServiceNow, celle à choisir pour identifiant unique.
	 */
	public function colId($colonnes)
	{
		foreach(array('number', 'sys_id') as $colCsv)
			if(array_key_exists($colCsv, $colonnes))
				return $colCsv;
		throw new Exception('Aucune colonne ID n\'est trouvable dans le CSV');
	}
	
	public function pondre($csv)
	{
		$CSVSQL = $this->champsCsvSql();
		
		for($passe = -1; ++$passe < 3;)
		{
			$f = fopen($csv, 'rb');
			
			// En-tête.
			$corr = fgetcsv($f, 0, ',', '"', "\000");
			
			switch($passe)
			{
				case ServiceNowImport::PASSE_ID:
					$colcs = array_intersect_key(array_flip($corr), $CSVSQL); // Les colonnes qui nous intéressent.
					$colId = $this->colId($colcs);
					$numColId = $colcs[$colId];
					$champId = $CSVSQL[$colId];
					
					$àGarderChamps = $àGarderLiens = array();
					foreach($colcs as $colc => $rien)
						switch(substr($cols = $CSVSQL[$colc], 0, 1))
						{
							case '': break; // Ne fera pas son chemin jusqu'au SQL (en tout cas pas directement, servira sans doute dans _retraiter()).
							case '@': $àGarderLiens[$colc] = substr($cols, 1); break;
							default: $àGarderChamps[$colc] = $cols; break;
						}
					
					$ids = array();
					break;
				case ServiceNowImport::PASSE_F:
					// À FAIRE: en mode update, on aura besoin de lister ceux des ID déjà en base (et basculant donc en update plutôt qu'insert à la passe suivante).
					$liens = array();
					break;
				case ServiceNowImport::PASSE_L:
					$this->_pondreSupprLiens('', $champId, array_keys($ids), $àGarderLiens);
					foreach($liens as $typeLien => $cibles)
					{
						if($typeLien == '^')
							$typeFicheSubst = $this->_typeFiche;
						else if(isset(static::$TYPE_FICHE_SUBST[$typeLien]))
							$typeFicheSubst = static::$TYPE_FICHE_SUBST[$typeLien];
						else
							$typeFicheSubst = null;
						$this->_pondreFicheSubst('', $typeFicheSubst, array_keys($cibles));
						foreach($cibles as $cible => $as)
							$this->_pondreLien('', $champId, $as, $typeLien, 'nom', $cible, $typeFicheSubst);
					}
					break;
			}
			
			// Lecture du contenu.
			while(($l = fgetcsv($f, 0, ',', '"', "\000"))) // Caractère d'échappemement: on ne veut que le "" par défaut, surtout pas le \ (car certains incidents se terminent par un chemin Windows clos par un \ juste avant le " CSV).
			{
				switch($passe)
				{
					case ServiceNowImport::PASSE_ID:
						// Par convention, la première colonne est celle qui servira d'identifiant.
						$ids[$l[$numColId]] = true;
						break;
					case ServiceNowImport::PASSE_F:
						$id = $l[$numColId];
						$l = array_combine($corr, $l);
						$l2 = $this->_retraiter(/*&*/ $l);
						$lCsv = $l;
						$l = array_combine($àGarderChamps, array_intersect_key($l, $àGarderChamps)); // Champs CSV -> champs SQL.
						if($l2) $l += $l2;
						$this->_pondreFiche($l);
						$ids[$id] = 0; // À FAIRE: récupérer son identifiant généré pour tirer les liens? Avantage: créations de lien plus directs. Inconvénient: un accès à la base est requis, pour réserver les ID.
						// On liste aussi les liens dont on aura besoin.
						// Et tant qu'à faire le lien lui-même (à réévaluer sur de très grosses instances).
						foreach(array_filter(array_intersect_key($lCsv, $àGarderLiens)) as $champCsv => $val)
						{
							$typeLien = $àGarderLiens[$champCsv];
							$liens[$typeLien][$val][] = $id;
						}
						break;
					case ServiceNowImport::PASSE_L:
						break 2; // En fait pas besoin de lire pour cette passe.
				}
			}
			
			fclose($f);
		}
	}
	
	protected function _retraiter(& $l)
	{
	}
}

?>
