<?php

namespace facebook\models;

use facebook\extensions\adapter\data\source\http\Facebook;
use lithium\data\Connections;

class Users extends \lithium\data\Model {

	public $_meta = array();

	public function __construct(array $config = array()) {
		$this->_meta['connection'] = GooglePlaces::$configName;
	}

	/*public static function config(array $config = array(), $a = array()) {
		debug($config);
		debug($a);

		//Connections::add(GooglePlaces::$configName, array('in' => 'out'));
		$adapter = Connections::get(GooglePlaces::$configName);
		debug($adapter->connection->config());
		//$cx->config($config);

		exit;
	}*/

}

?>
