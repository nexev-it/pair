<?php

namespace Pair;

class Form {

	/**
	 * List of all controls added to this form.
	 * @var FormControl[]
	 */
	private $controls = [];

	/**
	 * List of class to add on each controls.
	 * @var string[]
	 */
	private $controlClasses = [];

	/**
	 * Adds an FormControlInput object to this Form object. Default type is Text.
	 * Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 * @return	FormControlInput
	 */
	public function addInput(string $name, array $attributes = []): FormControlInput {

		$control = new FormControlInput($name, $attributes);
		$this->addControl($control);

		return $control;

	}

	/**
	 * Adds an FormControlSelect object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 * @return	FormControlSelect
	 */
	public function addSelect(string $name, array $attributes = []): FormControlSelect {

		$control = new FormControlSelect($name, $attributes);
		$this->addControl($control);

		return $control;

	}

	/**
	 * Adds an FormControlTextarea object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 * @return	FormControlTextarea
	 */
	public function addTextarea(string $name, array $attributes = []): FormControlTextarea {

		$control = new FormControlTextarea($name, $attributes);
		$this->addControl($control);

		return $control;

	}

	/**
	 * Adds an FormControlButton object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 * @return	FormControlButton
	 */
	public function addButton(string $name, array $attributes = []): FormControlButton {

		$control = new FormControlButton($name, $attributes);
		$this->addControl($control);

		return $control;

	}

	/**
	 * Add a FormControl object to controls list of this Form.
	 *
	 * @param	mixed	FormControl children class object.
	 */
	public function addControl($control) {

		$this->controls[$control->name] = $control;

	}

	/**
	 * Return the control object by its name.
	 *
	 * @param	string	Control name.
	 * @return	FormControl|NULL
	 */
	public function getControl(string $name): ?FormControl {

		if (substr($name, -2) == '[]') {
			$name = substr($name, 0, -2);
		}

		if ($this->controlExists($name)) {
			return $this->controls[$name];
		} else {
			Logger::error('Field control “' . $name . '” has not been defined in Form object');
			return NULL;
		}

	}

	/**
	 * Remove a control form a Form object.
	 *
	 * @param	string	Control name.
	 *
	 * @return	bool
	 */
	public function removeControl(string $name): bool {

		if (substr($name, -2) == '[]') {
			$name = substr($name, 0, -2);
		}

		if (!$this->controlExists($name)) {
			return FALSE;
		}

		unset($this->controls[$name]);
		return TRUE;

	}

	/**
	 * Set all registered controls as readonly.
	 */
	public function setAllReadonly() {

		foreach ($this->controls as $control) {
			$control->setReadonly();
		}

	}

	/**
	 * Check whether the control exists.
	 *
	 * @param	string	Control name.
	 *
	 * @return	boolean
	 */
	public function controlExists($name): bool {

		return array_key_exists($name, $this->controls);

	}

	/**
	 * Assigns all attributes of passed ActiveRecord children to controls with same name.
	 *
	 * @param	ActiveRecord	An object inherited by ActiveRecord.
	 * @return	void
	 */
	public function setValuesByObject(ActiveRecord $object): void {

		if (is_object($object) and is_subclass_of($object, 'Pair\ActiveRecord')) {

			$properties = $object->getAllProperties();

			foreach ($properties as $name=>$value) {
				if (array_key_exists($name, $this->controls)) {
					$control = $this->getControl($name);
					$control->setValue($value);
				}
			}

		}

	}

	/**
	 * Returns all FormControl subclass objects registered in this Form object.
	 *
	 * @return FormControl[]
	 */
	public function getAllControls(): array {

		return $this->controls;

	}

	/**
	 * Creates an HTML form control getting its object by its name.
	 *
	 * @param	string	HTML name for this control.
	 * @return	string
	 */
	public function renderControl(string $name): string {

		// gets control object
		$control = $this->getControl($name);

		if ($control) {

			// adds common CSS classes to requested control
			if (count($this->controlClasses)) {
				$control->addClass(implode(' ', $this->controlClasses));
			}

			return $control->render();

		} else {

			return '';

		}

	}

	/**
	 * Print the HTML code of a form control by its name.
	 *
	 * @param	string	HTML name of the wanted control.
	 * @return	void
	 */
	public function printControl(string $name): void {

		print $this->renderControl($name);

	}

	/**
	 * Print the HTML code of a control’s label.
	 *
	 * @param	string	HTML name of the wanted control.
	 */
	public function printLabel(string $name) {

		// gets control object
		$control = $this->getControl($name);

		if ($control) {
			$control->printLabel();
		}

	}

