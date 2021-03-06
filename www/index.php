<?php

$config = SimpleSAML_Configuration::getInstance();
$session = SimpleSAML_Session::getInstance();
$uregconf = SimpleSAML_Configuration::getConfig('module_userregistration.php');
/* Get a reference to our authentication source. */
$asId = $uregconf->getString('auth');

$links = array();
$admin_links = array();



	$links[] = array(
		'href' => SimpleSAML_Module::getModuleURL('userregistration/newUser.php'),
		'text' => '{userregistration:userregistration:link_newuser}',
	);

	$links[] = array(
		'href' => SimpleSAML_Module::getModuleURL('userregistration/lostPassword.php'),
		'text' => '{userregistration:userregistration:link_lostpw}',
	);

	// Admin links
	$admin_links[] = array(
		'href' => SimpleSAML_Module::getModuleURL('userregistration/admin_newUser.php'),
		'text' => '{userregistration:userregistration:link_newuser}',
	);
	$admin_links[] = array(
		'href' => SimpleSAML_Module::getModuleURL('userregistration/admin_manageUsers.php'),
		'text' => '{userregistration:userregistration:link_manageusers}',
	);

	if($session->isAuthenticated()) {

		$uregconf = SimpleSAML_Configuration::getConfig('module_userregistration.php');

		if ($session->getAuthority() == $asId) {
			$as = new SimpleSAML_Auth_Simple($asId);

			$links[] = array(
				'href' => SimpleSAML_Module::getModuleURL('userregistration/reviewUser.php'),
				'text' => '{userregistration:userregistration:link_review}',
			);
			$links[] = array(
				'href' => SimpleSAML_Module::getModuleURL('userregistration/changePassword.php'),
				'text' => '{userregistration:userregistration:link_changepw}',
			);
			$links[] = array(
				'href' => SimpleSAML_Module::getModuleURL('userregistration/changeMail.php'),
				'text' => '{userregistration:userregistration:link_changemail}',
			);
/*
			$links[] = array(
				'href' => SimpleSAML_Module::getModuleURL('userregistration/delUser.php'),
				'text' => '{userregistration:userregistration:link_deluser}',
			);
*/
			$links[] = array(
				'href' => $as->getLogoutURL(),
				'text' => '{status:logout}',
			);
		} else {
			$links[] = array(
				'href' => SimpleSAML_Module::getModuleURL('userregistration/reviewUser.php'),
				'text' => '{userregistration:userregistration:link_enter}',
            );
		}
	} else {
		// Not authenticated
		$links[] = array(
			'href' => SimpleSAML_Module::getModuleURL('userregistration/reviewUser.php'),
			'text' => '{userregistration:userregistration:link_enter}',
		);
	}

$html = new SimpleSAML_XHTML_Template(
		$config,
		'userregistration:index.tpl.php',
		'userregistration:userregistration');
$html->data['source'] = $asId;
$html->data['links'] = $links;

if (count($admin_links) != 0) {
    $html->data['admin_links'] = $admin_links;
}

if(array_key_exists('status', $_GET) && $_GET['status'] == 'deleted') {
	$html->data['userMessage'] = 'message_userdel';
}


$html->show();

