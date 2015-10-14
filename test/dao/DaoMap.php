<?php

/**
 * Class for generating meta data for use by a DaoQuery instance
 *
 * @package Core
 * @subpackage Dao
 */
class DaoMap
{
	// Used for one-to-one to detirmed where the matching id is
	const RELATION_OWNER = true;
	
	// Used for join tables
	const LEFT_SIDE = 1;
	const RIGHT_SIDE = 2;

	// Relationship types
	const ONE_TO_ONE = 1;
	const ONE_TO_MANY = 2;
	const MANY_TO_ONE = 3;
	const MANY_TO_MANY = 4;
	
	// Sorting
	const SORT_ASC = 'asc';
	const SORT_DESC = 'desc';
	
	private static $activeClassRaw = null;
	private static $activeClass = null;
	private static $tempMap = array();
	
	public static $map = array();

	/**
	 * Check if the dao map has been generated for an entity or class name
	 *
	 * @param HydraEntity|string $entityOrClass
	 * @return bool
	 */
	public static function hasMap($entityOrClass)
	{
		if (is_string($entityOrClass))
		{
			$entityOrClass = strtolower($entityOrClass);
			return isset(self::$map[$entityOrClass]);
		}
		
		if ($entityOrClass instanceof HydraEntity)
		{
			return isset(self::$map[strtolower(get_class($entityOrClass))]);
		}
		
		return false;
	}

	/**
	 * Load the internal Dao map for a given entity class
	 *
	 * @param string $class
	 */
	public static function loadMap($class)
	{
		if (!DaoMap::hasMap($class))
		{
		    if(empty($class))
		    {
		        throw new HydraDaoException('You can NOT create an object with empty classname!');
		    }
			$obj = new $class;
			$obj->__loadDaoMap();			
		}		
	}
	
	/**
	 * Start a DaoMap transaction
	 *
	 * @param HydraEntity $entity
	 */
	public static function begin(HydraEntity $entity, $alias=null)
	{
		self::$activeClassRaw = get_class($entity);
		self::$activeClass = strtolower(self::$activeClassRaw);
		self::$tempMap[self::$activeClass] = array();
		
		if (is_null($alias))
		{
			$alias = self::$activeClass;
		}
		
		self::$tempMap[self::$activeClass]['_']['alias'] = $alias;
		self::$tempMap[self::$activeClass]['_']['versioned'] = ($entity instanceof HydraVersionedEntity);
		self::$tempMap[self::$activeClass]['_']['sort'] = null;
	}
	
	/**
	 * Set the default sort order to apply when querying this entity
	 *
	 * @param string $field
	 * @param string $direction
	 */
	public static function defaultSortOrder($field, $direction=DaoMap::SORT_ASC)
	{
		if (strpos($field, '.') == false)
			$field = self::$activeClassRaw . '.' . $field;
			
		self::$tempMap[self::$activeClass]['_']['sort'] = array($field, $direction);
	}
	
	/**
	 * Register a one-to-many relationship
	 *
	 * @param string $field
	 * @param string $entityClass
	 * @param string $alias
	 */
	public static function setOneToMany($field, $entityClass, $alias=null, $defaultId=0)
	{
		if (is_null($alias))
		{
			$alias = $field;
		}
		
		self::$tempMap[self::$activeClass][$field] = array(
			'type' => 'int',
			'size' => 10,
			'unsigned' => true,
			'nullable' => false,
			'default' => $defaultId,
			'class' => $entityClass,
			'alias' => $alias,
			'rel' => self::ONE_TO_MANY
		);
	}
	
	/**
	 * Register a one-to-one relationship
	 *
	 * @param string $field
	 * @param string $entityClass
	 * @param bool $isOwner DaoMap::RELATION_OWNER if $this contains an id field mapping to the other end of the relationship
	 * @param string $alias
	 */
	public static function setOneToOne($field, $entityClass, $isOwner, $alias=null,$nullable=false,$defaultId=0)
	{
		if (is_null($alias))
		{
			$alias = $field;
		}
		
		self::$tempMap[self::$activeClass][$field] = array(
			'type' => 'int',
			'size' => 10,
			'unsigned' => true,
			'nullable' => ($isOwner) ? $nullable : false,
			'default' => $defaultId,
			'class' => $entityClass,
			'alias' => $alias,
			'owner' => $isOwner,
			'rel' => self::ONE_TO_ONE
		);
		
		if ($isOwner)
		{
			self::createIndex($field);
		}
	}
	
	/**
	 * Register a many-to-many relationship
	 *
	 * @param string $field
	 * @param string $entityClass
	 * @param int $side DaoMap::LEFT_SIDE | DaoMap::RIGHT_SIDE
	 * @param string $alias
	 */
	public static function setManyToMany($field, $entityClass, $side, $alias=null,$nullable=false,$defaultId=0)
	{
		if (is_null($alias))
		{
			$alias = $field;
		}
		
		self::$tempMap[self::$activeClass][$field] = array(
			'type' => 'int',
			'size' => 10,
			'unsigned' => true,
			'nullable' => $nullable,
			'default' => $defaultId,
			'class' => $entityClass,
			'alias' => $alias,
			'side' => $side,
			'rel' => self::MANY_TO_MANY
		);
	}
	
	/**
	 * Register a many-to-one relationship
	 *
	 * @param string $field
	 * @param string $entityClass
	 * @param string $alias
	 */
	public static function setManyToOne($field, $entityClass, $alias=null,$nullable=false,$defaultId=0)
	{
		if (is_null($alias))
		{
			$alias = $field;
		}
		
		self::$tempMap[self::$activeClass][$field] = array(
			'type' => 'int',
			'size' => 10,
			'unsigned' => true,
			'nullable' => $nullable,
			'default' => $defaultId,
			'class' => $entityClass,
			'alias' => $alias,
			'rel' => self::MANY_TO_ONE
		);
		
		self::createIndex($field);
	}
	
