<?php

/**
 * Tracks the global filter parameters to be applied to all queries against the Dao
 *
 * @package Core
 * @subpackage Dao
 */
class DaoFilterSet
{
	/**
	 * @var array[]DaoFilter
	 */
	private $filters = array();
	
	/**
	 * Add a filter to the set
	 *
	 * @param DaoFilter $filter
	 */
	public function add(DaoFilter $filter)
	{
		$this->filters[] = $filter;
	}

	/**
	 * Reset the internal filter list
	 */
	public function clear()
	{
		$this->filters = array();
	}
	
	/**
	 * Get the array of DaoFilters that were added to this DaoFilterSet
	 *
	 * @param array[optional] $fields
	 * @return array[]DaoFilter
	 */
	public function getFilters($fields = null)
	{
		// Return all filters
		if (is_null($fields))
		{
			return $this->filters;
		}

		// Return only filters that have a field that is set in the $fields parameter
		$tmp = array();
		
		foreach ($this->filters as $filter)
		{
			foreach ($fields as $fieldName => $sqlFragment)
			{
				if ($fieldName == $filter->getField())
				{
					$tmp[] = $filter;
					break;
				}
			}
		}

		return $tmp;
	}
}

?>