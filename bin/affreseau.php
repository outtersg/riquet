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
		
		if(isset($params['R']))
			$groupes['r'] = true;
		else if(isset($params['r']))
		{
			if(empty($params['r']))
				$groupes['r'] = true;
			else
			{
				$groupes['r'] = explode(',', $params['r']);
				$groupes['+'] = array_unique(array_merge($groupes['+'], $groupes['r']));
			}
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
		
		$raf = [];
		
		for($i = 0; ++$i < count($args);)
		{
			switch($arg = $args[$i])
			{
				case '-f': $groupes['f'] = $args[++$i]; break;
				case '-R': // Tout rafraîchir.
					$groupes['r'] = true;
					break;
				case '-r': // Rafraîchir la première entrée.
					if(!isset($args[$i + 1]) || in_array(substr($args[$i + 1], 0, 1), [ '-', '=', '' ]))
						throw new Exception('L\'option -r ("rafraîchir") doit être suivie du numéro du nœud à rafraîchir');
					$raf[] = $args[$i + 1];
					// Mais pas de ++$i, car l'entrée à rafraîchir dans une premier temps est aussi à parcourir (donc à passer dans le default).
					break;
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
		
		if(!empty($raf) && !isset($groupes['r']))
			$groupes['r'] = $raf;
		
		return $groupes + array('f' => 'dot');
	}
	
	const CACHE = 0x1;
	const SOURCE = 0x2;
	
	protected function _nœudsEtLiens($params)
	{
		// 3 modes:
		// - 0 depuis le cache
		// - 1 depuis le serveur source directement
		// - 2 depuis le serveur source poussé vers le cache, puis depuis le cache (rafraîchissement de base)
		// À FAIRE: être multi-sources. Noter que le mode 1 sera alors un peu compliqué s'il s'agit de combiner en mémoire le travail de plusieurs sources: n'est pas SQL qui veut.
		
		$mode = self::CACHE;
		if(isset($params['r'])) // Rafraîchir?
			$mode |= self::SOURCE;
		
		if(!count($params['+'])) return [];
		
		if($mode & self::SOURCE)
		{
			$paramsRaf = $params + [ '+' => [], '-' => [], '=' => [] ];
			// Veut-on rafraîchir l'intégralité du graphe ou seulement certains nœuds précis?
			// Auquel cas on remplace la liste des '+'.
			if($mode & self::CACHE && isset($params['r'])) // Si l'on passe par le cache (car si l'on est branchés en direct sur les sources, il nous faut de toute façon tout ramener: on n'aura pas le cache pour reconstituer les entrées non récupérées de la source).
			{
				$r = $params['r'];
				if(is_array($r) || (is_string($r) && $r = [ $r ]))
					$paramsRaf = [ '+' => $r ] + $params;
			}
			
			require_once R.'/app/import/Source.php';
			
			$cs = $this->app->classe('Source');
			$s = new $cs($this->app, !($mode & self::CACHE));
			$s->import->faire($paramsRaf['+'], 5, $paramsRaf['-'], $paramsRaf['=']);
			
			if(!($mode & self::CACHE))
				$ns = $s->persisteur->mém();
		}
		
		if($mode & self::CACHE)
		{
		require_once R.'/app/ChargeurBdd.php';
		require_once R.'/app/Parcours.php';
		
		$cc = $this->app->classe('ChargeurBdd');
		$cp = $this->app->classe('Parcours');
		
		$c = new $cc($this->app);
		$p = new $cp($c);

		$ns = $p->parcourir($params['+'], $params['-'], $params['=']);
		}
		
		return [ $ns ];
	}
	
	public function aff($params)
	{
		$nls = $this->_nœudsEtLiens($params);
		
		$ns = $nls[0];
		
		/* Calcul des éléments graphiques */
		
		switch($params['f'])
		{
			case 'html':
			case 'dot':
				$dots = [];
				if(isset($ns[0]) && count($ns[0]))
				{
				require_once R.'/app/export/Dot.php';
				$ce = $this->app->classe('ExportDot');
				$this->dot = $e = new $ce();
					$dots[] =
				$dot = $e->exporter($ns[0], $ns[1]);
				}
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
