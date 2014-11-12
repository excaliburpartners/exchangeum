<?php /* $Id */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

/*
 * Copyright (C) 2014 Excalibur Partners, LLC (info@excalibur-partners.com)
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
 
$orgs = exchangeum_get_orgs();

include('views/rnav.php');
echo '<div id="content">';

switch($_GET['exchangeum_form'])
{
	case 'orgs_edit':
		if(isset($_GET['delete']))
		{
			exchangeum_delete_orgs_edit($_GET['delete']);
			
			redirect_standard('exchangeum_form');
		}
		
		if(isset($_POST['action']) && $_POST['action'] == 'edit')
		{	
			$org['name'] = $_POST['name'];
			
			foreach($_POST['trunkid'] as $key=>$value)
				$org['trunks'][$key]['trunkid'] = $value;

			$id = exchangeum_save_orgs_edit($_GET['edit'], $org);
			
			redirect('config.php?type=setup&display=exchangeum&exchangeum_form=orgs_edit&edit=' . $id);
		}
		
		$org = exchangeum_get_orgs_edit($_GET['edit']);
			
		require 'modules/exchangeum/views/exchangeum_orgs_edit.php';
		break;

	default:
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
			'' => _("Disabled: Use FreePBX Voicemail")
		);
		
		foreach($orgs as $org)
			$enabled[$org['id']] = _("Enabled: Send to ") . $org['name'];
		
		require 'modules/exchangeum/views/exchangeum_general.php';
		break;
}

echo '</div>';

?>