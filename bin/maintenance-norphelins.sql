-- Je réalise que, si les fiches sont bien remplacées à chaque actualisation de la base (une entrée A lue hier et retrouvée aujourd'hui n'apparaît bien qu'une seule fois),
-- leur nœud, lui, reste en fantôme (si la fiche A avait été créée sous l'ID 1 hier, et 2 aujourd'hui, on n'a bien qu'une f d'ID 2, mais on a deux n d'ID 1 et 2.
-- Ce script supprime donc les n qui traînent sans f, du moment qu'un f (et son n) ont été recréés par la suite avec le même numéro.

create temporary table no (id integer primary key, num text); -- Et non pas create as select, sans quoi il ne détecte pas que l'id est notre integer primary key à indexer / optimiser en oid.
insert into no
	select n.id, n.num -- C'est trop con, mais les perfs 
	from n
	where t = 'f' and not exists (select 1 from f where f.id = n.id)
;

select count(1) n, 'n sans leur f associé' from no;

create temporary table nr (id integer primary key, rid integer);
insert into nr
	select no.id, max(r.id) rid
	from no join n r on r.num = no.num
	group by no.id
;

select count(1) n, 'à remplaçant trouvable' from nr;

delete from no where not exists (select 1 from nr where nr.id = no.id);
create temporary table no2 as select * from no limit 20000;
delete from no;
insert into no select * from no2;
drop table no2;
delete from nr where not exists (select 1 from no where nr.id = no.id);

select count(1) n, 'à traiter ce jour' from nr;

select 'Comparaison des liens entre ancien et nouveau nœud…';
create temporary table leq as -- Liens équivalents.
	select nr.id did, nr.rid, l.b dest, l.t, '>' sens, -1 source
	from nr join l on l.a = nr.id
	union
	select nr.id, nr.rid, l.a, l.t, '<' sens, -1 source
	from nr join l on l.b = nr.id
	union
	select nr.id, nr.rid, l.b, l.t, '>' sens, 1 source
	from nr join l on l.a = nr.rid
	union
	select nr.id, nr.rid, l.a, l.t, '<' sens, 1 source
	from nr join l on l.b = nr.rid
;
select count(1) n, 'liens à étudier depuis ces nœuds' from leq;
create index leq_did_x on leq(did);
create index leq_rid_x on leq(rid);

-- Les regroupements faciles: même ID:
select 'Les liens strictement identiques n''entravent pas la suppression du vieux nœud…';
create temporary table lidem as
	select l1.*
	from leq l1 join leq l2
	on l1.did = l2.did
	and l1.rid = l2.rid
	and l1.dest = l2.dest
	and l1.sens = l2.sens
	and l1.t = l2.t
	and l1.source = -1 and l2.source = 1
;
delete from leq where (did, rid, dest, sens, t) in (select did, rid, dest, sens, t from lidem);
drop table lidem;

select 'Les remplacements à l''intérieur d''une liste à choix unique n''entravent pas la suppression du vieux nœud…';

-- Les groupes exclusifs (listes à choix unique) sont les enfants de nœuds de type énumération.
create temporary table tgroupes as
	select l.a id, l.b gid
	from l, n
	where l.t = '^' and n.id = l.b and n.t = 'e'
;

-- Les liens monovalués: si l'entrée a changé de valeur, on peut supprimer l'ancienne fiche du moment que la nouvelle a la valeur écrasante.
create temporary table lidem as
	select l1.*, l2.dest dest1
	from leq l1 join leq l2
	on l1.did = l2.did
	and l1.rid = l2.rid
	and l1.sens = l2.sens
	and l1.t = l2.t
	and l1.source = -1 and l2.source = 1
	join tgroupes g1 on g1.id = l1.dest
	join tgroupes g2 on g2.id = l2.dest
	where g1.gid = g2.gid
;
delete from leq where (did, rid, dest, sens, t) in (select did, rid, dest, sens, t from lidem);
delete from leq where (did, rid, dest, sens, t) in (select did, rid, dest1, sens, t from lidem);
drop table lidem;

with n as (select distinct did from leq where source < 0)
select count(1) n, 'nœuds ne peuvent être supprimés car leur remplaçant porte moins d''infos' from n;
select 'Ex. (les source = -1 sans source 1 équivalent):';
.head on
select *
from leq, f dest
where dest.id = leq.dest
order by leq.did limit 20;
.head off

delete from no where id in (select did from leq where source < 0);
select count(1) n, 'nœuds orphelins à supprimer' from no;

delete from n where id in (select id from no);