	/**
	 * Validates all form field controls and returns a FormValidation result object.
	 *
	 * @return	bool
	 */
	public function isValid(): bool {

		$valid = TRUE;

		foreach ($this->controls as $control) {

			if (!$control->validate()) {
				$valid = FALSE;
			}

		}

		return $valid;

	}

	/**
	 * Return a list of unvalid FormControl objects.
	 *
	 * @return FormControl[]
	 */
	public function getUnvalidControls(): array {

		$unvalids = [];

		foreach ($this->controls as $control) {

			if (!$control->validate()) {
				$unvalids[] = $control;
			}

		}

		return $unvalids;

	}

	/**
	 * Adds a common CSS class to all controls of this form at render time. Chainable.
	 *
	 * @param	string	CSS Class name.
	 *
	 * @return	\Pair\Form
	 */
	public function addControlClass(string $class): Form {

		$this->controlClasses[] = $class;

		return $this;

	}

	/**
	 * Create an HTML select control starting from an object array and setting a default
	 * value (optional).
	 *
	 * @param	string	Select’s name.
	 * @param	array	Array with object as options.
	 * @param	string	Property name of the value for option object (default is “value”).
	 * @param	string	Property name of the text for option object (default is “text”).
	 * @param	string	Value selected in this select (default NULL).
	 * @param	string	Extended parameters as associative array tag=>value.
	 * @param	string	Prepend empty value (default NULL, no prepend).
	 *
	 * @return	string
	 */
	public static function buildSelect(string $name, array $list, string $valName='value', string $textName='text', $value=NULL, $attributes=NULL, $prependEmpty=NULL) {

		$control = new FormControlSelect($name, $attributes);
		$control->setListByObjectArray($list, $valName, $textName)->setValue($value);

		if ($prependEmpty) {
			$control->prependEmpty($prependEmpty);
		}

		return $control->render();

	}

	/**
	 * Proxy for buildSelect that allow to start option list from a simple array.
	 *
	 * @param	string	Select’s name.
	 * @param	array	Associative array value=>text for options.
	 * @param	string	Value selected in this select (default NULL).
	 * @param	string	Extended attributes as associative array tag=>value (optional).
	 * @param	string	Prepend empty value (default NULL, no prepend).
	 *
	 * @return	string
	 */
	public static function buildSelectFromArray(string $name, array $list, string $value=NULL, $attributes=NULL, $prependEmpty=NULL) {

		$control = new FormControlSelect($name, $attributes);
		$control->setListByAssociativeArray($list)->setValue($value);

		if ($prependEmpty) {
			$control->prependEmpty($prependEmpty);
		}

		return $control->render();

	}

	/**
	 * Creates an HTML input form control.
	 *
	 * @param	string	HTML name for this control.
	 * @param	string	Default value (NULL default).
	 * @param	string	Type (text -default-, email, tel, url, color, password, number, bool, date, datetime, file, image, address, hidden).
	 * @param	string	More parameters as associative array tag=>value (optional).
	 *
	 * @return	string
	 */
	public static function buildInput(string $name, string $value=NULL, string $type='text', $attributes=[]) {

		$control = new FormControlInput($name, $attributes);
		$control->setType($type)->setValue($value);

		return $control->render();

	}

	/**
	 * Creates a TextArea input field.
	 *
	 * @param	string	HTML name for this control.
	 * @param   int		Rows value.
	 * @param   int		Columns value.
	 * @param	string	Default value (NULL default).
	 * @param	string	More parameters as associative array tag=>value (optional).
	 *
	 * @return string
	 */
	public static function buildTextarea(string $name, int $rows, int $cols, $value=NULL, $attributes=[]) {

		$control = new FormControlTextarea($name, $attributes);
		$control->setRows($rows)->setCols($cols)->setValue($value);

		return $control->render();

	}

	/**
	 * Creates an HTML button form control prepending an optional icon.
	 *
	 * @param	string	Text for the button.
	 * @param	string	Type (submit -default-, button, reset).
	 * @param	string	HTML name for this control (optional).
	 * @param	string	More parameters as associative array tag=>value (optional).
	 * @param	string	Name of Font Awesome icon class (optional).
	 *
	 * @return	string
	 */
	public static function buildButton(string $value, string $type='submit', string $name=NULL, $attributes=[], $faIcon=NULL) {

		$control = new FormControlButton($name, $attributes);
		$control->setType($type)->setFaIcon($faIcon)->setValue($value);

		return $control->render();

	}

}

abstract class FormControl {

	/**
	 * Name of this control is HTML control name tag.
	 * @var string
	 */
	private $name;

	/**
	 * DOM object unique ID.
	 * @var string
	 */
	private $id;

	/**
	 * Current value for this control object.
	 * @var mixed
	 */
	private $value;

