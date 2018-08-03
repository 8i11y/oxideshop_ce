<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */
namespace OxidEsales\EshopCommunity\Core;

use oxException;
use OxidEsales\Eshop\Core\Contract\IDisplayError;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleTemplateBlockContentReader;
use OxidEsales\Eshop\Core\Module\ModuleTemplateBlockPathFormatter;
use OxidEsales\Eshop\Core\Module\ModuleTemplateBlockRepository;
use OxidEsales\Eshop\Core\Module\ModuleVariablesLocator;
use OxidEsales\Eshop\Core\Module\ModuleSmartyPluginDirectoryRepository;
use OxidEsales\Eshop\Core\ShopIdCalculator as EshopShopIdCalculator;
use OxidEsales\EshopCommunity\Internal\Templating\TemplateEngineBridgeInterface;
use Smarty;

/**
 * View utility class
 */
class UtilsView extends \OxidEsales\Eshop\Core\Base
{
    /**
     * Templates directories array
     *
     * @var array
     */
    protected $_aTemplateDir = [];

    /**
     * Templates directories array
     *
     * @var array
     */
    protected $_blIsTplBlocks = null;

    /**
     * Active module Ids array
     *
     * @var array
     */
    protected $_aActiveModuleInfo = null;

    /** @var \OxidEsales\Eshop\Core\ShopIdCalculator */
    private $shopIdCalculator;

    /**
     * Returns rendered template output. According to debug configuration outputs
     * debug information.
     *
     * @param string $templateName template file name
     * @param object $oObject      object, witch template we wish to output
     *
     * @return string
     */
    public function getTemplateOutput($templateName, $oObject)
    {
        $debugMode = $this->getConfig()->getConfigParam('iDebug');

        // assign
        $viewData = $oObject->getViewData();
        if (is_array($viewData)) {
            foreach (array_keys($viewData) as $viewName) {
                // show debug information
                if ($debugMode == 4) {
                    echo("TemplateData[$viewName] : \n");
                    var_export($viewData[$viewName]);
                }
            }
        } else {
            $viewData = [];
        }

        $template = $this->getContainer()->get(TemplateEngineBridgeInterface::class);
        return $template->renderTemplate($templateName, $viewData);
    }

    /**
     * adds the given errors to the view array
     *
     * @param array $aView  view data array
     * @param array $errors array of errors to pass to view
     */
    public function passAllErrorsToView(&$aView, $errors)
    {
        if (count($errors) > 0) {
            foreach ($errors as $sLocation => $aEx2) {
                foreach ($aEx2 as $sKey => $oEr) {
                    $aView['Errors'][$sLocation][$sKey] = unserialize($oEr);
                }
            }
        }
    }

    /**
     * Adds an exception to the array of displayed exceptions for the view
     * by default is displayed in the inc_header, but with the custom destination set to true
     * the exception won't be displayed by default but can be displayed where ever wanted in the tpl
     *
     * @param StandardException|IDisplayError|string $oEr                  an exception object or just a language local (string),
     *                                                                     which will be converted into a oxExceptionToDisplay object
     * @param bool                                   $blFull               if true the whole object is add to display (default false)
     * @param bool                                   $useCustomDestination true if the exception shouldn't be displayed
     *                                                                     at the default position (default false)
     * @param string                                 $customDestination    defines a name of the view variable containing
     *                                                                     the messages, overrides Parameter 'CustomError' ("default")
     * @param string                                 $activeController     defines a name of the controller, which should
     *                                                                     handle the error.
     */
    public function addErrorToDisplay($oEr, $blFull = false, $useCustomDestination = false, $customDestination = "", $activeController = "")
    {
        //default
        $destination = 'default';
        $customDestination = $customDestination ? $customDestination : \OxidEsales\Eshop\Core\Registry::getConfig()->getRequestParameter('CustomError');
        if ($useCustomDestination && $customDestination) {
            $destination = $customDestination;
        }

        //starting session if not yet started as all exception
        //messages are stored in session
        $session = $this->getSession();
        if (!$session->getId() && !$session->isHeaderSent()) {
            $session->setForceNewSession();
            $session->start();
        }

        $aEx = \OxidEsales\Eshop\Core\Registry::getSession()->getVariable('Errors');
        if ($oEr instanceof \OxidEsales\Eshop\Core\Exception\StandardException) {
            $oEx = oxNew(\OxidEsales\Eshop\Core\Exception\ExceptionToDisplay::class);
            $oEx->setMessage($oEr->getMessage());
            $oEx->setExceptionType($oEr->getType());

            if ($oEr instanceof \OxidEsales\Eshop\Core\Exception\SystemComponentException) {
                $oEx->setMessageArgs($oEr->getComponent());
            }

            $oEx->setValues($oEr->getValues());
            $oEx->setStackTrace($oEr->getTraceAsString());
            $oEx->setDebug($blFull);
            $oEr = $oEx;
        } elseif ($oEr && !($oEr instanceof \OxidEsales\Eshop\Core\Contract\IDisplayError)) {
            // assuming that a string was given
            $sTmp = $oEr;
            $oEr = oxNew(\OxidEsales\Eshop\Core\DisplayError::class);
            $oEr->setMessage($sTmp);
        } elseif ($oEr instanceof \OxidEsales\Eshop\Core\Contract\IDisplayError) {
            // take the object
        } else {
            $oEr = null;
        }

        if ($oEr) {
            $aEx[$destination][] = serialize($oEr);
            \OxidEsales\Eshop\Core\Registry::getSession()->setVariable('Errors', $aEx);

            if ($activeController == '') {
                $activeController = \OxidEsales\Eshop\Core\Registry::getConfig()->getRequestParameter('actcontrol');
            }
            if ($activeController) {
                $aControllerErrors[$destination] = $activeController;
                \OxidEsales\Eshop\Core\Registry::getSession()->setVariable('ErrorController', $aControllerErrors);
            }
        }
    }

