	
	 ___           __                ___                       __                      __         
	/\_ \    __  /'__`\            /'___\                     /\ \                    /\ \        
	\//\ \  /\_\/\_\L\ \          /\ \__/   __      ___     __\ \ \____    ___     ___\ \ \/'\    
	  \ \ \ \/\ \/_/_\_<_  _______\ \ ,__\/'__`\   /'___\ /'__`\ \ '__`\  / __`\  / __`\ \ , <    
	   \_\ \_\ \ \/\ \L\ \/\______\\ \ \_/\ \L\.\_/\ \__//\  __/\ \ \L\ \/\ \L\ \/\ \L\ \ \ \\`\  
	   /\____\\ \_\ \____/\/______/ \ \_\\ \__/.\_\ \____\ \____\\ \_,__/\ \____/\ \____/\ \_\ \_\
	   \/____/ \/_/\/___/            \/_/ \/__/\/_/\/____/\/____/ \/___/  \/___/  \/___/  \/_/\/_/
																								  
																								  
Facebook API library for the [lithium framework](http://github.com/UnionOfRAD/lithium)

* Auth adapter : [extensions/adapter/security/auth/Facebook.php](https://github.com/mgcrea/li3_facebook/blob/master/extensions/adapter/security/auth/Facebook.php)
* Http source : [extensions/adapter/data/source/http/Facebook.php](https://github.com/mgcrea/li3_facebook/blob/master/extensions/adapter/data/source/http/Facebook.php)
* Connectable behavior : [extensions/data/behavior/Connectable.php](https://github.com/mgcrea/li3_facebook/blob/master/extensions/data/behavior/Connectable.php)

## quick setup

> In your bootstrap/session.php

	use lithium\storage\Session;

	Session::config(array(
		'default' => array('adapter' => 'Php')
	));
	
	use lithium\security\Auth;

	Auth::config(array(
		'facebook' => array(
			'adapter' => 'Facebook',
			'app_id'      => 'FB_APP_ID_HERE',
			'api_key'     => 'FB_API_KEY_HERE',
			'app_secret'  => 'FB_APP_SECRET_HERE'
		)
	));

	use lithium\action\Dispatcher;
	use lithium\action\Response;

	Dispatcher::applyFilter('_callable', function($self, $params, $chain) {
		$controller = $chain->next($self, $params, $chain);
		
		if (Auth::check('facebook', $params['request'], array('checkSession' => false))) {
			return $controller;
		}
		if (isset($ctrl->publicActions) && in_array($params['request']->action, $ctrl->publicActions)) {
			return $controller;
		}
		return function() {
			return new Response(array('location' => '/login'));
		};
	});

> In one of your controller

	use facebook\models\Friends as FacebookFriends;

	class UsersController extends \lithium\action\Controller {
		
		public function index() {
			
			// Retreive user info from Facebook auth adapter
			$fbAuth = Auth::config('facebook');
			$fbUser = $fbAuth['object']->get('me');
			
			// Check for a match in db
			$user = Users::find('first', array('conditions' => array('email' => $fbUser['email'])));
			
			// Retrieve all friends
			$allFriends = FacebookFriends::all(array('limit' => 5, 'offset' => 0));
			
		}

	}

For Docs, License, Tests, and pre-packed downloads, see:

> http://mgcrea.github.com/li3-facebook/

To suggest a feature, report a bug, or general discussion:

> http://mgcrea.github.com/li3-facebook/issues/

All contributors are listed here:

> http://mgcrea.github.com/li3-facebook/contributors