	/**
	 * Flag for set this field as required.
	 * @var boolean
	 */
	private $required = FALSE;

	/**
	 * Flag for set this field as disabled.
	 * @var boolean
	 */
	private $disabled = FALSE;

	/**
	 * Flag for set this field as readonly.
	 * @var boolean
	 */
	private $readonly = FALSE;

	/**
	 * Flag for set this control name as array.
	 * @var boolean
	 */
	private $arrayName = FALSE;

	/**
	 * Control placeholder text.
	 * @var NULL|string
	 */
	private $placeholder;

	/**
	 * Minimum allowed length for value.
	 * @var NULL|integer
	 */
	private $minLength;

	/**
	 * Maximum allowed length for value.
	 * @var NULL|integer
	 */
	private $maxLength;

	/**
	 * List of optional attributes as associative array.
	 * @var string[]
	 */
	private $attributes = [];

	/**
	 * Container for all control CSS classes.
	 * @var string[]
	 */
	private $class = [];

	/**
	 * Optional label for this control.
	 * @var string|NULL
	 */
	private $label;

	/**
	 * Optional description for this control.
	 * @var string|NULL
	 */
	private $description;

	/**
	 * Build control with HTML name tag and optional attributes.
	 *
	 * @param	string	Control name.
	 * @param	array	Optional attributes (tag=>value).
	 */
	public function __construct(string $name, ?array $attributes=[]) {

		// remove [] from array and set TRUE to arrayName property
		if (substr($name, -2) == '[]') {
			$name = substr($name, 0, -2);
			$this->setArrayName();
		}

		$this->name			= $name;
		$this->attributes	= (array)$attributes;

	}

	/**
	 * Return property’s value if set. Throw an exception and returns NULL if not set.
	 *
	 * @param	string	Property’s name.
	 * @throws	Exception
	 * @return	mixed|NULL
	 */
	public function __get(string $name) {

		try {

			if (!property_exists($this, $name)) {
				throw new \Exception('Property “'. $name .'” doesn’t exist for object '. get_called_class());
			}

			return $this->$name;

		} catch (\Exception $e) {

			trigger_error($e->getMessage());
			return NULL;

		}

	}

	/**
	 * Magic method to set an object property value.
	 *
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	public function __set(string $name, $value) {

		$this->$name = $value;

	}

	/**
	 * Return a string value for this object, matches the control’s label.
	 *
	 * @return	string
	 */
	public function __toString(): string {

		return $this->getLabel();

	}

	abstract public function render();

	abstract public function validate();

	/**
	 * Adds a single data attribute, prepending the string "data-" to the given name.
	 * @param	string	Data attribute name.
	 * @param	string	Value.
	 * @return 	FormControl
	 */
	public function data(string $name, string $value): FormControl {

		$this->attributes['data-' . $name] = $value;

		return $this;

	}

	/**
	 * Sets value for this control subclass.
	 *
	 * @param	mixed		Value for this control.
	 * @return	FormControl
	 */
	public function setValue($value): FormControl {

		// special behavior for DateTime
		if (is_a($value, 'DateTime') and is_a($this, 'Pair\FormControlInput')) {

			// if UTC date, set user timezone
			if (defined('UTC_DATE') and UTC_DATE) {
				$app = Application::getInstance();
				$value->setTimezone($app->currentUser->getDateTimeZone());
			}

			// can be datetime or just date
			$format = (isset($this->type) and 'date'==$this->type) ? $this->dateFormat : $this->datetimeFormat;
			$this->value = $value->format($format);

		} else {

			$this->value = $value;

		}

		return $this;

	}

	/**
	 * Set the control ID.
	 *
	 * @param	string	Control identifier.
	 * @return	FormControl
	 */
	public function setId(string $id): FormControl {

		$this->id = $id;
		return $this;

	}

	/**
	 * Sets this field as required (enables JS client-side and PHP server-side validation).
	 * Chainable method.
	 *
	 * @return	FormControl subclass
	 */
	public function setRequired(): FormControl {

		$this->required = TRUE;
		return $this;

	}

	/**
	 * Sets this field as disabled only. Chainable method.
	 *
	 * @return	FormControl
	 */
	public function setDisabled(): FormControl {

		$this->disabled = TRUE;
		return $this;

	}

	/**
	 * Sets this field as read only. Chainable method.
	 *
	 * @return	FormControl subclass
	 */
	public function setReadonly(): FormControl {

		$this->readonly = TRUE;
		return $this;

	}

	/**
	 * Sets this field as array. Will add [] to control name. Chainable method.
	 *
	 * @return	FormControl subclass
	 */
	public function setArrayName(): FormControl {

		$this->arrayName = TRUE;
		return $this;

	}

