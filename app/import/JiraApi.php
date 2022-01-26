<?php

class JiraApi
{
	public function __construct($url, $idMdp)
	{
		$this->_racine = $url;
		$this->_idMdp = $idMdp;
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
		
		while($num = array_shift($àFaire))
		{
			$this->_aff($num);
			$j = $this->api('GET', '/issue/'.$num);
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
				$cr .= ' [-> '.implode(', ', array_keys($liés)).']';
			$this->_aff($num, $cr);
		}
	}
	
	protected function _aff($num, $rés = null)
	{
		if(!$rés)
			printf("[%s]\t", $num);
		else
			printf("\r[%s]\t%s\n", $num, $rés);
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
