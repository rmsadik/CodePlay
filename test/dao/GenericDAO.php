<?php

/**
 * Generic DAO
 *
 * @package Core
 * @subpackage Dao
 */
class GenericDAO
{
	/**
	 * The DaoQuery being queried by the Dao
	 *
	 * @var DaoQuery
	 */
	private $query;
	
	/**
	 * Temporary copy of the original DaoQuery
	 *
	 * @var DaoQuery
	 */
	private $tmpQuery;

	/**
	 * Last id inserted into this table
	 *
	 * @var int
	 */
	private $lastId = -1;

	/**
	 * Number of rows that were changed by the last query
	 *
	 * @var int
	 */
	private $affectedRows = -1;

	/**
	 * Total number of rows that would have been returned in a non paged query
	 *
	 * @var int
	 */
	private $totalRows = -1;
	
	/**
	 * Total pages in the last paged query
	 *
	 * @var int
	 */
	private $totalPages = -1;
	
	/**
	 * Page size used in the last paged query
	 *
	 * @var int
	 */
	private $pageSize = 30;
	
	/**
	 * Page number requested in the last paged query
	 *
	 * @var unknown_type
	 */
	private $pageNumber = -1;
	/**
	 * constructor
	 * 
	 * @param string $namespace The name of the entity
	 */
	public function __construct($entityClassName)
	{
		$this->tmpQuery = new DaoQuery($entityClassName);
		$this->resetQuery();
	}
	
	/**
	 * Return the internal DaoQuery instance
	 *
	 * @return DaoQuery
	 */
	public function getQuery()
	{
		return $this->query;
	}
	
	/**
	 * Replace the internal DaoQuery
	 *
	 * @param DaoQuery $query
	 */
	public function setQuery(DaoQuery $query)
	{
		$this->query = $query;
	}
	
	/**
	 * Start a database transaction
	 */
	public static function beginTransaction()
	{
		Dao::beginTransaction();
	}

	/**
	 * Commit a transaction
	 */
	public static function commitTransaction()
	{
		Dao::commitTransaction();
	}

	/**
	 * Rollback a transaction
	 */
	public static function rollbackTransaction()
	{
		Dao::rollbackTransaction();
	}
	
	/**
	 * Save an entity
	 *
	 * @param IHydraEntity $entity
	 */
	public function save(HydraEntity $entity)
	{
		if (is_array($messages = $entity->validateAll()))
		{
			throw new HydraEntityValidationException($messages);
		}
		
		if (is_null($entity->getId()))
		{
			$this->lastId = Dao::save($entity);
			$this->affectedRows = 1;
			$retVal = $this->lastId;
		}
		else
		{
			$this->affectedRows = Dao::save($entity);
			$this->lastId = -1;
			$retVal = $this->affectedRows;
		}
		
		$this->resetQuery();
	}

	/**
	 * Get a single instance of an entity by its database record id
	 *
	 * @param int $id
	 * @return IHydraEntity
	 */
	public function findById($id)
	{
		HydraEntity::$keepLogs = false;
		$results = Dao::findById($this->query, $id);
		$this->resetQuery();
		HydraEntity::$keepLogs = true;
		return $results;
	}

	/**
	 * Get a set of results that match a particular where clause
	 *
	 * @param string $criteria
	 * @param array[]mixed $params
 	 * @param array[] $orderByParamsArray[Entity.Field] = 'direction' 
	 * @return array(HydraEntity)
	 */
	public function findByCriteria($criteria, array $params=array(), $pageNumber=null, $pageSize=30, array $orderByParams=array())
	{
		HydraEntity::$keepLogs = false;
		if (!is_null($pageNumber))
		{
			$this->query->getPage($pageNumber, $pageSize);
		}
		
		$results = Dao::findByCriteria($this->query, $criteria, $params, $orderByParams);
		$this->resetQuery();
		HydraEntity::$keepLogs = true;
		
		return $results;
	}

	
	
	/**
	 * Deactivate records that match a particular where clause
	 *
	 * @param string $criteria
	 * @param array[]mixed $params
	 * @return int(Number of affected Records)
	 */
	public function deleteByCriteria($criteria, array $params=array())
	{
		HydraEntity::$keepLogs = false;
		$results = Dao::deleteByCriteria($this->query, $criteria, $params);
		$this->resetQuery();
		HydraEntity::$keepLogs = true;	
		return $results;
	}
	
