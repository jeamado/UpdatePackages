{*<!--
/*********************************************************************************
 * The contents of this file are subject to the YetiForce Public License Version 1.1 (the "License"); you may not use this file except
 * in compliance with the License.
 * Software distributed under the License is distributed on an "AS IS" basis, WITHOUT WARRANTY OF ANY KIND, either express or implied.
 * See the License for the specific language governing rights and limitations under the License.
 * The Original Code is YetiForce.
 * The Initial Developer of the Original Code is YetiForce. Portions created by YetiForce are Copyright (C) www.yetiforce.com. 
 * All Rights Reserved.
 ********************************************************************************/
-->*}
<style type="text/css">
	.filterContainerTimeControl{
		margin-top: 5px;
		padding: 0 !important
	}
	.dashboardWidgetContentTimeControl{
		padding: 0 !important
	}
	.widgetFilter{
		margin-bottom: 0 !important;
	}
	.iconMiddle { vertical-align: middle; }
</style>
<script type="text/javascript">
	Vtiger_Barchat_Widget_Js('Vtiger_Timecontrol_Widget_Js',{
		fillDateRange : function(){
			var thisInstance = this;
			var dateRange = $('.dateRange').val();
			if(dateRange.length <= 0) {
				var dateFormat = jQuery('#userDateFormat').val();
				var today = new Date();
				var weekAgo = new Date();
				weekAgo.setDate( weekAgo.getDate()-7 );
				var dateRange = app.getDateInVtigerFormat(dateFormat, weekAgo) +','+ app.getDateInVtigerFormat(dateFormat, today);

				$('.dateRange').val(dateRange);
			}
		},

		registerEvents: function() {
			this.fillDateRange();
		}
	},{
		generateChartData : function() {
			var container = this.getContainer();
			var jData = container.find('.widgetData').val();
			var data = JSON.parse(jData);
			var chartData = [];
			var xLabels = new Array();
			var yMaxValue = 0;
			for(var index in data) {
				var row = data[index];
				xLabels.push(app.getDecodedValue(row[1]))
				chartData.push(row[0]);
				if(parseInt(row[0]) > yMaxValue){
					yMaxValue = parseInt(row[0]);
				}
			}
			// yMaxValue Should be 25% more than Maximum Value
			yMaxValue = yMaxValue + 2 + (yMaxValue/100)*25;
			return {literal}{'chartData':[chartData], 'yMaxValue':yMaxValue, 'labels':xLabels}{/literal};
		},
	});

	jQuery(document).ready(function () {
		Vtiger_Timecontrol_Widget_Js.registerEvents();
		jQuery('select').select2();
	});
</script>
<div class="dashboardWidgetHeader">
	{foreach key=index item=cssModel from=$STYLES}
		<link rel="{$cssModel->getRel()}" href="{$cssModel->getHref()}" type="{$cssModel->getType()}" media="{$cssModel->getMedia()}" />
	{/foreach}
	{foreach key=index item=jsModel from=$SCRIPTS}
		<script type="{$jsModel->getType()}" src="{$jsModel->getSrc()}"></script>
	{/foreach}
	<table width="100%" cellspacing="0" cellpadding="0">
		<tbody>
			<tr>
				<td class="span5">
					<div class="dashboardTitle textOverflowEllipsis" title="{vtranslate($WIDGET->getTitle(), $MODULE_NAME)}" style="width: 15em;"><b>&nbsp;&nbsp;{vtranslate($WIDGET->getTitle(), $MODULE_NAME)}</b></div>
				</td>
				<td class="refresh span2" align="right">
					<span style="position:relative;">&nbsp;</span>
				</td>
				<td class="widgeticons span5" align="right">
					<div class="box pull-right">
						<a class="btn" onclick="jQuery('#menubar_quickCreate_OSSTimeControl').trigger('click'); return false;">
							<i class='icon-plus' border='0' title="{vtranslate('LBL_ADD_RECORD')}" alt="{vtranslate('LBL_ADD_RECORD')}"/>
						</a>
						<a class="btn" href="javascript:void(0);" name="drefresh" data-url="{$WIDGET->getUrl()}&linkid={$WIDGET->get('linkid')}&content=data">
							<i class="icon-refresh" hspace="2" border="0" align="absmiddle" title="{vtranslate('LBL_REFRESH')}" alt="{vtranslate('LBL_REFRESH')}"></i>
						</a>
						{if !$WIDGET->isDefault()}
							<a class="btn" name="dclose" class="widget" data-url="{$WIDGET->getDeleteUrl()}">
								<i class="icon-remove" hspace="2" border="0" align="absmiddle" title="{vtranslate('LBL_REMOVE')}" alt="{vtranslate('LBL_REMOVE')}"></i>
							</a>
						{/if}
					</div>
				</td>
			</tr>
		</tbody>
	</table>
	<div class="row-fluid filterContainerTimeControl">
		<div class="row-fluid span6">
			<span class="span4">
				<span class="pull-right">
					<i class="icon-calendar iconMiddle"></i>
				</span>
			</span>
			<span class="span8">
				<input type="text" name="time" class="dateRange widgetFilter" style="width:90%;" />
			</span>
		</div>
		<div class="row-fluid span6">
			<span class="span1">
				<span class="pull-right">
					<i class="icon-user iconMiddle"></i>
				</span>
			</span>
			<span class="span8">
				{assign var=ALL_ACTIVEUSER_LIST value=$CURRENTUSER->getAccessibleUsers()}
				{assign var=LOGGED_USER_ID value=$LOGGEDUSERID}
				<select class="widgetFilter" name="user" style="width:90%;" >
					<optgroup label="{vtranslate('LBL_USERS')}">
						{foreach key=OWNER_ID item=OWNER_NAME from=$ALL_ACTIVEUSER_LIST}
							<option {if $OWNER_ID eq $LOGGED_USER_ID } selected {/if} value="{$OWNER_ID}">
								{$OWNER_NAME}
							</option>
						{/foreach}
					</optgroup>
				</select>
			</span>
		</div>
	</div>
</div>
<div class="dashboardWidgetContent dashboardWidgetContentTimeControl">
	{include file="dashboards/TimeControlContents.tpl"|@vtemplate_path:$MODULE_NAME}
</div>