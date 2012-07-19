<?php

/**
* ServiceProxy provides a basic interface to a web service without relying on the cURL library.
* To implement logging, override the logEventHandler($message, $severity, $trace) function.
*   Severities: 1 = Notice, 2 = Warning, 3 = Error, 4 = Fatal
*
* @author Aaron Belovsky
* @link https://www.github.com/ambelovsky/PHP-Service-Proxy GitHub
*
* @package ambelovsky
*/
abstract class ServiceProxy {
  const LOG_INFO = 1;
  const LOG_WARN = 2;
  const LOG_ERROR = 3;
  //const LOG_FATAL = 4;
  
  /**
   * Custom user agent name to send the request as
   */
  protected static $user_agent;
  
  /**
   * Base service web url
   */
  protected static $endpoint;
  
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
   * Retains the default cache expiration time whenever it has been temporarily modified
   */
  private $cache_expiration_memory = 0;
  
  /**
   * Timeout in seconds to wait for a service call on this client to complete before failing
   */
  protected $timeout = 30;
  
  /**
   * Service function to call
   */
  protected $action;
  
  /**
   * Method of request "GET", "POST", "PUT", "DELETE"
   */
  protected $method;
  
  /**
   * Header to send with the request
   */
  protected $header;
  
  /**
   * Stored response meta data from the most recent request
   */
  protected $response_meta;
  
  /**
   * HTTP status code from the response
   */
  protected $response_status;
  
  /**
   * Stored response from the most recent request
   */
  protected $response;
  
  /**
   * Authentication information for the service
   */
  private $auth;
  
  /**
   * Stored data from the most recent request
   */
  private $data = null;
  
  /**
  * Constructor
  *
  * @param string $endpoint HTTP endpoint URI
  * @param mixed[] $header Array of header information
  * @param string $user_agent Name of the user agent that this client will represent itself as
  *  to the server that receives the request.
  *
  * @return ServiceProxy Instance of service proxy
  */
  function __construct(&$endpoint = "http://www.example.com", &$header = array(), &$user_agent = "") {
    $this->endpoint = $endpoint;
    $this->header = $header;
    $this->user_agent = $user_agent;
  }
  
  /**
  * Sets authentication information for a web service.  Must be set prior to sending any requests.
  *
  * @param string $username
  * @param string $password
  *
  * @return ServiceProxy
  */
  public function authenticate($username, $password = "") {
    $this->auth = base64_encode($username . ':' . $password);
    return $this;
  }
  
  /**
  * Turn caching on by default
  *
  * @param integer $expiration Time in seconds until the cached objects should expire.
  *  Anytime $expiration is not provided, the default expiration time will be used.
  */
  public function cacheOn($expiration = null) {
    $this->setCacheExpiration($expiration);
    $this->cache_by_default = true;
  }
  
  /**
  * Turn caching off by default
  */
  public function cacheOff() {
    $this->cache_by_default = false;
  }
  
  /**
  * Sets the cache expiration time to use for cached objects.  If no expiration time is
  * provided, this will reset the cache expiration time to the default.
  *
  * @param integer $expiration Time in seconds until the cached objects should expire.
  *  Anytime $expiration is not provided, the default expiration time will be used.
  */
  public function setCacheExpiration($expiration = null) {
    if(!isset($expiration) && $this->cache_expiration_memory > 0) {
      $this->cache_expiration = $this->cache_expiration_memory;
    } else {
      $this->cache_expiration_memory = $this->cache_expiration;
      $this->cache_expiration = $expiration;
    }
  }
  
  /**
  * Retrieves the expiration time in seconds for any given request object.
  *
  * @param mixed[] $request
  *
  * @return integer Expiration time in seconds
  */
  protected function getCacheExpiration($request = null) {
    if(!isset($request) || !isset($request['expiration'])) return $this->cache_expiration;
    return $request['expiration'];
  }
  