	/**
	 * update records that match a particular where clause
	 *
	 * @param string       $setClause
	 * @param string       $criteria
	 * @param array[]mixed $params
	 * 
	 * @return int(Number of affected Records)
	 */
	public function updateByCriteria($setClause, $criteria, array $params=array())
	{
		HydraEntity::$keepLogs = false;
		$results = Dao::updateByCriteria($this->query, $setClause, $criteria, $params);
		$this->resetQuery();
		HydraEntity::$keepLogs = true;	
		return $results;
	}
	

	/**
	 * count records that match a particular where clause
	 *
	 * @param string $criteria
	 * @param array[]mixed $params
	 * @return int(Number of affected Records)
	 */
	public function countByCriteria($criteria, array $params=array())
	{
		HydraEntity::$keepLogs = false;
		$results = Dao::countByCriteria($this->query, $criteria, $params);
		$this->resetQuery();
		HydraEntity::$keepLogs = true;
		return $results;
	}
	
	
	
	/**
	 * Alias for GenericDAO::deactivate()
	 *
	 * @param HydraEntity $entity
	 */
	public function delete(HydraEntity $entity)
	{
		return $this->deactivate($entity);
	}
	
	/**
	 * Deactivate an entity instance
	 *
	 * @param HydraEntity $entity
	 */
	public function deactivate(HydraEntity $entity)
	{
		$this->affectedRows = Dao::deactivate($entity);
		$this->lastId = -1;
		$this->resetQuery();
		
		return $this->affectedRows;
	}
	
	/**
	 * Activate an entity instance
	 *
	 * @param HydraEntity $entity
	 */
	public function activate(HydraEntity $entity)
	{
		$this->affectedRows = Dao::activate($entity);
		$this->lastId = -1;
		$this->resetQuery();
		
		return $this->affectedRows;
	}
	
	/**
	 * Get a paged list of entities of a particular type
	 *
	 * @param int optional $pageNumber
	 * @param int optional $pageSize 
	 * @return array(HydraEntity)
	 */
	public function findAll($pageNumber=null, $pageSize=30)
	{
		HydraEntity::$keepLogs = false;
		if (!is_null($pageNumber))
		{
			$this->query->getPage($pageNumber, $pageSize);
		}
		
		$results = Dao::findAll($this->query);
		$this->resetQuery();
		HydraEntity::$keepLogs = true;
		return $results;
	}
	
	/**
	 * Get all entities matching the search query
	 *
	 * @param string $searchString Boolean search string (eg. +apple -banana)
	 * @param int optional $pageNumber
	 * @param int optional $pageSize 
	 * @return array(IHydraEntity)
	 */
	public function findBySearchString($searchString, $pageNumber=null, $pageSize=30)
	{
		HydraEntity::$keepLogs = false;
		
		if(is_null($pageNumber))
		{
			$pageNumber = 1;
			$pageSize = 100;
		}
		$this->query->getPage($pageNumber, $pageSize);
		
		$results = Dao::search($this->query, $searchString, null);
		
		$this->resetQuery();
		HydraEntity::$keepLogs = true;
		
		return $results;
	}

	/**
	 * Get total rows in a paginated query
	 *
	 * @return int
	 */
	public function getTotalRows()
	{
		return $this->totalRows;
	}
	
	/**
	 * Get the total number of pages available in the last paged query
	 *
	 * @return int
	 */
	public function getTotalPages()
	{
		return $this->totalPages;
	}
	
	/**
	 * Get the page number retrieved in the last paged query
	 *
	 * @return int
	 */
	public function getPageNumber()
	{
		return $this->pageNumber;
	}
	
	/**
	 * Get the page size used in the last paged query
	 *
	 * @return int
	 */
	public function getPageSize()
	{
		return $this->pageSize;
	}
	
	/**
	 * Last id inserted into this table
	 *
	 * @return int
	 */
	public function getLastId()
	{
		return $this->lastId;
	}

