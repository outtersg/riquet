<?php

require_once dirname(__FILE__).'/../app/Sql.php';

$sql = new Sql();
$e = "insert into bla (x, y) values %{s,d}, (%s, %d), (%d);";
$p = array(array('a', 0), array('b', null));
$s = "insert into bla (x, y) values ('a',0),('b',null), ('C', 3), (5,6);";
if(($r = $sql->req($e, $p, 'C', 3, array(5, 6))) != $s)
{
	echo "[33m$s[0m\n";
	echo "[31m$r[0m\n";
}
else
	echo "[32mParfait[0m\n";

?>
