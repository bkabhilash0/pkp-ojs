{**
 * templates/editor/issues/articleGalleyForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form to add/edit an issue galley.
 *}
{if $urlRemote}
	{assign var="remoteRepresentation" value=true}
{else}
	{assign var="remoteRepresentation" value=false}
{/if}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#articleGalleyForm').pkpHandler('$.pkp.controllers.grid.representations.form.RepresentationFormHandler',
			{ldelim}
				remoteRepresentation: {$remoteRepresentation|json_encode}
			{rdelim}
		);
	{rdelim});
</script>
<form class="pkp_form" id="articleGalleyForm" method="post" action="{url op="updateGalley" submissionId=$submissionId publicationId=$publicationId representationId=$representationId}">
	{csrf}
	{fbvFormArea id="galley"}
		{fbvFormSection title="submission.layout.galleyLabel" required=true}
			{fbvElement type="text" label="submission.layout.galleyLabelInstructions" value=$label id="label" size=$fbvStyles.size.MEDIUM inline=true required=true disabled=$formDisabled}
		{/fbvFormSection}
		{fbvFormSection}
			{fbvElement type="select" id="locale" label="common.language" from=$supportedLocales selected=$locale|default:$formLocale size=$fbvStyles.size.MEDIUM translate=false inline=true required=true disabled=$formDisabled}
		{/fbvFormSection}
		{fbvFormSection for="remotelyHostedContent" list=true}
			{fbvElement type="checkbox" label="submission.layout.galley.remotelyHostedContent" id="remotelyHostedContent" disabled=$formDisabled}
			<div id="remote" style="display:none">
				{fbvElement type="text" id="urlRemote" label="submission.layout.galley.remoteURL" value=$urlRemote disabled=$formDisabled}
			</div>
		{/fbvFormSection}
		{fbvFormSection id="urlPathSection" title="publication.urlPath"}
			{fbvElement type="text" label="publication.urlPath.description" value=$urlPath id="urlPath" size=$fbvStyles.size.MEDIUM inline=true disabled=$formDisabled}
		{/fbvFormSection}
	{/fbvFormArea}

	{if $supportsDependentFiles}
		{capture assign=dependentFilesGridUrl}{url router=\PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.files.dependent.DependentFilesGridHandler" op="fetchGrid" submissionId=$submissionId publicationId=$publicationId submissionFileId=$articleGalleyFile->getId() stageId=$smarty.const.WORKFLOW_STAGE_ID_PRODUCTION escape=false}{/capture}
		{load_url_in_div id="dependentFilesGridDiv" url=$dependentFilesGridUrl}
	{/if}

	{fbvFormButtons submitText="common.save" submitDisabled=$formDisabled hideCancel=$formDisabled}
</form>
