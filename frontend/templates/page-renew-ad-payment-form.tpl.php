	<?php _e("You're about to renew your Ad. Please select a payment method below and click Continue.", 'AWPCP'); ?>

	<form method="post">
		<?php $selected = awpcp_post_param('payment-method', false) ?>
		<?php $selected = empty($selected) ? array_shift(awpcp_get_properties($payment_methods, 'slug')) : $selected ?>
		<?php echo awpcp_payments_methods_form($selected, false) ?>

		<p class="form-submit">
			<input class="button" type="submit" value="<?php _e('Continue', 'AWPCP') ?>" id="submit" name="submit">
			<input type="hidden" value="<?php echo esc_attr($transaction->id) ?>" name="awpcp-txn">
			<input type="hidden" value="checkout" name="step">
		</p>
	</from>