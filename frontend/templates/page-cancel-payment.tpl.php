<div id="classiwrapper">
	<?php echo awpcp_menu_items() ?>

	<h2><?php echo $texts['title'] ?></h2>

	<?php /*foreach ($header as $part): ?>
		<?php echo $part ?>
	<?php endforeach*/ ?>

	<?php $url = $transaction->get('cancel-redirect') ?>
	<form id="awpcp-cancel-payment-form" method="post" action="<?php echo esc_attr($url) ?>">
		<?php /*<h3><?php echo $texts['subtitle'] ?></h3>*/ ?>
		<p><?php echo $texts['text'] ?></p>
		<p class="form-submit">
			<input class="button" type="submit" value="<?php _e('Continue', 'AWPCP') ?>" id="submit" name="submit" />
			<input type="hidden" value="<?php echo esc_attr($transaction->id) ?>" name="awpcp-txn" />
			<?php foreach ((array) $transaction->get('cancel-form') as $field => $value): ?>
			<input type="hidden" value="<?php echo esc_attr($value) ?>" name="<?php echo esc_attr($field) ?>" />
			<?php endforeach ?>
		</p>
	</form>
</div>