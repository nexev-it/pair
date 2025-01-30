<?php

namespace Pair\Models;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

class Rule extends ActiveRecord {

	/**
	 * Table primary key.
	 * @var int
	 */
	protected $id;

	/**
	 * Optional value to set permission on just one action. If null, it means all actions.
	 * @var string|NULL
	 */
	protected $action;

	/**
	 * Flag to set access granted on administrators only
	 * @var bool
	 */
	protected $adminOnly;

	/**
	 * Name of module, lower case.
	 * @var string
	 */
	protected $moduleId;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'rules';

	/**
	 * Name of primary key db field.
	 * @var string
	 */
	const TABLE_KEY = 'id';
	
	/**
	 * Set for converts from string to Datetime, integer or boolean object in two ways.
	 */
	protected function init(): void {
	
		$this->bindAsInteger('id');
	
		$this->bindAsBoolean('adminOnly');
	
	}

	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return array
	 */
	protected static function getBinds(): array {

		$varFields = [
			'id'		=> 'id',
			'action'	=> 'action',
			'adminOnly'	=> 'admin_only',
			'moduleId'	=> 'module_id'
		];

		return $varFields;

	}
	
	/**
	 * Deletes all Acl of this Rule.
	 */
	protected function beforeDelete(): void {
	
		$acls = Acl::getAllObjects(['ruleId' => $this->id]);
		foreach ($acls as $acl) {
			$acl->delete();
		}
	
	}
	
	/**
	 * Returns the db-record of the current Rule object, NULL otherwise.
	 * 
	 * @param	int		Module ID.
	 * @param	string	Action name.
	 * @param	bool	Optional flag to get admin-only rules.
	 *
	 * @return	\stdClass|NULL
	 */
	public static function getRuleModuleName($module_id, $action, $adminOnly=FALSE): ?\stdClass {

		$query =
			' SELECT m.name AS moduleName,r.action AS ruleAction, r.admin_only '.
			' FROM `rules` AS r '.
			' INNER JOIN `modules` AS m ON m.id = r.module_id '.
			' WHERE m.id = ? AND r.action = ? AND r.admin_only = ?';

		return Database::load($query, [$module_id, $action, $adminOnly], PAIR_DB_OBJECT);

	}

}