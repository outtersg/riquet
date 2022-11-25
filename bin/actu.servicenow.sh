init()
{
	_actu --init "$@"
}

incr()
{
	_actu "$@"
}

_actu()
{
	grep -q servicenow "$R/etc/riquet.php" || return 0
	
	titre "[[[ IMPORT SERVICENOW ]]]"
	
	local suf= opttelech= temp=1 comm=1
	
	while [ $# -gt 0 ]
	do
		case "$1" in
			--init)
				opttelech="$opttelech --init"
				temp=
				;;
			--sans-commentaire)
				comm=
				;;
		esac
		shift
	done
	[ -z "$temp" ] || suf="$suf.temp"
	
	local csv="$VAR/init.servicenow$suf.csv"
	local sql="$VAR/init.servicenow$suf.sql"
	
	_telecache "$csv" 120 $opttelech || return 1
	[ -z "$comm" ] || _telecache "$csv.comm" 0 $opttelech --comm || return 1
	
	titre "Conversion CSV -> SQL"
	[ -n "$temp" ] || rm -f "$BDD" # /!\ On bute la base en mode --init. Pas sympa si d'autres sources que nous ont alimenté. # À FAIRE: un simple delete de tout ce qui est à nous.
	php "$SCRIPTS/../app/maj.php"
	php "$SCRIPTS/servicenowactu.php" "$csv" > "$sql"
	[ -z "$comm" ] || php "$SCRIPTS/servicenowactu.php" "$csv.comm" >> "$sql"
	
	titre "Intégration des ServiceNow à la base"
	time sqlite3 "$BDD" < "$sql"
	# Optimisations possibles:
	# https://news.ycombinator.com/item?id=27872575
	# Les pragma, pousser vers une base :memory: puis la mettre en fichier, etc.
}

_telecache()
{
	local csv="$1" persis="$2"
	shift ; shift
	
	find "$csv" -mmin -$persis 2> /dev/null | grep -q . || \
	{
		titre "Téléchargement des ServiceNow $*"
		time php "$SCRIPTS/servicenowtelech.php" $* > "$csv" || { rm -f "$csv" ; return 1 ; }
	}
}
