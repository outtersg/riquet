<?php

require_once __DIR__.'/../../vendor/gui/util/navigateur.inc';

class SN
{
	public function __construct($url, $cache = null)
	{
		$this->_racine = $url;
		
		$this->_n = 
			isset($cache) && file_exists($cache) // && ($infos = stat($cache)) && $infos['mtime'] >= time() - 2 * 3600
			? unserialize(file_get_contents($cache))
			: null;
		if(!$this->_n)
			$this->_n = new Navigateur();
		
		$this->_cache = $cache;
		$this->_auth = null;
	}
	
	public function auth()
	{
		/* À FAIRE: cette authentification est spécifique à un SSO par CAS. Généraliser. */
		
		$this->_auth = true; // Au moins on aura essayé.
		
		$n = $this->_n;
		
		//$n->aller($this->_racine.$urlDeDéconnexionSSO);
		$suite = $n->allerEtTrouver($this->_racine, null, 'redirection', "/(?:top.location.href *= *|top.location.replace *\()'([^']*)'/");
		$pAuth = $n->aller($suite, null);
		
		// Soit on est redirigé vers la mire d'authentification, soit on était déjà en session et on ne fait que suivre.
		$eSamlr = "/SAMLResponse[^>]* value='([^']*)'/";
		if(preg_match($eSamlr, $pAuth, $samlr))
			$samlr = $samlr[1];
		else if(preg_match('/name="execution"[^>]*value="([^"]*)"/', $pAuth, $execu))
		{
			$idMdp = $this->_idMdp();
			$execu = $execu[1];
			$samlr = $n->allerEtTrouver($n->url(), array
			(
				'username' => $idMdp[0],
				'password' => $idMdp[1],
				'execution' => $execu,
				'_eventId' => 'submit',
				'rememberMe' => 'true',
				'_rememberMe' => 'on',
				'geolocation' => '',
				'submit' => '',
			), 'SAMLResponse', $eSamlr);
			// Retour à la mire d'auth?
			if(!$samlr && strpos($n->page, 'name="execution"') !== false && preg_match('#<p id="errorMessageContainer"[^>]*>(.*)</p>#Us', $n->page, $rexp))
			{
				$mess = trim(preg_replace('#<[^>]*>#', '', $rexp[1]));
				throw new Exception("Erreur d'authentification: ".$mess);
			}
		}
		else
			throw new Exception("Je suis perdu, page d'authentification inattendue à ".$n->url());
		$page = $n->allerEtTrouver($this->_racine.'/navpage.do', array('RelayState' => $this->_racine.'/navpage.do', 'SAMLResponse' => $samlr), 'moi', "/NOW.user.name = '(.*)'/");
		
		if(isset($this->_cache))
			file_put_contents($this->_cache, serialize($this->_n));
	}
	
	protected function _idMdp()
	{
		throw new Exception('Authentification demandée. Veuillez surcharger la méthode pour renvoyer [ identifiant, mot de passe, chaîne à trouver attestant de la bonne connexion ]');
	}
	
	public function csv($table, $champs = null, $filtre = null)
	{
		$filtre = isset($filtre) ? '&sysparm_query='.$this->_filtre($filtre) : '';
		$champs = isset($champs) ? '&sysparm_fields='.implode(',', array_keys($champs)) : '';
		// Avantage du CSV: il est accessible à n'importe quel utilisateur qui peut se connecter, sans nécessité de droit à l'API REST ou SOAP.
		// Inconvénient: il lui manque quelques petites subtilités (ex.: dates ISO).
		$r = $this->_n->aller($this->_racine.'/'.$table.'_list.do?CSV'.$filtre.$champs);
		if($r == '' && !isset($this->_auth))
		{
			$this->auth();
			$r = $this->_n->aller($this->_racine.'/'.$table.'_list.do?CSV'.$filtre.$champs);
		}
		return $r;
	}
	
	protected function _filtre($critères, $concat = '^')
	{
		$pos = 'c'; // c: clé; v: valeur.
		$ops = array_flip(array('<', '<=', '!=', '>=', '>'));
		$opsÉq = array_flip(array('IN', 'NOTIN'));
		$f = array();
		foreach($critères as $clé => $val)
		{
			switch($pos)
			{
				case 'c':
					// L'opérateur, s'il n'arrive pas par la suite, sera un '=' implicite.
					$o = '=';
					if(is_int($clé))
					{
						if(isset($ops[$val]))
							throw new Exception("Opérateur $val inattendu ici");
						$c = $val;
						$pos = 'v';
						break;
					}
					// À FAIRE: is_array, pour entrer dans une alternance de OU au lieu de ET.
					$c = $clé;
					$pos = 'v';
					//break; // Pas de break, on traite maintenant $val.
				case 'v':
					if(is_array($val))
					{
						if($o == '=') $o = 'IN';
						if(!isset($opsÉq[$o]))
							throw new Exception("Opérateur $o inattendu devant un tableau");
						$val = implode(',', $val);
					}
					else if(isset($ops[$val]))
					{
						$o = $val;
						break;
					}
					$f[] = $c.$o.$val;
					$pos = 'c';
					break;
			}
		}
		
		return isset($concat) ? implode($concat, $f) : $f;
	}
}

?>
