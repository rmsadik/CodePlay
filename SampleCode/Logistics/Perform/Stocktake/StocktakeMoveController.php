<?php
/**
 * StockTake Controller page
 * 
 * @package	Hydra-Web
 * @subpackage Controller
 * @version	1.0
 */
class StocktakeMoveController extends StocktakeController
{	
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = "stocktakeMove";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_stocktakeMove";
		$this->barcodes = array();
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
			$value = Factory::service("UserPreference")->getOption(Core::getUser(),"defaultWarehouse");
			if($value != null)	        
			{
		        $warehouse = Factory::service("Warehouse")->getWarehouse($value);
				$this->warehouseid->value = Factory::service("Warehouse")->getWarehouseIdBreadCrumbs($warehouse);
			}        	
        	
			$this->DataList->EditItemIndex = -1;        	
			$this->dataLoad();
			$this->loadMatchList();
			
			$warehouse = Factory::service("Warehouse")->getWarehouse($this->Request['id']);
			$this->setInfoMessage("Stocktake for: ".Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse));			
        }
    }

    /**
     * InActivate StockTake
     *
     * @param Stocktake $stockTake
     * @return unknown
     */
    protected function inactivateStockTake(Stocktake $stockTake)
    {
    	$stockTake->setActive(0);
    	$partInstance = $stockTake->getPartInstance();
    	$warehouse = $stockTake->getTargetWarehouse();
    	$partInstance->setWarehouse($warehouse);
    	Factory::service("PartInstance")->save($partInstance);
    	return $stockTake;
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
     * Get Warehouse Name
     *
     * @param unknown_type $warehouseId
     * @return unknown
     */
    protected function getWarehouseName($warehouseId)
    {
    	$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId);
    	return Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse);
    }
    
    /**
     * Get Target Warehouse
     *
     * @param unknown_type $warehouse
     * @return unknown
     */
    protected function getTargetWarehouse($warehouse)
    {
    	return $this->getWarehouse('warehouseid');
    }
    
    /**
     * Merge StockTake
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function mergeStockTake($sender,$param)
    {    	
    	$warehouse = Factory::service("Warehouse")->get($this->Request['id']);
    	$lostWarehouse = $this->getLostWarehouse();
		if($warehouse == null || $lostWarehouse == null)
			throw new Exception("Warehouse not valid.");
			
    	$stocktakeLog = new LogStocktake();
    	$stocktakeLog->setWarehouse($warehouse);
    	Factory::service("PartInstance")->save($stocktakeLog);	

    	
    	$transitNotes = Factory::service("TransitNote")->findByCriteria("tn.transitNoteLocationId=?",array($warehouse->getId()));
    	//assuming there is only one transit for one warehouse
    	$transitNote=null;
    	if(count($transitNotes)>0)
    		$transitNote = $transitNotes[0];
    	

    	// Find all moved onto shelf's or lost quantities
    	$query = new DaoReportQuery("Stocktake");
    	$query->column('sk.id');
    	$query->column('pi.id');
    	$query->column('sk.quantity');
    	$query->column('pi.quantity');
    	$query->column('sk.warehouseid');
    	$query->column('pi.warehouseid');
    	$query->innerJoin('Stocktake.partInstance','pi','pi.active = 1');
    	$query->where('sk.warehouseid = ? and (pi.warehouseid != sk.warehouseid or pi.quantity != sk.quantity) and sk.active = 1',array($this->Request['id']));
    	$results = $query->execute(false);
    	
    	foreach($results as $row)
    	{
    		try 
    		{
    			$partInstance = Factory::service("PartInstance")->get($row[1]);
    			$partInstance->setQuantity($row[2]);	
    			Factory::service("PartInstance")->movePartInstanceToWarehouse($partInstance, $partInstance->getQuantity(), $warehouse, false, null, "Move as part of Reconcile");
    		} 
    		catch(Exception $e)
    		{
    			continue;
    		}
			
			// Create Quantity Change Log
			if($row[2] != $row[3])
			{
				$log = new LogStocktakeQuantityChange();
				$log->setStocktakeLog($stocktakeLog);
				$log->setPartInstance($partInstance);
				$log->setChangeInQuantity($row[2] - $row[3]);
				Factory::service("PartInstance")->save($log);
			}
			
    		// Create Found Log
			if($row[4] != $row[5])
			{
				$log = new LogStocktakeFound();
				$log->setStocktakeLog($stocktakeLog);
				$log->setPartInstance($partInstance);
				Factory::service("PartInstance")->save($log);
			}
    	}
 
    	$stocktakes = Factory::service("Stocktake")->findByCriteria("sk.warehouseid = ?",array($this->Request['id']));
    	foreach($stocktakes as $row)
    	{
    		$row = $this->inactivateStockTake($row);
    		Factory::service("Stocktake")->save($row);
    	}
    	
    	// deactivate the transitNote Warehouse ////////////////////////////////////////////////////////////////////////////////////
    	if($transitNote instanceof TransitNote)
    	{
    		$transitNoteWarehouse = $transitNote->getTransitNoteLocation();
    		$pi = $transitNoteWarehouse->getPartInstances();
    		if(sizeof($pi) == 0)
    		{
	    		$transitNote->setTransitNoteStatus("close");
	    		Factory::service("TransitNote")->save($transitNote);
	    		
	    		
	    		$transitNoteWarehouse->setActive(false);
	    		Factory::service("Warehouse")->save($transitNoteWarehouse);
    		}
    	}
    	
    	$this->dataLoad();
    	$this->loadMatchList();
    }
    
    /**
     * Has SerialNumber
     *
     * @param unknown_type $partInstanceId
     * @return unknown
     */
    public function hasSerialNumber($partInstanceId)
    {
    	$sql = "select distinct id from partinstancealias where active=1 and partInstanceAliasTypeId=1 and partInstanceId=$partInstanceId";
    	$result = Dao::getResultsNative($sql);
    	return count($result)>0;
    }
}
?>