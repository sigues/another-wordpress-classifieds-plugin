<?php // emails are sent in plain text, blank lines in templates are required ?>
<?php echo get_awpcp_option('listingaddedbody') ?>


<?php _e("Listing Title", "AWPCP") ?>: <?php echo $listingtitle ?>

<?php _e("Listing URL", "AWPCP") ?>: <?php echo $adlink ?>

<?php _e("Listing ID", "AWPCP") ?>: <?php echo $ad_id ?>

<?php _e("Listing Edit Email", "AWPCP") ?>: <?php echo $adposteremail ?>

<?php _e("Listing Edit Key", "AWPCP") ?>: <?php echo $key ?>

<?php if (!empty($transaction_id)): ?>
<?php _e("Additional Details", "AWPCP")?>: <?php echo $transaction_id ?>
<?php endif ?>


<?php if (!empty($message)): ?>
<?php _e('Additional Details', 'AWPCP') ?>

<?php echo $message ?>


<?php endif ?>
<?php _e("If you have questions about your listing contact", 'AWPCP') ?>: <?php echo $thisadminemail ?>


<?php _e('Thank you for your business', 'AWPCP') ?>


<?php echo home_url() ?>
