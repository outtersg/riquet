<?php

require_once dirname(__FILE__).'/Import.php';

class JiraImport extends Import
{
	public function début()
	{
		echo $this->sql->req
		(<<<TERMINE
drop table if exists t_f;
create temporary table t_f (oidf int, id_ext text, num text, nom text);

drop table if exists t_l;
create temporary table t_l (t text, a text, b text);
TERMINE
		);
	}
	
	public function pousserFiche($fiche)
	{
		$t =
		[
			'id_ext' => $fiche->id,
			'num' => $fiche->key,
			'nom' => $fiche->summary,
		];
		// À FAIRE: filtrer aussi par source avant d'écraser.
		echo $this->_ponte('t_f', $t);
	}
	
	public function pousserLiens($liens)
	{
		foreach($liens as $t => $liensType)
			foreach($liensType as $vers => $des)
				foreach($des as $de => $rien)
					echo $this->sql->req("insert into t_l (t, a, b) values (%s, %s, %s);\n", $t, $de, $vers);
	}
	
	public function fin()
	{
		echo $this->sql->req
		(<<<TERMINE
-- Création des fiches.

update t_f set oidf = f.oid from f where f.id_ext = t_f.id_ext; -- À FAIRE: and source = 'moi'.
insert into f (id_ext)
	select id_ext from t_f where oidf is null;
update t_f set oidf = f.oid from f where oidf is null and f.id_ext = t_f.id_ext; -- À FAIRE: and source = 'moi'.

-- Remplissage.

update f
set num = t_f.num, nom = t_f.nom
from t_f where t_f.oidf = f.oid;

-- oidissage des liens.

update t_l set t = r.num from n r where length(t_l.t) > 1 and r.nom = t_l.t and r.t = 'L'; -- Référentiel libellé lien -> code lien.
insert into n (t, nom) select t, 'Veuillez créer une entrée référentiel (de type ''L'') pour le libellé lien "'||t||'"' from t_l where length(t) > 1; -- En théorie ça ne rentre pas dans la case donc erreur fatale.

-- Ménage des liens.
-- À FAIRE: seulement ceux que nous alimentons, pas ceux créés en surimpression.

delete from l where a in (select oidf from t_f) and b in (select oidf from t_f);

insert into l (t, a, b)
	select t_l.t, a.oidf, b.oidf from t_l join t_f a on t_l.a = a.num join t_f b on t_l.b = b.num;
TERMINE
		);
	}
}

?>
