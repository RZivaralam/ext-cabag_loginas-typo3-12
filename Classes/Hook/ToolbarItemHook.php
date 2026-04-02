<?php

namespace Cabag\CabagLoginas\Hook;

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

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Backend\Controller\BackendController;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Session\Backend\DatabaseSessionBackend;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;


class ToolbarItemHook implements ToolbarItemInterface
{

    protected $backendReference;
    protected $users = array();
    protected $EXTKEY = 'cabag_loginas';

    public function __construct(BackendController &$backendReference = null)
    {
        $GLOBALS['LANG']->includeLLFile('EXT:cabag_loginas/Resources/Private/Language/locallang_db.xlf');
        $this->backendReference = $backendReference;
    }

    public function checkAccess()
    {
        $conf = $GLOBALS['BE_USER']->getTSConfig('backendToolbarItem.tx_cabagloginas.disabled');

        return ($conf['value'] == 1 ? false : true);
    }

    public function render()
    {
        $this->backendReference->addCssFile('cabag_loginas', ExtensionManagementUtility::extRelPath($this->EXTKEY) . 'Resources/Public/Stylesheets/cabag_loginas.css');
        $this->backendReference->addJavascriptFile(ExtensionManagementUtility::extRelPath($this->EXTKEY) . 'Resources/Public/JavaScripts/cabag_loginas.js');

        $toolbarMenu = array();

        $title = $GLOBALS['LANG']->sL('LLL:EXT:cabag_loginas/Resources/Private/Language/locallang_db.xlf:fe_users.tx_cabagloginas_loginas');
        $ext_conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cabag_loginas']);
        $defLinkText = trim($ext_conf['defLinkText']);
        if (empty($defLinkText) || strpos($defLinkText, '#') === false || strpos($defLinkText, 'password') !== false) {
            $defLinkText = '[#pid# / #uid#] #username# (#email#)';
        }

        $email = $GLOBALS['BE_USER']->user['email'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('fe_users');
        $this->users = $queryBuilder->select('*')
            ->from('fe_users')
            ->where($queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email, Connection::PARAM_STR)))
            ->setMaxResults(15)
            ->executeQuery()
            ->fetchAllAssociative();

