<?php

class AWPCP_Meta {

    public $ad = null;
    public $properties = array();
    public $category_id = null;

    private static $instance = null;

    private $doing_opengraph = false;

    private function __construct() {
        add_action('template_redirect', array($this, 'init'));
    }

    public static function instance() {
        if (is_null(self::$instance))
            self::$instance = new AWPCP_Meta();
        return self::$instance;
    }

    public function init() {
        $this->category_id = awpcp_request_param('category_id');
        $this->category_id = empty($this->category_id) ? get_query_var('cid') : $this->category_id;

        $ad_id = awpcp_request_param('adid');
        $ad_id = empty($ad_id) ? awpcp_request_param('id') : $ad_id;
        $ad_id = empty($ad_id) ? get_query_var('id') : $ad_id;

        // Generate OpenGraph information if we are viewing an Ad
        if (intval($ad_id) > 0) {
            $this->ad = AWPCP_Ad::find_by_id($ad_id);
            $this->properties = awpcp_get_ad_share_info($ad_id);

            if (!is_null($this->ad) && !is_null($this->properties)) {
                add_action('wp_head', array($this, 'opengraph'));
                $doing_opengraph = true;
            }            
        }


        add_action('wp_title', array($this, 'title'), 10, 3);

        // YOAST WordPress SEO Integration
        if (defined('WPSEO_VERSION'))
            $this->wordpress_seo();
    }

    private function seplocation($title, $sep) {
        $name = get_bloginfo('name');
        $regex = false;
        $seplocation = false;

        $left = '/^' . preg_quote($name, '/') . '\s*' . preg_quote(trim($sep), '/') . '\s*/';
        $right = '/' . '\s*' . preg_quote(trim($sep), '/') . '\s*' . preg_quote($name, '/') . '/';

        $seplocation = '';
        if (preg_match($left, $title, $matches)) {
            $seplocation = 'left';
            $regex = $left;
        } else if (preg_match($right, $title, $matches)) {
            $seplocation = 'right';
            $regex = $right;
        }

        if ($regex) {
            $title = preg_replace($regex, '', $title);
            $name = $matches[0];    
        } else {
            $name = '';
        }

        return array($title, $name, $seplocation);
    }

    public function title($title, $separator='-', $seplocation='left') {
        wp_reset_query();

        $show_ad_page = awpcp_get_page_id_by_ref('show-ads-page-name');
        $browse_cats_page = awpcp_get_page_id_by_ref('browse-categories-page-name');

        // only change title in the Show Ad and Browse Categories pages
        if (!is_page($show_ad_page) && !is_page($browse_cats_page)) return $title;

        if (is_page($show_ad_page) && is_null($this->ad)) return $title;

        if (is_page($browse_cats_page) && empty($this->category_id)) return $title;

        // We want to strip separators characters from each side of 
        // the title. WordPress uses wptexturize to replace some characters 
        // with HTML entities, we need to do the same if in case the separator
        // is one of those characters.
        $regex = '(\s(?:' . preg_quote($separator, '/') . '|' . preg_quote(trim(wptexturize(" $separator ")), '/') . ')\s*)';
        if (preg_match('/^' . $regex . '/', $title, $matches)) {
            $title = preg_replace('/^' . $regex . '/', '', $title);
            $appendix = ($matches[0]);
        } else if (preg_match('/' . $regex . '$/', $title, $matches)) {
            $title = preg_replace('/' . $regex . '$/', '', $title);
            $appendix = ($matches[0]);
        } else {
            $appendix = '';
        }
        // $title = trim($title, " $separator" . trim(wptexturize(" $separator ")));

        // if $seplocation is empty we are probably being called from one of
        // the SEO plugin's integration functions. We need to strip the
        // blog's name from the title and add it again at the end of the proceess
        if (empty($seplocation)) {
            list($title, $name, $seplocation) = $this->seplocation($title, $separator);
        } else {
            $name = '';
        }

        // overwrite default separatir using AWPCP setting value
        $sep = get_awpcp_option('awpcptitleseparator');
        $sep = empty($sep) ? $separator : $sep;


        $parts = array();

        if (!empty($this->category_id)) {
            $parts[] = get_adcatname($this->category_id);

        } else if (!is_null($this->ad)) {
            if (get_awpcp_option('showcategoryinpagetitle') ) {
                $parts[] = get_adcatname(get_adcategory($this->ad->ad_id));
            }

            if (get_awpcp_option('showcountryinpagetitle')) {
                $parts[] = get_adcountryvalue($this->ad->ad_id);
            }

            if (get_awpcp_option('showstateinpagetitle')) {
                $parts[] = get_adstatevalue($this->ad->ad_id);
            }

            if (get_awpcp_option('showcityinpagetitle')) {
                $parts[] = get_adcityvalue($this->ad->ad_id);
            }

            if (get_awpcp_option('showcountyvillageinpagetitle')) {
                $parts[] = get_adcountyvillagevalue($this->ad->ad_id);
            }
            
            $parts[] = get_adtitle($this->ad->ad_id);
        }

        $parts = array_filter($parts);

        if (empty($parts)) return $title;

        $title = trim($title, " $sep");
        if ($seplocation == 'right') {
            $parts = array_reverse($parts);
            return sprintf("%s %s %s%s%s", $title, $sep, join(" $sep ", $parts), $name, $appendix);
        } else {
            return sprintf("%s%s%s %s %s", $appendix, $name, $title, $sep, join(" $sep ", $parts));
        }
    }


