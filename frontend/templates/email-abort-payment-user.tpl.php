<?php // emails are sent in plain text, all blank lines in templates are required ?>
<?php echo get_awpcp_option('paymentabortedbodymessage') ?>


<?php _e('Additional Details', 'AWPCP') ?>


<?php echo sprintf("\t%s", $message); ?>


<?php if ($transaction): ?>
<?php _e('Payment transaction ID', 'AWPCP') ?>: <?php echo $transaction->get('txn-id') ?>


<?php endif ?>
<?php echo $nameofsite?>

<?php echo home_url() ?>
