<?php

use lithium\action\Dispatcher;
use lithium\storage\Session;

/**
 * Basics
 */
if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}
if(!defined('LITHIUM_CACHE_PATH')) {
	define('LITHIUM_CACHE_PATH', LITHIUM_APP_PATH . DS . 'resources' . DS . 'tmp' . DS . 'cache');
}

/*use lithium\action\Dispatcher;
use facebook\extensions\adapter\security\auth\Facebook;

Dispatcher::applyFilter('_callable', function($self, $params, $chain) {

	//debug(compact('self', 'params', 'chain'));
	Facebook::$request = $params['request'];

	// Always make sure to keep the filter chain going.
	$response = $chain->next($self, $params, $chain);

	return $response;
});*/
