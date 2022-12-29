create temporary table t_r as
	-- On garde l'entrée la _plus récente_, car on suppose qu'elle a reçu la dernière version des attributs de la source.
	select max(id) ref, t, num
	from n
	group by 2, 3
	having count(1) > 1
;

select count(1)||' nœuds ayant des doublons' from t_r;

create temporary table t_d as
	select n.id, ref
	from t_r join n on n.t is not distinct from t_r.t and n.num = t_r.num and n.id < ref
;

select count(1)||' doublons à supprimer' from t_d;

-- Bon seule la partie a des liens est touchée.
create temporary table t_l as
	with lignes as (select ref, id from t_d union select ref, ref from t_r)
	select l.t, l.a, ref, l.b
	from lignes join l on l.a = lignes.id
;

-- Pour les liens, c'est le contraire des entrées: c'est le lien le plus ancien qui reste.
create temporary table t_lr as
	select t, ref, b, min(a) nouvela
	from t_l
	group by 1, 2, 3
;
create temporary table t_ld as
	select t_l.t, t_l.a, t_l.b
	from t_lr join t_l on t_l.t = t_lr.t and t_l.b = t_lr.b and t_l.ref = t_lr.ref
	where a > nouvela
;

select count(1)||' liens "'||t||'" à supprimer' from t_ld group by t;

--select t, ref, b, count(1) from t_l group by 1, 2, 3 order by 4 desc limit 800;

--delete from t_ld
--select count(1) from l where a in (select id from t_d);
