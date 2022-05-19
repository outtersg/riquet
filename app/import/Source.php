<?php

/**
 * Chapeau pilotant un import de type Api (interrogation) et un de type Import (poussage vers notre BdD).
 */
class Source
{
	public function lancer($argv)
	{
		$this->import->lancer($argv);
	}
}

?>
