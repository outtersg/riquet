<?php

require_once dirname(__FILE__).'/../app/app.php';
require_once R.'/app/import/ServiceNowApi.php';

$app = new App();
$classe = $app->classe('ServiceNowApi');
fprintf(STDERR, "Extraction via la classe %s\n", $classe);

$sn = new $classe($app->config['servicenow'], '/tmp/sn.php.cache');
if(isset($argv[1]) && $argv[1] == '--init')
	$csv = $sn->tout();
else
	$csv = $sn->actuel();

// sysparm_display_value=all, displayvalue=all n'ont pas d'effet sur state.
// Mais en allant voir https://www.snow-mirror.com/introduction-to-display-values/ on déniche u_state qui nous intéresse.
// https://github.com/dograga/ServiceNowPython/blob/master/getrecords.py
//url="https://[[your servicenow url]]/api/now/table/task?sysparm_query=sys_created_onBETWEENjavascript:gs.dateGenerate('"+self.startdate+"','00:00:00')@javascript:gs.dateGenerate('"+self.enddate+"','23:59:59')&assignment_group=[[ servicenow assignment id ]]&sysparm_fields=number,short_description,caller_id.name,sys_created_by,impact,active,priority,closed_by,u_customer,made_sla,u_state,close_notes,sys_class_name,contact_type,sys_created_on"

echo iconv('cp1252', 'utf-8', $csv);

?>
