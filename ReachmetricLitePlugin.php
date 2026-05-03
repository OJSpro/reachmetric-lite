<?php

/**
 * @file plugins/generic/reachmetricLite/ReachmetricLitePlugin.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReachmetricLitePlugin
 *
 * @ingroup plugins_generic_reachmetricLite
 *
 * @brief Reachmetric Lite — free, OJS-data-only badges for Abstract Views and
 *        PDF Downloads on issue and article pages.
 */

namespace APP\plugins\generic\reachmetricLite;

use APP\core\Application;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class ReachmetricLitePlugin extends GenericPlugin
{
    /** @var array<int,array{abs:int,pdf:int}>|null Per-request issue stat cache, keyed by submission id. */
    private static $issueStatsCache = null;

    /** @var int|null The issue id whose submissions are loaded into $issueStatsCache. */
    private static $issueStatsCacheIssueId = null;

    /** @var bool Whether shared CSS has been emitted on the current page. */
    private static $cssInjected = false;

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!$success) {
            return false;
        }

        if ($this->getEnabled($mainContextId)) {
            Hook::add('Templates::Issue::Issue::Article', [$this, 'displayIssueBadges']);
            Hook::add('Templates::Article::Main', [$this, 'displayArticleBadges']);
        }

        return true;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.reachmetricLite.displayName');
    }

    public function getDescription()
    {
        $request = \APP\core\Application::get()->getRequest();
        $baseUrl = $request->getBaseUrl();
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;
        $utmParams = '?utm_source=' . urlencode($host) . '&utm_medium=plugin_gallery_description&utm_campaign=direct';
        $upgradeUrl = 'https://ojspro.com/plugin/ojs/reachmetric-pro/' . $utmParams;

        return __('plugins.generic.reachmetricLite.description', ['upgradeUrl' => $upgradeUrl]);
    }

    /**
     * Add a Settings link to the plugin row in the plugin gallery.
     */
    public function getActions($request, $verb)
    {
        $actions = parent::getActions($request, $verb);
        if (!$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        $linkAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']
                ),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );
        array_unshift($actions, $linkAction);
        return $actions;
    }

    /**
     * Handle the Settings link click.
     */
    public function manage($args, $request)
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        $form = new ReachmetricLiteSettingsForm($this);

        if ($request->getUserVar('save')) {
            $form->readInputData();
            if ($form->validate()) {
                $form->execute();
                return new JSONMessage(true);
            }
        } else {
            $form->initData();
        }
        return new JSONMessage(true, $form->fetch($request));
    }

    /**
     * Render badges below each article on the issue TOC.
     *
     * Hook signature: Hook::call($name, [&$params, $smarty, &$output])
     */
    public function displayIssueBadges($hookName, $args)
    {
        $templateMgr = $args[1] ?? null;
        $output = &$args[2];
        if (!$templateMgr) {
            return Hook::CONTINUE;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return Hook::CONTINUE;
        }
        $contextId = (int) $context->getId();

        $article = $templateMgr->getTemplateVars('article');
        if (!$article && isset($templateMgr->tpl_vars['article'])) {
            $article = $templateMgr->tpl_vars['article']->value ?? null;
        }
        if (!is_object($article) || !method_exists($article, 'getId')) {
            return Hook::CONTINUE;
        }
        $submissionId = (int) $article->getId();
        if ($submissionId <= 0) {
            return Hook::CONTINUE;
        }

        $issue = $templateMgr->getTemplateVars('issue');
        if (!$issue && isset($templateMgr->tpl_vars['issue'])) {
            $issue = $templateMgr->tpl_vars['issue']->value ?? null;
        }
        $issueId = (is_object($issue) && method_exists($issue, 'getId')) ? (int) $issue->getId() : 0;

        $stats = $this->getIssueArticleStats($contextId, $issueId, $submissionId);
        $output .= $this->renderBadges($contextId, $stats, false);

        return Hook::CONTINUE;
    }

    /**
     * Render badges between keywords and abstract on the article detail page.
     *
     * Hook signature: Hook::call($name, [&$params, $smarty, &$output])
     */
    public function displayArticleBadges($hookName, $args)
    {
        $templateMgr = $args[1] ?? null;
        $output = &$args[2];
        if (!$templateMgr) {
            return Hook::CONTINUE;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return Hook::CONTINUE;
        }
        $contextId = (int) $context->getId();

        $article = $templateMgr->getTemplateVars('article');
        if (!$article && isset($templateMgr->tpl_vars['article'])) {
            $article = $templateMgr->tpl_vars['article']->value ?? null;
        }
        if (!is_object($article) || !method_exists($article, 'getId')) {
            return Hook::CONTINUE;
        }
        $submissionId = (int) $article->getId();
        if ($submissionId <= 0) {
            return Hook::CONTINUE;
        }

        $stats = $this->getSingleArticleStats($contextId, $submissionId);
        $output .= $this->renderBadges($contextId, $stats, true);

        return Hook::CONTINUE;
    }

    /**
     * Pre-fetch and cache stats for every published submission in the current
     * issue with one SQL query. Subsequent calls within the same request are
     * served from the in-memory cache.
     *
     * @return array{abs:int,pdf:int}
     */
    private function getIssueArticleStats($contextId, $issueId, $submissionId)
    {
        if ($issueId > 0 && (self::$issueStatsCache === null || self::$issueStatsCacheIssueId !== $issueId)) {
            self::$issueStatsCache = [];
            self::$issueStatsCacheIssueId = $issueId;

            try {
                // metrics_submission schema:
                //   assoc_type = ASSOC_TYPE_SUBMISSION (0x0100009 = 16777225) → abstract page views
                //   assoc_type = ASSOC_TYPE_SUBMISSION_FILE (0x0000203 = 515) → galley/PDF downloads
                // We pivot the single `metric` column by assoc_type.
                $assocSubmission = \APP\core\Application::ASSOC_TYPE_SUBMISSION;       // 16777225
                $assocFile       = \APP\core\Application::ASSOC_TYPE_SUBMISSION_FILE;  // 515

                $rows = DB::table('metrics_submission as ms')
                    ->join('publications as p', 'p.submission_id', '=', 'ms.submission_id')
                    ->where('p.issue_id', $issueId)
                    ->where('ms.context_id', $contextId)
                    ->groupBy('ms.submission_id')
                    ->select('ms.submission_id')
                    ->selectRaw('SUM(CASE WHEN ms.assoc_type = ? THEN ms.metric ELSE 0 END) as abs_views', [$assocSubmission])
                    ->selectRaw('SUM(CASE WHEN ms.assoc_type = ? THEN ms.metric ELSE 0 END) as pdf_views', [$assocFile])
                    ->get();

                foreach ($rows as $row) {
                    self::$issueStatsCache[(int) $row->submission_id] = [
                        'abs' => (int) ($row->abs_views ?? 0),
                        'pdf' => (int) ($row->pdf_views ?? 0),
                    ];
                }
            } catch (\Throwable $e) {
                // Stats table missing or query failed — fail silent, return zeros.
            }
        }

        if (isset(self::$issueStatsCache[$submissionId])) {
            return self::$issueStatsCache[$submissionId];
        }

        return $this->getSingleArticleStats($contextId, $submissionId);
    }

    /**
     * @return array{abs:int,pdf:int}
     */
    private function getSingleArticleStats($contextId, $submissionId)
    {
        try {
            $assocSubmission = \APP\core\Application::ASSOC_TYPE_SUBMISSION;      // 16777225
            $assocFile       = \APP\core\Application::ASSOC_TYPE_SUBMISSION_FILE; // 515

            $row = DB::table('metrics_submission')
                ->where('submission_id', $submissionId)
                ->where('context_id', $contextId)
                ->selectRaw(
                    'SUM(CASE WHEN assoc_type = ? THEN metric ELSE 0 END) as abs_views,'
                    . ' SUM(CASE WHEN assoc_type = ? THEN metric ELSE 0 END) as pdf_views',
                    [$assocSubmission, $assocFile]
                )
                ->first();
            return [
                'abs' => (int) ($row->abs_views ?? 0),
                'pdf' => (int) ($row->pdf_views ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['abs' => 0, 'pdf' => 0];
        }
    }

    /**
     * Build the badge markup. Emits shared CSS once per page render.
     *
     * @param array{abs:int,pdf:int} $stats
     * @param bool $large Article-detail page uses the larger size.
     */
    private function renderBadges($contextId, array $stats, $large)
    {
        $showAbsSetting = $this->getSetting($contextId, 'showAbstractViews');
        $showPdfSetting = $this->getSetting($contextId, 'showPdfDownloads');

        // Default to hiding both when settings have never been saved.
        $showAbs = ($showAbsSetting === null) ? false : ((int) $showAbsSetting === 1);
        $showPdf = ($showPdfSetting === null) ? false : ((int) $showPdfSetting === 1);

        if (!$showAbs && !$showPdf) {
            return '';
        }

        $cssBlock = $this->ensureCss($contextId);

        $sizeClass = $large ? 'rml-badges-lg' : 'rml-badges-sm';
        $html = $cssBlock . '<div class="rml-badges ' . $sizeClass . '" aria-label="Article statistics">';

        if ($showAbs) {
            $html .= '<span class="rml-badge"><span class="rml-badge-label">'
                . htmlspecialchars(__('plugins.generic.reachmetricLite.abstractViews'), ENT_QUOTES, 'UTF-8')
                . '</span><span class="rml-badge-sep"> </span><span class="rml-badge-num">' . number_format($stats['abs']) . '</span></span>';
        }
        if ($showPdf) {
            $html .= '<span class="rml-badge"><span class="rml-badge-label">'
                . htmlspecialchars(__('plugins.generic.reachmetricLite.pdfDownloads'), ENT_QUOTES, 'UTF-8')
                . '</span><span class="rml-badge-sep"> </span><span class="rml-badge-num">' . number_format($stats['pdf']) . '</span></span>';
        }

        $html .= '</div>';

        if ($large) {
            $html .= $this->articleRepositionScript();
        } else {
            $html .= $this->issueRepositionScript();
        }

        return $html;
    }

    private function ensureCss($contextId)
    {
        if (self::$cssInjected) {
            return '';
        }
        self::$cssInjected = true;

        $bg = $this->sanitizeColor($this->getSetting($contextId, 'badgeBg'), '#ffffff');
        $color = $this->sanitizeColor($this->getSetting($contextId, 'badgeColor'), '#000000');
        $hoverBg = $this->sanitizeColor($this->getSetting($contextId, 'badgeHoverBg'), '#000000');
        $hoverColor = $this->sanitizeColor($this->getSetting($contextId, 'badgeHoverColor'), '#ffffff');

        return '<style>'
            . '.rml-badges{display:flex;flex-wrap:wrap;gap:8px;margin:6px 0 10px 0;align-items:center;}'
            . '.rml-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;font-size:0.78rem;'
            . 'font-weight:600;background:' . $bg . ';color:' . $color . ';border:1px solid ' . $color . ';'
            . 'border-radius:3px;line-height:1.4;letter-spacing:0.2px;cursor:default;'
            . 'transition:background .2s ease,color .2s ease,border-color .2s ease;}'
            . '.rml-badge:hover{background:' . $hoverBg . ';color:' . $hoverColor . ';border-color:' . $hoverBg . ';}'
            . '.rml-badge-num{font-weight:700;}'
            . '.rml-badges-lg{gap:10px;}'
            . '.rml-badges-lg .rml-badge{padding:8px 18px;font-size:0.95rem;border-radius:4px;}'
            . '</style>';
    }

    /**
     * Templates::Article::Main fires after the abstract in the Smarty template.
     * We inject the badge div at that position, then this script repositions it
     * to sit between keywords and abstract on DOM-ready, matching the left
     * padding of the surrounding section.item elements.
     */
    private function articleRepositionScript()
    {
        static $emitted = false;
        if ($emitted) {
            return '';
        }
        $emitted = true;

        return "<script>(function(){"
            . "function place(){"
            . "var b=document.querySelector('.rml-badges-lg');if(!b||b.dataset.rmlPlaced)return;"
            // Detect left padding of the surrounding section.item to match content indentation.
            . "var ref=document.querySelector('section.item.abstract,section.item.keywords,.item.abstract,.item.keywords');"
            . "if(ref){var pl=window.getComputedStyle(ref).paddingLeft;b.style.paddingLeft=pl;b.style.paddingRight=pl;}"
            // Insert before the abstract section; fall back to before keywords.
            . "var abstract=document.querySelector('section.item.abstract,.item.abstract');"
            . "var keywords=document.querySelector('section.item.keywords,.item.keywords');"
            . "var anchor=abstract||keywords;"
            . "if(anchor&&anchor.parentNode){anchor.parentNode.insertBefore(b,anchor);b.dataset.rmlPlaced='1';return;}"
            // Last resort: append to the article body wrapper.
            . "var wrap=document.querySelector('.obj_article_details .main_entry,.obj_article_details');"
            . "if(wrap){wrap.appendChild(b);b.dataset.rmlPlaced='1';}"
            . "}"
            . "if(document.readyState!=='loading')place();"
            . "else document.addEventListener('DOMContentLoaded',place);"
            . "})();</script>";
    }

    /**
     * Templates::Issue::Issue::Article fires after the galleys_links on the
     * issue TOC. This script moves the badge BEFORE the gallery list.
     */
    private function issueRepositionScript()
    {
        static $issueScriptEmitted = false;
        if ($issueScriptEmitted) {
            return '';
        }
        $issueScriptEmitted = true;

        return "<script>(function(){"
            . "function placeAll(){"
            // Process every badge on the page (one per article in the TOC).
            . "document.querySelectorAll('.obj_article_summary').forEach(function(art){"
            . "var b=art.querySelector('.rml-badges-sm');if(!b||b.dataset.rmlPlaced)return;"
            . "var g=art.querySelector('.galleys_links');"
            // Insert before the gallery list; if no gallery, insert before the badge's current position (no-op).
            . "if(g&&g.parentNode){g.parentNode.insertBefore(b,g);b.dataset.rmlPlaced='1';}"
            . "else{b.dataset.rmlPlaced='1';}"
            . "});"
            . "}"
            . "if(document.readyState!=='loading')placeAll();"
            . "else document.addEventListener('DOMContentLoaded',placeAll);"
            . "})();</script>";
    }

    private function sanitizeColor($val, $fallback)
    {
        if (is_string($val) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $val)) {
            return $val;
        }
        return $fallback;
    }

    /**
     * Reachmetric Lite branding shown at the bottom of the settings form.
     * Returned via Smarty so the locale strings are translatable.
     */
    public function getBrandingHtml()
    {
        $request = \APP\core\Application::get()->getRequest();
        $baseUrl = $request->getBaseUrl();
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;
        $utmParams = '?utm_source=' . urlencode($host) . '&utm_medium=plugin_setting_page&utm_campaign=direct';
        $ojsproUrl = 'https://ojspro.com/' . $utmParams;
        $upgradeUrl = 'https://ojspro.com/plugin/ojs/reachmetric-pro/' . $utmParams;

        $css = '<style>
            .rml-branding { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px; text-align: center; }
            .rml-branding-line { font-size: 13px; color: #666; margin-bottom: 15px; }
            .rml-upgrade-wrapper { position: relative; display: inline-block; margin-top: 15px; }
            .rml-upgrade-btn { display: inline-flex; align-items: center; gap: 8px; background: #005c9e; color: #fff !important; padding: 12px 24px; text-decoration: none !important; border-radius: 4px; font-weight: 600; font-size: 14px; transition: background 0.2s; text-align: left; }
            .rml-upgrade-btn:hover { background: #004475; }
        </style>';

        return $css . '<div class="rml-branding">'
            . '<p class="rml-branding-line">'
            . __('plugins.generic.reachmetricLite.branding.line1')
            . ' <a href="' . htmlspecialchars($ojsproUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">OJSpro.com</a></p>'
            . '<div class="rml-upgrade-wrapper">'
            . '<a class="rml-upgrade-btn" href="' . htmlspecialchars($upgradeUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
            . htmlspecialchars('Want Advanced GA4 Analytics Data? Upgrade to Reachmetric Pro', ENT_QUOTES, 'UTF-8')
            . ' <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>'
            . '<polyline points="15 3 21 3 21 9"></polyline>'
            . '<line x1="10" y1="14" x2="21" y2="3"></line>'
            . '</svg>'
            . '</a>'
            . '</div>'
            . '</div>';
    }
}
