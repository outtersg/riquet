init()
{
	grep -q servicenow "$R/etc/riquet.php" || return 0
	
	titre "[[[ IMPORT SERVICENOW ]]]"
	
	local suf=""
	local csv="$VAR/init.servicenow$suf.csv"
	local sql="$VAR/init.servicenow$suf.sql"
	
	find "$csv" -mmin -120 2> /dev/null | grep -q . || \
	{
		titre "Téléchargement des ServiceNow"
		time php "$SCRIPTS/servicenowtelech.php" --init > "$csv" || { rm -f "$csv" ; return 1 ; }
	}
	
	titre "Conversion CSV -> SQL"
	rm -f "$BDD"
	php "$SCRIPTS/../app/maj.php"
	php "$SCRIPTS/servicenowactu.php" "$csv" > "$sql"
	
	titre "Intégration des ServiceNow à la base"
	time sqlite3 "$BDD" < "$sql"
}
