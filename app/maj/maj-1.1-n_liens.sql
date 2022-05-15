-- Notre base n'est qu'un cache d'un silo potentiellement plus large, dont nous n'avons pas vocation à remonter l'intégralité:
-- afin de matérialiser les autres nœuds que nous n'aurons pas parcouru, nous permettons d'enregistrer le nombre de relations total
-- (celles remontées dans l + celles de la source ignorées)
alter table f add column n_liens integer;
