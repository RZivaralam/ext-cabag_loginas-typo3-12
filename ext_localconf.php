<?php

use TYPO3\CMS\Core\Utility\GeneralUtility;

if (!defined('TYPO3')) {
	die ('Access denied.');
}


// Hook to check for redirection
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauth.php']['postUserLookUp'][] = 'Cabag\CabagLoginas\Hook\PostUserLookupHook->postUserLookUp';

// Trigger authentication without setting FE_alwaysFetchUser globally
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Frontend\Middleware\FrontendUserAuthenticator::class] = [
    'className' => Cabag\CabagLoginas\Xclass\FrontendUserAuthenticatorXclass::class
];


\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService('cabag_loginas', 'auth', 'Cabag\\CabagLoginas\\Service\\LoginAsService' /* sv key */,
	array(

		'title' => 'Login as Service',
		'description' => 'Authenticate a frontend user using a link',

		'subtype' => 'getUserFE,authUserFE',

		'available' => TRUE,
		'priority' => 70,
		'quality' => 70,

		'os' => '',
		'exec' => '',

		'className' => Cabag\CabagLoginas\Service\LoginAsService::class
	)
);