<?php

/**
 * Class for executing DaoQuery objects and managing database connections
 *
 * @package Core
 * @subpackage Dao
 */
class Dao
{
	const AS_OBJECTS = 1;
	const AS_ARRAY = 2;
	const AS_XML = 3;

	const DB_MAIN_SERVER = 'LoadBalancer';			//point to where in .conf file
	const DB_REPORT_SERVER = 'ReportServer';		//point to where in .conf file
	const DB_MAIN_SERVER_INFO = 'Server';			//for conf file info
	const DB_REPORT_SERVER_INFO = 'ReportServer';	//for conf file info

	public static $connectTo = self::DB_MAIN_SERVER; //specify current/next connection

	private static $dsn = '';
	private static $user = '';
	private static $pass = '';

	private static $totalPages = 0;
	private static $totalRows = 0;
	private static $pageSize = 30;
	private static $pageNumber = 1;

	public static $Debug = false;
	public static $OutputFormat = Dao::AS_OBJECTS;
	public static $LazyLoadingEnabled = true;
	public static $LazyLoadInProgress = false;
	public static $AutoActiveEnabled = true;

	public static $lastInsertId=null;

	private static $_tempTableArray = null;

	public static $profilerError = false;

	//master switch for connecting a different db server
	public static $prepareNewConnectionEnabled = true;

	/**
	 * @var DaoFilterSet
	 */
	private static $filterSet = null;

	/**
	 * @var PDO
	 */
	private static $db = null;

	private static $dao_query_time = 0; // cumulative time spent doing queries

	/**
	 * Sets the global filters to apply to each query against the Dao
	 *
	 * @param DaoFilterSet $filters
	 */
	public static function setFilterSet(DaoFilterSet $filterSet)
	{
		self::$filterSet = $filterSet;
	}

	/**
	 * Global filters to apply to each query against the Dao
	 *
	 * @return DaoFilterSet
	 */
	public static function getFilterSet()
	{
		return self::$filterSet;
	}

	/**
	 * Get page size of the last paged query
	 *
	 * @return int
	 */
	public static function getPageSize()
	{
		return self::$pageSize;
	}

	/**
	 * Get total rows returned if the last query was NOT paged
	 *
	 * @return int
	 */
	public static function getTotalRows()
	{
		return self::$totalRows;
	}

	/**
	 * Get total pages returned if the last query was NOT paged
	 *
	 * @return int
	 */
	public static function getTotalPages()
	{
		return self::$totalPages;
	}

	/**
	 * Get page number of the last paged query
	 *
	 * @return int
	 */
	public static function getPageNumber()
	{
		return self::$pageNumber;
	}

	/**
	 * Set an object property via its setter or public property
	 *
	 * @param HydraEntity $entity
	 * @param string $field
	 * @param mixed $value
	 */
	public static function setProperty(HydraEntity &$entity, $field, &$value)
	{
		$method = 'set' . ucwords($field);

		if (method_exists($entity, $method))
		{
			$entity->$method($value);
			return;
		}

		$property = strtolower(substr($field, 0, 1)) . substr($field, 1);
		$entity->$property = &$value;
		return;
	}

	/**
	 * Get an object property via its getter or public property
	 *
	 * @param HydraEntity $entity
	 * @param string $field
	 * @return mixed
	 */
	public static function getProperty(HydraEntity &$entity, $field)
	{
		$method = 'get' . ucwords($field);
		if (method_exists($entity, $method))
		{
			return $entity->$method();
		}
		$property = strtolower(substr($field, 0, 1)) . substr($field, 1);
		return $entity->$property;
	}

