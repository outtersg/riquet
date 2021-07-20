init()
{
	_actu --init "$@"
}

_actu()
{
	grep -q servicenow "$R/etc/riquet.php" || return 0
	
	titre "[[[ IMPORT SERVICENOW ]]]"
	
	local suf= opttelech= temp=1
	
	while [ $# -gt 0 ]
	do
		case "$1" in
			--init)
				opttelech="$opttelech --init"
				temp=
				;;
		esac
		shift
	done
	[ -z "$temp" ] || suf="$suf.temp"
	
	local csv="$VAR/init.servicenow$suf.csv"
	local sql="$VAR/init.servicenow$suf.sql"
	
	_telecache "$csv" 120 $opttelech || return 1
	
	titre "Conversion CSV -> SQL"
	rm -f "$BDD"
	php "$SCRIPTS/../app/maj.php"
	php "$SCRIPTS/servicenowactu.php" "$csv" > "$sql"
	
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