    /**
     * Runs long description through smarty. If you pass array of data
     * to process, array will be returned, if you pass string - string
     * will be passed as result
     *
     * @param mixed                                            $sDesc       description or array of descriptions
     *                                                                      (array( [] => array(_ident_, _value_to_process_)))
     * @param string                                           $sOxid       current object id
     * @param \OxidEsales\Eshop\Core\Controller\BaseController $oActView    view data to use its view data (optional)
     * @param bool                                             $blRecompile force to recompile if found in cache
     *
     * @return mixed
     */
    public function parseThroughSmarty($sDesc, $sOxid = null, $oActView = null, $blRecompile = false)
    {
        if (\OxidEsales\Eshop\Core\Registry::getConfig()->isDemoShop()) {
            return $sDesc;
        }

        startProfile("parseThroughSmarty");

        if (!is_array($sDesc) && strpos($sDesc, "[{") === false) {
            stopProfile("parseThroughSmarty");

            return $sDesc;
        }

        $activeLanguageId = \OxidEsales\Eshop\Core\Registry::getLang()->getTplLanguage();

        // now parse it through smarty
        $templating = $this->getContainer()->get(TemplateEngineBridgeInterface::class);

        // save old tpl data
        $forceRecompile = $templating->getEngine()->force_compile;

        $templating->getEngine()->force_compile = $blRecompile;

        if (!$oActView) {
            $oActView = oxNew(\OxidEsales\Eshop\Application\Controller\FrontendController::class);
            $oActView->addGlobalParams();
        }

        $viewData = $oActView->getViewData();

        if (is_array($sDesc)) {
            foreach ($sDesc as $name => $aData) {
                $templating->getEngine()->oxidcache = new \OxidEsales\Eshop\Core\Field($aData[1], \OxidEsales\Eshop\Core\Field::T_RAW);
                $result[$name] = $templating->renderTemplate("ox:" . $aData[0] . $activeLanguageId, $viewData);
            }
        } else {
            $templating->getEngine()->oxidcache = new \OxidEsales\Eshop\Core\Field($sDesc, \OxidEsales\Eshop\Core\Field::T_RAW);
            $result = $templating->renderTemplate("ox:{$sOxid}{$activeLanguageId}", $viewData);
        }

        // restore tpl vars for continuing smarty processing if it is in one
        $templating->getEngine()->force_compile = $forceRecompile;

        stopProfile("parseThroughSmarty");

        return $result;
    }

    /**
     * Templates directory setter
     *
     * @param string $templatesDirectory templates path
     */
    public function setTemplateDir($templatesDirectory)
    {
        if ($templatesDirectory && !in_array($templatesDirectory, $this->_aTemplateDir)) {
            $this->_aTemplateDir[] = $templatesDirectory;
        }
    }

    /**
     * Initializes and returns templates directory info array
     *
     * @return array
     */
    public function getTemplateDirs()
    {
        $config = \OxidEsales\Eshop\Core\Registry::getConfig();

        // buffer for CE (main) edition templates
        $mainTemplatesDirectory = $config->getTemplateDir($this->isAdmin());

        // main templates directory has not much priority anymore
        $this->setTemplateDir($mainTemplatesDirectory);

        // out directory can have templates too
        if (!$this->isAdmin()) {
            $this->setTemplateDir($this->addActiveThemeId($config->getOutDir(true)));
        }

        return $this->_aTemplateDir;
    }

