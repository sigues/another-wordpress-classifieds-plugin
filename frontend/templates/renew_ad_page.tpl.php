<?php if ($step == 'renew-by-subscription'): 

	$message = __("The Ad has been successfully renewed. New expiration date is %s", 'AWPCP');
	echo sprintf($message, $subscription->get_end_date()) ?>

<?php elseif ($step == 'renew-subscription'): ?>

	<?php _e("This Ad was placed under a Subscription that has expired. Use the following link to renew your Subscription and then try to renew your Ad again.", 'AWPCP') ?></br>
	<a href="<?php echo $url ?>" title="Renew Subscription">Renew Subscription</a>

<?php elseif ($step == 'renew-subscription-disabled'): ?>

	<?php _e("This Ad was placed under a Subscription that has expired. Unfortunately Subscriptions are no longer available. Please contact the admin about this issue.", 'AWPCP'); ?>

<?php elseif ($step == 'renew-free'):

	$message = __("The Ad has been successfully renewed. New expiration date is %s", 'AWPCP');
	echo sprintf($message, $ad->get_end_date()) ?>

<?php elseif ($step == 'choose-payment-method'): ?>

	<?php _e("You're about to renew your Ad. Please select a payment method below and click Continue.", 'AWPCP'); ?>

	<form method="post">
		<fieldset>
			<legend><?php _e('Payment Method', 'AWPCP') ?></legend>
			<?php foreach ($payment_methods as $value => $label): ?>
			<input id="payment-<?php echo $value ?>" type="radio" name="payment-method" value="<?php echo $value ?>" />
			<label for="payment-<?php echo $value ?>"><?php echo $label ?></label><br />
			<?php endforeach ?>
		</fieldset>

		<input type="hidden" name="step" value="renew-ad" />
		<input class="button" type="submit" value="Continue" />
	</form>

<?php elseif ($step == 'renew-ad'): ?>

	<?php _e("You're close. Click Continue in order to repeat the step 3 of the Place Ad process to renew your Ad.", 'AWPCP'); ?>

	<form action="<?php echo $url ?>" method="post">
		<input type="hidden" value="<?php echo $payment_method ?>" name="adpaymethod">
		<input type="hidden" value="<?php echo $ad->ad_id ?>" name="adid">
		<input type="hidden" value="<?php echo $ad->ad_key ?>" name="adkey">
		<input type="hidden" value="<?php echo $ad->adterm_id ?>" name="adtermid">
		<input type="hidden" value="loadpaymentpage" name="a">
		<input class="button" type="submit" value="Continue" />
	</form>

<?php endif ?>