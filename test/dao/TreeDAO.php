<?php
/**
 * The dao for the Tree structure. Whatever is using this dao need to have tree compulsory columns: parentId, dBtree and position
 *
 * @package    Core
 * @subpackage Dao
 */
class TreeDAO extends GenericDAO
{
    /**
     * The number of digis
     *
     * @var INT
     */
    const DIGIS_PER_LEVEL = 4; //OOPSY!
    const DIGITS_PER_LEVEL = 4;
    /**
     * The tree is going to work on the base to present each level
     *
     * @var int
     */
	private $_base;
	/**
	 * Table Name
	 *
	 * @var string
	 */
	protected $_tablename;
	/**
	 * Constructor
	 *
	 * @param string $query
	 * @param int $base
	 */
	public function __construct($query)
	{
	    $this->_base = intval(1 . str_repeat('0', self::DIGITS_PER_LEVEL));
		$this->_tablename = strtolower($query);
		parent::__construct($query);
	}
	/**
	 * Makes base the new Base node for a tree of dbTree
	 *
	 * @param HydraTreeNode $base   The root node
	 * @param int           $dbTree The position
	 *
	 * @return TreeDAO
	 */
	public function addBaseNode(HydraTreeNode $base,$dbTree = 1)
	{
		$base->setParent(null);
		$base->setPosition(1);
		$base->setDbTree($dbTree);
		$this->save($base);
		return $this;
	}
	/**
	 * Adds child node to parent node
	 *
	 * @param HydraTreeNode $parent       The parent node
	 * @param HydraTreeNode $child        The child node
	 * @param bool          $commitChange Which we do Dao::exec()
	 *
	 * @return TreeDAO
	 */
	public function addTreeNode(HydraTreeNode $parent, HydraTreeNode &$child, $commitChange = true)
	{
		$child->setPosition($this->findFirstEmptyChildNode($parent));
		$child->setParent($parent);
		$child->setDbTree($parent->getDbTree());

		if ($commitChange)
			$this->save($child);
		return $this;
	}
	/**
	 * removes parent and all children from tree
	 *
	 * @param HydraTreeNode $parent The node that we are trying to remove
	 *
	 * @return TreeDAO
	 */
	public function removeTreeNode(HydraTreeNode $parent)
	{
		$set = "";
		$max = bcpow('10',64);
		$low = $high = $position = $parent->getPosition();
		while(bccomp($high,$max) == -1)
		{
			$low = bcmul($low,$this->_base);
			$high = bcadd(bcmul($high,$this->_base),bcsub($this->_base,1));
			$set .=  " OR position BETWEEN $low AND $high";
		}

		$query = "DELETE FROM $this->_tablename WHERE position = $position $set;";
		Dao::connect();
		Dao::execSql($query);
		return $this;
	}
	/**
	 * Moving a Tree node and all children to new parent
	 *
	 * @param HydraTreeNode $parent   The parent node
	 * @param HydraTreeNode $child    The child node
	 * @param bool          $override The override option
	 *
	 * @deprecated Pls using TreeDao::fastMoveTreeNode() instead
	 * @throws Exception
	 */
	public function moveTreeNode(HydraTreeNode $parent,HydraTreeNode $child, $override = false)
	{
		if($this->isParent($child,$parent))
		{
			//throw new HydraDAOException("Tried to move a Child to become a Parent.");
			return false;
		}

		if(!$override && $parent->getDbTree() != $child->getDbTree())
		{
			//throw new HydraDAOException("Tried to move a tree to a diffrent database tree.");
			return false;
		}

		$tree = array_merge(array($this->_hydraTreeNodeToArray($child)),$this->findTree($child));
		//$this->removeTreeNode($child);
		$this->addTreeNode($parent,$child);
		$tree[0]['newNode'] = $child;
		$parentPos = array_search('parent',array_keys(DaoMap::$map[$this->_tablename]));
		foreach($tree as $key => $node)
		{
			if($key == 0)
				continue;

			foreach($tree as $row)
			{
				if($node[$parentPos] == $row[0])
				{
					$object = Dao::objectify($this->getQuery(),$node);
					$this->addTreeNode($row['newNode'],$object);
					$tree[$key]['newNode'] = $object;
					break;
				}
			}
		}
		return true;
	}
	/**
	 * A fast moving a Tree node and all children to new parent
	 *
	 * @param HydraTreeNode $parent   The parent node
	 * @param HydraTreeNode $child    The child node
	 * @param bool          $override The override option
	 *
	 * @throws Exception
	 */
	public function fastMoveTreeNode(HydraTreeNode $parent,HydraTreeNode $child, $override = false)
	{
		if($this->isParent($child,$parent))
		{
			return false;
		}
		if(!$override && $parent->getDbTree() != $child->getDbTree())
		{
			return false;
		}

		$tree = array_merge(array($this->_hydraTreeNodeToArray($child)),$this->findTree($child));
		$oldPosition = $child->getPosition();
		$this->addTreeNode($parent, $child, false);
		$tree[0]['newNode'] = $child;
		// now update all subloc's position prefix !
		$newPosition = $child->getPosition();

		// make sure we don't go overboard with position size limit!
		$q = "SELECT MAX(LENGTH(wh.position)) FROM warehouse wh WHERE wh.position LIKE '$oldPosition%'";
		$res = Dao::execSql($q);
		$maxPosLen = -1;
		$canSafelyCommit = false;
		$tryUpdateKids = false;
		foreach ($res as $key => $val)
		{
			$maxPosLen = $val[0];
		}
		if ($maxPosLen > -1)
		{
			$q = "SELECT tt.id FROM treetable tt WHERE LENGTH(tt.lookup)='".(strlen($newPosition) - strlen($oldPosition) + $maxPosLen)."'";
			$res = Dao::execSql($q);
			foreach ($res as $key => $val)
			{
				$tryUpdateKids = true;
			}
			if (!$tryUpdateKids)
				throw new Exception("Tree level exceeds limit. Aborting operation.");
		}
		if ($tryUpdateKids && !empty($oldPosition) && !empty($newPosition) && $oldPosition !== $newPosition)
		{
			$q = "UPDATE warehouse SET position=CONCAT('$newPosition', SUBSTRING(position, LENGTH('$oldPosition') + 1)) WHERE position LIKE '".$oldPosition."_%'";
			$res = Dao::execSql($q);
			$canSafelyCommit = true;
		}
		if ($canSafelyCommit)
		{
			$this->save($child);
		}
		return $canSafelyCommit;
	}
    /**
     * getting the children nodes
     *
     * @param HydraTreeNode $parent The paren node
     * @param int           $depth  Unitl which level we are getting the nodes
     *
     * @return Ambigous <HydraEntity, multitype:number , multitype:>
     */
	public function findTreeChildrenAsObject(HydraTreeNode $parent,$depth=PHP_INT_MAX)
	{
		$children = $this->findTree($parent, $depth);
		foreach($children as $key => $child)
		{
			$children[$key] = Dao::objectify($this->getQuery(),$child);
		}
		return $children;
	}
	/**
	 * Find all sub nodes of Parent
	 *
	 * @param HydraTreeNode $parent The parent node
	 * @param int           $depth  Until which level we are getting
	 *
	 * @return array[int]
	 */
	public function findTree(HydraTreeNode $parent,$depth = PHP_INT_MAX)
	{
		return Dao::getResultsNative($this->createTreeSql($parent,$depth));
	}
	/**
	 * Override of findall
	 *
	 * @param int $dbTree The root id
	 *
	 * @return Ambigous <array(HydraEntity), Ambigous, multitype:, string, multitype:Ambigous <multitype:, multitype:NULL boolean number string mixed > >
	 */
	public function findAllForTree($dbTree = 1)
	{
		return $this->findByCriteria('dbTree = ?',array($dbTree));
	}