    /**
     * Get template compile id.
     *
     * @return string
     */
    public function getTemplateCompileId()
    {
        $shopId = $this->getConfig()->getShopId();
        $templateDirectories = $this->getTemplateDirs();

        return md5(reset($templateDirectories) . '__' . $shopId);
    }

    /**
     * Returns a full path to Smarty compile dir
     *
     * @return string
     */
    public function getSmartyDir()
    {
        $config = $this->getConfig();

        //check for the Smarty dir
        $compileDir = $config->getConfigParam('sCompileDir');
        $smartyDir = $compileDir . "/smarty/";
        if (!is_dir($smartyDir)) {
            @mkdir($smartyDir);
        }

        if (!is_writable($smartyDir)) {
            $smartyDir = $compileDir;
        }

        return $smartyDir;
    }

    /**
     * @return array
     */
    public function getShopSmartyPluginDirectories()
    {
        $coreDirectory = $this
            ->getConfig()
            ->getConfigParam('sCoreDir');

        return [
            $coreDirectory . 'Smarty/Plugin',
        ];
    }

    /**
     * Retrieve module block contents from active module block file.
     *
     * @param string $moduleId active module id.
     * @param string $fileName module block file name.
     *
     * @deprecated since v6.0.0 (2016-04-13); Use ModuleTemplateBlockContentReader::getContent().
     *
     * @see getTemplateBlocks
     * @throws oxException if block is not found
     *
     * @return string
     */
    protected function _getTemplateBlock($moduleId, $fileName)
    {
        $pathFormatter = oxNew(ModuleTemplateBlockPathFormatter::class);
        $pathFormatter->setModulesPath($this->getConfig()->getModulesDir());
        $pathFormatter->setModuleId($moduleId);
        $pathFormatter->setFileName($fileName);

        $blockContentReader = oxNew(ModuleTemplateBlockContentReader::class);

        return $blockContentReader->getContent($pathFormatter);
    }

    /**
     * Template blocks getter: retrieve sorted blocks for overriding in templates
     *
     * @param string $templateFileName filename of rendered template
     *
     * @see smarty_prefilter_oxblock
     *
     * @return array
     */
    public function getTemplateBlocks($templateFileName)
    {
        $templateBlocksWithContent = [];

        $config = $this->getConfig();

        $tplDir = trim($config->getConfigParam('_sTemplateDir'), '/\\');
        $templateFileName = str_replace(['\\', '//'], '/', $templateFileName);
        if (preg_match('@/' . preg_quote($tplDir, '@') . '/(.*)$@', $templateFileName, $m)) {
            $templateFileName = $m[1];
        }

        if ($this->isShopTemplateBlockOverriddenByActiveModule()) {
            $shopId = $config->getShopId();

            $ids = $this->_getActiveModuleInfo();

            $activeModulesId = array_keys($ids);
            $activeThemeIds = oxNew(\OxidEsales\Eshop\Core\Theme::class)->getActiveThemesList();

            $templateBlockRepository = oxNew(ModuleTemplateBlockRepository::class);
            $activeBlockTemplates = $templateBlockRepository->getBlocks($templateFileName, $activeModulesId, $shopId, $activeThemeIds);

            if ($activeBlockTemplates) {
                $activeBlockTemplatesByTheme = $this->filterTemplateBlocks($activeBlockTemplates);

                $templateBlocksWithContent = $this->fillTemplateBlockWithContent($activeBlockTemplatesByTheme);
            }
        }

        return $templateBlocksWithContent;
    }

    /**
     * Returns active module Ids
     *
     * @return array
     */
    protected function _getActiveModuleInfo()
    {
        if ($this->_aActiveModuleInfo === null) {
            $modulelist = oxNew(\OxidEsales\Eshop\Core\Module\ModuleList::class);
            $this->_aActiveModuleInfo = $modulelist->getActiveModuleInfo();
        }

        return $this->_aActiveModuleInfo;
    }

    /**
     * Add active theme at the end of theme path to form full path to templates.
     *
     * @param string $themePath
     *
     * @return string
     */
    protected function addActiveThemeId($themePath)
    {
        $themeId = $this->getConfig()->getConfigParam('sTheme');
        if ($this->isAdmin()) {
            $themeId = 'admin';
        }

        return $themePath . $themeId . "/tpl/";
    }

