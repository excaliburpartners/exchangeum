<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Don't bother uninstalling feature codes, now module_uninstall does it

sql('DROP TABLE IF EXISTS exchangeum_details, exchangeum_users, exchangeum_orgs, exchangeum_org_trunks');

global $astman;

// Remove all options in effect on extensions
if ($astman) {
        $astman->database_deltree('EXCHUM');
} else {
        fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
}

?>