	/**
	 * Connect to the database
	 */
	public static function connect()
	{
	// Only connect if we don't have a handle on the database
		if (!is_null(self::$db))
		{
			return;
		}

		try
		{
			// DSN FORMAT: "mysql:host=localhost;dbname=test"
			$driver = Config::get('Database', 'Driver');
			$host = Config::get('Database', self::$connectTo);
			$schema = Config::get('Database', 'CoreDatabase');

			self::$dsn = $driver . ':host=' . $host . ';dbname=' . $schema;

			if (self::$connectTo == self::DB_MAIN_SERVER)
			{
				self::$user = Config::get('Database','Username');
				self::$pass = Config::get('Database','Password');
			}
			else if (self::$connectTo == self::DB_REPORT_SERVER)
			{
				self::$user = Config::get('Database', self::DB_REPORT_SERVER . 'Username');
				self::$pass = Config::get('Database', self::DB_REPORT_SERVER . 'Password');
			}
			// else others here if need be

			self::$db = new PDO(self::$dsn, self::$user, self::$pass);
			self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch (PDOException $e)
		{
			throw new HydraDaoException ("Error (Dao::connect): " . $e->getMessage());
		}

		//set this here for the query comment, only once per script execution
		date_default_timezone_set('UTC');
	}

	/**
	 * Prepares and connects to a different server
	 * @param string $connectToNext			- the server to connect to (points to the .conf file)
	 * @param bool $definiteDisconnect		- whether we will re-connect regardless of the Admin Config option
	 */
	public static function prepareNewConnection($connectToNext = self::DB_MAIN_SERVER, $definiteDisconnect = false)
	{
		//check if we have the 'master' switch enabled
		if (self::$prepareNewConnectionEnabled === false)
			return;

		//return if we're trying to connect to the same as we're already connected
		if (self::$connectTo == $connectToNext)
			return;

		$disconnect = $definiteDisconnect;
		if (!$definiteDisconnect)
		{
			//check if the Admin Config option is set
			if ((bool)Config::getAdminConf('ReportRedirection', 'Enable') === true)
	    		$disconnect = true;
		}

		if ($disconnect)
		{
			self::$db = null; 					//disconnect from current connection
			self::$connectTo = $connectToNext; 	//set which server
			self::connect();					//connect
		}
	}

	/**
	 * Return server info depending on db connection
	 *
	 * @return string
	 */
	public static function getServerInfo()
	{
		if (self::$connectTo == self::DB_MAIN_SERVER) //pointing to main server
			return self::DB_MAIN_SERVER_INFO;
		else if (self::$connectTo == self::DB_REPORT_SERVER) //pointing to report server
			return self::DB_REPORT_SERVER_INFO;
	}

	/**
	 * Start a transaction
	 *
	 * @return bool
	 */
	public static function beginTransaction()
	{
		self::connect();
		return self::$db->beginTransaction();
	}

	/**
	 * Commit a transaction
	 *
	 * @return bool
	 */
	public static function commitTransaction()
	{
		self::connect();
		return self::$db->commit();
	}

	/**
	 * Rollback a transaction
	 *
	 * @return bool
	 */
	public static function rollbackTransaction()
	{
		self::connect();
		return self::$db->rollBack();
	}

	/**
	 * Convert an array into a set of objects defined by a DaoQuery instance
	 *
	 * @param DaoQuery $qry
	 * @param array $row
	 * @return HydraEntity
	 */
	public static function &objectify(DaoQuery $qry, array $row)
	{
		static $recurse = false;

		// Populate the focus object
		$fClass = $qry->getFocusClass();
		$focus = new $fClass;

		// We have to rebuild the objects in the order they appear in the sql results
		$i = 0;
		self::populateObject($qry, $focus, $fClass, $i, $row);

		foreach ($qry->getJoinClasses() as $joinClass)
		{
			list($joinClass, $joinField) = explode(':', $joinClass);

			// Check if we eager load this from the result set or from a sub query
			$resultsInArray = true;
			foreach (DaoMap::$map[strtolower($fClass)] as $field => $properties)
			{
				if ($field == '_')
				{
					continue;
				}

				if (isset($properties['rel']) && in_array($properties['rel'], array(DaoMap::MANY_TO_MANY, DaoMap::ONE_TO_MANY)))
				{
					if (isset($properties['class']) && $properties['class'] == $joinClass)
					{
						if (!$recurse)
						{
							$recurse = true;

							$q = new DaoQuery($properties['class']);

							if ($properties['rel'] == DaoMap::ONE_TO_MANY)
							{
								$criteria = sprintf('%sId = ?', strtolower(substr($fClass, 0, 1)) . substr($fClass, 1));
							}
							else
							{
								$prop = strtolower(substr($fClass, 0, 1)) . substr($fClass, 1) . 's';
								$otherProp = strtolower(substr($fClass, 0, 1)) . substr($fClass, 1);
								foreach(DaoMap::$map[strtolower($joinClass)] as $jcf => $jcp)
								{
									if(isset($jcp['rel']) && $jcp['rel'] == DaoMap::MANY_TO_MANY && $jcp['class'] == $fClass)
									{
										$prop = $jcf;
										break;
									}
								}
								$q->eagerLoad($joinClass . '.' . $prop);

								// JOIN TABLE ISSUE: MISSING JOIN TABLE NAME ALIAS FOR ID OF FIRST PARAM. $sId
								$criteria = sprintf('%sId = %s.id',
									$otherProp,
									DaoMap::$map[strtolower($joinClass)]['_']['alias']);
							}

							$r = self::findByCriteria($q, $criteria, array($row[0]));
							self::setProperty($focus, $field, $r);
						}

						$resultsInArray = false;
					}
				}
			}

			if ($resultsInArray)
			{
				// NOTE: From Tim, this may be required
				//$recurse = false;
				self::populateObject($qry, $focus, $joinClass, $i, $row, $joinField);
			}
		}

		// NOTE: From Tim, this may be required
		//$recurse = false;
		return $focus;
	}

	/**
	 * Load an instance of $class as a child of the $focus object with data beginning from $i offset in the $row array
	 *
	 * @param DaoQuery $qry
	 * @param HydraEntity $focus
	 * @param string $class
	 * @param int $i
	 * @param array $row
	 */
	private static function populateObject(DaoQuery &$qry, HydraEntity &$focus, $class, &$i, $row, $joinField=null)
	{
		if (get_class($focus) == $class)
		{
			$focus->setId($row[$i]);
			$i++;
		}

		foreach (DaoMap::$map[strtolower($class)] as $field => $properties)
		{
			if ($field == '_')
			{
				continue;
			}

			if (isset($properties['rel']))
			{
				if ($properties['rel'] == DaoMap::ONE_TO_ONE && !$properties['owner'])
				{
					continue;
				}

				if ($properties['rel'] == DaoMap::MANY_TO_ONE || ($properties['rel'] == DaoMap::ONE_TO_ONE))
				{
					$id = $row[$i];
					$cls = DaoMap::$map[strtolower($class)][$field]['class'];
					$value = new $cls;
					$value->setId($id);

					// Check if we are eager loading this object
					if (!in_array($properties['class'] . ':' . $field , $qry->getJoinClasses()))
					{
						// Otherwise create the child object as a proxy object
						$value->setProxyMode(true);
					}
				}
				else
				{
					continue;
				}
			}
			else
			{
				$value = $row[$i];
			}

			// Figure out which object we are working on
			if (get_class($focus) == $class)
			{
				// Add the value via the setter if it exists, otherwise assume there is a public property we can use
				self::setProperty($focus, $field, $value);
			}
			else
			{
				// Add the value via the setter if it exists, otherwise assume there is a public property we can use
				self::$LazyLoadingEnabled = false;
				$child = self::getProperty($focus, $joinField);
				self::setProperty($child, $field, $value);
				self::setProperty($focus, $joinField, $child);
				self::$LazyLoadingEnabled = true;
			}

			$i++;
		}
	}

	/**
	 * Internal function to calculate the paging stats for a paged select
	 *
	 * @param DaoQuery $qry
	 */
	private static function calculatePageStats(DaoQuery $qry, $results)
	{
		if ($qry->isPaged())
		{
			$sql = 'select found_rows()';
			$stmt = self::$db->prepare($sql);

			if (self::$Debug)
			{
				var_dump($sql);
				echo "<br />";
			}

			if (!$stmt->execute())
			{
				return;
			}

			$my = $stmt->fetch(PDO::FETCH_NUM);
			self::$totalRows = $my[0];

			list(self::$pageNumber, self::$pageSize) = $qry->getPageStats();

			self::$totalPages = ceil(self::$totalRows / self::$pageSize);
		}
		else
		{
			if (!is_array($results))
			{
				self::$pageNumber = 0;
				self::$totalRows = 0;
				self::$totalPages = 0;
			}
			else
			{
				self::$pageNumber = 1;
				self::$totalPages = 1;
				self::$totalRows = count($results);
				self::$pageSize = self::$totalRows;
			}
		}
	}

	/**
	 * Find all objects within a DaoQuery
	 *
	 * @param DaoQuery $qry
	 * @param int optional $outputFormat
	 * @return array[]HydraEntity
	 */
	public static function findAll(DaoQuery $qry, $outputFormat=null)
	{
		$tmpOutputFormat = self::$OutputFormat;

		if (!is_null($outputFormat))
		{
			self::$OutputFormat = $outputFormat;
		}

		self::connect();
		$sql = $qry->generateForSelect();

		$results = self::getResults($qry, $sql);

		self::$OutputFormat = $tmpOutputFormat;
		return $results;
	}

	/**
	 * Retrieve an entity from the database by its primary key
	 *
	 * @param DaoQuery $qry
	 * @param int $id
	 * @param int optional $outputFormat
	 * @return HydraEntity
	 */
	public static function findById(DaoQuery $qry, $id, $outputFormat=null)
	{
		//code added by Lin He on 1/3/2011: we noticed there are query to get warehouses with id=null.
		//this is to fix that.
		if(trim($id)=="") return null;

		$tmpOutputFormat = self::$OutputFormat;

		if (!is_null($outputFormat))
		{
			self::$OutputFormat = $outputFormat;
		}

		//set to distinct as query is returning many duplicate rows when used in cross table
		$qry->distinct(true);

		self::connect();
		DaoMap::loadMap($qry->getFocusClass());

		$oldAutoActive = self::$AutoActiveEnabled;
		self::$AutoActiveEnabled = false;
		$results = self::findByCriteria($qry, '`' . DaoMap::$map[strtolower($qry->getFocusClass())]['_']['alias'] . '`.`id`=?', array($id));
		self::$AutoActiveEnabled = $oldAutoActive;

		if (self::$OutputFormat == self::AS_XML)
		{
			$results = explode("\n", $results);
			unset($results[3]);
			unset($results[1]);
			$results = implode("\n", $results);
			self::$OutputFormat = $tmpOutputFormat;
			return $results;
		}

		self::$OutputFormat = $tmpOutputFormat;

		if (is_array($results) && sizeof($results) > 0)
		{
			return $results[0];
		}

		return null;
	}

	/**
	 * Retrieve an entity from the database with a modified where clause
	 *
	 * @param DaoQuery $qry
	 * @param int $id
	 * @param array[] $orderByParamsArray
	 * @param int optional $outputFormat
	 * @return HydraEntity
	 */
	public static function findByCriteria(DaoQuery $qry, $criteria, $params, array $orderByParams=array(), $outputFormat=null)
	{
		$tmpOutputFormat = self::$OutputFormat;

		if (!is_null($outputFormat))
		{
			self::$OutputFormat = $outputFormat;
		}

		self::connect();
		$qry = $qry->where($criteria);

		foreach ($orderByParams as $field => $direction)
			$qry = $qry->orderBy($field,$direction);

		$sql = $qry->generateForSelect();

		$results = self::getResults($qry, $sql, $params);

		self::$OutputFormat = $tmpOutputFormat;
		return $results;
	}



	/**
	 * Deactivate entities in the database with a modified where clause
	 *
	 * @param DaoQuery $qry
	 * @param int $id
	 * @param string  $criteria
	 * @return int (number of records affected)
	 */
	public static function deleteByCriteria(DaoQuery $qry, $criteria, $params)
	{
		return self::updateByCriteria($qry, 'active = 0', $criteria, $params);
	}

	/**
	 * Count entities in the database with a modified where clause
	 *
	 * @param DaoQuery $qry
	 * @param int $id
	 * @param string  $criteria
	 * @return int (number of records returned)
	 */
	public static function countByCriteria(DaoQuery $qry, $criteria, $params)
	{
		self::connect();

		$qry = $qry->where($criteria);

		$sql = $qry->generateForCount();

		$results = self:: getSingleResultNative($sql, $params);

		return $results[0];
	}

	/**
	 * update entities in the database with a modified where clause
	 *
	 * @param DaoQuery $qry
	 * @param string   $criteria
	 * @param array    $params
	 *
	 * @return int (number of records affacted)
	 */
	public static function updateByCriteria(DaoQuery $qry, $setClause, $criteria, $params)
	{
	    self::connect();
	    $qry = $qry->where($criteria);
	    $sql = $qry->generateForBulkUpdate($setClause);
	    $results = self::execSql($sql, $params);
	    return $results->rowCount();
	}

	/**
	 * Get either LIKE or RLIKE operator depending on the type of search string provided (will also modify searchString which is passed by reference)
	 * @param string $searchString
	 * @return string
	 */
	public static function getCorrectQueryOperator(&$searchString, $exactMatch=false)
	{
		// We need to delimit on '|' but the we may have passed searchString in using ','
		$searchString = explode(",",$searchString);

		// Remove any unwanted or unsafe chars
		$searchString = Dao::prepareSearchString(implode("|",$searchString));

		// If searchString is "|" delimited then we should do an RLIKE query
		// otherwise, just do a LIKE and add '%' wildcards to the search string
		if($exactMatch)
		{
			$operator = "=";
		}
		elseif(count(explode("|",$searchString)) > 1)
		{
			$operator = 'RLIKE';
		}
		else
		{
			$operator = 'LIKE';
			$searchString = "%" . $searchString . "%";
		}
		return $operator;
	}

	/**
	 * Prepare a string of search terms ready for inclusion in a Dao::search
	 *
	 * @param string $searchString
	 * @return string
	 */
	public static function prepareSearchString($searchString)
	{
		// Break into search boundaries
		$terms = explode('|', $searchString);

		for ($i=0; $i<count($terms); $i++)
		{
			$firstChar = substr($terms[$i], 0, 1);
			$lastChar = substr($terms[$i], -1);

			// Escape regexp special characters (order is very important)
			$terms[$i] = str_replace(
				array('\\', '.', '+', '?', '[', '^', ']', '$', '(', ')', '{', '}', '=', '!', '<', '>', ':'),
				array('\\\\', '\.', '\+', '\?', '\[', '\^', '\]', '\$', '\(', '\)', '\{', '\}', '\=', '\!', '\<', '\>', '\:'),
				$terms[$i]);

			// Wildcards
			$terms[$i] = str_replace('*', '[a-zA-Z0-9\-]*', $terms[$i]);
			$terms[$i] = str_replace('%', '[a-zA-Z0-9\-]*', $terms[$i]);

			if (($firstChar != '*') && ($lastChar != '*'))
				continue;

			// We use a * on the end of the regexp because we might already be at the edge of the word
			if ($firstChar != '*')
				$terms[$i] = '(^|[^a-zA-Z0-9\-]+)' . $terms[$i];

			// We use a * on the end of the regexp because we might already be at the edge of the word
			if ($lastChar != '*')
				$terms[$i] .= '([^a-zA-Z0-9\-]+|$)';
		}

		// Combine the terms back into a single string
		$searchString = implode('|', $terms);

		// Just in case someone tries to use & to represent the word "and"
		$searchString = str_replace('&', '(&|and)', $searchString);
		$searchString = str_replace(' and ', ' (&|and) ', $searchString);

		return $searchString;
	}

	/**
	 * Search for entities matching a search string
	 *
	 * @param DaoQuery $qry
	 * @param string $searchString
	 * @param int optional $outputFormat
	 * @return array[]HydraEntity
	 */
	public static function search(DaoQuery $qry, $searchString, $outputFormat=null)
	{
		if (!isset(DaoMap::$map[strtolower($qry->getFocusClass())]['_']['search']))
		{
			throw new HydraDaoException($qry->getFocusClass() . '::__loadDaoMap requires DaoMap::setSearchFields() to enable searching for this entity');
		}

		$tmpOutputFormat = self::$OutputFormat;

		if (!is_null($outputFormat))
		{
			self::$OutputFormat = $outputFormat;
		}

		self::connect();


		// Load the query
		DaoMap::loadMap($qry->getFocusClass());

		$where = '';

		$searchSQLFields = array();
		foreach (DaoMap::$map[strtolower($qry->getFocusClass())]['_']['search'] as $field)
		{
			// Separate the field into its class and property names
			$tmp = explode('.', $field);

			if (count($tmp) == 1)
			{
				$property = $tmp[0];
				$child = null;
			}
			else
			{
				$property = $tmp[0];
				$child = $tmp[1];
			}

			$fTable = strtolower($qry->getFocusClass());
			if (!isset(DaoMap::$map[$fTable][$property]['class']))
			{
				// This is a field on the focus table
				$table = DaoMap::$map[$fTable]['_']['alias'];
			}
			else
			{
				// Ensure the relationship is eager loaded before we add it to the search terms
				$qry = $qry->eagerLoad($qry->getFocusClass() . '.' . $property, 'left');

				$table = strtolower(DaoMap::$map[$fTable][$property]['alias']);
				$field = $child;
			}

			$searchSQLFields[] = '`' . $table . '`.`' . $field . '`';
		}

		$operator = self::getCorrectQueryOperator($searchString);

		if (count($searchSQLFields) > 0)
			$qry->where("(concat_ws(' ', " . implode(',', $searchSQLFields) . ") " . $operator . " :search)");

		$qry->DefaultJoinType = 'left join';
		$sql = $qry->generateForSelect();
		$sql = str_replace('select ', 'select distinct ', $sql);
		$qry->DefaultJoinType = 'inner join';

		if (self::$Debug)
		{
			var_dump($sql);
			echo "<br />";
		}

		$stmt = self::$db->prepare($sql);
		//$stmt->bindValue(':search', $searchString, PDO::PARAM_STR);

		try
		{
			if (!self::execStatement($stmt, array('search'=>$searchString)))
			{
				return null;
			}
		}
		catch (PDOException $ex)
		{
			if (self::$Debug)
			{
				self::drawFancyError($sql, $params);
			}

			throw $ex;
		}

		try
		{
			$results = array();
			while ($my = $stmt->fetch(PDO::FETCH_NUM))
			{
				$result = self::objectify($qry, $my);

				switch (self::$OutputFormat)
				{
					case self::AS_XML:
						$result = self::formatAsXml($result);
						break;

					case self::AS_ARRAY:
						$result = self::formatAsArray($result);
						break;

					default:
						break;
				}

				$results[] = $result;
			}
		}
		catch(Exception $e)
		{
			if (self::$profilerError === false) 	//throw the error if it is not coming from the profiler
				throw $e;

			self::$profilerError = false;			//reset to false to avoid missing other exceptions
		}

		self::calculatePageStats($qry, $results);

		self::$OutputFormat = $tmpOutputFormat;
		return $results;
	}

	/**
	 * Save an entity into the database
	 *
	 * @param HydraEntity $entity
	 * @return bool
	 */
	public static function save(HydraEntity $entity)
	{
		$entity->preSave();

		$logs = $entity->collectLogs();
		if(sizeof($logs) > 0)
		{
			foreach($logs as $log)
			{
				$behalfOfUserAccountId = null;
				$behalfOfRoleId = null;
				if (count($log) > 8)
					list($tentityId,$tentity,$field,$newValue,$oldValue,$comment,$createdSiteTime,$fieldEntity,$behalfOfUserAccount,$behalfOfRole) = $log;
				else
					list($tentityId,$tentity,$field,$newValue,$oldValue,$comment,$createdSiteTime,$fieldEntity) = $log;
				Logging::LogEntityChange($tentityId,$tentity,$field,$newValue,$oldValue,$comment,$createdSiteTime,$fieldEntity,$behalfOfUserAccount,$behalfOfRole);
			}

			if (method_exists($entity, 'onLog'))
				$entity->onLog($logs);
		}

		if (is_null($entity->getId()))
		{
			// Insert a new record
			$id = self::insert($entity);
			$entity->postSave($id,true);
			return $id;

		}
		else
		{
			// Check if the entity needs to be versioned or not
			if ($entity instanceof HydraVersionedEntity)
			{
				$oldVersionId = $entity->getId();

				// The entity that we are saving becomes the new child
				$c = get_class($entity);
				$parent = new $c();
				$parent->setProxyMode(true);
				$parent->setId($oldVersionId);

				$entity->setId(null);
				$entity->setPreviousVersion($parent);

				// Save the new versioned entity
				$deactivate = !$entity->getActive();
				$returnValue = self::insert($entity);

				// Fixed bug for updated time, as it was taking local sql server time instead of utc
				$nowUTC = new HydraDate();

				//bug fixing for the site versioning when deactivating a site
				//updated by Lin He on 14/10/2010, as we found out the updatedById is not updated when versioning a site.
				//to prevent session issues or other catches, if we can't get the current user, system will default updatedById to 1
				$currentUserId = ((($currentUser = Core::getUser()) instanceof UserAccount) ? $currentUser->getId() : 1);

				/// De-Activating the OLD entry as a new version has been created ///
				Dao::updateByCriteria(new DaoQuery(get_class($entity)), 'active = ?, updated = ?, updatedById = ?', 'id = ?', array(0, $nowUTC->__toString(), $currentUserId, $oldVersionId));

				if($deactivate) /// This means we actually want to de-activate the newly created version ///
    				Dao::updateByCriteria(new DaoQuery(get_class($entity)), 'active = ?, updated = ?, updatedById = ?', 'id = ?', array(0, $nowUTC->__toString(), $currentUserId, $entity->getId()));

				$entity->postSave($returnValue,false);

				// Check all related entities to see if we need to version them too
				self::versionAffectedChildren($entity, $parent);

				return $returnValue;
			}
			else
			{
				// Update an existing record
				$id = self::update($entity);
				$entity->postSave($id,false);
				return $id;
			}
		}
	}

	private static function versionAffectedChildren(HydraEntity $entity, HydraEntity $parent)
	{
		$map = DaoMap::$map[strtolower(get_class($entity))];

		foreach ($map as $field => $p)
		{
			if ($field == '_')
			{
				continue;
			}

			// If there is a many-to-many relationship attached to this entity, then we need to version that join data regardless
			if (isset($p['rel']) && $p['rel'] == DaoMap::MANY_TO_MANY)
			{
				// Join in the many to many join table
				if ($p['side'] == DaoMap::RIGHT_SIDE)
				{
					$mtmJoinTable = strtolower(get_class($entity) . '_' . $p['class']);
				}
				else
				{
					$mtmJoinTable = strtolower($p['class'] . '_' . get_class($entity));
				}

				$targetField = strtolower(substr($p['class'], 0, 1)) . substr($p['class'], 1);
				$sourceField = strtolower(substr(get_class($entity), 0, 1)) . substr(get_class($entity), 1);

				$sql = sprintf('select %sId from %s where %sId=%d',
					$targetField,
					$mtmJoinTable,
					$sourceField,
					$parent->getId());

				$stmt = self::execSql($sql);

				try
				{
					while ($my = $stmt->fetch(PDO::FETCH_NUM))
					{
						$sql = sprintf('insert into %s (%sId, %sId, createdById) values (%d, %d, %d)',
							$mtmJoinTable,
							$sourceField,
							$targetField,
							$entity->getId(),
							$my[0],
							Core::getUser()->getId());

						self::execSql($sql);
					}
				}
				catch(Exception $e)
				{
					if (self::$profilerError === false) 	//throw the error if it is not coming from the profiler
						throw $e;

					self::$profilerError = false;			//reset to false to avoid missing other exceptions
				}
			}
		}
	}

	/**
	 * Insert an entity into the database
	 *
	 * @param HydraEntity $entity
	 * @return bool|int
	 */
	public static function insert(HydraEntity &$entity)
	{
		self::connect();

		if (!is_null($entity->getId()))
		{
			throw new HydraDaoException('Entity has already been persisted');
		}
		// Add versioning data to entity
		if(!Core::getImpersonationStatus())
		{
			$entity->setCreatedBy(Core::getUser());
			$entity->setUpdatedBy(Core::getUser());
		}
		else
		{

			$entity->setCreatedBy(Core::getCurrentUserAccount());
			$entity->setUpdatedBy(Core::getCurrentUserAccount());
		}
		$entity->setActive(1);
		$entity->setCreated(new HydraDate());
		$entity->setUpdated(new HydraDate());

		$qry = new DaoQuery(get_class($entity));
		$sql = $qry->generateForInsert();

		if (self::$Debug)
		{
			var_dump($sql);
			echo "<br />";
		}
		$stmt = self::$db->prepare($sql);
		$data = array();
		foreach (DaoMap::$map[strtolower($qry->getFocusClass())] as $field => $properties)
		{
			if ($field == '_')
			{
				continue;
			}

			if (isset($properties['rel']))
			{
				if (($properties['rel'] == DaoMap::MANY_TO_ONE) || ($properties['rel'] == DaoMap::ONE_TO_ONE && $properties['owner']))
				{
					$child = self::getProperty($entity, $field);

					if($child != null)
					{
						$data[] = $child->getId();
					}
					else
					{
						$data[] = null;
					}
				}
				else
				{
					continue;
				}
			}
			else
			{
				$d = self::getProperty($entity, $field);

				if ($properties['type'] == 'bool')
				{
					$d = intval($d);
				}

				$data[] = $d;
			}
		}

		try
		{
			if ($id = self::execStatement($stmt, $data))
			{
				// Grab the last insert id for this record and attach it to the entity
//				$id = self::$db->lastInsertId();
				$entity->setId($id);

				if (method_exists($entity, 'onInsert'))
					$entity->onInsert();

				return $id;
			}
		}
		catch (PDOException $ex)
		{
			if (self::$Debug)
			{
				self::drawFancyError($sql, $data);
			}

			throw $ex;
		}

		return false;
	}

	/**
	 * Update an entity in the database
	 *
	 * @param HydraEntity $entity
	 * @return bool
	 */
	public static function update(HydraEntity &$entity)
	{
		self::connect();

		if (is_null($entity->getId()))
		{
			throw new HydraDaoException('Entity has not yet been persisted');
		}

		// Add versioning data to entity
		// Fixed bug for updated time, as it was taking local sql server time instead of utc
		$entity->setUpdated(new HydraDate());

		if(!Core::getImpersonationStatus())
			$entity->setUpdatedBy(Core::getUser());
		else
			$entity->setUpdatedBy(Core::getCurrentUserAccount());

		$qry = new DaoQuery(get_class($entity));
		$sql = $qry->generateForUpdate();

		if (self::$Debug)
		{
			var_dump($sql);
			echo "<br />";
		}

		$stmt = self::$db->prepare($sql);
		$data = array();

		foreach (DaoMap::$map[strtolower($qry->getFocusClass())] as $field => $properties)
		{
			if ($field == '_')
			{
				continue;
			}

			if (isset($properties['rel']))
			{
				if ($properties['rel'] == DaoMap::MANY_TO_ONE or ($properties['rel'] == DaoMap::ONE_TO_ONE and $properties['owner']))
				{
					$child = self::getProperty($entity, $field);
					if($child != null)
						$data[] = $child->getId();
					else
						$data[] = null;
				}
				else
				{
					continue;
				}
			}
			else
			{
				$d = self::getProperty($entity, $field);

				if ($properties['type'] == 'bool')
				{
					$d = intval($d);
				}

				$data[] = $d;
			}
		}

		$data[] = $entity->getId();

		$retVal = self::execStatement($stmt, $data);

		return $retVal;
	}

	/**
	 * Delete an entity from the database
	 *
	 * @param HydraEntity $entity
	 * @return bool
	 */
	public static function delete(HydraEntity $entity)
	{
		return self::deactivate($entity);
	}

	public static function activate(HydraEntity $entity)
	{
		self::connect();

		if (is_null($entity->getId()))
		{
			throw new HydraDaoException('Entity has not yet been persisted');
		}

		// Fixed bug for updated time, as it was taking local sql server time instead of utc
		$nowUTC = new HydraDate();
		$sql = sprintf('update %s set active=1, updated=\'%s\', updatedById=? where id=?', strtolower(get_class($entity)), $nowUTC->__toString());

		if (self::$Debug)
		{
			var_dump($sql);
			echo "<br />";
		}

		$stmt = self::$db->prepare($sql);
		$retVal = self::execStatement($stmt, array(Core::getUser()->getId(), $entity->getId()));

		if ($retVal !== false)
		{
			$entity->setActive(1);
		}

		return $retVal;
	}

	public static function deactivate(HydraEntity $entity)
	{
		self::connect();

		if (is_null($entity->getId()))
		{
			throw new HydraDaoException('Entity has not yet been persisted');
		}

		// Fixed bug for updated time, as it was taking local sql server time instead of utc
		$nowUTC = new HydraDate();
		$sql = sprintf('update %s set active=0, updated=\'%s\', updatedById=? where id=?', strtolower(get_class($entity)), $nowUTC->__toString());

		if (self::$Debug)
		{
			var_dump($sql);
			echo "<br />";
		}

		$stmt = self::$db->prepare($sql);
		$retVal = self::execStatement($stmt, array(Core::getUser()->getId(), $entity->getId()));

		if ($retVal !== false)
		{
			$entity->setActive(0);
		}

		return $retVal;
	}



	/**
	 * Escapes all the quotes and return the escaped value
	 *
	 * @param string $value
	 * @param string $value
	 *
	 */
    public static function _cleanSql($value)
    {
        if(get_magic_quotes_gpc()){
            $value = stripslashes($value);
        } else {
            $value = addslashes($value);
        }
        return $value;
    }

    /**
     * Escapes ALL quotes in the array values and returns the escaped values in an array
     *
     * @param array $vals
     * @param string $vals
     * @return array $retVal
     */
    public static function fixSQLVals($vals = "")
    {
    	if(is_array($vals))
       	{
	    	$retVal = array();
	    	foreach($vals as $k => $v)
	    	{
	    		$retVal[$k] = self::_cleanSql($v);
	       	}
	    }
	    else
	    {
	    	$retVal = self::_cleanSql($vals);
	    }

    	return $retVal;

    }

	/**
	 * Run an SQL statement and return the PDOStatement object
	 *
	 * @param string $sql
	 * @param array $params
	 * @return PDOStatement
	 */
	public static function execSql($sql, array $params=array())
	{
		self::connect();

		if (self::$Debug)
		{
			var_dump($sql);
			echo "<br />";
		}

		$stmt = self::$db->prepare($sql . self::_getQueryComment()); //add the sql comment at the end for investigation in logs

		$retVal = self::execStatement($stmt, $params);
		if(is_numeric($retVal))
			self::$lastInsertId=$retVal;
		return $stmt;
	}

	/**
	 * Checks each query to see if we are connected to the report server.
	 * - If so it will verify that any WRITE operations are only to a temporary table.
	 * -- If it is NOT to a temporary table, it will switch the connection back to the main database, and generate an error email
	 *
	 * @param PDOStatement $stmt   The PDO statement that is to be run
	 * @param array        $params The query params that are to substitued into the statement
	 */
	private static function verifyQueryConnectionType($stmt, $params)
	{
		if (self::$connectTo == self::DB_REPORT_SERVER) 										//currently pointing at report server, so do the checking
		{
			$str = trim(substr($stmt->queryString, 0, strpos($stmt->queryString, '(')));  		//find the first '(' if exists
			$words = explode(" ", $str);														//get the individual words from the first part of the query
			if (stripos($str, 'CREATE TEMPORARY TABLE') !== false)								//looking for 'CREATE TEMPORARY TABLE'
			{
				self::$_tempTableArray[] = array_pop($words);									//put the table name 'xxx' into the array for later
				return;
			}
			if (in_array($words[0], array("INSERT", "UPDATE", "DELETE", "DROP", "TRUNCATE")))	//we are modifying the db somewhat
			{
				foreach (self::$_tempTableArray as $ttName)										//go through each temp table name to see if it is an action on that
				{
					if (strpos($str, $ttName) !== false) 										//is an action on a temp table, so return (allow it)
					{
						return;
					}
				}
                //we've come this far, so we are trying to WRITE to a non-temp table
				self::$db = null; 																//disconnect from report server connection
				self::$connectTo = self::DB_MAIN_SERVER; 										//set main server for next connect, so we write to the main db
				self::_notifyAdmin();
			}
		}
	}
	/**
	 * send an email so we are aware of this, in case redirection has to be turned off
	 */
	private static function _notifyAdmin()
	{
	    $to[] = Config::get('ErrorDebugHandling', 'Email');
	    $to[] = Config::get('SupportHandling', 'Email');
	    $to[] = 'lhe@bytecraft.com.au';
	    $to[] = 'rpatel@bytecraft.com.au';
	    $subject = "SLAVE WARNING - Report Server Attempted WRITE";
	    $body = "BSuite attempted to write the following query to the database (" . Config::get('Database', self::DB_REPORT_SERVER) . ") at " . new HydraDate() .
	    						"\nThe connection was RESET to connect to (" . Config::get('Database', self::DB_REPORT_SERVER) . ") before execution of the query" .
	    						"\n\n" . StringUtils::queryParamReplace($stmt->queryString, $params, '?') .
	    						"\n" . Debug::backtrace();
	    Factory::service('Message')->email($to, $subject, $body);
	}

	/**
	 * This creates a MySQL (#) comment containing time in UTC, userId and URL (exluding ? marks), if they exist, to end to of the executed SQL
	 * @return string
	 */
	private static function _getQueryComment()
	{
		return "\n#" . date_format(date_create('now'), "Y-m-d H:i:s") . ' ' . (($user = Core::getUser()) instanceof UserAccount ? $user->getId() : '') . ' ' . ((isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '?') === false) ? $_SERVER['REQUEST_URI'] : '/');
	}

