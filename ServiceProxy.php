<?php

/**
* ServiceProxy provides a basic interface to a web service without relying on the cURL library.
* @author http://www.aaronbelovsky.com
*/
class ServiceProxy {
  public $endpoint;            // base service web url
  public $action;              // service function to call
  public $method;              // method of request "GET", "POST"
  public $header;              // header to send with the request
  public $user_agent;          // custom user agent name to send the request as
  
  private $auth;               // authentication information for the service
  private $data = null;        // stored data from the most recent request
  private $response;           // stored response from the most recent request
  
  /**
  * Constructor
  * @return ServiceProxy instance of service proxy
  */
  function __construct($endpoint="http://www.example.com", $action="", $method="POST", $header = array(), $user_agent = "") {
    $this->endpoint = $endpoint;
    $this->action = $action;
    $this->method = $method;
    $this->header = $header;
    $this->user_agent = $user_agent;
  }
  
  /**
  * Sets authentication information for a web service.  Must be set prior to sending any requests.
  */
  function authenticate($username, $password = "") {
    $this->auth = base64_encode($username . ':' . $password);
    return true;
  }
  
  /**
  * Send a request to the service
  * @return mixed web service response
  */
  function request($data = array()) {
    $endpoint = $this->endpoint;
  
    // request configuration
  	$params = array('http' => array(
  		'method' => $this->method
  	));
  	
  	// declare non-standard user agent
  	if(!empty($this->user_agent)) $params['http']['user_agent'] = $this->user_agent;
  	
  	// build non-standard header
  	if(!empty($this->header)) $params['http']['header'] = $this->header;
  	
  	// if authorization is necessary, add it to the header
  	if(isset($this->auth) && !empty($this->auth)) $params['http']['header'][] = "Authorization: Basic " . $this->auth;
  	
	  // build an http query to pass to the service from given object data
	  if(!empty($data)) {
	    $this->data &= $data;
  	  $query = http_build_query($data);
    	if($this->method != "GET") $params['http']['content'] = $query;
    	else $endpoint .= $query;
    }
  	
  	// create the stream
  	$context = stream_context_create($params);
  	
  	// send the request
  	$fileStream = @fopen($endpoint . $this->action, 'rb', false, $context);
  	
  	// get and return results
  	$this->response = @stream_get_contents($fileStream);
  	return $this->response;
  }
  
  /**
  * Resubmits the last request
  * @return mixed web service response
  */
  function retry() {
    return $this->request($this->data);
  }
  
  /**
  * Returns the read-only response
  * @return mixed web service response
  */
  function getResponse() {
    return $this->response;
  }
}

?>
