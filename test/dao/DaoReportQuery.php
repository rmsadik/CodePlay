<?php

class DaoReportQuery
{
	const ASC = 'asc';
	const DESC = 'desc';
	
	private $focus;
	private $distinct = true;
	private $columns = array();
	private $joins = array();
	private $groupBys = array();
	private $orderBys = array();
	private $whereClause = '';
	private $whereParams = array();
	private $havingClause = '';
	private $havingParams = array();
	private $unions = array();
	public $limit=null;
	public $PageNumber = null;
	public $PageSize = 50;
	public $TotalRows = 0;
	public $TotalPages = 0;
	
	public $unionAll = false;
	
	private $additionalJoin="";

	/**
	 * @param string $focusClass
	 * @param bool $distinct
	 */
	function __construct($focusClass=null, $distinct=true)
	{
		$this->focus = $focusClass;
		$this->distinct = $distinct;
	}

	/**
	 * Add a sub DaoReportQuery for insertion into an SQL UNION query
	 * 
	 * @param DaoReportQuery $query
	 */
	function union(DaoReportQuery $query)
	{
		$this->unions[] = $query;
	}
	
	/**
	 * Getter for WHERE parameters
	 * 
	 * @return array[]mixed
	 */
	function getWhereParams()
	{
		return $this->whereParams;
	}

	/**
	 * Getter for HAVING parameters
	 * 
	 * @return array[]mixed
	 */
	function getHavingParams()
	{
		return $this->havingParams;
	}
	
	/**
	 * Register a column for output in the report query
	 * 
	 * @param string $name
	 * @param string $alias
	 * @return DaoColumn
	 */
	function column($name, $alias=null)
	{
		$col = new DaoColumn($name, $alias);
		$this->columns[] = $col;
		return $col;
	}
	
	/**
	 * Returns an array of DaoColumn instances for this report query
	 * 
	 * @return array[]DaoColumn
	 */
	function getColumns()
	{
		return $this->columns;
	}
	
	function leftJoin($relationship, $alias=null,$where="")
	{
		$join = new DaoJoin($this->focus);
		$join->leftJoin($relationship, $alias,$where);
		$this->joins[] = $join;
		return $join;
	}
	
	function innerJoin($relationship, $alias=null,$where="")
	{
		$join = new DaoJoin($this->focus);
		$join->innerJoin($relationship, $alias,$where);
		$this->joins[] = $join;
		return $join;
	}
	
	function groupBy($relationship, $direction=DaoReportQuery::ASC)
	{
		if ($this->focus != null)
		{
			if (strpos($relationship, '.') === false)
			{
				$relationship = $this->focus . '.' . $relationship;
			}
		}

		$this->groupBys[] = $relationship . ' ' . $direction;		
//		$relationship .= '.' . $direction;
//		$this->groupBys[] = explode('.', $relationship);
		
		return $this;
	}
	
	
	
	function limitBy($limit)
	{
		$this->limit=$limit;
		return $this;
	}
	
	function orderBy($relationship, $direction=DaoReportQuery::ASC,$overide=false)
	{
		if (($this->focus != null)  and ($overide==false))
		{
			if (strpos($relationship, '.') === false)
			{
				$relationship = $this->focus . '.' . $relationship;
			}
		}

		$this->orderBys[] = $relationship . ' ' . $direction;
//			$relationship .= '.' . $direction;
//			$this->orderBys[] = explode('.', $relationship);
		
		return $this;
	}
	
	
	/**
	 * Set the where clause on the query
	 *
	 * @param string $clause
	 * @return DaoReportQuery
	 */
	public function where($clause, $params=array())
	{
		if (strlen($this->whereClause) > 0)
		{
			$this->whereClause .= ' and (' . $clause . ')';
		}
		else
		{
			$this->whereClause = '(' . $clause . ')';
		}
		$this->whereParams = array_merge($this->whereParams, $params);
		return $this;
	}
		
	
	/**
	 * Set the having clause on the query
	 *
	 * @param string $clause
	 * @return DaoReportQuery
	 */
	public function having($clause, $params=array())
	{
		if (strlen($this->havingClause) > 0)
		{
			$this->havingClause .= ' and (' . $clause . ')';
		}
		else
		{
			$this->havingClause = '(' . $clause . ')';
		}
		
		$this->havingParams = array_merge($this->havingParams, $params);
		
		return $this;
	}
	
	public function page($pageNumber, $pageSize=50)
	{
		$this->PageNumber = $pageNumber;
		$this->PageSize = $pageSize;
	}

