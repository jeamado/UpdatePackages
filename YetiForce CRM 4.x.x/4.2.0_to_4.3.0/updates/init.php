<?php
/**
 * YetiForceUpdate Class
 * @package YetiForce.UpdatePackages
 * @copyright YetiForce Sp. z o.o.
 * @license YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */

/**
 * YetiForceUpdate Class
 */
class YetiForceUpdate
{

	/**
	 * @var \vtlib\PackageImport
	 */
	public $package;

	/**
	 * @var object
	 */
	public $modulenode;

	/**
	 * Fields to delete
	 * @var string[]
	 */
	public $filesToDelete = [];

	/**
	 * Cron list
	 * @var string[] 
	 */
	private $cronAction = [];

	/**
	 * DbImporter
	 * @var DbImporter 
	 */
	private $importer;

	/**
	 * Constructor
	 * @param object $modulenode
	 */
	public function __construct($modulenode)
	{
		$this->modulenode = $modulenode;
		$this->filesToDelete = require_once('deleteFiles.php');
	}

	/**
	 * Preupdate
	 */
	public function preupdate()
	{
		return true;
	}

	/**
	 * Update
	 */
	public function update()
	{
		$db = App\Db::getInstance();
		$db->createCommand()->checkIntegrity(false)->execute();
		$this->importer = new \App\Db\Importer();
		$this->updateDbSchema();
		$this->importer->loadFiles(__DIR__ . '/dbscheme');
		$this->importer->updateScheme();
		$this->importer->importData();
		$this->importer->postUpdate();
		$this->importer->logs(false);
		$this->importer->refreshSchema();
		$db->createCommand()->checkIntegrity(true)->execute();

		$moduleBaseInstance = vtlib\Module::getInstance('RecycleBin');
		if ($moduleBaseInstance) {
			$moduleBaseInstance->delete();
		}

		$this->updateRows();
		$this->addRows();
		$this->deleteRows();
		$this->updateConfigFile();
		$this->addActions();
	}

	/**
	 * Postupdate
	 */
	public function postupdate()
	{
		$adb = \PearDatabase::getInstance();
		$adb->query('SET FOREIGN_KEY_CHECKS = 1;');
		$adb->insert('yetiforce_updates', [
			'user' => \Users_Record_Model::getCurrentUserModel()->get('user_name'),
			'name' => $this->modulenode->label,
			'from_version' => $this->modulenode->from_version,
			'to_version' => $this->modulenode->to_version,
			'result' => true,
			'time' => date('Y-m-d H:i:s')
		]);
		$adb->update('vtiger_version', ['current_version' => $this->modulenode->to_version]);
		\vtlib\Functions::recurseDelete('cache/updates');
		\vtlib\Functions::recurseDelete('cache/templates_c');
		file_put_contents('cache/logs/update.log', ob_get_contents(), FILE_APPEND);
		echo '<div class="modal fade in" style="display: block;top: 20%;"><div class="modal-dialog"><div class="modal-content"><div class="modal-header">';
		echo '<h4 class="modal-title">' . \App\Language::translate('LBL_IMPORTING_MODULE', 'Settings:ModuleManager') . '</h4>';
		echo '</div><div class="modal-body">';
		echo \App\Language::translate('LBL_IMPORTED_UPDATE', 'Settings:ModuleManager');
		echo '</div><div class="modal-footer">';
		echo '<a class="btn btn-success" href="index.php">' . \App\Language::translate('LBL_MAIN_PAGE') . '<a>';
		echo '</div></div></div></div>';
		exit;
	}

	/**
	 * Update
	 */
	public function updateDbSchema()
	{
		$this->droptFields();
		$this->addFields();
		$this->dropTables();
		$this->importer->dropColumns([
			['vtiger_users', 'crypt_type'],
		]);
	}

