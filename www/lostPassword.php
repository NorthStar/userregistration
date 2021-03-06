<?php

$config = SimpleSAML_Configuration::getInstance();
$uregconf = SimpleSAML_Configuration::getConfig('module_userregistration.php');
$mailoptions = $uregconf->getArray('mail');
$viewAttr = $uregconf->getArray('attributes');
$formFields = $uregconf->getArray('formFields');
$eppnRealm = $uregconf->getString('user.realm');
$store = sspmod_userregistration_Storage_UserCatalogue::instantiateStorage();
$customNavigation = $uregconf->getBoolean('custom.navigation', TRUE);
$redis_config = $uregconf->getArray('redis');

$tokenManager = new sspmod_userregistration_TokenManagement($redis_config, $mailoptions['token.lifetime']);


if (array_key_exists('emailreg', $_REQUEST)) {
	// Stage 2: User have submitted e-mail adress for password recovery
	try {
		$email = filter_input(INPUT_POST, 'emailreg', FILTER_VALIDATE_EMAIL);
		if(!$email){
			$rawValue = isset($_REQUEST['emailreg'])?$_REQUEST['emailreg']:NULL;
			if(!$rawValue){
				throw new sspmod_userregistration_Error_UserException(
					'void_value',
					$store->userRegisterEmailAttr,
					'',
					'Validation of user input failed.'
					.' Field:'.$store->userRegisterEmailAttr
					.' is empty');
			}else{
				throw new sspmod_userregistration_Error_UserException(
					'illegale_value',
					$store->userRegisterEmailAttr,
					$rawValue,
					'Validation of user input failed.'
					.' Field:'.$store->userRegisterEmailAttr
					.' Value:'.$rawValue);
			}
		}

		if(!$store->isRegistered($store->userRegisterEmailAttr, $email) ) {
			throw new sspmod_userregistration_Error_UserException(
				'email_not_found',
				$email,
				'',
				'Try to reset password, but mail address not found: '.$email
			);
		}

		$token = $tokenManager->generate($email);

		$url = SimpleSAML_Utilities::selfURL();

		$registerurl = SimpleSAML_Utilities::addURLparameter(
			$url,
			array(
				'token' => $token));

		$systemName = array('%SNAME%' => $uregconf->getString('system.name') );
		$mail_data = array(
			'registerurl' => $registerurl,
			'systemName' => $systemName,
			'tokenLifetime' => $mailoptions['token.lifetime'],
		);

		$emailto = $email;

		sspmod_userregistration_Util::sendEmail(
			$emailto,
			$mailoptions['subject'],
			'userregistration:lostPasswordMail_token.tpl.php',
			$mail_data
		);

		$html = new SimpleSAML_XHTML_Template(
			$config,
			'userregistration:lostPassword_sent.tpl.php',
			'userregistration:userregistration');
		$html->data['customNavigation'] = $customNavigation;
		$html->data['email'] = $emailto;
		$html->show();
	}catch(sspmod_userregistration_Error_UserException $e){
		$terr = new SimpleSAML_XHTML_Template(
			$config,
			'userregistration:lostPassword_email.tpl.php',
			'userregistration:userregistration');
		$terr->data['email'] = $_POST['emailreg'];

		$error = $terr->t(
			$e->getMesgId(),
			$e->getTrVars()
		);
		$terr->data['error'] = htmlspecialchars($error);
		$terr->data['customNavigation'] = $customNavigation;
		$terr->show();
	}

} elseif(array_key_exists('token', $_GET)) {
	// Stage 3: User access page from url in e-mail
	try{

		$token = $_GET['token'];
		$token_data = $tokenManager->getDetails($token);
		if ($token_data === false) {
			throw new sspmod_userregistration_Error_UserException('invalid_token');
		}

		$email = $token_data['email'];

		$formGen = new sspmod_userregistration_XHTML_Form($formFields, 'lostPassword.php');

		$showFields = array('pw1', 'pw2');
		$formGen->fieldsToShow($showFields);

		$userValues = $store->findAndGetUser($store->userRegisterEmailAttr, $email);

		$hidden = array(
			'emailconfirmed' => $email,
			'token' => $token);
		$formGen->addHiddenData($hidden);
		$formGen->setSubmitter('submit_change');
		$formHtml = $formGen->genFormHtml();

		$html = new SimpleSAML_XHTML_Template(
			$config,
			'userregistration:lostPassword_changePassword.tpl.php',
			'userregistration:userregistration');
		$html->data['formHtml'] = $formHtml;
		$html->data['uid'] = $userValues[$store->userIdAttr];

		if(!empty($store->passwordPolicy)) {
			$html->data['passwordPolicy'] = $store->passwordPolicy;
			$html->data['passwordPolicytpl'] = SimpleSAML_Module::getModuleDir('userregistration').'/templates/password_policy_tpl.php';
			$html->data['passwordField'] = 'pw1';
		}
		$html->data['customNavigation'] = $customNavigation;
		$html->show();
	} catch(sspmod_userregistration_Error_UserException $e) {
		// Invalid token
		$terr = new SimpleSAML_XHTML_Template(
			$config,
			'userregistration:lostPassword_email.tpl.php',
			'userregistration:userregistration');

		$error = $terr->t(
			$e->getMesgId(),
			$e->getTrVars()
		);
		$terr->data['error'] = htmlspecialchars($error);
		$terr->data['customNavigation'] = $customNavigation;
		$terr->show();
	}
} elseif (array_key_exists('sender', $_POST)) {
	try {
		// Add or update user object
		$listValidate = array('pw1', 'pw2');
		$validator = new sspmod_userregistration_Registration_Validation(
		  $formFields,
		  $listValidate);


		$token = isset($_REQUEST['token']) ? $_REQUEST['token'] : null;
		$token_data = $tokenManager->getDetails($token);

		if ($token_data === false) {
		  throw new sspmod_userregistration_Error_UserException('invalid_token');
		}

		$email = $token_data['email'];

		$userValues = $store->findAndGetUser($store->userRegisterEmailAttr, $email);
		$validValues = $validator->validateInput();
		$newPw = sspmod_userregistration_Util::validatePassword($validValues);

		if(!empty($store->passwordPolicy)) {
		  $validator->validatePolicyPassword($store->passwordPolicy, $validValues, $newPw);
		}

		$store->changeUserPassword($userValues[$store->userIdAttr], $newPw);
		$tokenManager->delete($token);
		header('Location: '.SimpleSAML_Module::getModuleURL('userregistration/lostPassword.php?success'));
		exit();

	} catch(sspmod_userregistration_Error_UserException $e) {
		// Some user error detected
		$formGen = new sspmod_userregistration_XHTML_Form($formFields, 'lostPassword.php');

		$showFields = array('pw1', 'pw2');
		$formGen->fieldsToShow($showFields);

		$hidden = array();
		$hidden['emailconfirmed'] = $_REQUEST['emailconfirmed'];
		$hidden['token'] = $_REQUEST['token'];
		$formGen->addHiddenData($hidden);

		$formGen->setValues(array($store->userIdAttr => $_REQUEST['emailconfirmed']));
		$formGen->setSubmitter('submit_change');
		$formHtml = $formGen->genFormHtml();

		$terr = new SimpleSAML_XHTML_Template(
		  $config,
		  'userregistration:lostPassword_changePassword.tpl.php',
		  'userregistration:userregistration');
		$terr->data['formHtml'] = $formHtml;
		$terr->data['uid'] = $userValues[$store->userIdAttr];

		$error = $terr->t(
		  $e->getMesgId(),
		  $e->getTrVars()
		);

		if(!empty($store->passwordPolicy)) {
		  $terr->data['passwordPolicy'] = $store->passwordPolicy;
		  $terr->data['passwordPolicytpl'] = SimpleSAML_Module::getModuleDir('userregistration').'/templates/password_policy_tpl.php';
		  $terr->data['passwordField'] = 'pw1';
		}

		$terr->data['error'] = htmlspecialchars($error);
		$terr->data['customNavigation'] = $customNavigation;
		$terr->show();
	}
}  elseif (array_key_exists('success', $_GET)) {
	$html = new SimpleSAML_XHTML_Template(
		$config,
		'userregistration:lostPassword_complete.tpl.php',
		'userregistration:userregistration');
	$html->data['customNavigation'] = $customNavigation;
	$html->show();
 
} else {
	// Stage 1: User access page to enter mail address for pasword recovery
	$html = new SimpleSAML_XHTML_Template(
		$config,
		'userregistration:lostPassword_email.tpl.php',
		'userregistration:userregistration');
	$html->data['customNavigation'] = $customNavigation;
	$html->show();
}

?>
