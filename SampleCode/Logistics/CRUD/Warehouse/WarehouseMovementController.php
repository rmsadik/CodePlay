<?php
/**
 * Warehouse Movement Controller 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class WarehouseMovementController extends HydraPage
{
	/**
	 * @var menuContext
	 */
	public $menuContext;
	
	/**
	 * @var searchCriteria
	 */
	private $searchCriteria;
	
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = "logistics_storeageLocationMovement";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_storeageLocationMovement";
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
			$this->getTargetLocations();	
        }
    }
    
    /**
     * Get Target Location
     *
     */
    public function getTargetLocations()
    {
    	$id =trim($this->Request['id']);
    	$storageLocation = Factory::service("Warehouse")->getWarehouse($id);
    	if(!$storageLocation instanceof Warehouse)
    	{
    		$this->setErrorMessage("Invalid warehouse (ID=$id)!");
    		$this->MainContent->Visible=false;
    		return;
    	}
       	$this->SourceLocation->Text = Factory::service("Warehouse")->getWarehouseBreadCrumbs($storageLocation,true," / ");
       	
       	//get parent
       	$parent = $storageLocation->getParent();
    	if(!$parent instanceof Warehouse)
    	{
    		$this->setErrorMessage("Invalid parent for warehouse (ID=$id)!");
    		$this->MainContent->Visible=false;
    		return;
    	}
       	$this->previousParentId->Value = $parent->getId();
       	$this->warehouseid->Value = Factory::service("Warehouse")->getWarehouseIdBreadCrumbs($parent);
    }
    
    /**
     * Move Location
     *
     */
    public function moveLocation()
    {
    	$this->setInfoMessage('');
    	$this->setErrorMessage('');
    	
    	$ids = explode('/',$this->warehouseid->getValue());
	    $warehouseId = end($ids);
	    
		$targetLocation = Factory::service("Warehouse")->getWarehouse($warehouseId);
    	$storageLocation = Factory::service("Warehouse")->findById($this->Request['id']);   	
		$storageParentName = $storageLocation->getParent()->getName();		
    	
    	try 
    	{
    		if (!UserAccountService::isSystemAdmin())
    		{
		    	$sql = "select sum(pi.quantity) from partinstance pi where pi.active = 1 and pi.warehouseId = $warehouseId";
		    	$result = Dao::getResultsNative($sql);
		    	if (count($result) > 0 && $result[0][0] > 0)
		    	{
		    		$this->setErrorMessage("There are ({$result[0][0]}) parts in the location '" . $targetLocation->getName() . "'. All parts must be cleared from this location before adding a sub-location.");
		    		return;
		    	}
    		}
    		
    		if ($targetLocation->getId() == $storageLocation->getId())
    		{
				$this->setErrorMessage("Cannot move a location into itself.");
    			return;
    		}
    		
    		$msg = "Move '" . $storageLocation->getName() ."' from '" . $storageParentName . "' to '" . $targetLocation->getName() . "'";
    		
    		//try the move
	    	if (Factory::service("Warehouse")->moveWarehouse($targetLocation, $storageLocation))
			{
				$this->setInfoMessage($msg . " successful.");
			}
			else 
			{
				$this->setInfoMessage($msg . " failed.");
			}
    	}
    	catch(Exception $ex)
    	{
    		$this->setErrorMessage("You do not have access rights to move a location into <font color=blue>". $targetLocation ."</font>.");
	    	return;
    	}
    }
    
    /**
     * Cancel Save
     *
     */
	public function cancelSave()
	{
		$this->onLoad("reload");
	}
	
	/**
	 * Redirect to storage location
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function redirectToStorageLocation($sender, $param)
    {
   		$this->response->Redirect("/storagelocation/".trim($this->previousParentId->Value));
    }
}

?>