<?php
use WHMCS\Database\Capsule;
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function zoho_recruit_MetaData()
{
    try {
         if(!Capsule::schema()->hasTable('zoho_recruit')){
    	       Capsule::schema()->create(
    	                                'zoho_recruit',
    	                           function ($table) {
    	                                 $table->string('authtoken');
    	                                 $table->string('domain');
    	                                 $table->string('server');
    	                                 $table->string('zoid');
    	                                 $table->string('profileid');
    	                                 $table->string('superAdmin');
    	                               }
    	                        );
        }
        else {
            $pdo = Capsule::connection()->getPdo();
            $pdo->beginTransaction();
        }
	} catch (Exception $e) {
	logModuleCall(
	    'zoho_crm',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
    }
    
    return array(
        'DisplayName' => 'Zoho Recruit',
        'APIVersion' => '1.1', // Use API Version 1.1
        'RequiresServer' => true, // Set true if module requires a server to work
        'DefaultNonSSLPort' => '1111', // Default Non-SSL Connection Port
        'DefaultSSLPort' => '1112', // Default SSL Connection Port
        'ServiceSingleSignOnLabel' => 'Login to Panel as User',
        'AdminSingleSignOnLabel' => 'Login to Panel as Admin',
    );
}

function zoho_recruit_ConfigOptions()
{
    return array(
         // the radio field type displays a series of radio button options
        'Domain' => array(
            'Type' => 'radio',
            'Options' => 'com,eu,cn,in',
            'Description' => 'Choose your domain!',
        ),
        // a text field type allows for single line text input
        'Authtoken' => array(
            'Type' => 'text',
            'Size' => '50',
            'Description' => '<br><a href="https://accounts.zoho.com/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi" target="_blank">Click here</a> to generate authtoken for US Domain. 
            <br><a href="https://accounts.zoho.eu/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi" target="_blank">Click here</a> to generate authtoken for EU Domain. 
            <br><a href="https://accounts.zoho.com.cn/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi" target="_blank">Click here</a> to generate authtoken for CN Domain. 
            <br><a href="https://accounts.zoho.in/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi" target="_blank">Click here</a> to generate authtoken for IN Domain.',
        ),
       
    );
}

function zoho_recruit_CreateAccount(array $params)
{
    $addonid;
    $urlOrg;
	$test = $params['configoption2'];
	$domain = $params['configoption1'];
	try {
	$curl = curl_init();
	$arrClient = $params['clientsdetails'];
	$planid = $params['configoptions']['Plan Type'];
	if($planid == 36104){
	    $addonid = 36216;
	}
	else if($planid == 36105) {
	    $addonid = 36217;
	}
	else {
	    $addonid = 36218;
	}
	$noofusers = $params['configoptions']['No. of users'];

	$bodyArr = json_encode(array(
		//'JSONString' => array(
		"serviceid" => 3601,
		"email" => $arrClient['email'],
		"customer" => array(
		"companyname" => $arrClient['companyname'],
		"street" => $arrClient['address1'],
		"city" => $arrClient['city'],
		"state" => $arrClient['state'],
		"country" => $arrClient['countryname'],
		"zipcode" => $arrClient['postcode'],
		"phone" => $arrClient['phonenumber']
		),
		"subscription" => array(
		"plan" => $planid,
		"addons" => array(
		array(
		"id" => $addonid,
		"count" => $noofusers
		)
		),
		"payperiod" => "YEAR",
		"currency" => "1",
		"addprofile" => "true"
		),
		//),
	));
	$authtoken = array(
	"authtoken" => $test
	);
	 
	$bodyJson = array('JSONString' => $bodyArr, 'authtoken' => $test);
	$bodyJsn = json_encode($bodyJson);
   $curlOrg = curl_init();
   if($domain == 'cn') {
    $urlOrg = 'https://payments.zoho.com.cn/restapi/partner/v1/json/subscription';
   }
   else {
       $urlOrg = 'https://payments.zoho.'.$params['configoption1'].'/restapi/partner/v1/json/subscription';
   }
   curl_setopt_array($curlOrg, array(
      CURLOPT_URL => $urlOrg,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => $bodyJson
   ));

		$responseOrg = curl_exec($curlOrg);
		$respOrgJson = json_decode($responseOrg); 
		$getInfo = curl_getinfo($curlOrg,CURLINFO_HTTP_CODE);
		curl_close($curlOrg);
		$result = $respOrgJson->result;
		if(($result == 'success') && ($getInfo == '200')) {
		    $customid = $respOrgJson->customid;
		    if($customid != '') {
		        $pdo = Capsule::connection()->getPdo();
		        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0 );
		        //$pdo->beginTransaction();
		        try {
			        $statement = $pdo->prepare('insert into zoho_recruit (authtoken,domain,server,zoid,profileid,superAdmin) values (:authtoken, :domain, :server, :zoid, :profileid, :superAdmin)');
	 
		            $statement->execute(
        		     [
        			   ':authtoken' => $test,
        			   ':domain' => $params['domain'],
        			   ':server' => $params['configoption1'],
        			   ':zoid' => $respOrgJson->customid,
        			   ':profileid' => $respOrgJson->profileid,
        			   ':superAdmin' => "true"              
        		    ]
        		 );
	 
        		 $pdo->commit();
        		 $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1 );
        		 } catch (\Exception $e) {
        			  return "Uh oh! {$e->getMessage()}".$urlChildPanel;
        			  $pdo->rollBack();
        		  }
	 
    		  return array ('success' => 'Recruit Org has been created successfully !');
    		    }
    		    else if(($result == 'success') && (isset($respOrgJson->ERRORMSG))) {
    		        return 'Failed  ->  '.$respOrgJson->ERRORMSG;
    		    }
    		    else if ($getInfo == '400') {
		            $updatedUserCount = Capsule::table('tblproducts')
		            ->where('servertype','zoho_recruit')
		            ->update(
        			  [
        			   'configoption2' => '',
        			  ]
		            );
			    }
			    else
        		{
        		    return 'Failed -->Description: '.$respOrgJson->status->description.' --->More Information:'.$respOrgJson->data->moreInfo.'--------------'.$getInfo;
        	    }
    		        
    		    
    		}
    		else if($getInfo == '400') {
    		    //$errorMsg = $respOrgJson->ERRORMSG;
    		    return 'Failed -->  Invalid Authtoken.';
    		}
    		else{
    		    $errorMsg = $respOrgJson->ERRORMSG;
    		    return 'Failed -->  '.$errorMsg;
    		}
	 
	} catch (Exception $e) {
		logModuleCall(
		    'zoho_recruit',
		    __FUNCTION__,
		    $params,
		    $e->getMessage(),
		    $e->getTraceAsString()
		);
		return $e->getMessage();
	    }
}

