<?php
/**
 * LostStock Warehouse Controller 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class LostStockWarehouseController extends HydraPage 
{
	/**
	 * @var $menuContext
	 */
	public $menuContext;
	
	/**
	 * @var $currentWaName
	 */
	public $currentWaName;

	/**
	 * @var $currentWaId
	 */
	public $currentWaId;
	
	
	private $numIterations;
	
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext="logistics_storageLocationAlias";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_storageLocationAlias";
		$this->menuContext="";
		
		
		$this->numIterations = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'numberUpdatesPerIteration',true);
		if(!is_numeric($this->numIterations))
		{
			$this->numIterations = 10;
		}
	}
	
	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
       	parent::onLoad($param);
       	
       	$this->jsLbl->Text = "";
       	if(!$this->IsPostBack || $param == "reload")
        {
        	$this->currentWarehouseId->Value = $this->Request["id"];
        	$warehouse = Factory::service("Warehouse")->getWarehouse($this->currentWarehouseId->Value);
        	if(!$warehouse instanceof Warehouse)
        	{
        		$this->setErrorMessage("This 'Lost Stock Warehouse'is not valid");
        		$this->dataPanel->Visible=false;
        		return;
        	}
        	$this->currentWarehouseName->Value = Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse);
        	$lostStockWarehouse = $warehouse->getLostStockWarehouse();
        	if($lostStockWarehouse instanceof Warehouse)
        	{
        		$this->currentLostStockWarehouse->Text = "The current lost stock warehouse is " . Factory::service("Warehouse")->getWarehouseBreadCrumbs($lostStockWarehouse);
        		$this->currentLostStockWarehouse->Style="font-weight:bold;color:green;";
        	}
        	else
        	{
        		$this->currentLostStockWarehouse->Text = "There is no lost stock warehouse!";
        		$this->currentLostStockWarehouse->Style="font-weight:bold;color:red;";
        	}	
		}
		
        $this->currentWaId = $this->currentWarehouseId->Value;
        $this->currentWaName = $this->currentWarehouseName->Value;
        $this->saveWarehouse->Text = "Save 'Lost Stock Warehouse' for '".$this->currentWaName."'";
    }
    
    
    /**
     * cycle through via ajax until all the parts have moved
     *
     * @return unknown
     */
    public function processChangeLostStockWarehouse()
    {
    	
		try{
			
			$iCount = 0;
			$noOfWarehouseChanged = 0;
			$output = "";
			
			$warehouse =Factory::service("Warehouse")->getWarehouse($this->currentWaId);
			$lostStockwarehouseId = $this->warehouseid->Value;
			$lostStockwarehouse =Factory::service("Warehouse")->getWarehouse($lostStockwarehouseId);
			$lostStockwarehouseName = $lostStockwarehouse->getName();
			
			$output .= "<li>$warehouse => $lostStockwarehouseName</li>";
			$warehouse->setLostStockWarehouse($lostStockwarehouse);
			Factory::service("Warehouse")->save($warehouse);
			
			
			$sql = "SELECT id FROM warehouse WHERE position LIKE '" . $warehouse->getPosition() ."%' and active = 1 
					 and lostStockWarehouseId != " . $lostStockwarehouse->getId();
			
			$results  = Dao::getResultsNative($sql);

			foreach($results as $row)
			{
				$movewarehouse = Factory::service("Warehouse")->getWarehouse($row[0]);
				if(!$movewarehouse instanceof Warehouse)
					continue;

				$movewarehouse->setLostStockWarehouse($lostStockwarehouse);
				Factory::service("Warehouse")->save($movewarehouse);
				$output .= "<li>".$movewarehouse->getName()." => $lostStockwarehouseName</li>";
				$noOfWarehouseChanged++;
				$iCount++;
				
				if($iCount == $this->numIterations)
				{
					$this->noOfWarehouseChanged->Value = intval($this->noOfWarehouseChanged->Value) + $noOfWarehouseChanged;
					return array('stop' => false);
				}

			}
			
			$this->noOfWarehouseChanged->Value = intval($this->noOfWarehouseChanged->Value) + $noOfWarehouseChanged;
    		return array('stop' => true);
    		
    	}
    	catch (Exception $e)
    	{
    		$this->exceptionMessage->Value = $e->getMessage();
    		$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessChangeLostStockWarehouse();</script>";
    		return array('stop' => true);
    	}
    	return array('stop' => false);
    }
    
    
    
    /**
     * call javascript function to hide modal box and call finishProcess()
     *
     */
    public function finishProcess()
    {
    	$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessChangeLostStockWarehouse();</script>";
    }
    
    
    
    /**
     * Finish ajax processing, show messages
     *
     */
    public function finishProcessChangeLostStockWarehouse()
    {
    	$warehouse =Factory::service("Warehouse")->getWarehouse($this->currentWaId);
    	$lostStockwarehouseId = $this->warehouseid->Value;
    	$lostStockwarehouse =Factory::service("Warehouse")->getWarehouse($lostStockwarehouseId);
    	
    	$this->OutputText->Text .= "</ul>";
    	$this->activeInformationMsg->Text= "'".$warehouse->getName()."' has been linked to '".$lostStockwarehouse->getName()."<br />";
    	if ($this->noOfWarehouseChanged->Value )
    	{
    		$this->activeInformationMsg->Text .=  $this->noOfWarehouseChanged->Value . " warehouse(s) have been linked to '".$lostStockwarehouse->getName()."'";
    		$this->currentLostStockWarehouse->Text = "The current lost stock warehouse is " . Factory::service("Warehouse")->getWarehouseBreadCrumbs($lostStockwarehouse);
    		$this->currentLostStockWarehouse->Style="font-weight:bold;color:green;";
    		
    	}	
    	if ($this->exceptionMessage->Value)
    	{
    		$this->setErrorMessage($this->exceptionMessage->Value);
    	}
    	 
    	if ($this->errorMessage->Value)
    	{
    		$this->setErrorMessage($this->getErrorMessage() . "<br />" . $this->errorMessage->Value);
    	}
    	
    	$this->noOfWarehouseChanged->Value = "0";
    }
    

    /**
     * Get Current Warehouse Name
     *
     * @return unknown
     */
    public function getCurrentWarehouseName()
    {
    	return $this->currentWaName;
    }
    
   
    /**
     * Save LostStock Warehouse
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function saveLostStockWarehouse($sender, $param)
    {
    	$this->activeInformationMsg->Text="";
    	$this->activeErrorMsg->Text="";
    	$this->OutputText->Text="";

    	$warehouse =Factory::service("Warehouse")->getWarehouse($this->currentWaId);
    	if(!$warehouse instanceof Warehouse)
    	{
    		$this->activeErrorMsg->Text="This location cannot be used as a 'Lost Stock Warehouse'.";
    		return;
    	}
    	$warehouseCategory = $warehouse->getWarehouseCategory();
    	if($warehouseCategory instanceof WarehouseCategory && $warehouseCategory->getId()==WarehouseCategory::ID_STOCK_DISC )
    	{
    		$this->activeErrorMsg->Text="A 'Stock Discrepancies' warehouse cannot be used for a 'Lost Stock Warehouse'.";
    		return;
    	}

    	$lostStockwarehouseId = $this->warehouseid->Value;
    	$lostStockwarehouse =Factory::service("Warehouse")->getWarehouse($lostStockwarehouseId);
    	
    	if(!$lostStockwarehouse instanceof Warehouse)
    	{
    		$this->activeErrorMsg->Text="This is not a valid 'Lost Stock Warehouse'";
    		return;
    	}
    	
    	$this->jsLbl->Text = "<script type=\"text/javascript\">saveChangeLostStockWarehouse();</script>";
    }

    /**
     * See Warehouse FullPath
     *
     * @param unknown_type $value
     */
    public function setWarehouse($value)
    {

    	$lostStockwarehouseId = explode("/",trim($value));
    	$lostStockwarehouseId = $lostStockwarehouseId[count($lostStockwarehouseId)-1];
    	$this->warehouseid->setValue($lostStockwarehouseId);
    	$this->saveWarehouse->Enabled=true;
    }
    
    /**
     * Reset Warehouse Full Path
     *
     */
    public function resetWarehouse()
    {
    	$this->warehouseid->setValue('');
    	$this->saveWarehouse->Enabled=false;
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}

?>