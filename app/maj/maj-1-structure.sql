#if :driver = "sqlite"
#set ID_N AUTOPRIMARY
#else
#define ID_N integer not null references n(id) on delete cascade
#endif

-- Nœuds
-- Tout objet est avant tout un nœud (qui lui donne sa position dans la constellation).
create table n
(
	id AUTOPRIMARY,
	t char(1) not null,
	num text,
	nom text
);

-- Liens
create table l
(
	p integer, -- Père
	f integer, -- Fils
	t char(1), -- Type
	n float    -- Poids; chaque type peut avoir sa façon de gérer n (par exemple, ce peut être considéré comme une distance pour un type, comme une importance pour un autre).
);

create table sources
(
	id ID_N,
	nom text,
	classe text,
	url text
);
#define N_TYPE 's'
#define N_TABLE sources
#define N_NOM nom
#include ntriggers.sql

insert into sources (nom) values ('.');

-- Fiches
create table f
(
	id ID_N,
	t char(1),
	num text,
	nom text,
	ctime timestamp default current_timestamp,
	mtime timestamp,
	dtime timestamp,
	descr text
);
#define N_TYPE 'f'
#define N_TABLE f
#define N_NUM num
#define N_NOM nom
#include ntriggers.sql

create table notes
(
	id AUTOPRIMARY,
	id_n integer not null,
	id_auteur integer,
	ctime timestamp default current_timestamp
);
