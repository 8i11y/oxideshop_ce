<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Core;

use OxidEsales\EshopCommunity\Core\Exception\StandardException;
use Exception;

/**
 * Class oxOnlineCaller makes call to given URL which is taken from child classes and sends request parameter.
 *
 * @internal Do not make a module extension for this class.
 * @see      https://oxidforge.org/en/core-oxid-eshop-classes-must-not-be-extended.html
 *
 * @ignore   This class will not be included in documentation.
 */
abstract class OnlineCaller
{
    const ALLOWED_HTTP_FAILED_CALLS_COUNT = 4;

    /** Amount of seconds for curl execution timeout. */
    const CURL_EXECUTION_TIMEOUT = 5;

    /** Amount of seconds for curl connect timeout. */
    const CURL_CONNECT_TIMEOUT = 3;

    /**
     * @var \OxidEsales\Eshop\Core\Curl
     */
    private $_oCurl;

    /**
     * @var \OxidEsales\Eshop\Core\OnlineServerEmailBuilder
     */
    private $_oEmailBuilder;

    /**
     * @var \OxidEsales\Eshop\Core\SimpleXml
     */
    private $_oSimpleXml;

    /**
     * @deprecated use self::getXMLDocumentName instead
     */
    abstract protected function _getXMLDocumentName(); // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

    /**
     * Gets XML document name.
     *
     * @return string XML document tag name.
     */
    abstract protected function getXMLDocumentName();

    /**
     * @deprecated use self::getServiceUrl instead
     */
    abstract protected function _getServiceUrl(); // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

    /**
     * Gets service url.
     *
     * @return string Web service url.
     */
    abstract protected function getServiceUrl();

    /**
     * Sets dependencies.
     *
     * @param \OxidEsales\Eshop\Core\Curl                     $oCurl         Sends request to OXID servers.
     * @param \OxidEsales\Eshop\Core\OnlineServerEmailBuilder $oEmailBuilder Forms email when OXID servers are unreachable.
     * @param \OxidEsales\Eshop\Core\SimpleXml                $oSimpleXml    Forms XML from Request for sending to OXID servers.
     */
    public function __construct(\OxidEsales\Eshop\Core\Curl $oCurl, \OxidEsales\Eshop\Core\OnlineServerEmailBuilder $oEmailBuilder, \OxidEsales\Eshop\Core\SimpleXml $oSimpleXml)
    {
        $this->_oCurl = $oCurl;
        $this->_oEmailBuilder = $oEmailBuilder;
        $this->_oSimpleXml = $oSimpleXml;
    }

