<?php
/**
 * Facebook Library for Lithium : the most rad php framework
 *
 * @copyright     Copyright 2011, Magenta Creations (http://mg-crea.com)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace facebook\extensions\adapter\security\auth;

use lithium\security\Auth;
use lithium\security\validation\RequestToken;
use lithium\storage\Session;
use lithium\net\http\Router;

/**
 * The `Facebook` adapter provides basic and digest authentication based on the Facebook API.
 *
 * {{{
 * Auth::config(array('name' => array(
 *     'adapter' => 'Http',
 *     'method' => 'basic'
 * )))
 * }}}
 *
 * @link http://tools.ietf.org/html/rfc2068#section-14.8
 * @see `\lithium\action\Request`
 */
class Facebook extends \lithium\core\Object {

	/**
	 * Facebook API Paths
	 */
	public static $paths = array(
		'graph' => array(
			'domain' => 'https://graph.facebook.com/',
		),
		'picture' => array(
			'domain' => 'http://graph.facebook.com/',
		),
		'www' => array(
			'domain' => 'https://www.facebook.com/'
		)
	);

	/**
	 * Default options for curl.
	 */
	public static $curlOptions = array(
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT        => 60,
		CURLOPT_USERAGENT      => 'curl-php',
	);

	/**
	 * Setup default configuration options.
	 *
	 * @param array $config
	 *        - `method`: default: `digest` options: `basic|digest`
	 *        - `realm`: default: `Protected by Lithium`
	 *        - `users`: the users to permit. key => value pair of username => password
	 */
	public function __construct(array $config = array()) {

		$defaults = array(
			'realm' => basename(LITHIUM_APP_PATH),
			'redirect_uri' => $this->_getCurrentUrl(),
			'certificate' => null,
			'scope' => array(),
			'display' => null,
		);

		parent::__construct($config + $defaults);

	}

	/**
	 * Setup default initialization.
	 */
	public function _init() {

		// use bundled certificate
		if(!$this->_config['certificate']) $this->_config['certificate'] = dirname(__FILE__) . DS . '..' . DS . '..' . DS . '..' . DS . '..' . DS . 'libraries' . DS . 'php-sdk' . DS . 'src' . DS . 'fb_ca_chain_bundle.crt';
		self::$curlOptions[CURLOPT_CAINFO] = $this->_config['certificate'];

	}

	/**
	 * Called by the `Auth` class to run an authentication check against the HTTP data using the
	 * credientials in a data container (a `Request` object), and returns an array of user
	 * information on success, or `false` on failure.
	 *
	 * @param object $request A env container which wraps the authentication credentials used
	 *               by HTTP (usually a `Request` object). See the documentation for this
	 *               class for further details.
	 * @param array $options Additional configuration options. Not currently implemented in this
	 *              adapter.
	 * @return array Returns an array containing user information on success, or `false` on failure.
	 */
	public function check($request, array $options = array()) { //debug($request->base()); exit;

		// check for any session code post from facebook
		if(!empty($request->query['code'])) {
			if (!RequestToken::check($request->query['state'], array('sessionKey' => 'security.facebook.state'))) {
				trigger_error("The state does not match. You may be a victim of CSRF.");
				// trigger Auth clear
				Auth::clear($this->_config['session']['key']);
				exit;
				//return $this->_redirect('/');
			}
			$this->_writeSession('code', $request->query['code']);
		}

		// check Token if Session code exists
		if($this->_readSession('code')) {
			if(!$this->_checkToken() && !$this->_getToken()) {
				// trigger Auth clear
				Auth::clear($this->_config['session']['key']);
			} else {
				// everything is fine, return session
				return $this->_readSession();
			}
		}

		// generate random state to protect from crsf
		$state = RequestToken::key(array('regenerate' => true, 'sessionKey' => 'security.facebook.state')); //md5(uniqid(rand(), true));

		// let's login to retreive a code from facebook
		$url = $this->_getLoginUrl(compact('state'));
		$this->_redirect($url);
	}

	/**
	 * A pass-through method called by `Auth`. Returns the value of `$data`, which is written to
	 * a user's session. When implementing a custom adapter, this method may be used to modify or
	 * reject data before it is written to the session.
	 *
	 * @param array $data User data to be written to the session.
	 * @param array $options Adapter-specific options. Not implemented in the `Form` adapter.
	 * @return array Returns the value of `$data`.
	 */
	public function set($data, array $options = array()) {
		return $data;
	}

	/**
	 * Called by `Auth` when a user session is terminated. Not implemented in the `Form` adapter.
	 *
	 * @param array $options Adapter-specific options. Not implemented in the `Form` adapter.
	 * @return void
	 */
	public function clear(array $options = array()) {
		$this->_writeSession();
	}

	/**
	 * Requests an access_token using a session code from login
	 *
	 * @link http://developers.facebook.com/docs/authentication/#app-login
	 * @param $options Array optional query parameters
	 * @return String the Token reteived
	 */
	protected function _getToken($options = array()) {

		$url = $this->_getTokenUrl($options);
		$response = $this->_request($url); //debug($response); exit;

		// parse the response
		if(!is_string($response)) return false;
		parse_str($response, $response);

		if(!empty($response['access_token'])) {
			// update session
			$this->_writeSession('access_token', $response['access_token']);
			$this->_writeSession('access_token_time', time() - 5);
			if(!empty($response['expires'])) $this->_writeSession('access_token_expires', $response['expires']);

			// get logged user
			$meUrl = $this->_getUrl('graph', 'me', array('access_token' => $this->_readSession('access_token')));
			$this->_writeSession('me', $this->_request($meUrl));

			return true;
		}

		return false;

	}

