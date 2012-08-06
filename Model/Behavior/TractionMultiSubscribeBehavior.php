<?php
// Manually load external class file
require(dirname(__FILE__) . '/../../Vendor/tractionMultiSubscribe.inc.php');

class TractionMultiSubscribeBehavior extends ModelBehavior {
	protected $_defaults = array(
			"traction_url"					=> 	"http://api.tractionplatform.com/ext/",				// traction live api base url
			"traction_multiSubscribe_url"	=> 	"MultiSubscribe",										// multisubscribe api uri			
			"traction_uid"					=>	"To be defined", 									// traction username
			"traction_pwd"					=>	"To be defined", 									// traction password
			"traction_ep"					=>	"To be defined", 									// web end point id
			"reply_url"						=>	'To be defined',
			
			//
			'default_mapping_fields'		=>	array(				
													// Traction			=>  CakePHP Field
													'firstName'			=> 	'first_name',
													'lastName'			=> 	'last_name',
													'title'				=> 	'title',
													'email'				=> 	'email',
													'mobile'			=> 	'mobile',
													'extUserId'			=> 	'ext_user_id',
													'active'			=> 	'active',
													'emailOpt'			=> 	'email_opt',
													'smsOpt'			=> 	'sms_opt',
													'password'			=> 	'password',
			),
			'custom_params_fields'					=> array(
													/*
													array( CakePHP Field Name 	=> Traction Field ID),
													..
													..
													
													*/
			),
			
	);
	
		
	/**
	 * Setup
	 * @see ModelBehavior::setup()
	 */
	public function setup(Model $Model, $config = array()) {

		if (isset($config[0])) {
			$config['type'] = $config[0];
			unset($config[0]);
		}
		$settings = array_merge($this->_defaults, $config);
		$this->settings[$Model->alias] = $settings;
		
	}
	
	private function getTractionObject(Model $Model, $tractionData){
		
		$multiSubObj = new tractionMultiSubscribe($this->settings[$Model->alias]['traction_url'] . $this->settings[$Model->alias]['traction_multiSubscribe_url']);
		$multiSubObj->setLoginDetails($this->settings[$Model->alias]['traction_uid'], $this->settings[$Model->alias]['traction_pwd'], $this->settings[$Model->alias]['traction_ep']);
		
		//$multiSubObj->setUser("email", $tractionData['email']);
		$multiSubObj->setUser("emailAddress", $tractionData['email']);
		$multiSubObj->setReplyUrl($this->settings[$Model->alias]['reply_url']);
		$multiSubObj->setSendReplySms(false);
		
		
		$multiSubObj->addCustomer(
				$tractionData['firstName'] 				// firstname
				,	$tractionData['lastName'] 			// lastname
				,	$tractionData['title']				// title
				,	$tractionData['email']				// email
				,	$tractionData['mobile']				// mobile
				,	$tractionData['extUserId']			// extUserId
				,	NULL								// active
				,	$tractionData['emailOpt']			// emailOpt
				,	$tractionData['smsOpt']				// smsOpt
				,	$tractionData['password']			// password
				,	$tractionData['customAttributeArray']
		);
		
		//debug($tractionData);
		//debug($this->settings[$Model->alias]);die();
		
		// Add subscription data
		foreach($tractionData['mailingLists']  as $mailingListID){
			$multiSubObj->addSubscription($mailingListID, 'S'); // unsubscribe from this subscription
		}
		//debug($multiSubObj);die();
		return $multiSubObj;
	} 
	
	
	/**
	 * 
	 * @param Model $Model
	 * @param unknown_type $data
	 */
	public function tractionSubscribe(Model $Model, $data){
		
		// Prepare data
		$tractionData = $this->mapData($Model, $data);
		
		// Get object
		$multiSubObj = $this->getTractionObject($Model, $tractionData);
				
		// Make a request
		$result = $multiSubObj->sendSubToTraction();
		
		$result = $this->parseResult($result, $tractionData);
		return $result;
	}
	
	
	
	/**
	 * 
	 * @param Model $Model
	 * @param unknown_type $data
	 * @return multitype:NULL
	 */
	public function mapData(Model $Model, $data){
		$modelAlias = $Model->alias;
		$tractionData = array();
		if (empty($data)){
			debug('No data available');
		}
		
		
		// Step 1: Default Customer Fields
		$default_fields = $this->settings[$modelAlias]['default_mapping_fields'];
		foreach($default_fields as $tractionFieldName=>$cakePHPFieldName){
			
			// Default data is null
			$tractionData[$tractionFieldName] = NULL;
			if (isset($data[$modelAlias][$cakePHPFieldName])){
				$tractionData[$tractionFieldName] = $data[$modelAlias][$cakePHPFieldName];
			}
		}		
		//debug($data);
		
		// Step 2: Add CustomParams
		$custom_params_fields = $this->settings[$modelAlias]['custom_params_fields'];
		$customAttributeArray = array();
		foreach($custom_params_fields as $tractionAttributeID=>$cakePHPFieldName){
			if (isset($data[$modelAlias][$cakePHPFieldName])){
				$customAttributeArray[$tractionAttributeID] = $data[$modelAlias][$cakePHPFieldName];
			}else{
				debug($cakePHPFieldName . ' doesn\'t exist in your data array');
			}
		}
		$tractionData["customAttributeArray"] = $customAttributeArray;
		
		
		// Step 3: Collect subscription list
		$tractionData['mailingLists'] = $data[$modelAlias]['mailingLists'];	
		
		return $tractionData;
	}
	
	
	/**
	 *
	 * @param unknown_type $result
	 */
	private function parseResult($myResult, $tractionData){
		$success = false;
		$traction_customer_id = null;
		$msg = '';
	
		// interpret response..
		@$myResponseHeaders = $myResult[1];
		if (!$myResult[0]) {
			// technical connection error..
			$msg = "Technical connection error: " . $myResponseHeaders["RESULT"] . " - " . $myResponseHeaders["ERROR"];
			$success = false;
		} else {
			// interpret traction header responses..
			if ($myResponseHeaders["RESULT"] == 0) {
				$success = true;
				$traction_customer_id = $myResult[1]['CUSTOMERID'];				
			} else if ($myResponseHeaders["RESULT"] < 0) {
				$msg = "Successful add customer request but with warnings: " . $myResponseHeaders["RESULT"] . " - " . $myResponseHeaders["WARN"];
				$success = true;
			} else if ($myResponseHeaders["RESULT"] > 0) {
				// failed.. insert custom handling for error messages..
				// general error handling for this instance.
				//trigger_error("Unsuccessful add customer request with rrror: " . $myResponseHeaders["RESULT"] . " - " . $myResponseHeaders["ERROR"], E_USER_NOTICE);
				$msg = "Unsuccessful add customer request with rrror: " . $myResponseHeaders["RESULT"] . " - " . $myResponseHeaders["ERROR"];
				$success = false;
				if ($myResponseHeaders["RESULT"] == 999) {
					$msg = "Unsuccessful add customer request with rrror: " . $myResponseHeaders["RESULT"] . " - " . $myResponseHeaders["ERROR"];
				} else {
					// naturally, particular specific errors can be catered for here as per the Traction Add Customer API documentation.,
					$msg = "Please check all form field values - " . $myResponseHeaders["ERROR"];
				}
			}
		}
		
		if (!$success){
			$this->log($msg, 'traction');
			$this->log($tractionData, 'traction');
		}
	
		return array(
				'status' 			=> $success,
				'TractionCustomerID'=> $traction_customer_id,
				'errorMessage' 		=> $msg
		);
	
	}

}
