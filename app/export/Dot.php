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

class ExportDot
{
	/**
	 * Pour certains types de lien, dot (orienté graphe d'états: un nœud désigne son successeur) et pdt (orienté hiérarchie: un nœud désigne son père) ont une interprétation opposée.
	 * On liste ici les types de relation fils - père.
	 */
	public static $LiensInverses = array
	(
		'v', // "descend de"
		'<', // "est causé par"
		'x', // "est bloqué par"
	);
	
	// Il y a un taré qui pour placer un point au milieu d'un arc demande à dot de tout placer, repère les arcs et recalcule où tombe son centre, rajoute en dur des bidules à cet endroit, et demande à nop2 (un rendeur qui ne fait que rendre, attendant tous les objets préplacés) de transformer en SVG.
	// https://stackoverflow.com/a/29709847/1346819
	// Bon on va faire plus simple, on renonce à l'idée de mettre notre sens interdit en milieu de tige.
	public static $Flèches = array
	(
		'v' => array('arrowtail' => 'odot', 'style' => 'bold', 'dir' => 'back'),
		'<' => array('style' => 'tapered'),
		/* minlen=0 plutôt que constraint=false pour les liens d'équivalence.
		 * En effet le constraint a pour effet indésirable (en plus de celui attendu, mettre les deux nœuds sur pied d'égalité)
		 * de les dissocier complètement, le second allant se mettre à l'autre bout du graphe (à moins qu'un lien tierce les rapproche évidemment).
		 * On voulait que ça transforme une relation père - fils en frère - frère, ça nous donne en fait deux parfaits inconnus.
		 * Le minlen est moins coercitif que le rank=same (sur les nœuds), permettant au placeur de décaler si d'autres relations entrent en jeu.
		 */
		'=' => array('arrowhead' => 'none', 'color' => 'black:black', 'style' => 'dashed', 'minlen' => '0'), // https://stackoverflow.com/a/6219948/1346819
		':' => array('arrowhead' => 'none', 'style' => 'dashed', 'minlen' => '0'),
		'x' => array('color' => 'black:black', 'arrowhead' => 'tee'), // On pourrait aussi jouer avec le label "en attente de": ⌛,🛇
	);
	
	public function exporter($nœuds, $liens)
	{
		$affn = array();
		foreach($nœuds as $id => $nœud)
			$affn[] = $this->id($nœud).' '.$this->style($this->propsNœud($nœud));
		$affn = array_map(function($x) { return "\t".$x."\n"; }, $affn);
		$affl = array();
		$oubliettes = array_fill_keys(array_keys($nœuds), 0);
		foreach($liens as $type => $liensType)
		{
			$inv = in_array($type, static::$LiensInverses);
			foreach($liensType as $source => $cibles)
				foreach($cibles as $cible => $poids)
				{
					if(!isset($nœuds[$cible]) || !isset($nœuds[$source]))
					{
						foreach([ $cible, $source ] as $présent)
							if(isset($nœuds[$présent]))
								++$oubliettes[$présent];
						continue;
					}
					$idSource = $this->id($nœuds[$inv ? $cible : $source]);
					$idCible = $this->id($nœuds[$inv ? $source : $cible]);
					$affl[] = $idSource.' -> '.$idCible.' '.$this->style($this->propsLien($type, $poids));
				}
		}
		// Infobulle représentant les nœuds non affichés.
		$oubliettes = array_filter($oubliettes);
		foreach($oubliettes as $id => $n)
		{
			$idaff = $this->id($nœuds[$id]);
			$affl[] = $idaff.'_rab '.$this->style([ 'shape' => 'egg', 'style' => 'dashed', 'label' => '+ '.$n ]);
			$affl[] = $idaff.' -> '.$idaff.'_rab '.$this->style([ 'arrowhead' => 'none', 'style' => 'dashed' ]);
			$affl[] = "{ rank=same; $idaff; ${idaff}_rab; }";
		}
		$affl = array_map(function($x) { return "\t".$x."\n"; }, $affl);
		return 'digraph'."\n".'{'."\n".implode('', $affn).implode('', $affl)."\n".'}';
	}
	
