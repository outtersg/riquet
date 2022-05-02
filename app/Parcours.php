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

class Parcours
{
	/**
	 * Plafond cul-de-sac.
	 * Si une fiche a plus de $_plaf fiches liées, on la considère trop générale donc à ne pas parcourir.
	 */
	protected $_plaf = 5;
	
	public function __construct($chargeur)
	{
		$this->chargeur = method_exists($chargeur, 'charger') ? $chargeur : new ProxyMonoChargeur($chargeur);
	}
	
	public function parcourir($plus, $moins = array(), $bofs = array(), $plaf = 5)
	{
		if(method_exists($this->chargeur, 'idNums'))
		{
			$plus = $this->chargeur->idNums($plus);
			$moins = $this->chargeur->idNums($moins);
			$bofs = $this->chargeur->idNums($bofs);
		}
		else
		{
			$plus = array_combine($plus, $plus);
			$moins = array_combine($moins, $moins);
			$bofs = array_combine($bofs, $bofs);
		}
		
		$faits = array();
		$liens = array();
		$àFaire = array_keys($plus);
		while(count($àFaire))
		{
			$nouveaux = $this->chargeur->charger($àFaire);
			$nouveauxLiens = $this->chargeur->chargerLiens($nouveaux);
			// $nouveauxLiens doit avoir pour indices de premier niveau le type de lien. Si c'est un "bête" tableau (indices numériques), c'est sans doute un agrégat de tableaux retour indépendants (à combiner).
			$listesDeNouveauxLiens = isset($nouveauxLiens[0]) ? $nouveauxLiens : [ $nouveauxLiens ];
			
			/* Parcours des liens. */
			
			$liés = array();
			foreach($listesDeNouveauxLiens as $nouveauxLiens)
			foreach($nouveauxLiens as $lType => $ls1)
			{
				$ls1 = array_diff_key($ls1, $moins);
				foreach($ls1 as $lDe => $ls2)
				{
					$ls2 = array_diff_key($ls2, $moins);
					if(count($ls2))
					{
						isset($liens[$lType][$lDe]) || $liens[$lType][$lDe] = array();
						isset($liés[$lDe]) || $liés[$lDe] = array();
						$liens[$lType][$lDe] += $ls2;
						/* Décompte des liés
						 * Chaque couple de nœuds ne compte qu'une fois, même si plusieurs liens les unissent.
						 */
						$liés[$lDe] += $ls2;
						foreach($ls2 as $lVers => $bla)
							$liés[$lVers][$lDe] = true;
					}
				}
			}
			
			/* Nœuds à parcourir à la prochaine itération?
			/* Blocage de propagation: si un nœud a trop de liens, il devient "bof", figurant encore dans le résultat, mais marquant un point d'arrêt à la propagation.
			 * On ignore donc les bofs, ainsi que les nœuds accessibles uniquement par des bofs.
			 */
			
			foreach($àFaire = array_diff_key($liés, $faits) as $id => $autres)
				if(count($autres) > $plaf)
					$bofs[$id] = true;
			$àFaire = array_diff_key($àFaire, $nouveaux);
			foreach($àFaire as $id => $autres)
				if(!count(array_diff_key($autres, $bofs)))
					unset($àFaire[$id]);
			
			/* Enregistrement et tour suivant. */
			
			$faits += $nouveaux;
			$àFaire = array_keys($àFaire);
		}
		
		return array($faits, $liens);
	}
}

trait MonoChargeur
{
	public function charger($àFaire)
	{
		$nœuds = [];
		foreach($àFaire as $id)
			$nœuds[$id] = $this->chargerUn($id);
		return $nœuds;
	}
	
	public function chargerLiens($àFaire)
	{
		$liens = [];
		foreach($àFaire as $truc)
			$liens[] = $this->chargerLiensUn($truc);
		return $liens;
	}
}

class ProxyMonoChargeur
{
	use MonoChargeur;
	
	public function __construct($chargeur)
	{
		$this->_chargeur = $chargeur;
	}
	
	public function chargerUn($id) { return $this->_chargeur->chargerUn($id); }
	public function chargerLiensUn($truc) { return $this->_chargeur->chargerLiensUn($truc); }
}

?>
