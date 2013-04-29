<div class="update-nag clearfix">
    <p><?php _e('Hello and welcome to <strong>Another WordPress Classifieds</strong>. This plugin is super easy to use AND highly configurable.', 'AWPCP') ?></p>
    <p><?php _e('Would you like some help getting started?', 'AWPCP') ?></p>

    <div id="quick-start-guide-notice">
        <div style="float:left;width:50%">
            <?php $text = _x('No Thanks', 'Quick Start Guide', 'AWPCP') ?>
            <p><a id="link-no-thanks" class="button" title="<?php echo esc_attr($text) ?>"><?php echo $text ?></a><br/>
                <?php _ex("I'll figure it out on my own.", 'Quick Start Guide', 'AWPCP') ?></p>
        </div>
        <div style="float:left;width:50%">
            <?php $text = _x('Yes Please!', 'Quick Start Guide', 'AWPCP') ?>
            <?php $url = esc_attr('http://awpcp.com/quick-start-guide') ?>
            <p><a id="link-no-thanks" class="button button-primary" href="<?php echo $url ?>" title="<?php echo esc_attr($text) ?>" target="_blank"><?php echo $text ?></a><br/>
                <?php _ex("Help me get my classifieds running quickly.", 'Quick Start Guide', 'AWPCP') ?></p>
        </div>
    </div>
</div>