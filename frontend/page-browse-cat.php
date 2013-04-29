<?php

class AWPCP_BrowseCatPage {

    public function __construct() {}

    public function dispatch() {
        if (isset($_REQUEST['category_id']) && !empty($_REQUEST['category_id'])) {
            $adcategory = intval($_REQUEST['category_id']);
        } else {
            $adcategory = intval(get_query_var('cid'));
        }

        $action = '';
        if (isset($_REQUEST['a']) && !empty($_REQUEST['a'])) {
            $action=$_REQUEST['a'];
        }
        if (!isset($action) || empty($action)){
            $action="browsecat";
        }

        if ($action == 'browsecat') {
            if ($adcategory == -1 || empty($adcategory)) {
                $where="";
            } else {
                $where="(ad_category_id='".$adcategory."' OR ad_category_parent_id='".$adcategory."') AND disabled =0";
            }
        } elseif (!isset($action)) {
            if (isset($adcategory) ) {
                if ($adcategory == -1 || empty($adcategory)) {
                    $where="";
                } else {
                    $where="(ad_category_id='".$adcategory."' OR ad_category_parent_id='".$adcategory."') AND disabled =0";
                }
            } else {
                $where="";
            }
        } else {
            $where="";
        }

        $output = '';
        if ($adcategory == -1) {
            $text = __("No specific category was selected for browsing so you are viewing listings from all categories","AWPCP");
            $output = sprintf("<p><b>%s</b></p>", $text);
        }

        $grouporderby = get_group_orderby();

        $output .= awpcp_display_ads($where,$byl='',$hidepager='',$grouporderby,$adorcat='cat');
        return $output;
    }

    public function shortcode($attrs) {
        global $wpdb;

        extract(shortcode_atts(array('id' => 0, 'children' => true), $attrs));

        $category = $id > 0 ? AWPCP_Category::find_by_id($id) : null;
        $children = awpcp_parse_bool($children);

        if (is_null($category))
            return __('Category ID must be valid for Ads to display.', 'category shortcode', 'AWPCP');

        if ($children) {
            // show children categories and disable possible sidebar (Region Control sidelist)
            $before = awpcp_display_the_classifieds_category('', $category->id, false);
        } else {
            $before = '';
        }

        if ($children)
            $where = '( ad_category_id=%1$d OR ad_category_parent_id = %1$d ) AND disabled = 0';
        else
            $where = 'ad_category_id=%1$d AND disabled = 0';
        $where = $wpdb->prepare($where, $category->id);
        $order = get_group_orderby();

        // required so awpcp_display_ads shows the name of the current category
        $_REQUEST['category_id'] = $category->id;

        return awpcp_display_ads($where, '', '', $order, 'cat', $before);
    }
}
