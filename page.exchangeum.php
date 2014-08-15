<?php /* $Id */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

/*
 * Copyright (C) 2013 Excalibur Partners, LLC (info@excalibur-partners.com)
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// configure device mailbox
if (isset($_REQUEST['action']) &&  $_REQUEST['action'] == 'devicemailbox'){	
	exchangeum_devicemailbox();
	redirect_standard();
}

// reset astdb
if (isset($_REQUEST['action']) &&  $_REQUEST['action'] == 'resetall'){	
	exchangeum_users2astdb();
	redirect_standard();
}

$get_vars = array(
	'digits'		=> '4',
	'enabled'		=> '',
	'trunk'			=> '', 

);
foreach($get_vars as $k => $v){
	$exchangeum[$k] = isset($_REQUEST[$k]) ? $_REQUEST[$k] : $v;
}

// get/put options
if (isset($_REQUEST['action']) &&  $_REQUEST['action'] == 'edit'){
	exchangeum_save_settings($exchangeum);
}
$exchangeum = array_merge($exchangeum, exchangeum_get_settings());
$action = '';//no action to do

$trunks = exchangeum_sip_trunks();

$digits = array(
	'2' => '2',
	'3' => '3',
	'4' => '4',
	'5' => '5',
	'6' => '6',
	'7' => '7',
	'8' => '8',
	'9' => '9',
	'10' => '10'
);
	
$enabled = array(
	'' => _("Disabled: Use FreePBX Voicemail"),
	'true' => _("Enabled: Send to Exchange Voicemail")
);

?>

<h2><?php echo _("Exchange UM Settings")?></h2>
<form name="edit" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
<input type="button" value="Set Device Mailbox" onclick="if(confirm('Are you sure you want to update the mailbox MWI setting for all devices?')) location.href='config.php?display=exchangeum&action=devicemailbox'" />
<input type="button" value="Initial Setup" onclick="if(confirm('Are you sure you want to set users not configured to \'<?php echo $enabled[$exchangeum['enabled']] ?>\'?')) location.href='config.php?display=exchangeum&action=resetall'" />

<table id="exchangeumoptionstable">		
<tbody>
	<tr><td colspan="2"><h5><?php echo _("Options")?><hr/></h5></td></tr>			
	<tr>
		<td width="175"><?php echo _("Extension Length")?></td>
		<td><?php echo form_dropdown('digits', $digits, $exchangeum['digits']); ?></td>	
	</tr>
	<tr>
		<td width="175"><?php echo _("Default Status")?></td>
		<td><?php echo form_dropdown('enabled', $enabled, $exchangeum['enabled']); ?></td>	
	</tr>		
	<tr>
		<td colspan="2"><h5><?php echo _("Trunk for Exchange UM")?><hr></h5></td>
	</tr>
	<tr>
		<td colspan="2"><?php echo form_dropdown('trunk', $trunks, $exchangeum['trunk']); ?></td>		
	</tr>	
</tbody>
</table>
<br />

<input type="hidden" value="exchangeum" name="display"/>
<input type="hidden" name="action" value="edit">
<input type=submit value="<?php echo _("Submit")?>">
</form>
<?php
//add hooks
echo $module_hook->hookHtml;
?>