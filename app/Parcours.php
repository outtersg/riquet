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
	
	const NORMAL = 1;
	const GROS = 0;
	const FORCÉ = 2;
	
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
		
		$notifRetenus = method_exists($this->chargeur, 'notifRetenu');
		
		$faits = array();
		$liens = array();
		$àFaire = array_keys($plus);
		while(count($àFaire) || (isset($this->chargeur->_àCharger) && count($this->chargeur->_àCharger)))
		{
			$nouveaux = $this->chargeur->charger($àFaire);
			$nouveauxLiens = $this->chargeur->chargerLiens($nouveaux);
			
			$this->_enCours = [ & $liens, & $faits, $plus, $moins, & $bofs, $plaf, $notifRetenus ];
			list($nouveaux, $àFaire) = $this->_reçu($nouveaux, $nouveauxLiens);
			
			/* Enregistrement et tour suivant. */
			
			$faits += $nouveaux;
			$àFaire = array_keys($àFaire);
		}
		
		return array($faits, $liens);
	}
	
	protected function _reçu($nouveaux, $nouveauxLiens)
	{
		list($liens, $faits, $plus, $moins, $bofs, $plaf, $notifRetenus) = $this->_enCours;
		$liens =& $this->_enCours[0];
		$faits =& $this->_enCours[1];
		$bofs =& $this->_enCours[4];
		
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
			
			$bofsCeTourCi = [];
			foreach($àFaire = array_diff_key($liés, $faits) as $id => $autres)
				if(count($autres) > $plaf)
					$bofsCeTourCi[$id] = !isset($plus[$id]); // Le fait de figurer dans $plus offre un repêchage.
			$bofs += array_filter($bofsCeTourCi);
			if($notifRetenus)
				foreach($nouveaux as $id => $données)
					$this->chargeur->notifRetenu($id, $données, $àFaire[$id], isset($bofsCeTourCi[$id]) ? ($bofsCeTourCi[$id] ? self::GROS : self::FORCÉ) : self::NORMAL);
			$àFaire = array_diff_key($àFaire, $nouveaux);
			foreach($àFaire as $id => $autres)
				if(!count(array_diff_key($autres, $bofs)))
					unset($àFaire[$id]);
		
		return [ $nouveaux, $àFaire ];
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
	
	/**
	 * Rétenteur.
	 * Les classes qui, pour des besoins de retour utilisateur, ont besoin d'appeler notifRetenu() au fur et à mesure,
	 * peuvent surcharger charger() par un appel à cette fonction.
	 */
	/* À FAIRE: découpler chargement et calcul des liens dans le Parcours.
	 * Cela sera nécessaire si on veut pouvoir faire du chargement parallèle:
	 * pour l'heure le fonctionnement est
	 *   foreach(ids) chargerUn(id); // ou charger(ids)
	 *   foreach(ids) chargerLiensUn(id); // ou chargerLiens(ids)
	 *   foreach(ids) notifRetenu(id);
	 * qui marche bien en SQL (charger(ids) est une seule opération; et ainsi le chargerLiens peut travailler sur ensemble plutôt qu'unité)
	 * mais pas en WS (on voudrait pouvoir lancer tous les chargements en parallèle, puis *au fur et à mesure* qu'on reçoit, chargerLiensUn et notifRetenu),
	 * ce que l'on pallie par ce _chargerUnParUn mais ne résoud pas nos problèmes.
	 * Il faudrait donc que le chargerLiensUn et le notifRetenu soient appelés sur retour de charger ou chargerUn.
	 */
	protected function _chargerUnParUn($àFaire)
	{
		isset($this->_àCharger) || $this->_àCharger = [];
		$this->_àCharger += array_flip($àFaire);
		foreach($this->_àCharger as $id => $bla)
		{
			unset($this->_àCharger[$id]);
			return [ $id => $this->chargerUn($id) ];
		}
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
