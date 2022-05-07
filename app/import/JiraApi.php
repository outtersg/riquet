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
	
	static $Couls = array
	(
		self::OUI => '32',
		self::NON => '31',
		self::BOF => '33',
		self::BONDACC => '36',
		self::RIEN => '90',
	);
	
	public function __construct($url, $idMdp, $sortie)
	{
		$this->_racine = $url;
		$this->_idMdp = $idMdp;
		$this->_sortie = $sortie;
	}
	
	public function lancer($params)
	{
		$àFaire = $plus = $moins = array();
		$plaf = 5; // Plafond. Si trop de liens on ne suit pas.
		
		for($i = 0; ++$i < count($params);)
			switch($param = $params[$i])
			{
				default:
					switch(substr($param, 0, 1))
					{
						case '-': $moins[substr($param, 1)] = 1; break;
						case '+': $plus[substr($param, 1)] = 1; break;
						case '=': $bofs[substr($param, 1)] = 1; break;
						default: $àFaire[] = $param; break;
					}
			}
		
		$this->faire($àFaire, $plaf, $moins, $plus);
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
		
		$this->_aff = new AffT(STDERR);
		
		$this->_sortie->début();
		
		list($nœuds, $liens) = $p->parcourir(array_merge($àFaire, $plus), $moins, [], $plaf);
		
		$this->_sortie->pousserLiens($liens);
		
		$this->_sortie->fin();
		
		$this->_aff->affl(null, '');
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
			else
				foreach($réps as $clé => $rép)
					$this->_reçu($this->_reqs[$clé], $rép);
		
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
		$j = $this->api('GET', '/issue/'.$num, null, $async);
		if(!$async) $j = $this->_traiterRés($num, $j);
		return $j;
	}
	
	protected function _traiterRés($num, $j)
	{
			$j->fields->id = $j->id;
			$j->fields->key = $j->key;
			$j = $j->fields; // L'enrobage ne nous intéresse pas.
		
		// On consolide ici les liens, tel que chargerLiens devra les renvoyer.
		$liens = [];
			if(isset($j->issuelinks))
				foreach($j->issuelinks as $lien)
				{
				if(isset($lien->outwardIssue)) { $de = $num; $vers = $lien->outwardIssue->key; }
				else { $vers = $num; $de = $lien->inwardIssue->key; }
					$liens[$lien->type->inward][$de][$vers] = 1;
				}
		$j->_liens = $liens;
		
		// Poussage!
		
		$this->_sortie->pousserFiche($j);
		return $j;
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
	
	public function api($méthode, $uri, $params = null, $async = false)
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
		];
		if($params)
			$o[CURLOPT_POSTFIELDS] = $params;
		
		// On utilise le multi_curl uniquement si nécessaire et disponible.
		// Il pourrait fonctionner tout aussi bien sur les modes "simples" (sans le if),
		// mais ne sortons le grand jeu que si nécessaire.
		if($this->mode >= 2 && $async)
		{
			if(!isset($this->_multicurl))
				$this->_multicurl = new Dklab_SoapClient_Curl();
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
		}
		
		return json_decode($r);
	}
	
	public function _reçu($num, $r)
	{
		$r = $r['body'];
		$j = json_decode($r);
		$j = $this->_traiterRés($num, $j);
		$àFaire = $this->_parcours->reçu([ $num => $j ]);
		$this->_lancerAsync($this->_parcours->àFaire());
	}
}

?>
