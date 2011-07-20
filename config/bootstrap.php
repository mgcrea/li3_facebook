<?php
use lithium\action\Dispatcher;
use facebook\extensions\adapter\security\auth\Facebook;

Dispatcher::applyFilter('_callable', function($self, $params, $chain) {

	//debug(compact('self', 'params', 'chain'));
	Facebook::$request = $params['request'];

	// Always make sure to keep the filter chain going.
	$response = $chain->next($self, $params, $chain);

	return $response;
});