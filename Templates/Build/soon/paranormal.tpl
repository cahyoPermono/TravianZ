<h2><?php echo defined('PARANORMAL_SANCTUARY') ? PARANORMAL_SANCTUARY : 'Sanggar Paranormal'; ?></h2>
<table class="new_building" cellpadding="1" cellspacing="1">
	<tbody><tr>
		<td class="desc"><?php echo defined('PARANORMAL_SANCTUARY_DESC') ? PARANORMAL_SANCTUARY_DESC : ''; ?></td>
		<td rowspan="3" class="bimg">
			<a href="#" onClick="return Popup(51,4);">
			<img class="building g51" src="img/x.gif" alt="<?php echo defined('PARANORMAL_SANCTUARY') ? PARANORMAL_SANCTUARY : 'Sanggar Paranormal'; ?>" title="<?php echo defined('PARANORMAL_SANCTUARY') ? PARANORMAL_SANCTUARY : 'Sanggar Paranormal'; ?>" /></a>
		</td>
	</tr>
	<tr>
	<td class="requ"><?php echo PREREQUISITES; ?></td>
	</tr>
	<tr>
		<td>
			<a href="#" onClick="return Popup(15,4);"><?php echo MAINBUILDING; ?></a> <span title="+3"><?php echo LEVEL; ?> 3</span>, <a href="#" onClick="return Popup(23,4);"><?php echo CRANNY; ?></a> <span title="+3"><?php echo LEVEL; ?> 3</span>
		</td>
	</tr></tbody>
</table>
