<?php
/*
 * Copyright (c) 2022 Guillaume Outters
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

class ChargeurBdd
{
	public function __construct($app)
	{
		$this->app = $app;
		$this->_cache = array();
		
		$rs = [];
		foreach($this->app->bdd->req("select id, num, nom from n where t in ('T', 'E')") as $r)
			$rs[$r['id']] = $r;
		$this->_réf = $rs;
	}
	
	public function idNums($nums)
	{
		$ls = $this->app->bdd->req("select id, num from f where num in (%s)", $nums);
		$ids = array();
		foreach($ls as $l)
			$ids[$l['id']] = $l['num'];
		return $ids;
	}
	
	public function charger($ids, $parNum = false)
	{
		$fiches = $this->app->bdd->req("select * from f where id in (%s)", $ids);
		$ids = array_map(function($f) { return $f['id']; }, $fiches);
		$fiches = array_map([ $this, '_enrichirFiche' ], $fiches);
		return array_combine($ids, $fiches);
	}
	
	public function _enrichirFiche($f)
	{
		foreach([ 'etat', 'nature' ] as $champ)
			if($f[$champ])
				$f[$champ] = $this->_réf[$f[$champ]];
		return $f;
	}
	
	/**
	 * Récupère les liens entre fiches.
	 *
	 * @param array $fiches Fiches renvoyées par charger().
	 *
	 * @return array [ <type>: [ <id source>: [ <id cible>: true ] ] ]
	 */
	public function chargerLiens(& $fiches)
	{
		$ids = array_keys($fiches);
		$liens = array();
		$liste = $this->app->bdd->req("select * from l where (a in (%s) or b in (%s)) and t not in ('T', 'E')", $ids, $ids);
		$nLiens = array_fill_keys($ids, 0);
		foreach($liste as $l)
		{
			$liens[$l['t']][$l['a']][$l['b']] = true;
			foreach([ 'a', 'b' ] as $col)
				if(isset($nLiens[$l[$col]]))
					++$nLiens[$l[$col]];
		}
		foreach($nLiens as $id => $n)
			$fiches[$id]['_nLiens'] = $n;
		return $liens;
	}
}

?>
