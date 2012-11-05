<?php

/**
* ServiceProxy provides a basic interface to a web service without relying on the cURL library.
* To implement logging, override the logEvent($message, $severity, $trace) function.
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
   * Base service transport protocol
   */
  protected static $transport;
  
  /**
   * Base service web url
   */
  protected static $endpoint;
  
  /**
   * Base service web host
   */
  protected static $host;
  
  /**
   * Base service web url port
   */
  protected static $port;
  
  /**
   * Globally disables caching regardless of other settings
   */
  protected $cache_disable = false;
  
  /**
   * Whether or not service calls should cache by default
   */
  protected $cache_by_default = true;
  
  /**
   * Whether or not service calls should cache by default
   */
  private $cache_by_default_memory = null;
  
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
  protected $timeout = 10;
  
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
   * Content type of the most recent response
   */
  protected $response_type;
  
  /**
   * HTTP status code from the most recent response
   */
  protected $response_status;
  
  /**
   * Stored response from the most recent request
   */
  protected $response;
  
  /**
   * Holds pointers for streams executing in parallel
   */
  protected static $streams;
  
  /**
   * Holds results for streams executing in parallel
   */
  protected static $stream_results;
  
  /**
   * Holds web service requests for streams executing in parallel
   */
  protected static $stream_requests;
  
  /**
   * Authentication information for the service
   */
  private $auth;
  
  /**
   * Authentication information for the service
   */
  private $auth_type = 'basic';
  
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
  function __construct(&$endpoint = "http://www.example.com", &$header = array(), &$user_agent = "", $port = null) {
    if(strpos($endpoint, 'https') === 0) {
      $this->port = 443;
      $this->transport = 'ssl';
    } elseif(strpos($endpoint, 'http') === 0) {
      $this->port = 80;
      $this->transport = 'tcp';
    }
    
    // if the user has specified an alternate port, use it instead.
    if(isset($port) && is_numeric($port)) $this->port = $port;
    
    // get only the URL, not the protocol
    if($transport_del = strpos($endpoint, '://'))
      $this->transport = isset($this->transport) ? $this->transport : substr($endpoint, 0, $transport_del);
    
    // default transport protocol is TCP
    if(!isset($this->transport)) $this->transport = 'tcp';
    
    $transport_del = $transport_del === FALSE ? 0 : $transport_del + 3;
    $this->host = substr($endpoint, $transport_del);
    
    if($path_del = strpos($endpoint, '/', $transport_del))
      $this->host = substr($endpoint, $transport_del, $path_del - $transport_del);
    
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
    if(!isset($this->cache_by_default_memory)) $this->cache_by_default_memory = $this->cache_by_default;
    
    $this->setCacheExpiration($expiration);
    $this->cache_by_default = true;
  }
  
  /**
  * Turn caching off by default
  */
  public function cacheOff() {
    if(!isset($this->cache_by_default_memory)) $this->cache_by_default_memory = $this->cache_by_default;
    
    $this->cache_by_default = false;
  }
  
  /**
  * Turn caching on or off according to default configured behavior
  */
  public function cacheDefaultBehavior() {
    if(isset($this->cache_by_default_memory)) $this->cache_by_default = $this->cache_by_default_memory;
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
  protected static function logEvent($message, $severity = ServiceProxy::LOG_INFO, $trace = array()) {
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
  protected static function commitToCache(&$response) {
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
  protected static function queryFromCache(&$request) {
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
    
    return false;
  }
  
  /**
  * Parses an XML string, auto-resolves all namespaces, and returns a SimpleXML
  * document.
  *
  * @param string $xml XML string to parse.
  *
  * @return SimpleXMLElement SimpleXML document.
  */
  protected static function parseXml(&$xml) {
    return $xml = ServiceProxy::resolveXmlNamespaces(
      simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_ERR_NONE | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS | LIBXML_NOCDATA)
    );
  }
  
  /**
  * Auto-resolves all nodes and XML namespaces associated with a SimpleXML document.
  *
  * @param mixed $obj Simple XML document node to begin traversing at.
  *
  * @return mixed[] Array of all nodes in the SimpleXML document.
  */
  protected static function resolveXmlNamespaces(&$obj) {
    if(is_object($obj)) {
      $child_nodes = ServiceProxy::toArray($obj->children(), false);
      
      $namespaces = array_keys($obj->getDocNamespaces(true));
      foreach($namespaces as $ns)
        $child_nodes += ServiceProxy::toArray($obj->children($ns, true), false);
        
      $obj = ServiceProxy::toArray($obj, false) + $child_nodes;
    }
    
    if(is_array($obj)) $obj = array_map("ServiceProxy::resolveXmlNamespaces", $obj);
    return $obj;
  }
  
  /**
  * Recursively converts objects to arrays
  *
  * @param mixed[] $data Object or Array of data to be fully traversed and converted to a multi-dimensional associative array.
  * @param bool $recursive Whether or not the object specified in $data should be fully traversed or if only the first layer should be converted to an Array.
  *
  * @return $mixed[] Array of data
  */
  public static function toArray($data, $recursive = true) {
    if($data instanceof SimpleXMLElement) $data = get_object_vars($data);
    if(is_array($data) && $recursive) $data = array_map("ServiceProxy::toArray", $data);
    
    if(is_array($data) && isset($data['@attributes'])) {
      foreach($data['@attributes'] as $key => &$value)
        $data = array($key => ServiceProxy::toArray($value)) + $data;
      unset($data['@attributes']);
    }
    
    return $data;
  }
  
  /**
  * Converts any associative array to a programmatic object (StdClass)
  *
  * @param mixed[] $data Array to convert to an object.
  * @param bool $recursive Whether or not the array specified in $data should be fully traversed or if only the first layer should be converted to an object.
  *
  * @return object StdClass object representation of associative array data
  */
  public static function toObject($data, $recursive = true) {
    if ($recursive && is_array($data)) return (object) array_map("ServiceProxy::toObject", $data);
    return is_array($data) ? (object) $data : $data;
  }
  
  /**
  * Internal function that sends a request to the service
  *
  * @param string $action
  * @param string $method
  * @param mixed[] $data Data to send in the request to the service
  * @param boolean $async whether to make this call in parallel
  * @param boolean $async_xml whether the response received from an asyncronous request will be XML
  *
  * @return mixed Web service response
  */
  protected function call($action, $method = "GET", &$data = array(), $async = false) {
    if(!is_array($data)) $data = &get_object_vars($data);
    $request = $this->getRequest($action, $method, $data);
    
    // fetch a response using the request
    try {
      $this->httpQueueRequest($request['params']['http']['header'], $request['action']); // send the request
      if(!$async) return $this->runAsync();
    } catch (Exception $e) {
      self::logEvent($e->getMessage(), ServiceProxy::LOG_INFO, $e);
    }
    
    return false;
  }
  
  /**
  * Event handler for implementing status detection.  By default, this function logs anything other than a status "200 OK".
  *
  * @param mixed $response Web service response
  * @param integer $response_status HTTP status code
  *
  * @return void
  */
  protected static function responseStatusCallback(&$response) {
    static $codes = array(
      200 => 1,
      201 => 1,
      202 => 2,
      203 => 2,
      204 => 1,
      
      400 => 0,
      401 => 0,
      402 => 0,
      403 => 0,
      404 => 1,
      
      500 => -1,
      501 => -1,
      502 => -1,
      503 => -1,
    );
    
    // extract status code from response meta data
    if(empty($response['status_code']) || !is_numeric($response['status_code'])) {
      self::logEvent('Unable to retrieve HTTP status code from response.', ServiceProxy::LOG_INFO, $response);
      $response['status_code'] = 500;
    }
  
    if($response['status_code'] == 404) {
      self::logEvent('404: No record found.', ServiceProxy::LOG_INFO);
      $response['header']['content-type'] = 'text/plain';
      $response['body'] = '404';
    }
      
    if($codes[$response['status_code']] == 0)
      self::logEvent('Error ' . $response['status_code'] . '.', ServiceProxy::LOG_WARN, $response);
    elseif($codes[$response['status_code']] == -1)
      self::logEvent('Error ' . $response['status_code'] . '.', ServiceProxy::LOG_ERROR, $response);
      
    if($codes[$response['status_code']] < 1) {
      $response['header']['content-type'] = 'text/plain';
      $response['body'] = $response['status_code'];
      return false;
    } else {
      self::mapResponse($response);
      if(!empty($response['request']))
        self::commitToCache($response);
      return true;
    }
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
  * Returns the web service response as an array
  * @return mixed Web service response
  */
  protected function getResponse() {
    return !empty($this->stream_results) ? $this->stream_results : FALSE;
  }
  
  /**
  * Returns the web service response content type
  * @return string Content type
  */
  protected static function getResponseType(&$response) {
    static $xml_types = array(
      'text/xml',
      'application/atom+xml',
      'application/rss+xml',
      'application/rdf+xml',
    );
    static $json_types = array(
      'application/json',
      'text/javascript',
      'text/json',
      'text/x-json',
    );
    static $value_types = array(
      'text/plain',
    );
    
    $content_type = !empty($response['header']['content-type']) ? $response['header']['content-type'] : 'text/plain';
    if(in_array($content_type, $xml_types)) return 'xml';
    elseif(in_array($content_type, $json_types)) return 'json';
    return 'value';
  }
  
  /**
  * Setter for $response property
  *
  * @param mixed $response Response object to assign to the $response property
  *
  * @return void
  */
  protected static function mapResponse(&$response) {
    switch(self::getResponseType($response)) {
      case 'xml':
        $response['body'] = ServiceProxy::parseXml($response['body']);
        $response['data'] = ServiceProxy::toObject($response['body']);
        break;
      case 'json':
        $response['body'] = json_decode($response['body']);
        $response['data'] = ServiceProxy::toObject($response['body']);
        break;
      case 'value':
        $response['data'] = $response['body'];
        break;
      default:
        self::logEvent('Unable to parse, unrecognized response content type.', ServiceProxy::LOG_INFO);
        return false;
    }
    
    return true;
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
  	if(!empty($this->auth)) $params['http']['header'][] = "Authorization: Basic " . $this->auth; // if authorization is necessary, add it to the header
  	  	
    // build an http query to pass to the service from given object data
    if(!empty($data)) {
      $this->data = $data;
  	  $query = http_build_query($data);
    	if($this->method != "GET") $params['http']['content'] = $query;
    	else $this->action .= '?' . htmlspecialchars_decode($query);
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
   * Configures SSL on a stream or socket context
   *
   * @param context &$context Context to configure SSL on.
   * @param string $cert Raw SSL cert to check against the peer.
   *
   * @return boolean success or fail
   */
  private static function httpConfigureSsl(&$context, $cert = null) {
    $result = stream_context_set_option($context, 'ssl', 'verify_host', true);
    if (isset($cert)) {
      $result = $result && stream_context_set_option($context, 'ssl', 'cafile', $cert);
      $result = $result && stream_context_set_option($context, 'ssl', 'verify_peer', true);
    } else {
      $result = $result && stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
    }
    
    return $result;
  }
  
  private function httpGetRequestHeader($action, $method = 'GET', $data = '', $header_lines = array()) {
    $url = $this->endpoint . $action;
    
    if(!empty($this->data) && empty($data)) $params = $this->data;
    else $params = !empty($this->data) && is_array($this->data) ? $this->data : array();
  
    foreach ($params as $key => &$val) {
      if (is_array($val)) $val = implode(',', $val);
      $post_params[] = $key . '=' . urlencode($val);
    }
    $post_string = implode('&', $post_params);
    unset($post_params, $params);
    
    $parts = parse_url($url); $path = $parts['path'];
    unset($parts);
    
    if($method == 'GET' && !empty($post_string)) $path .= '?' . $post_string;
  
    /*** HEADER ***/
    $req = "$method " . $path . " HTTP/1.0\r\n";
    $req .= "Host: " . $this->host . "\r\n";
    
    // basic or digest authentication
    if ($this->auth_type =='basic' && !empty($auth))
      $req .= "Authorization: Basic " . $this->auth ."\r\n";
    elseif ($this->auth_type == 'digest' && !empty($auth['username'])) {
      $req .= 'Authorization: Digest ';
      foreach ($auth as $k => $v) {
        if (empty($k) || empty($v)) continue;
        if ($k == 'password') continue;
        $req .= $k . '="' . $v . '", ';
      }
      $req .= "\r\n";
    }
    
    foreach($header_lines as $id => $header_entry) $req .= $header_entry . "\r\n";
    
    $method_override = ($method == 'GET' && stripos(implode('', array_values($header_lines)), 'X-HTTP-Method-Override') !== false);
    
    // special override for some web API's.  Google responds to this header.
    if($method_override)
      $req .= "X-HTTP-Method-Override: GET\r\n";
    
    if($method == 'POST')
      $req .= "Content-Type: application/x-www-form-urlencoded\r\n";
    
    if(!empty($post_string) && ($method == 'POST' || $method_override))
      $req .= 'Content-Length: '. strlen($post_string) ."\r\n";
      
    $req .= "Connection: Close\r\n";
    /*** END HEADER ***/
    
    // body of request
    $req .= "\r\n";
    if(!empty($post_string) && ($method == 'POST' || $method_override)) $req .= $post_string;
    
    return $req;
  }
  
  /**
  * Queue's a simple web services socket connection
  */
  private function httpQueueRequest($header = array(), $action = null, $method = '', $data = '', $cert = null) {
    // this object class also holds action/method data, use it if none is passed
    if(!isset($action)) $action = isset($this->action) ? $this->action : '';
    if(empty($method)) $method = isset($this->method) ? $this->method : 'GET';

    // default header extension is empty (in case user changes default to null)
    if(!isset($header)) $header = array();
    
    $server = $this->transport . '://' . $this->host . ':' . $this->port;
    
    // prep transport layer security
    $context = null;
    if($this->transport == 'ssl' || $this->transport == 'tls') {
      $context = stream_context_create();
      if(!ServiceProxy::httpConfigureSsl($context)) return false;
    }
    
    // add leading slash to action if necessary
    if(
      strrpos($this->endpoint, '/') !== strlen($this->endpoint) - 1
      && strpos($action, '/') !== 0
    ) $action = '/' . $action;
    
    // construct header
    $http_message = $this->httpGetRequestHeader($action, $method, $data, $header);
    
    // construct prepared request
    $action_parts = explode('?', $action);
    
    // construct request
    $request = array(
      'message' => $http_message,
      'context' => $context,
      'server' => $server,
      'action' => $action_parts[0],
    );
    
    // CACHING: if cache has a copy, use that one
    if($cached_response = $this->getCachedResponse($request)) {
      // queues the response for reading later rather than queuing a request for sending
      $this->stream_results[]['data'] = $cached_response;
    } else {
      // queues a request to be sent to web services for a round-trip response.
      $this->stream_requests[] = $request;
    }

    return true;
  }
  
  /**
  * After submitting one or more asynchronouse calls, this function must be
  * called to kick off the process.  Standard calls do not require the use of
  * this function.
  */
  protected function runAsync() {
    return $this->httpQueueRun();
  }
  
  protected function asyncWriter() {
    $writables = &$this->stream_writables;
    $readables = &$this->stream_readables;
    $requests = &$this->stream_requests;
    
    while(count($writables) > 0) {
      $stream = &array_shift($writables);
      stream_set_write_buffer($stream, 0);
      
      if(!is_resource($stream)) continue;
      
      // write the message from the necessary resource
      $rid = false;
      foreach($requests as $id => $request) {
        if($stream == $request['stream']) {
          $rid = $id;
          break;
        }
      }
      
      if($rid !== false) {
        $message = $requests[$rid]['message'];
        
        for($bytes_written = 0; $bytes_written < strlen($message); $bytes_written += $fwrite) {
          $fwrite = fwrite($stream, substr($message, $bytes_written));
          if ($fwrite === false || !is_resource($stream)) fclose($stream);
          elseif($fwrite === strlen($message)) array_push($readables, $stream); // mark for reading
        }
      } else unset($this->streams[array_search($stream, $this->streams)]);
    }
  }
  
  /**
  * Initiates queued web services socket connections
  */
  private function httpQueueRun() {
    // clean out used data
    $this->streams = array();
    $this->stream_results = array();
    $this->stream_writables = array();
    $this->stream_readables = array();
    $this->stream_failures = array();
  
    // open socket connections
    foreach($this->stream_requests as $id => &$request) {
      // if there's no cached copy, queue up a stream
      if(!empty($request['context'])) array_push($this->stream_writables, stream_socket_client($request['server'], $errno, $errstr, $this->timeout, STREAM_CLIENT_ASYNC_CONNECT|STREAM_CLIENT_CONNECT, $request['context']));
      else array_push($this->stream_writables, stream_socket_client($request['server'], $errno, $errstr, $this->timeout, STREAM_CLIENT_ASYNC_CONNECT|STREAM_CLIENT_CONNECT));

      $sid = count($this->stream_writables) - 1;
      $request['stream'] =& $this->stream_writables[$sid]; // request can keep track of it's own stream
      array_push($this->streams, $request['stream']); // register stream for processing
      
      // convenience resource
      $stream = $this->stream_writables[$sid];
      
      // a false stream is a naughty stream
      if(!is_resource($stream)) {
        fclose(array_pop($this->stream_writables[$sid]));
        array_pop($this->streams);
        
        // TODO: error detection
        continue;
      }
      
      // activate as a non-blocking stream
      for($trials = 2; !stream_set_blocking($stream, 0) && $trials > 0; $trials--) usleep(200000);
      
      // discard if unable to release blocking
      if($trials < 1 || $stream === false) {
        array_push($this->stream_failures, array_pop($this->streams));
        array_pop($this->stream_writables);
        continue;
      }
    }
    
    $this->asyncWriter();
    
    while($response = $this->httpPollStreams()) {
      // process the HTTP callback for this response
      self::responseStatusCallback($response);
    
      // keep the action together near top level of response so reader
      // can have a way of tagging which service responses go to which
      // group of requests.
      $response['action'] = $response['request']['action'];
      $this->stream_results[$response['pid']] = $response;
      
      // unset internal-use vars
      unset(
        $this->stream_results[$response['pid']]['body'],
        $this->stream_results[$response['pid']]['request'],
        $this->stream_results[$response['pid']]['http_version'],
        $this->stream_results[$response['pid']]['pid']
      );
      
      // top-level array vars are be turned into an object to match the rest of the schema
      $this->stream_results[$response['pid']] = (object) $this->stream_results[$response['pid']];
      unset($response);
    }
    
    $this->stream_requests = array();
    return $this->stream_results;
  }
  
  /**
  * Waits for streams to return with data and processes them when they do.
  */
  private function httpPollStreams() {
    // if there aren't any active streams, poll the result set for anything in need of processing
    if(count($this->streams) < 1) return next($this->stream_results);
    
    while (count($this->streams)) {
      $read = $this->stream_readables;
      if(0 === @stream_select($read, $w = null, $e = null, 1)) continue;
      
      foreach ($read as $r) {
        if($r === false) continue;
      
        $id = array_search($r, $this->streams);
        $sid = array_search($r, $this->stream_readables);
        if(!isset($this->stream_results["$id"])) $this->stream_results["$id"] = array(
          'http_version' => 0,
          'status_code' => 0,
          'status' => '',
          'header' => array(),
          'body' => '',
          'data' => null,
          'pid' => "$id",
          'request' => array(),
        );
        
        if (feof($r)) {
          if(self::httpGetMessageParts($this->stream_results["$id"])) {
            foreach($this->stream_requests as $rid => $request) if($this->streams[$id] == $request['stream'])
              $this->stream_results["$id"]['request'] = $request;
          }
          
          fclose($r);
          unset($this->stream_readables[$sid]);
          unset($this->streams[$id]);
        } else {
          $this->stream_results["$id"]['body'] .= fread($r, 4096);
        }
      }
    }
    
    return current($this->stream_results);
  }
  
  /**
  * Breaks a raw HTTP response into header and body data.  Header
  * data gets recorded as meta data.
  *
  * @param string &$message Full unbroken HTTP packet.
  *
  * @return mixed[] Associative array of status, header, and body info
  */
  protected static function httpGetMessageParts(&$message) {
    if(!empty($message['header'])) return true;
  
    $parts = explode("\r\n\r\n", $message['body'], 2);
    if(count($parts) < 2) return $message;
    
    // header
    $header = array();
    $header_lines = explode("\r\n", trim($parts[0]));
    $status_line = array_shift($header_lines);
    
    foreach($header_lines as $id => $line) {
      $line = explode(':', $line, 2);
      if(count($line) == 2)
        $header[strtolower(trim($line[0]))] = trim($line[1]);
    }
    
    // body
    $body = $parts[1]; unset($parts);
    self::httpDecodeChunked($body); // dechunk if necessary
    
    if(array_key_exists('content-length', $header)) {
      $read_length = intval($header['content-length']);
      
      if(0 <= $read_pos = strlen($body) - $read_length) {
        $read_pos = strlen($body) - $read_length;      
        $body = substr($body, $read_pos, $read_length);
      }
    }
    $body = trim(trim($body), "0\r\n");
    
    // ensure well-formed XML on front and back of body section
    if(strpos($header['content-type'], 'xml') !== false) {
      $body = substr($body, stripos($body, '<?xml'));
      $body = substr($body, 0, strrpos($body, '>') + 1);
    }
    
    // status, version
    $status = explode(' ', $status_line, 3);
    $status[0] = str_ireplace('http/', '', $status[0]);
    
    // set the values of the message array
    $message['http_version'] = $status[0];
    $message['status_code'] = intval($status[1]);
    $message['status'] = $status[2];
    $message['header'] = $header;
    $message['body'] = $body;
    
    return true;
  }
  
  /**
  * Decodes a chunked HTTP response.
  *
  * @param string $message Chunked response message to decode
  *
  * @return string Decoded HTTP response
  */
  protected static function httpDecodeChunked(&$message) {
    // unchunk if necessary
    $has_transfer_encoding = stripos($message, 'transfer-encoding:');
    $has_chunked = stripos($message, 'chunked');
    if((($has_transfer_encoding && $has_chunked) === false) || $has_chunked - $has_transfer_encoding > 25 || $has_chunked - $has_transfer_encoding < 0)
      return $message;
    
    // drop duplicate line feeds
    $parts = explode("\r\n\r\n", $message, 2);
    while(strpos($parts[1], "\r\n\r\n"))
      $parts[1] = str_replace("\r\n\r\n", "\r\n", $parts[1]);
    
    for ($res = ''; !empty($parts[1]); $parts[1] = trim($parts[1])) {
      $pos = strpos($parts[1], "\r\n");
      $len = hexdec(substr($parts[1], 0, $pos));
      $res .= substr($parts[1], $pos + 2, $len);
      $parts[1] = substr($parts[1], $pos + 2 + $len);
    } $parts[1] = $res;
    
    // recombine result set
    $message = implode("\r\n\r\n", $parts);
    return true;
  }
}

?>
