<?php // emails are sent in plain text, blank lines in templates and spaces at 
      // the end of the lineare required ?>
<?php echo get_awpcp_option('contactformbodymessage') ?>


<?php _e("Contacting About", "AWPCP") ?>:

    <?php echo $theadtitle ?> 
    <?php echo $url_showad ?>


<?php _ex("Message", 'reply email', "AWPCP") ?>:

    <?php echo $contactmessage ?>


<?php _e("Reply To", "AWPCP") ?> 

    <?php _e("Name", "AWPCP") ?>: <?php echo $sendersname ?> 
    <?php _e("Email", "AWPCP") ?>: <?php echo $sendersemail ?>


<?php echo $nameofsite ?> 
<?php echo home_url() ?>