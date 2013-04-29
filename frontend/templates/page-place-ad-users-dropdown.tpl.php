<p class="awpcp-form-spacer">

<?php _e('User', 'AWPCP') ?><br/>

<select id="place-ad-user-id" name="user_id">
    <option value="0"><?php _e('Select a User owner for this Ad', 'AWPCP') ?></option>

    <?php $selected = $user_id ?>
    <?php foreach ($users as $k => $user): ?>
    <option value="<?php echo esc_attr($user->ID) ?>" data-payment-terms="<?php echo esc_attr($user->payment_terms) ?>" <?php echo $selected == $user->ID ? 'selected="selected"' : '' ?>>
        <?php echo $user->display_name ?>
    </option>
    <?php endforeach ?>
</select>

</p>


<?php if ($payment_term !== false): ?>
<p class="awpcp-form-spacer">

<?php _e('User Payment Term', 'AWPCP') ?><br/>

<select id="place-ad-user-payment-terms" name="user_payment_term">

    <option id="payment-term-default" data-categories="<?php echo esc_attr(json_encode(array())) ?>" value="">
        <?php _e('Select a Payment Term for this Ad', 'AWPCP') ?>
    </option>

    <?php $selected = $payment_term ?>
    <?php foreach ($payment_terms as $term): ?>

        <?php $id = "{$term->type}-{$term->id}" ?>
        <?php $element_id = "payment-term-$id" ?>

    <option id="<?php echo esc_attr($element_id) ?>" value="<?php echo esc_attr($id) ?>" data-categories="<?php echo esc_attr(json_encode($term->categories)) ?>" data-characters-allowed=<?php echo esc_attr($term->characters_allowed) ?><?php echo $selected == $id ? 'selected="selected"' : '' ?>>
        <?php echo ucwords(str_replace('-', ' ', $term->type)) ?>: <?php echo $term->name ?>
    </option>

    <?php endforeach ?>

</select>

</p>
<?php endif ?>


<?php echo awpcp_inline_javascript_placeholder('users-dropdown', '<script type="text/javascript">//<![CDATA[
AWPCP_Users = ' . $json . ';
//]]></script>'); ?>
