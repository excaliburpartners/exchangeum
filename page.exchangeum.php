<?php /* $Id */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

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
<form name="edit" action="<?php echo $_SERVER['PHP_SELF']; ?>" method=POST>
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