<?php // emails are sent in plain text, all blank lines in templates are required ?>
<?php _e('Dear Administrator', 'AWPCP') ?>,

<?php _e("There was a problem encountered during a customer's attempt to submit payment.", 'AWPCP') ?>


<?php _e('Additional Details', 'AWPCP') ?>


<?php echo sprintf("\t%s", $message); ?>


<?php if ($user): ?>
<?php _e('User Name') ?>: <?php echo $user->display_name ?>

<?php _e('User Login') ?>: <?php echo $user->user_login ?>

<?php _e('User Email') ?>: <?php echo $user->user_email ?>

<?php endif ?>

<?php if ($transaction): ?>
<?php _e('Payment Term Type', 'AWPCP') ?>: <?php echo $transaction->get('payment-term-type') ?>

<?php _e('Payment Term ID', 'AWPCP') ?>: <?php echo $transaction->get('payment-term-id') ?>

<?php _e('Payment transaction ID', 'AWPCP') ?>: <?php echo $transaction->get('txn-id') ?>
<?php endif ?>