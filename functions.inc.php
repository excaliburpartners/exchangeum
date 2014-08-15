<?php 
/* $Id */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

function exchangeum_save_settings($settings) {
	global $db;
	
	if (is_array($settings)) foreach($settings as $key => $value){
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

function exchangeum_sip_trunks() {
	$result = sql('SELECT * FROM `trunks` WHERE tech = \'SIP\' ORDER BY `trunkid`','getAll',DB_FETCHMODE_ASSOC);
	
	$trunk_list = array();
	$trunk_list[''] = '';
	foreach ($result as $trunk) {
		$trunk_list[$trunk['trunkid']] = $trunk['name'];
	}
	
	return $trunk_list;
}

function exchangeum_configpageinit($pagename) {
	global $currentcomponent;
	
	// On a 'new' user, 'tech_hardware' is set, and there's no extension. 
	if ( 
		isset($_REQUEST['display'])
		&& ($_REQUEST['display'] == 'users' || $_REQUEST['display'] == 'extensions')
		&& isset($_REQUEST['extdisplay']) 
	) {
		$currentcomponent->addoptlistitem('umenabled', '', _("Disabled: Use FreePBX Voicemail"));
		$currentcomponent->addoptlistitem('umenabled', 'true', _("Enabled: Send to Exchange Voicemail"));
		$currentcomponent->setoptlistopts('umenabled', 'sort', false);
				
		$currentcomponent->addguifunc('exchangeum_configpageload');
		$currentcomponent->addprocessfunc('exchangeum_configprocess', 1);
	}
}

// This is called before the page is actually displayed, so we can use addguielem(). draws hook on the extensions/users page
function exchangeum_configpageload() {
	global $currentcomponent;
	global $display;
	
	$extdisplay=isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:'';
	$extensions=isset($_REQUEST['extensions'])?$_REQUEST['extensions']:'';
	$users=isset($_REQUEST['users'])?$_REQUEST['users']:'';
	
	if ($display == 'extensions' || $display == 'users') {
		$exchangeum = exchangeum_get_user($extdisplay);
		
		$section = _('Exchange UM');	
		$currentcomponent->addguielem($section, new gui_selectbox('umenabled', $currentcomponent->getoptlist('umenabled'), $exchangeum['umenabled'], _("Status"), '', false));
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

function exchangeum_save_user($umext,$umenabled) {
	global $db;
	global $astman;
	
	$sql = 'REPLACE INTO exchangeum_users (user, umenabled) VALUES (?, ?)';
	$ret = $db->query($sql, array($umext, $umenabled));
	db_e($ret);
	
	if($astman) {
		$astman->database_put('EXCHUM', $umext, $umenabled);
	}
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

function exchangeum_devicemailbox() {
	global $db;
	
	$context = sql("SELECT data FROM sip 
		WHERE id = (SELECT CONCAT('tr-peer-',value) FROM exchangeum_details WHERE `key` = 'trunk')
		AND keyword = 'unsolicited_mailbox'",'getOne');
	
	$results = sql("SELECT users.name, users.extension, devices.id, exchangeum_users.umenabled,
		(SELECT data FROM sip WHERE id = devices.id AND keyword = 'mailbox') AS mailbox
		FROM users
		INNER JOIN devices ON users.extension = devices.user
		LEFT OUTER JOIN exchangeum_users ON users.extension = exchangeum_users.user
		WHERE devices.tech = 'sip' ",'getAll',DB_FETCHMODE_ASSOC);
	
	$reload = false;
	
	foreach($results as $result) {
		$mailbox = $result['umenabled'] == 'true' && $context != null ? 
			$result['extension'].$context : $result['id'].'@device';
			
			if($result['mailbox'] != $mailbox) {
				sql("UPDATE sip SET data = '".$mailbox."' WHERE id = '".$result['id']."' AND keyword = 'mailbox'");			
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
	$ext->splice('macro-vm', 'vmx', 0, new ext_gosubif('$["${DB(EXCHUM/${ARG1})}" = "true"]', 'macro-exchangeum,s,1','','${ARG1}')); // Add hook into macro-vm
	
	$context = 'macro-exchangeum';
	$exten = 's';
	
	$digits = sql('SELECT value FROM exchangeum_details WHERE `key` = \'digits\'','getRow');
	
	$trunk = sql('SELECT channelid FROM exchangeum_details 
		INNER JOIN trunks ON exchangeum_details.value = trunks.trunkid 
		WHERE `key` = \'trunk\'','getRow');

	$ext->add($context, $exten, '', new ext_set('EXCHBOX','${ARG1:-'.$digits[0].':'.$digits[0].'}'));
	$ext->add($context, $exten, '', new ext_gotoif('$["${ARG2}" != "DIRECTDIAL"]', 'notdirect'));
	$ext->add($context, $exten, '', new ext_answer());
	$ext->add($context, $exten, '', new ext_wait('2'));
	$ext->add($context, $exten, 'notdirect', new ext_sipremoveheader('Diversion'));
	$ext->add($context, $exten, '', new ext_sipaddheader('Diversion', '<tel:${EXCHBOX}>\;reason=no-answer\;screen=no\;privacy=off'));
	$ext->add($context, $exten, '', new ext_sipremoveheader('Alert-Info'));
	$ext->add($context, $exten, '', new ext_dial('SIP/'.$trunk[0], '300,${TRUNK_OPTIONS}'));
	$ext->add($context, $exten, '', new ext_hangup());
}

function exchangeum_get_config($engine) {
	global $ext;
	
	$fcc = new featurecode('exchangeum', 'myvoicemail');
	$fc_my_vm= $fcc->getCodeActive();
	unset($fcc);
	
	$fcc = new featurecode('exchangeum', 'dialvoicemail');
	$fc_dial_vm = $fcc->getCodeActive();
	unset($fcc);
	
	if($fc_my_vm == '' && $fc_dial_vm == '')
		return;
		
	$ext->addInclude('from-internal-additional', 'app-exchangeum'); // Add the include from from-internal
	
	$context = 'app-exchangeum';
	
	$trunk = sql('SELECT channelid FROM exchangeum_details 
		INNER JOIN trunks ON exchangeum_details.value = trunks.trunkid 
		WHERE `key` = \'trunk\'','getRow');
	
	if ($fc_my_vm != '') {		
		$ext->add($context, $fc_my_vm, '', new ext_macro('user-callerid'));
		$ext->add($context, $fc_my_vm, '', new ext_dial('SIP/'.$trunk[0], '300,${TRUNK_OPTIONS}'));
		$ext->add($context, $fc_my_vm, '', new ext_macro('hangupcall'));
	}
	
	if ($fc_dial_vm != '') {
		$ext->add($context, $fc_dial_vm, '', new ext_set('CALLERID(num)',''));
		$ext->add($context, $fc_dial_vm, '', new ext_dial('SIP/'.$trunk[0], '300,${TRUNK_OPTIONS}'));
		$ext->add($context, $fc_dial_vm, '', new ext_macro('hangupcall'));
	}
}

?>
