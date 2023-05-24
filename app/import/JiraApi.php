<?php
/*
 * Copyright (c) 2022 Guillaume Outters
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

require_once R.'/vendor/gui/util/AffT.php';
include_once R.'/vendor/gui/dklab_soapclient/lib/Dklab/SoapClient.php';

require_once R.'/app/Parcours.php';

class JiraApi
{
	use MonoChargeur { charger as chargerBloc; }
	
	const OUI = 1;
	const NON = -1;
	const BOF = 0;
	const BONDACC = 2; // Ç'aurait dû être BOF, mais suite à insistance de la direction, c'est OUI.
	const RIEN = -99;
	
	protected $mode = 2;
	protected $typesIgnorés = [];
	
	static $Couls = array
	(
		self::OUI => '32',
		self::NON => '31',
		self::BOF => '33',
		self::BONDACC => '36',
		self::RIEN => '90',
	);
	
	public function __construct($url, $idMdp, $sortie, $liensAuFurEtÀMesure = false)
	{
		$this->_racine = $url;
		$this->_idMdp = $idMdp;
		$this->_sortie = $sortie;
		$this->_lafeàm = $liensAuFurEtÀMesure;
	}
	
	public function lancer($params)
	{
		$boulot = [ '-' => [], '+' => [], '=' => [] ];
		$plaf = 5; // Plafond. Si trop de liens on ne suit pas
		
		for($i = 0; ++$i < count($params);)
			switch($param = $params[$i])
			{
				default:
					switch($mode = substr($param, 0, 1))
					{
						case '-':
						case '+':
						case '=':
							$boulot[$mode][substr($param, 1)] = 1;
							break;
						default:
							$boulot['+'][$param] = 1;
							break;
					}
			}
		
		$this->faire(array_keys($boulot['+'] + $boulot['=']), $plaf, array_keys($boulot['-']), array_keys($boulot['+']));
	}
	
	/**
	 * Parcourt un graphe JIRA en partant d'$àFaire.
	 * 
	 * @param array $àFaire Numéro des JIRA par lesquels attaquer le graphe.
	 * @param int|null $plaf Plafond de liens (si un JIRA possède plus de $plaf liens, ceux-ci ne sont pas parcourus).
	 * @param array $moins Ne pas parcourir les liens de ces JIRA.
	 * @param array $plus Remonter les liens de ces JIRA même si outre $plaf.
	 */
	public function faire($àFaire, $plaf, $moins, $plus)
	{
		$p = new Parcours($this);
		
		if(defined('STDERR'))
		$this->_aff = new AffT(STDERR);
		
		$this->_sortie->début();
		
		list($nœuds, $liens) = $p->parcourir(array_merge($àFaire, $plus), $moins, [], $plaf);
		
		$this->_sortie->pousserLiens($liens);
		
		$this->_sortie->fin();
		
		if(isset($this->_aff))
		$this->_aff->affl(null, '');
	}
	
	public function ignorerLié($lien)
	{
		if(in_array($lien->fields->issuetype->name, $this->typesIgnorés))
			return true;
	}
	
	public function charger($àFaire, $parcours)
	{
		// 3 modes:
		// - chargement 1 à 1
		// - chargement par lots (requête 1 à 1 mais interprétation par lots)
		// - chargement en parallèle
		
		if($this->mode >= 2 && !class_exists('Dklab_SoapClient_Curl'))
			$this->mode = 1;
		
		switch($this->mode)
		{
			case 0: return $this->_chargerUnParUn($àFaire);
			case 1: return $this->chargerBloc($àFaire);
			case 2: return $this->chargerAsync($àFaire, $parcours);
		}
	}
	
	public function chargerAsync($àFaire, $parcours)
	{
		if(isset($this->_multicurl))
			throw new Exception('charger() appelée alors que la précédente n\'a pas fini');
		
		$this->_parcours = $parcours;
		
		$this->_reqs = [];
		$this->_lancerAsync($àFaire);
		while(($réps = $this->_multicurl->getAvailableResults()) !== null)
			if(!count($réps))
				usleep(10000);
			// Le traitement des résultats est fait par callback, on ne renvoie donc rien ici.
		
		$this->_multicurl = null;
		// On ne renvoie rien: on a notifié le Parcours (notre appelant, qui attend notre résultat) au fur et à mesure que l'on recevait les trames.
		return [];
	}
	
	protected function _lancerAsync($àFaire)
	{
		foreach($àFaire as $id)
			$this->_reqs[$this->chargerUn($id, true)] = $id;
	}
	
	public function chargerUn($num, $async = false)
	{
			$this->_aff($num);
		$j = $this->api('GET', '/issue/'.$num, null, $async, $async ? [ $this, '_reçu', $num ] : null);
		if(!$async) $j = $this->_traiterRés($num, $j, false);
		return $j;
	}
	
	protected function _traiterRés($num, $j, $async = false)
	{
			$j->fields->id = $j->id;
			$j->fields->key = $j->key;
			$j = $j->fields; // L'enrobage ne nous intéresse pas.
		
		// On consolide ici les liens, tel que chargerLiens devra les renvoyer.
		$liens = [];
		$nLiens = 0;
			if(isset($j->issuelinks))
				foreach($j->issuelinks as $lien)
				{
					if($this->ignorerLié(isset($lien->outwardIssue) ? $lien->outwardIssue : $lien->inwardIssue)) continue;
				if(isset($lien->outwardIssue)) { $de = $num; $vers = $lien->outwardIssue->key; }
				else { $vers = $num; $de = $lien->inwardIssue->key; }
					$liens[$lien->type->inward][$de][$vers] = 1;
				++$nLiens;
				}
		$j->_liens = $liens;
		$j->_nLiens = $nLiens;
		
		// En plus des liens inter-fiches, on peut avoir des relations de parenté à fondre dans le même moule.
		
		// La réciproque en premier:
		if(isset($this->épopée) && isset($j->{$this->épopée}))
		{
			$j->_liens['v'][$j->{$this->épopée}][$num] = 1;
			++$j->_nLiens;
		}
		if($j->issuetype->name == 'Epic')
		{
			// /!\ Bascule imminente sur parent: https://community.developer.atlassian.com/t/deprecation-of-the-epic-link-parent-link-and-other-related-fields-in-rest-apis-and-webhooks/54048
			$r = $this->api('GET', '/search?fields=key&jql="Epic%20Link"='.$num, null, $async, [ $this, '_traiterRésFils', $num, $j, $async ]);
			if($async) return; // Si $async, c'est _traiterRésFils qui appelera la suite des opérations.
		}
		
		return $this->_finaliser($num, $j, $async);
	}
	
	public function _finaliser($num, $j, $async)
	{
		// Poussage!
		
		$this->_sortie->pousserFiche($j);
		
		if($async)
			$this->_postfiche($num, $j);
		else
		return $j;
	}
	
	public function _traiterRésFils($num, $j, $async, $r)
	{
		$ro = json_decode($r['body']);
		foreach($ro->issues as $fil)
			$j->_liens['v'][$num][$fil->key] = 1;
		$j->_nLiens += count($ro->issues);
		
		$this->_finaliser($num, $j, $async);
		
		return $r;
	}
	
	public function notifRetenu($num, $j, $liés, $niveauRetenu)
	{
		$cr = $j->summary;
			if(count($liés))
				$cr .= ' [95m[-> '.implode(', ', array_keys($liés)).'][0m';
		$bien = self::OUI;
		switch($niveauRetenu)
		{
			case Parcours::GROS:
				$bien = self::BOF;
				$cr .= ' [33m(trop de liens)[0m';
				break;
			case Parcours::FORCÉ:
				$cr .= ' ['.self::$Couls[self::BONDACC].'m(trop de liens mais forcé)[0m';
				break;
			}
			$this->_aff($num, $bien, $cr);
	}
	
	public function chargerLiensUn($nœud)
	{
		return $nœud->_liens;
	}
	
	protected function _aff($num, $rés = null, $détail = null)
	{
		if(isset($this->traceur))
			$this->traceur->tracer($num, $rés, $détail);
		
		// À FAIRE: ce qui suit devrait aller dans un traceur générique d'imports.
		
		if(!isset($this->_aff)) return;
		
		if($détail === null && $rés)
		{
			$détail = $rés;
			$rés = self::OUI;
		}
		$coul = self::$Couls[isset($rés) ? $rés : self::RIEN];
		$coul = '['.$coul.'m';
		$neutre = '[0m';
		
		$numL = isset($this->_lignesDiag[$num]) ? $this->_lignesDiag[$num] : null;
		$nesp = 4;
		$tab = str_repeat(' ', $nesp - ((strlen($num) + 2) % $nesp)); // Émulation de tabulation pour AffT qui risque de tout décaler.
		$aff = sprintf("%s[%s]%s$tab", $coul, $num, $neutre);
		if($détail)
			$aff .= $détail;
		$this->_aff->affl($numL, $aff);
		if(!isset($numL))
			$this->_lignesDiag[$num] = $this->_aff->nl - 1;
	}
	
	public function api($méthode, $uri, $params = null, $async = false, $traitement = null)
	{
		$enTêtes = array
		(
			'Content-Type: application/json;charset=UTF-8',
		);
		$o =
		[
        	CURLOPT_URL => $this->_racine.'/rest/api/latest'.$uri,
			CURLOPT_CUSTOMREQUEST => $méthode,
			CURLOPT_USERPWD => $this->_idMdp,
			CURLOPT_HTTPHEADER => $enTêtes,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 15,
		];
		if($params)
			$o[CURLOPT_POSTFIELDS] = $params;
		
		// On utilise le multi_curl uniquement si nécessaire et disponible.
		// Il pourrait fonctionner tout aussi bien sur les modes "simples" (sans le if),
		// mais ne sortons le grand jeu que si nécessaire.
		if($this->mode >= 2 && $async)
		{
			if($traitement)
				$o['callback'] = $traitement;
			
			if(!isset($this->_multicurl))
			{
				$this->_multicurl = new Dklab_SoapClient_Curl();
				$this->_multicurl->throttle(0x18);
			}
			$clé = $this->_multicurl->addRequest($o, null);
			if($async)
				return $clé;
			
			$r = $this->_multicurl->getResult($clé);
			// À FAIRE: un peu plus d'exploration du code retour?
			$r = $r['body'];
		}
		else
		{
		$c = curl_init();
		curl_setopt_array($c, $o);
		$r = curl_exec($c);
		curl_close($c);
			if($traitement)
			{
				$r0 = [ 'body' => $r ];
				$params = array_merge(array_slice($traitement, 2), [ $r0 ]);
				$traitement = array_slice($traitement, 0, 2);
				call_user_func_array($traitement, $params);
				$r = $r0['body'];
			}
		}
		
		return json_decode($r);
	}
	
	public function _reçu($num, $r)
	{
		if($r['http_code'] == 403)
		{
			$libellé = $r['body'] ? $r['body'] : 'Erreur HTTP '.$r['http_code'];
			$this->_aff($num, self::NON, $libellé);
			// À FAIRE: récupérer tout ça éventuellement du lien (inwardMachin), qui avait tout de même un peu d'info.
			$j = (object)
			[
				'id' => '-'.$num,
				'key' => $num,
				'fields' => (object)
				[
					'summary' => $libellé,
					'status' => (object)[ 'name' => 'Error' ],
					'issuetype' => (object)[ 'name' => 'Error' ],
				]
			];
		}
		else
		{
		if($r['http_code'] < 200 || $r['http_code'] >= 400)
			throw new Exception('HTTP '.$r['http_code']);
		$r = $r['body'];
		$j = json_decode($r);
		}
		$j = $this->_traiterRés($num, $j, true);
		
		return $r;
	}
	
	public function _postfiche($num, $j)
	{
		$àFaire = $this->_parcours->reçu([ $num => $j ]);
		if($this->_lafeàm)
			$this->_sortie->pousserLiens($this->_parcours->liens);
		$this->_lancerAsync($this->_parcours->àFaire());
	}
}

?>
