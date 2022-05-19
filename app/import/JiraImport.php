<?php

require_once dirname(__FILE__).'/Import.php';

class JiraImport extends Import
{
	protected function _req($sql)
	{
		if(isset($this->bdd))
		{
			if(!isset($this->sqleur))
			{
				require_once R.'/vendor/gui/sqleur/sql2csv.php';
				$this->sqleur = new JoueurSqlPdo($this->bdd);
				$this->sqleur->bavard = false;
			}
			$this->sqleur->decoupe($sql);
		}
		
		if(!isset($this->bdd) || (isset($this->aff) && $this->aff))
		{
			echo $sql;
			if(substr($sql, -1) != "\n")
				echo "\n";
		}
	}
	
	public function début()
	{
		$this->_req($this->sql->req
		(<<<TERMINE
drop table if exists t_f;
create temporary table t_f
(
	oidf int,
	id_ext text,
	num text,
	nom text,
	type text,
	etat text,
	n_liens integer
);

drop table if exists t_l;
create temporary table t_l (t text, a text, b text);
TERMINE
		));
	}
	
	public function pousserFiche($fiche)
	{
		$t =
		[
			'id_ext' => $fiche->id,
			'num' => $fiche->key,
			'nom' => $fiche->summary,
		];
		if(isset($fiche->_nLiens))
			$t['n_liens'] = $fiche->_nLiens;
		// Les liens système:
		$l =
		[
			'T' => $fiche->issuetype->name,
			'E' => $fiche->status->name, // N.B.: $fiche->status->statusCategory->key est intéressant aussi, car il indique l'état macro (créé, en cours, ou terminé).
		];
		// À FAIRE: filtrer aussi par source avant d'écraser.
			$this->_req($this->_ponte('t_f', $t));
		foreach($l as $type => $nom)
				$this->_req($this->_ponte('t_l', [ 't' => $type, 'a' => $fiche->key, 'b' => $nom ]));
	}
	
	public function pousserLiens($liens)
	{
		foreach($liens as $t => $liensType)
			foreach($liensType as $vers => $des)
				foreach($des as $de => $rien)
						$this->_req($this->sql->req("insert into t_l (t, a, b) values (%s, %s, %s);\n", $t, $de, $vers));
	}
	
	public function fin()
	{
		$this->_req($this->sql->req
		(<<<TERMINE
-- Création des fiches.

update t_f set oidf = f.oid from f where f.id_ext = t_f.id_ext; -- À FAIRE: and source = 'moi'.
insert into f (id_ext)
	select id_ext from t_f where oidf is null;
update t_f set oidf = f.oid from f where oidf is null and f.id_ext = t_f.id_ext; -- À FAIRE: and source = 'moi'.

-- Remplissage.

update f
set
	num = t_f.num,
	nom = t_f.nom,
	n_liens = t_f.n_liens
from t_f where t_f.oidf = f.oid;

-- oidissage des liens.

update t_l set t = r.num from n r where length(t_l.t) > 1 and r.nom = t_l.t and r.t = 'L'; -- Référentiel libellé lien -> code lien.
insert into n (t, nom) select t, 'Veuillez créer une entrée référentiel (de type ''L'') pour le libellé lien "'||t||'"' from t_l where length(t) > 1; -- En théorie ça ne rentre pas dans la case donc erreur fatale.
insert into n (t, num, nom) select distinct t, b, b from t_l where t in ('T', 'E') and not exists(select 1 from n where n.t = t_l.t and n.nom = t_l.b);
update t_l set b = num from n where t_l.t in ('T', 'E') and n.t = t_l.t and n.nom = t_l.b; -- Référentiel libellé lien -> code lien.

-- Ménage des liens.
-- À FAIRE: seulement ceux que nous alimentons, pas ceux créés en surimpression.

delete from l where a in (select oidf from t_f) and b in (select oidf from t_f);

insert into l (t, a, b)
	select t_l.t, a.oidf, b.oidf from t_l join t_f a on t_l.a = a.num join t_f b on t_l.b = b.num;

insert into l (t, a, b)
	select t_l.t, a.oidf, r.id from t_l join t_f a on t_l.a = a.num join n r on r.t = t_l.t and r.num = t_l.b
	where t_l.t in ('T', 'E')
	and not exists(select 1 from l le where (le.t, le.a, le.b) = (t_l.t, a.oidf, r.id))
;
TERMINE
		));
	}
}

?>
