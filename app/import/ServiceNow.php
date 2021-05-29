<?php

class ServiceNow
{
	public static $CSV = array
	(
		/* Champs standard.
		 * Théoriques:
		 *   https://developer.servicenow.com/dev.do#!/reference/api/orlando/rest/c_TableAPI
		 *   https://cloud-elements.github.io/docs/elements/servicenow/ko/api-documentation.html?elementId=145#!/incidents
		 * Sur une vraie instance:
		 *   https://stackoverflow.com/questions/56994277/servicenow-rest-api-get-list-of-column-names (mais requiert un compte autorisé API sur une vraie instance).
		 */
		'number'                     => 'num',
		'sys_id'                     => 'id_ext',
		'sys_created_on'             => null,
		'opened_at'                  => 'ctime', // Utilisé de préférence à sys_created_on (car reflétant son heure de déclaration alors que sys_created_on donne l'heure d'arrivée dans ServiceNow, qui peut différer si la décl a eu lieu dans un autre système puis a été importée dans ServiceNow).
		'closed_at'                  => 'dtime',
		'resolved_at'                => null, // Parfois une tâche fermée n'a pas de date de fermeture, mais uniquement une de résolution.
		'business_duration'          => null,
		'short_description'          => 'nom',
		//'description'                => null,
		//'comments_and_work_notes'    => null,
		'parent'                     => '@^', // Parent
		'assignment_group'           => '@>', // Affectation
		'group_list'                 => null,
		'assigned_to'                => null,
		'additional_assignee_list'   => null,
		'u_contact_type'             => '@o', // Origine
		'category'                   => null,
		'subcategory'                => null,
		'urgency'                    => '@p', // Poids / priorité
		'priority'                   => '@p', // Poids / priorité
		'state'                      => '@e', // État
		//'close_code'                 => null,
	);
}

?>
