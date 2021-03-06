<?php

/**
 * PaymentsOut record model class
 * @package YetiForce.Model
 * @copyright YetiForce Sp. z o.o.
 * @license YetiForce Public License 2.0 (licenses/License.html or yetiforce.com)
 */
class PaymentsOut_Record_Model extends Vtiger_Record_Model
{

	public function getSummary($type, $bank, $file)
	{
		$adres = vglobal('cache_dir');
		if ($bank == 'Default') {
			vimport('~~modules/PaymentsOut/helpers/' . $type . '.php');
			$records = new $type($adres . $file);
			return $records;
		}

		vimport('~~modules/PaymentsOut/helpers/subclass/' . $type . '_' . $bank . '.php');
		$class = $type . '_' . $bank;
		$records = new $class($adres . $file);

		return $records;
	}
}
