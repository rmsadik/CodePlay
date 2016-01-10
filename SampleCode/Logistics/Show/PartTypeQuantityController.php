<?php
//ini_set("max_execution_time", 60);
/**
 * Part Type Quantity Serach Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class PartTypeQuantityController extends HydraPage
{
	/**
	 * @var menuContext
	 */
	public $menuContext;

	/**
	 * @var displayOptionArray
	 */
	private $displayOptionArray;

	/**
	 * @const SHOW_ZEROS
	 */
	const SHOW_ZEROS = 0;

	/**
	 * @const HIDE_ZEROS
	 */
	const HIDE_ZEROS = 1;

	/**
	 * @const SHOW_FLAT
	 */
	const SHOW_FLAT = 2;

	/**
	 * @const SHOW_FACILITY
	 */
	const SHOW_FACILITY = 3;

	/**
	 * @const SHOW_TN
	 */
	const SHOW_TN = 4;



	/**
	 * @var idsPositionKitParts
	 */
	private $idsPositionKitParts;

	/**
	 * @var finalStatusArray
	 */
	private $finalStatusArray;

	/**
	 * @var statusWiseCountArray
	 */
	private $statusWiseCountArray;

	/**
	 * @Array exceldata
	 */
	private $exceldata = array();

	/**
	 * @var $workBook
	 */
	private $workBook;

	/**
	 * On Pre Initialization
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
		$str = explode('/',$_SERVER['REQUEST_URI']);
		if ($str[1] == 'staging')
		{
			$this->getPage()->setMasterClass("Application.layouts.StagingLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_partTypeQuantity,menu_staging";
			$this->menuContext = 'staging/partTypeQuantity';
		}
		else
		{
			$this->getPage()->setMasterClass("Application.layouts.LogisticsLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_partTypeQuantity";
			$this->menuContext = 'partTypeQuantity';
		}
	}

	/**
	 * Construct
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		//$this->menuContext = 'partTypeQuantity';
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_partTypeQuantity";

		$this->displayOptionArray = array(array(PartTypeQuantityController::SHOW_ZEROS, "Include Locations with Zero Quantity"),
										  array(PartTypeQuantityController::HIDE_ZEROS, "Exclude Locations with Zero Quantity"),
										  array(PartTypeQuantityController::SHOW_FLAT, "Flat part list with selected warehouse downwards"),
										  array(PartTypeQuantityController::SHOW_FACILITY, "Facility part list by status (Excluding Transit/Dispatch Notes)"),
										  array(PartTypeQuantityController::SHOW_TN, "Facility part list by status (Unreconciled Transit Notes/Parts)"));

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
	        if(isset($this->Request['partType']) && intval($this->Request['partType']) != 0)
	        	$this->partType->loadPartTypeId($this->Request['partType']);

	        if(isset($this->Request['includeKits']) && intval($this->Request['includeKits']) != 0)
	        	$this->searchKit->setChecked(true);

	   		$this->bindDropDownList($this->displayOption, $this->displayOptionArray);
        	if(isset($this->Request['displayOption']) && is_numeric($this->Request['displayOption']))
	        	$this->displayOption->setSelectedValue($this->Request['displayOption']);
	        else
        		$this->displayOption->setSelectedValue(PartTypeQuantityController::HIDE_ZEROS);


	        if(isset($this->Request['warehouse']) && intval($this->Request['warehouse']) != 0)
	        {
	        	$warehouse = Factory::service("Warehouse")->getWarehouse($this->Request['warehouse']);
	        	$warehouseCrumbs = Factory::service("Warehouse")->getWarehouseIdBreadCrumbs($warehouse);
	        	$this->warehouseid->value = $warehouseCrumbs;
	        }

	        if(isset($this->Request['contract']) && intval($this->Request['contract']) != 0)
	        {
	        	$contract = Factory::service("Contract")->get($this->Request['contract']);
	        	$this->searchContract->setSelectedValue($contract);
	        }

	        if(isset($this->Request['owner']) && intval($this->Request['owner']) != 0)
	        {
	        	$owner = Factory::service("Client")->get($this->Request['owner']);
	        	$this->searchOwner->setSelectedValue($owner);
	        }

        	$status = new PartInstanceStatus();
        	$status->setId(-1);
        	$status->setName("ALL");
        	$statuses = Factory::service("PartInstanceStatus")->findAll();
        	array_unshift($statuses,$status);

        	$this->bindDropDownList($this->searchStatus,$statuses);
	        $this->searchStatus->setSelectedValue("-1");
	        if(isset($this->Request['status']) && trim($this->Request['status'])!="" && strtolower(trim($this->Request['status']))!="null")
	        {
	        	$statusIds = explode(",",$this->Request['status']);
	        	$this->searchStatus->setSelectedValues($statusIds);
	   		}

	   		$this->_bindCountryList();

			$this->search(null,null);
			$this->partType->focus();
		}
// 		Debug::inspect($this->getPage());
    }

    private function _bindCountryList()
    {
    	$countryList = array();
    	$countryList[] = array('id' => -1, 'name' => 'ALL');

    	//find all countries for warehouses with parts allow
    	$sql = "SELECT c.id, c.name FROM warehouse w
				INNER JOIN state s ON s.id=w.stateid
				INNER JOIN country c ON c.id=s.countryid
				WHERE w.active=1 AND w.ignorestockcount!=1
				GROUP BY c.id
    			ORDER BY c.name";
    	$res = Dao::getResultsNative($sql, array(), PDO::FETCH_ASSOC);
    	foreach ($res as $r)
    	{
    		$countryList[] = array('id' => $r['id'], 'name' => $r['name']);
    	}
        $this->bindDropDownList($this->searchCountry, $countryList);
        $this->searchCountry->setSelectedValue("-1");

        if (isset($this->Request['countryIds']) && trim($this->Request['countryIds'])!="" && strtolower(trim($this->Request['countryIds']))!="null")
        {
        	$ids = explode(",",$this->Request['countryIds']);
        	$this->searchCountry->setSelectedValues($ids);
        }
    }

    /**
     * Set Kit Parts Array
     *
     * @param unknown_type $partTypeId
     * @param unknown_type $warehousePosition
     */
    private function setKitPartsArray($partTypeId,$warehousePosition){
    	$this->idsPositionKitParts = $this->getPartInstanceIdsOfKitWhereRootParentIsInWarehouseFromPartType($partTypeId,$warehousePosition);
    	$_SESSION['PartTypeQuantity']['WarehousePosition'] = $warehousePosition;
    	$_SESSION['PartTypeQuantity']['IdsPositionKitParts'] = serialize($this->idsPositionKitParts);
    	$_SESSION['PartTypeQuantity']['PartTypeId'] = $partTypeId;
    }

    /**
     * Delimit Ids
     *
     * @param unknown_type $delimiter
     * @param unknown_type $idsPositionKitParts
     * @return unknown
     */
     private function delimitIds($delimiter,$idsPositionKitParts)
     {
     	$ret = "";
     	if(count($idsPositionKitParts) > 0)
     	{
			foreach ($idsPositionKitParts as $rows)
			{
     			$ret .= $rows['id'] . $delimiter;
			}
			$ret = substr($ret, 0, 0-strlen($delimiter));
     	}
     	return $ret;
     }

    /**
     * Search
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
    public function search($sender,$param)
    {
    	$this->finalStatusArray = array();
		$this->statusWiseCountArray = array();
		$this->excelString->Value = "";
    	$vars = $this->getSelectionVars();
    	$partTypeId = $vars["partTypeId"];
    	$warehouseId = $vars["warehouseId"];
    	$includeKits = $vars["includeKits"];

    	$exceldata = array();
    	$this->exceldata["contract"]= trim($this->searchContract->Text);
    	$this->exceldata["parttype"]= trim($this->partType->Text);
    	$this->exceldata["ownerclient"]= trim($this->searchOwner->Text);
    	$this->exceldata["displayoption"]= trim($this->displayOption->SelectedItem->Text);
    	$this->exceldata["searchkit"]= ($this->searchKit->getChecked()?"Include Parts in Kits":"");

    	//if this is a first time load or there is nothing selected
    	if($partTypeId=="")
    	{
    		if($sender!=null)
	    		$this->setErrorMessage("Part type information should be provided at least!");
    		return;
    	}

    	$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId);
    	if(!$warehouse instanceof Warehouse)
    	{
    		$this->setErrorMessage("Invalid warehouse selected!");
    		return;
    	}

    	$this->idsPositionKitParts = array();
    	if($includeKits)
    	{
    		$found = false;
    		if(isset($_SESSION['PartTypeQuantity']['PartTypeId']) and isset($_SESSION['PartTypeQuantity']['WarehousePosition']) and $_SESSION['PartTypeQuantity']['IdsPositionKitParts'])
    		{
    			if($_SESSION['PartTypeQuantity']['PartTypeId']==$partTypeId)
    			{
	    			if(preg_match("/^" .   $_SESSION['PartTypeQuantity']['WarehousePosition'] . "/", $warehouse->getPosition())==1)
	    			{
	    				//The warehouse is a subitem of original position so we dont need to reload kit parts
	    				if(isset($_SESSION['PartTypeQuantity']['IdsPositionKitParts']))
	    				{
	    					$found = true;
	    					$this->idsPositionKitParts = unserialize($_SESSION['PartTypeQuantity']['IdsPositionKitParts']);
	    					$this->idsPositionKitParts = $this->getPartIdsInWarehousePositionInPartIdsArray($this->idsPositionKitParts,$warehouse->getPosition());
	    				}
	    			}
    			}
    		}
    		if(!$found)
    		{
    			$this->setKitPartsArray($partTypeId,$warehouse->getPosition());
    		}
    	}

//     	if ($warehouse->getIgnoreStockCount())
//     	{`
//     		$this->locationListPanel->getControls()->add("<h3 style='color:green'>Selected warehouse is set to Ignore From Stock Count</h3>");
//     		return false;
//     	}

    	$warehousecrumb=Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse);
		$this->exceldata["locationunder"]= $warehousecrumb;

    	if($vars["displayOption"]=="3")
    	{
  		 	$facilityList = $this->getFacilityList($warehouse, $vars["displayOption"], $partTypeId, $vars["ownerId"], $vars["contractId"], $vars["statusIds"], $vars["stateIds"]);
	    	if($facilityList == "" )
	    	{
		    	$this->facilityListPanel->getControls()->add("<h3 style='color:green'>No Facility Data Found!</h3>");
	    	}
	    	else
	    	{
		    	$this->facilityListPanel->getControls()->add($facilityList);
	    	}
    	}
    	elseif($vars["displayOption"]=="4")
    	{
  		 	$TNList = $this->getTNList($warehouse,$vars["displayOption"],$partTypeId,$vars["ownerId"],$vars["contractId"],$vars["statusIds"], $vars["stateIds"]);
	    	if($TNList == "" )
	    	{
		    	$this->TNListPanel->getControls()->add("<h3 style='color:green'>No Transit Note Data Found!</h3>");

	    	}
	    	else
	    	{
		    	$this->TNListPanel->getControls()->add($TNList);
	    	}
    	}
    	else
    	{
    		$locationList = $this->getLocationList($warehouse,$vars["displayOption"],$partTypeId,$vars["ownerId"],$vars["contractId"],$vars["statusIds"], $vars["stateIds"]);
	    	$partsList = $this->getPartsList($warehouse,$vars["displayOption"],$partTypeId,$vars["ownerId"],$vars["contractId"],$vars["statusIds"], $vars["stateIds"]);
	    	if($partsList=="" && $locationList=="" )
	    	{
		    	$this->locationListPanel->getControls()->add("");
	    		$this->partsListPanel->getControls()->add("<h3 style='color:green'>No Data Found!</h3>");
	    	}
	    	else
	    	{
	    		$this->locationListPanel->getControls()->add($locationList);
		    	$this->partsListPanel->getControls()->add($partsList);
	    	}
    	}

    	if(isset($this->exceldata) && array_key_exists("body",$this->exceldata))
    	{
    		$this->excelString->Value = serialize($this->exceldata);
    	}

    }

	/**
	 * Get Parts Status Array
	 *
	 * @param unknown_type $statusIds
	 * @return unknown
	 */
    private function getPartStatusArray($statusIds)
    {
    	//Get Status ids and names
		$statusarray = array();

   	 	if(count($statusIds)>0)
    	{
    		$statusstr = implode(",",$statusIds);
    		$result = Factory::service("PartInstanceStatus")->findByCriteria("id in (".$statusstr.")",array());
    	}
    	else
    	{
    		$result = Factory::service("PartInstanceStatus")->findAll();
    	}

    	foreach($result as $rows)
    	{
    		$statusarray[] =  array("id" => $rows->getId(), "name" => $rows->getName());
    	}
    	return $statusarray;
    }

    /**
     * SetUp PartInstance Counts
     *
     * @param unknown_type $i
     * @param unknown_type $n
     * @param unknown_type $t
     * @param unknown_type $r
     * @param unknown_type $partTypeId
     * @param unknown_type $warehouseIds
     * @param unknown_type $ownerId
     * @param unknown_type $contractId
     * @param unknown_type $statusarray
     * @param unknown_type $idsPositionKitParts
     */
    private function setupPartInstanceCounts($i, &$n, &$t, &$r, $partTypeId,$warehouseIds,$ownerId,$contractId,$statusarray,$idsPositionKitParts, $stateIds)
    {
   		$partInstanceCount = Factory::service("PartInstance")->getPartStatusCountByPartTypeAndWarehouse($partTypeId, $warehouseIds, $ownerId, $contractId, $statusarray, array(), '', false, $stateIds);
   		if(count($this->idsPositionKitParts)>0)
		{
    		$partInstanceCountKits = Factory::service("PartInstance")->getPartStatusCountByPartTypeAndWarehouse($partTypeId, $warehouseIds, $ownerId, $contractId, $statusarray, $idsPositionKitParts, '', false, $stateIds);
			if(!$partInstanceCount)
			{
				$partInstanceCount = $partInstanceCountKits;
			}
			else
			{
				try{
    				$partInstanceCount = array_merge($partInstanceCount,$partInstanceCountKits);
				}catch(Exception $e){
					//catch if one is not an array
				}
			}
		}

	   	if($partInstanceCount)
		{
		    if(count($partInstanceCount) > 0)
		    {
		    	foreach($partInstanceCount as $row)
		    	{

		    		$j = $row["partInstanceStatusId"];
		    		if($j)
		    		{
		    			$j--;
		    			if($row["qty"])
		    			{
		    				$n[$i][$j] += $row["qty"];
		    				$r[$i] += $row["qty"];
			    			$t[$j] += $row["qty"];
		    			}
		    		}
		    	}
		    }
		}
    }

    /**
     * Get TN List
     *
     * @param Warehouse $selectedWarehouse
     * @param unknown_type $displayOption
     * @param unknown_type $partTypeId
     * @param unknown_type $ownerId
     * @param unknown_type $contractId
     * @param array $statusIds
     * @return unknown
     */
	private function getTNList(Warehouse $selectedWarehouse,$displayOption=1,$partTypeId,$ownerId="",$contractId="", array $statusIds=array(), array $stateIds = array())
    {
    	if($displayOption != PartTypeQuantityController::SHOW_TN ) return "";
    	if($selectedWarehouse->getId()== "3" || $selectedWarehouse->getId()=="1") return "<h3 style='color:green'>Too many results. Please select a child node!</h3>";

    	$statusarray = $this->getPartStatusArray($statusIds);
    	$cols=count($statusarray);



    	//Get Warehouses
		$warehouseAliasList = array(WarehouseService::CODENAME_UNRECONCILEDPARTS,WarehouseService::CODENAME_UNRECONCILEDTRANSITNOTEPARTS);
		$warehouses = Factory::service("Warehouse")->getWarehousesByPositionAndAlias($selectedWarehouse->getPosition(), $warehouseAliasList);

    	if(count($warehouses)== 0) return "";
    	$rows=(count($warehouses));

    	//Caculating
    	$n= array();
    	$t= array();
    	$r= array();

    	for($i=0; $i< $rows; $i++)
		 {
    		$r[$i]=0;
		 	for ($j=0; $j< $cols; $j++)
		    {
			   	$n[$i][$j]=0;
			}
    	}
  		for ($j=0; $j< $cols; $j++)
		{
    		$t[$j]=0;
		}

		$a=0;
   		for($i=0; $i< $rows; $i++)
	    {
   		 	if($warehouses[$i]->getWarehouseAlias()== WarehouseService::CODENAME_UNRECONCILEDTRANSITNOTEPARTS)
   		 	{
				$this->setupPartInstanceCounts($i, $n, $t, $r, $partTypeId,array($warehouses[$i]->getId()),$ownerId,$contractId,explode(",",$this->delimitIds(",",$statusarray)),explode(",",$this->delimitIds(",",$this->idsPositionKitParts)), $stateIds);
   		 	}
   		 	else
   		 	{

   		 		$wstr='';
   		 		$results = Factory::service("Warehouse")->getWarehouseSiblingNameArray($warehouses[$i]->getId(), '', 'id');
	    		if(count($results)>0)
	    		{
			  		$w= array();

		    		foreach($results as $warehouseids)
		    		{
		    			$w[] = $warehouseids;
		    		}

		    		$this->setupPartInstanceCounts($i, $n, $t, $r, $partTypeId,$w,$ownerId,$contractId,explode(",",$this->delimitIds(",",$statusarray)),explode(",",$this->delimitIds(",",$this->idsPositionKitParts)), $stateIds);
	    		}
   		 	}

	    	$a+=$r[$i];
    	}


    	///////Display
    	if ($a == 0) return "";
    	$head = array();
	    	$html="<h3>Facility part list by status (Unreconciled Transit Notes/Parts) </h3>";
		    $html.="<table  class='DataList'>";
	    	$html.="<thead>";
		    	$html.="<tr style='height: 20px'>";
	    		$html.="<th  style='font-weight:bold'>Location</th>";
	    		$head[]="Location";
	    		for($j=0; $j < $cols; $j++)
	    		{
	    			if($t[$j]> 0)
	    			{
	    				$html.="<th  style='font-weight:bold'>".$statusarray[$j]["name"]."</th>";
	    				$head[]= $statusarray[$j]["name"] ;
	    			}
	    		}
		    	$html.="</tr>";
	    	$html.="</thead>";

	    	$html.="<tbody>";

	    	$d=0; $body= array();
    		for($i=0; $i< $rows; $i++)
    		{
		    	if($r[$i]> 0)
	    		{

	    			$html.="<tr class='".($d%2==0 ? "DataListItem" : "DataListAlterItem")."'>";
		    		$path = Factory::service("Warehouse")->getWarehouseBreadCrumbs(Factory::service("Warehouse")->getWarehouse($warehouses[$i]->getId()),true,"/");
			   		$html.="<td style='text-align:left; padding-right:5px'>"."<a href='".$this->getUrlToDrillDown($warehouses[$i]->getId())."'>".$path."</a></td>";
			   		$body[$d][]=$path;
					for($j=0; $j< $cols; $j++)
					{
						if($t[$j]> 0)
		    			{
							$s=$j+1; $sid=array("$s");
		    				$html.="<td style='text-align:right; padding-right:5px'>"."<a href='".$this->getUrlToDrillDown($warehouses[$i]->getId(),$sid)."'>".$n[$i][$j]."</a></td>";
		    				$body[$d][]= $n[$i][$j];
		    			}
					}
		    		$html.="</tr>";
		    		$d++;
		    	}
    		}

    		$foot = array();
	    	$html.="</tbody>";
	    	$html.="<tfoot>";
				$html.="<tr>";
					$html.="<th  style='font-weight:bold'>Total</th>";
					$foot[]="Total";
				    for($j=0; $j < $cols; $j++)
		    		{
		    			if($t[$j]> 0)
		    			{
		    				$html.="<th  style='font-weight:bold; text-align:right;'>".$t[$j]."</th>";
		    				$foot[]=$t[$j];
		    			}
		    		}
			$html.="</tr>";
			$html.="</tfoot>";
	    	$html.="</table>";

	    	$this->exceldata["head"]=$head;
	    	$this->exceldata["body"]=$body;
	    	$this->exceldata["foot"]=$foot;

    	return $html;
    }

    /**
     * Get PartInstance Ids Of Kit Where Root Parent Is In Warehouse From PartType
     *
     * @param unknown_type $partTypeId
     * @param unknown_type $warehousePosition
     * @return unknown
     */
    private function getPartInstanceIdsOfKitWhereRootParentIsInWarehouseFromPartType($partTypeId,$warehousePosition){

    	$index = 0;
    	$partInstances = array();

    	$sql = "select pi.id,
    	(select w3.position from partinstance pi3 inner join warehouse w3 on w3.id = pi3.warehouseId and w3.ignorestockcount=0 where pi3.position=1 and pi3.id = pi.dbTree limit 1) as parentWarehousePosition
    	from partinstance pi
		where pi.active=1 and NOT ISNULL(pi.parentId) and
		NOT ISNULL((select pi2.kitTypeId from partinstance pi2  where pi2.position=1 and pi2.id = pi.dbTree limit 1))
		and pi.partTypeId= " . $partTypeId;
    	$result = Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC);
  		if($result)
  		{
	   		if(count($result) > 0)
	   		{
	   			foreach($result as $row){
			    	$partInstance = Factory::service("PartInstance")->getPartInstance($row["id"]);
			    	if($partInstance instanceof PartInstance){
						if(preg_match("/^" .  $warehousePosition  . "/", $row["parentWarehousePosition"])==1){
							//Parts parent is with warehouse
							$partInstances[$index]['id'] = $partInstance->getId();
							$partInstances[$index]['pos'] = $row["parentWarehousePosition"];
							$index++;
						}
			    	}
	   			}
	   		}
  		}
    	return $partInstances;
    }

    /**
     * Get Facility List
     *
     * @param Warehouse $selectedWarehouse
     * @param unknown_type $displayOption
     * @param unknown_type $partTypeId
     * @param unknown_type $ownerId
     * @param unknown_type $contractId
     * @param array $statusIds
     * @return unknown
     */
	private function getFacilityList(Warehouse $selectedWarehouse,$displayOption=1,$partTypeId,$ownerId="",$contractId="",array $statusIds=array(), array $stateIds = array())
    {
		if($displayOption != PartTypeQuantityController::SHOW_FACILITY ) return "";
		if($selectedWarehouse->getId()== "3" || $selectedWarehouse->getId()=="1") return "<h3 style='color:green'>Too many results. Please select a child node!</h3>";

		$statusarray = $this->getPartStatusArray($statusIds);
    	$cols = count($statusarray);

    	//Get Facilities
    	$facilities = array();
    	$results = Factory::service("Warehouse")->searchWarehousesWhereFacilityIsNotNull('',$selectedWarehouse->getPosition());
    	if($results)
    	{
    		foreach($results as $rows)
    		{
    			$facilities[] = array("id"=>$rows->getId(),"name"=>$rows->getName(),"position"=>$rows->getPosition());
    		}
    	}

    	if(count($facilities)== 0) return "";
    	$rows=(count($facilities));

    	$total = 0;
    	//Caculating
    	$n= array();
    	$t= array();
    	$r= array();
    	$partInstanceCount = array();

    	for($i=0; $i< $rows; $i++)
		 {
    		$r[$i]=0;
		 	for ($j=0; $j< $cols; $j++)
		    {
			   	$n[$i][$j]=0;
			}
    	}
  		for ($j=0; $j< $cols; $j++)
		{
    		$t[$j]=0;
		}

		$stateSql = '';
		if (!empty($stateIds))
		{
			$stateSql = ' AND stateid IN (' . implode(',', $stateIds) . ')';
		}

		$a=0;
	   	for($i=0; $i< $rows; $i++)
		{
			$arg = " active=1 and parts_allow=1 and ignorestockcount!=1 and warehouseCategoryId <> " . WarehouseCategory::ID_TRANSITNOTE . " and position like '".$facilities[$i]["position"]."%'" . $stateSql;
			$warehouseids = Factory::service("Warehouse")->findByCriteria($arg,array());
	    	if(count($warehouseids) > 0)
	    	{
			  	$w= array();
			  	$wstr='';
		    	for($k=0; $k< count($warehouseids); $k++)
		    	{
		    		$w[$k]=$warehouseids[$k]->getId();
		    	}
				$this->setupPartInstanceCounts($i, $n, $t, $r, $partTypeId,$w,$ownerId,$contractId,explode(",",$this->delimitIds(",",$statusarray)),explode(",",$this->delimitIds(",",$this->idsPositionKitParts)), $stateIds);
	    	}
	    	$a += $r[$i];
    	}

    	if ($a == 0) return "";
    	$head = array();
    	//Display
    	$html="<h3>List Of Facilities (Excluding Transit/Dispatch Notes)</h3>";
	    	$html.="<table  class='DataList'>";
		    	$html.="<thead>";
			    	$html.="<tr style='height: 20px'>";
		    		$html.="<th  style='font-weight:bold'>Location</th>";
		    		$head[]="Location";
		    		for($j=0; $j < $cols; $j++)
		    		{
		    			if($t[$j]> 0)
		    			{
		    				$html.="<th  style='font-weight:bold'>".$statusarray[$j]["name"]."</th>";
		    				$head[]= $statusarray[$j]["name"];
		    			}
		    		}
			    	$html.="</tr>";
		    	$html.="</thead>";
		    	$html.="<tbody>";

		    	$d=0; $body= array();
	    		for($i=0; $i< $rows; $i++)
	    		{
			    	if($r[$i]> 0)
		    		{
		    			$html.="<tr class='".($d%2==0 ? "DataListItem" : "DataListAlterItem")."'>";

				   		$html.="<td style='text-align:left; padding-right:5px'>"."<a href='".$this->getUrlToDrillDown($facilities[$i]["id"])."'>".$facilities[$i]["name"]."</a></td>";
				   		$body[$d][]=$facilities[$i]["name"];
						for($j=0; $j< $cols; $j++)
						{
							if($t[$j]> 0)
			    			{
								$s=$j+1; $sid=array("$s");
			    				$html.="<td style='text-align:right; padding-right:5px'>"."<a href='".$this->getUrlToDrillDown($facilities[$i]["id"],$sid)."'>".$n[$i][$j]."</a></td>";
			    				$body[$d][]=$n[$i][$j];
			    			}
						}

			    		$html.="</tr>";
			    		$d++;
			    	}
	    		}
	    		$foot = array();
		    	$html.="</tbody>";
		    	$html.="<tfoot>";
					$html.="<tr>";
						$html.="<th  style='font-weight:bold'>Total</th>";
						$foot[]="Total";
					    for($j=0; $j < $cols; $j++)
			    		{
			    			if($t[$j]> 0)
			    			{
			    				$html.="<th  style='font-weight:bold; text-align:right;'>".$t[$j]."</th>";
			    				$foot[]=$t[$j];
			    			}
			    		}
					$html.="</tr>";
			$html.="</tfoot>";
	    	$html.="</table>";

	    	$this->exceldata["head"]=$head;
	    	$this->exceldata["body"]=$body;
	    	$this->exceldata["foot"]=$foot;

    	return $html;
    }

    /**
     * Get Parts List
     *
     * @param Warehouse $selectedWarehouse
     * @param unknown_type $displayOption
     * @param unknown_type $partTypeId
     * @param unknown_type $ownerId
     * @param unknown_type $contractId
     * @param array $statusIds
     * @return unknown
     */
    private function getPartsList(Warehouse $selectedWarehouse,$displayOption=1,$partTypeId,$ownerId="",$contractId="",array $statusIds=array(), array $stateIds = array())
    {
    	if(($displayOption==PartTypeQuantityController::SHOW_TN) || ($displayOption==PartTypeQuantityController::SHOW_FACILITY)) return "";
    	$head = array();
    	$html="<h3>List Of Parts</h3>";
	    	$html.="<table width='100%' class='DataList'>";
		    	$html.="<thead>";
			    	$html.="<tr style='height: 20px'>";
				    	$html.=$this->getSortableHeader("qty", "Qty" , "pi.quantity", "5%", "column1");$head[]="Qty";
				    	$html.=$this->getSortableHeader("status", "Status", "pis.name", "12%", "column2");$head[]="Status";
				    	$html.=$this->getSortableHeader("barcode", "Barcode/Serial No", "pia.alias", "18%", "column3");$head[]="Barcode/Serial No";
				    	$html.=$this->getSortableHeader("location", "Location", "ware.position", "65%", "column4");$head[]="Location";
			    	$html.="</tr>";
		    	$html.="</thead>";
		    	$html.="<tbody>";

		    		$totalCount=0;
		    		$orderField = $this->sortingField->Value;
	        		$orderDirection = $this->sortingOrder->Value;
	        		$partInstanceArr = Factory::service("PartInstance")->getPartsListDetailsForPartTypeQuantityPage($partTypeId, $ownerId, $contractId, $statusIds, array(), true, $selectedWarehouse, $displayOption, $orderField, $orderDirection, $stateIds);

	        		if(count($this->idsPositionKitParts)>0)
					{
			    		$partInstancesKits = Factory::service("PartInstance")->getPartsListDetailsForPartTypeQuantityPage($partTypeId, $ownerId, $contractId, $statusIds, explode(",",$this->delimitIds(",",$this->idsPositionKitParts)), false, $selectedWarehouse, $displayOption, $orderField, $orderDirection, $stateIds);
			    		if(!$partInstanceArr)
						{
							$partInstanceArr = $partInstancesKits;
						}
						else
						{
							try{
			    				$partInstanceArr = array_merge($partInstanceArr,$partInstancesKits);
							}catch(Exception $e){
								//catch if one is not an array
							}
						}
					}

		    		if(count($partInstanceArr)==0)
		    			return "";
		    		$rowNo=0; $body = array();

		    		foreach($partInstanceArr as $row)
		    		{
		    			$row['link'] = PartInstanceLogic::getPartInstanceDetailsLink($row['barcode']);

				    	$html.="<tr class='".(($rowNo++)%2==0 ? "DataListItem" : "DataListAlterItem")."'>";
					    	$html.="<td style='text-align:right; padding-right:5px'>{$row["quantity"]}</td>";
					    	$body[$rowNo][]=$row["quantity"];
					    	$html.="<td>{$row["status"]}</td>";
					    	$body[$rowNo][]=$row["status"];

					    	if ($row["warehouseId"] != $row["warehouseId2"])
					    	{
					    		$html .= "<td>" . $row["link"]." (Part in kit) "."</td>";
					    		$body[$rowNo][] = $row["barcode"]." (Part in kit) ";
					    	}
					    	else
					    	{
					    		if(strlen(trim($row["barcode"]))>0 && !is_null($row["link"]))
					    			$barcode = $row["link"];
					    		else // if de-activated alias
					    		{
					    			$pt = Factory::service('PartType')->getPartType($partTypeId);
					    			$deactivatedBarcode='';
					    			if($pt instanceof PartType)
					    			{
					    				if((int)$pt->getSerialised() == 1)//if serialsed
					    				{
					    					$aliasType = PartInstanceAliasType::ID_SERIAL_NO;
					    					$deactivatedBarcode = Factory::service('PartInstance')->searchPartInstanceAliaseByPartInstanceAndAliasType($row["id"], $aliasType,true);
					    					if(count($deactivatedBarcode)>0 && $deactivatedBarcode[0] instanceof PartInstanceAlias)
					    						$deactivatedBarcode = $deactivatedBarcode[0]->getAlias();
					    				}
					    				else //if non-serialized.
					    				{
					    					$aliasType = PartTypeAliasType::ID_BP;
					    					$bp = Factory::service('PartTypeAlias')->findByCriteria('pta.parttypeid=? and pta.parttypealiastypeid=? and pta.active=0',array($pt->getId(),$aliasType),true);
					    					if(count($bp)>0 && $bp[0] instanceof PartTypeAlias)
					    						$deactivatedBarcode = $bp[0]->getAlias();
					    				}
					    			}
					    			$deactivatedBarcode = ((!empty($deactivatedBarcode) && $deactivatedBarcode>'') ?$deactivatedBarcode."<br/> (De-active)":'');
						    		$barcode = "<b><span style='color:red;'>".$deactivatedBarcode."</span></b>";
					    		}

					    		$html .= "<td>". $barcode."</td>";
					    		$body[$rowNo][] = $row["barcode"];
					    	}


					    	if($displayOption!=PartTypeQuantityController::SHOW_FLAT)
					    	{
					    		$html.="<td>{$selectedWarehouse}</td>";
					    		$body[$rowNo][]=$selectedWarehouse;
					    	}
					    	else
					    	{
					    		$path = Factory::service("Warehouse")->getWarehouseBreadCrumbs(Factory::service("Warehouse")->getWarehouse($row["warehouseId"]),true,"/");
					    		$html.="<td><a href='".$this->getUrlToDrillDown($row["warehouseId"])."'>$path</a></td>";
					    		$body[$rowNo][]=$path;
					    	}

				    	$html.="</tr>";
				    	$totalCount += $row["quantity"];
		    		}
	    		$foot = array();
		    	$html.="</tbody>";
		    	$html.="<tfoot>";
					$html.="<tr>";
						$html.="<th colspan='4' style='font-weight: bold; text-align: center;'>";
							$html.="Total $totalCount Parts";
						$html.="</th>";
						$foot[]="Total $totalCount Parts";
					$html.="</tr>";
			$html.="</tfoot>";
	    	$html.="</table>";
	    	$html.="<script type='text/javascript'>showOrder();</script>";
        	$html.="<br />";

	    	$this->exceldata["head"]=$head;
	    	$this->exceldata["body"]=$body;
	    	$this->exceldata["foot"]=$foot;
    	return $html;
    }

    /**
     * Get Location List
     *
     * @param Warehouse $selectedWarehouse
     * @param unknown_type $displayOption
     * @param unknown_type $partTypeId
     * @param unknown_type $ownerId
     * @param unknown_type $contractId
     * @param array $statusIds
     * @return unknown
     */
    private function getLocationList(Warehouse $selectedWarehouse,$displayOption=1,$partTypeId,$ownerId="",$contractId="",array $statusIds=array(), array $stateIds = array()) // FUNCTION CHANGED TO SHOW STATUS IN THE VIEW
    {
    	if(($displayOption==PartTypeQuantityController::SHOW_FLAT) || ($displayOption==PartTypeQuantityController::SHOW_FACILITY)|| ($displayOption==PartTypeQuantityController::SHOW_TN)) return "";

    	$finalWarehouseArray = array(); //
 	 	$totalRows = 0; 	//
 	 	$totalCount = 0;	//
 	 	$colspanValue = 0;

    	$result = Factory::service("Warehouse")->getWarehouseListByParent($selectedWarehouse->getId(), false);

    	if(count($result)==0)
    	{
    		return "";
    	}
    	else
    	{
    		foreach($result as $warehouse)
    		{
    			$key = $warehouse->getId() ."~". $warehouse->getName();
    			$tempArray = $this->countPartsPosition($warehouse->getPosition(), $partTypeId, $ownerId, $contractId, $statusIds, $stateIds);

    			//we're searching on specific states and no data was returned
    			if (!empty($stateIds) && empty($tempArray) && $displayOption == PartTypeQuantityController::SHOW_ZEROS)
    			{
    				//see if we have any children with the state(s), if not then exclude it
    				$childStateCount = Factory::service("Warehouse")->countByCriteria("position LIKE '" . $warehouse->getPosition() . "%' AND ignorestockcount!=1 AND active=1 AND stateid IN (" . implode(',', $stateIds) .")");
    				if ($childStateCount == 0)
    					continue;
    			}

    			$finalWarehouseArray["$key"] = $tempArray;
    		}
    	}

    	$html="<h3>List Of Locations</h3>";
		$html.="<table width='100%' class='DataList'>";

		$head=array();

		$html.="<thead><tr style='height: 20px'>";
		$html.="<th width='85%' style='padding-left: 30px; font-weight:bold'>Location</th>";
    	$head[]="Location";

    	if(count($this->finalStatusArray) == 0 && $displayOption == PartTypeQuantityController::HIDE_ZEROS)
    	{
    		return "";
    	}
    	else if(count($this->finalStatusArray) == 0 && $displayOption == PartTypeQuantityController::SHOW_ZEROS)
    	{
    		$html.="<th style='font-weight:bold; text-align:right; padding-right: 30px'>Qty</th>";
    	}
    	else
		{

			for($i = 0; $i < count($this->finalStatusArray); $i++)
			{
				$tempStatus = $this->finalStatusArray[$i];
				$tempStatusArray = explode("~", $tempStatus);
				$html.="<th style='font-weight:bold; text-align:center; padding-right: 30px'>".$tempStatusArray[1]."</th>";
				$head[]=$tempStatusArray[1];
				$colspanValue++;
			}
		}


		if($colspanValue > 0)
		{
			$colspanValue = $colspanValue + 1;
		}
		else
		{
			$colspanValue = 2;
		}

		$html.="</tr></thead>";
		$html.="<tbody>";

		$body = array();
		foreach($finalWarehouseArray as $key => $value)
		{
			if(count($value) == 0 && $displayOption == PartTypeQuantityController::HIDE_ZEROS)
			{
				continue;
			}

			$warehouseInformation = $key;
			$quantityArray = $value;

			$warehouseInfoArray = explode("~", $warehouseInformation);
			$warehouseId = $warehouseInfoArray[0];
			$warehouseName = $warehouseInfoArray[1];

			$html.="<tr class='".($totalRows%2==0 ? "DataListItem" : "DataListAlterItem")."'>";
			$html.="<td style='padding-left: 30px;'><a href='".$this->getUrlToDrillDown($warehouseId)."'>{$warehouseName}</a></td>";
			$body[$totalRows][]=$warehouseName;

			if(count($this->finalStatusArray) > 0)
			{
				for($i = 0; $i < count($this->finalStatusArray); $i++)
				{
					$tempStatus = $this->finalStatusArray[$i];
					$tempStatusArray = explode("~", $tempStatus);
					$tempStatusId = $tempStatusArray[0];

					if(array_key_exists($tempStatusId, $quantityArray))
					{
						$generatedLink = $this->getUrlToDrillDown2($warehouseId, $tempStatusId);
						$html.="<td style='text-align:right; padding-right: 30px'> <a href = '".$generatedLink."'> $quantityArray[$tempStatusId] </a></td>";
						$body[$totalRows][]= $quantityArray[$tempStatusId];
						$this->statusWiseCountArray[$i] += $quantityArray[$tempStatusId];
						$totalCount += $quantityArray[$tempStatusId];
					}
					else
					{
						$html.="<td style='text-align:right; padding-right: 30px'></td>";
						$body[$totalRows][]= 0;
					}
				}
			}
			else  // DID NOT FIND THE STATUS COLUMN LIST SO DISPLAY JUST SHOW QTY COLUMN
			{
				$html.="<td style='text-align:right; padding-right: 30px'></td>";
			}

			$totalRows++;

			$html.="</tr>";
		}


		$html.="</tbody>";

		$foot = array();
		$html.="<tfoot><tr>";
				$html.="<th style='font-weight: bold; text-align: center;'>";
					$html.="Total $totalCount Parts";
				$html.="</th>";

				$foot[]=$totalCount;
				if(count($this->statusWiseCountArray) > 0)
				{
					for($i = 0; $i < count($this->statusWiseCountArray); $i++)
					{
						$html .= "<th style = 'font-weight:bold; text-align:center;'>".$this->statusWiseCountArray[$i]."</th>";
						$foot[]= $this->statusWiseCountArray[$i];
					}
				}
				else
				{
					$html .= "<th></th>";
					$foot[]= 0;
				}

			$html.="</tr></tfoot>";
    	$html.="</table>";

    	$this->exceldata["head"]=$head;
    	$this->exceldata["body"]=$body;
    	$this->exceldata["foot"]=$foot;

    	return $totalRows == 0 ? "" : $html;

    }

    /**
     * Get Part Ids Warehouse Position In Part Ids Array
     *
     * @param unknown_type $idsPositionKitParts
     * @param unknown_type $position
     * @return unknown
     */
    private function getPartIdsInWarehousePositionInPartIdsArray($idsPositionKitParts,$position)
    {
    	$index = 0;
    	$partInstances = array();
    	if(isset($_SESSION['PartTypeQuantity']['WarehousePosition']))
    	{
	    	if(count($idsPositionKitParts)>0)
	    	{
				foreach ($idsPositionKitParts as $rows)
				{
					if(preg_match("/^" . $position  . "/",$rows['pos'])==1)
					{
						$partInstances[$index]['id'] = $rows['id'];
						$partInstances[$index]['pos'] = $rows['pos'];
						$index++;
					}
				}
	    	}
    	}
    	else
    	{
	    	$result = Factory::service("PartInstance")->getPartParentLocationDetials(explode(",",$this->delimitIds(",",$this->idsPositionKitParts)));
	    	if($result)
	    	{
		    	if(count($result)>0)
		    	{
		    		foreach($result as $row)
		    		{
		    			$warehouse = Factory::service("Warehouse")->getWarehouse($row[1]);
		    			if($warehouse instanceof Warehouse)
		    			{
			    			if(preg_match("/^" . $position  . "/", $warehouse->getPosition())==1)
			    			{
								$partInstances[$index]['id'] = $row[0];
								$partInstances[$index]['pos'] = $warehouse->getPosition();
								$index++;
							}
		    			}
		    		}
		    	}
	    	}
    	}

    	return $partInstances;
    }

    /////////// THIS FUNCTION WILL FIND THE COUNT OF PART INSTANCES GROUP BY STATUS OF A PARTICULAR POSITION OF A WAREHOUSE
    /**
     * Count Parts Position
     *
     * @param unknown_type $position
     * @param unknown_type $partTypeId
     * @param unknown_type $ownerId
     * @param unknown_type $contractId
     * @param unknown_type $statusIds
     * @return unknown
     */
    private function countPartsPosition($position, $partTypeId, $ownerId = "", $contractId = "", $statusIds = array(), $stateIds = array())
    {
    	$tempidsPositionKitParts = array();
    	$returnArray = array();

    	if(count($this->idsPositionKitParts)>0)
    	{
    		$tempidsPositionKitParts = $this->getPartIdsInWarehousePositionInPartIdsArray($this->idsPositionKitParts,$position);
    	}

		$partInstanceCount = Factory::service("PartInstance")->getPartStatusCountByPartTypeAndWarehouse($partTypeId, array(), $ownerId, $contractId, $statusIds, array(), $position, true, $stateIds);
   		if(count($tempidsPositionKitParts) > 0)
		{
    		$partInstanceCountKits = Factory::service("PartInstance")->getPartStatusCountByPartTypeAndWarehouse($partTypeId, array(), $ownerId, $contractId, $statusIds,explode(",",$this->delimitIds(",",$tempidsPositionKitParts)), $position,array(), $stateIds);
			if(!$partInstanceCount)
			{
				$partInstanceCount = $partInstanceCountKits;
			}
			else
			{
				try{
    				$partInstanceCount = array_merge($partInstanceCount,$partInstanceCountKits);
				}catch(Exception $e){
					//catch if one is not an array
				}
			}
		}

		if($partInstanceCount)
		{
	    	if(count($partInstanceCount) == 0)
	    	{
	    		return $returnArray;
	    	}
	    	else
	    	{
	    		foreach($partInstanceCount as $row)
	    		{
	    			$partInstanceStatusId = $row["partInstanceStatusId"];
	    			$partInstanceStatusName = $row['statusName'];
	    			$quantity = $row['qty'];

	    			$needle = $partInstanceStatusId."~".$partInstanceStatusName;

	    			if(!in_array($needle, $this->finalStatusArray))
	    			{
	    				$this->finalStatusArray[] = $partInstanceStatusId."~".$partInstanceStatusName;
	    				$this->statusWiseCountArray[] = 0;
	    			}

	    			if(isset($returnArray["$partInstanceStatusId"]))
	    			{
	    				$returnArray["$partInstanceStatusId"] += $quantity;
	    			}
	    			else
	    			{
	    				$returnArray["$partInstanceStatusId"] = $quantity;
	    			}
	    		}
	    	}
		}
    	return $returnArray;
    }

    /**
     * Bind DropDown List
     *
     * @param unknown_type $list
     * @param unknown_type $data
     */
    private function bindDropDownList(&$list, $data)
    {
    	$list->DataSource = $data;
    	$list->DataBind();
    }

    /////////////////////// THIS FUNCTION WILL GENERATE URL FOR QUANTITY FOR DISPLYA OPTION 0 AND 1
    /**
     * Get URL to DrillDown2
     *
     * @param unknown_type $warehouseId
     * @param unknown_type $statusId
     * @return unknown
     */
    private function getUrlToDrillDown2($warehouseId, $statusId)
    {
    	$statusIds = array();
    	$vars = $this->getSelectionVars();
    	$partTypeId = $vars["partTypeId"];
    	$contractId = $vars["contractId"];
    	$ownerId = $vars["ownerId"];
    	$includeKits = $vars["includeKits"];
    	$displayOption = $vars["displayOption"];
    	$countryIds = $vars["countryIds"];

    	if($displayOption == "0" || $displayOption == "1")
    	{
    		$displayOption = 2;
    	}

    	if($statusId != "")
    	{
    		$statusIds = array($statusId);
    	}

    	if($includeKits == "")	$includeKits = 'null';
    	if($contractId == "")	$contractId = 'null';
    	if($ownerId == "") $ownerId = 'null';
    	if(count($statusIds) == 0) $statusIds = array("null");
    	if (count($countryIds) == 0) $countryIds = array("null");

    	return "/partquantityatlocation/$partTypeId/$warehouseId/$contractId/$ownerId/$displayOption/".implode(",",$statusIds).'/'.$includeKits.'/'.implode(",",$countryIds);
    }

    /**
     * Get URL to DrillDown
     *
     * @param unknown_type $warehouseId
     * @param unknown_type $sid
     * @return unknown
     */
	private function getUrlToDrillDown($warehouseId,$sid=array())
    {
    	$vars = $this->getSelectionVars();
    	$partTypeId = $vars["partTypeId"];
    	$contractId = $vars["contractId"];
    	$ownerId = $vars["ownerId"];
    	$includeKits = $vars["includeKits"];
    	$countryIds = $vars["countryIds"];

    	$displayOption = $vars["displayOption"];
    	if($displayOption == "3" || $displayOption == "4" ) $displayOption = 2;

    	if(count($sid)== 0)
    	{
    		$statusIds = $vars["statusIds"];
    	}
    	else
    	{
    		$statusIds=$sid;
    	}

    	if($includeKits == "")	$includeKits = 'null';
    	if($contractId == "")	$contractId = 'null';
    	if($ownerId == "") $ownerId = 'null';
		if(count($statusIds)==0) $statusIds = array("null");
		if (count($countryIds) == 0) $countryIds = array("null");

		return "/partquantityatlocation/$partTypeId/$warehouseId/$contractId/$ownerId/$displayOption/".implode(",",$statusIds).'/'.$includeKits.'/'.implode(",",$countryIds);
    }

    /**
     * Get Selection Variables
     *
     * @return unknown
     */
    private function getSelectionVars()
    {
    	$partTypeId = trim($this->partType->getSelectedValue());
    	$warehouseIds = explode("/",trim($this->warehouseid->value));
    	$warehouseId = end($warehouseIds);
    	$ownerId = trim($this->searchOwner->getSelectedValue());
    	$contractId = trim($this->searchContract->getSelectedValue());
    	$displayOption = trim($this->displayOption->getSelectedValue());
    	$includeKits = $this->searchKit->getChecked();
    	$statusIds = $this->searchStatus->getSelectedValues();
    	if (in_array(-1, $statusIds))
    	{
    		$statusIds = array();
    	}

    	$stateIds = array();
    	$countryIds = $this->searchCountry->getSelectedValues();
    	if (!in_array(-1, $countryIds))
    	{
    		$sql = "SELECT DISTINCT id FROM state WHERE countryid IN (" . implode(',', $countryIds) . ")";
    		$res = Dao::getResultsNative($sql);
    		foreach ($res as $r)
    			$stateIds[] = $r[0];
    	}
    	else $countryIds = array();

    	return array(
    					"partTypeId" =>$partTypeId,
    					"warehouseId" =>$warehouseId,
    					"ownerId" =>$ownerId,
    					"contractId" =>$contractId,
    					"displayOption" =>$displayOption,
    					"statusIds" => $statusIds,
    					"stateIds" => $stateIds,
    					"countryIds" => $countryIds,
    					"includeKits" =>$includeKits
    				);
    }

    /**
     * Output to Excel
     *
     */
   public function outputToExcel()
    {
    	if($this->excelString->Value>"")
		{
			$this->exceldata = unserialize($this->excelString->Value);
		}
    	else
    	{
    		return ;
    	}

    	$this->workBook = new HYExcelWorkBook();

		$javascriptLabel = new TActiveLabel();
		$javascriptLabel->setID("javascriptLabel");
		$this->MainContent->Controls[] = $javascriptLabel;

    	$this->generateData();

    	try
			{
				$this->MainContent->findControl("javascriptLabel")->Text="";
				$workBookXml = $this->workBook->__toString();
				if($workBookXml!='')
				{
					$contentServer = new ContentServer();
					$assetId = $contentServer->registerAsset(ContentServer::TYPE_REPORT, "partquantityatlocation.xls", $workBookXml);
					$this->MainContent->findControl("javascriptLabel")->Text = "<script type=\"text/javascript\">window.open('/report/download/$assetId');</script>";
				}

			}
			catch(Exception $e)
			{
				$this->setErrorMessage($e->getMessage());
			}

    }

    /**
     * Generate Data
     *
     */
    private function generateData()
    {
    	//create  workSheet
    	$workSheet = new HYExcelWorkSheet("Show Part Quantity at Location");
    	$this->addTitle($workSheet);
		$this->addDataTable($workSheet);
		$this->workBook->addWorkSheet($workSheet);
    }

    /**
     * Add Title
     *
     * @param HYExcelWorkSheet $sheet
     */
    private function addTitle(HYExcelWorkSheet &$sheet)
    {
    	//sheet Title
    	$titleStyle = new HYExcelStyle("title");
		$titleStyle->setFont("Swiss", "#000000", false, false,16);
		$titleStyle->setAlignment("Left","Center",false);
		$titleRow = new HYExcelRow();
    	$title = "Show Part Quantity at Location";
		$titleRow->addCell(new HYExcelCell($title,"String",$titleStyle));
		$sheet->addRow($titleRow);

		//sub Title
		$subTitleStyle= new HYExcelStyle("subtitle");
		$subTitleStyle->setAlignment("Left", "Center", false);
		$subTitleStyle->setFont("Swiss", "#000000", true, false, 8);


		$subTitleRow = new HYExcelRow();
		$subTitleRow->addCell(new HYExcelCell("Owner Client --  ".$this->exceldata["ownerclient"],"String",$subTitleStyle));
		$sheet->addRow($subTitleRow);

		$subTitleRow = new HYExcelRow();
		$subTitleRow->addCell(new HYExcelCell("Contract --  ".$this->exceldata["contract"],"String",$subTitleStyle));
		$sheet->addRow($subTitleRow);

		$subTitleRow = new HYExcelRow();
		$subTitleRow->addCell(new HYExcelCell("Part Type -- ".$this->exceldata["parttype"],"String",$subTitleStyle));
		$sheet->addRow($subTitleRow);

		$subTitleRow = new HYExcelRow();
		$subTitleRow->addCell(new HYExcelCell("Option -- ".$this->exceldata["displayoption"],"String",$subTitleStyle));
		$sheet->addRow($subTitleRow);


		$subTitleRow = new HYExcelRow();
		$subTitleRow->addCell(new HYExcelCell("Option -- ".$this->exceldata["searchkit"],"String",$subTitleStyle));
		$sheet->addRow($subTitleRow);

		$subTitleRow = new HYExcelRow();
		$subTitleRow->addCell(new HYExcelCell("Location under -- ".$this->exceldata["locationunder"],"String",$subTitleStyle));
		$sheet->addRow($subTitleRow);

		//blank row
		$blankRow = new HYExcelRow();
		$blankRow->addCell(new HYExcelCell("","String"));
		$sheet->addRow($blankRow);

		//table headers
		$headerStyle = new HYExcelStyle("header");
		$headerStyle->setFont("Swiss","#FFFFFF", true, false, 8);
		$headerStyle->setInterior("#666666");
		$headerStyle->setAlignment("Center","Center",true);

		$sheet->addRow($this->addRow($this->exceldata["head"],$headerStyle,30));

    }

    /**
     * Add Row
     *
     * @param unknown_type $dataRow
     * @param unknown_type $style
     * @param unknown_type $height
     * @param unknown_type $rowStyle
     * @param unknown_type $lastCellData
     * @param unknown_type $lastCellStyle
     * @return unknown
     */
    private function addRow($dataRow=array(), $style=null,$height="",$rowStyle=null, $lastCellData="",$lastCellStyle=null)
    {
    	if(!$lastCellStyle instanceof HYExcelStyle)
    		$lastCellStyle = null;
    	$row = new HYExcelRow($height,$rowStyle);
    	if(!$style instanceof HYExcelStyle )
    		$style=null;
    	foreach($dataRow as $r)
		{
			if(strstr($r," ")!==false)
				$row->addCell(new HYExcelCell($r,"String",$style));
			else if(is_numeric($r))
				$row->addCell(new HYExcelCell($r,"Number",$style));
			else
				$row->addCell(new HYExcelCell($r,"String",$style));
		}
		if($lastCellData!="")
		{
			if(is_numeric($lastCellStyle))
				$row->addCell(new HYExcelCell($lastCellData,"Number",$lastCellStyle));
			else
				$row->addCell(new HYExcelCell($lastCellData,"String",$lastCellStyle));
		}
		return  $row;
    }

    /**
     * Add Data Table
     *
     * @param HYExcelWorkSheet $sheet
     */
    private function addDataTable(HYExcelWorkSheet &$sheet)
    {
		$dataStyle = new HYExcelStyle("data");
		$dataStyle->setAlignment("Left", "Center", true);
		$dataStyle->setFont("Swiss","#000000", false, false, 8);
		$dataStyle->setBorder("all", "#C0C0C0");

		$intDataStyle = new HYExcelStyle("intData");
		$intDataStyle->setAlignment("Right", "Center", true);
		$intDataStyle->setFont("Swiss","#000000", false, false, 8);
		$intDataStyle->setBorder("all", "#C0C0C0");

		$altDataStyle = new HYExcelStyle("altData");
		$altDataStyle->setAlignment("Left", "Center", true);
		$altDataStyle->setFont("Swiss","#000000", false, false, 8);
		$altDataStyle->setBorder("all", "#C0C0C0");
		$altDataStyle->setInterior("#CCFFFF");

		$altIntDataStyle = new HYExcelStyle("altIntData");
		$altIntDataStyle->setAlignment("Right", "Center", true);
		$altIntDataStyle->setFont("Swiss","#000000", false, false, 8);
		$altIntDataStyle->setBorder("all", "#C0C0C0");
		$altIntDataStyle->setInterior("#CCFFFF");

		if (count($this->exceldata["body"]) == 0)
		{
			throw new Exception("There's no data to be displayed.");
			die();
		}

		$currRow = 0;
		foreach($this->exceldata["body"] as $row)
		{

			$currStyle = $dataStyle;
			$currIntStyle = $intDataStyle;
			if ($currRow % 2 != 0)
			{
				$currStyle = $altDataStyle;
				$currIntStyle = $altIntDataStyle;
			}
			$currRow++;

			$dataRow =new HYExcelRow();

			foreach($row as $cell)
			{
				$dataRow->addCell(new HYExcelCell($cell,"",$currStyle));
			}

			$sheet->addRow($dataRow);
		}

		$blankRow = new HYExcelRow();
		$blankRow->addCell(new HYExcelCell("","String"));
		$sheet->addRow($blankRow);

			$footer = new HYExcelRow();
			$footerStyle = new HYExcelStyle("footer");
			$footerStyle->setFont("Swiss","#ff0000",true);
			$footerStyle->setInterior("#CCFFFF");
			$footerStyle->setAlignment("Left","Center",false);
			$footerStyle->setBorder("all","#000000",2);

			foreach($this->exceldata["foot"] as $cell)
			{
				$footer->addCell(new HYExcelCell($cell,"",$footerStyle));
			}

			$sheet->addRow($footer);

    }

    /**
     * Get Sortable Header
     *
     * @param unknown_type $Id
     * @param unknown_type $text
     * @param unknown_type $sortingField
     * @param unknown_type $width
     * @return unknown
     */
 	private function getSortableHeader($Id, $text, $sortingField, $width)
 	{
        if($Id == 'qty')
            return "<th width='$width' style='text-align:right; padding-right:5px; font-weight:bold'><a Id='$Id' href='javascript: void(0);' onClick=\"changeSortingOrder(this,'$sortingField','$Id');\">$text</a></th>";
        else
        	return "<th width='$width' style='font-weight:bold'><a Id='$Id' href='javascript: void(0);' onClick=\"changeSortingOrder(this,'$sortingField','$Id');\">$text</a></th>";
    }
}

?>
