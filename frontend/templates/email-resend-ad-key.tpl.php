<?php // emails are sent in plain text, all blank lines in templates are required ?>
<?php echo $introduction ?>:

<?php _e("Total ads found sharing your email address", "AWPCP") ?>: <?php echo count($keys) ?>


<?php foreach ($keys as $key => $title): ?>
<?php echo sprintf("- %s:\t%s\n", $title, $key) ?>

<?php endforeach ?>

<?php echo $nameofsite ?>

<?php echo $home_url ?>