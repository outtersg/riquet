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
		
		$this->faits = array();
		$this->bofs = $bofs;
		$this->liens = array();
		$this->àFaire = $plus;
		$this->enCours = [];
		$this->_params =
		[
			'+' => $plus,
			'-' => $moins,
			'plaf' => $plaf,
			'notif' => method_exists($this->chargeur, 'notifRetenu'),
		];
		
		while(count($this->àFaire + $this->enCours))
		{
			// enCours mémorise les entrées déjà passées en àFaire, mais non encore traitées
			// (par exemple si charger() décide de ne pas traiter tout ce qu'on lui demande, mais de s'en garder une partie pour plus tard):
			// supposant que le chargeur en a bien pris note, on évite de les lui redemander.
			// Notons que pour éviter une boucle infinie, le chargeur DOIT renvoyer un null pour ce qu'il a tenté de charger sans succès.
			$nouveaux = $this->chargeur->charger($this->àFaire(), $this);
			$this->_reçu($nouveaux);
		}
		
		return array($this->faits, $this->liens);
	}
	
	/**
	 * Renvoie la liste des prochains à faire, EN LA MARQUANT COMME PRISE EN CHARGE.
	 * L'appelant s'engage donc à lancer les requêtes dans la foulée.
	 */
	public function àFaire()
	{
		$this->enCours += $this->àFaire;
		return array_keys($this->àFaire);
	}
	
	protected function _reçu($nouveaux)
	{
		$plus = $this->_params['+'];
		$moins = $this->_params['-'];
		$plaf = $this->_params['plaf'];
		
		$nouveauxLiens = $this->chargeur->chargerLiens($nouveaux);
		
		// Les reçus ne sont plus en cours; mais pas encore tout à fait faits,
		// car reste une vérif à faire plus bas, savoir si on veut les entreposer complets (intéressants dans notre résultat) ou null (à écarter).
		/* À FAIRE: $moins pourrait être une simple entrée [ id: null ] dans $this->faits, comme ça on évite d'arry_diff_key deux fois, une avoir $moins, une avec $this->faits. */
		$this->enCours = array_diff_key($this->enCours, $nouveaux);
		
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
					isset($this->liens[$lType][$lDe]) || $this->liens[$lType][$lDe] = array();
						isset($liés[$lDe]) || $liés[$lDe] = array();
					$this->liens[$lType][$lDe] += $ls2;
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
		foreach($àFaire = array_diff_key($liés, $this->faits) as $id => $autres)
				if(count($autres) > $plaf)
				$bofsCeTourCi[$id] = !isset($this->_params['+'][$id]); // Le fait de figurer dans $this->_params['+'] offre un repêchage.
		$this->bofs += array_filter($bofsCeTourCi);
		if($this->_params['notif'])
				foreach($nouveaux as $id => $données)
					$this->chargeur->notifRetenu($id, $données, $àFaire[$id], isset($bofsCeTourCi[$id]) ? ($bofsCeTourCi[$id] ? self::GROS : self::FORCÉ) : self::NORMAL);
		$àFaire = array_diff_key($àFaire, $nouveaux, $this->enCours);
			foreach($àFaire as $id => $autres)
			if(!count(array_diff_key($autres, $this->bofs)))
					unset($àFaire[$id]);
		
		$this->faits += $nouveaux;
		$this->àFaire = $àFaire;
		return $nouveaux;
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
