<?php

/**
 * Defines a column od data that can be returned
 *
 * @package Core
 * @subpackage Dao
 */
class DaoColumn
{
	/**
	 * @var string
	 */
	private $column;
	
	/**
	 * @var string
	 */
	private $alias;

	/**
	 * Create an instance of a column
	 *
	 * @param string $name
	 */
	public function __construct($name, $alias=null)
	{
		$this->column = $name;
		$this->alias = $alias;
	}

	/**
	 * Factory method for registering new column instances
	 *
	 * @param string $name
	 * @return DaoColumn
	 */
	public static function create($name)
	{
		return new DaoColumn($name);
	}

	/**
	 * Sum a column of data
	 *
	 * @return DaoColumn
	 */
	public function sum()
	{
		$this->column = 'sum(' . $this->column . ')';
		return $this;
	}

	/**
	 * Find the biggest number in a column of data
	 *
	 * @return DaoColumn
	 */
	public function max()
	{
		$this->column = 'max(' . $this->column . ')';
		return $this;
	}

	/**
	 * Find the smallest number in a column of data
	 *
	 * @return DaoColumn
	 */
	public function min()
	{
		$this->column = 'min(' . $this->column . ')';
		return $this;
	}
	
	/**
	 * Count a column of data
	 *
	 * @return DaoColumn
	 */
	public function count()
	{
		$this->column = 'count(' . $this->column . ')';
		return $this;
	}
	
	/**
	 * Average a column of data
	 *
	 * @return DaoColumn
	 */
	public function avg()
	{
		$this->column = 'avg(' . $this->column . ')';
		return $this;
	}
	
	public function concat(array $values)
	{
		$fieldlist = implode(", ",$values);
		$this->column = 'concat('.$fieldlist.')';
		return $this;
	}
	
	public function group_concat($seperator = ',',$distinct = true)
	{
		$name = $this->column;
		$this->column = 'group_concat(';
		if($distinct)
			$this->column .= 'DISTINCT ';
		$this->column .= $name.' SEPARATOR \''.$seperator.'\')';
		return $this;
	}
	
	/**
	 * Run a column of data through a callback
	 *
	 * @return DaoColumn
	 */
	public function custom($callback)
	{
		$this->column = call_user_func($callback, $this->column);
		return $this;
	}
	
	public function __toString()
	{
		if (is_null($this->alias))
			return $this->column;
			
		return $this->column . ' ' . $this->alias;
	}
}

?>