	/**
	 * Sets placeholder text. Chainable method.
	 *
	 * @param	string		Placeholder’s text.
	 * @return	FormControl subclass
	 */
	public function setPlaceholder(string $text): FormControl {

		$this->placeholder = $text;
		return $this;

	}

	/**
	 * Sets minimum length for value of this control. It’s a chainable method.
	 *
	 * @param	int		Minimum length for value.
	 *
	 * @return	FormControl subclass
	 */
	public function setMinLength(int $length): FormControl {

		$this->minLength = $length;
		return $this;

	}

	/**
	 * Sets maximum length for value of this control. It’s a chainable method.
	 *
	 * @param	int		Maximum length for value.
	 *
	 * @return	FormControl subclass
	 */
	public function setMaxLength(int $length): FormControl {

		$this->maxLength = $length;
		return $this;

	}

	/**
	 * Adds CSS single class, classes string or classes array to this control, avoiding
	 * duplicates. This method is chainable.
	 *
	 * @param	string|array	Single class name, list space separated or array of class names.
	 *
	 * @return	FormControl subclass
	 */
	public function addClass($class): FormControl {

		// classes array
		if (is_array($class)) {

			// adds all of them
			foreach ($class as $c) {
				if (!in_array($c, $this->class)) {
					$this->class[] = $c;
				}
			}

		// single class
		} else if (!in_array($class, $this->class)) {

			$this->class[] = $class;

		}

		return $this;

	}

	/**
	 * Set a label for this control as text or translation key. Chainable method.
	 *
	 * @param	string	The text label or the uppercase translation key.
	 * @return	FormControl
	 */
	public function setLabel(string $label): FormControl {

		$this->label = $label;

		return $this;

	}

	/**
	 * Return the control’s label.
	 *
	 * @return	string
	 */
	public function getLabel(): string {

		// no label, get it by the control’s name
		if (!$this->label) {

			$label = ucwords(preg_replace(['/(?<=[^A-Z])([A-Z])/', '/(?<=[^0-9])([0-9])/'], ' $0', $this->name));

		// check if it’s a translation key, uppercase over 3 chars
		} else if (strtoupper($this->label) == $this->label and strlen($this->label) > 3) {

			$label = Translator::do($this->label);

		// simple label
		} else {

			$label = $this->label;

		}

		return $label;

	}

	/**
	 * Set a description for this control as text. Chainable method.
	 *
	 * @param	string	The text description.
	 * @return	FormControl
	 */
	public function setDescription(string $description): FormControl {

		$this->description = $description;

		return $this;

	}

	/**
	 * Return the control’s description.
	 *
	 * @return	string
	 */
	public function getDescription(): string {

		return $this->description;

	}

	/**
	 * Print the control’s label even with required-field class.
	 *
	 * @return	void
	 */
	public function printLabel(): void {

		$label = $this->getLabel();

		// if required, add required-field css class
		if ($this->required and !$this->readonly and !$this->disabled) {
			$label = '<span class="required-field">' . $label . '</span>';
		}

		if ($this->description) {
			$label .= ' <i class="fal fa-question-circle" data-toggle="tooltip" data-placement="auto" title="' . htmlspecialchars((string)$this->description) . '"></i>';
		}

		print $label;

	}

	/**
	 * Print the HTML code of this FormControl.
	 *
	 * @param	string	HTML name of the wanted control.
	 * @return	void
	 */
	public function printControl(): void {

		print $this->render();

	}

	/**
	 * Process and return the common control attributes.
	 *
	 * @return string
	 */
	protected function processProperties(): string {

		$ret = '';

		if ($this->required and (!isset($this->type) or (isset($this->type) and 'bool' != $this->type))) {
			$ret .= ' required';
		}

		if ($this->disabled) {
			$ret .= ' disabled';
		}

		if ($this->readonly) {
			$ret .= ' readonly';
		}

		if ($this->placeholder) {
			$ret .= ' placeholder="' . $this->placeholder . '"';
		}

		// CSS classes
		if (count($this->class)) {
			$ret .= ' class="' . implode(' ', $this->class) . '"';
		}

		// misc tag attributes
		foreach ($this->attributes as $attr=>$val) {
			$ret .= ' ' . $attr . '="' . str_replace('"','\"',$val) . '"';
		}

		return $ret;

	}

	/**
	 * Create a control name escaping special chars and adding array puncts in case of.
	 *
	 * @return string
	 */
	protected function getNameProperty(): string {

		return 'name="' . htmlspecialchars($this->name . ($this->arrayName ? '[]' : '')) . '"';

	}

}

class FormControlInput extends FormControl {

	/**
	 * Can be text, email, tel, url, color, password, number, bool, date, datetime, file, image, address, hidden.
	 * @var string
	 */
	protected $type;