	/**
	 * Renvoie un identifiant dot pour le nœud passé en paramètre.
	 * 
	 * @return string ID dot; en plus de respecter la nomenclature dot (alphanum, pas de num en premier), est idéalement stable: ainsi deux graphes incluant le même nœud l'auront sous le même identifiant, ce qui permettra une transition entre les graphes.
	 */
	protected function id($nœud)
	{
		return strtr($nœud['num'], '-', '_');
	}
	
	protected function style($props)
	{
		if(!$props) return '';
		$r = array();
		foreach($props as $clé => $val)
		{
			$val =
				substr($val, 0, 1) == '<'
				? $val
				: '"'.strtr($val, array('"' => '\"')).'"'
			;
			$r[] = $clé.'='.$val;
		}
		return '[ '.implode(', ', $r).' ]';
	}
	
	const HTML_NŒUD =
	'<
		<table border="0" cellborder="1" cellspacing="0" color="@foncé">
			<tr><td bgcolor="@foncé"><font color="white">@num</font></td></tr>
			<tr><td bgcolor="@clair">@nom</td></tr>
		</table>
	>';
	
	protected function propsNœud($nœud)
	{
		$couls = $this->couleurs($nœud);
		$affNœud = strtr(self::HTML_NŒUD,
		[
			'@num' => $nœud['num'],
			'@nom' => $this->jolibellé($nœud['nom']),
			'@foncé' => $couls[0],
			'@clair' => $couls[1],
		]);
		$r = array
		(
			'label' => $affNœud,
			'shape' => 'plain',
		);
		
		$classe = [];
		if(isset($nœud['nature']))
			$classe[] = 't'.$this->attrEnClasse($nœud['nature']['num']);
		if(isset($nœud['etat']))
			$classe[] = 'e'.$this->attrEnClasse($nœud['etat']['num']);
		if(count($classe))
			$r['class'] = implode(' ', $classe);
		
		return $r;
	}
	
	public function css()
	{
		return '';
	}
	
	public function couleurs($nœud)
	{
		return [ '#5f5f5f', '#ffffbf' ];
	}
	
	protected function jolibellé($libellé, $idéal = 32)
	{
		// On essaie de découper le libellé en lignes de longueur proche, dans une certaine limite de caractères par ligne.
		
		$taille = mb_strlen($libellé);
		$tFragment = $taille / ($nFragments = ceil($taille / $idéal));
		$posDernier = 0;
		$fragments = array();
		for($i = 0; ++$i < $nFragments;)
		{
			// Partant de la césure "idéale", on sonde en alternant un coup à gauche, un coup à droite, jusqu'à trouver le premier espace à proximité de la césure.
			$posi = $i * $tFragment - 0.5; // Une chaîne de 7 caractères à couper en 2 trouvera sa césure en 3,5, soit au caractère d'indice 3.
			$pos = round($posi);
			$pas = $pos < $posi ? 1 : -1; // Si l'arrondi nous fait partir un peu en arrière, le pas suivant sera en avant, et inversement.
			while(true)
			{
				if($pos <= 0) continue 2;
				if($pos >= $taille) break 2;
				if(substr($libellé, $pos, 1) == ' ')
					break;
				$pos += $pas;
				$pas = $pas > 0 ? -$pas - 1 : -$pas + 1;
			}
			if($pos > $posDernier)
			{
				$fragments[] = substr($libellé, $posDernier, $pos - $posDernier);
				$posDernier = $pos + 1; // On saute aussi l'espace.
			}
		}
		if($posDernier < $taille)
			$fragments[] = substr($libellé, $posDernier);
		
		return implode('<br/>', array_map('htmlspecialchars', $fragments));
	}
	
	protected function propsLien($type, $poids)
	{
		if(isset(static::$Flèches[$type]))
			return static::$Flèches[$type];
	}
	
	public function attrEnClasse($attr)
	{
		return preg_replace('/[^a-z]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $attr)));
	}
}

?>
