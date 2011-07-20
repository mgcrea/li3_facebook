<?php

namespace facebook\models;

use lithium\data\Connections;

class Users extends \lithium\data\Model {

	public $_meta = array(
		'connection' => "facebook"
	);

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
