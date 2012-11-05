PHP-Service-Proxy
=================

Asynchronous web service proxy for PHP that does not rely on the cURL library or any PHP plugins outside of core.

Current features include
 - HTTP 1.0 support
 - Anynchronous requests by design
 - No cURL requirement
 - Responses are PHP objects (includes automatic XML parsing)
 - Mild error handling

This service proxy is asynchronous only and is currently missing the following:
 - Response sequence ordering
 - Perfect error handling
 - HTTP 1.1 support, chunking

Sample Usage
============

ServiceProxy should always be inherited and built upon as opposed to being referenced or instantiated.

`
	/**
	 * Web service client base class for building drivers to different web services
	 */
	abstract class ServiceClient extends ServiceProxy {
	  protected static $user_agent = "Service Client";
	  
	  /**
	   * Globally disables caching regardless of other settings
	   */
	  protected $cache_disable = false;
	  
	  /**
	   * Whether or not service calls should cache by default
	   */
	  protected $cache_by_default = true;
	  
	  /**
	   * Time in seconds that the cache should be maintained before refreshing data
	   */
	  protected $cache_expiration = 300; // default is 5 minutes
	  
	  /**
	   * Whether or not old cached data should be automatically removed from the data store
	   */
	  protected $cache_auto_prune = true;
	  
	  /**
	   * Timeout in seconds to wait for a service call on this client to complete before failing
	   */
	  protected $timeout = 10;
	  
	  /**
	   * Constructor
	   *
	   * @param string $endpoint HTTP endpoint URI
	   * @param string $user Username to authenticate with
	   * @param string $pass Password to authenticate with
	   * @param mixed[] $header Array of header information
	   * @param integer $timeout Timeout in seconds for all requests through this service client
	   *
	   * @return ServiceClient Instance of service client
	   */
	  function __construct(&$endpoint = null, &$header = array(), &$timeout = 30) {
		// Allow authentication and headers to be reset in future instantiations when necessary
		if(isset($this->header)) $header = array_merge($header, $this->header);
		
		parent::__construct($endpoint, $header, ServiceClient::$user_agent);
	  }
	  
	  /**
	  * Implements caching support
	  *
	  * @param mixed[] $request
	  * @param mixed $response
	  *
	  * @return void
	  */
	  protected static function commitToCache(&$response) {
		$stored_data = array(
		  'timestamp' => time(),
		  'expiration' => $this->getCacheExpiration(),
		  'request' => $request,
		  'response' => $response,
		);
		
		// enter this into a super fast database
	  }
	  
	  /**
	  * Implement caching support
	  *
	  * @param mixed[] $request
	  *
	  * @return mixed[]|null Array of response data
	  */
	  protected static function queryFromCache(&$request) {
		return null;
		
		// get data from a super fast data store
		$stored_data = array(
		  'timestamp' => time() - 60, // fake a minute ago
		  'expiration' => $this->getCacheExpiration(),
		  'request' => $request,
		  'response' => "Some data twas cached..."
		);
		
		return $stored_data;
	  }
	  
	  /**
	  * Implements caching support
	  * Prunes expired cached responses.  Override this function to implement caching.  Use
	  * $this->getCacheExpiration() to retrieve the time in seconds that cached responses should
	  * be available.
	  *
	  * @return void
	  */
	  protected function pruneCache() {
		
	  }
	  
	  /**
	  * Implements caching support
	  * Removes a single object from cache
	  *
	  * @param mixed $request Request to clear the response cache for
	  *
	  * @return void
	  */
	  public function removeFromCache(&$request) {
		
	  }
	  
	  /**
	  * Implements caching support
	  * Clears all cached objects.
	  *
	  * @return void
	  */
	  public function clearCache() {
		
	  }
	}
`
