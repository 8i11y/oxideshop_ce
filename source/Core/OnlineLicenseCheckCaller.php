<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Core;

use oxException;

/**
 * Class makes call to given URL address and sends request parameter.
 *
 * @internal Do not make a module extension for this class.
 * @see      https://oxidforge.org/en/core-oxid-eshop-classes-must-not-be-extended.html
 *
 * @ignore   This class will not be included in documentation.
 */
class OnlineLicenseCheckCaller extends \OxidEsales\Eshop\Core\OnlineCaller
{
    /** Online License Key Check web service url. */
    const WEB_SERVICE_URL = 'https://olc.oxid-esales.com/check.php';

    /** XML document tag name. */
    const XML_DOCUMENT_NAME = 'olcRequest';

    /**
     * Expected response element in the XML response message fom web service.
     *
     * @var string
     */
    private $_sResponseElement = 'olc';

    /**
     * Performs Web service request
     *
     * @param \OxidEsales\Eshop\Core\OnlineLicenseCheckRequest $oRequest Object with request parameters
     *
     * @throws oxException
     * @return \OxidEsales\Eshop\Core\OnlineLicenseCheckResponse
     */
    public function doRequest(\OxidEsales\Eshop\Core\OnlineLicenseCheckRequest $oRequest)
    {
        return $this->_formResponse($this->call($oRequest));
    }

    /**
     * @deprecated use self::formEmail instead
     */
    protected function _formEmail($oRequest) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::formEmail($oRequest);
    }

    /**
     * Removes serial keys from request and forms email body.
     *
     * @param \OxidEsales\Eshop\Core\OnlineLicenseCheckRequest $oRequest
     *
     * @return string
     */
    protected function formEmail($oRequest)
    {
        $oRequest->keys = null;

        return parent::_formEmail($oRequest);
    }
    /**
     * @deprecated use self::formResponse instead
     */
    protected function _formResponse($sRawResponse) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::formResponse($sRawResponse);
    }

    /**
     * Parse response message received from Online License Key Check web service and save it to response object.
     *
     * @param string $sRawResponse UnResponse from server
     *
     * @throws oxException
     *
     * @return \OxidEsales\Eshop\Core\OnlineLicenseCheckResponse
     */
    protected function formResponse($sRawResponse)
    {
        /** @var \OxidEsales\Eshop\Core\UtilsXml $oUtilsXml */
        $oUtilsXml = \OxidEsales\Eshop\Core\Registry::getUtilsXml();
        if (empty($sRawResponse) || !($oDomDoc = $oUtilsXml->loadXml($sRawResponse))) {
            throw new \OxidEsales\Eshop\Core\Exception\StandardException('OLC_ERROR_RESPONSE_NOT_VALID');
        }

        if ($oDomDoc->documentElement->nodeName != $this->_sResponseElement) {
            throw new \OxidEsales\Eshop\Core\Exception\StandardException('OLC_ERROR_RESPONSE_UNEXPECTED');
        }

        $oResponseNode = $oDomDoc->firstChild;

        if (!$oResponseNode->hasChildNodes()) {
            throw new \OxidEsales\Eshop\Core\Exception\StandardException('OLC_ERROR_RESPONSE_NOT_VALID');
        }

        $oNodes = $oResponseNode->childNodes;

        /** @var \OxidEsales\Eshop\Core\OnlineLicenseCheckResponse $oResponse */
        $oResponse = oxNew(\OxidEsales\Eshop\Core\OnlineLicenseCheckResponse::class);

        // iterate through response node to get response parameters
        for ($i = 0; $i < $oNodes->length; $i++) {
            $sNodeName = $oNodes->item($i)->nodeName;
            $sNodeValue = $oNodes->item($i)->nodeValue;
            $oResponse->$sNodeName = $sNodeValue;
        }

        return $oResponse;
    }
    /**
     * @deprecated use self::getXMLDocumentName instead
     */
    protected function _getXMLDocumentName() // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::getXMLDocumentName();
    }

    /**
     * Gets XML document name.
     *
     * @return string XML document tag name.
     */
    protected function getXMLDocumentName()
    {
        return self::XML_DOCUMENT_NAME;
    }
    /**
     * @deprecated use self::getServiceUrl instead
     */
    protected function _getServiceUrl() // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::getServiceUrl();
    }

    /**
     * Gets service url.
     *
     * @return string Web service url.
     */
    protected function getServiceUrl()
    {
        return self::WEB_SERVICE_URL;
    }
}
