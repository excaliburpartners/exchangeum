<h2><?php echo _("Exchange UM Settings")?></h2>
<form name="edit" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
<input type="button" value="Set Device Mailbox" onclick="if(confirm('Are you sure you want to update the mailbox MWI setting for all devices?')) location.href='config.php?display=exchangeum&action=devicemailbox'" />
<input type="button" value="Initial Setup" onclick="if(confirm('Are you sure you want to set users not configured to \'<?php echo $enabled[$exchangeum['enabled']] ?>\'?')) location.href='config.php?display=exchangeum&action=resetall'" />

<table id="exchangeumoptionstable">		
<tbody>
	<tr><td colspan="2"><h5><?php echo _("Options")?><hr/></h5></td></tr>
	<tr>
		<td width="175"><?php echo _("Extension Length")?><span class="help">?<span style="display: none;">The number of digits to send to Exchange. This should match your Exchange UM Dial Plan.</span></span></td>
		<td><?php echo form_dropdown('digits', $digits, $exchangeum['digits']); ?></td>	
	</tr>
	<tr>
		<td width="175"><?php echo _("Default Status")?><span class="help">?<span style="display: none;">When creating new users select this as the default setting for Exchange UM.</span></span></td>
		<td><?php echo form_dropdown('enabled', $enabled, $exchangeum['enabled']); ?></td>	
	</tr>
</tbody>
</table>
<br />

<input type="hidden" value="exchangeum" name="display"/>
<input type="hidden" name="action" value="edit">
<input type=submit value="<?php echo _("Submit")?>">
</form>