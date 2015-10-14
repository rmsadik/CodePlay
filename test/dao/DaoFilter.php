<?php

/**
 * Tracks the global filter parameters to be applied to all queries against the Dao
 *
 * @package Core
 * @subpackage Dao
 */
class DaoFilter
{
	/**
	 * Equal To
	 */
	const EQ = '=';
	
	/**
	 * Not Equal To
	 */
	const NEQ = '!=';
	
	/**
	 * Between
	 */
	const BETWEEN = 'between';
	
	private $field = '';
	private $operator = '';
	private $value = '';
	
	/**
	 * Create a DaoFilter
	 *
	 * @param string $field
	 * @param string $operator
	 * @param string|array[]string $value
	 */
	public function __construct($field, $operator, $value)
	{
		$this->field = $field;
		$this->operator = $operator;
		$this->value = $value;
		
		if ($this->operator == DaoFilter::BETWEEN && (!is_array($this->value) || count($this->value) != 2))
		{
			throw new DaoFilterException('DaoFilter::BETWEEN requires an array of 2 values only');
		}
		
		if (is_array($this->value))
		{
			foreach ($this->value as $v)
			{
				if (is_string($v))
				{
					throw new DaoFilterException('DaoFilter can only be matched against integer values');
				}
			}
		}
	}
	
	/**
	 * Get the field for this filter
	 *
	 * @return string
	 */
	public function getField()
	{
		return $this->field;
	}

	/**
	 * Get the operator for this filter
	 *
	 * @return string
	 */
	public function getOperator()
	{
		return $this->operator;
	}
	
	/**
	 * Get the value for this filter
	 *
	 * @return mixed|array[]int
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * Build a string representation of the DaoFilter which can be used in an SQL query
	 *
	 * @return string
	 */
	public function __toString()
	{
		if (strlen(trim($this->field)) == 0)
		{
			return '';
		}
		
		$out = ':' . $this->field . ' ';
		
		if ($this->operator == DaoFilter::BETWEEN)
		{
			$out .= $this->operator . ' ';
			$out .= $this->value[0] . ' and ' . $this->value[1];
		}
		else
		{ 
			if (is_array($this->value))
			{
				$op = ($this->operator == DaoFilter::EQ) ? 'in ' : 'not in ';
				$out .= $op . '(' . implode(',', $this->value) . ')';
				
			}
			else
			{
				$out .= $this->operator . ' ' . $this->value;
			}
		}
		
		return $out;
	}
}

?>