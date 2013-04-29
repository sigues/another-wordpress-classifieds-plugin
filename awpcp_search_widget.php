<?php
define('TBLPFX',$wpdb->prefix);
//Search widget for AWPCP
class AWPCP_Search_Widget extends WP_Widget {
	function AWPCP_Search_Widget() {
		parent::WP_Widget(false, $name = 'AWPCP Classifieds Search');
	}

	function ads_sel($by,$field,$search_field)
	{
		$field_rec=mysql_query("SELECT DISTINCT ".$field." FROM ".TBLPFX."awpcp_ads WHERE disabled=0 AND (flagged IS NULL OR flagged = 0) ORDER BY ".$field." ASC");
		if($detail=mysql_fetch_assoc($field_rec)) {
			echo $by.'<br/><select name='.$search_field.'><option></option>';
			do {
				echo'<option';
				if($_POST[$search_field]==$detail[$field]) {
					echo' selected=\'selected\'';
				}
				echo'>'.$detail[$field].'</option>';
			}
			while($detail=mysql_fetch_assoc($field_rec));
			echo'</select><br/>';
		}
	}

	function widget($args, $instance) {
		extract($args);

		$title = $instance['title'].'<br/><span class="widgetstitle">'.$instance['subtitle'].'</span>';

		//echo $before_widget; if(!empty($title)) { $before_title . $title . $after_title; }
		echo $before_widget . $before_title . $title . $after_title;
		echo '<div align="center"><form method=\'post\' action="'.url_searchads().'"><input type="hidden" name="a" value="dosearch"/>';

		$keywordphrase = awpcp_post_param('keywordphrase');

		if ($instance['show_keyword'] == 1) {
			echo __('Search by keyword', "AWPCP").'<br/><input type="text"  name="keywordphrase" value="' . $keywordphrase . '"><br/>';
		}
		if ($instance['show_by'] == 1) {
			$this->ads_sel(__('Find ads by ', "AWPCP"),'ad_contact_name','searchname');
		}
		if ($instance['show_city'] == 1) {
			$this->ads_sel(__('Search by City ', "AWPCP"),'ad_city','searchcity');
		}
		if ($instance['show_state'] == 1) {
			$this->ads_sel(__('Search by State ', "AWPCP"),'ad_state','searchstate');
		}
		if ($instance['show_country'] == 1) {
			$this->ads_sel(__('Search by Country ', "AWPCP"),'ad_country','searchcountry');
		}
		echo '<br/><input class=\'button\' type=\'submit\' value="Search"></form></div>';
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['subtitle'] = strip_tags($new_instance['subtitle']);
		$instance['show_keyword'] = (strip_tags($new_instance['show_keyword']) == 1 ? 1 : 0);
		$instance['show_by'] = (strip_tags($new_instance['show_by']) == 1 ? 1 : 0);
		$instance['show_city'] = (strip_tags($new_instance['show_city']) == 1 ? 1 : 0);
		$instance['show_state'] = (strip_tags($new_instance['show_state']) == 1 ? 1 : 0);
		$instance['show_country'] = (strip_tags($new_instance['show_country']) == 1 ? 1 : 0);
		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'subtitle' => '', 'show_keyword' => 1, 'show_by' => 1, 'show_city' => 1, 'show_state' => 1, 'show_country' => 1 ) );
		$title = strip_tags($instance['title']);
		$subtitle = strip_tags($instance['subtitle']);
		$show_keyword = strip_tags($instance['show_keyword']);
		$show_by = strip_tags($instance['show_by']);
		$show_city = strip_tags($instance['show_city']);
		$show_state = strip_tags($instance['show_state']);
		$show_country = strip_tags($instance['show_country']);
		?>
<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'AWPCP'); ?></label>
<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
	name="<?php echo $this->get_field_name('title'); ?>" type="text"
	value="<?php echo esc_attr($title); ?>" /></p>

<p><label for="<?php echo $this->get_field_id('subtitle'); ?>"><?php _e('Subtitle:', 'AWPCP'); ?></label>
<input class="widefat"
	id="<?php echo $this->get_field_id('subtitle'); ?>"
	name="<?php echo $this->get_field_name('subtitle'); ?>" type="text"
	value="<?php echo esc_attr($subtitle); ?>" /></p>

<p><label for="<?php echo $this->get_field_id('show_keyword'); ?>"><?php _e('Show keyword field?', 'AWPCP'); ?>
<input class="widefat"
	id="<?php echo $this->get_field_id('show_keyword'); ?>"
	name="<?php echo $this->get_field_name('show_keyword'); ?>"
	type="checkbox" value="1"
	<?php echo $show_keyword == 1 ? " checked='checked'" : "" ?> /></label></p>

<p><label for="<?php echo $this->get_field_id('show_by'); ?>"><?php _e('Show Posted By field?', 'AWPCP'); ?>
<input class="widefat"
	id="<?php echo $this->get_field_id('show_by'); ?>"
	name="<?php echo $this->get_field_name('show_by'); ?>" 
	type="checkbox"	value="1"
	<?php echo $show_by == 1 ? " checked='checked'" : "" ?> /></label></p>

<p><label for="<?php echo $this->get_field_id('show_city'); ?>"><?php _e('Show City field?', 'AWPCP'); ?>
<input class="widefat"
	id="<?php echo $this->get_field_id('show_city'); ?>"
	name="<?php echo $this->get_field_name('show_city'); ?>"
	type="checkbox" value="1"
	<?php echo $show_city == 1 ? " checked='checked'" : "" ?> /></label></p>

<p><label for="<?php echo $this->get_field_id('show_state'); ?>"><?php _e('Show State field?', 'AWPCP'); ?>
<input class="widefat"
	id="<?php echo $this->get_field_id('show_state'); ?>"
	name="<?php echo $this->get_field_name('show_state'); ?>"
	type="checkbox" value="1"
	<?php echo $show_state == 1 ? " checked='checked'" : "" ?> /></label></p>

<p><label for="<?php echo $this->get_field_id('show_country'); ?>"><?php _e('Show Country field?', 'AWPCP'); ?>
<input class="widefat"
	id="<?php echo $this->get_field_id('show_country'); ?>"
	name="<?php echo $this->get_field_name('show_country'); ?>"
	type="checkbox" value="1"
	<?php echo $show_country == 1 ? " checked='checked'" : "" ?> /></label></p>
	<?php
	}
}
