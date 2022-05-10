select 'Dédoublonnage des fiches:';
create temporary table fd as
	select t, num, nom, id_ext, descr, comm, min(ctime) ctime, max(mtime) mtime, max(dtime) dtime, min(id) idr, group_concat(id, ',') ids, count(1) n
	from f
	group by 1, 2, 3, 4, 5, 6
	having count(1) > 1;

select '[33m'||t||' '||num||' apparaît en '||count(1)||' exemplaires. Uniformisez les titre / description / commentaire avant de retenter.[0m'
from fd
group by t, num
having count(1) > 1;

create temporary table fr as
	select idr, f.id
	from fd join f on ','||ids||',' like '%,'||f.id||',%'
	where f.id <> idr;
select * from fr;

update l set a = idr from fr where a = id;
update l set b = idr from fr where b = id;
delete from f where id in (select id from fr);
delete from n where id in (select id from fr);

select count(1) n, t, 'supprimés' m from fd group by 2 order by 1 desc;

-- À FAIRE: dédoublonnage des commentaires le jour où on les met dans une table dédiée plutôt que concaténés dans un champ de f.

select 'Dédoublonnage des liens:';
create temporary table ld as
	select t, a, b, min(c) c, max(d) d from l group by t, a, b having count(1) > 1;
delete from l where (t, a, b) in (select t, a, b from ld);
insert into l (t, a, b, c, d) select t, a, b, c, d from ld;

select count(1) n, t, 'supprimés' m from ld group by 2 order by 1 desc;
