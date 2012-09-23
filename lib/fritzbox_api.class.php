<?php
/**
 * Fritz!Box API - A simple wrapper for automatted changes in the Fritz!Box Web-UI
 * 
 * handles the new secured login/session system and implements a cURL wrapper
 * new in v0.2: Can handle remote config mode via https://example.dyndns.org
 * new in v0.3: New method doGetRequest handles GET-requests
 * new in v0.4: Added support for the new .lua forms like the WLAN guest access settings
 * 
 * @author   Gregor Nathanael Meyer <Gregor [at] der-meyer.de>
 * @license  http://creativecommons.org/licenses/by-sa/3.0/de/ Creative Commons cc-by-sa
 * @version  0.4 2011-05-15
 * @package  Fritz!Box PHP tools
 */

/* A simple usage example
 *
 * require_once('fritzbox_api.class.php');
 * try
 * {
 *   $fritz = new fritzbox_api('password', 'fritz-box');
 *   $formfields = array(
 *     'getpage'                  => '../html/de/menus/menu2.html', // the getpage parameter is mandatory
 *     'tam:settings/TAM0/Active' => 1, // enables the first answering machine, any POST-field from the Web-UI can be used
 *   );
 *   $fritz->doPostForm($formfields);   // send the command
 *   $fritz = null;                     // destroy the object to log out
 * }
 * catch (Exception $e)
 * {
 *   echo $e->getMessage();             // schow the error message in anything failed
 * }
 *
 */
 
/**
 * the main Fritz!Box API class
 *
 */
class fritzbox_api {
  /**
    * @var  string  the Fritz!Box password, set by the constructor
    */
  protected $password;
  
  /**
    * @var  bool    enable remote config mode, set by the constructor
    */
  protected $enable_remote_config;
  
  /**
    * @var  string  username for remote config mode, set by the constructor
    */
  protected $remote_config_user;
  
  /**
    * @var  string  password for remote config mode, set by the constructor
    */
  protected $remote_config_password;
  
  /**
    * @var  string  the Fritz!Box base URL, set by the constructor
    */
  protected $fritzbox_url;
  
  /**
    * @var  string  the session ID, set by method initSID() after login
    */
  protected $sid = '0000000000000000';
  
  
  /**
    * the constructor, initializes the object and calls the login method
    * 
    * @access public
    * @param  string $password                the Fritz!Box password, optional, defaults to null
    * @param  string $fritzbox_ip             the Fritz!Box IP address or DNS name, optional, defaults to fritz.box
    * @param  bool   $enable_remote_config    set true to enable remote config, optional, defaults to false
    * @param  string $remote_config_user      the remote config username, mandatory, if remote config is used
    * @param  string $remote_config_password  the remote config password, mandatory, if remote config is used
    */
  public function __construct($password = null, $fritzbox_ip = 'fritz.box', $enable_remote_config = false, $remote_config_user = null, $remote_config_password = null)
  {
    $this->password = $password;
    
    if ( $enable_remote_config === true )
    {
      if ( !isset($remote_config_user) || !isset($remote_config_password) )
      {
        $this->error('ERROR: Remote config mode enabled, but no username or no password provided');
      }
      $this->fritzbox_url            = 'https://' . $fritzbox_ip;
      $this->enable_remote_config    = true;
      $this->remote_config_user      = $remote_config_user;
      $this->remote_config_password  = $remote_config_password;
    }
    else
    {
      $this->fritzbox_url            = 'http://' . $fritzbox_ip;
      $this->enable_remote_config    = false;
      $this->remote_config_user      = null;
      $this->remote_config_password  = null;
    }
    
    $this->sid = $this->initSID();
  }
  
  
  /**
    * the destructor just calls the logout method
    * 
    * @access public
    */
  public function __destruct()
  {
    $this->logout();
  }
  
  
  /**
    * do a POST request on the box
    * the main cURL wrapper handles the command
    * 
    * @access public
    * @param  array  $formfields    an associative array with the POST fields to pass
    * @return string                the raw HTML code returned by the Fritz!Box
    */
  public function doPostForm($formfields = array())
  {  
    $ch = curl_init();
    if ( strpos($formfields['getpage'], '.lua') > 0 )
    {
      curl_setopt($ch, CURLOPT_URL, $this->fritzbox_url . $formfields['getpage'] . '?sid=' . $this->sid);
      unset($formfields['getpage']);
    }
    else
    {
      // add the sid, if it is already set
      if ($this->sid != '0000000000000000')
      {
        $formfields['sid'] = $this->sid;
      }   
      curl_setopt($ch, CURLOPT_URL, $this->fritzbox_url . '/cgi-bin/webcm');
    }
    curl_setopt($ch, CURLOPT_POST, 1);
    if ( $this->enable_remote_config )
    {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_USERPWD, $this->remote_config_user . ':' . $this->remote_config_password);
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formfields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
  }
  
