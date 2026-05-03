{**
 * plugins/generic/reachmetricLite/templates/settingsForm.tpl
 *
 * Reachmetric Lite settings form.
 *}

<script>
	$(function() {ldelim}
		$('#reachmetricLiteSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="reachmetricLiteSettings" method="POST"
	action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="reachmetricLiteSettingsFormNotification"}

	<div id="description">
		<p>{translate key="plugins.generic.reachmetricLite.settings.intro"}</p>
	</div>

	<fieldset class="pkp_form_section">
		<legend>{translate key="plugins.generic.reachmetricLite.settings.metrics.legend"}</legend>

		{fbvFormSection list="true"}
			{fbvElement type="checkbox" id="showAbstractViews" value="1" checked=$showAbstractViews
				label="plugins.generic.reachmetricLite.settings.showAbstractViews"}
			{fbvElement type="checkbox" id="showPdfDownloads" value="1" checked=$showPdfDownloads
				label="plugins.generic.reachmetricLite.settings.showPdfDownloads"}
		{/fbvFormSection}
	</fieldset>

	<fieldset class="pkp_form_section">
		<legend>{translate key="plugins.generic.reachmetricLite.settings.colors.legend"}</legend>
		<p class="hint">{translate key="plugins.generic.reachmetricLite.settings.colors.hint"}</p>

		<div class="rml-colors-grid">
			{fbvFormSection title="plugins.generic.reachmetricLite.settings.badgeBg"}
				{fbvElement type="text" id="badgeBg" value=$badgeBg maxlength="9" size=$smarty.const.SMALL placeholder="#ffffff"}
			{/fbvFormSection}

			{fbvFormSection title="plugins.generic.reachmetricLite.settings.badgeColor"}
				{fbvElement type="text" id="badgeColor" value=$badgeColor maxlength="9" size=$smarty.const.SMALL placeholder="#000000"}
			{/fbvFormSection}

			{fbvFormSection title="plugins.generic.reachmetricLite.settings.badgeHoverBg"}
				{fbvElement type="text" id="badgeHoverBg" value=$badgeHoverBg maxlength="9" size=$smarty.const.SMALL placeholder="#000000"}
			{/fbvFormSection}

			{fbvFormSection title="plugins.generic.reachmetricLite.settings.badgeHoverColor"}
				{fbvElement type="text" id="badgeHoverColor" value=$badgeHoverColor maxlength="9" size=$smarty.const.SMALL placeholder="#ffffff"}
			{/fbvFormSection}
		</div>
	</fieldset>

	<div class="rml-branding-wrap">{$rmlBrandingHtml nofilter}</div>

	{fbvFormButtons}
</form>

<style>
{literal}
.rml-colors-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
.rml-colors-grid .section { margin-bottom: 10px; }
.rml-branding-wrap{margin-top:24px;padding-top:18px;margin-bottom:18px;border-top:1px solid #e5e5e5;border-bottom:1px solid #e5e5e5;padding-bottom:18px;}
.rml-branding{text-align:center;}
.rml-branding-line{margin:0 0 10px 0;color:#555;font-size:0.9rem;}
.rml-branding-line a{color:#1e6ba1;text-decoration:none;font-weight:600;}
.rml-branding-line a:hover{text-decoration:underline;}
.rml-upgrade-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;background:#1e6ba1;color:#fff;border-radius:4px;text-decoration:none;font-weight:600;font-size:0.9rem;transition:background .2s ease;}
.rml-upgrade-btn:hover{background:#155a8a;color:#fff;}
.rml-upgrade-btn svg{flex-shrink:0;}
{/literal}
</style>
