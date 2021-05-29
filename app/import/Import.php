<?php

require_once dirname(__FILE__).'/../Sql.php';

class Import
{
	static $TYPE_FICHE_SUBST = array
	(
		'>' => 'p',
		'e' => 'tÉtat',
		'p' => 'tPoids',
		'o' => 's',
	);
	
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
	
	/**
	 * Émet une destruction de tous les liens d'un ou plusieurs types, partant d'un lot de fiches identifiées.
	 */
	protected function _pondreSupprLiens($source, $champId, $ids, $typesLien)
	{
		// À FAIRE: $source.
		echo $this->sql->req("delete from l where a in (select id from f where $champId in (%s)) and t in (%s);\n", $ids, $typesLien);
	}
	
	/**
	 * Crée une ébauche de fiche.
	 */
	protected function _pondreFicheSubst($source, $type, $nom, $champ = 'nom')
	{
		// À FAIRE: $source.
		$values = array_map(function($x) use($type) { return array($x, $type); }, is_array($nom) ? $nom : array($nom));
		// L'on conflict ne marche qu'avec définition d'une contrainte, or selon le champ les doublons peuvent être autorisés ou non: impossible de mettre une clé d'unicité sur l'ensemble des champs (dont le nom), ce qui entraînerait des blocages sur les types où le nom n'est pas déterminant.
		// On émule donc à la mimine.
		// De plus, argh, SQLite ne veut pas de pseudo-table from (values (a, b)) as t(a, b): on doit passer par une table temporaire :-\
		//echo $this->sql->req("insert into f ($champ, t) values %{s,s} on conflict ($champ, t) do nothing;\n", $values);
		echo $this->sql->req
		(
"drop table if exists t_f;
create temporary table t_f as select $champ, t from f limit 0;
insert into t_f ($champ, t) values %{s,s};
insert into f ($champ, t) select t.* from t_f t left join f on (f.t = t.t and f.$champ = t.$champ) where f.$champ is null;\n",
			$values
		);
	}
	
	/**
	 * Tire un lien de type $typeLien des entrées $orig vers les entrées $valCible. $typeCible permet de restreindre la recherche à un type.
	 */
	protected function _pondreLien($source, $champOrig, $orig, $typeLien, $champCible, $valCible, $typeCible = null)
	{
		// À FAIRE: $source.
		$values = array_map(function($x) use($typeLien) { return array($x, $typeLien); }, is_array($orig) ? $orig : array($orig));
		$filtreCible = isset($typeCible) ? " and b.t in (%s)" : "";
		
		$req =
"insert into l (a, b, t)
	select a.id, max(b.id), %s
	from f a, f b
	where a.$champOrig in (%s) and b.$champCible in (%s)$filtreCible
	group by a.id;\n";
		if($filtreCible)
			echo $this->sql->req($req, $typeLien, $orig, $valCible, $typeCible);
		else
			echo $this->sql->req($req, $typeLien, $orig, $valCible);
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
