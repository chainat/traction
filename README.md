CakePHP 2 Traction Plugin (version 1)
=====================================

Date: 06/08/2012

By: Chainat Wongtapan

I see the Traction API should sit very close to the model, thus I make a behaviour for it. There are multiple advantages we could get from this.

- Utilise existing validation rules
- Can bundle with the data saving process. i.e. save to our database first and then push to Traction
- Easy to perform a test (Not available at this stage)      

### Quick start guide

1. Clone this plugin to your plugin folder

2. Explicitly tell CakePHP to load this plugin by inserting this line below to your bootstrap.php
   CakePlugin::load('Traction');

3. There are two APIs you can use
	
	3.1 AddCustomer

	Just to add new customer data to Traction database with additional attributes
		
	To use this api:

		var $actsAs = array(
			'Traction.TractionAddCustomer'=>array(
				"traction_uid"					=>	"your_endpoint", 							// traction username
				"traction_pwd"					=>	'your_endpoint_password', 					// traction password
				"traction_ep"					=>	"your_endpoint_id",
				"reply_url"					=>      "http://www.change_this_to_your_domain.com.au",

				// Custom Params
				'custom_params_fields'			=> array(
	                            // ID		=> 'CakePHP field name'
	                            1111111		=> 'postcode',								// Additional attributes
	                            2222222		=> 'optin_arrival',							
	                            3333333		=> 'optin_update',							
	                            ....
				)
			),
		}


		In the model function:

		$result = $this->tractionAddCustomer($data);
		
		
	3.2 MultiSubscribe
	
	Add a new or replace customer data to mailling list.

	To use this:

		var $actsAs = array(	
			'Traction.TractionMultiSubscribe'=>array(
					"traction_uid"                  =>	"your_endpoint", 		// traction username
					"traction_pwd"			=>	'your_endpoint_password', 	// traction password
					"traction_ep"			=>	"your_endpoint_id",
					"reply_url"			=>      "http://www.change_this_to_your_domain.com.au",
					
					// Use default fields
					// Custom Params
					'custom_params_fields'			=> array(
                                                      2981398		=> 'postcode',			// Postcode
					),
					
					//Note: we need to add array of mailingLists to data and send it along 										
			),
		}
		
		
		In the model function:
		
		// Prepare your data
		$data[$this->alias]['mailingLists'] = array();
		$data[$this->alias]['mailingLists'][] = 18219763;		//subscribe default mailing list
		$data[$this->alias]['mailingLists'][] = xxxxx;			//subscribe default mailing list
		
		// Add to traction
		$result = $this->tractionSubscribe($data);


Note:
-----

The plugins use default field name as described below. If you use other name than this, just overide it from your settings.

	'default_mapping_fields'    =>	array(				
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
		
