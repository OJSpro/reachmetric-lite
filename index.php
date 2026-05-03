<?php

/**
 * @defgroup plugins_generic_reachmetricLite Reachmetric Lite Plugin
 */

/**
 * @file plugins/generic/reachmetricLite/index.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_reachmetricLite
 *
 * @brief Wrapper for the Reachmetric Lite plugin.
 */

require_once('ReachmetricLitePlugin.php');
require_once('ReachmetricLiteSettingsForm.php');

return new \APP\plugins\generic\reachmetricLite\ReachmetricLitePlugin();
