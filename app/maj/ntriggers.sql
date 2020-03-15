#if :driver = "sqlite"

#set N_TRIGGERI concat(N_TABLE, "_i")
#set N_TRIGGERU concat(N_TABLE, "_u")

-- Bien qu'on ne puisse, en before insert, insérer dans n et modifier NEW.id par celui tout juste inséré dans n,
-- on peut, after insert, modifier la table avec le résultat de l'insert.
-- On prendra le max(id), les autoincrement SQLite nous en donnant l'assurance.
-- De plus comme n est la combinaison de toutes les autres tables, max(n.id) >= max(autre.id). En prenant max(n.id) on est donc sûrs de ne pas écraser une entrée d'une autre table.
create trigger N_TRIGGERI after insert on N_TABLE for each row begin insert into n (t
#if defined(N_NUM)
, num
#endif
#if defined(N_NOM)
, nom
#endif
) values (N_TYPE
#if defined(N_NUM)
, NEW.N_NUM
#endif
#if defined(N_NOM)
, NEW.N_NOM
#endif
);
update N_TABLE set id = (select max(id) from n) where id = NEW.id; end;

#if defined(N_NUM) or defined(N_NOM)
create trigger N_TRIGGERU after update of
#if defined(N_NUM)
N_NUM
#if defined(N_NOM)
,
#endif
#endif
#if defined(N_NOM)
N_NOM
#endif
on N_TABLE for each row begin update n set id = id
#if defined(N_NUM)
, num = NEW.N_NUM
#endif
#if defined(N_NOM)
, nom = NEW.N_NOM
#endif
where id = NEW.id; end;
#endif

#else

erreur Je ne gère pas :driver

#endif

#define N_TYPE
#define N_TABLE
#define N_NOM
#define N_NUM