    /**
     * Leave only one element for items grouped by fields: OXTEMPLATE and OXBLOCKNAME
     *
     * Pick only one element from each group if OXTHEME contains (by following priority):
     * - Active theme id
     * - Parent theme id of active theme
     * - Undefined
     *
     * Example of $activeBlockTemplates:
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_a" (group a)
     *  OXTHEME = ""
     *  "content_a_default"
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_a" (group a)
     *  OXTHEME = "parent_of_active_theme"
     *  "content_a_parent"
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_a" (group a)
     *  OXTHEME = "active_theme"
     *  "content_a_active"
     *
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_b" (group b)
     *  OXTHEME = ""
     *  "content_b_default"
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_b" (group b)
     *  OXTHEME = "parent_of_active_theme"
     *  "content_b_parent"
     *
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_c" (group c)
     *  OXTHEME = ""
     *  OXFILE = "x"
     *  "content_c_x_default"
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_c" (group c)
     *  OXTHEME = ""
     *  OXFILE = "y"
     *  "content_c_y_default"
     *
     * Example of return:
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_a" (group a)
     *  OXTHEME = "active_theme"
     *  "content_a_active"
     *
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_b" (group b)
     *  OXTHEME = "parent_of_active_theme"
     *  "content_b_parent"
     *
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_c" (group c)
     *  OXTHEME = ""
     *  OXFILE = "x"
     *  "content_c_x_default"
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_c" (group c)
     *  OXTHEME = ""
     *  OXFILE = "y"
     *  "content_c_y_default"
     *
     * @param array $activeBlockTemplates list of template blocks with all parameters.
     *
     * @return array list of blocks with their content.
     */
    private function filterTemplateBlocks($activeBlockTemplates)
    {
        $templateBlocks = $activeBlockTemplates;

        $templateBlocksToExchange = $this->formListOfDuplicatedBlocks($activeBlockTemplates);

        if ($templateBlocksToExchange['theme']) {
            $templateBlocks = $this->removeDefaultBlocks($activeBlockTemplates, $templateBlocksToExchange);
        }

        if ($templateBlocksToExchange['custom_theme']) {
            $templateBlocks = $this->removeParentBlocks($templateBlocks, $templateBlocksToExchange);
        }

        return $templateBlocks;
    }

    /**
     * Form list of blocks which has duplicates for specific theme.
     *
     * @param array $activeBlockTemplates
     *
     * @return array
     */
    private function formListOfDuplicatedBlocks($activeBlockTemplates)
    {
        $templateBlocksToExchange = [];
        $customThemeId = $this->getConfig()->getConfigParam('sCustomTheme');

        foreach ($activeBlockTemplates as $activeBlockTemplate) {
            if ($activeBlockTemplate['OXTHEME']) {
                if ($customThemeId && $customThemeId === $activeBlockTemplate['OXTHEME']) {
                    $templateBlocksToExchange['custom_theme'][] = $this->prepareBlockKey($activeBlockTemplate);
                } else {
                    $templateBlocksToExchange['theme'][] = $this->prepareBlockKey($activeBlockTemplate);
                }
            }
        }

        return $templateBlocksToExchange;
    }

    /**
     * Remove default blocks whose have duplicate for specific theme.
     *
     * @param array $activeBlockTemplates
     * @param array $templateBlocksToExchange
     *
     * @return array
     */
    private function removeDefaultBlocks($activeBlockTemplates, $templateBlocksToExchange)
    {
        $templateBlocks = [];
        foreach ($activeBlockTemplates as $activeBlockTemplate) {
            if (!in_array($this->prepareBlockKey($activeBlockTemplate), $templateBlocksToExchange['theme'])
                || $activeBlockTemplate['OXTHEME']
            ) {
                $templateBlocks[] = $activeBlockTemplate;
            }
        }

        return $templateBlocks;
    }

    /**
     * Remove parent theme blocks whose have duplicate for custom theme.
     *
     * @param array $templateBlocks
     * @param array $templateBlocksToExchange
     *
     * @return array
     */
    private function removeParentBlocks($templateBlocks, $templateBlocksToExchange)
    {
        $activeBlockTemplates = $templateBlocks;
        $templateBlocks = [];
        $customThemeId = $this->getConfig()->getConfigParam('sCustomTheme');
        foreach ($activeBlockTemplates as $activeBlockTemplate) {
            if (!in_array($this->prepareBlockKey($activeBlockTemplate), $templateBlocksToExchange['custom_theme'])
                || $activeBlockTemplate['OXTHEME'] === $customThemeId
            ) {
                $templateBlocks[] = $activeBlockTemplate;
            }
        }

        return $templateBlocks;
    }

