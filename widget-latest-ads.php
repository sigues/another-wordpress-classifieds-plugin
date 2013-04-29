<?php

### Function: Init AWPCP Latest Classified Headlines Widget
function init_awpcpsbarwidget() {
	if (!function_exists('wp_register_sidebar_widget')) {
		return;
	}

    function awpcp_latest_ads_widget_options() {
        $defaults = array(
            'hlimit' => 10,
            'title' => __('Latest Classifieds', 'AWPCP'),
            'showimages' => 1,
            'showblank' => 1,
            'show-title' => 1,
            'show-excerpt' => 1
        );

        $options = get_option('widget_awpcplatestads');
        if (is_array($options)) {
            $options = array_merge($defaults, $options);
        } else {
            $options = $defaults;
        }

        return $options;
    }

	### Function: AWPCP Latest Classified Headlines Widget
	function widget_awpcplatestads($args) {
		$output = '';
		extract($args);

		$limit = isset($args[0]) ? $args[0] : '';
		$title = isset($args[1]) ? $args[1] : '';

        $options = awpcp_latest_ads_widget_options();

		if(empty($limit)) {
			$limit = htmlspecialchars(stripslashes($options['hlimit']));
		}
		if(empty($title)) {
			$title = htmlspecialchars(stripslashes($options['title']));
		}

		if(ads_exist()) {
			$awpcp_sb_widget_beforecontent = get_awpcp_option('sidebarwidgetbeforecontent');
			$awpcp_sb_widget_aftercontent = get_awpcp_option('sidebarwidgetaftercontent');
			$awpcp_sb_widget_beforetitle = get_awpcp_option('sidebarwidgetbeforetitle');
			$awpcp_sb_widget_aftertitle = get_awpcp_option('sidebarwidgetaftertitle');

			if(isset($awpcp_sb_widget_beforecontent) && !empty($awpcp_sb_widget_beforecontent)) {
				$output .= "$awpcp_sb_widget_beforecontent";
			}
			if(isset($awpcp_sb_widget_beforetitle) && !empty($awpcp_sb_widget_beforetitle)) {
				$output .= "$awpcp_sb_widget_beforetitle";
			}

			$output .= "$title";
			if(isset($awpcp_sb_widget_aftertitle) && !empty($awpcp_sb_widget_aftertitle)) {
				$output .= "$awpcp_sb_widget_aftertitle";
			}

			if (function_exists('awpcp_sidebar_headlines')) {
				$output .= '<ul>'."\n";
				$output .= awpcp_sidebar_headlines($limit, $options['showimages'], $options['showblank'], $options['show-title'], $options['show-excerpt']);
				$output .= '</ul>'."\n";
			}

			if(isset($awpcp_sb_widget_aftercontent) && !empty($awpcp_sb_widget_aftercontent)) {
				$output .= "$awpcp_sb_widget_aftercontent";
			}
		}

		echo $output;
	}

	### Function: AWPCP Latest Classified Headlines Widget Options
	function widget_awpcplatestads_options() {
        $options = awpcp_latest_ads_widget_options();

		if (isset($_POST['awpcplatestads-submit']) && $_POST['awpcplatestads-submit']) {
			$options['hlimit'] = intval($_POST['awpcpwid-limit']);
			$options['title'] = strip_tags($_POST['awpcpwid-title']);
			$options['showimages'] = awpcp_post_param('awpcpwid-showimages', 0);
			$options['showblank'] = awpcp_post_param('awpcpwid-showblank', 0);
            $options['show-title'] = awpcp_post_param('awpcpwid-show-title', 0);
            $options['show-excerpt'] = awpcp_post_param('awpcpwid-show-excerpt', 0);
			update_option('widget_awpcplatestads', $options);
		}

		$output = '<p><label for="awpcpwid-title">'.__('Widget Title', 'AWPCP').':</label>&nbsp;&nbsp;&nbsp;<input type="text" id="awpcpwid-title" size="35" name="awpcpwid-title" value="'.htmlspecialchars(stripslashes($options['title'])).'" />';
		$output.= '<p><label for="awpcpwid-limit">'.__('Number of Items to Show', 'AWPCP').':</label>&nbsp;&nbsp;&nbsp;<input type="text" size="5" id="awpcpwid-limit" name="awpcpwid-limit" value="'.htmlspecialchars(stripslashes($options['hlimit'])).'" />';
		$output.= '<p><label for="awpcpwid-showimages">'.__('Show Thumbnails in Widget?', 'AWPCP').':</label>&nbsp;&nbsp;&nbsp;<input type="checkbox" id="awpcpwid-showimages" name="awpcpwid-showimages" value="1" '. ($options['showimages'] == 1 ? 'checked=\"true\"' : '') .' />';
		$output.= '<p><label for="awpcpwid-showblank">'.__('Show "No Image" PNG when ad has no picture (improves layout)?', 'AWPCP').':</label>&nbsp;&nbsp;&nbsp;<input type="checkbox" id="awpcpwid-showblank" name="awpcpwid-showblank" value="1" '. ($options['showblank'] == 1 ? 'checked=\"true\"' : '') .' />';
        $output.= '<p><label for="awpcpwid-show-title">'.__('Show Ad title', 'AWPCP').':</label>&nbsp;&nbsp;&nbsp;<input type="checkbox" id="awpcpwid-show-title" name="awpcpwid-show-title" value="1" '. ($options['show-title'] == 1 ? 'checked=\"true\"' : '') .' />';
        $output.= '<p><label for="awpcpwid-show-excerpt">'.__('Show Ad excerpt', 'AWPCP').':</label>&nbsp;&nbsp;&nbsp;<input type="checkbox" id="awpcpwid-show-excerpt" name="awpcpwid-show-excerpt" value="1" '. ($options['show-excerpt'] == 1 ? 'checked=\"true\"' : '') .' />';
		//$output .= '<p><label for="awpcpwid-beforewidget">'.__('Before Widget HTML', 'AWPCP').':</label>&nbsp;&nbsp;&nbsp;<input type="text" id="awpcpwid-beforewidget" size="35" name="awpcpwid-beforewidget" value="'.htmlspecialchars(stripslashes($options['beforewidget'])).'" />';
		//$output .= '<p><label for="awpcpwid-afterwidget">'.__('After Widget HTML<br>Exclude all quotes<br>(<del>class="XYZ"</del> => class=XYZ)', 'AWPCP').':</label>&nbsp;&nbsp;&nbsp;<input type="text" id="awpcpwid-afterwidget" size="35" name="awpcpwid-afterwidget" value="'.htmlspecialchars(stripslashes($options['afterwidget'])).'" />';
		//$output .= '<p><label for="awpcpwid-beforetitle">'.__('Before title HTML', 'AWPCP').':</label>&nbsp;&nbsp;&nbsp;<input type="text" id="awpcpwid-beforetitle" size="35" name="awpcpwid-beforetitle" value="'.htmlspecialchars(stripslashes($options['beforetitle'])).'" />';
		//$output .= '<p><label for="awpcpwid-aftertitle">'.__('After title HTML', 'AWPCP').':</label>&nbsp;&nbsp;&nbsp;<input type="text" id="awpcpwid-aftertitle" size="35" name="awpcpwid-aftertitle" value="'.htmlspecialchars(stripslashes($options['aftertitle'])).'" />';
		$output.= '<input type="hidden" id="awpcplatestads-submit" name="awpcplatestads-submit" value="1" />'."\n";

		echo $output;
	}

	// Register Widgets
    $name = __('AWPCP Latest Ads', 'AWPCP');
    $options = array('width' => 350, 'height' => 120, 'id_base' => 'awpcp-latest-ads');
	wp_register_sidebar_widget('awpcp-latest-ads', $name, 'widget_awpcplatestads');
	wp_register_widget_control('awpcp-latest-ads', $name, 'widget_awpcplatestads_options', $options);
}


