<?php

class ServiceNow
{
	public static $CSV = array
	(
		'number'                     => 'num',
		'sys_id'                     => 'id_ext',
		//'sys_created_on'             => 'ctime',
		'opened_at'                  => 'ctime',
		'closed_at'                  => 'dtime',
		'short_description'          => 'nom',
		'parent'                     => '@^', // Parent
		'assignment_group'           => '@>', // Affectation
		'urgency'                    => '@p', // Poids / priorité
		'priority'                   => '@p', // Poids / priorité
		'state'                      => '@e', // État
	);
}

?>
