<?php
/**
 * Warehouse Minimum Level Controller
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class WarehouseMinimumLevelController extends CRUDPage
{
	/**
	 * @var mslWh,suppWh
	 */
	private $mslWh, $suppWh;

	/**
	 * @var barcodes
	 */
	public $barcodes;
	/**
	 * @var totalRows
	 */
	protected $totalRows = 0;

	/**
	 * @var orange
	 */
	private $orange = '#ff6c00';

	/**
	 * @var hideSuppWh
	 */
	public $hideSuppWh = false;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = "logistics_storeageLocationMiniumLevel";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_storeageLocationMiniumLevel";
		$this->barcodes = array();
		$this->focusOnSearch = false;

		//check here to see if the user has a NAB contract filter, if so we hide all the supplying warehouse stuff
		$uaFilters = Session::getRoleFilters();
		foreach ($uaFilters as $uaf)
		{
			$f = $uaf->getFilter();
			if ($f->getId() == 3)
			{
				$vals = explode(',', $uaf->getFilterValue());
				if (in_array(89, $vals))
					$this->hideSuppWh = true;
			}
		}
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
       	$this->MainContent->Enabled = true;
       	$this->dataPanel->Style = '';
       	$this->jsLbl->Text = '';
       	if ($this->hideSuppWh)
       	{
       		$this->supPWhPanel->Style = 'display:none;';
       	}

		$this->mslWh = Factory::service("Warehouse")->getWarehouse($this->Request['id']);
		if (!$this->mslWh instanceof Warehouse)
		{
	       	$this->MainContent->Enabled = false;
			$this->setErrorMessage('Invalid MSL Warehouse...');
	       	$this->dataPanel->Style = 'display:none;';
			return;
		}

       	$resultsPerPage = $this->resultsPerPageList->numResults->getSelectedValue();
       	if($resultsPerPage == '') $resultsPerPage=50;
       	$this->DataList->pageSize = $resultsPerPage;
       	$this->MSLDataList->pageSize = $resultsPerPage;

	    if(!$this->IsPostBack)
        {
			$this->populateSupplyingWarehouseList();
			$this->DataList->EditItemIndex = -1;
			$this->dataLoad();
        }
        $this->setErrorMessage('');
    }

    /**
     * Reset
     *
     */
    public function reset()
    {
    	$this->Response->Redirect('/storagelocationminimumlevel/' . $this->Request['id']);
    }

    /**
     * Returns the info message formatted
     */
    public function getInfoMessageTable()
    {
    	$suppBread = 'No Supplying Warehouse';
    	$this->suppWh = Factory::service("Warehouse")->getWarehouse($this->suppWhId->Value);
    	if ($this->suppWh instanceof Warehouse)
    		$suppBread = Factory::service("Warehouse")->getWarehouseBreadCrumbs($this->suppWh, false, '/');

    	$this->currentSuppWhBreadcrumbs->Value = $suppBread;
    	$this->selectedSuppWhBreadcrumbs->Value = $suppBread;
    	$msg = '<table border="0">
    				<tr>
    					<td width="200px">Setting Warehouse MSL for: </td>
    					<td>' . Factory::service("Warehouse")->getWarehouseBreadCrumbs($this->mslWh, false, '/') . '</td>
    				</tr>';

    	if (!$this->hideSuppWh)
    	{
    		$msg .= '<tr>
	    				<td>For Supplying Warehouse: </td>
	    				<td>' . $suppBread . '</td>
    				</tr>';
    	}

    	$msg .= '</table>';
    	return $msg;
    }

    /**
     * Return true or false to show the move supplying warehouse check boxes
     */
    public function getShowMoveSuppWhCheckBoxes()
    {
    	//we aren't showing any supplying warehouse stuff
    	if ($this->hideSuppWh)
    		return 'false';

    	$items = $this->suppWhList->getItems();
    	return (count($items) > 1 ? 'true' : 'false');	//show the checkboxes if there are more than one supplying warehouse
    }

    /**
     * Populate the supplying warehouse list
     */
    protected function populateSupplyingWarehouseList($additionalItemId = null)
    {
    	$arr = array();
    	$arr[] = array(0, 'No Supplying Warehouse');

    	$selectedItemId = 0;
    	$addWh = $defWh = null;
    	if ($additionalItemId != null)
    	{
    		$addWh = Factory::service("Warehouse")->getWarehouse($additionalItemId);
    	}

    	$suppWhIds = Factory::service("MSL")->getSupplyInfoFromSuppliedWarehouse($this->mslWh->getId());
    	if (count($suppWhIds))
    	{
	    	foreach ($suppWhIds as $sw)
	    	{
	    		$wh = Factory::service("Warehouse")->getWarehouse($sw);
	    		if ($wh instanceof Warehouse)
	    		{
	    			if ($selectedItemId == 0)
	    				$selectedItemId = $wh->getId();

	    			$arr[] = array($wh->getId(), Factory::service("Warehouse")->getWarehouseBreadCrumbs($wh, false, '/'));
	    		}
	    	}
    	}
    	else //we have no supplying warehouses so get the main bytecraft store based on the state
    	{
    		//check if we have any MSLs set for this warehouse yet
    		$sql = "SELECT id FROM parttypeminimumlevel WHERE active=1 AND warehouseid={$this->mslWh->getId()}";
    		$res = Dao::getSingleResultNative($sql);
    		if ($res === false) //we have no MSLs set yet
    		{
	    		$state = $this->mslWh->getState();
	    		if ($state instanceof State)
	    		{
	    			$defWh = Factory::service("Warehouse")->getMainBytecraftWarehouseFromState($state); //get the bytecraft store for state
	    		}

	    		if (!$defWh instanceof Warehouse)
	    		{
	    			//we found nothing so default to Dandenong Store
	    			$defWh = Factory::service("Warehouse")->getWarehouse(11467);
	    		}

		    	if ($defWh instanceof Warehouse)
		    	{
		    		$arr[] = array($defWh->getId(), Factory::service("Warehouse")->getWarehouseBreadCrumbs($defWh, false, '/'));
		    		$selectedItemId = $defWh->getId();
		    	}
    		}
    		else
    		{
    			//we have MSLs set but no supplying warehouse
    		}

    	}

    	//we have to add a new item, and select it
    	if ($addWh instanceof Warehouse)
    	{
    		$arr[] = array($addWh->getId(), Factory::service("Warehouse")->getWarehouseBreadCrumbs($addWh, false, '/'));
    		$selectedItemId = $addWh->getId();
    	}

		$this->bindDropDownList($this->suppWhList, $arr);
		$this->suppWhList->setSelectedValue($selectedItemId);

		$this->suppWh = Factory::service("Warehouse")->getWarehouse($selectedItemId);
		$this->suppWhId->Value = $selectedItemId;
    }

    /**
     * Called after add supplying warehouse auto-complete suggestion is selected
     */
    public function addSuppWhSuggestionSelected($val)
    {
    	$this->hidden_addSuppWhId->Value = $val;
    }

    /**
     * Function is called as well as auto-complete suggest
     */
    public function addSuppWhExtraSuggest()
    {
    	$this->hidden_addSuppWhId->Value = null;
    }

    /**
     * Called when user adds a supplying warehouse
     */
    public function addSuppWh()
    {
    	$this->setErrorMessage('');
    	$breadcrumbs = explode('/', $this->hidden_addSuppWhId->Value);
    	$suppWh = Factory::service("Warehouse")->getWarehouse(end($breadcrumbs));
    	if ($suppWh instanceof Warehouse)
    	{
    		$facWh = $suppWh->getNearestFacilityWarehouse();
    		if (!$facWh instanceof Warehouse)
    		{
	    		$this->setErrorMessage("You cannot add ($suppWh) as a supplying warehouse as it is not inside a valid facility...<br /><br />");
	    		return;
    		}

    		$this->populateSupplyingWarehouseList($suppWh->getId());
	    	$this->addSuppWh->Text = '';;
	    	$this->addSuppWh->Value = null;
    		$this->hidden_addSuppWhId->Value = null;

			$this->jsLbl->Text = '<script type="text/javascript">afterAddOrMoveSuppWh();</script>';
    		return;
    	}

    	$this->setErrorMessage('Invalid Supplying Warehouse...<br /><br />');
    	$this->setInfoMessage($this->getInfoMessageTable());
    }

    /**
     * Called when user moves parts to new supplying warehouse
     */
    public function moveSuppWh()
    {
    	$suppWhId = $this->suppWhList->getSelectedValue();
    	$suppWh = Factory::service("Warehouse")->getWarehouse($suppWhId);
    	if ($suppWh instanceof Warehouse || $suppWhId == 0)
    	{
	    	foreach($this->DataList->items as $item)
	    	{
	    		if ($item->chk->checked)
	    		{
	    			$id = $this->DataList->DataKeys[$item->itemindex];
	    			$msl = Factory::service("MSL")->get($id);

	    			if ($msl instanceof PartTypeMinimumLevel)
	    			{
		    			$msl->setSupplyingWarehouse($suppWh);
		    			Dao::save($msl);
	    			}
	    		}
	    	}
	    	$this->jsLbl->Text = '<script type="text/javascript">afterAddOrMoveSuppWh();</script>';
	    	return;
    	}
    	else
    	{
    		$this->setErrorMessage('Invalid Supplying Warehouse...<br /><br />');
    		$this->setInfoMessage($this->getInfoMessageTable());
    	}
    }

    /**
     * Called when supplying warehouse drop down is changed
     */
    public function suppWhChanged($sender, $param)
    {
    	$suppBread = 'No Supplying Warehouse';
    	$this->suppWh = Factory::service("Warehouse")->getWarehouse($this->suppWhList->getSelectedValue());
    	if ($this->suppWh instanceof Warehouse)
    		$suppBread = Factory::service("Warehouse")->getWarehouseBreadCrumbs($this->suppWh, false, '/');

    	$this->selectedSuppWhBreadcrumbs->Value = $suppBread;
    }

    /**
     * Load Match List
     *
     * @param unknown_type $contract
     * @param unknown_type $partTypeId
     */
    protected function loadMatchList($contract = null,$partTypeId="")
    {
    	$pageNumber = $this->MSLDataList->CurrentPageIndex + 1;
    	$pageSize = $this->MSLDataList->pageSize;

    	$query = new DaoReportQuery("PartType");
    	$query->column('pt.id');
    	$query->column('pt.name');
    	$query->column("(SELECT GROUP_CONCAT(DISTINCT ptaxx.alias SEPARATOR ', ') FROM parttypealias ptaxx where ptaxx.partTypeId = pt.id AND ptaxx.partTypeAliasTypeId = 1 and ptaxx.active = 1 GROUP BY ptaxx.parttypeid)","ptalias");
    	$query->where("pt.active=1");
    	$query->orderBy('pt.name',DaoReportQuery::ASC);
    	$query->page($pageNumber,$pageSize);

    	$query->setAdditionalJoin(' INNER JOIN contract_parttype cpt ON cpt.partTypeId = pt.id
    								INNER JOIN contract c ON cpt.contractId=c.id AND c.active=1');

    	if($contract != null)
    	{
    		$query->where('c.id=? AND (pt.id not in (SELECT partTypeId FROM parttypeminimumlevel p where p.active = 1 and p.warehouseId = ?))',array($contract,$this->mslWh->getId()));
    	}
    	else
    	{
    		$query->where('(pt.id not in (SELECT partTypeId FROM parttypeminimumlevel p where p.active = 1 and p.warehouseId = ?))',array($this->mslWh->getId()));
    	}
		if(trim($partTypeId)!="")
    		$query->where('pt.id='.$partTypeId);

    	if ($this->serialisedPartsOnly->Checked)
    		$query->where('pt.serialised=1');

    	$query->where('c.id!=1'); //filter out alpha parts

    	$results = $query->execute(true);

    	$this->MSLDataList->VirtualItemCount = $query->TotalRows;
    	$this->MSLPaginationPanel->visible = $query->TotalRows > $pageSize;

    	$this->MSLDataList->DataSource = $results;
    	$this->MSLDataList->DataBind();
    }

    /**
     * MSL Page Changed
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function MSLPageChanged($sender, $param)
    {
    	$this->MSLDataList->EditItemIndex = -1;
      	$this->MSLDataList->CurrentPageIndex = $param->NewPageIndex;
      	$this->dataLoad();
    }

    /**
     * How Big Was the Query
     *
     * @return unknown
     */
    protected function howBigWasThatQuery()
    {
    	return $this->totalRows;
    }

    /**
     * Get all of entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$query = new DaoReportQuery("PartTypeMinimumLevel");
    	$query->column('ptml.id');
    	$query->column('pt.name');
    	$query->column('ptml.quantity');
    	$query->column("(SELECT GROUP_CONCAT(DISTINCT ptaxx.alias SEPARATOR ', ') FROM parttypealias ptaxx where ptaxx.partTypeId = pt.id AND ptaxx.partTypeAliasTypeId = 1 and ptaxx.active = 1 GROUP BY ptaxx.parttypeid)","ptalias");
    	$query->where(" ptml.active = 1");
    	$query->where('ptml.warehouseid='.$this->mslWh->getId());

    	//we are showing the supplying warehouse stuff
    	if ($this->hideSuppWh == false)
    	{
	    	$suppWhId = $this->suppWhList->getSelectedValue();
	    	if ($suppWhId == 0)
	    	{
	    		$query->where('ptml.supplyingwarehouseid IS NULL');
	    	}
	    	else
	    	{
	    		$query->where('ptml.supplyingwarehouseid='.$suppWhId);
	    	}
    	}

    	$addJoin = " INNER JOIN parttype pt ON ptml.partTypeId=pt.id AND pt.active = 1";
    	$addJoin .= " INNER JOIN contract_parttype cpt ON cpt.partTypeId=pt.id ";
    	$addJoin .= " INNER JOIN contract c ON cpt.contractId=c.id AND c.active=1 ";
    	$query->setAdditionalJoin($addJoin);
    	$query->page($pageNumber,$pageSize);
    	$query->orderBy('pt.name',DaoReportQuery::ASC);

    	$results = $query->execute(true);
       	$this->totalRows = $query->TotalRows;

    	$query->where('c.id!=1'); //filter out alpha parts

    	$this->loadMatchList();

    	$this->DataList->VirtualItemCount = $query->TotalRows;
    	$this->PaginationPanel->visible = $query->TotalRows > $pageSize;

    	return  $results;
    }

    /**
     * Update Stock
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function updateStock($sender,$param)
    {
    	$id = $param->CommandParameter;
    	$msl = Factory::service("PartTypeMinimumLevel")->get($id);
    	$text = intval($sender->parent->quantity->Text);

    	if($text < 1)
    	{
    		$msl->setActive(0);
    	} else {
    		$msl->setQuantity($text);
    	}

    	Factory::service("PartTypeMinimumLevel")->save($msl);

    	$this->dataLoad();
    	$this->loadMatchList();
    }

    /**
     * To Perform Search
     *
     * @return unknown
     */
    protected function toPerformSearch()
    {
    	$search = false;
    	$selectedContract = $this->contract->getSelectedValue();
    	$selectedPartType = $this->SearchPartType->getSelectedValue();
    	if(!empty($selectedContract) || !empty($selectedPartType))
    		$search = true;

    	$this->suppWh = Factory::service("Warehouse")->getWarehouse($this->suppWhList->getSelectedValue());
    	$this->suppWhId->Value = $this->suppWhList->getSelectedValue();
    	$this->setInfoMessage($this->getInfoMessageTable());

    	return !$search;
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
    	$suppWhId = null;
    	$foundParts = false;

    	$contract = $this->contract->getSelectedValue();
    	$partTypeId = $this->SelectedPartTypeId->Value;

    	if (trim($partTypeId)!="") //we are searching on part type so check the supplying warehouse so we'll always have a result
    	{
    		$sql = "SELECT supplyingwarehouseid FROM parttypeminimumlevel WHERE warehouseid={$this->mslWh->getId()} AND parttypeid=$partTypeId AND active=1 ORDER BY id DESC LIMIT 1";
    		$res = Dao::getSingleResultNative($sql);
    		if ($res !== false)
    		{
    			$suppWhId = $res[0];
    			$foundParts = true;
    		}
    	}

    	$query = new DaoReportQuery("PartTypeMinimumLevel");
    	$query->column('ptml.id');
    	$query->column('pt.name');
    	$query->column('ptml.quantity');
		$query->column("pta.alias", "ptalias");
    	$addJoin = " INNER JOIN parttype pt ON ptml.partTypeId=pt.id AND pt.active=1 ";
    	$addJoin .= " INNER JOIN parttypealias pta ON pt.id = pta.partTypeId AND pta.partTypeAliasTypeId=1 AND pta.active=1 ";
    	$addJoin .= " INNER JOIN contract_parttype cpt ON cpt.partTypeId = pt.id ";
    	$addJoin .= " INNER JOIN contract c ON cpt.contractId=c.id AND c.active=1 ";
    	$query->setAdditionalJoin($addJoin);

    	if($contract>0)
    		$query->where('c.id = '.$contract);

    	$query->where("ptml.active = 1");
    	$query->where('ptml.warehouseid = ? ',array($this->mslWh->getId()));

    	if(trim($partTypeId)!="")
    		$query->where('pt.id='.$partTypeId);

    	if ($this->serialisedPartsOnly->Checked)
    		$query->where('pt.serialised=1');

    	$query->where('c.id!=1'); //filter out alpha parts

    	//we are showing the supplying warehouse stuff
    	if ($this->hideSuppWh == false)
    	{
    		if ($suppWhId == null)
    		{
	    		$suppWhId = $this->suppWhList->getSelectedValue();
    		}
    		else //we've found the actual supplying warehouse above, because we're searching on part type
    		{
    			if ($suppWhId != $this->suppWhList->getSelectedValue()) //we've got to change the drop down and info message
    			{
	    			$this->suppWhId->Value = $suppWhId;
	    			$this->suppWhList->setSelectedValue($suppWhId);
	    			$this->suppWhChanged(null, null);
	    			$this->setInfoMessage($this->getInfoMessageTable() . "<br /><span style='color:{$this->orange};'>Supplying Warehouse changed to '{$this->selectedSuppWhBreadcrumbs->Value}' for search results...</span><br />");

    			}
    		}

	    	if ($suppWhId == 0)
	    	{
	    		$query->where('ptml.supplyingwarehouseid IS NULL');
	    	}
	    	else
	    	{
	    		$query->where('ptml.supplyingwarehouseid='.$suppWhId);
	    	}
    	}

    	$query->page($pageNumber,$pageSize);
    	$query->orderBy('pt.name',DaoReportQuery::ASC);
    	$results = $query->execute(true);
       	$this->totalRows = $query->TotalRows;

    	$this->loadMatchList($contract,$partTypeId);
    	$this->DataList->VirtualItemCount = $query->TotalRows;
    	$this->PaginationPanel->visible = $query->TotalRows > $pageSize;

    	if (count($results) == 0 && $foundParts) //we don't have any results, but found some above, perhaps non-serialised?
    	{
    		$serialisedText = 'SERIALISED';
    		if ($this->serialisedPartsOnly->Checked)
	    		$serialisedText = 'NON-SERIALISED';

    		$this->setInfoMessage($this->getInfoMessage() . "<br /><span style='color:{$this->orange};'>There were no results for search criteria, although there is a result for a $serialisedText part.<br />Change the 'Serialised Parts Only?' checkbox and search again.</span>");
    	}
    	return $results;
    }

    /**
     * Add MSL
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function addMSL($sender,$param)
    {
    	$suppWh = Factory::service("Warehouse")->getWarehouse($this->suppWhId->Value);
    	foreach($this->MSLDataList->items as $item)
    	{
    		$quantity = intval($item->quantity->text);
    		if($quantity > 0)
    		{
    			$id = $this->MSLDataList->DataKeys[$item->itemindex];
    			$partType = Factory::service("PartType")->get($id);

    			$piml = new PartTypeMinimumLevel();
    			$piml->setFirstIdentified(HydraDate::zeroDateTime());
    			$piml->setPartType($partType);
    			$piml->setQuantity($quantity);
    			$piml->setWarehouse($this->mslWh);

    			if ($this->hideSuppWh == false && $suppWh instanceof Warehouse)
    			{
    				$piml->setSupplyingWarehouse($suppWh);
    			}
    			Factory::service("PartType")->save($piml);
    		}
    	}
    	$contract = $this->contract->getSelectedValue();
    	$this->dataLoad();
    	$this->loadMatchList($contract);
    }

    /**
     * Update MSL
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function updateMSL($sender,$param)
    {
    	$parent = $sender->getParent()->getParent();
    	$quantityValue = intval($parent->quantity->Text);
    	$piml = Factory::service("PartTypeMinimumLevel")->get($param->CommandParameter);
    	if($quantityValue < 1)
    	{
    		$piml->setActive(0);
    	}
    	else
    	{
    		$piml->setQuantity($quantityValue);
    	}

    	Factory::service("PartTypeMinimumLevel")->save($piml);
    	$contract = $this->contract->getSelectedValue();
    	$this->dataLoad();
    	$this->loadMatchList($contract);
    }

    /**
     * Get Style
     *
     * @param unknown_type $index
     * @return unknown
     */
    public function getStyle($index)
    {
    	if($index % 2 == 0)
    		return 'DataListItem';
    	else
    		return 'DataListAlterItem';
    }

    /**
     * Find PartType
     *
     * @param unknown_type $searchString
     * @return unknown
     */
	public function findPartType($searchString)
    {
    	$partCodes = array();
    	$selectedContractId = $this->contract->getSelectedValue();

		$this->SelectedPartTypeId->Value="";
		if(strlen($searchString)>0)
		{
	   		$query = new DaoReportQuery("PartType",true);
	    	$query->column("pta.partTypeId");
	    	$query->column("CONCAT(pta.alias, ' - ', pt.name)");
	    	$query->column("pt.name");
			$addJoin = "INNER JOIN parttypealias pta ON pt.id=pta.partTypeId AND pta.active=1 AND pta.partTypeAliasTypeId=1 ";
			if (!empty($selectedContractId) && is_numeric($selectedContractId))
				$addJoin .= "INNER JOIN contract_parttype cpt ON cpt.partTypeId=pt.id AND cpt.contractId='$selectedContractId' ";
			$query->setAdditionalJoin($addJoin);
    		$query->where("pta.alias LIKE '$searchString%' OR pt.name LIKE '%$searchString%'");
    		$query->where("pt.active=1");
	    	$query->orderBy("pta.alias");
			$query->page(1,40);
		   	$result = $query->execute(false);
	    	if(sizeof($result) == 0)
	    		$partCodes= array(array("","No Data Returned."));
	    	else
	    	{
	    		foreach($result as $r)
	    		{
	    			$temp = new Area();
	    			$temp->setId($r[0]);
	    			$temp->setName($r[1]." - ".$r[2]);
	    			$partCodes[] = $temp;
	    		}
	    		$partCodes = $result;
	    	}

		}
    	if(sizeof($partCodes) == 0)
    		$partCodes = array(array("","No Data Returned."));
    	return $partCodes;
    }

    /**
     * Handle Selected PartType
     *
     * @param unknown_type $id
     */
	public function handleSelectedPartType($id)
	{
		$this->SelectedPartTypeId->Value = $id;
	}
}
?>