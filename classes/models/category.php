<?php

class AWPCP_Category {

    public function __construct($id, $name, $icon='', $order=0, $parent=0) {
        $this->id = $id;
        $this->parent = $parent;
        $this->name = $name;
        $this->icon = $icon;
        $this->order = $order;
    }

    public static function create_from_object($object) {
        return new AWPCP_Category(
            $object->category_id,
            $object->category_name,
            $object->category_icon,
            awpcp_get_property($object, 'category_order', ''),
            $object->category_order
        );
    }

    public static function find_by_id($category_id) {
        global $wpdb;

        $sql = 'SELECT * FROM ' . AWPCP_TABLE_CATEGORIES . ' ';
        $sql.= 'WHERE category_id = %d';

        $category = $wpdb->get_row($wpdb->prepare($sql, $category_id));

        return self::create_from_object($category);
    }

    private function _get_children_id($parents=array()) {
        global $wpdb;

        if (!is_array($parents)) {
            $parents = array($parents);
        } else if (empty($parents)) {
            return array();
        }

        $sql = 'SELECT category_id FROM ' . AWPCP_TABLE_CATEGORIES . ' ';
        $sql.= 'WHERE category_parent_id IN (' . join(',', $parents) . ')';

        $children = $wpdb->get_col($sql);
        return array_merge($children, $this->_get_children_id($children));
    }

    public function get_children_id() {
        return $this->_get_children_id($this->id);
    }
}
