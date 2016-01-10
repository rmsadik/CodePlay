<?php
/**
 * Move Warehouse Controller Page 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version 1.0
 */
class MoveWarehouseController extends CRUDPage 
{	
	/**
	 * @var querySize
	 */
	protected $querySize;
	
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = 'partinstancedetails';
		$this->openFirst = true;
		//TODO:: if using this page, change roleLocks below as "page_logistics_parttypedetails", as it's been used elsewhere!
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_parttypedetails";
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
        if(!$this->IsPostBack || $param == "reload")
        {        
			$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
	        $this->dataLoad();
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
     * Get Warehouse
     *
     * @param unknown_type $name
     * @return unknown
     */
    protected function getWarehouse($name)
    {
		$warehouseId = $this->$name->Value;
		$warehouseId = explode('/',$warehouseId);
		$warehouseId = end($warehouseId);
		return Factory::service("Warehouse")->get($warehouseId);
    }
    
    /**
     * Move One to Two
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    protected function moveOneToTwo($sender,$param)
    {
    	$warehouse1 = $this->getWarehouse('Warehouseid1');
    	$warehouse2 = $this->getWarehouse('Warehouseid2');
    	Factory::service("Warehouse")->moveWarehouse($warehouse2, $warehouse1);
    }
    
    /**
     * Move TWO to ONE
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    protected function moveTwoToOne($sender,$param)
    {
    	$warehouse1 = $this->getWarehouse('Warehouseid2');
    	$warehouse2 = $this->getWarehouse('Warehouseid1');
    	Factory::service("Warehouse")->moveWarehouse($warehouse2, $warehouse1);
    }
    
    /**
     * Explode Warehouse
     *
     */
    protected function explodeWarehouse()
    {
    	$warehouse = $this->getWarehouse('Warehouseid2');
    	Factory::service("Warehouse")->removeWarehouse();
    	
    	$subNodes = Factory::service('Warehouse')->getWarehouseChildrenCount($warehouse);
    	if(sizeof($subNodes) > 0)
    		throw new Exception("Trying to explode parential warehouse");
    	
    	$warehouseParent = $warehouse->getParent();
    	$partInstances = $warehouse->getPartInstances();
    	foreach($partInstances as $partInstance)
    	{
			$partInstance->setWarehouse($warehouseParent);
			Factory::service("Warehouse")->save($partInstance);
    	}
    }
}

?>