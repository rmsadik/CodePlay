<?php
/**
 * Change quantity for part instance
 * @author aahmed
 *
 */
class ChangeQuantityPartInstanceController extends CRUDPage 
{	
	/**
	 * @var unknown_type
	 */
	protected $querySize;

	/**
	 * @var unknown_type
	 */
	public $partTypeName = "";

	/**
	 * @var unknown_type
	 */
	protected $types = array();
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->focusOnSearch = false;
		$this->menuContext = 'changePartQuantity';
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_changeQuantityPartInstance";
		$this->querySize = 0;
	}

	/**
	 * onLoad
	 * @param unknown_type $param
	 */
    public function onLoad($param)
    {
        parent::onLoad($param);
        if(!$this->IsPostBack || $param == "reload")
        {        
			$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
	        $this->dataLoad();
	       
	        $this->BTBarcode->setAttribute( 'onkeydown', "doEnterBehavior(event,'ctl0_MainContent_SearchButton');" );
	        $this->bindDropDownList($this->aliasType,Factory::service("PartTypeAliasType")->findAll());
	        
	        $this->BTBarcode->focus();
	    	$warehouseid = Factory::service("UserPreference")->getOption(Core::getUser(),'defaultWarehouse');
	    	$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseid); 
	    	
        	if($warehouse == 'NULL'||$warehouse == "")
	    	{
	    		$err = "Please Contact ". Config::get("SupportHandling","Contact")." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email");
	    		$this->setErrorMessage("No Default Warehouse set. ".$err);
	    		$this->SearchButton->Enabled="false";
	    	}
	    	else
	    	{	
	    		$this->SearchButton->Enabled="true";
	    	}
        }
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
    	return array();
    }

    /**
     * Search 
     * @return String Barcode
     */
    protected function toPerformSearch()
    {
    	$this->SearchString->Value = $this->BTBarcode->Text;
    	return $this->BTBarcode->Text == "";
    }
    
    /**
     * Return query size 
     * @return int querysize
     */
    protected function howBigWasThatQuery()
    {
    	return $this->querySize;
    }
       
    /**
     * Item Created
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function itemCreated($sender, $param)
    {
    	$item=$param->Item;
    	if($item->ItemType==='Item' || $item->ItemType==='AlternatingItem') 
    	{

    	}
    }
    
    /**
     * Search functionality
     * @param unknown_type $searchString
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     */
    protected function searchEntity($searchString,&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$this->setErrorMessage("");
    	$this->setInfoMessage("");
    	$partTypeAliasTypeId = $this->aliasType->getSelectedValue();
    	
    	$query = new DaoReportQuery("PartTypeAlias");
    	$query->column("pta.partTypeId","id");
    	$query->column('pt.name','name');
    	$query->page(0,2);
    	$query->innerJoin('PartTypeAlias.partType','pt','pt.active = 1');
    	$query->where("pta.partTypeAliasTypeId = $partTypeAliasTypeId and pta.active = 1 and pta.alias = ? ",array($searchString));
    	$results = $query->execute(false);
    	
    	if(sizeof($results) < 1)
    	{
    		$this->setErrorMessage("Part Not found");
    		return array();
    	}
    	
    	if(sizeof($results) > 1)
    	{
    		$this->setErrorMessage("Duplicate Part Found");
    		return array();
    	}

    	$partTypeId = $results[0][0];
    	$partTypeName = $results[0][1];
    	
    	//checking whether the part is serialised or not
    	//if it contain BCP/BP, then show it; otherwise show error message
    	$checkBCPQuery = new DaoReportQuery("PartTypeAlias");
    	$checkBCPQuery->column("count(id)");
    	$checkBCPQuery->where("pta.active=1 and (pta.alias like 'BP%' or pta.alias like 'BCP%') AND pta.partTypeId=?",array($partTypeId));
    	$checkCount = $checkBCPQuery->execute(false);
    	
    	if($checkCount[0][0]==0)
    	{
    		$this->setErrorMessage("There are only serialised parts for this part type, you can't change the quantity of the serialised part!");
    		return array();
    	}
    	
    	$warehouseid = Factory::service("UserPreference")->getOption(Core::getUser(),'defaultWarehouse');
    	$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseid); 
    	$treeDao = new  TreeDAO('Warehouse');
    	
    	if($warehouse == 'NULL'||$warehouse == "")
    	{
    		$this->setErrorMessage("No Default Warehouse Set");
    		return;
    	}
    	else
    	{	
    		$sql = $treeDao->createTreeSqlInclusive($warehouse); 
    	}
    	$query = new DaoReportQuery("PartInstance");
    	$query->column("pi.id","id");
    	$query->column("pi.quantity","quantity");
    	$query->column("(select cl.clientName from client cl where pt.ownerClientId = cl.id)","owner");
    	$query->column("(SELECT GROUP_CONCAT(DISTINCT cxx.contractName SEPARATOR ', ') FROM contract cxx INNER JOIN contract_partinstance cpxx ON cpxx.contractId = cxx.id where cpxx.partinstanceid = pi.id group by pi.id order by cxx.id)","Contracts");
    	$query->column("(select ware.id from warehouse ware where ware.id=pi.warehouseId)","warehouse");
    	$query->innerJoin('PartTypeAlias.partType','pt','pt.active = 1');
    	$query->where("pi.active = 1 and pi.partTypeId = ? and pi.warehouseid in ($sql) ", array($partTypeId));
     	
    	$query->page($pageNumber,$pageSize);
    	$results = $query->execute(false);
    	
    	$query = new DaoReportQuery("PartInstance");
    	$query->column("count(pi.id)",null);
    	$query->where("pi.active = 1 and pi.partTypeId = ? and pi.warehouseid in ($sql)", array($partTypeId));
		$rows = $query->execute(false);
    	$this->querySize = $rows[0][0];
    	
    	$this->setInfoMessage("Part: ".$partTypeName.", ".$this->querySize." sets found");
    	
    	return $results;
    }
    
    /**
     * Retrieve contract group & contract details for a part instance
     * @param unknown_type $sender
     * @param unknown_type $param
     * @throws Exception
     */
    public function showContractDetails($sender, $param)
    {
    	$results = $errors = array();
    	try
    	{
    		if(!isset($param->CallbackParameter->partInstanceId) || ($partInstanceId = trim($param->CallbackParameter->partInstanceId)) === '')
    		{
    			throw new Exception('No Selected Part Instance!');
    		}
    		
    		//get part type id for part instance
    		$query = new DaoReportQuery("PartInstance");
    		$query->column("pi.partTypeId","partTypeId");
    		$query->where("pi.id = ? and pi.active = 1",array($partInstanceId));
    		$resultSet = $query->execute(false);
    		$results['partTypeId'] = $resultSet[0][0];
    		$partTypeId = $resultSet[0][0];
    		
    		//get contract & contract groups
    		$q = new DaoReportQuery("Contract");
    		$addJoin = "INNER JOIN contract_parttype cpt ON cpt.contractId=c.id ";
    		$addJoin .= "INNER JOIN contractgroup cg ON (cg.id = c.contractGroupId) ";
    		$q->setAdditionalJoin($addJoin);
    		$q->column("c.contractName");
    		$q->column("cg.groupName");
    		$q->where("cpt.partTypeId = ? ",array($partTypeId));
    		$resultContract = $q->execute(false);
    		
    		$results['contract'] = $resultContract;
    	}
    	catch(Exception $ex)
    	{
    		$errors[] = $ex->getMessage();
    	}
    	$param->ResponseData = Core::getJSONResponse($results, $errors);
    }
    
    /**
     * Display location for a warehouse
     * @param unknown_type $warehouseId
     * @return breadcrumbs/location
     */
    public function showLocation($warehouseId)
    {
    	$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId); 
    	if(!$warehouse instanceof Warehouse)
    		return;
    	
    	return Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true);
    }
    
    /**
     * Reset Screen
     *
     */
    protected function resetScreen()
    {
    	
    }
    
    /**
     * Re-register PartInstances
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function reregisterPartInstances($sender,$param)
	{
		$this->resetScreen();
		$this->setErrorMessage('');
    	$this->setInfoMessage('Part Instance sucessfully reregistered.');		
	}

	/**
	 * Change the quantity for part instance
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function ChangeQuantity($sender,$param)
	{
		$parent = $sender->getParent();
		$quantityText = $parent->quantity->text;
		
		$pi = Factory::service("PartInstance")->get($param->CommandParameter);
		$pi->setQuantity($quantityText);
		
		if($quantityText==0)
		{
			$piComments = new PartInstanceAlias();
			$piComments->setPartInstance($pi);
			$piComments->setAlias("Part deactivated (via changing quantity to '0') by ".Core::getUser()->getPerson()." @ ".DateUtils::now()."(UTC)");
			$piComments->setPartInstanceAliasType(Factory::service("PartInstanceAliasType")->get(5));
			Factory::service("PartInstanceAlias")->save($piComments);
			$pi->setActive(false);
		}
		Factory::service("PartInstance")->save($pi);
		$this->dataLoad();
	}
	
	/**
	 * Get View
	 *
	 * @param unknown_type $data
	 * @return unknown
	 */
	public function getView($data)
	{
		if($data[0] == '-1')
			return "2";
		
		if($data[2] == "1")
			return "1";		
		
		return "0";
	}
	
	/**
	 * Get Type
	 *
	 * @param unknown_type $type
	 * @return unknown
	 */
	public function getType($type)
	{
		return Factory::service("PartInstanceAliasType")->get($type);
	}
}

?>