  /**
  * Event handler for implementing logging support
  *
  * @param string $message Primary log message
  * @param integer $severity Severity of the issue that is being logged
  * @param mixed $trace Generally intended to be an Array or Exception type, $trace can
  *  hold any additionally pertinent logging information.
  *
  * @return void
  */
  protected function logEventHandler($message, $severity = ServiceProxy::LOG_INFO, $trace = array()) {
    // override this function to provide logging support
  }
  
  /**
  * Removes a single object from cache.  Override this function to implement caching.
  *
  * @param mixed $request Request to clear the response cache for
  *
  * @return void
  */
  public function removeFromCache(&$request) {
    
  }
  
  /**
  * Clears all cached objects.  Override this function to implement caching.
  *
  * @return void
  */
  public function clearCache() {
    
  }
  
  /**
  * Override this function to implement caching support
  *
  * @param mixed[] $request
  * @param mixed $response
  *
  * @return void
  *
  * @example
  *  $stored_data = array(
  *    'timestamp' => time(),
  *    'expiration' => $this->getCacheExpiration(),
  *    'request' => $request,
  *    'response' => $response,
  *  );
  *  // enter this data into a super fast data store
  */
  protected function commitToCache(&$request, &$response) {
    // override to implement caching support
  }
  
  /**
  * Override this function to implement caching support
  *
  * @param mixed[] $request
  *
  * @return mixed[]|null Array response containing the following top-level fields
  *  -timestamp: the time() that this object was entered into cache
  *  -response: the actual response data
  *  -expiration: (optional) object-specific expiration time in seconds
  */
  protected function queryFromCache(&$request) {
    return null;
  }
  
  /**
  * Prunes expired cached responses.  Override this function to implement caching.  Use
  * $this->getCacheExpiration() to retrieve the time in seconds that cached responses should
  * be available.
  *
  * @return void
  */
  protected function pruneCache() {
    
  }
  
  /**
  * Retrieves a cached response for a given request if the response exists.  If no response exists,
  * this function will call the appropriate service, cache the response, and return the cached response.
  *
  * @param mixed[] $request
  * @param boolean $cache True to force caching, false to force no caching
  */
  protected function getCachedResponse(&$request, $cache = null) {
    if(!isset($cache)) $cache = $this->cache_by_default;
    if($this->cache_auto_prune) $this->pruneCache(); // auto-prune old cached objects
  
    if(!$this->cache_disable && $cache) {
      $response = $this->queryFromCache($request);
      
      if( // cached response is valid
        isset($response)
        && !empty($response)
        && isset($response['timestamp'])
        && $response['timestamp'] > time() - $this->getCacheExpiration($request)
      ) {
        unset($response['timestamp'], $response['expiration']);
        return $response['response'];
      }
    }
    
    // fetch a response using the request
    $this->sendRequest($request);
    
    // cache the response
    $this->commitToCache($request, $this->getResponse());
    
    // return the cached response
    return $this->getResponse();
  }
  
  /**
  * Send a request expecting an XML response from the answering service
  *
  * @param string $action
  * @param string $method
  * @param mixed[] $data Data to send in the request to the service
  *
  * @return mixed[] XML web service response
  */
  protected function callXml($action, $method = "GET", &$data = array()) {
    $this->header = array_merge($this->header, array('Accept: text/xml'));
    $this->call($action, $method, $data);
    
    try {
      $this->setResponse(new SimpleXmlElement($this->getResponse()));
    } catch (Exception $e) {
      $this->logEventHandler($e->getMessage(), ServiceProxy::LOG_ERROR, $e);
    }
    
  	return $this->getResponse();
  }
  
  /**
  * Event handler for implementing status detection.  By default, this function logs anything other than a status "200 OK".
  *
  * @param mixed $response Web service response
  * @param integer $response_status HTTP status code
  *
  * @return void
  */
  protected function responseStatusEventHandler(&$response = null, &$response_status = null) {
    if(!isset($response)) $response = &$this->getResponse();
    if(!isset($response_status)) $response_status = &$this->response_status;
    
    if($this->response_status == 404)
      $this->logEventHandler('404: No record found for this request or web service not available.', ServiceProxy::LOG_INFO);
    if($this->response_status != 200 && $this->response_status != 404)
      $this->logEventHandler('Error ' . $this->response_status . '.', ServiceProxy::LOG_ERROR, $this->response_meta);
  }
  
