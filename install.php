<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

global $db;

$fcc = new featurecode('exchangeum', 'myvoicemail');
$fcc->setDescription('My Exchange UM');
$fcc->setDefault('3300');
$fcc->setProvideDest();
$fcc->update();
unset($fcc);

$fcc = new featurecode('exchangeum', 'dialvoicemail');
$fcc->setDescription('Dial Exchange UM');
$fcc->setDefault('3301');
$fcc->setProvideDest();
$fcc->update();
unset($fcc);

$sql[]='CREATE TABLE IF NOT EXISTS `exchangeum_details` (
  `key` varchar(50) default NULL,
  `value` varchar(510) default NULL,
  PRIMARY KEY `key` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;';

$sql[]='CREATE TABLE IF NOT EXISTS `exchangeum_users` (
  `user` varchar(15) default NULL,
  `umenabled` varchar(10) default NULL,
  PRIMARY KEY `user` (`user`)
)';

foreach ($sql as $statement){
	$check = $db->query($statement);
	if (DB::IsError($check)){
		die_freepbx( "Can not execute $statement : " . $check->getMessage() .  "\n");
	}
}

?>