    // The function to add the page meta and Facebook meta to the header of the index page
    // https://www.facebook.com/sharer/sharer.php?u=http%3A%2F%2F108.166.84.26%2F%25253Fpage_id%25253D5%252526id%25253D3&t=Ad+in+Rackspace+1.8.9.4+(2)
    public function opengraph() {
        $charset = get_bloginfo('charset');

        // TODO: handle integration with other plugins
        echo '<meta name="title" content="' . $this->properties['title'] . '" />' . PHP_EOL;
        echo '<meta name="description" content="' . htmlspecialchars($this->properties['description'], ENT_QUOTES, $charset) . '" />' . PHP_EOL;

        echo '<meta property="og:type" content="article" />' . PHP_EOL;
        echo '<meta property="og:url" content="' . $this->properties['url'] . '" />' . PHP_EOL;
        echo '<meta property="og:title" content="' . $this->properties['title'] . '" />' . PHP_EOL;
        echo '<meta property="og:description" content="' . htmlspecialchars($this->properties['description'], ENT_QUOTES, $charset) . '" />' . PHP_EOL;

        foreach ($this->properties['images'] as $k => $image) {
            echo '<meta property="og:image" content="' . $image . '" />' . PHP_EOL;
            echo '<link rel="image_src" href="' . $image . '" />' . PHP_EOL;
        }

        if (empty($this->properties['images'])) {
            echo '<meta property="og:image" content="' . AWPCP_URL . 'images/adhasnoimage.gif" />' . PHP_EOL;
        }
    }

    /**
     * Integration with YOAST WordPress SEO
     */
    public function wordpress_seo() {
        global $wp_filter;

        /* Overwrite title */

        add_filter('wpseo_title', array($this, 'wordpress_seo_title'));
        remove_filter('wp_title', array($this, 'title'), 10, 3);


        /* Disable OpenGraph meta tags in Show Ad page */

        if (!$this->doing_opengraph) return;

        if (!isset($wp_filter['wpseo_head'])) return;

        if (!class_exists('WPSEO_OpenGraph')) return;

        $id = false;
        foreach ($wp_filter['wpseo_head'] as $priority => $functions) {
            foreach ($functions as  $idx => $item) {
                if (is_array($item['function']) && $item['function'][0] instanceof WPSEO_OpenGraph) {
                    $id = $idx;
                    break;
                }
            }

            if ($id) break;
        }

        if ($id) {
            unset($wp_filter['wpseo_head'][$priority][$id]);
        }
    }

    public function wordpress_seo_title($title) {
        global $sep;
        return $this->title($title, $sep, '');
    }
}