	/**
	 * Generate the sql statement to be run
	 * 
	 * @return string
	 */
	function generate($unionMode=false)
	{
		if (count($this->unions) > 0)
		{
			$sql = '';
			
			for ($i=0; $i<count($this->unions); $i++)
			{
				if ($i > 0)
				{
					$sql .= 'union ';
					if($this->unionAll)
						$sql .= 'all ';
					$mode = true;
				} else {
					$mode = false;
				}

				$sql .= '(' . $this->unions[$i]->generate($mode) . ') ';
			}
		}
		else
		{
			$sql = 'select ';
			
			if ($this->distinct)
				$sql .= 'distinct ';
			
			if (!$unionMode)
				$sql .= 'sql_calc_found_rows ';
				//$sql .= 'count(*) ';
			
			// Add output columns
			for ($i=0; $i<count($this->columns); $i++)
			{
				if ($i > 0)
					$sql .= ', ';
					
				$sql .= (string)$this->columns[$i];
			}
			
			DaoMap::loadMap($this->focus);
			$sql .= ' from ' . strtolower($this->focus) . ' ' . DaoMap::$map[strtolower($this->focus)]['_']['alias'];
			
			// Add table joins
			foreach ($this->joins as $join)
			{
				$sql .= (string)$join;
			}
		}
		
		if($this->additionalJoin !="")
			$sql .= " ".$this->additionalJoin." ";
		
		// Where clause
		if (strlen($this->whereClause) > 0)
			$sql .= ' where ' . $this->whereClause;
		
		// Group by
		if (count($this->groupBys) > 0)
		{
			$sql .= ' group by ' . implode(', ', $this->groupBys);
//			
//			for ($i=0; $i<count($this->groupBys); $i++)
//			{
//				if ($i > 0)
//					$sql .= ', ';
//					
//				$sql .= $this->groupBys[$i][0] . '.' . $this->groupBys[$i][1] . ' ' . $this->groupBys[$i][2];
//			}
		}
		
		// Having clause
		if (strlen($this->havingClause) > 0)
			$sql .= ' having ' . $this->havingClause;
			
		// Order by
		if (count($this->orderBys) > 0)
		{
			$sql .= ' order by ' . implode(', ', $this->orderBys);
//			
//			for ($i=0; $i<count($this->orderBys); $i++)
//			{
//				if ($i > 0)
//					$sql .= ', ';
//					
//				$sql .= $this->orderBys[$i][0] . '.' . $this->orderBys[$i][1] . ' ' . $this->orderBys[$i][2];
//			}
		}
		// limit clause
		if (strlen($this->limit) > 0)
			$sql .= ' limit ' . $this->limit;
			
			
		// Paging
		if (!is_null($this->PageNumber) && $this->PageSize > 0)
		{
			$startRecord = ($this->PageNumber - 1) * $this->PageSize;
			$sql .= ' limit ' . max($startRecord, 0) . ', ' . $this->PageSize;
		}
		return $sql;
	}
	
	function __toString()
	{
		return $this->generate();
	}
	
	/**
	 * Run the report against the database and return the result in raw array format
	 * 
	 * @return array[]array[]mixed
	 */
	function execute($calculateRows=true, $fetchParam=PDO::FETCH_NUM)
	{
		$sql = $this->generate(!$calculateRows);
		
		$params = array();
		
		if (count($this->unions) > 0)
		{
			foreach ($this->unions as $union)
			{
				$params = array_merge($params,$union->getWhereParams());
				$params = array_merge($params,$union->getHavingParams());
			}
		}
		
		$params = array_merge($params,$this->getWhereParams());
		$params = array_merge($params,$this->getHavingParams());
				
		try
		{
			$stmt = Dao::execSql($sql, $params);  
			$results = $stmt->fetchAll($fetchParam);
		}
		catch(Exception $e)
		{
			if (Dao::$profilerError === false) 	//throw the error if it is not coming from the profiler
				throw $e;	
			
			Dao::$profilerError = false;			//reset to false to avoid missing other exceptions
		}

		if (!is_array($results))
		{
			throw new HydraDaoException('An error occured while running the report');
		}
		
		// Work out the result size stats
		if(!$calculateRows || is_null($this->PageNumber))
		{
			$this->TotalRows = count($results);
		}
		else
		{
			$sql = 'select found_rows() `0`';
			
			try 
			{
				$stmt = Dao::execSql($sql);		
				$my = $stmt->fetch($fetchParam);
			}
			catch(Exception $e)
			{
				if (Dao::$profilerError === false) 	//throw the error if it is not coming from the profiler
					throw $e;	
				
				Dao::$profilerError = false;			//reset to false to avoid missing other exceptions
			}
			$this->TotalRows = $my[0];
		}
		
		$this->TotalPages = ceil($this->TotalRows / $this->PageSize);
		
		return $results;
	}
	
	/**
	 * getter additionalJoin
	 *
	 * @return additionalJoin
	 */
	public function getAdditionalJoin()
	{
		return $this->additionalJoin;
	}
	
	/**
	 * setter additionalJoin
	 *
	 * @var additionalJoin
	 */
	public function setAdditionalJoin($additionalJoin)
	{
		$this->additionalJoin = $additionalJoin;
	}

	/**
	 * function equivalent to mysql_escape_string()
	 *
	 * @param string $string
	 * @return escaped string
	 */
	public function escapeString($string)
	{
		if (!get_magic_quotes_gpc())
		{
			$string = addslashes($string);
		}
		return $string;
	}
}

?>