	/**
	 * This function returns all the parent Nodes of the HydraTreeNode provided
	 *
	 * @param HydraTreeNode $node             The parent of HydraTreeNode to look against
	 * @param string        $order            the query order
	 * @param boolena       $includeInactive  Do we want to include the inactive tree nodes or not
	 *
	 * @return Array[] HydraTreeNode
	 */
	public function findParentsAsObjects(HydraTreeNode $node, $order = 'asc', $includeInactive = false)
	{
		if($includeInactive === true)
			Dao::$AutoActiveEnabled = false;

		$pos = $node->getPosition();
		$arr[] = substr($pos, 0, 1);
		for ($i = self::DIGITS_PER_LEVEL, $j = strlen($pos); $i < $j; $i += self::DIGITS_PER_LEVEL)
			$arr[] = substr($pos, 0, $i+1);
		array_pop($arr);

		//Fail safe ... if $pos = 0 OR $pos = 1 then array ends up empty which blows out query
		if(count($arr)==0)
			$arr[0] = -1;

		$positionSql = "('".implode("', '", $arr)."')";

		$query = $this->getQuery();
		$query->orderBy($query->getFocusClass(). ".position", $order);
		$this->setQuery($query);
		$result = $this->findByCriteria("position IN ".$positionSql);

		if($includeInactive === true)
			Dao::$AutoActiveEnabled = true;

		return $result;
	}

