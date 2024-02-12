<?php

namespace Pair;

class Group extends ActiveRecord {

	/**
	 * Property that binds db primary key id.
	 * @var int
	 */
	protected $id;
	
	/**
	 * Property that binds db field name.
	 * @var string
	 */
	protected $name;

	/**
	 * Property that binds db field is_default.
	 * @var bool
	 */
	protected $default;

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = 'groups';
	
	/**
	 * Name of primary key db field.
	 * @var array
	 */
	const TABLE_KEY = ['id'];
	
	/**
	 * Set for converts from string to Datetime, integer or boolean object in two ways.
	 */
	protected function init() {
	
		$this->bindAsInteger('id');
	
		$this->bindAsBoolean('default');
	
	}
	
	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return array
	 */
	protected static function getBinds(): array {
		
		$varFields = array (
			'id'		=> 'id',
			'name'		=> 'name',
			'default' 	=> 'is_default');
		
		return $varFields;
		
	}
	
	/**
	 * If default is TRUE, will set other groups to zero. Insert default Module as
	 * ACL default module when create a Group.
	 */
	protected function afterCreate() {
	
		// unset the other defaults
		$this->unsetSiblingsDefaults();

		// get the rule_id of default module
		$defaultRuleId = $this->getDefaultModule();

		if ($defaultRuleId) {

			// create first ACL with rule_id of default module
			$acl			= new Acl();
			$acl->ruleId	= $defaultRuleId;
			$acl->groupId   = $this->id;
			$acl->default	= TRUE;
	
			$acl->create();
			
		}

	}
	
	/**
	 * If isDefault is 1 or TRUE, will set other groups to zero.
	 */
	protected function beforeUpdate() {
	
		$this->unsetSiblingsDefaults();
			
	}

	/**
	 * Deletes all Acl and User objects of this Group.
	 */
	protected function beforeDelete() {

		$acls = Acl::getAllObjects(['groupId' => $this->id]);
		foreach ($acls as $acl) {
			$acl->delete();
		}

		$userClass = PAIR_USER_CLASS;
		$users = $userClass::getAllObjects(['groupId' => $this->id]);
		foreach ($users as $user) {
			$user->delete();
		}

	}

	/**
	 * Returns list of User objects of this Group.
	 *
	 * @return	array:user
	 */
	public function getUsers(): array {
	
		// a subclass may have been defined for the user
		$userClass = PAIR_USER_CLASS;

		return $userClass::loadObjectsByQuery('SELECT * FROM `users` WHERE group_id=?', [$this->id]);
	
	}
	
	/**
	 * Returns the default Group object, NULL otherwise.
	 *
	 * @return	Group|NULL
	 */
	public static function getDefault(): ?Group {
	
		return self::getObjectByQuery('SELECT * FROM `groups` WHERE `is_default` = 1');
	
	}
	
	/**
	 * If default property is TRUE, will set is_default=0 on all other groups.
	 */
	protected function unsetSiblingsDefaults() {
	
		if ($this->default) {
			Database::run('UPDATE `groups` SET `is_default` = 0 WHERE `id` <> ?', [$this->id]);
		}
	
	}

	/**
	 * Return Rule objects from acl table for this Group.
	 *
	 * @return array:Acl
	 */
	public function getAcls(): array {
		
		return Acl::getAllObjects(['groupId'=>$this->id]);
		
	}

	/**
	 * Return Rule objects which does not exist in acl table for this Group.
	 *
	 * @return array:Rule
	 */
	public function getAllNotExistRules(): array {

		$query =
			'SELECT r.*, m.`name` AS module_name' .
			' FROM `rules` AS r' .
			' INNER JOIN `modules` AS m ON m.id = r.module_id' .
			' WHERE admin_only = 0' .
			' AND r.id NOT IN(SELECT a.rule_id FROM `acl` AS a WHERE a.group_id = ?)';
		
		return Rule::getObjectsByQuery($query, [$this->id]);
		
	}
	
	/**
	 * Get rule ID of default module with "users" name.
	 *
	 * @return int
	 */
	private function getDefaultModule() {

		$query =
			'SELECT r.id' .
			' FROM `rules` as r' .
			' INNER JOIN `modules` as m ON m.id = r.module_id' .
			' WHERE m.name = "users"'.
			' LIMIT 1';

		return Database::load($query, NULL, PAIR_DB_RESULT);

	}
	
	/**
	 * Get default Acl object of this group, if any, NULL otherwise.
	 *
	 * @return Acl|NULL
	 */
	public function getDefaultAcl() {
		
		$query = 'SELECT * FROM `acl` WHERE `group_id` = ? AND `is_default` = 1';
		return Acl::getObjectByQuery($query, [$this->id]);
	
	}
	
	/**
	 * Set a default Acl of this group.
	 *
	 * @param	int		Acl ID.
	 */
	public function setDefaultAcl($aclId) {
	
		// set no default to siblings
		Database::run('UPDATE `acl` SET `is_default` = 0 WHERE `group_id` = ? AND `id` <> ?', [$this->id, $aclId]);

		// set default to this
		Database::run('UPDATE `acl` SET `is_default` = 1 WHERE `group_id` = ? AND `id` = ?', [$this->id, $aclId]);
		
	}

	/**
	 * Adds all rules that don’t exist in ACL table for this group but admins rules.
	 */
	public function addAllAcl() {
		
		$query =
			'INSERT INTO `acl` (`rule_id`, `group_id`, `is_default`)' .
			' SELECT `id`, ?, 0 FROM `rules`' .
			' WHERE `admin_only` = 0 AND `id` NOT IN(' .
			'  SELECT `rule_id` FROM `acl` WHERE `group_id` = ?' .
			' )';
		
		Database::run($query, [$this->id, $this->id]);
		
	}
	
}