	/**
	 * Accepted file type file_extension, audio/*, video/*, image/* or media_type.
	 */
	protected $accept;

	/**
	 * Default date format.
	 * @var string
	 */
	protected $dateFormat = 'Y-m-d';

	/**
	 * Default datetime format
	 * @var string
	 */
	protected $datetimeFormat = 'Y-m-d\TH:i:s';

	/**
	 * Step value for number input controls.
	 * @var string
	 */
	protected $step;

	/**
	 * Minimum allowed length for value.
	 * @var string
	 */
	protected $min;

	/**
	 * Maximum allowed length for value.
	 * @var string
	 */
	protected $max;

	/**
	 * Extends parent constructor in order to sets default type to text.
	 *
	 * @param	string	Control name.
	 * @param	array	Additional attributes (tag=>value).
	 */
	public function __construct(string $name, array $attributes = []) {

		parent::__construct($name, $attributes);

		$this->setType('text');

		if (Input::usingCustomDatepicker() and defined('PAIR_FORM_DATE_FORMAT')) {
			$this->setDateFormat(PAIR_FORM_DATE_FORMAT);
		}

		if (Input::usingCustomDatetimepicker() and defined('PAIR_FORM_DATETIME_FORMAT')) {
			$this->setDatetimeFormat(PAIR_FORM_DATETIME_FORMAT);
		}

	}

	/**
	 * Sets type for a FormControlInput. Chainable method.
	 *
	 * @param	string	Input type (text, password, number, bool, tel, email, url, color, date, datetime, file, image, address,
	 * hidden)
	 *
	 * @return	FormControlInput
	 */
	public function setType(string $type): FormControlInput {

		$this->type = $type;
		return $this;

	}

	/**
	 * Set accepted file type by input field (only affects the “file” input). Chainable method.
	 *
	 * @param	string	File type: file_extension, audio/*, video/*, image/*, media_type.
	 *
	 * @return	FormControlInput
	 */
	public function setAccept(string $fileType): FormControlInput {

		$this->accept = $fileType;
		return $this;

	}

	/**
	 * Set date format. Chainable method.
	 *
	 * @param	string	Date format.
	 *
	 * @return	FormControlInput
	 */
	public function setDateFormat(string $format): FormControlInput {

		$this->dateFormat = $format;
		return $this;

	}

	/**
	 * Set datetime format. Chainable method.
	 *
	 * @param	string	Datetime format.
	 *
	 * @return	FormControlInput
	 */
	public function setDatetimeFormat(string $format): FormControlInput {

		$this->datetimeFormat = $format;
		return $this;

	}

	/**
	 * Set step value for input field of number type. Chainable method.
	 *
	 * @param	mixed	Integer or decimal value for this control.
	 *
	 * @return	FormControlInput
	 */
	public function setStep($value): FormControlInput {

		$this->step = (string)$value;
		return $this;

	}

	/**
	 * Set the minimum value for this control. It’s a chainable method.
	 *
	 * @param	mixed	Minimum value.
	 *
	 * @return	FormControlInput
	 */
	public function setMin($minValue): FormControlInput {

		$this->min = (int)$minValue;
		return $this;

	}

	/**
	 * Set the maximum value for this control. It’s a chainable method.
	 *
	 * @param	mixed		Maximum value.
	 *
	 * @return	FormControlInput
	 */
	public function setMax($maxValue): FormControlInput {

		$this->max = (int)$maxValue;
		return $this;

	}

