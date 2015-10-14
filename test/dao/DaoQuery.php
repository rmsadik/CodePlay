<?php

/**
 * Class for generating SQL statements for use by the Dao pattern
 *
 * @package Core
 * @subpackage Dao
 */
class DaoQuery
{
	public $DefaultJoinType = 'inner join';
	protected $joinOverride = "";
	private $joinTracker = array();
	private $focus = null;
	private $distinct = false;
	private $eagerLoads = array();
	private $whereClause = '';
	private $orderBy = array();
	private $sql = '';
	private $classes = array();
	private $pageSize = 30;
	private $pageNumber = 1;
	private $withPaging = false;
	
	/**
	 * Creates a new DaoQuery, initialised to a focus object
	 *
	 * @param string $entityName
	 * @param int $pageNumber optional
	 * @param int $pageSize optional
	 */
	public function __construct($entityName, $pageNumber=null, $pageSize=30)
	{
		$this->focus = $entityName;
		
		if (!is_null($pageNumber))
		{
			if (intval($pageNumber) < 1)
			{
				$pageNumber = 1;
			}
			
			$this->pageNumber = intval($pageNumber);
			$this->pageSize = intval($pageSize);
			$this->withPaging = true;
		}
		
		DaoMap::loadMap($entityName);
	}

	/**
	 * daoOverride (hack method)
	 *
	 * @param DaoJoin $joins
	 */
	public function daoOverride($joins)
	{
		if (is_array($joins))
		{
			$this->joinOverride = implode(' ', $joins);
		}
		else
		{
			$this->joinOverride = $joins->__toString();
		}
	}
	
	/**
	 * Returns the class name used to instantiate this DaoQuery instance
	 * 
	 * @return string
	 */
	public function getFocusClass()
	{
		return $this->focus;
	}
	
	/**
	 * Get a list of classes that appear in the result set
	 *
	 * @return array[]string
	 */
	public function getJoinClasses()
	{
		return $this->classes;
	}
	
	/**
	 * Set the distinct behaviour on a select query
	 *
	 * @param bool $bool
	 * @return DaoQuery
	 */
	public function distinct($bool)
	{
		$this->distinct = (bool)$bool;
		
		return $this;
	}
	
	/**
	 * Set a relationship to eager load for performance reasons
	 *
	 * @param string $relationship
	 * @return DaoQuery
	 */
	public function eagerLoad($relationship, $joinType='inner')
	{
		if (!$this->isEager($relationship))
		{
			$join = explode('.', $relationship);
			$join[2] = $joinType;
			$this->eagerLoads[] = $join;
		}
		
		return $this;
	}

	/**
	 * Check if a relationship is being eager loaded or not
	 *
	 * @param string $relationship
	 * @return bool
	 */
	public function isEager($relationship)
	{
		return in_array(explode('.', $relationship), $this->eagerLoads);
	}
	
	/**
	 * Set the order by clauses on the query
	 *
	 * @param string $field
	 * @param string $direction
	 * @return DaoQuery
	 */
	public function orderBy($field, $direction='asc')
	{
		$tmp = array();
		if(strstr($field,"("))
			$tmp[] = $field;
		else
			$tmp = explode('.', $field);
		$tmp[] = $direction;
		$this->orderBy[] = $tmp;
		return $this;
	}
	
	/**
	 * Set the where clause on the query
	 *
	 * @param string $clause
	 */
	public function where($clause)
	{
		if (strlen($this->whereClause) > 0)
		{
			$this->whereClause .= ' and (' . $clause . ')';
		}
		else
		{
			$this->whereClause = $clause;
		}
		
		return $this;
	}

	/**
	 * Set which page number should return in the results
	 *
	 * @param int $pageNumber
	 * @param int $pageSize
	 */
	public function getPage($pageNumber=1, $pageSize=30)
	{
		if (intval($pageNumber) < 1)
		{
			$pageNumber = 1;
		}
		
		$this->pageNumber = intval($pageNumber);
		$this->pageSize = intval($pageSize);
		$this->withPaging = true;
		
		return $this;
	}

