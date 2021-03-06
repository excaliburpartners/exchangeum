<?php 
/* $Id */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

function exchangeum_get_orgs() {
	return sql("SELECT id, name FROM exchangeum_orgs ORDER BY id",'getAll',DB_FETCHMODE_ASSOC);
}

function exchangeum_get_trunks($orgid) {
	global $db;
	
	return sql("SELECT trunks.trunkid, `continue` FROM exchangeum_org_trunks 
		INNER JOIN trunks ON exchangeum_org_trunks.trunkid = trunks.trunkid 
		WHERE exchangeum_org_trunks.orgid = '".$db->escapeSimple($orgid)."'
		ORDER BY exchangeum_org_trunks.seq",'getAll',DB_FETCHMODE_ASSOC);
}

function exchangeum_get_mwi_context($orgid) {
	global $db;
	
	return sql("SELECT data FROM sip 
		WHERE id = (SELECT CONCAT('tr-peer-',trunkid) FROM exchangeum_org_trunks 
			WHERE orgid = '".$db->escapeSimple($orgid)."' AND seq = 0)
		AND keyword = 'unsolicited_mailbox'",'getOne');
}

function exchangeum_save_settings($settings) {
	global $db;
	
	if (is_array($settings)) 
		foreach($settings as $key => $value){
			sql("REPLACE INTO exchangeum_details (`key`, `value`) VALUES ('".$key."','".$db->escapeSimple($value)."')");
		}
	
	needreload();
}

function exchangeum_get_settings() {
	$settings = sql('SELECT * FROM exchangeum_details', 'getAssoc', 'DB_FETCHMODE_ASSOC');
	
	foreach($settings as $setting => $value){
		$set[$setting]=$value['0'];
	}
	
	if(!is_array($set)) {
		$set=array();
	}//never return a null value
	
	return $set;
}

function exchangeum_dropdown_trunks() {
	$result = sql('SELECT * FROM `trunks` 
		WHERE tech = \'SIP\' AND disabled = "off" 
		ORDER BY `trunkid`','getAll',DB_FETCHMODE_ASSOC);
	
	$trunk_list = array();
	$trunk_list[''] = '';
	foreach ($result as $trunk) {
		$trunk_list[$trunk['trunkid']] = $trunk['name'];
	}
	
	return $trunk_list;
}

function exchangeum_get_orgs_edit($id) {
	global $db;
	
	$org = sql("SELECT name FROM exchangeum_orgs
		WHERE id = \"{$db->escapeSimple($id)}\"",'getRow',DB_FETCHMODE_ASSOC);
	
	$trunks = sql("SELECT trunkid, seq FROM exchangeum_org_trunks
		WHERE orgid = \"{$db->escapeSimple($id)}\"
		ORDER BY seq",'getAll',DB_FETCHMODE_ASSOC);
	
	$org['trunks'] = array();
	foreach($trunks as $trunk)
		$org['trunks'][$trunk['seq']] = $trunk;

	return $org;
}

function exchangeum_save_orgs_edit($id, $org) {
	global $db;
	
	if(empty($id))
	{
		sql("INSERT INTO exchangeum_orgs (name) 
			VALUES ('".$db->escapeSimple($org['name'])."')");
		
		$id = sql("SELECT LAST_INSERT_ID()",'getOne');
		
		$fcc = new featurecode('exchangeum', 'dialvoicemail-' . $id);
		$fcc->setDescription('Dial Exchange: ' . $org['name']);
		$fcc->setDefault('3300' + $id);
		$fcc->setProvideDest();
		$fcc->update();
		unset($fcc);
	}
	else
	{
		sql("UPDATE exchangeum_orgs SET 
				name = '".$db->escapeSimple($org['name'])."'
			WHERE id = '".$db->escapeSimple($id)."'");
			
		$fcc = new featurecode('exchangeum', 'dialvoicemail-' . $id);
		$fcc->setDescription('Dial Exchange: ' . $org['name']);
		$fcc->update();
		unset($fcc);
	}
	
	sql("DELETE FROM exchangeum_org_trunks WHERE orgid = '".$db->escapeSimple($id)."'");
	
	foreach($org['trunks'] as $trunkid => $trunk)
		sql("INSERT INTO exchangeum_org_trunks (orgid, trunkid, seq)
			VALUES ('".$db->escapeSimple($id)."','".$db->escapeSimple($trunk['trunkid'])."','".
				$db->escapeSimple($trunkid)."')");

	needreload();
		
	return $id;
}

function exchangeum_delete_orgs_edit($id) {
	global $db;
	global $astman;
	
	$fcc = new featurecode('exchangeum', 'dialvoicemail-' . $id);
	$fcc->delete();
	unset($fcc);
	
	$results = sql("SELECT user FROM exchangeum_users WHERE umenabled = '".$db->escapeSimple($id)."'",'getAll',DB_FETCHMODE_ASSOC);

	foreach($results as $result) {
		if($astman) {
			$astman->database_del('EXCHUM', $umext);
		}
	}

	sql("DELETE FROM exchangeum_users WHERE umenabled = '".$db->escapeSimple($id)."'");
	
	sql("DELETE FROM exchangeum_orgs WHERE id = '".$db->escapeSimple($id)."'");
	sql("DELETE FROM exchangeum_org_trunks WHERE orgid = '".$db->escapeSimple($id)."'");
}

function exchangeum_configpageinit($pagename) {
	global $currentcomponent;
	
	// We only want to hook 'devices' pages.
	if ($pagename == 'devices')  {
		$currentcomponent->addprocessfunc('exchangeum_configprocess_device', 8);
	}
	
	// We only want to hook 'users' or 'extensions' pages.
	if ($pagename == 'users' || $pagename == 'extensions')  {
		$currentcomponent->addoptlistitem('umenabled', '', _("Disabled: Use FreePBX Voicemail"));
		
		foreach(exchangeum_get_orgs() as $org)
			$currentcomponent->addoptlistitem('umenabled', $org['id'], _("Enabled: Send to ") . $org['name']);
			
		$currentcomponent->setoptlistopts('umenabled', 'sort', false);
				
		$currentcomponent->addguifunc('exchangeum_configpageload');
		$currentcomponent->addprocessfunc('exchangeum_configprocess', 8);
	}
}

// This is called before the page is actually displayed, so we can use addguielem(). draws hook on the extensions/users page
function exchangeum_configpageload() {
	global $currentcomponent;
	global $display;
	
	$tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;
	$view = isset($_REQUEST['view'])?$_REQUEST['view']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	
	if ($tech_hardware != null || $view == 'add' || !empty($extdisplay)) {
		$exchangeum = exchangeum_get_user($extdisplay);
		$category = "voicemail";
		
		$section = _('Exchange UM');	
		$currentcomponent->addguielem($section, new gui_selectbox('umenabled', $currentcomponent->getoptlist('umenabled'), $exchangeum['umenabled'], _("Status"), '', false), $category);
	}
}

function exchangeum_get_user($umext) {
	global $db;
	
	if ($umext) {
		$sql		= "SELECT * FROM exchangeum_users WHERE user = ?";
		$settings	= $db->getRow($sql, array($umext), DB_FETCHMODE_ASSOC);
	}
	
	db_e($settings);
	
	//make sure were retuning an array (even if its blank)
	if (!is_array($settings)) {
		$settings = array();
		
		$defaults = exchangeum_get_settings();
		$settings['umenabled'] = $defaults['enabled'];
	}
	
	return $settings;
}

//prosses received arguments
function exchangeum_configprocess() {
	$action		= isset($_REQUEST['action']) ?$_REQUEST['action']:null;
	$ext		= isset($_GET['extdisplay'])?$_GET['extdisplay']:$_REQUEST['extension'];
	$umenabled	= isset($_REQUEST['umenabled'])?$_REQUEST['umenabled']:null;

	switch ($action) {
		case 'add':
		case 'edit':
			exchangeum_save_user($ext,$umenabled);
 			break;
		case 'del':
			exchangeum_delete_user($ext);
			break;
	}
}

function exchangeum_configprocess_device() {
	$action		= isset($_REQUEST['action']) ?$_REQUEST['action']:null;
	$ext		= isset($_REQUEST['deviceuser'])?$_REQUEST['deviceuser']: null;
	
	if(empty($ext))
		return;
	
	switch ($action) {
		case 'add':
		case 'edit':
			exchangeum_devicemailbox($ext);
 			break;
	}
}

function exchangeum_save_user($umext,$umenabled) {
	global $db;
	global $astman;
	
	$sql = 'REPLACE INTO exchangeum_users (user, umenabled) VALUES (?, ?)';
	$ret = $db->query($sql, array($umext, $umenabled));
	db_e($ret);
	
	if($astman) {
		$astman->database_put('EXCHUM', $umext, $umenabled);
	}
	
	exchangeum_devicemailbox($umext);
}

function exchangeum_delete_user($umext) {
	global $db;
	global $astman;
	
	$umext = $db->escapeSimple($umext);
	sql('DELETE FROM exchangeum_users where user = "' . $umext . '"');
	
	if($astman) {
		$astman->database_del('EXCHUM', $umext);
	}
}

function exchangeum_devicemailbox($umext = null) {
	global $db;
	
	foreach(exchangeum_get_orgs() as $org)
		$context[$org['id']] = exchangeum_get_mwi_context($org['id']);
	
	$results = sql("SELECT users.extension, devices.id, exchangeum_users.umenabled,
		(SELECT data FROM sip WHERE id = devices.id AND keyword = 'mailbox') AS mailbox
		FROM users
		INNER JOIN devices ON users.extension = devices.user
		LEFT OUTER JOIN exchangeum_users ON users.extension = exchangeum_users.user
		WHERE devices.tech = 'sip'" . (!empty($umext) ? 
			"AND users.extension = '".$db->escapeSimple($umext)."'" : ""),'getAll',DB_FETCHMODE_ASSOC);
	
	$reload = false;
	
	// Update the device mailbox context to reflect the trunk unsolicited_mailbox
	foreach($results as $result) {
		$mailbox = $result['umenabled'] != '' && $context[$result['umenabled']] != null ? 
			$result['extension'].$context[$result['umenabled']] : $result['id'].'@device';
			
			if($result['mailbox'] != $mailbox) {
				sql("REPLACE INTO sip (id, keyword, data) VALUES ('".$result['id']."','mailbox','".$mailbox."')");
				$reload = true;
			}
	}
	
	if($reload)
		needreload();
}

function exchangeum_users2astdb() {
	global $db;
	global $astman;
	
	$defaults = exchangeum_get_settings();
	
	$results = sql("SELECT users.extension, exchangeum_users.user, exchangeum_users.umenabled FROM users 
		LEFT OUTER JOIN exchangeum_users on users.extension = exchangeum_users.user
		WHERE exchangeum_users.user IS NULL",'getAll',DB_FETCHMODE_ASSOC);

	foreach($results as $result) {
		sql("INSERT INTO exchangeum_users (user, umenabled)
			VALUES ('".$db->escapeSimple($result['extension'])."','".$defaults['enabled']."')");
	}

	$results = sql("SELECT user, umenabled FROM exchangeum_users",'getAll',DB_FETCHMODE_ASSOC);
	
	foreach($results as $result) {
		if($astman) {
			$astman->database_put('EXCHUM', $result['user'], $result['umenabled']);
		}
	}
}

function exchangeum_hookGet_config($engine) {
	global $ext;
	
	// ARG1 - extension
	// ARG2 - DIRECTDIAL/BUSY
	// ARG3 - RETURN makes macro return, otherwise hangup
	$ext->splice('macro-vm', 'vmx', 0, new ext_gosubif('$["foo${DB(EXCHUM/${ARG1})}" != "foo"]', 'macro-exchangeum,vm${DB(EXCHUM/${ARG1})},1','','${ARG1}')); // Add hook into macro-vm
}

function exchangeum_get_config($engine) {
	global $ext;
	
	$digits = sql('SELECT value FROM exchangeum_details WHERE `key` = \'digits\'','getOne');	
	
	// When dialing Exchange 2013 CAS directly, with no diversion header, and the mailbox is on a 2010 
	// or different 2013 mailbox server, the call will be blind transferred to the appropriate server.
	// If a diversion header is provided a 302 Moved Temporarily redirect is used instead of the transfer.
	
	$context = 'macro-exchangeum';
	
	foreach(exchangeum_get_orgs() as $org)
	{	
		$exten = 'exch'. $org['id'];
		
		$ext->add($context, $exten, '', new ext_sipremoveheader('Alert-Info'));
		$ext->add($context, $exten, '', new ext_set('INTRACOMPANYROUTE','YES'));

		// Override transfer context and track org id to handle Exchange blind transfers with no extension
		$ext->add($context, $exten, '', new ext_set('_TRANSFER_CONTEXT','from-exchangeum-xfer'));
		$ext->add($context, $exten, '', new ext_set('_EXCHORG',$org['id']));

		// Override dial options to prevent callers from being able to transfer
		$ext->add($context, $exten, '', new ext_set('_DIAL_OPTIONS',''));
		
		foreach(exchangeum_get_trunks($org['id']) as $trunk)
			$ext->add($context, $exten, '', new ext_macro('dialout-trunk', $trunk['trunkid'] . ',,,' . $trunk['continue']));
		
		$ext->add($context, $exten, '', new ext_macro("outisbusy"));
		
		$exten = 'vm'. $org['id'];
		
		$ext->add($context, $exten, '', new ext_set('EXCHBOX','${ARG1:-'.$digits.':'.$digits.'}'));
		$ext->add($context, $exten, '', new ext_gotoif('$["${ARG2}" != "DIRECTDIAL"]', 'notdirect'));
		$ext->add($context, $exten, '', new ext_answer());
		$ext->add($context, $exten, '', new ext_wait('2'));
		$ext->add($context, $exten, 'notdirect', new ext_sipremoveheader('Diversion'));
		$ext->add($context, $exten, '', new ext_sipaddheader('Diversion', '<tel:${EXCHBOX}>\;reason=no-answer\;screen=no\;privacy=off'));
		$ext->add($context, $exten, '', new ext_goto('1', 'exch' . $org['id']));
	}


	$context = 'from-exchangeum-xfer';
	$exten = 's';
	
	$ext->add($context, $exten, '', new ext_set('EXCHBOX','${SIPREFERREDBYHDR:-'.($digits+1).':'.$digits.'}'));

	// Override caller id so Exchange will go directly to the mailbox extension
	$ext->add($context, $exten, '', new ext_set('CALLERID(num)','${EXCHBOX}'));
	$ext->add($context, $exten, '', new ext_goto('1', 'exch${EXCHORG}', 'macro-exchangeum'));

	// Add the include for the default transfer extensions
	$ext->addInclude($context, 'from-internal-xfer');
	

	$context = 'app-exchangeum';

	// Include this context in the default internal context	
	$ext->addInclude('from-internal-additional', $context);

	// My voicemail feature code
	$fcc = new featurecode('exchangeum', 'myvoicemail');
	$fc_my_vm = $fcc->getCodeActive();
	unset($fcc);
	
	if ($fc_my_vm != '') {		
		$ext->add($context, $fc_my_vm, '', new ext_macro('user-callerid'));
		$ext->add($context, $fc_my_vm, '', new ext_gosubif('$["foo${DB(EXCHUM/${AMPUSER})}" != "foo"]', 'macro-exchangeum,exch${DB(EXCHUM/${AMPUSER})},1'));

		// User not enabled for Exchange voicemail
		$ext->add($context, $fc_my_vm, '', new ext_macro("outisbusy"));
	}

	// Additional feature codes
	foreach(featurecodes_getModuleFeatures('exchangeum') as $feature)
	{
		$parts = explode('-', $feature['featurename']);
		
		if(count($parts) != 2)
			continue;
	
		$fcc = new featurecode('exchangeum', $feature['featurename']);
		$fc_dial_vm = $fcc->getCodeActive();
		unset($fcc);
		
		if ($fc_dial_vm != '') {
			// Override caller id so Exchange will prompt for mailbox extension to directly access
			$ext->add($context, $fc_dial_vm, '', new ext_set('CALLERID(num)',''));
			$ext->add($context, $fc_dial_vm, '', new ext_gosub('1', 'exch' . $parts[1], 'macro-exchangeum'));	
		}
	}
}

?>