	/**
	 * Renders and returns an HTML input form control.
	 *
	 * @return	string
	 */
	public function render(): string {

		$ret = '<input ' . $this->getNameProperty();

		switch ($this->type) {

			default:
			case 'text':
			case 'email':
			case 'tel':
			case 'url':
			case 'color':
			case 'password':
				$ret .= ' type="' . htmlspecialchars((string)$this->type) . '" value="' . htmlspecialchars((string)$this->value) . '"';
				break;

			case 'number':
				$curr = setlocale(LC_NUMERIC, 0);
				setlocale(LC_NUMERIC, 'en_US');
				$ret .= ' type="number" value="' . htmlspecialchars((string)$this->value) . '"';
				setlocale(LC_NUMERIC, $curr);
				break;

			case 'bool':
				$ret .= ' type="checkbox" value="1"';
				if ($this->value) $ret .= ' checked="checked"';
				break;

			case 'date':
				$ret .= ' type="date" value="' . htmlspecialchars((string)$this->value) . '"';
				break;

			case 'datetime':
				$type = Input::usingCustomDatetimepicker() ? 'datetime' : 'datetime-local';
				$ret .= ' type="' . $type . '" value="' . htmlspecialchars((string)$this->value) . '"';
				break;

			case 'file':
				$ret .= ' type="file"';
				break;

			case 'image':
				$ret .= ' type="image"';
				break;

			case 'address':
				$ret .= ' type="text" value="'. htmlspecialchars((string)$this->value) .'" size="50" autocomplete="on" placeholder=""';
				$this->addClass('googlePlacesAutocomplete');
				break;

			case 'hidden':
				$ret .= ' type="hidden" value="' . htmlspecialchars((string)$this->value) . '"';
				break;

		}

		// set min and max value attribute for date and number only
		if (in_array($this->type, ['number','date'])) {

			if (!is_null($this->min)) {
				$ret .= ' min="' . htmlspecialchars((string)$this->min) . '"';
			}

			if (!is_null($this->max)) {
				$ret .= ' max="' . htmlspecialchars((string)$this->max) . '"';
			}

		}

		// set minlength attribute
		if ($this->minLength) {
			$ret .= ' minlength="' . htmlspecialchars((string)$this->minLength) . '"';
		}

		// set maxlength attribute
		if ($this->maxLength) {
			$ret .= ' maxlength="' . htmlspecialchars((string)$this->maxLength) . '"';
		}

		// set accept attribute
		if ($this->accept) {
			$ret .= ' accept="' . $this->accept . '"';
		}

		// set step attribute
		if ($this->step) {
			$ret .= ' step="' . htmlspecialchars((string)$this->step) . '"';
		}

		$ret .= $this->processProperties() . ' />';

		return $ret;

	}

	/**
	 * Validates this control against empty values, minimum length, maximum length,
	 * and returns TRUE if is all set checks pass.
	 *
	 * @return	bool
	 */
	public function validate(): bool {

		$value	= Input::get($this->name);
		$valid	= TRUE;

		if ($this->required) {

			switch ($this->type) {

				default:
				case 'text':
				case 'password':
				case 'date':
				case 'datetime':
				case 'file':
				case 'image':
				case 'tel':
				case 'address':
				case 'color':
				case 'hidden':
					if (''==$value) {
						Logger::event('Control validation on field “' . $this->name . '” has failed (required)');
						$valid = FALSE;
					}
					break;

				case 'email':
					if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
						Logger::event('Control validation on field “' . $this->name . '” has failed (email required)');
						$valid = FALSE;
					}
					break;

				case 'url':
					if (!filter_var($value, FILTER_VALIDATE_URL)) {
						Logger::event('Control validation on field “' . $this->name . '” has failed (url required)');
						$valid = FALSE;
					}
					break;

				case 'number':
					if (!is_numeric($value)) {
						Logger::event('Control validation on field “' . $this->name . '” has failed (number required)');
						$valid = FALSE;
					}
					break;

				case 'bool':
					break;

			}

		}

		// set min and max value attribute for date and number only
		if (in_array($this->type, ['number','date'])) {

			if ($this->min and $value < $this->min) {
				Logger::event('Control validation on field “' . $this->name . '” has failed (min=' . $this->min . ')');
				$valid = FALSE;
			}

			if ($this->max and $value > $this->max) {
				Logger::event('Control validation on field “' . $this->name . '” has failed (max=' . $this->max . ')');
				$valid = FALSE;
			}

		}

		// check validity of minlength attribute
		if ($this->minLength and ''!=$value and strlen($value) < $this->minLength) {
			Logger::event('Control validation on field “' . $this->name . '” has failed (minLength=' . $this->minLength . ')');
			$valid = FALSE;
		}

		// check validity of minlength attribute
		if ($this->maxLength and strlen($value) > $this->maxLength) {
			Logger::event('Control validation on field “' . $this->name . '” has failed (maxLength=' . $this->maxLength . ')');
			$valid = FALSE;
		}

		return $valid;

	}

}

class FormControlSelect extends FormControl {

	/**
	 * Items list of \stdClass objs with value and text attributes.
	 * @var array
	 */
	private $list = [];

	/**
	 * Flag to enable this control to multiple values.
	 * @var bool
	 */
	private $multiple = FALSE;

	/**
	 * If populated with text, add an empty option before the list of values.
	 * @var string|NULL
	 */
	private $emptyOption;

	/**
	 * Check whether this select control has options.
	 */
	public function hasOptions(): bool {

		return count($this->list) > 0;

	}

	/**
	 * Populates select control with an associative array. Chainable method.
	 *
	 * @param	array	Associative array (value=>text).
	 *
	 * @return	FormControlSelect
	 */
	public function setListByAssociativeArray(array $list): FormControlSelect {

		foreach ($list as $value=>$text) {

			$option			= new \stdClass();
			$option->value	= $value;
			$option->text	= $text;
			$option->attributes	= [];

			$this->list[]	= $option;

		}

		return $this;

	}