	/**
	 * Register a string type
	 *
	 * @param string $field
	 * @param string $dataType
	 * @param int $size
	 * @param bool $nullable
	 * @param string $defaultValue
	 */
	public static function setStringType($field, $dataType='varchar', $size=50, $nullable=false, $defaultValue='')
	{
		self::$tempMap[self::$activeClass][$field] = array(
			'type' => $dataType,
			'size' => $size,
			'nullable' => $nullable,
			'default' => $defaultValue
		);
	}

	/**
	 * Register an integer type
	 *
	 * @param string $field
	 * @param string $dataType
	 * @param int $size
	 * @param bool $unsigned
	 * @param bool $nullable
	 * @param string $defaultValue
	 */
	public static function setIntType($field, $dataType='int', $size=10, $unsigned=true, $nullable=false, $defaultValue=0, $class='')
	{
		self::$tempMap[self::$activeClass][$field] = array(
			'type' => $dataType,
			'size' => $size,
			'unsigned' => $unsigned,
			'nullable' => $nullable,
			'default' => $defaultValue
		);
	}
	
	/**
	 * Register a boolean type
	 *
	 * @param string $field
	 * @param string $dataType
	 * @param int $defaultValue
	 */
	public static function setBoolType($field, $dataType='bool', $defaultValue=0)
	{
		self::$tempMap[self::$activeClass][$field] = array(
			'type' => $dataType,
			'default' => $defaultValue
		);
	}

	/**
	 * Register a date type
	 *
	 * @param string $field
	 * @param string $dataType
	 * @param bool $nullable
	 * @param string $defaultValue
	 */
	public static function setDateType($field, $dataType='datetime', $nullable=false, $defaultValue='0001-01-01 00:00:00')
	{
		self::$tempMap[self::$activeClass][$field] = array(
			'type' => $dataType,
			'nullable' => $nullable,
			'default' => $defaultValue
		);
	}
	
	/**
	 * Register which properties on an entity are searchable. Takes multiple strings as parameter
	 * 
	 * @param string
	 */
	public static function setSearchFields()
	{
		self::$tempMap[self::$activeClass]['_']['search'] = func_get_args();
	}
	
	/**
	 * Set the base query that should be built off every time
	 *
	 * @param string $query
	 */
	public static function setBaseQuery($query)
	{
		self::$tempMap[self::$activeClass]['_']['base'] = $query;
	}
	
	/**
	 * Register which filters the entity will respond to
	 * 
	 * @param string
	 * @param string
	 */
	public static function createFilter($filterName, $filterClause)
	{
		self::$tempMap[self::$activeClass]['_']['filters'][$filterName] = $filterClause;
	}
	
	/**
	 * Create an index. Takes multiple strings as parameter
	 * 
	 * @param string
	 */
	public static function createIndex()
	{
		if (!isset(self::$tempMap[self::$activeClass]['_']['index']))
		{
			self::$tempMap[self::$activeClass]['_']['index'] = array();
		}
		
		self::$tempMap[self::$activeClass]['_']['index'][] = func_get_args();
	}
	
	/**
	 * Create a unique index. Takes multiple strings as parameter
	 * 
	 * @param string
	 */
	public static function createUniqueIndex()
	{
		if (!isset(self::$tempMap[self::$activeClass]['_']['unique']))
		{
			self::$tempMap[self::$activeClass]['_']['unique'] = array();
		}
		
		self::$tempMap[self::$activeClass]['_']['unique'][] = func_get_args();
	}
	
	/**
	 * Link a table to a tablespace
	 * 
	 * @param string
	 */
	public static function useTablespace($tablespace)
	{
		self::$tempMap[self::$activeClass]['_']['tablespace'] = $tablespace;
	}

	/**
	 * Specify which special case storage engine to use for this entity
	 * 
	 * @param string $engine eg. Federated
	 * @param string $confSection eg. LogDatabase
	 */
	public static function storageEngine($engine, $confSection)
	{
		self::$tempMap[self::$activeClass]['_']['engine'] = array($engine, $confSection);
	}
	
	/**
	 * Commit the data map to the internal hash table
	 */
	public static function commit()
	{
		// Set the manadatory properties of HydraEntity that get persisted
		if (is_subclass_of(self::$activeClassRaw, 'HydraVersionedEntity'))
		{
			self::setManyToOne('previousVersion', self::$activeClassRaw, self::$tempMap[self::$activeClass]['_']['alias'] . '_parent', true);
		}
		
		self::setBoolType('active', 'bool', 1);
		self::setDateType('created');
		self::setManyToOne('createdBy', 'UserAccount');
		self::setDateType('updated', 'timestamp', false, 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
		self::setManyToOne('updatedBy', 'UserAccount');
		
		// Copy the temp data into the live properties
		self::$map[self::$activeClass] = self::$tempMap[self::$activeClass];
		
		// Reset the temp variables
		self::$tempMap = array();
		self::$activeClass = null;
	}
	
	/**
	 * Check if the relationship is bi-directional
	 */
	public static function isBidirectional($focus, $child)
	{
		if ($focus == $child)
			return false;
			
		self::loadMap($focus);
		self::loadMap($child);
		
		$result = false;
		foreach (self::$map[strtolower($child)] as $field => $p)
		{
			if (isset($p['class']) && $p['class'] == $focus)
			{
				$result = true;
				break;
			}
		}
		return $result;
	}	
}

?>