-- Les liens système sont normalisés en majuscule (les liens fonctionnels se contentent donc des minuscules ou diacritiques).
-- Un "lien unique" est une relation nœuds n..1 référence, c'est-à-dire qu'à un instant donné, un nœud n'est lié qu'à une seule donnée de référence.
-- Ce lien n..1, puisqu'unique, peut être dénormalisé en colonne sur la table nœuds (mais sa présence dans la table l reste intéressante pour suivre l'historique).

update l set t = 'E' where t = 'e';

alter table f add column etat integer;

#define LUT_TYPE E
#define LUT_COLONNE etat
#include lien_unique_triggers.sql

-- À FAIRE: il existe déjà une colonne t (type); cependant elle sert à fourrer n'importe quoi dans f, aussi bien des données d'exploitation que de référence (types, comptes utilisateur, etc.). Or la table n (nœuds) sert déjà à ça (mettre dans le même panier tous les nœuds, qu'ils soient fiche ou donnée de référence): on pourrait donc extraire ces fiches de référence pour en constituer une table r, autre fille de n. Quant à l'usage de t pour distinguer les données provenant de différentes sources (info nécessaire si par exemple deux sources ont un même nature, par exemple "ticket" dans un système technique et "ticket" dans un système fonctionnel), on pourrait ajouter une colonne s branchée sur le lien S (source) de type LUT.
alter table f add column nature integer;

#define LUT_TYPE T
#define LUT_COLONNE nature
#include lien_unique_triggers.sql
