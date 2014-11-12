<h2><?php echo empty($_GET['edit']) ? 'Add' : 'Edit'; ?> Organization</h2>

<?php
	$dropdown_trunks = exchangeum_dropdown_trunks();

	$newtrunk = '
	<tr>
		<td class="sort"><img src="images/arrow_up_down.png" alt="sort" title="Drag up or down to reposition" /></td>
		<td class="index"></td>
		<td class="type">'.form_dropdown('trunkid[]', $dropdown_trunks, '').'</td>
		<td><img src="images/trash.png" class="deletetrunk" style="cursor:pointer; float:none;" alt="remove" title="Click to delete trunk"></td>
	</tr>';
?>

<script type="text/javascript">
$(document).ready(function() {
	// Functions
	var updateIndex = function(e, ui) {
		$('td.index', ui.item.parent()).each(function (i) {
			$(this).html(i + 1);
		});
	};

	var tableIndex = function(ui) {
		$('td.index', ui).each(function (i) {
			$(this).html(i + 1);
		});
	};
	
	var loadDropdown = function(ui, list) {
		$.each(list, function (key, cat) {
			var group = $('<optgroup>',{label:key});
			
			if(cat.length == 0) {
				$("<option/>",{value:key,text:cat}).appendTo(ui);
			} else {
				$.each(cat,function(subkey,item) {
					$("<option/>",{value:subkey,text:item}).appendTo(group);
				});
				group.appendTo(ui);
			}
		});
		ui.removeAttr('id');
	};

	// Trunks
	$(".addtrunk").on("click",function() {
		$("#trunks").append('<?php echo json_encode($newtrunk); ?>');	
		loadDropdown($("#newtrunk"), <?php echo json_encode($dropdown_trunks); ?>);
		tableIndex($("#trunks"));
	});

	$("#trunks").on("click", ".deletetrunk", function() {
		var td = $(this).parent();
		var tr = td.parent();
		var table = tr.parent();
		tr.remove();
		tableIndex(table);
	});

	$("#trunks tbody").sortable({
		handle: ".sort",
		stop: updateIndex
	});
});
</script>

<form name="exchangeum_orgs_edit" method="post" action="config.php?type=setup&display=exchangeum&exchangeum_form=orgs_edit&edit=<?php echo $_GET['edit'];?>">
<?php 
if(!empty($_GET['edit'])) { 
?>
<input type="button" value="Delete organization" title="Delete this organization" onclick="if(confirm('Are you sure you want to delete this organization?')) location.href='config.php?type=setup&display=exchangeum&exchangeum_form=orgs_edit&delete=<?php echo $_GET['edit'];?>'" />
<?php 
} 
?>

<table>		
<tbody>
	<tr><td colspan="2"><h5><?php echo _("Options")?><hr/></h5></td></tr>
	<tr>
		<td width="175"><?php echo _("Name")?></td>
		<td><?php echo form_input('name', $org['name']); ?></td>	
	</tr>
	
	<tr><td colspan="3"><h5><?php echo _("Trunk Sequence")?><hr/></h5></td></tr>	
	<tr>
		<td colspan="2">	
		<table id="trunks">
		<tbody>
			<?php
			$i=1;
			foreach($org['trunks'] as $trunk) {
			?>
			<tr>
				<td class="sort"><img src="images/arrow_up_down.png" alt="sort" title="Drag up or down to reposition" /></td>
				<td class="index"><?php echo $i;?></td>
				<td><?php echo form_dropdown('trunkid[]', $dropdown_trunks, $trunk['trunkid']); ?></td>
				<td><img src="images/trash.png" class="deletetrunk" style="cursor:pointer; float:none;" alt="remove" title="Click to delete trunk"></td>
			</tr>
			<?php
				$i++;
			}
			?>
		</tbody>
		</table>
		<input type="button" class="addtrunk" value="<?php echo _("Add Trunk")?>"/>
		</td>
	</tr>
</tbody>
</table>
<br />

<input type="hidden" name="action" value="edit">
<input type=submit value="<?php echo _("Submit")?>">
</form>