	/**
	 * Get the paging stats that were used in the query
	 *
	 * @return array[int,int]
	 */
	public function getPageStats()
	{
		return array($this->pageNumber, $this->pageSize);
	}
	
	/**
	 * Check if the results are paged or not
	 *
	 * @return bool
	 */
	public function isPaged()
	{
		return $this->withPaging;
	}
	
	public function parseBaseQuery()
	{
		if (!isset(DaoMap::$map[strtolower($this->focus)]['_']['base']))
		{
			return '';
		}
		
		$base = DaoMap::$map[strtolower($this->focus)]['_']['base'];

		if (strlen($base) == 0)
		{
			return '';
		}
		
		$tags = array();
		preg_match_all('/(inner join|left join) \[(.+)\]/U', $base, $tags);

		$alias = DaoMap::$map[strtolower($this->focus)]['_']['alias'];
		$leftClass = $this->focus;
		 
		for ($i=0; $i<count($tags[0]); $i++)
		{
			$joinType = $tags[1][$i];
			$tag = $tags[2][$i];
			
			list($entity, $field) = explode('.', $tag);
			
			if ($entity == $this->focus)
			{
				$alias = DaoMap::$map[strtolower($this->focus)]['_']['alias'];
				$leftClass = $this->focus;
			}
			
			DaoMap::loadMap($entity);
		 	$joins = $this->buildJoin($joinType, $leftClass, $alias, $entity, $field);
		
			$base = str_replace('[' . $tag . ']', implode(' ', $joins), $base);
			
			if (DaoMap::$map[strtolower($entity)][$field]['rel'] == DaoMap::MANY_TO_MANY)
			{
				$entity = DaoMap::$map[strtolower($entity)][$field]['class'];
				$field = '_';
			}
			
			$alias = DaoMap::$map[strtolower($entity)][$field]['alias'];
			
			$leftClass = $entity;
		}
		
		return $base;
	}
	
	private function buildJoin($joinType, $leftClass, $alias, $entity, $field)
	{
		$hash = md5($leftClass . ':' . $alias . ':' . $entity . ':' . $field);
		
		if (in_array($hash, $this->joinTracker))
		{
			return array();
		}
		
		$this->joinTracker[] = $hash;
		DaoMap::loadMap($entity);
		
		$p = DaoMap::$map[strtolower($entity)][$field];
		$joins = array();
		
		$focus = strtolower($this->focus);
		$joinTable = strtolower($p['class']);
		$joinAlias = $p['alias'];
		
		if ($p['rel'] == DaoMap::MANY_TO_MANY)
		{
			$joinTableMap = strtolower($p['class']);
			
			// Join in the many to many join table
			if ($p['side'] == DaoMap::RIGHT_SIDE)
			{
				$mtmJoinTable = strtolower($entity) . '_' . $joinTableMap; 
			}
			else
			{
				$mtmJoinTable = $joinTableMap . '_' . strtolower($entity); 
			}
			
			$joins[] = sprintf('%s on (%s.id = %s.%sId)',
				$mtmJoinTable,
				$alias,
				$mtmJoinTable,
				strtolower(substr($entity, 0, 1)) . substr($entity, 1));

			DaoMap::loadMap($p['class']);
			$joinAlias = DaoMap::$map[$joinTable]['_']['alias'];
			
			$joinTableMap = strtolower($entity);
			$joins[] = sprintf('%s %s %s on (%s.%sId = %s.id)',
				$joinType,
				$joinTable,
				$joinAlias,
				$mtmJoinTable,
				strtolower(substr(DaoMap::$map[$joinTableMap][$field]['class'], 0, 1)) . substr(DaoMap::$map[$joinTableMap][$field]['class'], 1),
				$joinAlias);
		}
		else if ($p['rel'] == DaoMap::ONE_TO_MANY)
		{
			$joins[] = sprintf('%s %s on (%s.id = %s.%sId)',
				$joinTable,
				$joinAlias,
				$alias,
				$joinAlias,
				strtolower(substr($leftClass, 0, 1)) . substr($leftClass, 1));
		}
		else if ($p['rel'] == DaoMap::ONE_TO_ONE)
		{
			if ($p['owner'])
			{
				$joins[] = sprintf('%s %s on (%s.%sId = %s.id)',
					$joinTable,
					$joinAlias,
					$alias,
					$field,
					$joinAlias);
			}
			else
			{
				$joins[] = sprintf('%s %s on (%s.id = %s.%sId)',
					$joinTable,
					$joinAlias,
					$alias,
					$joinAlias,
					strtolower(substr($leftClass, 0, 1)) . substr($leftClass, 1));
			}
		}
		else
		{
			$joins[] = sprintf('%s %s on (%s.%sId = %s.id)',
				$joinTable,
				$joinAlias,
				$alias,
				$field,
				$joinAlias);
		}
		
		return $joins;
	}
	
