<?php
/**
 * Evaluate MSL Controller
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 *
 */
class EvaluateMSLController extends CRUDPage
{
	/**
	 * @var querySize
	 */
	protected $querySize;

	/**
	 * @const SORT_BY_PARTCODE
	 */
	const SORT_BY_PARTCODE = 'pta.alias';

	/**
	 * @const SORT_BY_NAME
	 */
	const SORT_BY_NAME = 'pt.name';

	/**
	 * @const SORT_BY_MSL
	 */
	const SORT_BY_MSL = 'ptml.quantity';

	/**
	 * @const SORT_BY_EVALUATION
	 */
	const SORT_BY_EVALUATION = 'pt.id';

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = 'msl';
		$this->openFirst = false;
		$this->roleLocks = "pages_all,page_logistics_evaluateMSL";
		$this->querySize = 0;
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
    public function onLoad($param)
    {
		parent::onLoad($param);
		$this->initAddMSLAutoComplete();
		$this->jsLbl->Text = "";
		$this->cloneFromButton->Enabled = false;
		$this->clonePanel->Style = "display:none";
		$this->warehouseCloneTo->focus();

        if(!$this->IsPostBack || $param == "reload")
        {
        	$this->setWarehouseCloneFrom->Value = 0;
        	$this->editMSL->Visible = false;
        	$this->addGroupMSL->Visible = false;
			$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
	        $this->dataLoad();

	        $contractArr = array(array(0, '(please select)'));
			$q = new DaoReportQuery("Contract");
			$q->column("c.id");
			$q->column("c.contractName");
			$q->where("c.active=1");
			$q->orderBy("c.contractName");
			$contractArr = array_merge($contractArr, $q->execute(false));
			$this->bindDropDownList($this->ContractList, $contractArr);


			$sortResultArr = array(self::SORT_BY_PARTCODE => "Part Code",
								   self::SORT_BY_NAME => "Part Name",
								   self::SORT_BY_MSL => "MSL Value",
								   self::SORT_BY_EVALUATION => "Evaluation"
								   );
			$this->bindDropDownList($this->SortResultList, $sortResultArr);

			$sortOrderArr = array(DaoReportQuery::ASC => "in ascending order",
								  DaoReportQuery::DESC => "in descending order");
			$this->bindDropDownList($this->SortOrderList, $sortOrderArr);
        }

    }

    /**
     * Initialize and MSL auto complete
     *
     */
	public function initAddMSLAutoComplete()
    {
    	$whIds = array();
		$sql = "SELECT DISTINCT(warehouseid) FROM parttypeminimumlevel WHERE active=1";
		$results = Dao::getResultsNative($sql);
		foreach ($results as $r)
			$whIds[] = $r[0];

		//set the addMSL auto-complete to exclude all warehouses that already have an MSL set
		$this->addMSLAC->setExcludeWarehouseIds(implode(',', $whIds));
    }

    /**
     * Add MSL Suggestion Selected
     *
     * @param unknown_type $val
     */
	public function addMSLSuggestionSelected($val)
    {
    	$this->hidden_addMSLWhId->Value = $val;
    }

    /**
     * Add MSL Extra Suggest
     *
     */
    public function addMSLExtraSuggest()
    {
    	$this->hidden_addMSLWhId->Value = null;
    }

    /**
     * Get all of Entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	return array();
    }

    /**
     * How Big Was The Query
     *
     * @return unknown
     */
    protected function howBigWasThatQuery()
    {
    	return $this->querySize;
    }

    /**
     * Add MSL
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function addMSL($sender,$param)
	{
		$whIds = explode("/",$this->hidden_addMSLWhId->Value);
		$whId = end($whIds);

		$errMsg = Factory::service("MSL")->getCanSetMslOrReorderingForWarehouse($whId, "MSL");

		if ($errMsg !== true)
		{
			$this->setErrorMessage($errMsg);
			$this->dataLoad();
			return;
		}

		$this->response->Redirect('/storagelocationminimumlevel/'.$whId.'/');
	}

   /**
	*	Sets setWarehouseCloneFrom and searchs
	*/
	public function setCloneFrom()
	{
		$this->Page->jsLbl->Text = '<script type="text/javascript">searchCloneFrom();</script>';
	}

   /**
	*	Hides clone panel when 'search all' is populated
	*/
	public function setCloneTo()
	{
		$this->Page->jsLbl->Text = '<script type="text/javascript">hideClonePanel();</script>';
	}