    /**
     * Fill array with template content or skip if template does not exist.
     * Logs error message if template does not exist.
     *
     * Example of $activeBlockTemplates:
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_a"
     *  "content_a_active"
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_b"
     *  OXFILE = "x"
     *  "content_b_x_default"
     *
     *  OXTEMPLATE = "requested_template_name.tpl"  OXBLOCKNAME = "block_name_b"
     *  OXFILE = "y"
     *  "content_b_y_default"
     *
     * Example of return:
     *
     * $templateBlocks = [
     *   block_name_a = [
     *     0 => "content_a_active"
     *   ],
     *   block_name_c = [
     *     0 => "content_b_x_default",
     *     1 => "content_b_y_default"
     *   ]
     * ]
     *
     * @param array $blockTemplates
     *
     * @return array
     */
    private function fillTemplateBlockWithContent($blockTemplates)
    {
        $templateBlocksWithContent = [];

        foreach ($blockTemplates as $activeBlockTemplate) {
            try {
                if (!is_array($templateBlocksWithContent[$activeBlockTemplate['OXBLOCKNAME']])) {
                    $templateBlocksWithContent[$activeBlockTemplate['OXBLOCKNAME']] = [];
                }
                $templateBlocksWithContent[$activeBlockTemplate['OXBLOCKNAME']][] = $this->_getTemplateBlock($activeBlockTemplate['OXMODULE'], $activeBlockTemplate['OXFILE']);
            } catch (\OxidEsales\Eshop\Core\Exception\StandardException $exception) {
                \OxidEsales\Eshop\Core\Registry::getLogger()->error($exception->getMessage(), [$exception]);
            }
        }

        return $templateBlocksWithContent;
    }

    /**
     * Check if at least one active module overrides at least one template (in active shop).
     * To win performance when:
     * - no active modules exists.
     * - none active module overrides template.
     *
     * @return bool
     */
    private function isShopTemplateBlockOverriddenByActiveModule()
    {
        if ($this->_blIsTplBlocks !== null) {
            return $this->_blIsTplBlocks;
        }

        $moduleOverridesTemplate = false;

        $ids = $this->_getActiveModuleInfo();
        if (count($ids)) {
            $templateBlockRepository = oxNew(ModuleTemplateBlockRepository::class);
            $shopId = $this->getConfig()->getShopId();
            $activeModulesId = array_keys($ids);
            $blocksCount = $templateBlockRepository->getBlocksCount($activeModulesId, $shopId);

            if ($blocksCount) {
                $moduleOverridesTemplate = true;
            }
        }

        $this->_blIsTplBlocks = $moduleOverridesTemplate;

        return $moduleOverridesTemplate;
    }

    /**
     * Prepare indicator for template block.
     * This indicator might be used to identify same template block for different theme.
     *
     * @param array $activeBlockTemplate
     *
     * @return string
     */
    private function prepareBlockKey($activeBlockTemplate)
    {
        return $activeBlockTemplate['OXTEMPLATE'] . $activeBlockTemplate['OXBLOCKNAME'];
    }

    /**
     * @return array
     */
    public function getModuleSmartyPluginDirectories()
    {
        $moduleSmartyPluginDirectoryRepository = $this->getSmartyPluginDirectoryRepository();
        $moduleSmartyPluginDirectories = $moduleSmartyPluginDirectoryRepository->get();

        return $moduleSmartyPluginDirectories->getWithFullPath();
    }

    /**
     * @return ModuleSmartyPluginDirectoryRepository
     */
    private function getSmartyPluginDirectoryRepository()
    {
        $subShopSpecificCache = oxNew(
            \OxidEsales\Eshop\Core\SubShopSpecificFileCache::class,
            $this->getShopIdCalculator()
        );

        $moduleVariablesLocator = oxNew(
            ModuleVariablesLocator::class,
            $subShopSpecificCache,
            $this->getShopIdCalculator()
        );

        return oxNew(
            ModuleSmartyPluginDirectoryRepository::class,
            $this->getConfig(),
            $moduleVariablesLocator,
            oxNew(Module::class)
        );
    }

    /**
     * @return EshopShopIdCalculator
     */
    private function getShopIdCalculator()
    {
        if (is_null($this->shopIdCalculator)) {
            $moduleVariablesCache = oxNew(\OxidEsales\Eshop\Core\FileCache::class);

            $this->shopIdCalculator = oxNew(
                EshopShopIdCalculator::class,
                $moduleVariablesCache
            );
        }
        return $this->shopIdCalculator;
    }
}