	public static function execStatement(PDOStatement $stmt, $params=array())
	{
		$microtime_before = 0.0;
		$retVal = null;
		try
		{
			// Is this is NOT a profiler query, start profiling
			if (strpos($stmt->queryString, 'insert into ' . Profiler::getProfilerDatabase()) === false)
			{
				$isProfilerQuery = false;

				$profiler = new Profiler(Profiler::getSQLProfilerOnOff());
				$profiler->Sql = StringUtils::queryParamReplace($stmt->queryString, $params, '?');
				$profiler->start();

				self::verifyQueryConnectionType($stmt, $params); //checks if connected to ReportServer and is attempting to write to slave database

				$microtime_before = microtime(true);
			}
			else
			{
				$isProfilerQuery = true;
			}

// 			DEBUG: Uncomment this to display SQL
// 			Debug::inspect(StringUtils::queryParamReplace($stmt->queryString, $params, '?'));

			self::connect();
			$retVal = $stmt->execute($params);

			if (!$isProfilerQuery)
			{
				$timediff = microtime(true) - $microtime_before;
				self::blogSql($stmt, $params, array('sql_query_time' => $timediff));
				self::$dao_query_time += $timediff;
				BlogSession::getBlogProfiler()->setAuditParam('dao_query_time', self::$dao_query_time);
			}

			// If this is an insert statement, then we need to capture the last insert id, otherwise the profiler overrides it if activated
			if ($retVal && (strtoupper(substr($stmt->queryString, 0, 6)) == 'INSERT'))
			{
				$retVal = self::$db->lastInsertId();
			}

			// Stop the profiler
			if (!$isProfilerQuery)
			{
				$profiler->stop();
			}
		}
		catch (PDOException $ex)
		{
			if(strstr($ex->getMessage(), '|HYDRA_TRIGGER_ERROR_31|') === false)
			{
				if (self::$Debug)
				{
					self::drawFancyError($stmt->queryString, $params);
				}
				else
				{
					throw $ex;
				}
			}
			else
			{
				$array = explode('|', $ex->getMessage());

				if(sizeof($array) == 4)
				{
					$type = $array[2];
					$param = $array[3];
					throw new $type($param);
				}
				else
				{
					throw $ex;
				}
			}
		}
		return $retVal;
	}