	/**
	 * Checks an access_token using expires timestamp
	 *
	 * @param $options Array optional query parameters
	 * @return Boolean the Token status
	 */
	protected function _checkToken($options = array()) {

		// handle expiration
		$expiration = $this->_readSession('access_token_time') + $this->_readSession('access_token_expires');
		if($expiration && time() >= $expiration) {
			return false;
		}

		return (boolean) $this->_readSession('access_token');

	}

	/**
	 * Returns facebook adequate token url
	 *
	 * @param $options Array optional query parameters
	 * @return String the URL for the given parameters
	 */
	protected function _getTokenUrl($options = array()) {
		$defaults = array(
			'client_id' => $this->_config['app_id'],
			'client_secret' => $this->_config['app_secret'],
			'redirect_uri' => $this->_config['redirect_uri'],
			'code' => $this->_readSession('code'), // user login
			//'grant_type' => "client_credentials", // app login
		);

		return $this->_getUrl('graph', 'oauth/access_token', $options + $defaults);
	}

	/**
	 * Returns facebook adequate login url
	 *
	 * @param $options Array optional query parameters
	 * @return String the URL for the given parameters
	 */
	protected function _getLoginUrl($options = array()) {
		$defaults = array(
			'client_id' => $this->_config['app_id'],
			'redirect_uri' => $this->_config['redirect_uri'],
			'scope' => implode(',', $this->_config['scope']),
			'display' => $this->_config['display']
		);

		return $this->_getUrl('www', 'dialog/oauth', $options + $defaults);
	}

	/**
	 * Build the URL for given domain alias, path and parameters.
	 *
	 * @param $name String the name of the domain
	 * @param $path String optional path (without a leading slash)
	 * @param $params Array optional query parameters
	 * @return String the URL for the given parameters
	 */
	protected function _getUrl($name, $path = null, $params = array()) {
		if ($path && $path[0] === '/') $path = substr($path, 1);
		return static::$paths[$name]['domain'] . $path . ($params ? '?' . http_build_query($params) : null);
	}

	/**
	 * Retreive the current url
	 *
	 * @return String the URL for the given parameters
	 */
	protected function _getCurrentUrl() {
		$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://';
		//debug(Router::match()); exit;
		return $protocol . $_SERVER['HTTP_HOST'] . (!empty($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : '/');
	}

	/**
	 * Helper method for reading in the Session
	 *
	 * @param string $key
	 * @return mixed
	 */
	protected function _readSession($key = null) {
		return Session::read('security.'.$this->_config['session']['key'] . ($key ? '.' . $key : null));
	}

	/**
	 * Public helper method for reading in the Session
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function get($key = null) {
		return $this->_readSession($key);
	}

	/**
	 * Helper method for writing in the Session
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	protected function _writeSession($key = null, $value = null) {
		return Session::write('security.'.$this->_config['session']['key'] . ($key ? '.' . $key : null), $value);
	}

	/**
	 * Helper method for redirecting user.
	 *
	 * @param string $url the redirection url
	 * @return void
	 */
	protected function _redirect($url) {
		 $this->_writeHeader('Location: ' . $url);
		 exit;
	}

	/**
	 * Helper method for writing headers. Mainly used to override the output while testing.
	 *
	 * @param string $string the string the send as a header
	 * @return void
	 */
	protected function _writeHeader($string) {
		header($string, true);
	}

	/**
	 * Process an oAuth request
	 *
	 * @param $url String url to fetch
	 * @param $options Array optional query parameters
	 * @return Mixed the Response received
	 */
	protected function _request($url, $options = array()) {

		$response = self::curl_get($url, $options);
		if(!empty($response[0]) && $response[0] == '{') $result = json_decode($response, true);
		$response = !empty($result) ? $result : $response;

		// results are returned, errors are thrown
		if (is_array($response) && !empty($response['error'])) {
			trigger_error($response['error']['type'] . ': ' . $response['error']['message'] . ' ~ ' . $url, E_USER_WARNING);
			return false;
		}

		return $response;
	}

	/**
	 * Process a request with curl
	 *
	 * @param $url String url to fetch
	 * @param $options Array optional query parameters
	 * @return Mixed the Response received
	 */
	public static function curl_get($url, array $options = array()) {

		$options += self::$curlOptions;

		$ch = curl_init($url);

		// disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
		// for 2 seconds if the server does not support this header.
		if (isset($options[CURLOPT_HTTPHEADER])) {
			$existing_headers = $options[CURLOPT_HTTPHEADER];
			$existing_headers[] = 'Expect:';
			$options[CURLOPT_HTTPHEADER] = $existing_headers;
		} else {
			$options[CURLOPT_HTTPHEADER] = array('Expect:');
		}

		curl_setopt_array($ch, $options);
		$response = curl_exec($ch);

		if(empty($response)) {
			trigger_error('Curl error ' . curl_errno($ch) . ' : ' . ucfirst(curl_error($ch)), E_USER_ERROR);
		}
		if (curl_errno($ch) == 60) { // CURL_SSL_CACERT
			trigger_error('Invalid or no certificate authority found', E_USER_ERROR);
		}

		curl_close($ch);

		return $response;
	}
}

?>