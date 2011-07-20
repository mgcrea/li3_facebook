	
	 ___           __                ___                       __                      __         
	/\_ \    __  /'__`\            /'___\                     /\ \                    /\ \        
	\//\ \  /\_\/\_\L\ \          /\ \__/   __      ___     __\ \ \____    ___     ___\ \ \/'\    
	  \ \ \ \/\ \/_/_\_<_  _______\ \ ,__\/'__`\   /'___\ /'__`\ \ '__`\  / __`\  / __`\ \ , <    
	   \_\ \_\ \ \/\ \L\ \/\______\\ \ \_/\ \L\.\_/\ \__//\  __/\ \ \L\ \/\ \L\ \/\ \L\ \ \ \\`\  
	   /\____\\ \_\ \____/\/______/ \ \_\\ \__/.\_\ \____\ \____\\ \_,__/\ \____/\ \____/\ \_\ \_\
	   \/____/ \/_/\/___/            \/_/ \/__/\/_/\/____/\/____/ \/___/  \/___/  \/___/  \/_/\/_/
																								  
																								  
Facebook API library for the [lithium framework](http://github.com/UnionOfRAD/lithium)

> Main file currently is an Auth adapter : adapter/security/auth/Facebook.php

## quick setup

> In your bootstrap/auth.php

	<?php

	use lithium\storage\Session;
	use lithium\security\Auth;
	use facebook\extensions\adapter\security\auth\Facebook;

	Session::config(array(
		'default' => array('adapter' => 'Php')
	));

	Auth::config(array(
		'facebook' => array(
			'adapter' => 'Facebook',
			'app_id'      => 'FB_APP_ID_HERE',
			'api_key'     => 'FB_API_KEY_HERE',
			'app_secret'  => 'FB_APP_SECRET_HERE'
		)
	));

	?>

> In your SessionsController (or another one).

	public function add() {

		if (Auth::check('facebook', $this->request)) {
			return $this->redirect('/');
		}

		// Handle failed authentication attempts

	}

For Docs, License, Tests, and pre-packed downloads, see:

> http://mgcrea.github.com/li3-facebook/

To suggest a feature, report a bug, or general discussion:

> http://mgcrea.github.com/li3-facebook/issues/

All contributors are listed here:

> http://mgcrea.github.com/li3-facebook/contributors