	/**
	 * Run a native SQL statement against the database and return the record
	 *
	 * @param string $sql
	 * @param array $params
	 * @return array[]mixed
	 */
	public static function getSingleResultNative($sql, array $params=array())
	{
		try
		{
			$stmt = self::execSql($sql, $params);
			$my = $stmt->fetch(PDO::FETCH_NUM);
		}
		catch(Exception $e)
		{
			if (self::$profilerError === false) 	//throw the error if it is not coming from the profiler
				throw $e;

			self::$profilerError = false;			//reset to false to avoid missing other exceptions
		}
		return $my;
	}

	/**
	 * Run a native SQL statement against the database and return the result set
	 *
	 * @param string $sql
	 * @param array $params
	 * @return array[]array[]mixed
	 */
	public static function getResultsNative($sql, array $params=array(),$fetchParam=PDO::FETCH_NUM)
	{
		try
		{
			$stmt = self::execSql($sql, $params);
			$results = $stmt->fetchAll($fetchParam);
		}
		catch(Exception $e)
		{
			if (self::$profilerError === false) 	//throw the error if it is not coming from the profiler
				throw $e;

			self::$profilerError = false;			//reset to false to avoid missing other exceptions
		}
		return $results;
	}

	/**
	 * Retrieve a single record from the database as an entity structure
	 *
	 * @param DaoQuery $qry
	 * @param string $sql
	 * @param array[]mixed $params
	 * @return HydraEntity
	 */
	public static function getSingleResult(DaoQuery $qry, $sql, array $params=array())
	{
		try
		{
			$stmt = self::execSql($sql, $params);
			$my = $stmt->fetch(PDO::FETCH_NUM);
		}
		catch(Exception $e)
		{
			if (self::$profilerError === false) 	//throw the error if it is not coming from the profiler
				throw $e;

			self::$profilerError = false;			//reset to false to avoid missing other exceptions
		}

		if(!is_array($my))
			return null;

		$result = self::objectify($qry, $my);

		switch (self::$OutputFormat)
		{
			case self::AS_XML:
				$result = self::formatAsXml($result);
				break;

			case self::AS_ARRAY:
				$result = self::formatAsArray($result);
				break;

			default:
				break;
		}

		return $result;
	}

