init()
{
	grep -q servicenow "$R/etc/riquet.php" || return 0
	
	titre "[[[ IMPORT SERVICENOW ]]]"
	
	local csv="$VAR/init.servicenow.csv"
	local sql="$VAR/init.servicenow.sql"
	
	find "$csv" -mmin -120 2> /dev/null | grep -q . || \
	{
		titre "Téléchargement des ServiceNow"
		time php "$SCRIPTS/servicenowtelech.php" --init > "$csv" || rm -f "$csv"
	}
	
	titre "Conversion CSV -> SQL"
	rm -f "$BDD"
	php "$SCRIPTS/../app/maj.php"
	php "$SCRIPTS/servicenowactu.php" "$csv" > "$sql"
	
	titre "Intégration des ServiceNow à la base"
	time sqlite3 "$BDD" < "$sql"
}
