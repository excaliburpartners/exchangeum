<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

global $db, $astman;

$fcc = new featurecode('exchangeum', 'myvoicemail');
$fcc->setDescription('My Exchange UM');
$fcc->setDefault('3300');
$fcc->setProvideDest();
$fcc->update();
unset($fcc);

$sql[]='CREATE TABLE IF NOT EXISTS `exchangeum_details` (
  `key` varchar(50) default NULL,
  `value` varchar(510) default NULL,
  PRIMARY KEY `key` (`key`)
)';

$sql[]='CREATE TABLE IF NOT EXISTS `exchangeum_users` (
  `user` varchar(15) default NULL,
  `umenabled` varchar(10) default NULL,
  PRIMARY KEY `user` (`user`)
)';

$sql[]='CREATE TABLE IF NOT EXISTS `exchangeum_orgs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) default NULL,
  PRIMARY KEY `id` (`id`)
)';

$sql[]='CREATE TABLE IF NOT EXISTS `exchangeum_org_trunks` (
  `orgid` int(11) NOT NULL,
  `trunkid` int(11) NOT NULL,
  `seq` int(11) NOT NULL,
  PRIMARY KEY  (`orgid`,`trunkid`,`seq`)
)';

foreach ($sql as $statement){
	$check = $db->query($statement);
	if (DB::IsError($check)){
		die_freepbx( "Can not execute $statement : " . $check->getMessage() .  "\n");
	}
}

// Migrate to organization format
$migrate = sql("SELECT `key` FROM exchangeum_details WHERE `key` = 'trunk'",'getOne');

if(!empty($migrate))
{		
	$sql[]="INSERT IGNORE INTO `exchangeum_orgs` (`id`, `name`) VALUES
	(1, 'Migrated Exchange');";

	$sql[]="INSERT IGNORE INTO `exchangeum_org_trunks` (`orgid`, `trunkid`, `seq`) 
		SELECT '1', value, '0' FROM exchangeum_details WHERE `key` = 'trunk'";
	
	$sql[]="UPDATE `exchangeum_users` SET `umenabled` = '1' WHERE `umenabled` = 'true'";
	
	$sql[]="UPDATE `exchangeum_details` SET `value` = '1' WHERE `key` = 'enabled' AND `value` = 'true'";
	
	$sql[]="DELETE FROM exchangeum_details WHERE `key` = 'trunk'";
	
	$sql[]="UPDATE featurecodes SET featurename = 'dialvoicemail-1', description = 'Dial Exchange: Migrated Exchange'
		WHERE modulename = 'exchangeum' AND featurename = 'dialvoicemail'";
	
	foreach ($sql as $statement){
		$check = $db->query($statement);
		if (DB::IsError($check)){
			die_freepbx( "Can not execute $statement : " . $check->getMessage() .  "\n");
		}
	}

	$results = sql("SELECT user, umenabled FROM exchangeum_users",'getAll',DB_FETCHMODE_ASSOC);

	foreach($results as $result) {
		if($astman) {
			$astman->database_put('EXCHUM', $result['user'], $result['umenabled']);
		}
	}
}

?>