	/**
	 * Retrieve a list of records from the database and convert the output to entities
	 *
	 * @param DaoQuery $qry
	 * @param string $sql
	 * @param array[]mixed $params
	 * @return array[]HydraEntity
	 */
	public static function getResults(DaoQuery $qry, $sql, array $params=array())
	{
		try
		{
			$stmt = self::execSql($sql, $params);

			$results = array();

			while ($my = $stmt->fetch(PDO::FETCH_NUM))
			{
				$result = self::objectify($qry, $my);
				switch (self::$OutputFormat)
				{
					case self::AS_XML:
						$result = self::formatAsXml($result);
						$tmp = explode("\n", $result);
						$xmlHeader = trim($tmp[0]);
						$result = trim($tmp[1]);
						break;

					case self::AS_ARRAY:
						$result = self::formatAsArray($result);
						break;

					default:
						break;
				}
				$results[] = $result;
			}
		}
		catch(Exception $e)
		{
			if (self::$profilerError === false) 	//throw the error if it is not coming from the profiler
				throw $e;

			self::$profilerError = false;			//reset to false to avoid missing other exceptions
		}


		self::calculatePageStats($qry, $results);

		if (self::$OutputFormat == self::AS_XML)
		{
			$results = implode("\n", $results);
			$results = $xmlHeader . "\n<resultset>\n" . $results . "\n</resultset>";
		}

		return $results;
	}