function zoho_recruit_TestConnection(array $params)
{
    try {
        // Call the service's connection test function.
        $success = true;
        $errorMsg = '';
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'zoho_recruit',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        $success = false;
        $errorMsg = $e->getMessage();
    }
    return array(
        'success' => $success,
        'error' => $errorMsg,
    );
}

function zoho_recruit_AdminServicesTabFields(array $params)
{
    try{
    $url;
    $paymenturl;
	$cli = Capsule::table('zoho_recruit')->where('domain',$params['domain'])->first();
	$domain = $params['configoption1'];
	if($domain == 'cn') 
	{
	    $url = 'https://accounts.zoho.com.'.$domain.'/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi';
	    $paymenturl = 'https://payments.zoho.com.'.$domain.'/store/reseller.do?profileId='.$cli->profileid;
	}
	else
    {
        $url = 'https://accounts.zoho.'.$domain.'/apiauthtoken/create?SCOPE=ZohoPayments/partnerapi';
        $paymenturl = 'https://payments.zoho.'.$domain.'/store/reseller.do?profileId='.$cli->profileid;
    }
	$authtoken = $params['configoption2'];
	if(!$authtoken == '') {
	$authtoken = '<h2 style="color:green;">Authenticated</h2>';
	}
	else {
	$authtoken = '<a href="'.$url.'" type="submit" target="_blank"> Click here </a> (Call only once for authenticating)';
	}
	$response = array();

	return array(
	     'Authenticate' => $authtoken,
	     'Super Administrator' => $cli->superAdmin,
	     'ZOID' => $cli->zoid, 
	     'URL to Manage Customers' => '<a href="'.$paymenturl.'" target=_window>Click here</a>'
	    );
	 
    } catch (Exception $e) {
	logModuleCall(
	    'zoho_recruit',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
    }
	return array();
}

function zoho_recruit_AdminServicesTabFieldsSave(array $params)
{
    // Fetch form submission variables.
    $originalFieldValue = isset($_REQUEST['zoho_recruit_original_uniquefieldname'])
        ? $_REQUEST['zoho_recruit_original_uniquefieldname']
        : '';
    $newFieldValue = isset($_REQUEST['zoho_recruit_uniquefieldname'])
        ? $_REQUEST['zoho_recruit_uniquefieldname']
        : '';
    // Look for a change in value to avoid making unnecessary service calls.
    if ($originalFieldValue != $newFieldValue) {
        try {
            // Call the service's function, using the values provided by WHMCS
            // in `$params`.
        } catch (Exception $e) {
            // Record the error in WHMCS's module log.
            logModuleCall(
                'zoho_recruit',
                __FUNCTION__,
                $params,
                $e->getMessage(),
                $e->getTraceAsString()
            );
            // Otherwise, error conditions are not supported in this operation.
        }
    }
}

function zoho_recruit_ServiceSingleSignOn(array $params)
{
    try {
        // Call the service's single sign-on token retrieval function, using the
        // values provided by WHMCS in `$params`.
        $response = array();
        return array(
            'success' => true,
            'redirectTo' => $response['redirectUrl'],
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'zoho_recruit',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return array(
            'success' => false,
            'errorMsg' => $e->getMessage(),
        );
    }
}

function zoho_recruit_AdminSingleSignOn(array $params)
{
    try {
        // Call the service's single sign-on admin token retrieval function,
        // using the values provided by WHMCS in `$params`.
        $response = array();
        return array(
            'success' => true,
            'redirectTo' => $response['redirectUrl'],
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'zoho_recruit',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return array(
            'success' => false,
            'errorMsg' => $e->getMessage(),
        );
    }
}

function zoho_recruit_ClientArea(array $params)
{
    $serviceAction = 'get_stats';
    $templateFile = 'templates/overview.tpl';
    $recruiturl;
    $domain = $params['configoption1'];
    if($domain == 'cn')
    {
        $recruiturl = 'https://recruit.zoho.com.cn';
    }
    else
    {
        $recruiturl = 'https://recruit.zoho.'.$domain;
    }
    try {
      $cli = Capsule::table('zoho_recruit')->where('zoid',$params['zoid'])->first();
      $urlToPanel = $cli->url;
      
	return array(
	    'tabOverviewReplacementTemplate' => $templateFile,
	    'templateVariables' => array(
	     'recruitUrl' => $recruiturl
	    ),
	);
    } catch (Exception $e) {
	// Record the error in WHMCS's module log.
	logModuleCall(
	    'zoho_recruit',
	    __FUNCTION__,
	    $params,
	    $e->getMessage(),
	    $e->getTraceAsString()
	);
	// In an error condition, display an error page.
	return array(
	    'tabOverviewReplacementTemplate' => 'error.tpl',
	    'templateVariables' => array(
	        'usefulErrorHelper' => $e->getMessage(),
	    ),
	);
    }
}
