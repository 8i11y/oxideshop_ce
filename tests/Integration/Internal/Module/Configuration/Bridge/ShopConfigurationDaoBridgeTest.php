<?php declare(strict_types=1);
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Tests\Integration\Internal\Module\Configuration\Bridge;

use OxidEsales\EshopCommunity\Internal\Common\Storage\FileStorageFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\Bridge\ShopConfigurationDaoBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataMapper\ModuleConfiguration\ModuleSettingsDataMapper;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ModuleConfiguration;
use OxidEsales\EshopCommunity\Internal\Module\Configuration\DataObject\ShopConfiguration;
use OxidEsales\EshopCommunity\Internal\Module\Setting\Setting;
use OxidEsales\EshopCommunity\Internal\Utility\ContextInterface;
use OxidEsales\EshopCommunity\Tests\Integration\Internal\ContainerTrait;
use PHPUnit\Framework\TestCase;

class ShopConfigurationDaoBridgeTest extends TestCase
{
    use ContainerTrait;
    /**
     * @var string
     */
    private $testModuleId = 'testModuleId';

    public function testSaving(): void
    {
        $shopConfigurationDaoBridge = $this->get(ShopConfigurationDaoBridgeInterface::class);

        $someModule = new ModuleConfiguration();
        $someModule
            ->setId('someId')
            ->setPath('somePath');

        $shopConfiguration = new ShopConfiguration();
        $shopConfiguration->addModuleConfiguration($someModule);

        $shopConfigurationDaoBridge->save($shopConfiguration);

        $this->assertEquals(
            $shopConfiguration,
            $shopConfigurationDaoBridge->get()
        );
    }

    public function testSavingRemoveEnvironmentFile(): void
    {
        $shopConfigurationDaoBridge = $this->get(ShopConfigurationDaoBridgeInterface::class);

        $originalSetting = new Setting();
        $originalSetting
            ->setName('settingToOverwrite')
            ->setValue('originalValue')
            ->setType('int');

        $module = new ModuleConfiguration();
        $module
            ->setId($this->testModuleId)
            ->setPath('test')
            ->addModuleSetting($originalSetting);

        $shopConfiguration = new ShopConfiguration();
        $shopConfiguration->addModuleConfiguration($module);
        $shopConfigurationDaoBridge->save($shopConfiguration);

        $this->prepareTestEnvironmentShopConfigurationFile();

        $this->assertSame(
            'overwrittenValue',
            $shopConfigurationDaoBridge
                ->get()
                ->getModuleConfiguration($this->testModuleId)
                ->getModuleSetting('settingToOverwrite')
                ->getValue()
        );

        $shopConfigurationDaoBridge->save($shopConfiguration);

        $this->assertSame(
            'originalValue',
            $shopConfigurationDaoBridge
                ->get()
                ->getModuleConfiguration($this->testModuleId)
                ->getModuleSetting('settingToOverwrite')
                ->getValue()
        );
    }

    private function prepareTestEnvironmentShopConfigurationFile(): void
    {
        $fileStorageFactory = $this->get(FileStorageFactoryInterface::class);
        $storage = $fileStorageFactory->create(
            $this->get(ContextInterface::class)
                ->getProjectConfigurationDirectory() . 'environment/1.yaml'
        );

        $storage->save([
            'modules' => [
                $this->testModuleId => [
                    ModuleSettingsDataMapper::MAPPING_KEY => [
                        'settingToOverwrite' => [
                            'value' => 'overwrittenValue',
                        ]
                    ]
                ]
            ]
        ]);
    }
}