   /**
	*	Validates data then clones
	*/
	protected function cloneMSL()
	{
		$warehouseIdFrom = $this->warehouseCloneFrom->getSelectedValue();
		$warehouseIdTo = $this->warehouseCloneTo->getSelectedValue();

		$warehouseIdFrom = explode('/',$warehouseIdFrom);
		$warehouseIdFrom = end($warehouseIdFrom);
		$warehouseFrom = Factory::service("Warehouse")->getWarehouse($warehouseIdFrom);
		if(!$warehouseFrom instanceOf Warehouse)
		{
			$this->setErrorMessage("Please select a warehouse from the Clone From auto complete.");
			$this->searchAll->Value = 1;
			$this->DataList->EditItemIndex = -1;
	    	$this->dataLoad();
			return;
		}

		$warehouseIdTo = explode('/',$warehouseIdTo);
		$warehouseIdTo = end($warehouseIdTo);
		$warehouseTo = Factory::service("Warehouse")->getWarehouse($warehouseIdTo);
		if(!$warehouseTo instanceOf Warehouse)
		{
			$this->setErrorMessage("Please select a warehouse from the Search All auto complete.");
			$this->setWarehouseCloneFrom->Value = 1;
			$this->DataList->EditItemIndex = -1;
	    	$this->dataLoad();
			return;
		}

		if($warehouseFrom->getWarehouseCategory()->getId() != $warehouseTo->getWarehouseCategory()->getId()){
			$this->setErrorMessage("'Clone MSLs from' needs to be Warehouse Category:  " . $warehouseTo->getWarehouseCategory()->getName() . ".");
			$this->setWarehouseCloneFrom->Value = 1;
			$this->DataList->EditItemIndex = -1;
	    	$this->dataLoad();
			return;
		}

		//check that users being cloned has zero MSL
		$ptml = Factory::service("PartTypeMinimumLevel")->findByCriteria("warehouseId = ? ",array($warehouseTo->getId()));
		if(count($ptml)>0){
			$this->setErrorMessage("User already has an MSL set. You may only clone to an empty MSL set.");
			$this->setWarehouseCloneFrom->Value = 1;
			$this->DataList->EditItemIndex = -1;
	    	$this->dataLoad();
			return;
		}


		$results = $this->getMslQueryResult($warehouseFrom,self::SORT_BY_PARTCODE,DaoReportQuery::ASC);
		foreach($results as $key => $row)
		{
			$partType = Factory::service("PartType")->getPartType($row['partTypeId']);
			if($partType instanceOf PartType)
			{
				$ptml = new PartTypeMinimumLevel();
				$ptml->setFirstIdentified(HydraDate::zeroDateTime());
				$ptml->setSupplyingWarehouse(Factory::service("Warehouse")->get($row['supplyingWarehouseId']));
				$ptml->setActive(true);
				$ptml->setPartType($partType);
				$ptml->setWarehouse($warehouseTo);
				$ptml->setQuantity($row['mslQty']);
				Factory::service("PartTypeMinimumLevel")->save($ptml);
			}
		}

		$this->clonePanel->Style = "display:none";
		$this->warehouseCloneFrom->setSelectedValue(0);
		$this->firstSearchButtonClicked->Value = 0;
		$this->setWarehouseCloneFrom->Value = 0;
		$this->searchAll->Value = 1;
		$this->DataList->EditItemIndex = -1;
	    $this->dataLoad();

	    $this->setInfoMessage("Successfully cloned from " . $warehouseFrom->getName() . " to " . $warehouseTo->getName() );
	}

   /**
	*	Resets variables involved in cloning
	*/
	private function resetClone()
	{
		$this->searchAll->Value = 0;
		$this->setWarehouseCloneFrom->Value = 0;
		$this->clonePanel->Style = "display:none";
		$this->warehouseCloneFrom->setSelectedValue(0);
		$this->warehouseCloneTo->setSelectedValue(0);
		$this->firstSearchButtonClicked->Value = 0;
	}

