<?php
/* ************************************************************************
** $Id: tractionAddCustomer.inc.php,v 1.1 2007/03/19 07:08:07 beng Exp $
**
** Copyright (c) 2000-2007 MassMedia Studios Pty Ltd.
** 68-72 Wentworth Ave, Surry Hills, NSW 2010, Australia.
** All rights reserved.
**
** This software is the confidential and proprietary information of 
** MassMedia Studios Pty Ltd. ("Confidential Information").  You shall not
** disclose such Confidential Information and shall use it only in
** accordance with the terms of the license agreement you entered into
** with MassMedia Studios Pty Ltd.
** ------------------------------------------------------------------------
** Author:			Ben Gillies --> ben.gillies at massmedia.com.au
** Required:		PHP version 5+
** Comments:		Sends POST HTTP request to Traction Add Customer API 
**					by MassMedia Studios.
************************************************************************ */

class tractionAddCustomer {
	private $endPointId;
	private $userId;
	private $passWord;
	private $replyUrl;
	private $customer;
	private $delimiter;
	private $tractionUrl;
	
	public function tractionAddCustomer($myTractionUrl) {
	/* Initialise.
	*/
		/*$this->tractionUrl = "http://traction.massmedia.com.au/traction/external/S2FS";  // traction url.*/
		$this->tractionUrl = $myTractionUrl;  // traction url.
		$this->delimiter = chr(31);  // delimiter for customer attribute values.
	}
	
	public function setLoginDetails($userId, $password, $endPointId) {
	/* Sets up the account's login details and for the account which this object is posting to in Traction.
	** Parameters: userId, passWord, and endPointId are required for successful login.
	*/
		$this->userId = $userId;  //required.
		$this->passWord = $password;  //required.
		$this->endPointId = $endPointId;  //required.
	}

	public function addCustomer($firstName, $lastName, $title, $email, $mobile, $extUserId, $active, $emailOpt, $smsOpt, $password, $customParams) {
	/* Add a customer to the post request object.
	** Format customer string into what traction is expecting.
	** $firstName, $lastName, $email parameters are required.
	*/
		$myCustomer = "EMAIL|" . $email;
		if (!is_null($firstName) && $firstName != "")	$myCustomer .= $this->delimiter . "FIRSTNAME|" . $firstName;
		if (!is_null($lastName) && $lastName != "")		$myCustomer .= $this->delimiter . "LASTNAME|" . $lastName;
		if (!is_null($title) && $title != "")			$myCustomer .= $this->delimiter . "TITLE|" . $title;
		if (!is_null($mobile) && $mobile != "")			$myCustomer .= $this->delimiter . "MOBILE|" . $mobile;
		if (!is_null($extUserId) && $extUserId != "")	$myCustomer .= $this->delimiter . "EXTUSERID|" . $extUserId;
		if (!is_null($active) && $active != "")			$myCustomer .= $this->delimiter . "ACTIVE|" . $active;
		if (!is_null($emailOpt) && $emailOpt != "")		$myCustomer .= $this->delimiter . "EMAILOPT|" . $emailOpt;
		if (!is_null($smsOpt) && $smsOpt != "")			$myCustomer .= $this->delimiter . "SMSOPT|" . $smsOpt;
		if (!is_null($password) && $password != "")		$myCustomer .= $this->delimiter . "PASSWORD|" . $password;
		if (!is_null($customParams) && is_array($customParams)) {
			foreach($customParams as $key => $val) {
				$myCustomer .= $this->delimiter . $key . "|" . $val;
			}
		}
		
		$this->customer = $myCustomer;  // add to customer array.
	}
	
	public function getCustomer() {
	/* Returns the customer string of the customer.
	*/
		return $this->customer;
	}
	
	public function setReplyUrl($myValue) {
		$this->replyUrl = $myValue;
	}
	
	public function setTractionUrl($myValue) {
		$this->tractionUrl = $myValue;
	}
	
	public function setUser($matchKey, $matchValue) {
		switch ($matchKey) {
			case 'email':
				$this->matchKey = "E";
				break;
			case '': /* for later expansion */
				
				break;
			default:
				$this->matchKey = "E";
		}
		$this->matchValue = $matchValue;
	}

