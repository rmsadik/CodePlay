<?php

class DaoJoin
{
	private $focus;
	private $focusAlias;
	private $join = '';
	private $lastEntity;
	private $lastEntityAlias;
	
	function __construct($baseEntityName)
	{
		$this->focus = $baseEntityName;
		
		DaoMap::loadMap($baseEntityName);
		$this->focusAlias = DaoMap::$map[strtolower($this->focus)]['_']['alias'];
		
		$this->lastEntity = $this->focus;
		$this->lastEntityAlias = $this->focusAlias;
	}
	
	function revertStateTo($baseEntityName, $baseEntityAlias=null)
	{
		$this->focus = $baseEntityName;
		
		DaoMap::loadMap($baseEntityName);
		
		if (is_null($baseEntityAlias))
			$this->focusAlias = DaoMap::$map[strtolower($this->focus)]['_']['alias'];
		else
			$this->focusAlias = $baseEntityAlias;
			
		$this->lastEntity = $this->focus;
		$this->lastEntityAlias = $this->focusAlias;
		
		return $this;
	}
	
	function leftJoin($relationship, $alias=null,$where="")
	{
		$this->join .= ' left join ' . $this->build($relationship, $alias, 'left join',$where);
		return $this;
	}
	
	function innerJoin($relationship, $alias=null,$where="")
	{
		$this->join .= ' inner join ' . $this->build($relationship, $alias, 'inner join',$where);
		return $this;
	}
	
	public function overrideJoin($join)
	{
		$this->join .= ' '.$join.' ';
		return $this;
	}
	
	private function build($relationship, $alias, $joinType,$where="")
	{
		list($entity, $field) = explode('.', $relationship);
		
		$p = DaoMap::$map[strtolower($this->lastEntity)][$field];
		
		switch ($p['rel'])
		{
			case DaoMap::MANY_TO_MANY:
				$sql = $this->buildManyToMany($relationship, $joinType,$alias,$where);
				break;
		
			case DaoMap::ONE_TO_MANY:
				$sql = $this->buildOneToMany($relationship,$alias,$where);
				break;
		
			case DaoMap::ONE_TO_ONE:
				$sql = $this->buildOneToOne($relationship,$alias,$where);
				break;
		
			default:
				$sql = $this->buildManyToOne($relationship,$alias,$where);
				break;
		}		
		
		return $sql;
	}
	
	private function buildManyToMany($relationship, $joinType,$alias = null,$where)
	{
		//var_dump("ManyToMany");
		
		list($entity, $field) = explode('.', $relationship);
		DaoMap::loadMap($entity);
		
		$p = DaoMap::$map[strtolower($entity)][$field];
		
		$joinClass = $p['class'];
		$joinTable = strtolower($joinClass);

		DaoMap::loadMap($joinClass);
		$joinAlias = DaoMap::$map[$joinTable]['_']['alias'];
		
		$focus = strtolower($this->focus);
		
		$joinTableMap = strtolower($p['class']);
			
		// Join in the many to many join table
		
		if ($p['side'] == DaoMap::RIGHT_SIDE)
			$mtmJoinTable = strtolower($entity) . '_' . $joinTableMap; 
		else
		    $mtmJoinTable = $joinTableMap . '_' . strtolower($entity);
		
		DaoMap::loadMap($p['class']);
		if($alias == null)
		{
			$joinAlias = DaoMap::$map[$joinTable]['_']['alias'];
			$m2mJoinAlias = $mtmJoinTable;		
		} else {
			$joinAlias = $alias;
			$m2mJoinAlias = $joinAlias."_join";	
		}
		
			
		$sql = sprintf('%s %s on (%s.id = %s.%sId)',
			$mtmJoinTable,
			($mtmJoinTable === $m2mJoinAlias) ? "" : $m2mJoinAlias,		// is this is problematic ?
			//$m2mJoinAlias, // edited by DM 20120829
			$this->lastEntityAlias,
			$m2mJoinAlias,
			strtolower(substr($entity, 0, 1)) . substr($entity, 1));

		
		$joinTableMap = strtolower($entity);
		$sql .= sprintf(' %s %s %s on (%s.%sId = %s.id%s)',
			$joinType,
			$joinTable,
			$joinAlias,
			$m2mJoinAlias,
			strtolower(substr(DaoMap::$map[$joinTableMap][$field]['class'], 0, 1)) . substr(DaoMap::$map[$joinTableMap][$field]['class'], 1),
			$joinAlias,
			$this->buildAndWhere($where));

		$this->lastEntity = $joinClass;
		$this->lastEntityAlias = $joinAlias;
		
		return $sql;
	}
	