    /**
    * upload a file to the box (via POST) [all files are uploaded to the 'firmwarecfg' cgi-program] 
    * the main cURL wrapper handles the command
    * 
    * @access public
    * @param  array  $formfields    an associative array with the POST fields to pass
    * @param  array  $filefileds    an associative array with the file data (key: content) and type (key: type)
    * @return string                the raw HTML code returned by the Fritz!Box
    */
  public function doPostFile($formfields = array(), $filefileds = array())
  {  
    $ch = curl_init();
   
    // add the sid, if it is already set
    if ($this->sid != '0000000000000000')
    {
		// 'sid' MUST be the first POST variable!!! (otherwise it will not work!!)
		// therfore we use array_merge to ensuere the foreach outputs 'sid' fist
		$formfields = array_merge(array('sid' => $this->sid), $formfields);
		//$formfields['sid'] = $this->sid;
    }   
    curl_setopt($ch, CURLOPT_URL, $this->fritzbox_url . '/cgi-bin/firmwarecfg'); 
    curl_setopt($ch, CURLOPT_POST, 1);
    
    // remote config?
    if ( $this->enable_remote_config )
    {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_USERPWD, $this->remote_config_user . ':' . $this->remote_config_password);
    }
    
    // enable for debugging:
    //curl_setopt($ch, CURLOPT_VERBOSE, TRUE); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    // if filefileds not specified ('@/path/to/file.xml;type=text/xml' works fine)
    if(empty( $filefileds )) {
		curl_setopt($ch, CURLOPT_POSTFIELDS, $formfields); // http_build_query
	} 
	// post calculated raw data
	else {
		$header = $this->_create_custom_file_post_header($formfields, $filefileds);
		curl_setopt($ch, CURLOPT_HTTPHEADER , array(
			'Content-Type: multipart/form-data; boundary=' . $header['delimiter'], 'Content-Length: ' . strlen($header['data']) )
			);
			
		curl_setopt($ch, CURLOPT_POSTFIELDS, $header['data']);		
	}
	
    $output = curl_exec($ch);

	// curl error
	if(curl_errno($ch)) {
		$this->error(curl_error($ch)." (".curl_errno($ch).")");
	}

    // finger out an error message, if given
    preg_match('@<p class="ErrorMsg">(.*?)</p>@is', $output, $matches);
    if (isset($matches[1]))
    {
		$this->error(str_replace('&nbsp;', ' ', $matches[1]));
    }