	private function getPostVars() {
	/* Private method to build array of variable fields to post to Traction.
	*/
		// build data array containing variables to post..
		$myDataArray = array(
				"USERID"		=>	$this->userId,
				"PASSWORD"		=>	$this->passWord,
				"ENDPOINTID"	=>	$this->endPointId,
				"REPLYURL"		=>	$this->replyUrl, 
				"MATCHKEY"		=>	$this->matchKey,
				"MATCHVALUE"	=>	$this->matchValue
			);
		if (!is_null($this->customer)) $myDataArray["CUSTOMER"] = $this->customer;
		
		return $myDataArray;
	}
	
	public function sendToTraction() {
	/* Public method for sending the post vars to Traction.
	** Returns an array of 2 elements:
	**    0. true/false indication of if socket connection successful or unsuccessful.
	**    1. array containing error code, error messages and other trac headers from traction response.
	*/
		// compile post variables to send to traction..
		$myDataArray = $this->getPostVars();

		// post data array and retrieve the result..
		//trigger_error("Sending Traction Add Customer API request", E_USER_NOTICE);
		$myResult = $this->httpPost($myDataArray, $this->tractionUrl);
		
		// interpret the result..
		if (isset($myResult["errno"])) {
			// socket error occurred..
			return array(false, array("RESULT" => $myResult["errno"], "ERROR" => $myResult["errstr"]));
		} else {
			// interpret traction header response..
			$found = false;
			$myResultErrorCode = 999;
			$myResultErrorStr = "";
			$tempArray = array();
			$myReg = '/^TRAC\-([^:]+):(.*)$/';
			$responseHeadersArray = array();
			foreach ($myResult as $header) {
				preg_match($myReg, $header, $tempArray);
				if (count($tempArray) == 3) {
					$responseHeadersArray{$tempArray[1]} = trim($tempArray[2]);
				}
			}
			if (!isset($responseHeadersArray["RESULT"])) {
				// did not find result in response, so exit..
				trigger_error("Failed to find result in Traction Promotion API response: " . join("", $myResult), E_USER_NOTICE);
				return array(true, array("RESULT" => "999", "ERROR" => "Failed to find result in Traction Promotion API response"));
			} else {
				return array(true, $responseHeadersArray);
			}
		}
	}

	private function httpPost($myDataArray, $myUrl) {
	/* Private method used to send request and retrieve response from Traction. */
		// get url parts..
		$default_port = "";
		$url = "";
		if (preg_match("/^http:\/\//", $myUrl))	{
			//echo "here";
			$default_port = 80;
			$url = preg_replace("@^http://@i", "", $myUrl);  // remove transport protocol
		}
		else if (preg_match("/^https:\/\//", $myUrl))	{
			//echo "here too";
			$default_port = 443;
			$url = preg_replace("@^https://@i", "", $myUrl);  // remove transport protocol
		}
		
		$host = substr($url, 0, strpos($url, "/"));  // host of web application
		$uri = strstr($url, "/");  // path for web application
		$port = (int) substr($uri, strpos($uri, ":") + 1);  // get port from myUrl.
		if (!($port > 0))	{
			$port = $default_port;
		}
		
		//create request body..
		$myRequestBody = "";
		foreach ($myDataArray as $key => $val) {
			if (!empty($myRequestBody))
				$myRequestBody .= "&";
			$myRequestBody .= $key . "=" . urlencode($val);
		}
		$myContentLength = strlen($myRequestBody);
		// create request header..
		$myRequestHeader = "POST " . $uri . " HTTP/1.0 \r\n";
		$myRequestHeader .= "Host: " . $host . "\r\n";
		$myRequestHeader .= "User-Agent: VML_Traction_Client\r\n";
		$myRequestHeader .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$myRequestHeader .= "Content-Length: " . $myContentLength . "\r\n\r\n";
		$myRequestHeader .= $myRequestBody . "\r\n";
		
		//connect to server..
		if ($port == 443)	{
			$socket = fsockopen("ssl://".$host, $port, $errno, $errstr);
		}
		else	{
			$socket = fsockopen($host, $port, $errno, $errstr);
		}
		
		if (!$socket) {
			// socket failed, return error details..
			$result["errno"] = $errno;
			$result["errstr"] = $errstr;
			return $result;
		}
		
		// pass data through socket..
		fputs($socket, $myRequestHeader);
		
		$result = "";
		while (!feof($socket)) {
			// get result..
			$result[] = fgets($socket, 4096);
		}
		fclose($socket); // close socket.
		
		return $result; // return successful socket result
	}
	
} // end class.
?>