	/**
	 * Populates select control with an object array. Each object must have properties
	 * for value and text. If property text includes a couple of round parenthesys, will
	 * invoke a function without parameters. It’s a chainable method.
	 *
	 * @param	\stdClass[]	Object with value and text properties.
	 * @param	string		Name of property’s value.
	 * @param	string		Name of property’s text or an existent object function.
	 * @param 	string		Name of property's attributes (optional).
	 *
	 * @return	FormControlSelect
	 */
	public function setListByObjectArray(array $list, string $propertyValue, string $propertyText, $propertyAttributes = null): FormControlSelect {

		// for each list object, add an option
		foreach ($list as $opt) {

			$option			= new \stdClass();
			$option->value	= $opt->$propertyValue;
			$option->attributes = [];

			if (is_array($propertyAttributes)) {
				foreach ($propertyAttributes as $pa) {
					array_push($option->attributes, ['name' => $pa, 'value' => $opt->$pa]);
				}
			} else if (is_string($propertyAttributes)) {
				array_push($option->attributes, ['name' => $propertyAttributes, 'value' => $opt->$propertyAttributes]);
			}

			// check wheter the propertyText is a function call
			if (FALSE !== strpos($propertyText,'()') and strpos($propertyText,'()')+2 == strlen($propertyText)) {
				$functionName = substr($propertyText, 0, strrpos($propertyText,'()'));
				$option->text = $opt->$functionName();
			} else {
				$option->text = $opt->$propertyText;
			}

			$this->list[] = $option;

		}

		return $this;

	}

	/**
	 * Populate this control through an array in which each element is the group title and
	 * in turn contains a list of objects with the value and text properties. Chainable.
	 *
	 * @param	array:\stdClass[]	Two-dimensional list.
	 *
	 * @return	FormControlSelect
	 */
	public function setGroupedList(array $list): FormControlSelect {

		$this->list = $list;

		return $this;

	}

	/**
	 * Adds a null value as first item. Chainable method.
	 *
	 * @param	string|NULL	Option text for first null value.
	 *
	 * @return	FormControlSelect
	 */
	public function prependEmpty(string $text=NULL): FormControlSelect {

		$this->emptyOption = is_null($text) ? Translator::do('SELECT_NULL_VALUE') : $text;

		return $this;

	}

	/**
	 * Enables this select control to accept multiple choises. Chainable method.
	 *
	 * @return	FormControlSelect
	 */
	public function setMultiple(): FormControlSelect {

		$this->multiple = TRUE;
		return $this;

	}

	/**
	 * Renders a Select field tag as HTML code.
	 *
	 * @return string
	 */
	public function render(): string {

		/**
		 * Build the code of an option HTML tag.
		 * @var		\stdClass
		 * @return	string
		 */
		$buildOption = function ($option) {
			// check on required properties
			if (!isset($option->value) or !isset($option->text)) {
				return '';
			}
			// check if value is an array
			if (is_array($this->value)) {
				$selected = in_array($option->value, $this->value) ? ' selected="selected"' : '';
			} else {
				$selected = $this->value == $option->value ? ' selected="selected"' : '';
			}

			$attributes = '';

			if (isset($option->attributes) and count($option->attributes)) {
				foreach($option->attributes as $a) {
					$attributes .= ' ' . $a['name'] . '="' . $a['value'] . '"';
				}
			}

			// build the option
			return '<option value="' . htmlspecialchars((string)$option->value) . '"' . $selected . $attributes . '>' .
					htmlspecialchars((string)$option->text) . "</option>\n";
		};

		// add an initial line to the options of this select
		if (!is_null($this->emptyOption)) {
			$option			= new \stdClass();
			$option->value	= '';
			$option->text	= ($this->disabled or $this->readonly) ? '' : $this->emptyOption;
			$this->list = array_merge([$option], $this->list);
		}

		$ret = '<select ' . $this->getNameProperty();

		if ($this->multiple) {
			$ret .= ' multiple';
		}

		$ret .= $this->processProperties() . ">\n";

		try {

			// build each option
			foreach ($this->list as $item) {

				// recognize optgroup
				if (isset($item->list) and is_array($item->list) and count($item->list)) {

					$ret .= '<optgroup label="' . htmlspecialchars(isset($item->group) ? (string)$item->group : '') . "\">\n";
					foreach ($item->list as $option) {
						$ret .= $buildOption($option);
					}
					$ret .= "</optgroup>\n";

				} else {

					$ret .= $buildOption($item);

				}

			}

		} catch (\Exception $e) {

			print $e->getMessage();

		}

		$ret .= "</select>\n";
		return $ret;

	}