	/**
	 * Number of rows that were changed by the last query
	 *
	 * @return int
	 */
	public function getAffectedRows()
	{
		return $this->affectedRows;
	}

	/**
	 * Set the last id inserted value
	 *
	 * @param int id
	 * @return void
	 */
	public function setLastId($id)
	{
		$this->lastId = $id;
	}

	/**
	 * Set the number of rows that were changed by the last query
	 *
	 * @param int Number of rows
	 * @return void
	 */
	public function setAffectedRows($numRows)
	{
		$this->affectedRows = $numRows;
	}

	/**
	 * Add a join table record for many to many relationship
	 *
	 * @param HydraEntity $leftEntity
	 * @param HydraEntity $rightEntity
	 */
	public function saveManyToManyJoin(HydraEntity $leftEntity, HydraEntity $rightEntity)
	{
		// Check if the left and right entities are the correct way around
		$leftEntity->__loadDaoMap();
		$rightEntity->__loadDaoMap();
		
		$leftClass = get_class($leftEntity);
		$rightClass = get_class($rightEntity);
		
		$found = false;
		foreach (DaoMap::$map[strtolower($leftClass)] as $field => $properties)
		{
			if (isset($properties['rel']) and $properties['rel'] == DaoMap::MANY_TO_MANY)
			{
				if ($properties['class'] == $rightClass)
				{
					if ($properties['side'] == DaoMap::LEFT_SIDE)
					{
						// Swap them around the right way
						$tmp = $rightEntity;
						$rightEntity = $leftEntity;
						$leftEntity = $tmp;
						$leftClass = get_class($leftEntity);
						$rightClass = get_class($rightEntity);
					}
					
					$found = true;
				}
			}
		}
		
		if (!$found)
		{
			throw new HydraDaoException('Many-to-many relationship not found');
		}

		$sql = sprintf('insert into %s_%s (%sId, %sId, createdById) values (?, ?, ?)',
			strtolower($leftClass),
			strtolower($rightClass),
			strtolower(substr($leftClass, 0, 1)) . substr($leftClass, 1),
			strtolower(substr($rightClass, 0, 1)) . substr($rightClass, 1));
			
		if (Dao::execSql($sql, array($leftEntity->getId(), $rightEntity->getId(), Core::getUser()->getId())))
		{
			$this->affectedRows = 0;
		}
		else
		{
			$this->affectedRows = 1;
		}
		
		$this->resetQuery();
	}

	/**
	 * Remove a join table record for many to many relationship
	 * If either entity is null then remove that constraint / criteria (Dean 28/09/2012)
	 *
	 * @param HydraEntity $leftEntity
	 * @param HydraEntity $rightEntity
	 */
	public function deleteManyToManyJoin(HydraEntity $leftEntity, HydraEntity $rightEntity)
	{
		// Check if the left and right entities are the correct way around
		$leftEntity->__loadDaoMap();
		$rightEntity->__loadDaoMap();
		
		$leftClass = get_class($leftEntity);
		$rightClass = get_class($rightEntity);
		
		$found = false;
		foreach (DaoMap::$map[strtolower($leftClass)] as $field => $properties)
		{
			if (isset($properties['rel']) and $properties['rel'] == DaoMap::MANY_TO_MANY)
			{
				if ($properties['class'] == $rightClass)
				{
					if ($properties['side'] == DaoMap::LEFT_SIDE)
					{
						// Swap them around the right way
						$tmp = $rightEntity;
						$rightEntity = $leftEntity;
						$leftEntity = $tmp;
						$leftClass = get_class($leftEntity);
						$rightClass = get_class($rightEntity);
					}
					
					$found = true;
				}
			}
		}
		
		if (!$found)
		{
			throw new HydraDaoException('Many-to-many relationship not found');
		}

		
/*		
		$sql = sprintf('delete from %s_%s where %sId=? and %sId=?',
			strtolower($leftClass),
			strtolower($rightClass),
			strtolower(substr($leftClass, 0, 1)) . substr($leftClass, 1),
			strtolower(substr($rightClass, 0, 1)) . substr($rightClass, 1));
*/

		// Make query string conditional of entity id existing ( Dean 28/09/2012)
		
		$sql = sprintf('delete from %s_%s',strtolower($leftClass),strtolower($rightClass));
				

		if (is_null($leftEntity->getId()) == false && is_null($rightEntity->getId()) == false){
			$where = sprintf(' where %sId=? and %sId=?',				
					strtolower(substr($leftClass, 0, 1)) . substr($leftClass, 1),
					strtolower(substr($rightClass, 0, 1)) . substr($rightClass, 1));
			$params = array($leftEntity->getId(), $rightEntity->getId());

		}else{

			if (is_null($leftEntity->getId()) == false && is_null($rightEntity->getId()) == true){
				$where = sprintf(' where %sId=?',
						strtolower(substr($leftClass, 0, 1)) . substr($leftClass, 1));
				$params = array($leftEntity->getId());
				
			}elseif(is_null($leftEntity->getId()) == true && is_null($rightEntity->getId()) == false){
				$where = sprintf(' where %sId=?',				
						strtolower(substr($rightClass, 0, 1)) . substr($rightClass, 1));
				$params = array($rightEntity->getId());
			}else{
				throw new HydraDaoException('At least one hydra entity with a valid id is required');
			}
				
		}
		
		
		$sql =  $sql . $where;

		if (Dao::execSql($sql,$params))
		{
			$this->affectedRows = 0;
		}
		else
		{
			$this->affectedRows = 1;
		}
		
		$this->resetQuery();
	}