	/**
	 * Create an update SQL query
	 * 
	 * @return string
	 */
	public function generateForUpdate()
	{
		// Load the Dao map for the focus entity
		DaoMap::loadMap($this->focus);
		
		$focus = strtolower($this->focus);
		
		// ----------------------------------------------------------
		// Grab the fields to insert in the table
		// ----------------------------------------------------------
		$fields = array();
		foreach (DaoMap::$map[$focus] as $field => $properties)
		{
			if ($field == '_')
			{
				continue;
			}
			
			if (isset($properties['rel']))
			{
				if (in_array($properties['rel'], array(DaoMap::MANY_TO_MANY, DaoMap::ONE_TO_MANY)))
				{
					continue;
				}

				if ($properties['rel'] == DaoMap::ONE_TO_ONE and !$properties['owner'])
				{
					continue;
				}
				
				if ($properties['rel'] == DaoMap::MANY_TO_ONE or ($properties['rel'] == DaoMap::ONE_TO_ONE and $properties['owner']))
				{
					$field .= 'Id';
				}
			}
						
			$fields[] = '`' . $field . '`=?';
		}
		
		$sql = 'update ' . $focus . ' set ';
		$sql .= implode(', ', $fields);
		$sql .= ' where id=?';
		
		return $sql;
	}
	
	/**
	 * Create an insert SQL query
	 *
	 * @return string
	 */
	public function generateForInsert()
	{
		// Load the Dao map for the focus entity
		DaoMap::loadMap($this->focus);
		
		$focus = strtolower($this->focus);
		
		// ----------------------------------------------------------
		// Grab the fields to insert in the table
		// ----------------------------------------------------------
		$fields = array();
		$values = array();
		foreach (DaoMap::$map[$focus] as $field => $properties)
		{
			if ($field == '_')
			{
				continue;
			}
			
			if (isset($properties['rel']))
			{
				if (in_array($properties['rel'], array(DaoMap::MANY_TO_MANY, DaoMap::ONE_TO_MANY)))
				{
					continue;
				}
				
				if ($properties['rel'] == DaoMap::ONE_TO_ONE and !$properties['owner'])
				{
					continue;
				}
				
				if ($properties['rel'] == DaoMap::MANY_TO_ONE or ($properties['rel'] == DaoMap::ONE_TO_ONE and $properties['owner']))
				{
					$field .= 'Id';
				}
			}
			
			$fields[] = '`' . $field . '`';
			$values[] = '?';
		}
		
		//change database if it is a profiling query, and profiling db is not null
		$db = Profiler::getProfilerDatabase();
		if (($focus == 'profileresource' || $focus == 'profilesql') && $db !== false)
			$sql = "insert into $db.$focus";
		else 
			$sql = "insert into $focus";
		
		$sql .= ' (' . implode(',', $fields) . ') values (' . implode(',', $values) . ')';
		
		return $sql;
	}
	
