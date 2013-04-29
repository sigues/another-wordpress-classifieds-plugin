<div id="classiwrapper">
	<?php if (!is_admin()): ?>
	<?php echo awpcp_menu_items() ?>
	<?php endif ?>

	<?php if ($edit): ?>
	<h2><?php _e("Your changes have been saved", 'AWPCP') ?></h2>
	<?php else: ?>
	<h2><?php _e("Your Ad has been submitted", "AWPCP") ?></h2>
	<?php endif ?>

	<?php foreach ((array) $header as $part): ?>
		<?php echo $part ?>
	<?php endforeach ?>

	<?php if (!empty($message)): ?>
	<p class="ad_status_msg"><?php echo $message ?></p>
	<?php endif ?>

	<?php echo showad($ad_id, true, true, false) ?>
</div>