	/**
	 * Convert an entity into an XML string
	 *
	 * @param HydraEntity $entity
	 * @return string
	 */
	public static function formatAsXml(HydraEntity $entity)
	{
		return XmlObjectConverter::toXml($entity);
	}

	/**
	 * Convert an entity into an array
	 *
	 * @param HydraEntity $entity
	 * @return array
	 */
	public static function formatAsArray(HydraEntity $entity)
	{
		return ArrayObjectConverter::toArray($entity);
	}

	/**
	 *
	 *
	 */
	protected static function sql_insert($data)
	{
		$sql = "insert into ";
		$tbl = $data[0];
		$keys = array_keys($data[1]);
		$vals = array_values($data[1]);
		$sql_keys= "";
		$sql_vals="";

		foreach($keys as $key)
		{
			$sql_keys .= "`".$key."`,";
		}
		$sql_keys = substr($sql_keys,0,-1);
		foreach($vals as $val)
		{
			$sql_vals .= "`".$val."`,";
		}
		$sql_vals = substr($sql_vals,0,-1);


		$sql .= $tbl ."(". $sql_keys .") values (". $sql_vals  .")";
		self::drawFancyError($sql,""); die();

	}

	/**
	 *
	 *
	 */

	public static function runSQL($type,$table,$fieldVals,$cond="")
	{
		if($type == "insert")
		{
			self::sql_insert(array($table, $fieldVals,$cond ));
		}
	}
	/**
	 * Draw a fancy box on the screen containing info about the query that was run
	 *
	 * @param string $sql
	 * @param array $data
	 */
	private static function drawFancyError($sql, $data)
	{
		echo "<div style=\"border:1px solid #000;padding:5px;background-color:#ffffcc;\">
		<b>An error occured while trying to run the following query:</b><br /><br />\n";
		var_dump($sql);
		echo "<br />\n";
		var_dump($data);
		echo "</div>";
	}