	/**
	 * Create a udpate SQL query for bulkupdates
	 *
	 * @return string
	 */
	public function generateForBulkUpdate($setClause)
	{
		// Load the Dao map for the focus entity
		DaoMap::loadMap($this->focus);
		$focus = strtolower($this->focus);
        if(trim($this->whereClause) === '')
            throw new HydraDaoException('System Error: ' . __FUNCTION__. ' need a where clause at least!');
		$sql = 'update ' . $focus . ' set ' . $setClause . ',  updatedById = \''.Core::getUser()->getId() . '\' where ' . $this->whereClause;
		return $sql;
	}
	
	/**
	 * Create a delete SQL query
	 *
	 * @return string
	 */
	public function generateForDelete()
	{
		// Load the Dao map for the focus entity
		DaoMap::loadMap($this->focus);
		
		$focus = strtolower($this->focus);

		$sql = 'delete from ' . $focus . ' where id=?';
		return $sql;
	}
	
	/**
	 * Create a select SQL query
	 *
	 * @return string
	 */
	public function generateForSelect()
	{
		// Load the Dao map for the focus entity
		DaoMap::loadMap($this->focus);
		
		$focus = strtolower($this->focus);
		$fAlias = DaoMap::$map[$focus]['_']['alias'];
		
		if (Dao::$AutoActiveEnabled)
		{
			$this->where($fAlias . '.active=1');
		}
		
		if (!Dao::$LazyLoadInProgress)
		{
			// Load the query base
			$base = ' ' . $this->parseBaseQuery();
	
			// Inject global user filters into the query
			if (Dao::getFilterSet() instanceof DaoFilterSet && isset(DaoMap::$map[$focus]['_']['filters']) && is_array(DaoMap::$map[$focus]['_']['filters']) && count(DaoMap::$map[$focus]['_']['filters']) > 0)
			{
				$filterSet = Dao::getFilterSet();
				$filters = $filterSet->getFilters(DaoMap::$map[$focus]['_']['filters']);
				
				$filters = '(' . implode(') and (', $filters) . ')';
				//echo "===>" ; Debug::inspect($filters );
				foreach (DaoMap::$map[$focus]['_']['filters'] as $filterName => $sqlFragment)
				{
					$filters = str_replace(':' . $filterName, $sqlFragment, $filters);
				}
				
				if ($filters != '()')
					$this->where($filters);
			}
		}
				
		// ----------------------------------------------------------
		// Select which fields to return in the query on the focus table
		// ----------------------------------------------------------
		$fields = array();
		$fields[] = $fAlias . '.`id`';
		foreach (DaoMap::$map[$focus] as $field => $properties)
		{
			if ($field == '_')
			{
				continue;
			}
			
			if (isset($properties['rel']))
			{
				switch ($properties['rel'])
				{
					// Don't return any of these field types
					case DaoMap::ONE_TO_MANY:
					case DaoMap::MANY_TO_MANY:
						break;

					case DaoMap::ONE_TO_ONE:
						if ($properties['owner'])
						{
							$fields[] = $fAlias . '.`' . $field . 'Id`';
						}
						else
						{
							// Make sure we eager loading this before including it
							if ($this->isEager($this->focus . '.' . $field))
							{
								$fields[] = $properties['alias'] . '.`id`';
							}
						}
						break;
						
					default:
						$field .= 'Id';
						$fields[] = $fAlias . '.`' . $field . '`';
						break;
				}
			}
			else
			{
				$fields[] = $fAlias . '.`' . $field . '`';
			}
		}
		
		// ----------------------------------------------------------
		// Link in the join tables
		// ----------------------------------------------------------
		$joins = array();
		foreach ($this->eagerLoads as $eager)
		{
			$entity = DaoMap::$map[strtolower($eager[0])][$eager[1]]['class'];
			
			// Register the class for eager loading
			$this->classes[] = $entity . ':' . $eager[1];
			
			$tmp = $this->buildJoin(' ' . $eager[2] . ' join ', $this->focus, $fAlias, $eager[0], $eager[1]);
			
			foreach ($tmp as $join)
			{
				if (substr($join, 0, strlen($this->DefaultJoinType) + 2) != ' ' . $this->DefaultJoinType . ' ')
				{
					$join = ' ' . $this->DefaultJoinType . ' ' . $join;
				}
				
				$joins[] = $join;
			}

			// Check if we need to add active flag checking on the entities
			if (Dao::$AutoActiveEnabled)
			{
				$mSource = DaoMap::$map[strtolower($eager[0])][$eager[1]];
				DaoMap::loadMap($mSource['class']);
				$mTarget = DaoMap::$map[strtolower($mSource['class'])];

				if ($mSource['rel'] == DaoMap::ONE_TO_MANY)
				{
					//$this->where(DaoMap::$map[strtolower($eager[0])]['_']['alias'] . '.active=1');
					$this->where($mTarget['_']['alias'] . '.active=1');
				}
				else if ($mSource['rel'] == DaoMap::MANY_TO_MANY && $mTarget['_']['versioned'])
				{
					$this->where($mTarget['_']['alias'] . '.active=1');
				}
			}
			
			if (DaoMap::$map[strtolower($eager[0])][$eager[1]]['rel'] == DaoMap::MANY_TO_ONE)
			{
				// Return the many-to-one fields back in the result set as well
				$joinTableMap = DaoMap::$map[strtolower($eager[0])][$eager[1]]['class'];
				DaoMap::loadMap($joinTableMap);
				$joinTableMap = strtolower($joinTableMap);
				
				foreach (DaoMap::$map[$joinTableMap] as $field => $properties)
				{
					if ($field == '_')
					{
						continue;
					}
	
					if (isset($properties['rel']))
					{
						switch ($properties['rel'])
						{
							case DaoMap::ONE_TO_MANY:
							case DaoMap::MANY_TO_MANY:
							case DaoMap::ONE_TO_ONE:
								break;
								
							case DaoMap::MANY_TO_ONE:
							default:
								// If the child has many to one children, this may not work?
								$field .= 'Id';
								$fields[] = DaoMap::$map[strtolower($eager[0])][$eager[1]]['alias'] . '.`' . $field . '`';
								break;
						}
					}
					else
					{
						$fields[] = DaoMap::$map[strtolower($eager[0])][$eager[1]]['alias'] . '.`' . $field . '`';
					}
				}
			}
			
		}

		// ----------------------------------------------------------
		// Set the order by clause
		// ----------------------------------------------------------
		if (count($this->orderBy) == 0 && is_array(DaoMap::$map[$focus]['_']['sort']))
		{
			$this->orderBy(DaoMap::$map[$focus]['_']['sort'][0], DaoMap::$map[$focus]['_']['sort'][1]);
		}
		
		$orders = array();
		foreach ($this->orderBy as $order)
		{
			if (strtolower($order[0]) == $focus)
			{
				$orders[] = $fAlias . '.`' . $order[1] . '` ' . strtolower($order[2]);
			}
			else if(strstr(strtolower($order[0]),"("))
			{
				$originalOrder = implode(" ",$order);
				$orders[] = $originalOrder;
			}
			else
			{
				$tmp = strtolower(substr($order[0], 0, 1)) . substr($order[0], 1);
				$orders[] = DaoMap::$map[$focus][$tmp]['alias'] . '.`' . $order[1] . '` ' . strtolower($order[2]);
			}
		}

		// ----------------------------------------------------------
		// Build the sql query
		// ----------------------------------------------------------
		$sql = 'select ';
		
		if ($this->distinct)
		{
			$sql .= 'distinct ';
		}
		
		if ($this->withPaging)
		{
			$sql .= 'sql_calc_found_rows ';
		}
		
		$sql .= implode(', ', $fields);
		$sql .= sprintf(' from %s %s', $focus, $fAlias);
		
		if (!Dao::$LazyLoadInProgress)
		{
			$sql .= $base;
		}
		
		$sql .= implode('', $joins);
		
		// HACK: Yes I went there.
		if($this->joinOverride != "")
		{
			$sql .= $this->joinOverride;
		}
			
		
		if (strlen($this->whereClause) > 0)
		{
			$sql .= ' where ' . $this->whereClause;
		}
		
		if (count($orders) > 0)
		{
			$sql .= ' order by ' . implode(', ', $orders);
		}
		
		if ($this->withPaging)
		{
			$startRecord = ($this->pageNumber - 1) * $this->pageSize;
			$sql .= ' limit ' . $startRecord . ', ' . $this->pageSize;
		}
		$this->sql = $sql;
		return $sql;
	}
	