	/**
	 * update rows
	 */
	public function updateRows()
	{
		$db = \App\Db::getInstance();
		$db->createCommand("UPDATE u_yf_emailtemplates SET content = REPLACE(content, 'href=\"$(record%20%3A%20CrmDetailViewURL)$\"', 'href=\"$(record%20%3A%20PortalDetailViewURL)$\"') WHERE name = 'Notify Owner On Ticket Create';")->execute();
		\App\Db\Updater::batchUpdate([
			['u_yf_emailtemplates', ['name' => 'ResetPassword', 'sys_name' => 'UsersResetPassword', 'email_template_priority' => 9, 'subject' => 'Your password has been changed', 'content' => '<table border="0" style="width:100%;font-family:Arial, \'Sans-serif\';border:1px solid #ccc;border-width:1px 2px 2px 1px;background-color:#fff;">
	<tbody>
		<tr>
			<td style="background-color:#f6f6f6;color:#888;border-bottom:1px solid #ccc;font-family:Arial, \'Sans-serif\';font-size:11px;">
			<h3 style="padding:0 0 6px 0;margin:0;font-family:Arial, \'Sans-serif\';font-size:16px;font-weight:bold;color:#222;"><span>Your password has been changed</span></h3>
			</td>
		</tr>
		<tr>
			<td>
			<div style="padding:2px;">
			<table border="0">
				<tbody>
					<tr>
						<td style="padding:0 1em 10px 0;font-family:Arial, \'Sans-serif\';font-size:13px;color:#888;white-space:nowrap;">Dear user,<br />
						The system generated a new password for you. Below you can find your new password and access data to your account.<br />
						<br />
						$(translate : LBL_SITE_URL)$:&nbsp;$(general : SiteUrl)$<br />
						$(translate : Users|User Name)$:&nbsp;$(record : user_name)$<br />
						$(translate : Users|Password)$:&nbsp;$(params : password)$</td>
					</tr>
				</tbody>
			</table>
			</div>
			</td>
		</tr>
		<tr>
			<td style="background-color:#f6f6f6;color:#888;border-top:1px solid #ccc;font-family:Arial, \'Sans-serif\';font-size:11px;">
			<div style="float:right;">$(organization : mailLogo)$</div>
			&nbsp;

			<p><span style="font-size:12px;">$(translate : LBL_EMAIL_TEMPLATE_FOOTER)$</span></p>
			</td>
		</tr>
	</tbody>
</table>
',], ['sys_name' => 'UsersForgotPassword']],
			['vtiger_eventhandlers', ['event_name' => 'EntityChangeState'], ['event_name' => 'EntityBeforeDelete']],
			['vtiger_field', ['displaytype' => 1], ['tablename' => 'vtiger_users', 'columnname' => 'user_name']],
			['vtiger_settings_field', ['linkto' => 'index.php?module=OSSMailScanner&parent=Settings&view=Logs'], ['linkto' => 'index.php?module=OSSMailScanner&parent=Settings&view=logs']],
			['vtiger_settings_field', ['linkto' => 'index.php?module=Colors&parent=Settings&view=Index'], ['linkto' => 'index.php?module=Users&parent=Settings&view=Colors']],
			['vtiger_field', ['uitype' => 300], ['tablename' => 'vtiger_ossmailview', 'columnname' => 'uid']],
			['vtiger_field', ['uitype' => 300], ['tablename' => 'vtiger_ossmailview', 'columnname' => 'content']],
			['vtiger_field', ['uitype' => 300], ['tablename' => 'vtiger_ossmailview', 'columnname' => 'orginal_mail']],
			['vtiger_field', ['uitype' => 35], ['columnname' => 'addresslevel1a']],
			['vtiger_field', ['uitype' => 35], ['columnname' => 'addresslevel1b']],
			['vtiger_field', ['uitype' => 35], ['columnname' => 'addresslevel1c']],
			['vtiger_field', ['typeofdata' => 'I~O'], ['tablename' => 'vtiger_entity_stats', 'columnname' => 'crmactivity']],
			['vtiger_eventhandlers', ['event_name' => 'EntityChangeState'], ['event_name' => 'EntityAfterRestore', 'handler_class' => 'Vtiger_Workflow_Handler']],
			['vtiger_eventhandlers', ['event_name' => 'EntityAfterDelete'], ['event_name' => 'EntityAfterRestore', 'handler_class' => 'PBXManager_PBXManagerHandler_Handler']],
			//['vtiger_eventhandlers', ['event_name' => 'EntityChangeState'], ['event_name' => 'EntityAfterDelete', 'handler_class' => 'PBXManager_PBXManagerHandler_Handler']],
			['vtiger_eventhandlers', ['event_name' => 'EntityChangeState'], ['event_name' => 'EntityAfterDelete', 'handler_class' => 'OSSTimeControl_TimeControl_Handler']],
			['vtiger_eventhandlers', ['event_name' => 'EntityAfterDelete'], ['event_name' => 'EntityAfterRestore', 'handler_class' => 'OSSTimeControl_TimeControl_Handler']],
			['vtiger_eventhandlers', ['event_name' => 'EntityChangeState'], ['event_name' => 'EntityAfterDelete', 'handler_class' => 'ProjectTask_ProjectTaskHandler_Handler']],
			['vtiger_eventhandlers', ['event_name' => 'EntityAfterDelete'], ['event_name' => 'EntityAfterRestore', 'handler_class' => 'ProjectTask_ProjectTaskHandler_Handler']],
			['vtiger_eventhandlers', ['event_name' => 'EntityChangeState'], ['event_name' => 'EntityAfterRestore', 'handler_class' => 'Calendar_CalendarHandler_Handler']],
		]);
	}

	public function addRows()
	{
		$data = [
			['vtiger_password', ['type' => 'change_time', 'val' => '0']],
			['vtiger_password', ['type' => 'lock_time', 'val' => '5']],
		];
		$rows = (new \App\Db\Query)->select(['user_name', 'id'])->from('vtiger_users')->all();
		foreach ($rows as $row) {
			$data[] = ['l_#__username_history', ['user_name' => $row['user_name'], 'user_id' => $row['id'], 'date' => date('Y-m-d H:i:s')]];
		}
		\App\Db\Updater::batchInsert($data);
	}

	public function deleteRows()
	{
		$data = [
			['vtiger_calendar_config', ['type' => 'colors', 'name' => 'break']],
			['vtiger_calendar_config', ['type' => 'colors', 'name' => 'holiday']],
			['vtiger_calendar_config', ['type' => 'colors', 'name' => 'work']],
			['vtiger_eventhandlers', ['event_name' => 'EntityAfterRestore', 'handler_class' => 'ModTracker_ModTrackerHandler_Handler']],
			['vtiger_language', ['prefix' => 'fr_fr']],
			['vtiger_settings_field', ['name' => 'LBL_DATAACCESS']],
			['vtiger_settings_field', ['name' => 'LBL_ACTIVITY_TYPES']],
			['vtiger_settings_blocks', ['label' => 'LBL_YETIFORCE_SHOP']],
			['vtiger_ws_operation', ['name' => 'changePassword']],
		];
		\App\Db\Updater::batchDelete($data);
	}

	private function addFields()
	{

//		$columnName = [0 => "tabid", 1 => "id", 2 => "column", 3 => "table", 4 => "generatedtype", 5 => "uitype", 6 => "name", 7 => "label", 8 => "readonly", 9 => "presence", 10 => "defaultvalue", 11 => "maximumlength", 12 => "sequence", 13 => "block", 14 => "displaytype", 15 => "typeofdata", 16 => "quickcreate", 17 => "quicksequence", 18 => "info_type", 19 => "masseditable", 20 => "helpinfo", 21 => "summaryfield", 22 => "fieldparams", 23 => 'header_field', 24 => "columntype", 25 => "blocklabel", 26 => "setpicklistvalues", 27 => "setrelatedmodules", 28 => 'moduleName'];
		$fields = [];
		$query = (new \App\Db\Query())->from('vtiger_field')->where(['uitype' => 11]);
		$dataReader = $query->createCommand()->query();
		while ($row = $dataReader->read()) {
			$fields[] = [NULL, NULL, $row['columnname'] . '_extra', $row['tablename'], 1, 1, $row['fieldname'] . '_extra', 'FL_PHONE_CUSTOM_INFORMATION', 1, 2, '', 100, NULL, NULL, 3, 'V~O', 1, NULL, 'BAS', 1, '', 0, '', NULL, 'string(100)', $row['block'], [], [], App\Module::getModuleName($row['tabid'])];
		}
		$fields = array_merge($fields, [
			[33, 2618, 'customernumber_extra', 'vtiger_pbxmanager', 1, 1, 'customernumber_extra', 'FL_PHONE_CUSTOM_INFORMATION', 1, 2, '', 100, 19, 88, 3, 'V~O', 1, 0, 'BAS', 1, '', 0, '', NULL, 'varchar(100)', 'LBL_PBXMANAGER_INFORMATION', [], [], 'PBXManager'],
			[29, 2625, 'date_password_change', 'vtiger_users', 1, 80, 'date_password_change', 'FL_DATE_PASSWORD_CHANGE', 1, 2, '', 100, 27, 79, 2, 'DT~O', 1, 0, 'BAS', 1, '', 0, '', NULL, 'datetime', 'LBL_MORE_INFORMATION', [], [], 'Users'],
			[29, 2626, 'force_password_change', 'vtiger_users', 1, 56, 'force_password_change', 'FL_FORCE_PASSWORD_CHANGE', 1, 2, '', 100, 28, 79, 1, 'C~O', 1, 0, 'BAS', 1, '', 0, '', NULL, 'tinyint(1)', 'LBL_MORE_INFORMATION', [], [], 'Users'],
			[37, 2627, 'contactid', 'vtiger_assets', 1, 10, 'contactid', 'FL_CONTACT', 1, 2, '', 100, 8, 96, 1, 'V~O', 1, 0, 'BAS', 1, '', 0, '', NULL, 'int(19)', 'LBL_CUSTOM_INFORMATION', [], ['Contacts'], 'Assets'],
			[58, 2628, 'contactid', 'vtiger_osssoldservices', 1, 10, 'contactid', 'FL_CONTACT', 1, 2, '', 100, 0, 96, 1, 'V~O', 1, 0, 'BAS', 1, '', 0, '', NULL, 'int(19)', 'LBL_CUSTOM_INFORMATION', [], ['Contacts'], 'OSSSoldServices'],
			[16, 2629, 'linkextend', 'vtiger_activity', 1, 65, 'linkextend', 'FL_RELATION_EXTEND', 1, 2, '', 100, 5, 119, 1, 'I~O', 2, 11, 'BAS', 1, '', 0, '', NULL, 'int(10)', 'LBL_RELATED_TO', [], [], 'Events'],
			[9, 2630, 'linkextend', 'vtiger_activity', 1, 65, 'linkextend', 'FL_RELATION_EXTEND', 1, 2, '', 100, 0, 119, 1, 'I~O', 2, 11, 'BAS', 1, '', 0, '', NULL, 'int(10)', 'LBL_RELATED_TO', [], [], 'Calendar'],
			[111, 2631, 'linkextend', 'u_yf_notification', 1, 65, 'linkextend', 'FL_RELATION_EXTEND', 1, 2, '', 100, 15, 374, 1, 'I~O', 2, 8, 'BAS', 1, '', 0, '', NULL, 'int(10)', 'LBL_NOTIFICATION_INFORMATION', [], ['Contacts'], 'Notification'],
			[51, 2632, 'linkextend', 'vtiger_osstimecontrol', 1, 65, 'linkextend', 'FL_RELATION_EXTEND', 1, 2, '', 100, 14, 129, 1, 'I~O', 1, 0, 'BAS', 1, '', 0, '', NULL, 'int(10)', 'LBL_BLOCK', [], [], 'OSSTimeControl'],
			[84, 2633, 'linkextend', 'vtiger_reservations', 1, 65, 'linkextend', 'FL_RELATION_EXTEND', 1, 2, '', 100, 5, 262, 1, 'I~O', 1, 0, 'BAS', 1, '', 0, '', NULL, 'int(10)', 'LBL_BLOCK', [], [], 'Reservations'],
			[60, 2634, 'linkextend', 'vtiger_osspasswords', 1, 65, 'linkextend', 'FL_RELATION_EXTEND', 1, 2, '', 100, 14, 147, 1, 'I~O', 1, 0, 'BAS', 1, '', 0, '', NULL, 'int(10)', 'LBL_OSSPASSWORD_INFORMATION', [], [], 'OSSPasswords'],
		]);
		foreach ($fields as $field) {
			$moduleId = App\Module::getModuleId($field[28]);
			$isExists = (new \App\Db\Query())->from('vtiger_field')->where(['tablename' => $field[3], 'columnname' => $field[2], 'tabid' => $moduleId])->exists();
			if (!$moduleId || $isExists) {
				continue;
			}
			$moduleInstance = \vtlib\Module::getInstance($field[28]);
			$blockInstance = \vtlib\Block::getInstance($field[25], $moduleInstance);
			if (!$blockInstance) {
				App\Log::error("No block found to create a field, you will need to create a field manually. Module: {$field[28]}, field name: {$field[6]}, field label: {$field[7]}");
				continue;
			}
			$fieldInstance = new \vtlib\Field();
			$fieldInstance->column = $field[2];
			$fieldInstance->name = $field[6];
			$fieldInstance->label = $field[7];
			$fieldInstance->table = $field[3];
			$fieldInstance->uitype = $field[5];
			$fieldInstance->typeofdata = $field[15];
			$fieldInstance->readonly = $field[8];
			$fieldInstance->displaytype = $field[14];
			$fieldInstance->masseditable = $field[19];
			$fieldInstance->quickcreate = $field[16];
			$fieldInstance->columntype = $field[24];
			$fieldInstance->presence = $field[9];
			$fieldInstance->maximumlength = $field[11];
			$fieldInstance->quicksequence = $field[17];
			$fieldInstance->info_type = $field[18];
			$fieldInstance->helpinfo = $field[20];
			$fieldInstance->summaryfield = $field[21];
			$fieldInstance->generatedtype = $field[4];
			$fieldInstance->defaultvalue = $field[10];
			$fieldInstance->fieldparams = $field[22];
			$blockInstance->addField($fieldInstance);
			if ($field[26] && ($field[5] == 15 || $field[5] == 16 || $field[5] == 33 )) {
				$fieldInstance->setPicklistValues($field[26]);
			}
			if ($field[27] && $field[5] == 10) {
				$fieldInstance->setRelatedModules($field[27]);
			}
		}

		$moduleName = 'Assets';
		$relatedModule = 'Contacts';
		$targetModule = vtlib\Module::getInstance($relatedModule);
		$targetModule->setRelatedList(vtlib\Module::getInstance($moduleName), $moduleName, ['Add'], 'getDependentsList');
		$moduleName = 'OSSSoldServices';
		$targetModule = vtlib\Module::getInstance($relatedModule);
		$targetModule->setRelatedList(vtlib\Module::getInstance($moduleName), $moduleName, ['Add'], 'getDependentsList');
	}

	public function droptFields()
	{
		$fieldId = (new \App\Db\Query)->select(['fieldid'])->from('vtiger_field')->where(['columnname' => 'calendarsharedtype', 'tablename' => 'vtiger_users'])->scalar();
		if ($fieldId) {
			$fieldInstance = Settings_LayoutEditor_Field_Model::getInstance($fieldId);
			$fieldInstance->delete();
		}
	}

	/**
	 * delete tables
	 */
	public function dropTables()
	{
		$tables = [
			'vtiger_calendar_default_activitytypes',
			'vtiger_calendar_default_activitytypes_seq',
			'vtiger_calendar_user_activitytypes',
			'vtiger_calendarsharedtype',
			'vtiger_calendarsharedtype_seq',
			'vtiger_dataaccess',
			'vtiger_dataaccess_cnd',
			'vtiger_shareduserinfo',
			'vtiger_ws_entity_fieldtype',
			'vtiger_ws_entity_fieldtype_seq',
			'vtiger_ws_entity_name',
			'vtiger_ws_entity_referencetype',
			'vtiger_ws_entity_tables',
		];
		$this->importer->dropTable($tables);
	}

	public function updateConfigFile()
	{
		foreach ($iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(ROOT_DIRECTORY . DIRECTORY_SEPARATOR . 'config', \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $item) {
			if (!$item->isDir()) {
				$content = file_get_contents($item->getRealPath());
				$content = str_replace('@license YetiForce Public License 2.0 (licenses/License.html or yetiforce.com)', '@license YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)', $content);
				file_put_contents($item->getRealPath(), $content);
			}
		}
		$this->updateConfigurationFiles();
		$query = (new \App\Db\Query())->from('vtiger_tab')->where(['customized' => 1]);
		$dataReader = $query->createCommand()->query();
		while ($row = $dataReader->read()) {
			$file = ROOT_DIRECTORY . DIRECTORY_SEPARATOR . "modules/{$row['name']}/{$row['name']}.php";
			if (file_exists($file)) {
				$content = str_replace('vtlib_handler', 'moduleHandler', file_get_contents($file));
				file_put_contents($file, $content);
			}
		}
	}

	private function getConfigurations()
	{
		return [
			['name' => 'config/modules/Accounts.php', 'conditions' => [
					['type' => 'remove', 'search' => 'List of date and time fields'],
					['type' => 'remove', 'search' => '[Label => Name]'],
					['type' => 'removeTo', 'search' => 'FIELD_TO_UPDATE_BY_BUTTON', 'end' => '],'],
					['type' => 'add', 'search' => 'COUNT_IN_HIERARCHY', 'checkInContents' => 'Hide summary products services bookmark', 'addingType' => 'after', 'value' => "	// Hide summary products services bookmark
"],
					['type' => 'add', 'search' => 'HIDE_SUMMARY_PRODUCTS_SERVICES', 'checkInContents' => 'Default view for record detail view', 'addingType' => 'after', 'value' => "	// Default view for record detail view. Values: LBL_RECORD_DETAILS or LBL_RECORD_SUMMARY
"],
					['type' => 'add', 'search' => 'DEFAULT_VIEW_RECORD', 'checkInContents' => 'defaultDetailViewName', 'addingType' => 'after', 'value' => "	// Default module view. Values: List, ListPreview or DashBoard
	'defaultViewName' => 'List',
	// Default record view for list preview. Values: full or summary
	'defaultDetailViewName' => 'full',
"],
				]
			],
			['name' => 'config/modules/Notification.php', 'conditions' => [
					['type' => 'add', 'search' => 'AUTO_REFRESH_REMINDERS', 'checkInContents' => 'AUTO_MARK_NOTIFICATIONS_READ_AFTER_EMAIL_SEND', 'addingType' => 'after', 'value' => "	// Auto mark notifications as readed after send emails to users
	'AUTO_MARK_NOTIFICATIONS_READ_AFTER_EMAIL_SEND' => true, // Boolean
"],
				],
			],
			['name' => 'config/modules/OSSMail.php', 'conditions' => [
					['type' => 'update', 'search' => 'verfify_peer_name', 'replace' => ['verfify_peer_name', 'verify_peer_name']],
				],
			],
			['name' => 'config/modules/Users.php', 'conditions' => [
					['type' => 'remove', 'search' => 'Password crypt type'],
					['type' => 'remove', 'search' => 'PASSWORD_CRYPT_TYPE'],
					['type' => 'add', 'search' => 'IS_VISIBLE_USER_INFO_FOOTER', 'checkInContents' => 'USER_NAME_IS_EDITABLE', 'addingType' => 'after', 'value' => "	// Is it possible to edit a user's name
	'USER_NAME_IS_EDITABLE' => true,
	// Verify previously used usernames
	'CHECK_LAST_USERNAME' => true,
"],
				],
			],
			['name' => 'config/config.inc.php', 'conditions' => [
					['type' => 'add', 'search' => '$forceRedirect', 'checkInContents' => '$phoneFieldAdvancedVerification', 'addingType' => 'after', 'value' => '// Enable advanced phone number validation. Enabling  it will block saving invalid phone number.
$phoneFieldAdvancedVerification = true;'],
				],
			],
			['name' => 'config/debug.php', 'conditions' => [
					['type' => 'remove', 'search' => 'isplays information about the tracking code when an error occurs'],
					['type' => 'remove', 'search' => 'DISPLAY_DEBUG_BACKTRACE'],
					['type' => 'remove', 'search' => 'EXCEPTION_ERROR_HANDLER'],
					['type' => 'remove', 'search' => 'Save logs to file'],
					['type' => 'add', 'search' => 'EXCEPTION_ERROR_TO_SHOW', 'checkInContents' => 'DISPLAY_EXCEPTION_BACKTRACE', 'addingType' => 'after', 'value' => "	// Displays information about the tracking code when an error occurs. Available only with the active SQL_DIE_ON_ERROR = true
	'DISPLAY_EXCEPTION_BACKTRACE' => false,
	// Display logs when error exception occurs
	'DISPLAY_EXCEPTION_LOGS' => false,
	// Turn on the error handler
	'EXCEPTION_ERROR_HANDLER' => false,
"],
				]
			],
			['name' => 'config/search.php', 'conditions' => [
					['type' => 'add', 'search' => 'GLOBAL_SEARCH_OPERATOR', 'checkInContents' => 'LIST_ENTITY_STATE_COLOR', 'addingType' => 'after', 'value' => "	// Colors for record state will be displayed in list view, history, and preview.
	'LIST_ENTITY_STATE_COLOR' => [
		'Archived' => '#0032a2',
		'Trash' => '#ab0505',
		'Active' => '#009405',
	],
"],
				]
			],
			['name' => 'config/security.php', 'conditions' => [
					['type' => 'add', 'search' => '$SECURITY_CONFIG = [', 'checkInContents' => 'USER_ENCRYPT_PASSWORD_COST', 'addingType' => 'after', 'value' => "	// Password encrypt algorithmic cost. Numeric values - we recommend values greater than 10. The greater the value, the longer it takes to encrypt the password.
	'USER_ENCRYPT_PASSWORD_COST' => 10,
"],
				]
			],
			['name' => 'user_privileges/moduleHierarchy.php', 'conditions' => [
					['type' => 'add', 'search' => 'modulesMap1M', 'checkInContents' => 'xxxxxxxxx', 'addingType' => 'after', 'value' => "		'SSalesProcesses' => ['Accounts'],
		'SQuotes' => ['SSalesProcesses'],
		'FInvoice' => ['Accounts'],
		'SSingleOrders' => ['SSalesProcesses'],
		'SRecurringOrders' => ['SSalesProcesses', 'SQuotes'],
		'FBookkeeping' => ['Accounts'],
"],
				]
			],
		];
	}

	private function updateConfigurationFiles()
	{
		$rootDirectory = ROOT_DIRECTORY . DIRECTORY_SEPARATOR;
		foreach ($this->getConfigurations() as $config) {
			if (!$config) {
				continue;
			}
			$conditions = $config['conditions'];
			$fileName = $rootDirectory . $config['name'];
			if (file_exists($fileName)) {
				$baseContent = file_get_contents($fileName);
				$configContent = $configContentClone = file($fileName);
				$emptyLine = false;
				$addContent = [];
				$indexes = [];
				foreach ($configContent as $key => $line) {
					if ($emptyLine && strlen($line) == 1) {
						unset($configContent[$key]);
						$emptyLine = false;
						continue;
					}
					$emptyLine = false;
					foreach ($conditions as $index => $condition) {
						if (empty($condition)) {
							continue;
						}
						if ($condition['type'] === 'add' && !in_array($index, $indexes)) {
							$addContent[$index] = $condition['value'];
							$indexes[] = $index;
						}
						if (strpos($line, $condition['search']) !== false) {
							switch ($condition['type']) {
								case 'add':
									if ($condition['checkInContents'] && strpos($baseContent, $condition['checkInContents']) === false) {
										$configContent[$key] = $condition['addingType'] === 'before' ? $condition['value'] . $configContent[$key] : $configContent[$key] . $condition['value'];
									}
									unset($addContent[$index]);
									break;
								case 'remove':
									if (!empty($condition['before'])) {
										if (strpos($configContentClone[$key - 1], $condition['before']) !== false) {
											unset($configContent[$key]);
											$emptyLine = true;
										}
									} else {
										unset($configContent[$key]);
										$emptyLine = true;
									}
									break;
								case 'removeTo':
									unset($configContent[$key]);
									$while = 0;
									while ($while !== false) {
										$while++;
										unset($configContent[$key + $while]);
										if (strpos($configContent[$key + $while], $condition['end']) === false) {
											$while = false;
										}
									}
									$emptyLine = true;
									break;
								case 'update':
									if ($condition['checkInLine'] && (strpos($condition['checkInLine'], $configContent[$key]) !== false)) {
										break;
									}
									if ($condition['replace']) {
										$configContent[$key] = str_replace($condition['replace'][0], $condition['replace'][1], $configContent[$key]);
									} else {
										$configContent[$key] = $condition['value'];
									}
									break;
								default:
									break;
							}
						}
					}
				}
				$content = implode("", $configContent);
				if ($addContent) {
					$addContentString = implode("", $addContent);
					$content .= $addContentString;
				}
				file_put_contents($fileName, $content);
			}
		}
	}

	public function addActions()
	{
		$actions = [52 => 'MassArchived', 53 => 'MassActive', 54 => 'ArchiveRecord', 55 => 'ActiveRecord', 56 => 'MassTrash', 57 => 'MoveToTrash'];
		$dbCommand = \App\Db::getInstance()->createCommand();
		$profileIds = \vtlib\Profile::getAllIds();
		foreach ($actions as $actionId => $action) {
			$dbCommand->insert('vtiger_actionmapping', ['actionid' => $actionId, 'actionname' => $action, 'securitycheck' => 0])->execute();
			foreach (vtlib\Functions::getAllModules(true) as $moduleId => $module) {
				foreach ($profileIds as $profileId) {
					$isExists = (new \App\Db\Query)->from('vtiger_profile2utility')
						->where(['profileid' => $profileId, 'tabid' => $moduleId, 'activityid' => $actionId])
						->exists();
					if (!$isExists) {
						$dbCommand->insert('vtiger_profile2utility', ['profileid' => $profileId, 'tabid' => $moduleId, 'activityid' => $actionId, 'permission' => 0])->execute();
					}
				}
			}
		}
	}
}
