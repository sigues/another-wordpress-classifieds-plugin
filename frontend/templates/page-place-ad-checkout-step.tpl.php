<div id="classiwrapper">
	<?php if (!is_admin()): ?>
	<?php echo awpcp_menu_items() ?>
	<?php endif ?>

	<h2><?php _e('Complete Payment', 'AWPCP') ?></h2>

	<?php foreach ($header as $part): ?>
		<?php echo $part ?>
	<?php endforeach ?>

	<?php if ($text): ?>
	<p><?php echo sprintf($text, $transaction->get('amount')) ?></p>
	<?php endif ?>

	<?php echo $checkout_form ?>
</div>