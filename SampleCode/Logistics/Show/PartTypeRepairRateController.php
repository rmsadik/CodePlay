<?php
/**
 * Part Type RepairRate Controller Page 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version 1.0
 */
class PartTypeRepairRateController extends CRUDPage 
{	
	/**
	 * @var querySize
	 */
	private $querySize;
	
	/**
	 * @var dataListHeader
	 */
	protected $dataListHeader;
	
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = "partRepairRate";
		$this->roleLocks = "pages_all,pages_admin,page_admin_bulkload_repair_rate";
		$this->dataListHeader = array();
		$this->allowOutPutToExcel = true;
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
    public function onLoad($param)
    {
        parent::onLoad($param);
        if(!$this->IsPostBack || $param == "reload")
        {        
        	$this->resetFields($param);
        	$this->pageTitle->setText("Part Type Repair Rate");
	        $this->dataLoad();
        }  
    }

    /**
     * Reset Fields
     *
     * @param unknown_type $params
     */
    public function resetFields($params)
    {
    	$q = new DaoReportQuery("Contract");
    	$q->where("c.active=1");
    	$q->column("c.id");
    	$q->column("c.contractName");
    	$q->orderBy("c.contractName");
    	$res = $q->execute(false);
    	$contractArr = array(array(0, "(any contract)"));
    	foreach ($res as $row)
    	{
    		$contractArr[] = array($row[0], $row[1]);
    	}
    	
    	$this->bindDropDownList($this->Contract, $contractArr);
    	 
    	$res = $this->getStates();
    	$stateArr = array(array(0, "(any state)"));
    	foreach ($res as $row)
    	{
    		$stateArr[] = array($row[0], $row[1]);
    	}
    	
    	$this->bindDropDownList($this->State, $stateArr); 
    }
    
    /**
     * Get States
     *
     * @return unknown
     */
    private function getStates() 
    {
    	$q = new DaoReportQuery("State");
    	$q->where("st.active=1");
    	$q->column("st.id");
    	$q->column("st.name");
    	$q->orderBy("st.name");
    	$res = $q->execute(false);
    	$stateArr = array();
    	foreach ($res as $row)
    	{
    		$stateArr[] = array($row[0], $row[1]);
    	}
    	return $stateArr;
    }
    
    /**
     * Print DataItem
     *
     * @param unknown_type $dataItem
     * @return unknown
     */
    public function printDataItem($dataItem)
    {
    	$html = '';
		foreach ($dataItem as $td)
		{
			$html .= "<td>$td</td>";
		}
		return $html;
    }
	
    /**
     * Search
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function search($sender,$param)
	{
		$partTypeId = $this->PartCode->getSelectedValue();
		$contractId = $this->Contract->getSelectedValue();
		$rate = $this->Rate->getText();
		$stateId = $this->State->getSelectedValue();
		$tmpArr = array("partTypeId" => $partTypeId, "contractId" => $contractId, "rate" => $rate, "stateId" => $stateId);
		$this->SearchString->Value = serialize($tmpArr);
//		$searchQueryString = $this->SearchText->Text;
//		$this->DataList->EditItemIndex = -1;
		$this->dataLoad();
	}
	   
	/**
	 * Create New Entity
	 *
	 */
	protected function createNewEntity()
    {
    }

    /**
     * Lookup Entity
     *
     * @param unknown_type $id
     */
    protected function lookupEntity($id)
    {
    }
    
    /**
     * Get All Of Entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$res = $this->searchEntity("", $focusObject, $pageNumber, $pageSize);
    	$this->querySize = count($res);
    	return $res;
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
    protected function searchEntity($searchString, &$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$searchStringArr = unserialize($searchString);
    	$allStates = $this->getStates();
    	$this->dataListHeader = array("Part Code", "Part Name");
    	$q = new DaoReportQuery("PartType");
    	$addJoin = '';
    	$addJoin .= "INNER JOIN parttyperepairrate ptrr ON ptrr.partTypeId=pt.id AND ptrr.active=1 ";
    	$addJoin .= "LEFT JOIN parttypealias pta ON pt.id=pta.partTypeId AND pta.partTypeAliasTypeId=1 AND pta.active=1 ";
    	$q->column("pta.alias");
    	$q->column("pt.name");
    	foreach ($allStates as $stRow)
    	{
    		list($stateId, $stateName) = $stRow;
    		$addJoin .= "LEFT JOIN parttyperepairrate ptrr$stateId ON ptrr$stateId.partTypeId=pt.id AND ptrr$stateId.active=1 AND ptrr$stateId.stateId=$stateId ";
    		$q->column("ptrr$stateId.repairPercent");
    		$this->dataListHeader[] = $stateName;
    	}
    	$q->where("pt.active=1");
    	$paramArr = array();
    	if (!empty($searchStringArr) && !empty($searchStringArr['partTypeId']))
    	{
    		$paramArr[] = $searchStringArr['partTypeId'];
    		$q->where("pt.id = ?", $paramArr);
    	}
    	if (!empty($searchStringArr) && !empty($searchStringArr['stateId']))
    	{
    		$paramArr[] = $searchStringArr['stateId'];
    		$q->where("ptrr.stateId = ?", $paramArr);
    	}
    	if (!empty($searchStringArr) && !empty($searchStringArr['rate']))
    	{
    		$paramArr[] = $searchStringArr['rate'];
    		$q->where("ptrr.repairPercent = ?", $paramArr);
    	}
    	if (!empty($searchStringArr) && !empty($searchStringArr['contractId']))
    	{
    		$paramArr[] = $searchStringArr['contractId'];
			$addJoin .= "INNER JOIN contract_parttype cpt ON ptrr.partTypeId=cpt.partTypeId ";
    		$q->where("cpt.contractId = ?", $paramArr);
    	}
		$q->setAdditionalJoin($addJoin);
		$q->orderBy("pta.alias");
    	$res = $q->execute(false);
    	
    	$this->querySize = count($res);
    	if ($this->querySize == 0)
    	{
    		$this->setInfoMessage("No data to be displayed.");
    	}
    	return $res;
    }
    
    /**
     * Output To Excel
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function outputToExcel($sender, $param)
    {
    	$partTypeId = $this->PartCode->getSelectedValue();
		$contractId = $this->Contract->getSelectedValue();
		$rate = $this->Rate->getText();
		$stateId = $this->State->getSelectedValue();
		$tmpArr = array("partTypeId" => $partTypeId, "contractId" => $contractId, "rate" => $rate, "stateId" => $stateId);
		$this->SearchString->Value = serialize($tmpArr);
		$result = $this->searchEntity($this->SearchString->Value);

		$columnHeaderArray = $this->dataListHeader;
		// add a new column
		$columnHeaderArray[] = 'x';
    	$totalSize = sizeof($result);
    	
    	if($totalSize <= 0 )
    	{
    		$this->setErrorMessage("Can't Output To Excel, as There is No Data.");
    	}
    	else
    	{
	    	$allData = $result;
	    	for ($i=0; $i<count($allData); $i++)
	    	{
	    		$allData[$i][] = 'x';
	    	}
    	}

    	if(isset($allData))
    	{
	    	$columnDataArray = array();
			foreach ($allData as $row)
				array_push($columnDataArray, $row);
	    	
			$fileName = "Part Type Repair Rate";
		    $this->toExcel($fileName, "", "", $columnHeaderArray, $columnDataArray);
    	}    	
    }
    
    /**
     * How Big Was that Query
     *
     * @return unknown
     */
    protected function howBigWasThatQuery()
    {
    	return $this->querySize;
    }

}

?>