	private function buildOneToMany($relationship,$alias = null,$where)
	{
		//var_dump("OneToMany");
		
		list($entity, $field) = explode('.', $relationship);
		DaoMap::loadMap($entity);
		
		$joinClass = DaoMap::$map[strtolower($entity)][$field]['class'];
		$joinTable = strtolower($joinClass);

		DaoMap::loadMap($joinClass);
		if($alias == null)
			$joinAlias = DaoMap::$map[$joinTable]['_']['alias'];
		else
			$joinAlias = $alias;
		
		$sql = sprintf('%s %s on (%s.id = %s.%sId%s)',
				$joinTable,
				$joinAlias,
				$this->lastEntityAlias,
				$joinAlias,
				strtolower(substr($this->lastEntity, 0, 1)) . substr($this->lastEntity, 1),
				$this->buildAndWhere($where));
		
		$this->lastEntity = $joinClass;
		$this->lastEntityAlias = $joinAlias;
		
		return $sql;
	}
	
	private function buildManyToOne($relationship,$alias = null,$where)
	{
		//var_dump("ManyToOne");
		list($entity, $field) = explode('.', $relationship);
		DaoMap::loadMap($entity);
		
		$joinClass = DaoMap::$map[strtolower($entity)][$field]['class'];
		$joinTable = strtolower($joinClass);

		DaoMap::loadMap($joinClass);
		if($alias == null)
			$joinAlias = DaoMap::$map[$joinTable]['_']['alias'];
		else
			$joinAlias = $alias;		
		
		$sql = sprintf('%s %s on (%s.%sId = %s.id%s)',
				$joinTable,
				$joinAlias,
				$this->lastEntityAlias,
				$field,
				$joinAlias,
				$this->buildAndWhere($where));
		
		$this->lastEntity = $joinClass;
		$this->lastEntityAlias = $joinAlias;
				
		return $sql;
	}
	
	private function buildOneToOne($relationship,$alias = null,$where)
	{
		//var_dump("OneToOne");
		list($entity, $field) = explode('.', $relationship);
		DaoMap::loadMap($entity);
		
		$p = DaoMap::$map[strtolower($entity)][$field];
		
		$joinClass = $p['class'];
		$joinTable = strtolower($joinClass);

		DaoMap::loadMap($joinClass);
		if($alias == null)
			$joinAlias = DaoMap::$map[$joinTable]['_']['alias'];
		else
			$joinAlias = $alias;		
		
		if ($p['owner'])
		{
			$sql = sprintf('%s %s on (%s.%sId = %s.id%s)',
				$joinTable,
				($joinTable === $joinAlias) ? "" : $joinAlias,		// is this is problematic ?
				// $joinAlias, // edited by DM 20120829			
				$this->lastEntityAlias,
				$field,
				$joinAlias,
				$this->buildAndWhere($where));
		}
		else
		{
			$sql = sprintf('%s %s on (%s.id = %s.%sId %s)',
				$joinTable,
				$joinAlias,
				$this->lastEntityAlias,
				$joinAlias,
				strtolower(substr($this->lastEntity, 0, 1)) . substr($this->lastEntity, 1),
				$this->buildAndWhere($where));
		}
		
		$this->lastEntity = $joinClass;
		$this->lastEntityAlias = $joinAlias;
				
		return $sql;
	}
	
	function buildAndWhere($where)
	{
		if($where == "")
			return "";
		else
			return " and ($where)";
	}
	
	function __toString()
	{
		return $this->join;
	}
}

?>