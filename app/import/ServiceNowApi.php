<?php

require_once __DIR__.'/../../vendor/gui/util/navigateur.inc';

class ServiceNowApi
{
	public function __construct($url, $cache = null)
	{
		$this->_racine = $url;
		
		$this->_n = 
			isset($cache) && file_exists($cache) && ($infos = stat($cache)) && $infos['mtime'] >= time() - 2 * 3600
			? unserialize(file_get_contents($cache))
			: null;
		if(!$this->_n)
			$this->_n = new Navigateur();
		
		$this->_cache = $cache;
		$this->_auth = null;
		$this->_configAuth =
		[
			'pageVérif' => $this->_racine.'/navpage.do',
			'exprVérif' => "/NOW.user.name = '(.*)'/",
		];
	}
	
	public function tout()
	{
		$snic = $GLOBALS['app']->classe('ServiceNowImport');
		$sImport = new $snic();
		if(isset($this->mode)) $sImport->mode = $this->mode;
		$champs = $sImport->champsCsvSql();
		return $this->csv('incident', $champs, $this->filtre);
	}
	
    // À FAIRE: changements d'affectataire. Là si un élément a disparu de notre radar (est échu à quelqu'un d'autre), nos filtres ne le renverront pas et donc il restera éternellement à l'état "chez nous" (sauf à effacer la base et faire un tout()).
	public function actuel()
	{
		$nJours = 7;
		$this->filtre[] = 'sys_updated_on';
		$this->filtre[] = '>=';
		$this->filtre[] = substr(date('c', time() - 3600 * 24 * $nJours), 0, 19);
		return $this->tout();
	}
	
	const INTERNE = 'interne';
	const SSO = 'SSO';
	
	public function auth($page = null)
	{
		/* À FAIRE: cette authentification est spécifique à un SSO par CAS. Généraliser. */
		
		$this->_auth = true; // Au moins on aura essayé.
		
		$n = $this->_n;
		
		$expRedir = "/(?:.location.href *= *|.location.replace *\()['\"]([^'\"]*)['\"]/"; // On repose sur la présence d'une redirection JS, nous attendant à ce que la page ait une telle formule "on load".
		
		// Si l'auth est appelée depuis une page qui, sans session d'ouverte, a déjà entamé la séquence d'authentification, on reprend où on détecte qu'il en est.
		// Sinon on provoquera un accès à page pour récupérer les infos dont on a besoin.
		if(!($page && preg_match($expRedir, $page, $réponses, 0) && ($suite = $réponses[1])))
		{
		//$n->aller($this->_racine.$urlDeDéconnexionSSO);
		// A-t-on une redirection type SSO?
		$urlConnexion = isset($this->_configAuth['urlConnexion']) ? $this->_configAuth['urlConnexion'] : $this->_racine.'/';
			$suite = $this->_n->allerEtTrouver($urlConnexion, null, 'redirection', $expRedir);
		}
		$lard = null; // Le paquet de viandasse qu'on devra pousser à la page cible pour lui signifier par exemple le jeton du SSO.
		if($suite)
		{
		$pAuth = $n->aller($suite, null);
		
		// Soit on est redirigé vers la mire d'authentification, soit on était déjà en session et on ne fait que suivre.
		$eSamlr = "/SAMLResponse[^>]* value=['\"]([^'\"]*)['\"]/";
		if(preg_match($eSamlr, $pAuth, $samlr))
			$samlr = $samlr[1];
		else if(preg_match('/name="execution"[^>]*value="([^"]*)"/', $pAuth, $execu))
		{
			$idMdp = $this->_idMdp(ServiceNowApi::SSO);
			
			preg_match_all('/name="([^"]*)"[^>]*value="([^"]*)"/', $pAuth, $prérempl, PREG_SET_ORDER);
			$formu = array();
			foreach($prérempl as $pr)
				$formu[$pr[1]] = $pr[2];
			
			$formu = array
			(
				'username' => $idMdp[0],
				'password' => $idMdp[1],
			) + $formu;
			
			$samlr = $n->allerEtTrouver($n->url(), $formu, 'SAMLResponse', $eSamlr);
			// Retour à la mire d'auth?
			if(!$samlr && strpos($n->page, 'name="execution"') !== false && preg_match('#<p id="errorMessageContainer"[^>]*>(.*)</p>#Us', $n->page, $rexp))
			{
				$mess = trim(preg_replace('#<[^>]*>#', '', $rexp[1]));
				throw new Exception("Erreur d'authentification: ".$mess);
			}
		}
		else
			throw new Exception("Je suis perdu, page d'authentification inattendue à ".$n->url());
			$lard = array('RelayState' => $this->_racine.'/navpage.do', 'SAMLResponse' => $samlr);
		}
		else
		{
			// Mire d'authentification classique (mais coincée dans un iframe un peu compliqué, on préfère attaquer en direct).
			$suite = $n->aller('/welcome.do');
			preg_match_all('|<input[^>]*name="([^">]*)"[^>]*value="([^">]*)"|', $suite, $r);
			$formu = array_combine($r[1], $r[2]);
			$idMdp = $this->_idMdp(ServiceNowApi::INTERNE);
			$formu['user_name'] = $idMdp[0];
			$formu['user_password'] = $idMdp[1];
			$formu['sys_action'] = 'sysverb_login';
			$suite = $n->aller('/login.do', $formu);
			// Redirigé, on peut trouver dans la page un g_url = puis un nav_to.do?uri=<g_url>
			//$suite = $n->aller('/navpage.do');
			if(preg_match('#outputmsg_error.*<div class="outputmsg_text"[^>]*>(.*)</div>#Us', $suite, $rexp))
			{
				// Au passage si l'on est sur cette page on a le lien pour réinitialiser son mot de passe.
				$mess = trim(preg_replace('#<[^>]*>#', '', $rexp[1]));
				throw new Exception("Erreur d'authentification: ".$mess);
			}
		}
		$page = $n->allerEtTrouver($this->_configAuth['pageVérif'], $lard, 'moi', $this->_configAuth['exprVérif']);
		if(!$page)
			throw new Exception("Authentification échouée, impossible de retrouver notre nom dans la page.");
		else
			fprintf(STDERR, "[32mBienvenue %s![0m\n", $page);
		// Clé pour les appels JSON.
		if(preg_match_all('/g_ck *= *[\'"]([^\'"]*)[\'"]/', $n->page, $résrég))
			$this->_n->jeton = $résrég[1][0];
		
		if(isset($this->_cache))
			file_put_contents($this->_cache, serialize($this->_n));
	}
	