	/**
	 * Getting all the parent Warehouse!!!!!!
	 *
	 * @param HydraTreeNode $node
	 * @param string        $order The order that we are
	 * @param boolean       $includeInactive Should we include the deactivated warehouse or NOT
	 *
	 * @deprecated This should NOT be here as a Dao function!!!! It's for Warehouse ONLY!!!
	 *
	 * @return multitype:
	 */
	public function findParents(HydraTreeNode $node, $order = 'asc', $includeInactive = false)
	{
		$pos = $node->getPosition();
		$arr[] = substr($pos, 0, 1);
		for ($i = self::DIGITS_PER_LEVEL, $j = strlen($pos); $i < $j; $i += self::DIGITS_PER_LEVEL)
			$arr[] = substr($pos, 0, $i+1);
		array_pop($arr);
		//Fail safe ... if $pos = 0 OR $pos = 1 then array ends up empty which blows out query
		if(count($arr)==0)
		{
			$arr[0] = -1;
		}

		$activeSqlPart = "w.active = 1";
		if($includeInactive === true)
			$activeSqlPart = "w.active IN (1,0)";

		$sql = 'select w.id, w.name, w.facilityId, w.warehouseCategoryId from warehouse w where '.$activeSqlPart;
		$sql .= " and (w.position='" . implode("' OR w.position='", $arr) . "') order by w.position $order";
		$result = Dao::getResultsNative($sql);
		return $result;
	}
	/**
	 * Determins if Parent is a parent of Child
	 *
	 * @param HydraTreeNode $parent The parent node
	 * @param HydraTreeNode $child  The child node
	 *
	 * @return boolean
	 */
	public function isParent(HydraTreeNode $parent, HydraTreeNode $child)
	{
		$parents = $this->findParents($child);
		foreach($parents as $row)
		{
			if($row[0] == $parent->getId())
				return true;
		}
		return false;
	}
	/**
	 * Gets the root node of the Tree
	 *
	 * @return HydraTreeNode
	 */
	public function getRootNode($dbTree=1)
	{
		$query = $this->getQuery();
		$query->where("position = 1 and dbTree = $dbTree");
		return Dao::getSingleResult($query,$query->generateForSelect());
	}
	/**
	 * Finds an Empty Position after last position within a range in the tree table
	 *
	 * @param HydraTreeNode $parent The parent node
	 *
	 * @return int
	 */
	protected function findEmptyChildNode(HydraTreeNode $parent)
	{
		$idbase = bcmul($parent->getPosition(),$this->_base);
		$idbasehigh = bcadd($idbase,$this->_base);
		$tree = $parent->getDbTree();

		//changed query was slow as hell, checks below to not add more than 999 children also
		$query = "	SELECT MAX(W1.position) AS id
					FROM $this->_tablename AS W1
					WHERE W1.dbtree=$tree AND (W1.position >= $idbase AND W1.position< $idbasehigh)
					LIMIT 0,1";

		Dao::connect();
		$array = Dao::getSingleResultNative($query);

		if(sizeof($array) != 1)
			throw new HydraDaoException(" Could not determine empty record in tree structure to populate.");
		else
		{
			$pos = $array[0];
			if (is_null($pos)) //is first child node
			{
				return bcadd($idbase, 1);
			}

			$lastPos = substr($pos, strlen($pos) - self::DIGITS_PER_LEVEL, self::DIGITS_PER_LEVEL);
			if ((int)$lastPos == ($this->_base - 1))
			{
				throw new HydraDaoException(" You have reached the maximum (" . ($this->_base-1) . ") number of children under this location, aborted...");
			}
			else
				return bcadd($pos,1);
		}
	}

