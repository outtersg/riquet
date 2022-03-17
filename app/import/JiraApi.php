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

class JiraApi
{
	const OUI = 1;
	const NON = -1;
	const BOF = 0;
	const RIEN = -99;
	
	static $Couls = array
	(
		self::OUI => '32',
		self::NON => '31',
		self::BOF => '33',
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
		$faits = array();
		$liens = array();
		
		$this->_sortie->début();
		
		while($num = array_shift($àFaire))
		{
			$this->_aff($num);
			$j = $this->api('GET', '/issue/'.$num);
			$j->fields->id = $j->id;
			$j->fields->key = $j->key;
			$j = $j->fields; // L'enrobage ne nous intéresse pas.
			$cr = $j->summary;
			$liens = array();
			$liés = array();
			if(isset($j->issuelinks))
				foreach($j->issuelinks as $lien)
				{
					if(isset($lien->outwardIssue)) { $de = $num; $liés[$vers = $lien->outwardIssue->key] = 1; }
					else { $vers = $num; $liés[$de = $lien->inwardIssue->key] = 1; }
					$liens[$lien->type->inward][$de][$vers] = 1;
				}
			if(count($liés))
				$cr .= ' [95m[-> '.implode(', ', array_keys($liés)).'][0m';
			$bien = self::OUI;
			if(isset($plaf) && count($liés) > $plaf && !isset($plus[$num]))
			{
				$bien = self::BOF;
				$cr .= ' [33m(trop de liens)[0m';
			}
			$this->_aff($num, $bien, $cr);
			
			$faits[$num] = 1;
			
			// On remet en lice les liés, sous condition.
			
			if($bien == self::OUI && !isset($moins[$num]))
				$àFaire = array_keys(array_flip($àFaire) + array_diff_key($liés, $faits));
			
			// Poussage!
			
			$this->_sortie->pousserFiche($j);
		}
		
		$this->_sortie->pousserLiens($liens);
		
		$this->_sortie->fin();
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
		if(!$détail)
			fprintf(STDERR, "%s[%s]%s\t", $coul, $num, $neutre);
		else
			fprintf(STDERR, "\r%s[%s]%s\t%s\n", $coul, $num, $neutre, $détail);
	}
	
	public function api($méthode, $uri, $params = null)
	{
		$c = curl_init($this->_racine.'/rest/api/latest'.$uri);
		curl_setopt($c, CURLOPT_CUSTOMREQUEST, $méthode);
		curl_setopt($c, CURLOPT_USERPWD, $this->_idMdp);
		$enTêtes = array
		(
			'Content-Type: application/json;charset=UTF-8',
		);
		curl_setopt($c, CURLOPT_HTTPHEADER, $enTêtes);
		if($params)
			curl_setopt($c, CURLOPT_POSTFIELDS, $params);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		$r = curl_exec($c);
		curl_close($c);
		
		return json_decode($r);
	}
}

?>
