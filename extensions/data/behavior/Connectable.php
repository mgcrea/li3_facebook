<?php

namespace facebook\extensions\data\behavior;

class Connectable extends \lithium\core\StaticObject {

    /**
	 * An array of configurations indexed by model class name,
	 * for each model to which this class is bound.
	 *
	 * @var array
	 */
	protected static $_configurations = array();

	/**
	 * sync
	 */
	public static function syncFacebookFriends($model, array &$data = array(), array $config = array()) {
		debug(compact('model', 'data', 'config')); exit;

		// check friends
		$allFriends = array_shift($Model->Facebook->graph('me/friends', array('offset' => 0)));
		$allFriendsIds = array();

		foreach($allFriends as $friend) {
			$user = $Model->find('first', array('conditions' => array('facebook_id' => $friend['id'])));
			if(!$user) {
				$Model->create();
				$user = $Model->save(array('User' => $friend), array('type' => "facebook"));
			}
			$allFriendsIds[] = $user[$Model->alias]['_id'];
		}

		$Model->save(array($Model->alias => array('_id' => $data[$Model->alias]['_id'], 'friends' => $allFriendsIds)), array('type' => "facebook"));

	}

	/**
	 * Behavior init setup
	 *
	 * @param object $class
	 * @param array	$config
	 */
    public static function bind($model, array $config = array()) {

		$defaults = array(
			'debug' => true
		);
		$config += $defaults;

		debug(static::_object()); exit;

		//$model::syncFacebookFriends = function()  { debug('GOOD'); };

		$model::applyFilter('syncFacebookFriends', function($self, $params, $chain) use ($model) {
			debug('IN!'); exit;
		});

		$model::applyFilter('_call', function($self, $params, $chain) use ($model) {
			debug('_call'); exit;
			/*$params = Dateable::invokeMethod('_formatUpdated', array(
				$class, $params
			));*/
			return $chain->next($self, $params, $chain);
		});
    }

	public function __call($name, $arguments) {
        debug(func_get_args());
    }

	public static function __callStatic($name, $arguments) {
		debug(func_get_args());
	}
}

?>