/**
 * XXX: is it necessary to consider active region when showing
 *      latest Ads widget?
 */
function awpcp_sidebar_headlines($limit, $showimages, $showblank, $show_title, $show_excerpt) {
    $output = '';
    global $wpdb,$awpcp_imagesurl;
    $tbl_ads = $wpdb->prefix . "awpcp_ads";

    $awpcppage=get_currentpagename();
    $awpcppagename = sanitize_title($awpcppage, $post_ID='');
    $permastruc=get_option('permalink_structure');
    $quers=setup_url_structure($awpcppagename);
    $displayadthumbwidth = intval(trim(get_awpcp_option('displayadthumbwidth'))) . 'px';

    if(!isset($limit) || empty($limit)){
        $limit = 10;
    }

    $query = "SELECT ad_id,ad_title,ad_details FROM ". AWPCP_TABLE_ADS ." ";
    $query.= "WHERE ad_title <> '' AND disabled = 0 ";
    // $query.= "AND (flagged IS NULL OR flagged = 0) ";
    $query.= "ORDER BY ad_postdate DESC, ad_id DESC LIMIT ". $limit . "";
    $res = awpcp_query($query, __LINE__);

    while ($rsrow=mysql_fetch_row($res)) {
        $ad_id=$rsrow[0];
        $modtitle= awpcp_esc_attr($rsrow[1]);
        $hasNoImage = true;
        $url_showad=url_showad($ad_id);

        $ad_title="<a href=\"$url_showad\">".stripslashes($rsrow[1])."</a>";
        if (!$showimages) {
            //Old style, list only:
            $output .= "<li>$ad_title</li>";
        } else {
            //New style, with images and layout control:
            $awpcp_image_display="<a class=\"self\" href=\"$url_showad\">";
            if (get_awpcp_option('imagesallowdisallow')) {
                $totalimagesuploaded=get_total_imagesuploaded($ad_id);
                if ($totalimagesuploaded >=1) {
                    $image = awpcp_get_ad_primary_image($ad_id);
                    if (!is_null($image)) {
                        $awpcp_image_name_srccode="<img src=\"" . awpcp_get_image_url($image) . "\" border=\"0\" width=\"$displayadthumbwidth\" alt=\"$modtitle\"/>";
                        $hasNoImage = false;
                    } else {
                        $awpcp_image_name_srccode="<img src=\"$awpcp_imagesurl/adhasnoimage.gif\" width=\"$displayadthumbwidth\" border=\"0\" alt=\"$modtitle\"/>";
                    }
                } else {
                    $awpcp_image_name_srccode="<img src=\"$awpcp_imagesurl/adhasnoimage.gif\" width=\"$displayadthumbwidth\" border=\"0\" alt=\"$modtitle\"/>";
                }
            } else {
                $awpcp_image_name_srccode="<img src=\"$awpcp_imagesurl/adhasnoimage.gif\" width=\"$displayadthumbwidth\" border=\"0\" alt=\"$modtitle\"/>";
            }

            $ad_teaser = stripslashes(substr($rsrow[2], 0, 50)) . "...";
            $read_more = "<a href=\"$url_showad\">[" . __("Read more", "AWPCP") . "]</a>";
            $awpcp_image_display.="$awpcp_image_name_srccode</a>";

            $awpcp_image_display = (!$showblank && $hasNoImage) ? '' : $awpcp_image_display;
            $html_title = $show_title ? "<h3>$ad_title</h3>" : '';
            $html_excerpt = $show_excerpt ? "<p>$ad_teaser<br/>$read_more</p>" : '';

            $output .= "<li><div class='awpcplatestbox'><div class='awpcplatestthumb'>$awpcp_image_display</div>$html_title $html_excerpt<div class='awpcplatestspacer'></div></div></li>";
        }
    }
    return $output;
}
