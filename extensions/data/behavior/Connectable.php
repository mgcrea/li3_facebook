<?php

namespace facebook\extensions\data\behavior;

use facebook\models\Friends as FacebookFriends;
use facebook\extensions\adapter\data\source\http\Facebook;

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
	public static function syncFacebookFriends($model, $me, array $options = array()) {
		//debug(compact('class', 'model', 'data', 'config')); exit;

		// Retrieve all friends
		$allFriends = FacebookFriends::all(array('limit' => 5, 'offset' => 0));

		$allFriendsIds = array();
		foreach($allFriends as $friend) {
			$user = $model::find('first', array('conditions' => array('facebook.id' => $friend['id'])));
			if(!$user) {
				$user = $model::create($friend);
				$success = $user->save(null, array('source' => 'facebook'));
			}
			$allFriendsIds[] = $user->_id->{'$id'};
		}

		$me->set(array('friends' => $allFriendsIds));
		return $success = $me->save();

	}

	/**
	 * Behavior init setup
	 *
	 * @param object $class
	 * @param array	$config
	 */
    public static function bind($model, array $config = array()) {

		$defaults = array(
			//'debug' => true
		);
		$config += $defaults;

		$model::applyFilter('save', function($self, $params, $chain) use ($model) {

			if(!empty($params['options']['source']) && $params['options']['source'] == 'facebook') {

				// Append picture url
				$pictureUrl = Facebook::$domains['graph']['scheme'] . '://' . Facebook::$domains['graph']['host'] . '/' . $params['entity']->id . '/picture';
				$params['entity']->set(array('picture' => $pictureUrl));

				// Wrap data into a array
				$data = $params['entity']->data();
				foreach($data as $k => $v) {
					 unset($params['entity']->{$k});
				}
				$params['entity']->set(array('facebook' => $data));
				// This is breaking everything up !!!!
				//$params['entity'] = $model::create(array('facebook' => $params['entity']->data()));

				// Extract fields
				// @todo add in method
				if(!empty($params['entity']->facebook['name'])) $params['entity']->set(array('name' => $params['entity']->facebook['name']));
				if(!empty($params['entity']->facebook['email'])) $params['entity']->set(array('email' => $params['entity']->facebook['email']));
			}

			// Always make sure to keep the filter chain going.
			$response = $chain->next($self, $params, $chain);

			//debug($params['entity']);
			//debug($response);
			//debug($params['entity']->_id); exit;

			return $response;

		});

    }

}

?>