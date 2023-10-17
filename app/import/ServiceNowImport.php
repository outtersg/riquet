<?php

require_once dirname(__FILE__).'/Import.php';
require_once dirname(__FILE__).'/ServiceNow.php';

class ServiceNowImport extends Import
{
	const PASSE_ID = 0;
	const PASSE_F = 1;
	const PASSE_L = 2;
	const PASSE_COMM = 3;
	const N_PASSES = 4;
	
	const ENS_INFO = 1;
	const ENS_DESC = 2;
	const ENS_COMM = 4;
	
	protected $_typeFiche = 't'; // Tâche.
	public $mode = ServiceNowImport::ENS_INFO;
	
	public function champsCsvSql()
	{
		$r = ServiceNow::$CSV;
		if(!($this->mode & ServiceNowImport::ENS_INFO)) $r = array_intersect_key($r, array('number' => 1, 'sys_id' => 1));
		if($this->mode & ServiceNowImport::ENS_DESC) $r += array('description' => 'desc');
		if($this->mode & ServiceNowImport::ENS_COMM) $r += array('comments_and_work_notes' => 'comm');
		
		return $r;
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
	
	protected function _modeEnTêtes($enTêtes)
	{
		if(!isset($enTêtes['state'])) $this->mode ^= ServiceNowImport::ENS_INFO;
		if(isset($enTêtes['description'])) $this->mode |= ServiceNowImport::ENS_DESC;
		if(isset($enTêtes['comments_and_work_notes'])) $this->mode |= ServiceNowImport::ENS_COMM;
	}
	
	public function pondre($csv)
	{
		for($passe = -1; ++$passe < ServiceNowImport::N_PASSES;)
		{
			$f = fopen($csv, 'rb');
			
			// En-tête.
			$corr = fgetcsv($f, 0, ',', '"', "\000");
			
			switch($passe)
			{
				case ServiceNowImport::PASSE_ID:
					$corri = array_flip($corr);
					$this->_modeEnTêtes($corri);
					$CSVSQL = $this->champsCsvSql();
					$colcs = array_intersect_key($corri, $CSVSQL); // Les colonnes qui nous intéressent.
					$colId = $this->colId($colcs);
					$numColId = $colcs[$colId];
					$champId = $CSVSQL[$colId];
					
					$àGarderChamps = $àGarderLiens = array();
					foreach($colcs as $colc => $rien)
						if(($cols = $CSVSQL[$colc]) !== null)
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
					// Pour le moment on fait le ménage:
					if($this->mode & ServiceNowImport::ENS_INFO)
						echo $this->sql->req("delete from f where $champId in (%s);\n", array_keys($ids)); // À FAIRE: s'assurer que nous en sommes la source.
					$liens = array();
					break;
				case ServiceNowImport::PASSE_L:
					if(!($this->mode & ServiceNowImport::ENS_INFO)) break;
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
			if(($this->mode & ServiceNowImport::ENS_INFO) || ($passe != ServiceNowImport::PASSE_F))
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
				case ServiceNowImport::PASSE_COMM:
					if(!($this->mode & (ServiceNowImport::ENS_DESC|ServiceNowImport::ENS_COMM))) break;
					$l = array_combine($corr, $l);
					$l = array_combine($àGarderChamps, array_intersect_key($l, $àGarderChamps)); // Champs CSV -> champs SQL.
					$this->_pondreFiche($l, 'id_ext');
					// À FAIRE: découper sur les dates.
					// À FAIRE: repérer l'auteur.
					// À FAIRE: simple remplacement si on détecte un commentaire même fiche même date.
					break;
				}
			}
			
			fclose($f);
		}
	}
	
	protected function _retraiter(& $l)
	{
		if($l['state'] == 'Clos' && $l['closed_at'] == '')
			$l['closed_at'] = $l['resolved_at'];
		
		return array
		(
			't' => $this->_typeFiche,
		);
	}
}

?>