   /**
	*	Checks if 'clone to' is empty if so shows clone panel
	*	Checks that 'clone from' has MSL s set
	*/
	private function checkIfCriteriaOkForClone()
	{
		// which column does the user want to sort on?
		$sortResultName = self::SORT_BY_PARTCODE;
		$tmpSortResultName = $this->SortResultList->getSelectedValue();
		if (!empty($tmpSortResultName))
			$sortResultName = $tmpSortResultName;

		$sortOrderName = DaoReportQuery::ASC;
		$tmpSortOrderName = $this->SortOrderList->getSelectedValue();
		if (!empty($tmpSortOrderName))
			$sortOrderName = $tmpSortOrderName;

		$warehouseToId = $this->warehouseCloneTo->getSelectedValue();
		$warehouseFromId = $this->warehouseCloneFrom->getSelectedValue();
		$warehouseTo = null;
		$warehouseFrom = null;
		if($warehouseToId)
		{
			$warehouseToId = explode('/',$warehouseToId);
			$warehouseToId = end($warehouseToId);
			$warehouseTo = Factory::service("Warehouse")->getWarehouse($warehouseToId);

			if($warehouseTo instanceOf Warehouse)
			{
				$resultsTo = $this->getMslQueryResult($warehouseTo,$sortResultName,$sortOrderName);
				if(count($resultsTo)==0)
				{
					$this->clonePanel->Style = "display:block";
					$this->warehouseCloneFrom->focus();
				}
			}
		}

		if($warehouseFromId)
		{
			$warehouseFromId = explode('/',$warehouseFromId);
			$warehouseFromId = end($warehouseFromId);
			$warehouseFrom = Factory::service("Warehouse")->getWarehouse($warehouseFromId);

			if($warehouseTo instanceOf Warehouse && $warehouseFrom instanceOf Warehouse)
			{
				$resultsFrom = $this->getMslQueryResult($warehouseFrom,$sortResultName,$sortOrderName);
				if(count($resultsTo)==0 && count($resultsFrom)>0)
				{
					$this->cloneFromButton->Enabled = true;
				}

			}
		}
	}

	/**
	 * Get MSL Query Result
	 *
	 * @param unknown_type $warehouse
	 * @param unknown_type $sortResultName
	 * @param unknown_type $sortOrderName
	 * @return unknown
	 */
	private function getMslQueryResult($warehouse,$sortResultName,$sortOrderName)
	{
		//let the user filter by contract
		$contractArr = array();
		$tmpContractId = $this->ContractList->getSelectedValue();
		if (!empty($tmpContractId) && $tmpContractId > 0)
			$contractArr[] = $tmpContractId;

		$query = new DaoReportQuery('PartTypeMinimumLevel');
		$addJoin = "INNER JOIN parttype pt ON pt.id=ptml.partTypeId AND pt.active=1 ";
		$addJoin .= "INNER JOIN contract_parttype cpt ON cpt.partTypeId=pt.id ";
		$addJoin .= "INNER JOIN contract c ON cpt.contractId=c.id AND c.active=1 ";
		$addJoin .= "INNER JOIN parttypealias pta ON pta.partTypeId=pt.id AND pta.partTypeAliasTypeId=1 AND pta.active=1 ";
		$addJoin .= "inner JOIN useraccount ua ON ua.id =ptml.updatedById ";
		$addJoin .= "inner JOIN person p ON p.id =ua.personId ";

		$query->column('ptml.id', 'id');
		$query->column('pt.id', 'partTypeId');
		$query->column('ptml.supplyingwarehouseid', 'supplyingWarehouseId');
		$query->column('ptml.quantity', 'mslQty');
		$query->column('(TO_DAYS(NOW()) - TO_DAYS(ptml.firstIdentified))', 'daysSince');
		$query->column('pt.name', 'partTypeName');
		$query->column('pta.alias', "partCode");
		$query->column('ptml.updated', "updatedOn");
		$query->column('concat(p.firstName," ",p.lastName)', "updatedBy");
		$query->setAdditionalJoin($addJoin);
		$query->where('ptml.warehouseId in ('.$warehouse->getId().')');
		$query->where("ptml.active = 1");
		if (!empty($contractArr))
			$query->where("cpt.contractId IN (".join(',', $contractArr).")");

		$query->orderBy($sortResultName, $sortOrderName);
		$results = $query->execute(false, PDO::FETCH_ASSOC);
		return $results;
	}

