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
		'=' => array('arrowhead' => 'none', 'color' => 'black:black', 'style' => 'dashed'), // https://stackoverflow.com/a/6219948/1346819
		':' => array('arrowhead' => 'none', 'style' => 'dashed'),
		'x' => array('color' => 'black:black', 'arrowhead' => 'tee'), // On pourrait aussi jouer avec le label "en attente de": ⌛,🛇
	);
	
	public function exporter($nœuds, $liens)
	{
		$affn = array();
		foreach($nœuds as $id => $nœud)
			$affn[] = 'n'.$id.' '.$this->style($this->propsNœud($nœud));
		$affn = array_map(function($x) { return "\t".$x."\n"; }, $affn);
		foreach($liens as $type => $liensType)
		{
			$inv = in_array($type, static::$LiensInverses);
			foreach($liensType as $source => $cibles)
				foreach($cibles as $cible => $poids)
				{
					if($inv) { $hop = $source; $source = $cible; $cible = $hop; }
					$affl[] = 'n'.$source.' -> '.'n'.$cible.' '.$this->style($this->propsLien($type, $poids));
				}
		}
		$affl = array_map(function($x) { return "\t".$x."\n"; }, $affl);
		return 'digraph'."\n".'{'."\n".implode('', $affn).implode('', $affl)."\n".'}';
	}
	
	protected function style($props)
	{
		if(!$props) return '';
		$r = array();
		foreach($props as $clé => $val)
			$r[] = $clé.'="'.strtr($val, array('"' => '\"')).'"';
		return '[ '.implode(', ', $r).' ]';
	}
	
	protected function propsNœud($nœud)
	{
		return array
		(
			'label' => $nœud['num'],
		);
	}
	
	protected function propsLien($type, $poids)
	{
		if(isset(static::$Flèches[$type]))
			return static::$Flèches[$type];
	}
}

?>