  /**
  * Resubmits the last request
  * @return mixed Web service response
  */
  protected function retry() {
    return $this->call($this->data);
  }
  
  /**
  * Resubmits the last request
  * @return mixed Web service response
  */
  protected function retryXml() {
    return $this->callXml($this->data);
  }
  
  /**
  * Returns the read-only response
  * @return mixed Web service response
  */
  protected function getResponse() {
    return $this->response;
  }
  
  /**
  * Internal function that sends a request to the service
  *
  * @param string $action
  * @param string $method
  * @param mixed[] $data Data to send in the request to the service
  *
  * @return mixed Web service response
  */
  private function call($action, $method = "GET", &$data = array()) {
    $request = $this->getRequest($action, $method, $data);
    $this->getCachedResponse($request);
  	
  	return $this->getResponse();
  }
  
  /**
  * Setter for $response property
  *
  * @param mixed $response Response object to assign to the $response property
  *
  * @return void
  */
  private function setResponse($response) {
    $this->response = $response;
  }
  
  /**
  * Setter for all response meta data
  *
  * @param string[] $response_meta Array of response meta data
  *
  * @return void
  */
  private function setResponseMeta($response_meta) {
    if(!isset($response_meta['wrapper_data'])) {
      $this->logEventHandler('No meta data available from response.', ServiceProxy::LOG_WARN);
      return;
    }
    
    $this->response_meta = $response_meta['wrapper_data'];
    
    // extract status code from response meta data
    try {
      $status_parts = explode(' ', $this->response_meta[0]);
      $this->response_status = (int) $status_parts[1];
      unset($status_parts);
    } catch (Exception $e) {
      $this->logEventHandler('Unable to retrieve HTTP status code from response.', ServiceProxy::LOG_WARN, $e);
    }
  }
  
  /**
  * Internal function that sends a request to the service
  *
  * @param string $action
  * @param string $method
  * @param mixed[] $data Data to send in the request to the service
  *
  * @return mixed[] Web service request
  */
  private function getRequest($action, $method = "GET", &$data = array()) {
    $this->action = $action;
    $this->method = $method;
    
  	$params = array('http' => array('method' => $this->method)); // request configuration
  	
  	if(!empty($this->user_agent)) $params['http']['user_agent'] = $this->user_agent; // declare non-standard user agent
  	if(!empty($this->header)) $params['http']['header'] = $this->header; // build non-standard header
  	if(isset($this->auth) && !empty($this->auth)) $params['http']['header'][] = "Authorization: Basic " . $this->auth; // if authorization is necessary, add it to the header
  	
    // build an http query to pass to the service from given object data
    if(!empty($data)) {
      $this->data = $data;
  	  $query = http_build_query($data);
    	if($this->method != "GET") $params['http']['content'] = $query;
    	else $this->action .= $query;
    }
  	
  	return array(
  	  'endpoint' => $this->endpoint,
  	  'action' => $this->action,
  	  'method' => $this->method,
  	  'params' => $params,
  	  'settings' => array(
  	    'timeout' => $this->timeout,
  	  ),
  	);
  }
  
  /**
  * Sends a request to the web service
  *
  * @param mixed[] $request
  *
  * @return mixed[] XML web service response
  */
  private function sendRequest($request) {
    try {
      $context = stream_context_create($request['params'], $request['settings']); // create the stream
  	  $file_stream = @fopen($request['endpoint'] . $request['action'], 'rb', false, $context); // send the request
  
  	  $this->setResponse(@stream_get_contents($file_stream)); // get results
  	  $this->setResponseMeta(@stream_get_meta_data($file_stream)); // get results
  	} catch (Exception $e) {
  	  $this->logEventHandler($e->getMessage(), ServiceProxy::LOG_ERROR, $e);
  	}
  	
  	$this->responseStatusEventHandler();
  	return $this->getResponse();
  }
}

?>