	/**
	 * Finds first Empty Position within a range in the tree table, to replace findEmptyChildNode() : Complete rewrite May 2013 RT42928 BG/JT
	 *
	 * @param HydraTreeNode $parent The parent node
	 *
	 * @return int
	 */
 	public function findFirstEmptyChildNode(HydraTreeNode $parent)
	{
		$parentPosition = $parent->getPosition();

		//get all direct children under the parent
		$query = "	SELECT position
					FROM $this->_tablename
					WHERE dbtree=" . $parent->getDbTree() . " AND position LIKE '" . $parentPosition . str_repeat('_', self::DIGITS_PER_LEVEL) . "'
					ORDER BY position";
		$res = Dao::getResultsNative($query);

		$usedPositions = array();
		foreach ($res as $r)
		{
			$usedPositions[] = substr($r[0], -self::DIGITS_PER_LEVEL);										//get the last 4 digits of the position
		}

		if (empty($usedPositions))
		{
			return $parentPosition . str_repeat('0', self::DIGITS_PER_LEVEL);								//no nodes yet so start from start
		}
		else
		{
			$maxPosition = str_repeat('9', self::DIGITS_PER_LEVEL);
			for ($i=1; $i <= $maxPosition; $i++)
			{
				if (!in_array($i, $usedPositions))
				{
					return $parentPosition . str_pad($i, self::DIGITS_PER_LEVEL, 0, STR_PAD_LEFT);			//return new position
				}
			}
			throw new HydraDaoException(" No available warehouses for this parent: " . $parentPosition); 	//If we get this far without finding anything then there are no available warehouses left
		}
	}


	/**
	 * coverts a HydraTreeNode into an array entry useful for matching
	 *
	 * @param HydraTreeNode $node The HydraTreeNode that we are trying to convert
	 *
	 * @return array[string]
	 */
	private function _hydraTreeNodeToArray(HydraTreeNode $node)
	{
		$keys = array_keys(DaoMap::$map[$this->_tablename]);
		$parentPos = array_search('parent',$keys);
		$positionPos = array_search('position',$keys);
		return array(0 => $node->getId(), $parentPos => $node->getParent(), $positionPos => $node->getPosition());
	}

	/**
	 * getter base
	 *
	 * @return int
	 */
	public function getBase()
	{
		return $this->_base;
	}
	/**
	 * Getting the Tree sql
	 *
	 * @param HydraTreeNode $parent The parent node
	 * @param int           $depth  Which level are we getting
	 *
	 * @return string
	 */
	public function createTreeSql(HydraTreeNode $parent, $depth = PHP_INT_MAX)
	{
		$sql = $this->createTreeSqlInclusive($parent, $depth, false, '*')." order by xxtn.position ASC;";
		return $sql;
	}

	/**
	 * Creating the Tree Sql for HydraTreeNode
	 *
	 * @param HydraTreeNode $parent    The parent node
	 * @param int           $depth     Which level are we getting
	 * @param bool          $inclusive Whether we need to include $parent
	 * @param string        $field     Which field we are creating agains
	 *
	 * @return string
	 */
	public function createTreeSqlInclusive(HydraTreeNode $parent, $depth = PHP_INT_MAX, $inclusive = true, $field = 'xxtn.id')
	{
		$tree = $parent->getDbTree();
		$position = $parent->getPosition();
		if ($depth == 1)
		{
			$positionSql = str_repeat("_", self::DIGITS_PER_LEVEL);
			$positionClause = "xxtn.position LIKE '" . $position . $positionSql."'";
			if ($inclusive)
				 $positionClause .= " OR xxtn.position = '$position'";
		}
		else
		{
			$positionClause = "xxtn.position LIKE '$position%'";
			if (!$inclusive)
				$positionClause .= " and xxtn.position != '$position'";
		}

		return "SELECT $field FROM {$this->_tablename} xxtn WHERE ($positionClause) and xxtn.active = 1 and xxtn.dbtree=$tree";
	}

	/**
	 * Find ids of children for parent node
	 *
	 * @param HydraTreeNode $parent The parent node
	 * @param int           $depth  Until which level we are getting
	 *
	 * @return int
	 */
	public function getChildrenIds(HydraTreeNode $parent, $depth=PHP_INT_MAX)
	{
		$ids = array();
		$res = Dao::getResultsNative($this->createTreeSqlInclusive($parent, $depth, false));
		foreach ($res as $r)
			$ids[] = $r[0];

		return $ids;
	}

	/**
	 * Find count of children for parent node
	 *
	 * @param HydraTreeNode $parent The parent node
	 * @param int           $depth  Until which level we are getting
	 *
	 * @return int
	 */
	public function getChildrenCount(HydraTreeNode $parent, $depth=PHP_INT_MAX)
	{
		$res = Dao::getSingleResultNative($this->createTreeSqlInclusive($parent, $depth, false, 'COUNT(xxtn.id)'));
		if ($res !== false)
			return $res[0];

		return 0;
	}
}


?>