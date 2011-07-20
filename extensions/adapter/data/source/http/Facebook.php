<?php

namespace facebook\extensions\adapter\data\source\http;

use lithium\util\String;
use lithium\util\Inflector;
use lithium\storage\Cache;

class Facebook extends \lithium\data\source\Http {

	/**
	 * API Paths
	 */
	public static $paths = array(
		'places' => array(
			'path' => '/maps/api/place/search/{:output}?location={:location}&radius={:radius}&types={:types}&language={:language}&name={:name}&sensor={:sensor}&key={:key}',
			'defaults' => array(
				'output' => "json",
				'location' => null,
				'radius' => null,
				'types' => null,
				'language' => null,
				'name' => null,
				'sensor' => 'false',
				'key' => null
			)
		),
		'restaurants' => 'eatery'
	);

	/**
	 * Default configs
	 */
	static $_config = array(
		'cache' => 'facebook',
		'connection' => 'facebook'
	);

	/**
	 * Define what item() uses to create data objects
	 */
	protected $_classes = array(
		'service' => 'lithium\net\http\Service',
		'entity'  => 'lithium\data\entity\Document',
		'set'     => 'lithium\data\collection\DocumentSet',
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
			'scheme'   => 'https',
			'host'     => 'maps.googleapis.com',
			'port'     => 443,
			'cache'    => true,
			'curl'     => true,
			//'basePath' => '/api/v2/json',
		);
		$config += $defaults;

		$cachePath = LITHIUM_CACHE_PATH . DS . self::$cacheConfig;
		if(!is_dir($cachePath)) mkdir($cachePath, 0770, true);
		Cache::config(array(self::$cacheConfig => array(
			'adapter' => 'File',
			'path' => $cachePath,
			'expiry' => '+1 year'
		)));

		parent::__construct($config);
	}

	public function read($query, array $options = array()) { debug(compact('options')); debug($this); debug($this->_config); exit;

		// asking the Query object to export the parameters that we care about for this API call
		$params = $query->export($this, array('source', 'conditions'));

		// 'source' is the API resource accessed
		$source = !empty($options['source']) ? $options['source'] : $params['source'];
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

	/*public function calculation($type, $query) { //debug(compact('options')); debug($this->_config);

		debug(compact('query', 'options')); exit;
	}*/


	/**
	* request
	*/
	protected function request($path) {

		// Cache external requests
		$cacheKey = Inflector::slug($path);
		if(!$this->_config['cache'] || ($ressource = Cache::read(self::$cacheConfig, $cacheKey)) === false) {
			if(!$this->_config['curl']) $ressource = $this->connection->get($this->_config['host'] . $path);
			else $ressource = self::curl_get($this->_config['scheme'] . '://' . $this->_config['host'] . ':' . $this->_config['port'] . $path);
			if($ressource) Cache::write(self::$cacheConfig, $cacheKey, $ressource);
		}

		return $ressource;
	}

	/**
	* curl_get
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

/**
 * Basic defines
 */
if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}
if(!defined('LITHIUM_CACHE_PATH')) {
	define('LITHIUM_CACHE_PATH', LITHIUM_APP_PATH . DS . 'resources' . DS . 'tmp' . DS . 'cache');
}