	/**
	 * Get Warehouse Category name allowed for clone
	 *
	 * @return unknown
	 */
	protected function getWarehouseCategoryNamesAllowedForClone()
	{
		$includeWarehouseCategoryIds = array();
		$includeWarehouseCategoryIds = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'IncludeWarehouseCategoryIds');
		$warehouseCategoryNames = "";
		if(count($includeWarehouseCategoryIds)>0)
		{
			foreach($includeWarehouseCategoryIds as $warehouseCategoryId)
			{
				$results = Factory::service("WarehouseCategory")->findByCriteria("id IN " . "('" . implode("', '", $includeWarehouseCategoryIds) . "')", array());

				if(count($results)>0)
	    		{
	    			foreach($results as $rows)
	    			{
	    				if($rows instanceOf WarehouseCategory)
	    				{
	    					if($warehouseCategoryNames)
	    					{
	    						$warehouseCategoryNames .= ",<br>" . $rows->getName();
	    					}
	    					else
	    					{
	    						$warehouseCategoryNames .= $rows->getName();
	    					}
	    				}
	    			}
	    			if($warehouseCategoryNames)
	    			{
	    				return $warehouseCategoryNames . " only";
	    			}
	    		}
			}
		}

		return 'Techs';
	}

	/**
	 * Search Entity
	 *
	 * @param unknown_type $searchString
	 * @param unknown_type $focusObject
	 * @param unknown_type $pageNumber
	 * @param unknown_type $pageSize
	 * @return unknown
	 */
    protected function searchEntity($searchString,&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$output = array();
		if($this->firstSearchButtonClicked->Value)
		{
			$warehouseId = $this->warehouseid->Value;
			$this->resetClone();
		}
		else if($this->setWarehouseCloneFrom->Value)
		{
			$this->setWarehouseCloneFrom->Value = 0;
			$warehouseId = $this->warehouseCloneFrom->getSelectedValue();
			if(!$warehouseId){
				$this->setErrorMessage("Please select a warehouse from the Clone From auto complete.");
				return;
			}
		}
		else if($this->searchAll->Value)
		{
			$this->searchAll->Value = 0;
			$warehouseId = $this->warehouseCloneTo->getSelectedValue();
			if(!$warehouseId){
				$this->setErrorMessage("Please select a warehouse from the Search All auto complete.");
				return;
			}
		}
		else
		{
			$warehouseId = $this->warehouseid->Value;
		}

		$warehouseId = explode('/',$warehouseId);
		$warehouseId = end($warehouseId);
		$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId);

		$this->warehouseIdSubmit->Value = $warehouseId;

		// which column does the user want to sort on?
		$sortResultName = self::SORT_BY_PARTCODE;
		$tmpSortResultName = $this->SortResultList->getSelectedValue();
		if (!empty($tmpSortResultName))
			$sortResultName = $tmpSortResultName;

		$sortOrderName = DaoReportQuery::ASC;
		$tmpSortOrderName = $this->SortOrderList->getSelectedValue();
		if (!empty($tmpSortOrderName))
			$sortOrderName = $tmpSortOrderName;

		$results = $this->getMslQueryResult($warehouse, $sortResultName, $sortOrderName);
		if (count($results) > 0)
		{
			//get all the warehouse ids to search under for quantity
			$dao = new TreeDAO("Warehouse");
			$treeSql = $dao->createTreeSqlInclusive($warehouse);
			$whIds = array();
			$res = Dao::getResultsNative($treeSql);
			foreach ($res as $r)
			{
				$whIds[] = $r[0];
			}

			//get all the part type ids to look for
			$qtyArray = array();
			foreach($results as $row)
			{
				$qtyArray[$row["partTypeId"]] = 0;
			}

			//get the msl config params from DontHardcode table
			try
			{
				$mslConfigParams = MSLReport::getCommonMslConfigParams();
			}
			catch (Exception $e)
			{
				$this->setErrorMessage("Problem: MSLConfigParams [" . $e->getMessage() . "], please contact BSuiteHelp");
				return;
			}

			if ($mslConfigParams['ignoreReservedPartsForStock'])
			{
				$this->sohLabel->Text = '&nbsp;&nbsp;Reserved Parts are being ignored for SOH, ';
			}

			$statuses = array();
			$sql = "SELECT name FROM partinstancestatus WHERE id IN (" . implode(',', $mslConfigParams['includedPartStatusIds']) . ") AND active=1 ORDER BY name";
			$res = Dao::getResultsNative($sql);
			foreach ($res as $r)
				$statuses[] = $r[0];

			if (!empty($statuses))
			{
				$this->sohLabel->Text .= ' statuses include: ' . implode(' | ', $statuses);
			}

			//get the quantities of the part types, all at once instead of one query per part type
			$query = new DaoReportQuery("PartInstance");
			$query->setAdditionalJoin("INNER JOIN warehouse w ON w.id=pi.warehouseid AND w.active=1 AND w.ignorestockcount!=1 AND w.id IN (" . implode(',', $whIds) . ")");
			$query->column('pi.parttypeid');
			$query->column('SUM(pi.quantity)');
			$query->where('pi.partTypeId IN (' . implode(",", array_keys($qtyArray)) . ') AND pi.active = 1');

			if (!empty($mslConfigParams['includedPartStatusIds']))
				$query->where('pi.partinstancestatusid IN (' . implode(',', $mslConfigParams['includedPartStatusIds']) . ')');

			if ($mslConfigParams['ignoreReservedPartsForStock'])
				$query->where('pi.facilityrequestid IS NULL');

			$query->groupBy("pi.parttypeid");

			$res = $query->execute(false);

			//put the quantities into an array with the parttypeid as the key
			foreach ($res as $r)
			{
				if ($r[1] != null)
				{
					$qtyArray[$r[0]] = $r[1];
				}
			}

			foreach($results as $key => $row)
			{
				$soh = $qtyArray[$row['partTypeId']];

				$sf = $soh - $row['mslQty'];

				if($row['mslQty'] > $soh)
				{
					if ($row['daysSince'] > 4000) // outdated data, lets update it
					{
						$currPtml = Factory::service("PartTypeMinimumLevel")->get($row['id']);
						if ($currPtml)
						{
							// save it back to the database
							$currPtml->setFirstIdentified(new HydraDate());
							Factory::service("PartTypeMinimumLevel")->save($currPtml);
						}
						$row['daysSince'] = 0;
					}
					$status = "<span style='color:red;font-weight:bold;'>" . $sf . "</span>";
				}
				else
				{
					if ($row['daysSince'] < 4000) // outdated data, lets update it
					{
						$currPtml = Factory::service("PartTypeMinimumLevel")->get($row['id']);
						if ($currPtml)
						{
							// save it back to the database
							$currPtml->setFirstIdentified(HydraDate::zeroDateTime());
							Factory::service("PartTypeMinimumLevel")->save($currPtml);
						}
					}
					$row['daysSince'] = '-';

					$status = "-";
					if ($sf > 0)
					{
						$status = "<span style='color:green;font-weight:bold;'>" . $sf . "</span>";
					}
				}

				$output[$key] = $row;
				$output[$key]['soh'] = $soh;
				$output[$key]['status'] = $status;
			}

			if ($sortResultName == self::SORT_BY_EVALUATION)
			{
				$reverse = "";
				if ($sortOrderName == DaoReportQuery::DESC)
					$reverse = "-1 * ";

				usort($output, create_function('$a, $b', 'return ('.$reverse.'strcmp($a["status"], $b["status"]));'));
			}

			$this->querySize = sizeof($output);
		}

		if (count($output)>0)
		{
			$this->setInfoMessage('');
			$this->WarehouseLabel->setText(Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse));
    		$this->editMSL->Visible = true;
    		$this->addGroupMSL->Visible = true;
		}
		else
		{
			$this->setInfoMessage("There are no MSLs set for ($warehouse)");
			$this->WarehouseLabel->setText('');
			$this->editMSL->Visible = false;
	    	$this->addGroupMSL->Visible = false;
		}
    	$this->checkIfCriteriaOkForClone();
    	return $output;
    }

    /**
     * Populate Edit
     *
     * @param unknown_type $editItem
     */
    protected function populateEdit($editItem)
    {
    	if (!empty($editItem))
    		$editItem->NewMslQty->focus();
    }

    /**
     * Set Entity
     *
     * @param unknown_type $object
     * @param unknown_type $params
     * @param unknown_type $focusObject
     */
    protected function setEntity(&$object, $params, &$focusObject=null)
    {
    	$qtyInput = trim($params->NewMslQty->Text);
    	if (!strlen($qtyInput) || !is_numeric($qtyInput))
    	{
    		$this->setErrorMessage("Please enter valid quantity level.");
    		return;
    	}

    	//deactivate the entry
    	if ($qtyInput == "0")
    	{
	    	$object->setActive(0);
	    	$msg = "Successfully deactivated.";
    	}
    	else
    	{
	    	$object->setQuantity($qtyInput);
	    	$msg = "Entry updated.";
    	}
    	$this->setInfoMessage($msg);
    	Factory::service("PartTypeMinimumLevel")->save($object);
    }

    /**
     * Lookup entity
     *
     * @param unknown_type $id
     * @return unknown
     */
	protected function lookupEntity($id)
    {
    	return Factory::service("PartTypeMinimumLevel")->get($id);
    }
}

?>
