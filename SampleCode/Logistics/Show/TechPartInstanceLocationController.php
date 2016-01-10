<?php
/**
 * Tech Part Instance Location Controller Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version 1.0
 */
class TechPartInstanceLocationController extends CRUDPage
{
	/**
	 * @var itemCount
	 */
	public $itemCount;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = 'showtechparts';
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
	        $this->AddPanel->Visible=false;
	        $this->DataList->EditItemIndex = -1;
	        $this->dataLoad();
        }
    }
    /**
     * On Pre Initialization
     *
     * @param unknown_type $param
     */
	public function onPreInit($param)
	{
		// Agent Logistics come from "Agent" menu, not Agent Logistics Menu.
		if(Core::getRole()->getId()==11) //Agent View
			$this->getPage()->setMasterClass("Application.layouts.AgentLogisticsLayout");
		else if(Core::getRole()->getId()==6 || Core::getRole()->getId()==30) //Agent Technician OR Agent Logistics
			$this->getPage()->setMasterClass("Application.layouts.AgentViewLayout");
		else if(Core::getRole()->getId()==7) //client Technician
			$this->getPage()->setMasterClass("Application.layouts.ClientViewLayout");
		else
			$this->getPage()->setMasterClass("Application.layouts.FieldTechLayout");
	}

	/**
	 * Get Base data
	 *
	 * @param unknown $searchString
	 * @param string $focusObject
	 * @param string $pageNumber
	 * @param string $pageSize
	 * @return void|NULL|multitype:Ambigous <multitype:multitype: , multitype:]array[ > Ambigous <multitype:]array[ , multitype:>
	 */
	public function getbaseData($searchString,&$focusObject = null,$pageNumber=null,$pageSize=null)
	{
		// get user's defaultMobileWarehouse
		$userDefMobileWarehouseId = Factory::service("UserPreference")->getOption(Core::getUser(), "defaultMobileWarehouse");
		if (empty($userDefMobileWarehouseId))
		{
			$this->setInfoMessage("No default mobile warehouse has been set against your account.");
			return null;
		}
		$techWarehouse = Factory::service("Warehouse")->get($userDefMobileWarehouseId);

		// overloading tree sql, which improves executionspeed and performance by much faster
		$subWarehouses = Factory::service("Warehouse")->getWarehouseTreeAsObjects($techWarehouse);
		$maxSubWarehouses = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'maxSubWarehouses',true);
		if(!$maxSubWarehouses)
		{
			$maxSubWarehouses = 10;
		}

		if(count($subWarehouses) > 10)
		{
			$this->setErrorMessage("Your default mobile warehouse creates to many records! Please contact Bytecraft Technology!");
			return;
		}

		$whIdsOnly = array();
		foreach ($subWarehouses as $swh)
		{
			$whIdsOnly[] = $swh->getId();
		}
		$treeSql = join(",", array_filter(array_merge(array($userDefMobileWarehouseId), $whIdsOnly), create_function('$a', 'return !empty($a);')));
		// end overloading

		$query = new DaoReportQuery('PartInstance',false);
		$query->column('pi.quantity');		// 0
		$searchPta = $searchString>""?" and `ptaliases` in (".$searchString.")":"";
		$query->column("(SELECT GROUP_CONCAT(DISTINCT ptaxx.alias SEPARATOR ', ') FROM parttypealias ptaxx where partTypeId = pi.partTypeId  AND ptaxx.partTypeAliasTypeId = 1 GROUP BY pi.partTypeId)","ptaliases");
		$query->column("(SELECT GROUP_CONCAT(DISTINCT piaxx.alias SEPARATOR ', ') FROM partinstancealias piaxx where piaxx.partInstanceId = pi.id AND piaxx.partInstanceAliasTypeId = 1 GROUP BY pi.id)","pialias");
		$query->column('(SELECT pis.name FROM partinstancestatus pis WHERE pis.id = pi.partinstancestatusid)');		// 3
		$query->column('(SELECT pt.name FROM parttype pt WHERE pt.id=pi.partTypeId)');			// 4
		$query->column('(SELECT w.name FROM warehouse w WHERE w.id=pi.warehouseId)');		// 5
		$query->column('pi.warehouseId');													// 6
		$query->column("(SELECT GROUP_CONCAT(DISTINCT piaxx.alias SEPARATOR ', ') FROM partinstancealias piaxx where piaxx.partInstanceId = pi.id AND piaxx.partInstanceAliasTypeId = 6 GROUP BY pi.id)","pimanuno");		// 7
		$query->column("(SELECT GROUP_CONCAT(DISTINCT piaxx.alias SEPARATOR ', ') FROM partinstancealias piaxx where piaxx.partInstanceId = pi.id AND piaxx.partInstanceAliasTypeId = 8 GROUP BY pi.id)","picliassetno");	// 8
		$query->column("(SELECT s.name FROM warehouse w INNER JOIN facility f ON w.facilityId=f.id INNER JOIN address a ON f.addressId=a.id INNER JOIN state s ON a.stateId=s.id WHERE w.id=pi.warehouseId)", "state");		// 9
		$query->column("(SELECT GROUP_CONCAT(DISTINCT piaxx.alias SEPARATOR ', ') FROM partinstancealias piaxx where piaxx.partInstanceId = pi.id AND piaxx.partInstanceAliasTypeId = 10 GROUP BY pi.id)","pipono");		// 10
		$query->column("(SELECT GROUP_CONCAT(DISTINCT piaxx.alias SEPARATOR ', ') FROM partinstancealias piaxx where piaxx.partInstanceId = pi.id AND piaxx.partInstanceAliasTypeId = 7 GROUP BY pi.id)","piwardet");		// 11
		$query->column("(SELECT GROUP_CONCAT(DISTINCT ptg.name SEPARATOR ', ') FROM parttypegroup ptg LEFT JOIN parttype_parttypegroup ptptg ON ptg.id=ptptg.partTypeGroupId LEFT JOIN parttype pt ON ptptg.partTypeId=pt.id WHERE pt.id = pi.partTypeId)");  // 12
		$query->where('pi.warehouseId IN ('.$treeSql.')');
		$query->where('pi.active = 1 ');
		$query->orderBy("(SELECT GROUP_CONCAT(DISTINCT piaxx.alias SEPARATOR ', ') FROM partinstancealias piaxx where piaxx.partInstanceId = pi.id AND piaxx.partInstanceAliasTypeId = 1 GROUP BY pi.id)", DaoReportQuery::DESC);
		$query->orderBy('(SELECT pt.name FROM parttype pt WHERE pt.id=pi.partTypeId)', DaoReportQuery::ASC);

		if(!isset($searchString) || $searchString == "")
			$query->page($pageNumber,$pageSize);

		$stockPartInstances = $query->execute(false);

		//search string
		if(isset($searchString)&& $searchString>"")
		{
			foreach($stockPartInstances as $key => $result)
			{
				if(is_numeric($searchString))
				{
					if(!in_array($searchString,explode(',',$result[1])))//partcode
					{
						unset($stockPartInstances[$key]);
					}
				}
				else
				{
					if(!in_array($searchString,explode(', ',$result[2])))//barcode
					{
						unset($stockPartInstances[$key]);
					}
				}
			}
		}
		if(!$searchString)
		{
			$query = new DaoReportQuery('PartInstance');
			$query->column('count(*)');
			$query->where('pi.warehouseId IN ('.$treeSql.')');
			$query->where('pi.active = 1');
			$results = $query->execute(false);
		}
		else{
			$results=array(array());
		}

		return array($stockPartInstances,$results);

	}


    /**
     * Function that returns all parts under current user's defaultMobileWarehouse
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$results = $this->getbaseData("",$focusObject,$pageNumber,$pageSize);
	    $stockPartInstances = $results[0];
	    $array2 = $results[1];
	    $this->itemCount = $array2[0][0];

    	$userDefMobileWarehouseId = Factory::service("UserPreference")->getOption(Core::getUser(), "defaultMobileWarehouse");
    	if (empty($userDefMobileWarehouseId))
    	{
    		$this->setInfoMessage("No default mobile warehouse has been set against your account.");
    		return null;
    	}

    	$techWarehouse = Factory::service("Warehouse")->get($userDefMobileWarehouseId);
    	if(empty($stockPartInstances))
    	{
	    	$this->setInfoMessage('No parts found in '.$techWarehouse->getName().'.');
    	}
    	else
    	{
	    	$this->PageTitleLabel->setText('Parts in '.$techWarehouse->getName());
    	}

    	if(count($stockPartInstances)>0)
    	{
	    	// turn the warehouse location into fullpath!
	    	foreach ($stockPartInstances as $key => $row)
	    	{
	    		$currWh = Factory::service("Warehouse")->getWarehouse($row[6]);
	    		$fullWarehouse = Factory::service("Warehouse")->getWarehouseBreadCrumbs($currWh);
	    		$stockPartInstances[$key][5] = $fullWarehouse;
	    	}

    	}
    	return $stockPartInstances;
    }

    /**
     * (non-PHPdoc)
     * @see CRUDPage::populateEdit()
     */
    protected function populateEdit($editItem)
    {
    	$data = $editItem->getData();
		$this->getAvailableStatuses($editItem,$data[3]);
    }

    /**
     * (non-PHPdoc)
     * @see CRUDPage::populateAdd()
     */
    protected function populateAdd()
    {
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
    	$this->setInfoMessage("");
    	$results = $this->getbaseData($searchString,$focusObject,$pageNumber,$pageSize);
    	$stockPartInstances = $results[0];
    	$array2 = $results[1];
    	if($array2 && isset($array2[0][0]))
    	{
	    	$this->itemCount = $array2[0][0];
    	}
    	else
    	{
	    	$this->itemCount = count($stockPartInstances);
    	}

    	if(count($stockPartInstances)==0)
    		$this->setInfoMessage("No Record Found.");

    	return $stockPartInstances;
    }

    /**
     * How Big Was That Query
     *
     * @return unknown
     */
	protected function howBigWasThatQuery()
    {
    	return $this->itemCount;
    }

    /**
     * Get available statuses
     *
     * @param unknown $editItem
     * @param unknown $oldStatus
     */
    public function getAvailableStatuses($editItem,$oldStatus=array(''))
    {
    	$statusArray = Factory::service('DontHardcode')->searchDontHardcodeByParamNameAndFilterName('WapPartListController','availableStatus',true);
    	if(!empty($statusArray))
    		$statusArray = explode(',',$statusArray);
    	else
    		$statusArray = array('Good','Not Good','PDOA');

    	//remove the existing status
    	if(($key = array_search($oldStatus, $statusArray)) !== false) {
    		unset($statusArray[$key]);
    	}
    	$results = array();
    	foreach($statusArray as $key => $value)
    	{
    		$results[$value] = $value;
    	}
    	$this->bindDropDownList($editItem->statusList,$results);

    }

    /**
     * Sets object value
     *
     * @param &$object
     * @param $params
     * @param &$focusObject
     */
    protected function setEntity(&$object, $params, &$focusObject=null)
    {
    	$barcode = array_unique(explode(",",$params->barcode->Value));
    	$partNumber = intval($params->partcode->Value);
    	$qty = $params->NewQuantity->Text;
    	$comments = $params->Comments->Text;
    	$statusChoice = $params->statusList->getSelectedValue();
    	$newStatus=Factory::service("PartInstanceStatus")->getStatusFromName($statusChoice);

    	if(!empty($barcode)&& $barcode[0]>"")
    	{
    		$serialNo = count($barcode)>1?$barcode[1]:$barcode[0];
     		$pi = Factory::service("PartInstance")->searchPartInstancesByPartInstanceAlias($serialNo,array(PartInstanceAliasType::ID_SERIAL_NO,PartInstanceAliasType::ID_BARCODE), true);
	    	if($pi && count($pi)>0)
	    	{
	    		//serialized
	    		$partInstance = $pi[0];
	    		$qty = $partInstance->getQuantity();
	    		$newQuantity=' and quantity = '.$qty;
	    	}
	    	else
	    	{
	    		$this->setErrorMessage('...');
	    	}

    	}
    	else
    	{
    		//non serialized
    		$parttype = Factory::service("PartType")->searchPartTypeByAlias($partNumber,array(1));
			$partInstances = Factory::service('PartInstance')->getPartInstanceForWarehouseAndPartType(Factory::service('Warehouse')->getDefaultMobileWarehouse(CORE::getUser()),$parttype[0]);

    		foreach($partInstances as $pi)
    		{
    			if(trim($params->status->Value) == $pi->getPartInstanceStatus()->getName())
    				$partInstance=$pi;
    		}
     		$newQuantity = " and quantity = ".$qty;
    	}

 	    try
 	    {
	    	Factory::service("PartInstance")->movePartInstanceToWarehouse($partInstance, $qty, $partInstance->getWarehouse(), false, $newStatus,"(via Web) Updated Status from ".$partInstance->getPartInstanceStatus()->getName()." to ".$newStatus->getName()." ".$newQuantity."; Tech Comment : ".$comments);
    		$this->setInfoMessage("Part Status Updated to : ".$newStatus->getName());
	    }
	    catch(Exception $e)
	    {
    		$this->setErrorMessage($e->getMessage());
	    }
    }

    /**
     * Save entity
     * @see CRUDPage::saveEntity()
     */
    protected function saveEntity(&$object)
    {

    }

}

?>
