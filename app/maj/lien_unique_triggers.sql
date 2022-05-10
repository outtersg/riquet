#if 0
-- Copyright (c) 2022 Guillaume Outters
--
-- Permission is hereby granted, free of charge, to any person obtaining a copy
-- of this software and associated documentation files (the "Software"), to deal
-- in the Software without restriction, including without limitation the rights
-- to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
-- copies of the Software, and to permit persons to whom the Software is
-- furnished to do so, subject to the following conditions:
--
-- The above copyright notice and this permission notice shall be included in
-- all copies or substantial portions of the Software.
--
-- THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
-- IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
-- FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
-- AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
-- LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
-- OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
-- SOFTWARE.
#endif

#if not defined(LUT_TYPE) or not defined(LUT_COLONNE)
Veuillez définir LUT_TYPE et LUT_COLONNE;
#endif

#set LUT_INSERT concat("l_", LUT_TYPE, "_i")

#if :driver = "sqlite"

-- SQLite manquant d'un langage procédural (avec variables pour entreposage des listes calculées à réutiliser d'une requête à l'autre),
-- et ne permettant pas de travailler sur des tables temporaires depuis un trigger (https://sqlite-users.sqlite.narkive.com/3NGormlH/triggers-and-temp-tables-ticket-1689),
-- on "innove" en ayant une table de travail:
#define LUT_TT l_lut_t
drop table if exists LUT_TT;
create table LUT_TT (a integer, t varchar(15), b integer, c timestamp, d timestamp, dernier boolean, majc boolean, majd boolean);

create trigger LUT_INSERT
	after insert on l
	for each row
	when new.t = 'LUT_TYPE'
begin
	insert into LUT_TT (a, t, b, c, d, dernier)
		select a, t, b, c, d, l.b = new.b
		from l where l.a = new.a and l.t = new.t;
	-- À FAIRE: supprimer les doublons (m̂ type, m̂ cible).
	
	-- Si la date de création a été explicitement renseignée, et qu'il existe une plus récente, on n'est plus le dernier.
	update LUT_TT set dernier = false
	where d is not null or exists (select 1 from LUT_TT recent where recent.a = LUT_TT.a and recent.t = LUT_TT.t and recent.c > LUT_TT.c);
	-- Il nous faut un dernier.
	update LUT_TT set dernier = true where (a, t, b) in
	(
		-- https://stackoverflow.com/questions/1897352/sqlite-group-concat-ordering/57076660#57076660
		select distinct
			a, t,
			first_value(b) over (partition by a, t order by c desc rows between unbounded preceding and unbounded following) b
		from LUT_TT l
		where not exists (select 1 from LUT_TT adernier where adernier.a = l.a and adernier.t = l.t and adernier.dernier)
		and l.d is null
	);
	-- Date de création du lien: si nous sommes le premier lien de ce type, et que nous n'avons pas de date de création, nous prenons celle du nœud (on suppose le nœud créé avec nous pour valeur initiale).
	update LUT_TT set majc = true, c = f.ctime
	from f
	where not exists (select 1 from LUT_TT ancien where ancien.a = LUT_TT.a and ancien.t = LUT_TT.t and not ancien.dernier)
	and LUT_TT.dernier and (LUT_TT.c is null or LUT_TT.c >= datetime(current_timestamp, '+1 minute'));
	-- À défaut le lien référent doit tout de même être daté.
	update LUT_TT set majc = true, c = current_timestamp where dernier and c is null;
	
	-- Maintenant on désactive tous les non-derniers.
	update LUT_TT set majd = true, d = (select min(c) from LUT_TT r where r.a = LUT_TT.a and r.t = LUT_TT.t and dernier)
	where not dernier;
	
	-- Application!
	update l set
		c = case when majc then t.c else l.c end,
		d = case when majd then t.d else l.d end
	from LUT_TT t where (majc or majd) and t.a = l.a and t.t = l.t;
	
	-- Voici la seule partie spécifique à LUT_COLONNE (qui nous force à faire un trigger par table, puisque SQLite ne propose pas d'exec).
	update f set LUT_COLONNE = l.b
	from LUT_TT l where l.a = f.id and l.t = 'LUT_TYPE' and l.dernier;
	
	delete from LUT_TT where a = new.a and t = new.t;
end;

-- Initialisation.

with
	tt as
	(
		select distinct
			a, t,
			first_value(b) over (partition by a, t order by c desc rows between unbounded preceding and unbounded following) b
		from l
		where d is null and t = 'LUT_TYPE'
	)
update f set LUT_COLONNE = tt.b from tt where tt.a = f.id;

#else

erreur Je ne gère pas :driver

#endif
