<?php
/**
 * This file is part of OXID eShop Community Edition.
 *
 * OXID eShop Community Edition is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID eShop Community Edition is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID eShop Community Edition.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2014
 * @version   OXID eShop CE
 */

require_once realpath( "." ).'/unit/OxidTestCase.php';
require_once realpath( "." ).'/unit/test_config.inc.php';

class Unit_Core_oxUserAddressListTest extends OxidTestCase
{
    public $aList = array();

    /**
     * Initialize the fixture.
     *
     * @return null
     */
    protected function setUp()
    {
        parent::setUp();

        $oSubj = new oxAddress();
        $oSubj->setId('__testAddress');
        $oSubj->oxaddress__oxuserid = new oxField('oxdefaultadmin');
        // Set country Austria as this country has different name in english and germany.
        $oSubj->oxaddress__oxcountryid = new oxField('a7c40f6320aeb2ec2.72885259');
        $oSubj->oxaddress__oxfname = new oxField('Fname');
        $oSubj->oxaddress__oxlname = new oxField('Lname');
        $oSubj->oxaddress__oxstreet = new oxField('Street');
        $oSubj->oxaddress__oxstreetnr = new oxField('StreetNr');
        $oSubj->oxaddress__oxcity = new oxField('Kaunas');
        $oSubj->save();
    }

    /**
     * Tear down the fixture.
     *
     * @return null
     */
    protected function tearDown()
    {
        $this->cleanUpTable('oxaddress');

        /*
        foreach ( $this->aList as $oCountry )
            $oCountry->delete();
        */
        parent::tearDown();
    }

    public function providerLoadActiveAddress()
    {
        return array(
            array(0, '�sterreich'),
            array(1, 'Austria'),
        );
    }

    /**
     * Tests selectString and _localCompare
     *
     * @param int $iLanguageId
     * @param string $sCountryNameExpected
     *
     * @dataProvider providerLoadActiveAddress
     */
    public function testLoadActiveAddress($iLanguageId, $sCountryNameExpected)
    {
        oxRegistry::getLang()->setBaseLanguage( $iLanguageId );

        $sUserId = 'oxdefaultadmin';
        $oAddressList = new oxUserAddressList;
        $oAddressList->load($sUserId);

        $this->assertSame(1, count($oAddressList), 'User has one address created in test setup.');
        $this->assertSame($sCountryNameExpected, $oAddressList['__testAddress']->oxaddress__oxcountry->value, 'Country name is different in different language.');
    }
}
