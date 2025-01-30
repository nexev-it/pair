<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Time extends FormControl {

	/**
	 * Minimum allowed length for value.
	 */
	protected string|DateTime|NULL $min = NULL;

	/**
	 * Maximum allowed length for value.
	 */
	protected string|DateTime|NULL $max = NULL;

	/**
	 * Set the minimum value for this control. It’s a chainable method.
	 *
	 * @param string|\DateTime If string, valid format is 'H:i'.
	 */
	public function min(string|\DateTime $minValue): self {

		$this->min = is_a($minValue, 'DateTime')
		? $minValue->format('Y-m-d')
		: (string)$minValue;

		return $this;

	}

	/**
	 * Set the maximum value for this control. It’s a chainable method.
	 *
	 * @param string|\DateTime If string, valid format is 'H:i'.
	 */
	public function max(string|\DateTime $maxValue): self {

		$this->max = is_a($maxValue, 'DateTime')
		? $maxValue->format('Y-m-d')
		: (string)$maxValue;

		return $this;

	}

	public function render(): string {

		$ret = '<input ' . $this->nameProperty();
		$ret .= ' type="time"';

		if ($this->value) {
			$ret .= ' value="' . $this->value . '"';
		}

		if (!is_null($this->min)) {
			$ret .= ' min="' . (string)$this->min . '"';
		}

		if (!is_null($this->max)) {
			$ret .= ' max="' . (string)$this->max . '"';
		}

		$ret .= $this->processProperties() . ' />';

		return $ret;

	}

}