	/**
	 * finds if a join table record for many to many relationship exists
	 *
	 * @param HydraEntity $leftEntity
	 * @param HydraEntity $rightEntity
	 * @return bool
	 */
	public function existsManyToManyJoin(HydraEntity $leftEntity, HydraEntity $rightEntity)
	{
		// Check if the left and right entities are the correct way around
		$leftEntity->__loadDaoMap();
		$rightEntity->__loadDaoMap();
	
		$leftClass = get_class($leftEntity);
		$rightClass = get_class($rightEntity);
	
		$found = false;
		foreach (DaoMap::$map[strtolower($leftClass)] as $field => $properties)
		{
			if (isset($properties['rel']) and $properties['rel'] == DaoMap::MANY_TO_MANY)
			{
				if ($properties['class'] == $rightClass)
				{
					if ($properties['side'] == DaoMap::LEFT_SIDE)
					{
						// Swap them around the right way
						$tmp = $rightEntity;
						$rightEntity = $leftEntity;
						$leftEntity = $tmp;
						$leftClass = get_class($leftEntity);
						$rightClass = get_class($rightEntity);
					}
						
					$found = true;
				}
			}
		}
	
		if (!$found)
		{
			throw new HydraDaoException('Many-to-many relationship not found');
		}
		
		$sql = sprintf('select * from %s_%s',strtolower($leftClass),strtolower($rightClass));
				

		if (is_null($leftEntity->getId()) == false && is_null($rightEntity->getId()) == false){
			$where = sprintf(' where %sId=? and %sId=?',				
					strtolower(substr($leftClass, 0, 1)) . substr($leftClass, 1),
					strtolower(substr($rightClass, 0, 1)) . substr($rightClass, 1));
			$params = array($leftEntity->getId(), $rightEntity->getId());

		}else{

			if (is_null($leftEntity->getId()) == false && is_null($rightEntity->getId()) == true){
				$where = sprintf(' where %sId=?',
						strtolower(substr($leftClass, 0, 1)) . substr($leftClass, 1));
				$params = array($leftEntity->getId());
				
			}elseif(is_null($leftEntity->getId()) == true && is_null($rightEntity->getId()) == false){
				$where = sprintf(' where %sId=?',				
						strtolower(substr($rightClass, 0, 1)) . substr($rightClass, 1));
				$params = array($rightEntity->getId());
			}else{
				throw new HydraDaoException('At least one hydra entity with a valid id is required');
			}
				
		}
		
		
		$sql =  $sql . $where;

		
		$results = Dao::execSql($sql,$params);
		
		if ($results->rowCount() > 0){
			return true;
		}else{
			return false;	
		}
		
		$this->resetQuery();
	}
	
	/**
	 * Reset the internal DaoQuery back to its original state
	 */
	protected function resetQuery()
	{
		$this->query = clone $this->tmpQuery;
	}
}

?>