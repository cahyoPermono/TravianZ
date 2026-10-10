<table class="results attacker" cellpadding="1" cellspacing="1">
	<thead>
		<tr>
			<td class="role">Attacker</td>
			<td><img src="img/x.gif" class="unit u91" title="<?php echo defined('U91') ? U91 : 'Pendekar Keris'; ?>" alt="<?php echo defined('U91') ? U91 : 'Pendekar Keris'; ?>" /></td>
			<td><img src="img/x.gif" class="unit u92" title="<?php echo defined('U92') ? U92 : 'Prajurit Tombak'; ?>" alt="<?php echo defined('U92') ? U92 : 'Prajurit Tombak'; ?>" /></td>
			<td><img src="img/x.gif" class="unit u93" title="<?php echo defined('U93') ? U93 : 'Pemanah Busur Gendewa'; ?>" alt="<?php echo defined('U93') ? U93 : 'Pemanah Busur Gendewa'; ?>" /></td>
			<td><img src="img/x.gif" class="unit u94" title="<?php echo defined('U94') ? U94 : 'Telik Sandi'; ?>" alt="<?php echo defined('U94') ? U94 : 'Telik Sandi'; ?>" /></td>
			<td><img src="img/x.gif" class="unit u95" title="<?php echo defined('U95') ? U95 : 'Kavaleri Berkuda'; ?>" alt="<?php echo defined('U95') ? U95 : 'Kavaleri Berkuda'; ?>" /></td>
			<td><img src="img/x.gif" class="unit u96" title="<?php echo defined('U96') ? U96 : 'Gajah Perang Bhayangkara'; ?>" alt="<?php echo defined('U96') ? U96 : 'Gajah Perang Bhayangkara'; ?>" /></td>
			<td><img src="img/x.gif" class="unit u97" title="<?php echo defined('U97') ? U97 : 'Cetbang Pemecah Benteng'; ?>" alt="<?php echo defined('U97') ? U97 : 'Cetbang Pemecah Benteng'; ?>" /></td>
			<td><img src="img/x.gif" class="unit u98" title="<?php echo defined('U98') ? U98 : 'Meriam Kalantaka'; ?>" alt="<?php echo defined('U98') ? U98 : 'Meriam Kalantaka'; ?>" /></td>
			<td><img src="img/x.gif" class="unit u99" title="<?php echo defined('U99') ? U99 : 'Senapati Palapa'; ?>" alt="<?php echo defined('U99') ? U99 : 'Senapati Palapa'; ?>" /></td>
			<td><img src="img/x.gif" class="unit u100" title="<?php echo defined('U100') ? U100 : 'Pemukim Bahari'; ?>" alt="<?php echo defined('U100') ? U100 : 'Pemukim Bahari'; ?>" /></td>
		</tr>
	</thead>
	<tbody>
		<tr>
			<th>Troops</th>
			<td <?php if (!$form->getValue('a1_1')) { echo "class=\"none\">0"; } else { echo ">" . $form->getValue('a1_1'); } ?></td>
			<td <?php if (!$form->getValue('a1_2')) { echo "class=\"none\">0"; } else { echo ">" . $form->getValue('a1_2'); } ?></td>
			<td <?php if (!$form->getValue('a1_3')) { echo "class=\"none\">0"; } else { echo ">" . $form->getValue('a1_3'); } ?></td>
			<td <?php if (!$form->getValue('a1_4')) { echo "class=\"none\">0"; } else { echo ">" . $form->getValue('a1_4'); } ?></td>
			<td <?php if (!$form->getValue('a1_5')) { echo "class=\"none\">0"; } else { echo ">" . $form->getValue('a1_5'); } ?></td>
			<td <?php if (!$form->getValue('a1_6')) { echo "class=\"none\">0"; } else { echo ">" . $form->getValue('a1_6'); } ?></td>
			<td <?php if (!$form->getValue('a1_7')) { echo "class=\"none\">0"; } else { echo ">" . $form->getValue('a1_7'); } ?></td>
			<td <?php if (!$form->getValue('a1_8')) { echo "class=\"none\">0"; } else { echo ">" . $form->getValue('a1_8'); } ?></td>
			<td <?php if (!$form->getValue('a1_9')) { echo "class=\"none\">0"; } else { echo ">" . $form->getValue('a1_9'); } ?></td>
			<td <?php if (!$form->getValue('a1_10')) { echo "class=\"none\">0"; } else { echo ">" . $form->getValue('a1_10'); } ?></td>
		</tr>
		<tr>
			<th>Casualties</th>
			<td <?php if (!$troops = $form->getValue('a1_1')) { echo "class=\"none\">0"; } else { echo ">" . round($troops * $_POST['result'][1]); } ?></td>
			<td <?php if (!$troops = $form->getValue('a1_2')) { echo "class=\"none\">0"; } else { echo ">" . round($troops * $_POST['result'][1]); } ?></td>
			<td <?php if (!$troops = $form->getValue('a1_3')) { echo "class=\"none\">0"; } else { echo ">" . round($troops * $_POST['result'][1]); } ?></td>
			<td <?php if (!$troops = $form->getValue('a1_4')) { echo "class=\"none\">0"; } else { echo ">" . round($troops * $_POST['result'][1]); } ?></td>
			<td <?php if (!$troops = $form->getValue('a1_5')) { echo "class=\"none\">0"; } else { echo ">" . round($troops * $_POST['result'][1]); } ?></td>
			<td <?php if (!$troops = $form->getValue('a1_6')) { echo "class=\"none\">0"; } else { echo ">" . round($troops * $_POST['result'][1]); } ?></td>
			<td <?php if (!$troops = $form->getValue('a1_7')) { echo "class=\"none\">0"; } else { echo ">" . round($troops * $_POST['result'][1]); } ?></td>
			<td <?php if (!$troops = $form->getValue('a1_8')) { echo "class=\"none\">0"; } else { echo ">" . round($troops * $_POST['result'][1]); } ?></td>
			<td <?php if (!$troops = $form->getValue('a1_9')) { echo "class=\"none\">0"; } else { echo ">" . round($troops * $_POST['result'][1]); } ?></td>
			<td <?php if (!$troops = $form->getValue('a1_10')) { echo "class=\"none\">0"; } else { echo ">" . round($troops * $_POST['result'][1]); } ?></td>
		</tr>
	</tbody>
</table>
