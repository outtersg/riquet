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
	grep -q jira "$R/etc/riquet.php" || return 0
	
	titre "[[[ IMPORT JIRA ]]]"
	
	local optimport= temp=1 comm=
	
	while [ $# -gt 0 ]
	do
		case "$1" in
			--init)
				optimport="$optimport $1"
				temp=
				;;
			--comm)
				optimport="$optimport $1"
				;;
			*) break ;;
		esac
		shift
	done
	
	php "$SCRIPTS/../app/maj.php"
	php "$SCRIPTS/jiraimport.php" $optimport "$@"
}
