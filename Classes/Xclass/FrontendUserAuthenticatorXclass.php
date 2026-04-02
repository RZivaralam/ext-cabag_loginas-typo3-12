<?php

/*
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

namespace Cabag\CabagLoginas\Xclass;

use TYPO3\CMS\Frontend\Middleware\FrontendUserAuthenticator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Needed to trigger authentication without setting FE_alwaysFetchUser globally
 */
class FrontendUserAuthenticatorXclass extends FrontendUserAuthenticator
{

    /**
     *
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // update 12
        $FE_alwaysFetchUser = @$GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['FE_alwaysFetchUser'];
        $requestcabagloginas = &$GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
        $cabag_loginas_data = $requestcabagloginas->getParsedBody()['tx_cabagloginas'] ?? $requestcabagloginas->getQueryParams()['tx_cabagloginas'] ?? null;
        if (isset($cabag_loginas_data['userid']) && ($cabag_loginas_data['userid'] > 0)) {
            // Trigger authentication without setting FE_alwaysFetchUser globally
            $GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['FE_alwaysFetchUser'] = true;
        }
        $response = parent::process($request, $handler);
        $GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['FE_alwaysFetchUser'] = $FE_alwaysFetchUser;

        return $response;
    }
}