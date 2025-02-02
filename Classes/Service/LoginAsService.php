<?php

namespace Cabag\CabagLoginas\Service;

use TYPO3\CMS\Core\Session\Backend\DatabaseSessionBackend;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;

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
class LoginAsService extends AbstractAuthenticationService
{

    protected $rowdata;

    public function getUser()
    {
        $row = false;
        //update 12
        if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 12) {
            $cabagLoginasData = GeneralUtility::_GP('tx_cabagloginas');
        }else{
            $request = &$GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
            $cabagLoginasData = $request->getParsedBody()['tx_cabagloginas'] ?? $request->getQueryParams()['tx_cabagloginas'] ?? null;
        }   

        if (isset($cabagLoginasData['verification'])) {
            $ses_id = $_COOKIE['be_typo_user'];
            $databaseSessionBackend = GeneralUtility::makeInstance(DatabaseSessionBackend::class);
            $hashedSesId = $databaseSessionBackend->hash($ses_id);
            $verificationHash = $cabagLoginasData['verification'];
            unset($cabagLoginasData['verification']);
            if (md5($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] . $hashedSesId . serialize($cabagLoginasData)) === $verificationHash &&
                $cabagLoginasData['timeout'] > time()) {
                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_users');
                    $queryBuilder->getRestrictions()
                        ->removeAll()
                        ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
                        ->add(GeneralUtility::makeInstance(HiddenRestriction::class));
                    $user = $queryBuilder
                        ->select('*')
                        ->from('fe_users')
                        ->where(
                            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($cabagLoginasData['userid'], \PDO::PARAM_INT))
                        )
                        ->execute()
                        ->fetchAll();
                if ($user[0]) {
                    $row = $user[0];
                    $this->rowdata = $user[0];
                    if (is_object($GLOBALS['TSFE']->fe_user)) {
                        $GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_cabagloginas', true);
                    }
                }
            }
        }

        return $row;
    }

    public function authUser(array $user): int
    {
        $OK = 100;

        if (!$this->rowdata) {
            $this->getUser();
        }
        if ($this->rowdata['uid'] == $user['uid']) {
            $OK = 200;
        }

        return $OK;
    }

}