        if (count($this->users)) {
            if (count($this->users) == 1) {
                $title .= ' ' . $this->formatLinkText($this->users[0], $defLinkText);
                $toolbarMenu[] = $this->getLoginAsIconInTable($this->users[0], $title);
            } else {
                $toolbarMenu[] = '<a href="#" class="toolbar-item"><img' . IconUtility::skinImg($this->backPath, 'gfx/su_back.gif', 'width="16" height="16"') . ' title="' . $title . '" alt="' . $title . '" /></a>';

                $toolbarMenu[] = '<ul class="toolbar-item-menu" style="display: none;">';
                $userIcon = IconUtility::getSpriteIcon('apps-pagetree-folder-contains-fe_users', array('style' => 'background-position: 0 10px;'));
                foreach ($this->users as $user) {
                    $linktext = $this->formatLinkText($user, $defLinkText);
                    $link = $this->getHREF($user);
                    $toolbarMenu[] = '<li><a href="' . htmlspecialchars($link) . '" title="' . $title . '" target="_blank">' . $userIcon . $linktext . '</a></li>';
                }

                $toolbarMenu[] = '</ul>';
            }

            return implode("\n", $toolbarMenu);
        }
    }

    public function formatLinkText($user, $defLinkText)
    {
        foreach ($user as $key => $value) {
            $defLinkText = str_replace('#' . $key . '#', $value, $defLinkText);
        }

        return $defLinkText;
    }

    public function _getAdditionalAttributes()
    {
        if (count($this->users)) {
            return ' id="tx-cabagloginas-menu"';
        } else {
            return '';
        }
    }

    public function getHREF($user)
    {
        if (!MathUtility::canBeInterpretedAsInteger($user['uid'])) {
            return '#';
        }
        $parameterArray = array();
        $parameterArray['userid'] = (string)$user['uid'];
        $parameterArray['timeout'] = (string)$timeout = time() + 3600;
        // Check user settings for any redirect page

        //update 12
        if (@$user['felogin_redirectPid']) {
            $parameterArray['redirecturl'] = $this->getRedirectUrl($user['felogin_redirectPid']);
        } else {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('fe_users');
            $userGroup = $queryBuilder
                ->select('fg.felogin_redirectPid')
                ->from('fe_users', 'fu')
                ->join(
                    'fu',
                    'fe_groups',
                    'fg',
                    'fg.uid in (fu.usergroup)'
                )
                ->where(
                    'fg.felogin_redirectPid != \'\'',
                    'fu.uid = ' . $user['uid']
                )->executeQuery()
                ->fetchAssociative();

            $parameterArray['redirecturl'] = $this->getRedirectUrl($userGroup['felogin_redirectPid'] ?? $user['pid']);
        }
        $ses_id = $_COOKIE['be_typo_user'];
        $databaseSessionBackend = GeneralUtility::makeInstance(DatabaseSessionBackend::class);
        $hashedSesId = $databaseSessionBackend->hash($ses_id);
        $parameterArray['verification'] = md5($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] . $hashedSesId . serialize($parameterArray));
        $link = GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . '?' . GeneralUtility::implodeArrayForUrl('tx_cabagloginas', $parameterArray);

        return $link;
    }

    public function getLink($data)
    {
        $isUnsavedNewUser = (strpos($data['row']['uid'], 'NEW') === 0);
        $label = $content = $data['label'] . ' ' . $data['row']['username'];
        if (!$isUnsavedNewUser) {
            $link = $this->getHREF($data['row']);
            $content = '<a href="' . $link . '" target="_blank" style="text-decoration:underline;">' . $label . '</a>';
        }

        return $content;
    }

    public function getLoginAsIconInTable($user, $title = '')
    {
        $additionalClass = '';
        if (trim($title) === '') {
            $title = $GLOBALS['LANG']->sL('LLL:EXT:cabag_loginas/Resources/Private/Language/locallang_db.xlf:cabag_loginas.switchToFeuser');
        }
        //update 12
        $typo3Version=GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class);
        if (version_compare(strval($typo3Version->getMajorVersion()), '7.6.0', '>=')) {
            $iconFactory = GeneralUtility::makeInstance('TYPO3\CMS\Core\Imaging\IconFactory');
            $switchUserIcon = $iconFactory->getIcon('actions-system-backend-user-switch', Icon::SIZE_SMALL)->render();
            $additionalClass = '  class="btn btn-default"';
        } else {
            $switchUserIcon = IconUtility::getSpriteIcon('actions-system-backend-user-emulate', array('style' => 'background-position: 0 10px;'));
        }
        $link = $this->getHREF($user);
        $content = '<a title="' . $title . '" href="' . $link . '" target="_blank"' . $additionalClass . '>' . $switchUserIcon . '</a>';
        return $content;
    }

    /**
     * Finds the redirect link for the current domain.
     *
     * @param integer $pid Page id the user is stored in
     *
     * @return string '../' if nothing was found, the link in the form of http://www.domain.tld/link/page.html otherwise.
     */
    public function getRedirectForCurrentDomain($pid)
    {
        $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cabag_loginas']);

        $domain = $this->getRealDomain($pid);

        $domainArray = parse_url($domain);

        if (empty($extConf['enableDomainBasedRedirect'])) {
            return $domain;
        }

        if (class_exists(ConnectionPool::class)) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_domain');
            $queryBuilder->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
                ->add(GeneralUtility::makeInstance(HiddenRestriction::class));
            $rows = $queryBuilder
                ->select('domainName', 'tx_cabagfileexplorer_redirect_to')
                ->from('sys_domain')
                ->where(
                    $queryBuilder->expr()->eq('domainName', $queryBuilder->createNamedParameter($domainArray['host'], Connection::PARAM_STR))
                )
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();
        } else {
            $rowArray = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                'domainName, tx_cabagfileexplorer_redirect_to', 'sys_domain', 'hidden = 0 AND domainName = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($domainArray['host'], 'sys_domain'), '', '', 1
            );
        }

        if (count($rowArray) === 0 || (trim($rowArray[0]['tx_cabagfileexplorer_redirect_to'])) === '') {
            return $domain;
        }

        $domain = 'http' . (GeneralUtility::getIndpEnv('TYPO3_SSL') ? 's' : '') . '://' . $rowArray[0]['domainName'] . '/' .
            ltrim($rowArray[0]['tx_cabagfileexplorer_redirect_to'], '/');

        return $domain;
    }

    /**
     * @param integer $pageId
     *
     * @return string
     */
    protected function getRedirectUrl($pageId)
    {
        return rawurlencode($this->getRealDomain($pageId) . '/index.php?id=' . $pageId);
    }

    /**
     * Render "item" part of this toolbar
     *
     * @return string Toolbar item HTML
     */
    public function getItem()
    {
        return $this->render();
    }

    /**
     * TRUE if this toolbar item has a collapsible drop down
     *
     * @return bool
     */
    public function hasDropDown()
    {
        return false;
    }

    /**
     * Render "drop down" part of this toolbar
     *
     * @return string Drop down HTML
     */
    public function getDropDown()
    {
        return '';
    }

    /**
     * Returns an array with additional attributes added to containing <li> tag of the item.
     *
     * Typical usages are additional css classes and data-* attributes, classes may be merged
     * with other classes needed by the framework. Do NOT set an id attribute here.
     *
     * array(
     *     'class' => 'my-class',
     *     'data-foo' => '42',
     * )
     *
     * @return array List item HTML attributes
     */
    public function getAdditionalAttributes()
    {
        return array(
            'id' => 'tx-cabagloginas-menu'
        );
    }

    /**
     * Returns an integer between 0 and 100 to determine
     * the position of this item relative to others
     *
     * By default, extensions should return 50 to be sorted between main core
     * items and other items that should be on the very right.
     *
     * @return int 0 .. 100
     */
    public function getIndex()
    {
        return 50;
    }

    /**
     * Get the the real domain of given pid.
     *
     * When outside a normal page tree (i.e. global storage), this returns the current domain in which the user
     * is logged in the backend.
     *
     * @param int $pageId
     * @return string
     */
    private function getRealDomain(int $pageId): string
    {
        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
            return parse_url($site->getRouter()->generateUri($pageId), PHP_URL_HOST);
        } catch(SiteNotFoundException $e) {
            // In some cases, when frontend users are outside a normal page tree (global storage)
            // just return the domain from which the user is logged in the backend
            return GeneralUtility::getIndpEnv('HTTP_HOST');
        }
    }

}