	/**
	 * blogSql - a function to blog a DAO SQL statement
	 *
	 */
	private static function blogSql($stmt, $sql_params, $msg_params=array())
	{
		if (Blogger::is_enabled()) {
			$msg = StringUtils::queryParamReplace($stmt->queryString, $sql_params, '?');
			$log_level = (isset($msg_params['sql_query_time']) && $msg_params['sql_query_time'] >= 5.0)
								?  Blogger::LOG_WARNING : Blogger::LOG_DEBUG; // LOG_WARNING will force callstack logging
			// Setting 'log_level' => Blogger::LOG_STATS will suppress logging the callstack
			Blogger::blog($msg, array ('subsystem' => 'DAO', 'log_level' => $log_level), $msg_params);
		}
	}

	/**
	 * Confirm whether a table exists
	 *
	 * @param string $table
	 * @return $string
	 * @throws HydraDaoException
	 */
	public static function validateTable($table)
	{
		$table = self::_cleanSql($table);

		$target = Dao::getResultsNative("SHOW TABLES LIKE '" . $table . "'");

		if(empty($target))
			throw new HydraDaoException("Table for '" . $table . "' does not exist");
		else
			return $table;
	}

	/**
	 * Confirm whether a column exists in a table (also checks the table)
	 *
	 * @param string $table
	 * @param string $column
	 * @return $string
	 * @throws HydraDaoException
	 */
	public static function validateColumn($table, $column)
	{
		$table = self::validateTable($table);
		$column = self::_cleanSql($column);

		$target = Dao::getResultsNative("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $column . "'");

		if(empty($target))
			throw new HydraDaoException("Column '" . $column . "' does not exist in table '" . $table . "'");
		else
			return $table;
	}
}

?>