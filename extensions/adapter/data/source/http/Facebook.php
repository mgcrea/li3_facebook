<?php

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
	 * Facebook API Domains
	 */
	public static $domains = array(
		'graph' => 'https://graph.facebook.com/',
		'picture' => 'http://graph.facebook.com/',
		'www' => 'https://www.facebook.com/'
	);

	/**
	 * Facebook API Paths
	 */
	public static $paths = array(
		'users' => array(
			'domain' => 'graph',
			'request' => 'me'
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

	public function __construct(array $config = array()) {

		$defaults = array(
			'cache' => 'facebook',
			'connection' => 'facebook',
			'certificate' => null
		);

		parent::__construct($config += $defaults);
	}

	public function _init() {
		parent::_init();

		//$authConfig = Auth::config($this->_config['auth']);
		//$authConfig = $authConfig['object']->_config;

		// use bundled certificate
		if(!$this->_config['certificate']) $this->_config['certificate'] = dirname(__FILE__) . DS . '..' . DS . '..' . DS . '..' . DS . '..' . DS . '..' . DS . 'libraries' . DS . 'php-sdk' . DS . 'src' . DS . 'fb_ca_chain_bundle.crt';
		self::$curlOptions[CURLOPT_CAINFO] = $this->_config['certificate'];

		$cachePath = LITHIUM_CACHE_PATH . DS . $this->_config['cache'];
		if(!is_dir($cachePath)) mkdir($cachePath, 0770, true);
		Cache::config(array($this->_config['cache'] => array(
			'adapter' => 'File',
			'path' => $cachePath,
			'expiry' => '+1 year'
		)));
	}

	public function read($query, array $options = array()) { //debug(compact('options')); debug($this); debug($this->_config); exit;

		// asking the Query object to export the parameters that we care about for this API call
		$params = $query->export($this, array('source', 'conditions'));

		// 'source' is the API resource accessed
		$source = !empty($options['source']) ? $options['source'] : $params['source'];

		debug(compact('options', 'params', 'source', 'url'));

		debug($this->graph($options['conditions']['id']));

		 exit;
		// make sure the resource we're attempting to read from is one we know about in our map
		if(!isset(self::$paths[$source])) return null;
		// support source redirections
		if(is_string(self::$paths[$source])) $source = self::$paths[$source];

		// apply defaults
		$conditions = (array) $params['conditions'] + self::$paths[$source]['defaults'];
		// merge required params
		$required = array('key' => $this->_config['key']);
		$conditions = array_merge($conditions, $required);
		// craft the URL to the API using the conditions from the Query
		$path = String::insert(self::$paths[$source]['path'], array_map('urlencode', (array) $conditions));
		//$path .= '?' . http_build_query(array_merge($conditions, $required));

		// request ressource
		$ressource = json_decode($this->request($path), true);


		if(!$ressource) {
			trigger_error('API Request failed.', E_USER_WARNING);
			return null;
		}

		$data[$source] = $ressource['results'];
		return $this->item($query->model(), $data[$source], array('class' => 'set'));
	}

	/**
	 * graph() ~ Returns facebook graph information
	 *
	 * @param string $request
	 * @param array $options to pass to the api (limit, offset, until, since)
	 * @return array returned json from facebook
	 * @access public
	 */
	function graph($request = 'me', $options = array()) {

		// check if we need a picture ~ we won't check certs for that
		if(preg_match('/\/picture$/', $request)) {
			self::$CURL_OPTS = array_merge_keys(self::$CURL_OPTS, array(
				CURLOPT_BINARYTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false
			));
		}

		$defaults = array(
			'access_token' => $this->_readSession('access_token'),
			'metadata' => false,
			//'limit' => null,
			//'offset' => null,
			//'until' => null,
			//'since' => null,
		);
		$url = $this->getUrl('graph', $request, array_merge($defaults, $options));
		$response = static::request($url);
		debug($response); exit;

		return $response;

	}

	/**
	 * Helper method for reading in the Session
	 *
	 * @param string $key
	 * @return void
	 */
	protected function _readSession($key = null) {
		return Session::read('security.' . $this->_config['auth'] . ($key ? '.' . $key : null));
	}

	/**
	 * Helper method for writing in the Session
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	protected function _writeSession($key = null, $value = null) {
		return Session::write('security.' . $this->_config['auth'] . ($key ? '.' . $key : null), $value);
	}

	/**
	 * Build the URL for given domain alias, path and parameters.
	 *
	 * @param $name String the name of the domain
	 * @param $path String optional path (without a leading slash)
	 * @param $params Array optional query parameters
	 * @return String the URL for the given parameters
	 */
	public static function getUrl($name, $path = null, $params = array()) {
		if ($path && $path[0] === '/') $path = substr($path, 1);
		return static::$domains[$name] . $path . ($params ? '?' . http_build_query($params) : null);
	}

	/**
	 * Process an oAuth request
	 *
	 * @param $url String url to fetch
	 * @param $options Array optional query parameters
	 * @return Mixed the Response received
	 */
	public static function request($url, $options = array()) {

		$response = self::curlGet($url, $options);
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
