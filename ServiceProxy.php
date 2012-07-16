<?php

/**
* ServiceProxy provides a basic interface to a web service without relying on the cURL library.
* To implement logging, override the logEventHandler($message, $severity, $trace) function.
*   Severities: 1 = Notice, 2 = Warning, 3 = Error, 4 = Fatal
*
* @author Aaron Belovsky
*  http://www.github.com/ambelovsky/PHP-Service-Proxy
*  http://www.aaronbelovsky.com
*/
abstract class ServiceProxy {
  //const LOG_INFO = 1;
  //const LOG_WARN = 2;
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
   * Authentication information for the service
   */
  private $auth;
  
  /**
   * Stored data from the most recent request
   */
  private $data = null;
  
  /**
   * Stored response from the most recent request
   */
  private $response;
  
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
  * Event handler for implementing logging support
  *
  * @param string $message Primary log message
  * @param integer $severity Severity of the issue that is being logged
  * @param mixed $trace Generally intended to be an Array or Exception type, $trace can
  *  hold any additionally pertinent logging information.
  *
  * @return void
  */
  protected function logEventHandler($message, $severity, $trace) {
    // override this function to provide logging support
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
  private function call(&$action, &$method = "GET", &$data = array()) {
    $this->action = $action;
    $this->method = $method;
    
  	$params = array('http' => array('method' => $this->method)); // request configuration
  	
  	if(!empty($this->user_agent)) $params['http']['user_agent'] = $this->user_agent; // declare non-standard user agent
  	if(!empty($this->header)) $params['http']['header'] = $this->header; // build non-standard header
  	if(isset($this->auth) && !empty($this->auth)) $params['http']['header'][] = "Authorization: Basic " . $this->auth; // if authorization is necessary, add it to the header
  	
    // build an http query to pass to the service from given object data
    if(!empty($data)) {
      $this->data &= $data;
  	  $query = http_build_query($data);
    	if($this->method != "GET") $params['http']['content'] = $query;
    	else $this->endpoint .= $query;
    }
  	
  	try {
  	  $context = stream_context_create($params); // create the stream
  	  $fileStream = @fopen($this->endpoint . $this->action, 'rb', false, $context); // send the request
  	  $this->response = @stream_get_contents($fileStream); // get results
  	} catch (Exception $e) {
  	  $this->logEventHandler($e->getMessage(), ServiceProxy::LOG_ERROR, $e);
  	}
  	
  	return $this->response;
  }
  
  /**
  * Sets authentication information for a web service.  Must be set prior to sending any requests.
  *
  * @param string $username
  * @param string $password
  *
  * @return void
  */
  protected function authenticate($username, $password = "") {
    $this->auth = base64_encode($username . ':' . $password);
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
  protected function callXml(&$action, &$method = "GET", &$data = array()) {
    $this->header = array_merge($this->header, array('Accept: text/xml'));
    $this->call($action, $method, $data);
    
    try {
      $response = new SimpleXmlElement($this->getResponse());
    } catch (Exception $e) {
      $this->logEventHandler($e->getMessage(), ServiceProxy::LOG_ERROR, $e);
    }
    
  	return $response;
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
}

?>
