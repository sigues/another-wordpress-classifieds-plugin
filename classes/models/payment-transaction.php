<?php

class AWPCP_Payment_Transaction {

	public static $PAYMENT_STATUS_UNKNOWN = 'Unknown';
	public static $PAYMENT_STATUS_INVALID = 'Invalid';
	public static $PAYMENT_STATUS_FAILED = 'Failed';
	public static $PAYMENT_STATUS_PENDING = 'Pending';
	public static $PAYMENT_STATUS_COMPLETED = 'Completed';
	public static $PAYMENT_STATUS_SUBSCRIPTION_CANCELED = 'Canceled';

	private $attributes = array('__items__' => array());

	public $id;
	public $errors = array();

	public function AWPCP_Payment_Transaction($id, $attributes=array()) {
		$this->id = $id;
		$this->attributes = $attributes;
		$this->errors = awpcp_array_data('__errors__', array(), $attributes);
	}

	public static function find_by_id($id) {
		$attributes = get_option("awpcp-payment-transaction-$id", null);
		if (is_null($attributes)) {
			return null;
		}
		return new AWPCP_Payment_Transaction($id, $attributes);
	}

	public static function find_or_create($id) {
		$transaction = AWPCP_Payment_Transaction::find_by_id($id);
		if (is_null($transaction)) {
			$parts = split(' ', microtime());
			$id = md5(($parts[1]+$parts[0]) . wp_salt());
			$transaction = new AWPCP_Payment_Transaction($id);
		}
		return $transaction;
	}

	public static function find() {

	}

	public function get($name, $default=null) {
		if (isset($this->attributes[$name])) {
			return $this->attributes[$name];
		}
		return $default;
	}

	public function set($name, $value) {
		$this->attributes[$name] = $value;
	}

	/**
	 * @param $increment	number of periods (the 8 in 8 Weeks)
	 * @param $period		D, W, M, Y
	 *
	 * Increment and period are used in a different way in other sections of
	 * AWPCP. Increment would be D, W, M, Y and period the number.
	 */
	public function add_item($id, $name, $increment, $period, $lineas) {
		$item = new stdClass();
		$item->id = $id;
		$item->name = $name;
		$item->increment = $increment;
		$item->period = $period;
		$this->attributes['__items__'][] = $item;
	}

	public function get_item($index) {
		if (isset($this->attributes['__items__'][$index])) {
			return $this->attributes['__items__'][$index];
		}
		return null;
	}

	public function save() {
		$this->attributes['__errors__'] = $this->errors;
		$this->attributes['__updated__'] = current_time('mysql');
      /*aqui se cambio*/ //var_dump($this->attributes);//die();
		if (!isset($this->attributes['__created__'])) {
			$this->attributes['__created__'] = current_time('mysql');
           		add_option("awpcp-payment-transaction-{$this->id}", $this->attributes, '', 'no');
		} else {
           		update_option("awpcp-payment-transaction-{$this->id}", $this->attributes);
		}
	}
}