	/**
	 * Create a select SQL query
	 *
	 * @return string
	 */
	public function generateForCount()
	{
		// Load the Dao map for the focus entity
		DaoMap::loadMap($this->focus);
	
		$focus = strtolower($this->focus);
		$fAlias = DaoMap::$map[$focus]['_']['alias'];
	
		if (Dao::$AutoActiveEnabled)
		{
			$this->where($fAlias . '.active=1');
		}
	
		if (!Dao::$LazyLoadInProgress)
		{
			// Load the query base
			$base = ' ' . $this->parseBaseQuery();
	
			// Inject global user filters into the query
			if (Dao::getFilterSet() instanceof DaoFilterSet && isset(DaoMap::$map[$focus]['_']['filters']) && is_array(DaoMap::$map[$focus]['_']['filters']) && count(DaoMap::$map[$focus]['_']['filters']) > 0)
			{
				$filterSet = Dao::getFilterSet();
				$filters = $filterSet->getFilters(DaoMap::$map[$focus]['_']['filters']);
	
				$filters = '(' . implode(') and (', $filters) . ')';
				//echo "===>" ; Debug::inspect($filters );
				foreach (DaoMap::$map[$focus]['_']['filters'] as $filterName => $sqlFragment)
				{
					$filters = str_replace(':' . $filterName, $sqlFragment, $filters);
				}
	
				if ($filters != '()')
					$this->where($filters);
			}
		}
	
		// ----------------------------------------------------------
		// Select which fields to return in the query on the focus table
		// ----------------------------------------------------------
		$fields = array();
		$fields[] = $fAlias . '.`id`';
		foreach (DaoMap::$map[$focus] as $field => $properties)
		{
			if ($field == '_')
			{
				continue;
			}
				
			if (isset($properties['rel']))
			{
				switch ($properties['rel'])
				{
					// Don't return any of these field types
					case DaoMap::ONE_TO_MANY:
					case DaoMap::MANY_TO_MANY:
						break;
	
					case DaoMap::ONE_TO_ONE:
						if ($properties['owner'])
						{
							$fields[] = $fAlias . '.`' . $field . 'Id`';
						}
						else
						{
							// Make sure we eager loading this before including it
							if ($this->isEager($this->focus . '.' . $field))
							{
								$fields[] = $properties['alias'] . '.`id`';
							}
						}
						break;
	
					default:
						$field .= 'Id';
						$fields[] = $fAlias . '.`' . $field . '`';
						break;
				}
			}
			else
			{
				$fields[] = $fAlias . '.`' . $field . '`';
			}
		}
	
		// ----------------------------------------------------------
		// Link in the join tables
		// ----------------------------------------------------------
		$joins = array();
		foreach ($this->eagerLoads as $eager)
		{
			$entity = DaoMap::$map[strtolower($eager[0])][$eager[1]]['class'];
				
			// Register the class for eager loading
			$this->classes[] = $entity . ':' . $eager[1];
				
			$tmp = $this->buildJoin(' ' . $eager[2] . ' join ', $this->focus, $fAlias, $eager[0], $eager[1]);
				
			foreach ($tmp as $join)
			{
				if (substr($join, 0, strlen($this->DefaultJoinType) + 2) != ' ' . $this->DefaultJoinType . ' ')
				{
					$join = ' ' . $this->DefaultJoinType . ' ' . $join;
				}
	
				$joins[] = $join;
			}
	
			// Check if we need to add active flag checking on the entities
			if (Dao::$AutoActiveEnabled)
			{
				$mSource = DaoMap::$map[strtolower($eager[0])][$eager[1]];
				DaoMap::loadMap($mSource['class']);
				$mTarget = DaoMap::$map[strtolower($mSource['class'])];
	
				if ($mSource['rel'] == DaoMap::ONE_TO_MANY)
				{
					//$this->where(DaoMap::$map[strtolower($eager[0])]['_']['alias'] . '.active=1');
					$this->where($mTarget['_']['alias'] . '.active=1');
				}
				else if ($mSource['rel'] == DaoMap::MANY_TO_MANY && $mTarget['_']['versioned'])
				{
					$this->where($mTarget['_']['alias'] . '.active=1');
				}
			}
				
			if (DaoMap::$map[strtolower($eager[0])][$eager[1]]['rel'] == DaoMap::MANY_TO_ONE)
			{
				// Return the many-to-one fields back in the result set as well
				$joinTableMap = DaoMap::$map[strtolower($eager[0])][$eager[1]]['class'];
				DaoMap::loadMap($joinTableMap);
				$joinTableMap = strtolower($joinTableMap);
	
				foreach (DaoMap::$map[$joinTableMap] as $field => $properties)
				{
					if ($field == '_')
					{
						continue;
					}
	
					if (isset($properties['rel']))
					{
						switch ($properties['rel'])
						{
							case DaoMap::ONE_TO_MANY:
							case DaoMap::MANY_TO_MANY:
							case DaoMap::ONE_TO_ONE:
								break;
	
							case DaoMap::MANY_TO_ONE:
							default:
								// If the child has many to one children, this may not work?
								$field .= 'Id';
								$fields[] = DaoMap::$map[strtolower($eager[0])][$eager[1]]['alias'] . '.`' . $field . '`';
								break;
						}
					}
					else
					{
						$fields[] = DaoMap::$map[strtolower($eager[0])][$eager[1]]['alias'] . '.`' . $field . '`';
					}
				}
			}
				
		}
	
		// ----------------------------------------------------------
		// Set the order by clause
		// ----------------------------------------------------------
		if (count($this->orderBy) == 0 && is_array(DaoMap::$map[$focus]['_']['sort']))
		{
			$this->orderBy(DaoMap::$map[$focus]['_']['sort'][0], DaoMap::$map[$focus]['_']['sort'][1]);
		}
	
		$orders = array();
		foreach ($this->orderBy as $order)
		{
			if (strtolower($order[0]) == $focus)
			{
				$orders[] = $fAlias . '.`' . $order[1] . '` ' . strtolower($order[2]);
			}
			else if(strstr(strtolower($order[0]),"("))
			{
				$originalOrder = implode(" ",$order);
				$orders[] = $originalOrder;
			}
			else
			{
				$tmp = strtolower(substr($order[0], 0, 1)) . substr($order[0], 1);
				$orders[] = DaoMap::$map[$focus][$tmp]['alias'] . '.`' . $order[1] . '` ' . strtolower($order[2]);
			}
		}
	
		// ----------------------------------------------------------
		// Build the sql query
		// ----------------------------------------------------------
		$sql = 'select ';
	
		if ($this->distinct)
		{
			$sql .= 'distinct ';
		}
	
		if ($this->withPaging)
		{
			$sql .= 'sql_calc_found_rows ';
		}
	
//		$sql .= implode(', ', $fields);
		$sql .= sprintf(' count(*) from %s %s', $focus, $fAlias);
	
		if (!Dao::$LazyLoadInProgress)
		{
			$sql .= $base;
		}
	
		$sql .= implode('', $joins);
	
		// HACK: Yes I went there.
		if($this->joinOverride != "")
		{
			$sql .= $this->joinOverride;
		}
			
	
		if (strlen($this->whereClause) > 0)
		{
			$sql .= ' where ' . $this->whereClause;
		}
	
		if (count($orders) > 0)
		{
			$sql .= ' order by ' . implode(', ', $orders);
		}
	
		if ($this->withPaging)
		{
			$startRecord = ($this->pageNumber - 1) * $this->pageSize;
			$sql .= ' limit ' . $startRecord . ', ' . $this->pageSize;
		}
		$this->sql = $sql;
		return $sql;
	}
	
	public function __toString()
	{
		return 'DaoQuery("' . $this->focus . '")'; 
	}
}

?>