	/**
	 * Validates this control and returns TRUE if is valid.
	 *
	 * @return	bool
	 */
	public function validate(): bool {

		$value = Input::get($this->name);

		// check if the value is required but empty
		if ($this->required and (''==$value or is_null($value))) {

			Logger::warning('Control validation on field “' . $this->name . '” has failed (required)');
			$valid = FALSE;

		// check if the value is in the allowed list
		} else if (count($this->list)) {

			// this FormControlSelect contains an empty option as the first element
			if (!is_null($this->emptyOption) and !$this->required and (''==$value or is_null($value))) {

				$valid = TRUE;

			} else {

				$valid = FALSE;

				// check if the value corresponds to one of the options
				foreach ($this->list as $item) {
					if ($item->value == $value) $valid = TRUE;
				}

				if (!$valid) {
					Logger::warning('Control validation on field “' . $this->name . '” has failed (value “' . $value . '” is not in list)');
				}

			}

		// empty list and value not required
		} else {

			$valid = TRUE;

		}

		return $valid;

	}

}

class FormControlTextarea extends FormControl {

	private $rows = 2;

	private $cols = 20;

	/**
	 * Sets rows for this textarea. Chainable method.
	 *
	 * @param	int		Rows number.
	 *
	 * @return	FormControlTextarea
	 */
	public function setRows(int $num): FormControlTextarea {

		$this->rows = $num;
		return $this;

	}

	/**
	 * Sets columns for this textarea. Chainable method.
	 *
	 * @param	int		Columns number.
	 *
	 * @return	FormControlTextarea
	 */
	public function setCols(int $num): FormControlTextarea {

		$this->cols = $num;
		return $this;

	}

	/**
	 * Renders a TextArea field tag as HTML code.
	 *
	 * @return string
	 */
	public function render(): string {

		$ret  = '<textarea ' . $this->getNameProperty();
		$ret .= ' rows="' . $this->rows . '" cols="' . $this->cols . '"';
		$ret .= $this->processProperties() . '>';
		$ret .= htmlspecialchars((string)$this->value) . '</textarea>';

		return $ret;

	}

	/**
	 * Validates this control against empty values, minimum length, maximum length,
	 * and returns TRUE if is all set checks pass.
	 *
	 * @return	bool
	 */
	public function validate(): bool {

		$app	= Application::getInstance();
		$value	= Input::get($this->name);
		$valid	= TRUE;

		if ($this->required and ''==$value) {
			Logger::event('Control validation on field “' . $this->name . '” has failed (required)');
			$valid = FALSE;
		}

		if ($this->minLength and ''!=$value and strlen($value) < $this->minLength) {
			Logger::event('Control validation on field “' . $this->name . '” has failed (minLength=' . $this->minLength . ')');
			$valid = FALSE;
		}

		if ($this->maxLength and strlen($value) > $this->maxLength) {
			Logger::event('Control validation on field “' . $this->name . '” has failed (maxLength=' . $this->maxLength . ')');
			$valid = FALSE;
		}

		return $valid;

	}

}

class FormControlButton extends FormControl {

	/**
	 * Button type (submit, reset, button).
	 * @var string
	 */
	private $type;

	/**
	 * FontAwesome icon class.
	 * @var string
	 */
	private $faIcon;

	/**
	 * Sets type for a FormControlButton (submit, reset, button). Chainable method.
	 *
	 * @param	string	The button type.
	 *
	 * @return	FormControlButton
	 */
	public function setType(string $type): FormControlButton {

		$this->type = $type;
		return $this;

	}

	/**
	 * Sets a FontAwesome icon for this button object. Chainable method.
	 *
	 * @param	string	The icon class.
	 *
	 * @return	FormControlButton
	 */
	public function setFaIcon(string $class): FormControlButton {

		$this->faIcon = $class;
		return $this;

	}

	/**
	 * Renders an HTML button form control prepending an optional FontAwesome icon.
	 *
	 * @return	string
	 */
	public function render(): string {

		$ret = '<button type="' . $this->type . '"' ;

		if ($this->id) {
			$ret .= 'id=' . $this->id;
		}

		if ($this->name) {
			$ret .= ' ' . $this->getNameProperty();
		}

		$ret .= $this->processProperties() . '>';

		if ($this->faIcon) {
			$ret .= '<i class="fa ' . $this->faIcon . '"></i> ';
		}

		$ret .= trim(htmlspecialchars((string)$this->value)) . ' </button>';

		return $ret;

	}

	/**
	 * Validation is disabled for buttons, returns always TRUE.
	 *
	 * @return	bool
	 */
	public function validate(): bool {

		return TRUE;

	}

}