    /**
     * Makes curl call with given parameters to given url.
     *
     * @param \OxidEsales\Eshop\Core\OnlineRequest $oRequest Information set in Request object will be sent to OXID servers.
     *
     * @return null|string In XML format.
     */
    public function call(\OxidEsales\Eshop\Core\OnlineRequest $oRequest)
    {
        $sOutputXml = null;
        $iFailedCallsCount = \OxidEsales\Eshop\Core\Registry::getConfig()->getSystemConfigParameter('iFailedOnlineCallsCount');
        try {
            $sXml = $this->_formXMLRequest($oRequest);
            $sOutputXml = $this->_executeCurlCall($this->_getServiceUrl(), $sXml);
            $statusCode = $this->_getCurl()->getStatusCode();
            if ($statusCode != 200) {
                /** @var \OxidEsales\Eshop\Core\Exception\StandardException $oException */
                $oException = new StandardException('cUrl call to ' . $this->_getCurl()->getUrl() . ' failed with HTTP status ' . $statusCode);
                throw $oException;
            }
            $this->_resetFailedCallsCount($iFailedCallsCount);
        } catch (Exception $oEx) {
            if ($iFailedCallsCount > self::ALLOWED_HTTP_FAILED_CALLS_COUNT) {
                $this->_castExceptionAndWriteToLog($oEx);
                $sXml = $this->_formEmail($oRequest);
                $this->_sendEmail($sXml);
                $this->_resetFailedCallsCount($iFailedCallsCount);
            } else {
                $this->_increaseFailedCallsCount($iFailedCallsCount);
            }
        }

        return $sOutputXml;
    }
    /**
     * @deprecated use self::castExceptionAndWriteToLog instead
     */
    protected function _castExceptionAndWriteToLog(\Exception $oEx) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::castExceptionAndWriteToLog($oEx);
    }

    /**
     * Depending on the type of exception, first cast the exception and then write it to log.
     *
     * @deprecated since v6.3 (2018-04-25); This method will be removed completely. Use Registry::getLogger() to log error messages in the future.
     *
     * @param \Exception $oEx
     */
    protected function castExceptionAndWriteToLog(\Exception $oEx)
    {
        if (!($oEx instanceof \OxidEsales\Eshop\Core\Exception\StandardException)) {
            $oOxException = oxNew(\OxidEsales\Eshop\Core\Exception\StandardException::class);
            $oOxException->setMessage($oEx->getMessage());
            $oOxException->debugOut();
        } else {
            $oEx->debugOut();
        }
    }
    /**
     * @deprecated use self::formEmail instead
     */
    protected function _formEmail($oRequest) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::formEmail($oRequest);
    }

    /**
     * Forms email.
     *
     * @param \OxidEsales\Eshop\Core\OnlineRequest $oRequest Request object from which email should be formed.
     *
     * @return string
     */
    protected function formEmail($oRequest)
    {
        return $this->_formXMLRequest($oRequest);
    }
    /**
     * @deprecated use self::formXMLRequest instead
     */
    protected function _formXMLRequest($oRequest) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::formXMLRequest($oRequest);
    }

    /**
     * Forms XML request.
     *
     * @param \OxidEsales\Eshop\Core\OnlineRequest $oRequest Request object from which server request should be formed.
     *
     * @return string
     */
    protected function formXMLRequest($oRequest)
    {
        return $this->_getSimpleXml()->objectToXml($oRequest, $this->_getXMLDocumentName());
    }
    /**
     * @deprecated use self::getSimpleXml instead
     */
    protected function _getSimpleXml() // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::getSimpleXml();
    }

    /**
     * Gets simple XML.
     *
     * @return \OxidEsales\Eshop\Core\SimpleXml
     */
    protected function getSimpleXml()
    {
        return $this->_oSimpleXml;
    }
    /**
     * @deprecated use self::getCurl instead
     */
    protected function _getCurl() // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::getCurl();
    }

    /**
     * Gets curl.
     *
     * @return \OxidEsales\Eshop\Core\Curl
     */
    protected function getCurl()
    {
        return $this->_oCurl;
    }
    /**
     * @deprecated use self::getEmailBuilder instead
     */
    protected function _getEmailBuilder() // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::getEmailBuilder();
    }

    /**
     * Gets email builder.
     *
     * @return \OxidEsales\Eshop\Core\OnlineServerEmailBuilder
     */
    protected function getEmailBuilder()
    {
        return $this->_oEmailBuilder;
    }
    /**
     * @deprecated use self::executeCurlCall instead
     */
    private function _executeCurlCall($sUrl, $sXml) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::executeCurlCall($sUrl, $sXml);
    }

    /**
     * Executes CURL call with given parameters.
     *
     * @param string $sUrl Server address to call to.
     * @param string $sXml Data to send. Currently OXID servers only accept XML formatted data.
     *
     * @return string
     */
    private function executeCurlCall($sUrl, $sXml)
    {
        $oCurl = $this->_getCurl();
        $oCurl->setMethod('POST');
        $oCurl->setUrl($sUrl);
        $oCurl->setParameters(['xmlRequest' => $sXml]);
        $oCurl->setOption(
            \OxidEsales\Eshop\Core\Curl::EXECUTION_TIMEOUT_OPTION,
            static::CURL_EXECUTION_TIMEOUT
        );

        return $oCurl->execute();
    }
    /**
     * @deprecated use self::sendEmail instead
     */
    private function _sendEmail($sBody) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::sendEmail($sBody);
    }

    /**
     * Sends an email with server information.
     *
     * @param string $sBody Mail content.
     */
    private function sendEmail($sBody)
    {
        $oEmail = $this->_getEmailBuilder()->build($sBody);
        $oEmail->send();
    }
    /**
     * @deprecated use self::resetFailedCallsCount instead
     */
    private function _resetFailedCallsCount($iFailedOnlineCallsCount) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::resetFailedCallsCount($iFailedOnlineCallsCount);
    }

    /**
     * Resets config parameter iFailedOnlineCallsCount if it's bigger than 0.
     *
     * @param int $iFailedOnlineCallsCount Amount of calls which previously failed.
     */
    private function resetFailedCallsCount($iFailedOnlineCallsCount)
    {
        if ($iFailedOnlineCallsCount > 0) {
            \OxidEsales\Eshop\Core\Registry::getConfig()->saveSystemConfigParameter('int', 'iFailedOnlineCallsCount', 0);
        }
    }
    /**
     * @deprecated use self::increaseFailedCallsCount instead
     */
    private function _increaseFailedCallsCount($iFailedOnlineCallsCount) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::increaseFailedCallsCount($iFailedOnlineCallsCount);
    }

    /**
     * increases failed calls count.
     *
     * @param int $iFailedOnlineCallsCount Amount of calls which previously failed.
     */
    private function increaseFailedCallsCount($iFailedOnlineCallsCount)
    {
        \OxidEsales\Eshop\Core\Registry::getConfig()->saveSystemConfigParameter('int', 'iFailedOnlineCallsCount', ++$iFailedOnlineCallsCount);
    }
}
