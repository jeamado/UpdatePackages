<?php

/**
 * Mail scanner action bind Accounts
 * @package YetiForce.MailScanner
 * @license licenses/License.html
 * @author Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class OSSMailScanner_BindAccounts_ScannerAction extends OSSMailScanner_EmailScannerAction_Model
{

	public function process($mail)
	{
		return parent::process($mail, 'Accounts');
	}
}
