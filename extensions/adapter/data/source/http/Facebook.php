<?php
/**
 * Facebook Library for Lithium : the most rad php framework
 *
 * @copyright     Copyright 2011, Magenta Creations (http://mg-crea.com)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace facebook\extensions\adapter\data\source\http;

use lithium\util\String;
use lithium\util\Inflector;
use lithium\security\Auth;
use lithium\storage\Cache;
use lithium\storage\Session;

class Facebook extends \lithium\data\source\Http {

	/**
	 * Define what item() uses to create data objects
	 */
	protected $_classes = array(
		'service' => 'lithium\net\http\Service',
		'entity'  => 'lithium\data\entity\Document',
		'set'     => 'lithium\data\collection\DocumentSet',
	);

	/**
	 * Store related Auth object
	 */
	protected $_auth = null;

	/**
	 * Facebook API Domains
	 */
	public static $domains = array(
		'graph' => array(
			'scheme' => 'https',
			'host' => 'graph.facebook.com',
			'port' => 443
		),
		'picture' => 'http://graph.facebook.com/',
		'www' => 'https://www.facebook.com/'
	);

	/**
	 * Facebook API Paths
	 */
	public static $paths = array(
		'friends' => array(
			'domain' => 'graph',
			'defaults' => array('id' => 'me/friends')
		),
		'users' => 'friends'
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
		CURLOPT_SSL_VERIFYHOST => true
	);

	public function __construct(array $config = array()) {

		// defaults will be overriden based on model
		$defaults = array(
			'auth' => 'facebook', // required
			'scheme' => null,
			'host' => null,
			'port' => null,
			'cache' => 'facebook',
			'connection' => 'facebook',
			'certificate' => null,
			'socket' => 'Context'//'Curl'
		);

		parent::__construct($config += $defaults);
	}

	public function _init() {
		parent::_init();

		// reference related Auth for later use
		$authConfig = Auth::config($this->_config['auth']);
		$this->_auth = $authConfig['object'];

		// use bundled certificate
		if(!$this->_config['certificate']) $this->_config['certificate'] = dirname(__FILE__) . DS . '..' . DS . '..' . DS . '..' . DS . '..' . DS . '..' . DS . 'libraries' . DS . 'php-sdk' . DS . 'src' . DS . 'fb_ca_chain_bundle.crt';
		self::$curlOptions[CURLOPT_CAINFO] = $this->_config['certificate'];

		$cachePath = LITHIUM_CACHE_PATH . DS . $this->_config['cache'];
		if(!is_dir($cachePath)) mkdir($cachePath, 0770, true);
		Cache::config(array($this->_config['cache'] => array(
			'adapter' => 'File',
			'strategies' => array('Json'),
			'path' => $cachePath,
			'expiry' => '+1 year'
		)));
	}

	public function read($query, array $options = array()) { //debug(compact('options')); debug($this); debug($this->_config); exit;

		// asking the Query object to export the parameters that we care about for this API call
		$params = $query->export($this, array('source', 'conditions'));
		// 'source' is the API resource accessed
		$source = !empty($options['source']) ? $options['source'] : $params['source'];

		// make sure the resource we're attempting to read from is one we know about in our map
		if(!isset(self::$paths[$source])) {
			trigger_error('No map available for ressource `' . $source . '`', E_USER_WARNING);
			return null;
		}
		// support source redirections
		if(is_string(self::$paths[$source])) $source = self::$paths[$source];
		// override default configuration based on domain
		$this->connection->_config = self::$domains[self::$paths[$source]['domain']] + $this->connection->_config;
		$this->_config = self::$domains[self::$paths[$source]['domain']] + $this->_config;

		// initialize the socket
		if($this->_config['socket'] == 'Curl') {
			//$defaults = array('return' => 'body', 'classes' => $this->connection->_classes);
			$curl = $this->connection->connection();
			$curl->set(self::$curlOptions);
		}

		// apply static defaults to conditions
		if(!empty(self::$paths[$source]['defaults'])) $conditions = (array)$params['conditions'] + self::$paths[$source]['defaults'];

		$conditions += array(
			'access_token' => $this->_auth->get('access_token'), // retrieve acces_token from Auth adapter
			'metadata' => false,
			'limit' => !empty($options['limit']) ? $options['limit'] : null,
			'offset' => !empty($options['offset']) ? $options['offset'] : null,
			//'until' => null,
			//'since' => null,
		);

		// use the id as the main key ?
		$request = $conditions['id'];
		unset($conditions['id']);

		// craft the URL to the API using the conditions from the query
		$url = static::craftPath($request, $conditions);
		// get request response
		$ressource = $this->_request($url);
		//debug(compact('request', 'conditions', 'source', 'url', 'ressource'));

		// deal with multiple returns
		if(!empty($ressource['data'])) {
			return $this->item($query->model(), $ressource['data'], array('class' => 'set'));
		// or single ones
		} else {
			//debug($query->model());
			return $this->item($query->model(), array($ressource), array('class' => 'entity'));
			//return $this->item($query->model(), array($ressource), array('class' => 'entity'));
		}

	}

	/**
	 * Helper method for reading in the Session
	 *
	 * @param string $key
	 * @return void
	 */
	protected function _readSession($key = null) {
		return Session::read('data.' . $this->_config['auth'] . ($key ? '.' . $key : null));
	}

	/**
	 * Helper method for writing in the Session
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	protected function _writeSession($key = null, $value = null) {
		return Session::write('data.' . $this->_config['auth'] . ($key ? '.' . $key : null), $value);
	}

	/**
	 * Craft the URL for given domain alias, path and parameters.
	 *
	 * @param $name String the name of the domain
	 * @param $path String optional path (without a leading slash)
	 * @param $params Array optional query parameters
	 * @return String the URL for the given parameters
	 */
	public static function craftPath($path = null, $params = array()) {
		if ($path && $path[0] === '/') $path = substr($path, 1);
		return '/' . $path . ($params ? '?' . http_build_query($params) : null);
	}

	/**
	 * Process an oAuth request
	 *
	 * @param $url String url to fetch
	 * @param $options Array optional query parameters
	 * @return Mixed the Response received
	 */
	protected function _request($path, $options = array()) {

		// Cache external requests
		// @todo can use access_token for fb session lifetime cache
		$cacheKey = $this->_auth->get('me.id') . '-' . sha1($path);
		//Cache::delete($this->_config['cache'], $cacheKey);
		if($this->_config['cache'] && ($response = Cache::read($this->_config['cache'], $cacheKey)) !== null) {
			return $response;
		}
		//$response = self::curlGet($url, $options);
		$response = $this->connection->get($path);
		//debug(compact('path', 'response'));

		if(!empty($response[0]) && $response[0] == '{') $result = json_decode($response, true);
		$response = !empty($result) ? $result : $response;

		// results are returned, errors are thrown
		if (is_array($response) && !empty($response['error'])) {
			trigger_error($response['error']['type'] . ': ' . $response['error']['message'] . ' ~ ' . $path, E_USER_WARNING);
			return false;
		}

		if($this->_config['cache']) Cache::write($this->_config['cache'], $cacheKey, $response);

		return $response;
	}

	/**
	 * Process a request with curl
	 *
	 * @param $url String url to fetch
	 * @param $options Array optional query parameters
	 * @return Mixed the Response received
	 */
	public static function curlGet($url, array $options = array()) {

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
