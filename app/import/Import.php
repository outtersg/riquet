<?php

require_once dirname(__FILE__).'/../Sql.php';

class Import
{
	public function __construct()
	{
		$this->sql = new Sql();
	}
	
	protected function _pondreFiche($l)
	{
		foreach($l as $c => & $v)
			$v = $this->formaterSql($c, $v);
		// À FAIRE: update si déjà présent (pour conserver des liens qui auraient été mis manuellement, hors source, et qui sautent donc si l'on réimporte par effacement puis import total).
		$req = $this->_insert($l);
		
		echo $req;
	}
	
	protected function _insert($l)
	{
		return "insert into f (".implode(", ", array_keys($l)).") values (".implode(", ", $l).");\n";
	}
	
	public function formaterSql($c, $v)
	{
		switch($c)
		{
			case 'ctime':
			case 'dtime':
				$v = preg_replace('#^([0-9]{2})/([0-9]{2})/([0-9]{4})#', '\3-\2-\1', $v);
				break;
		}
		
		if($v == '')
			$v = 'null';
		// À FAIRE: else if numérique
		else
			$v = "'".strtr($v, array("'" => "''"))."'";
		
		return $v;
	}
}

?>
