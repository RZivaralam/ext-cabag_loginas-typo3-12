<?php

namespace Cabag\CabagLoginas\Hook;

//update 12
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use \TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Frontend hook to support redirection.
 *
 * @author Nicole Cordes <typo3@cordes.co>
 *
 * @package TYPO3
 * @subpackage tx_cabagloginas
 */
class PostUserLookupHook {

    /**
     * Looks for any redirection after login.
     *
     * @param array $params
     * @param object $pObj
     *
     * @return void
     */
    public function postUserLookUp(array $params, object &$pObj) {

        if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface
            && ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend()
        ) {
             $request = &$GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
             $frontendUser=$request->getAttribute('frontend.user');
             
            if (isset($frontendUser) && !empty($frontendUser->user['uid'])) {
                    $request = &$GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
                    $cabagLoginasData = $request->getParsedBody()['Cabag\CabagLoginas\Hook\ToolbarItemHook'] ?? $request->getQueryParams()['Cabag\CabagLoginas\Hook\ToolbarItemHook'] ?? null;
               
                if (!empty($cabagLoginasData['redirecturl'])) {
                    $partsArray = parse_url(rawurldecode($cabagLoginasData['redirecturl']));
                    if (strpos(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'), $partsArray['scheme'] . '://' . $partsArray['host'] . '/') === false) {
                        $partsArray['query'] .= '&FE_SESSION_KEY=' . rawurlencode(
                            $pObj->id . '-' . md5($pObj->id . '/' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'])
                        );
                    }
                    $redirectUrl = (isset($partsArray['scheme']) ? $partsArray['scheme'] . '://' : '') .
                        (isset($partsArray['user']) ? $partsArray['user'] .
                        (isset($partsArray['pass']) ? ':' . $partsArray['pass'] : '') . '@' : '') .
                        (isset($partsArray['host']) ? $partsArray['host'] : '') .
                        (isset($partsArray['port']) ? ':' . $partsArray['port'] : '') .
                        (isset($partsArray['path']) ? $partsArray['path'] : '') .
                        (isset($partsArray['query']) ? '?' . $partsArray['query'] : '') .
                        (isset($partsArray['fragment']) ? '#' . $partsArray['fragment'] : '');

                    $responseFactory = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                            \Psr\Http\Message\ResponseFactoryInterface::class
                        );
                        $response = $responseFactory
                            ->createResponse()
                            ->withAddedHeader('location', $redirectUrl);    
                    //HttpUtility::redirect($redirectUrl);
                }
            }
        }
    }

}
