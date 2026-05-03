<?php

/**
 * @file plugins/generic/reachmetricLite/ReachmetricLiteSettingsForm.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReachmetricLiteSettingsForm
 *
 * @ingroup plugins_generic_reachmetricLite
 *
 * @brief Settings form for the Reachmetric Lite plugin.
 */

namespace APP\plugins\generic\reachmetricLite;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorRegExp;

class ReachmetricLiteSettingsForm extends Form
{
    /** @var ReachmetricLitePlugin */
    public $plugin;

    public function __construct($plugin)
    {
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->plugin = $plugin;

        $colorPattern = '/^#[0-9a-fA-F]{3,8}$/';
        $this->addCheck(new FormValidatorRegExp($this, 'badgeBg', 'optional', 'plugins.generic.reachmetricLite.error.color', $colorPattern));
        $this->addCheck(new FormValidatorRegExp($this, 'badgeColor', 'optional', 'plugins.generic.reachmetricLite.error.color', $colorPattern));
        $this->addCheck(new FormValidatorRegExp($this, 'badgeHoverBg', 'optional', 'plugins.generic.reachmetricLite.error.color', $colorPattern));
        $this->addCheck(new FormValidatorRegExp($this, 'badgeHoverColor', 'optional', 'plugins.generic.reachmetricLite.error.color', $colorPattern));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData()
    {
        $contextId = $this->getContextId();
        $plugin = $this->plugin;
        $this->setData('badgeBg', $plugin->getSetting($contextId, 'badgeBg') ?: '#ffffff');
        $this->setData('badgeColor', $plugin->getSetting($contextId, 'badgeColor') ?: '#000000');
        $this->setData('badgeHoverBg', $plugin->getSetting($contextId, 'badgeHoverBg') ?: '#000000');
        $this->setData('badgeHoverColor', $plugin->getSetting($contextId, 'badgeHoverColor') ?: '#ffffff');
        $showAbs = $plugin->getSetting($contextId, 'showAbstractViews');
        $showPdf = $plugin->getSetting($contextId, 'showPdfDownloads');
        $this->setData('showAbstractViews', $showAbs === null ? 0 : (int) $showAbs);
        $this->setData('showPdfDownloads', $showPdf === null ? 0 : (int) $showPdf);
    }

    public function readInputData()
    {
        $this->readUserVars([
            'badgeBg',
            'badgeColor',
            'badgeHoverBg',
            'badgeHoverColor',
            'showAbstractViews',
            'showPdfDownloads',
        ]);
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign('rmlBrandingHtml', $this->plugin->getBrandingHtml());
        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs)
    {
        $contextId = $this->getContextId();
        $plugin = $this->plugin;
        $plugin->updateSetting($contextId, 'badgeBg', $this->getData('badgeBg') ?: '#ffffff', 'string');
        $plugin->updateSetting($contextId, 'badgeColor', $this->getData('badgeColor') ?: '#000000', 'string');
        $plugin->updateSetting($contextId, 'badgeHoverBg', $this->getData('badgeHoverBg') ?: '#000000', 'string');
        $plugin->updateSetting($contextId, 'badgeHoverColor', $this->getData('badgeHoverColor') ?: '#ffffff', 'string');
        $plugin->updateSetting($contextId, 'showAbstractViews', $this->getData('showAbstractViews') ? 1 : 0, 'int');
        $plugin->updateSetting($contextId, 'showPdfDownloads', $this->getData('showPdfDownloads') ? 1 : 0, 'int');
        return parent::execute(...$functionArgs);
    }

    private function getContextId()
    {
        $context = Application::get()->getRequest()->getContext();
        return $context ? (int) $context->getId() : \PKP\core\PKPApplication::CONTEXT_SITE;
    }
}