	protected function _idMdp($canal)
	{
		throw new Exception("Authentification demandée sur le canal $canal. Veuillez surcharger la méthode pour renvoyer [ identifiant, mot de passe ]");
	}
	
	public function aller($url, $formu = null, $tenterAuth = true)
	{
		$enTêtes = [];
		$urlBrute = $url;
		if(is_array($url))
		{
			$trucs = $url;
			unset($url);
			foreach($trucs as $clé => $truc)
				// À FAIRE: si clé alpha, en-tête supplémentaire.
				switch($truc)
				{
					case 'JSON':
						$enTêtes['Accept'] = 'application/json;charset=utf-8';
						$enTêtes['Content-Type'] = 'application/json';
						if(is_array($formu) || is_object($formu))
							$formu = json_encode($formu);
						break;
					default:
						$url = $truc;
				}
		}
		if(substr($url, 0, 1) == '/')
			$url = $this->_racine.$url;
		if(isset($this->_n->jeton))
			$enTêtes['X-UserToken'] = $this->_n->jeton;
		
		$r = $this->_n->obtenir($url, $formu, true, $enTêtes);
		$err = // Selon que l'on tape une ressource HTML ou XML.
			$r == ''
			|| strpos($r, 'invalid token') !== false
			|| strpos($r, '<script>window.top.location.replace(') !== false
			|| preg_match("#onload *=.*window.location.href *= *['\"]#", $r)
		;
		if($err && $tenterAuth && !isset($this->_auth))
		{
			$this->auth($r);
			$r = $this->aller($urlBrute, $formu, false);
		}
		return $r;
	}
	
	public function csv($table, $champs = null, $filtre = null)
	{
		$filtre = isset($filtre) ? '&sysparm_query='.$this->_filtre($filtre) : '';
		$champs = isset($champs) ? '&sysparm_fields='.implode(',', array_keys($champs)) : '';
		// Avantage du CSV: il est accessible à n'importe quel utilisateur qui peut se connecter, sans nécessité de droit à l'API REST ou SOAP.
		// Inconvénient: il lui manque quelques petites subtilités (ex.: dates ISO).
		$r = $this->aller('/'.$table.'_list.do?CSV'.$filtre.$champs.'&sysparm_orderby=number');
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
	
	public $mode;
	public $filtre;
	protected $_racine;
	protected $_n;
	protected $_cache;
	protected $_auth;
	protected $_configAuth;
}

?>
