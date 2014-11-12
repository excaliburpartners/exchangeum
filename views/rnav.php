<?php

$show[] = '<li><a ' 
	. ($_REQUEST['exchangeum_form'] == '' 
	? 'class="current ui-state-highlight" ' : '') 
	. 'href="config.php?type=setup&display=exchangeum">Settings</a></li>';
	
$show[] = '<li><a ' 
	. ($_REQUEST['exchangeum_form'] == 'orgs_edit' && $_REQUEST['edit'] == ''
	? 'class="current ui-state-highlight" ' : '') 
	. 'href="config.php?type=setup&display=exchangeum&exchangeum_form=orgs_edit&edit=">Add Organization</a></li>';

foreach ($orgs as $org)
{
	$show[] = '<li><a ' 
		. ($_REQUEST['exchangeum_form'] == 'orgs_edit' && $_REQUEST['edit'] == $org['id']
		? 'class="current ui-state-highlight" ' : '') 
		. 'href="config.php?type=setup&display=exchangeum&exchangeum_form=orgs_edit&edit=' . $org['id'] . '">' . $org['name'] . '</a></li>';			
}

echo '
<div class="rnav"><ul>';
foreach ($show as $s) {
	echo $s;
}
echo '
</ul></div>';
?>
