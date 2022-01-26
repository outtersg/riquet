<?php

class JiraApi
{
	public function __construct($url, $idMdp)
	{
		$this->_racine = $url;
		$this->_idMdp = $idMdp;
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
