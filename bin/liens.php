<?php
/*
 * Copyright (c) 2021 Guillaume Outters
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

require_once dirname(__FILE__).'/../app/app.php';
require_once R.'/app/import/ServiceNowImport.php';

class Liens
{
	const FORMAT = 0x0F;
	const HTML   = 0x01;
	const JIRA   = 0x02;
	const NOM    = 0x10;
	
	public function __construct($app)
	{
		$this->app = $app;
	}
	
	public function aff($idsOuRech, $mode)
	{
		$req = $this->req($idsOuRech, $mode);
		return implode("\n", $this->app->bdd->query($req)->fetchAll(PDO::FETCH_COLUMN));
	}
	
	public function sql($c)
	{
		return "'".strtr($c, array("'" => "''"))."'";
	}
	
	public function req($idsOuRech, $mode)
	{
		$where = '';
		$cols = '';
		if($mode & Liens::NOM) $cols .= "' '||f.nom";
		$where = is_array($idsOuRech)
			? "f.num in ('".implode("','", $idsOuRech)."')"
			: "f.desc regexp '$idsOuRech' or f.comm regexp '$idsOuRech'"
		;
		
		// A-t-on des états indiquant qu'une fiche est close?
	
		$metato = $metatf = $etats = '';
		if(is_array($this->app->config['terminaux']))
		{
			switch($mode & Liens::FORMAT)
			{
				case Liens::HTML: $metato = ' style="text-decoration-line: line-through;"'; break;
				case Liens::JIRA: $metato = $metatf = '-'; break;
			}
			$metato = "case when l.b is null then '' else '$metato' end||";
			$metatf = "||case when l.b is null then '' else '$metatf' end";
			$tsql = implode(',', array_map(array($this, 'sql'), $this->app->config['terminaux']));
			$etats = "left join (l join f e on e.id = l.b and e.nom in ($tsql)) on l.a = f.id and l.t = 'e'";
		}
		
		if($cols)
		{
			if($mode & Liens::HTML) $cols = "replace(replace($cols, '<', '&lt;'), '>', '&gt;')";
			$cols = "'||".$cols."||'";
		}
		
		$url = $this->app->config['servicenow'];
		switch($mode & Liens::FORMAT)
		{
			case Liens::HTML: $aff = "'<div><a href=\"$url/incident.do?sys_id='||f.id_ext||'\"'||$metato'>'||f.num||'</a>$cols</div>'"; break;
			case Liens::JIRA: $aff = "$metato'['||f.num||'|$url/incident.do?sys_id='||f.id_ext||']'$metatf$cols"; break;
		}
		return
		"
			select $aff
			from f
			$etats
			where $where order by f.num desc;
		";
	}
}

$app = new App();
$l = new Liens($app);
if(isset($_GET))
	echo $l->aff($_GET['q'], Liens::HTML|Liens::NOM)."\n";

?>
