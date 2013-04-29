<table class="awpcp-table awpcp-payment-methods-table">
    <?php if ($heading): ?>
    <thead>
        <tr>
            <th colspan="2"><?php echo $heading ?></th>
        </tr>
    </thead>
    <?php endif ?>
    <tbody>
        <?php foreach ($methods as $method): ?>
        <tr class="js-awpcp-payment-method">
            <td>
                <?php $id = "payment-method-{$method->slug}" ?>
                <input id="<?php echo $id ?>" type="radio" name="payment-method" value="<?php echo esc_attr($method->slug) ?>" <?php echo $method->slug == $selected ? 'checked="checked"' : '' ?> />
            </td>
            <td>
                <?php if (property_exists($method, 'icon') && $method->icon): ?>
                <label for="<?php echo $id ?>"><img src="<?php echo $method->icon ?>" alt="<?php esc_attr($method->name) ?>" /></label>
                <?php else: ?>
                <label for="<?php echo $id ?>"><strong><?php echo $method->name ?></strong></label><br/>
                <?php endif ?>
                <?php //echo $method->description ?>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>