    curl_close($ch);
    return $output;
  }
  
  
  private function _create_custom_file_post_header($postFields, $fileFields) {
		// form field separator
		$delimiter = '-------------' . uniqid();
		
		/*
		// file upload fields: name => array(type=>'mime/type',content=>'raw data')
		$fileFields = array(
			'file1' => array(
				'type' => 'text/xml',
				'content' => '...your raw file content goes here...',
				'filename' = 'filename.xml'
			),
		);
		// all other fields (not file upload): name => value
		$postFields = array(
			'otherformfield'   => 'content of otherformfield is this text',
		);
		*/
		
		$data = '';

		// populate normal fields first (simpler)
		foreach ($postFields as $name => $content) {
		   $data .= "--" . $delimiter . "\r\n";
			$data .= 'Content-Disposition: form-data; name="' . urlencode($name) . '"';
			$data .= "\r\n\r\n";
			$data .= $content;
			$data .= "\r\n";
		}
		// populate file fields
		foreach ($fileFields as $name => $file) {
			$data .= "--" . $delimiter . "\r\n";
			// "filename" attribute is not essential; server-side scripts may use it
			$data .= 'Content-Disposition: form-data; name="' . urlencode($name) . '";' .
					 ' filename="' . $file['filename'] . '"' . "\r\n";
					 
			//$data .= 'Content-Transfer-Encoding: binary'."\r\n";
			// this is, again, informative only; good practice to include though
			$data .= 'Content-Type: ' . $file['type'] . "\r\n";
			// this endline must be here to indicate end of headers
			$data .= "\r\n";
			// the file itself (note: there's no encoding of any kind)
			$data .= $file['content'] . "\r\n";
		}
		// last delimiter
		$data .= "--" . $delimiter . "--\r\n";
	
		return array('delimiter' => $delimiter, 'data' => $data);
	}
  
  
  
  /**
    * do a GET request on the box
    * the main cURL wrapper handles the command
    * 
    * @access public
    * @param  array  $params    an associative array with the GET params to pass
    * @return string            the raw HTML code returned by the Fritz!Box
    */
  public function doGetRequest($params = array())
  {
    // add the sid, if it is already set
    if ($this->sid != '0000000000000000')
    {
      $params['sid'] = $this->sid;
    }    
  
    $ch = curl_init();
    if ( strpos($params['getpage'], '.lua') > 0 )
    {
      $getpage = $params['getpage'] . '?';
      unset($params['getpage']);
    }
    else
    {
      $getpage = '/cgi-bin/webcm?';
    }
    curl_setopt($ch, CURLOPT_URL, $this->fritzbox_url . $getpage . http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPGET, 1);
    if ( $this->enable_remote_config )
    {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_USERPWD, $this->remote_config_user . ':' . $this->remote_config_password);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
  }
  
  
  /**
    * the login method, handles the secured login-process
    * newer firmwares (xx.04.74 and newer) need a challenge-response mechanism to prevent Cross-Site Request Forgery attacks
    * see http://www.avm.de/de/Extern/Technical_Note_Session_ID.pdf for details
    * 
    * @access protected
    * @return string                a valid SID, if the login was successful, otherwise throws an Exception with an error message
    */
  protected function initSID()
  {
    // read the current status
    $ch = curl_init($this->fritzbox_url . '/cgi-bin/webcm?getpage=../html/login_sid.xml');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if ( $this->enable_remote_config )
    {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_USERPWD, $this->remote_config_user . ':' . $this->remote_config_password);
    }
    $output = curl_exec($ch);
    curl_close($ch);
    $session_status_simplexml = simplexml_load_string($output);
    
    // perhaps we already have a SID (i.e. when no password is set)
    if ($session_status_simplexml->iswriteaccess == 1)
    {
      return $session_status_simplexml->SID;
    }
    // we have to login and get a new SID
    else
    {
      // the challenge-response magic, pay attention to the mb_convert_encoding()
      $challenge = $session_status_simplexml->Challenge;
      $response = $challenge . '-' . md5(mb_convert_encoding($challenge . '-' . $this->password, "UCS-2LE", "UTF-8"));
      
      // do the login
      $formfields = array(
        'getpage'                => '../html/de/menus/menu2.html',
        'login:command/response' => $response,
      );
      $output = $this->doPostForm($formfields);
      
      // finger out an error message, if given
      preg_match('@<p class="errorMessage">(.*?)</p>@is', $output, $matches);
      if (isset($matches[1]))
      {
        $this->error(str_replace('&nbsp;', ' ', $matches[1]));
      }
      
      // finger out the SID from the response
      preg_match('@<input type="hidden" name="sid" value="([A-Fa-f0-9]{16})" id="uiPostSid">@i', $output, $matches);
      if (isset($matches[1]) && $matches[1] != '0000000000000000')
      {
        return $matches[1];
      }
      else
      {
        $this->error('ERROR: Login failed with an unknown response');
      }
    }
  }
  
  
  /**
    * the logout method just sends a logout command to the Fritz!Box
    * 
    * @access protected
    */
  protected function logout()
  {
    $formfields = array(
      'getpage'                 => '../html/de/menus/menu2.html',
      'security:command/logout' => 'logout',
    );
    $this->doPostForm($formfields);
  }
  
  
  /**
    * the error method just throws an Exception
    * 
    * @access protected
    * @param  string   $message     an error message for the Exception
    */
  protected function error($message = null)
  {
    throw new Exception("ERROR: ".$message."\n");
  }
  
  
  /**
    * a getter for the session ID
    * 
    * @access public
    * @return string                $this->sid
    */
  public function getSID()
  {
    return $this->sid;
  }
}
