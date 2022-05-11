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

require_once dirname(__FILE__).'/../app/app.php';

class AffRéseau
{
	public function __construct($app)
	{
		$this->app = $app;
	}
	
	public function analyserParamètresEtFaire($params)
	{
		if(isset($params['q']))
			$params = $this->_analyserReq($params);
		else
			$params = $this->_analyserArgs($params);
		$this->aff($params);
	}
	
	protected function _analyserReq($params)
	{
		// Pour faciliter l'écriture de la requête, on voudrait y faire figurer les notions de + (inclure), = (ne pas aller plus loin si on tombe dessus), - (ne pas afficher).
		// Le + est rendu comme espace. Le - étant susceptible de figurer dans un numéro de bidule, deux possibilités: soit le remplacer par un /, soit le précéder d'un espace.
		preg_match_all('# ?(^|[-+=/ ])([^+=/ ]+)#', $_GET['q'], $r);
		$groupes = array
		(
			'+' => array(),
			'=' => array(),
			'-' => array(),
		);
		foreach($r[1] as $pos => $signe)
		{
			switch($signe)
			{
				case ' ':
				case '':
					$signe = '+';
					break;
				case '/':
					$signe = '-';
					break;
			}
			$groupes[$signe ? $signe : '+'][] = $r[2][$pos];
		}
		
		return $groupes + array_intersect_key($params, array('f' => 1)) + array('f' => 'html');
	}
	
	protected function _analyserArgs($args)
	{
		$groupes = array
		(
			'+' => array(),
			'=' => array(),
			'-' => array(),
		);
		
		for($i = 0; ++$i < count($args);)
		{
			switch($arg = $args[$i])
			{
				default:
					switch(substr($arg, 0, 1))
					{
						case '-': $signe = '-'; $arg = substr($arg, 1); break;
						case '=': $signe = '='; $arg = substr($arg, 1); break;
						default: $signe = '+'; break;
					}
					$groupes[$signe][] = $arg;
					break;
			}
		}
		
		return $groupes + array('f' => 'dot');
	}
	
	public function aff($params)
	{
		require_once R.'/app/ChargeurBdd.php';
		require_once R.'/app/Parcours.php';
		
		$cc = $this->app->classe('ChargeurBdd');
		$cp = $this->app->classe('Parcours');
		
		$c = new $cc($this->app);
		$p = new $cp($c);

		$ns = $p->parcourir($params['+'], $params['-'], $params['=']);
		
		/* Calcul des éléments graphiques */
		
		switch($params['f'])
		{
			case 'html':
			case 'dot':
				require_once R.'/app/export/Dot.php';
				$ce = $this->app->classe('ExportDot');
				$this->dot = $e = new $ce();
				$dot = $e->exporter($ns[0], $ns[1]);
				break;
		}
		
		/* Rendu final */
		
		switch($params['f'])
		{
			case 'html':
				$moi = isset($_GET['REQUEST_URI']) ? $_GET['REQUEST_URI'] : '';
				include R.'/app/aff/dot.html';
				break;
			case 'dot':
				echo $dot;
				break;
		}
	}
}

$app = new App();
$affRéseau = new AffRéseau($app);
$affRéseau->analyserParamètresEtFaire(isset($argv) ? $argv : $_GET);

?>
