<?php
/**
 * Parts By Location Controller Page 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version 1.0
 */
class PartsByLocationController extends CRUDPage 
{
	/**
	 * @var menuContext
	 */
	public $menuContext;
	
	/**
	 * @var rowCount
	 */
	private $rowCount;
	
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = "partsbylocation";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_partsByLocation";
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
			if(count($this->DataList->DataSource) < 1)
				$this->DataListPanel->Visible=false;
        }
    }
    
    /**
     * Get Row Count
     *
     * @return unknown
     */
    public function getRowCount()
    {    
    	return $this->rowCount;
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
    	$this->setErrorMessage('');
    	$partTypeAlias = new PartTypeAlias();
    	$partInstances = array();
    	
    	$warehouseId = explode('/',$this->warehouseid->Value);
    	$warehouse = end($warehouseId);
		$warehouse = Factory::service("Warehouse")->getWarehouse($warehouse);
		if($this->partTypes->Text == "" && $this->DataListPanel->Visible == false || $this->partTypes->getSelectedValue() == 0)
    	{
    		$this->partTypesValidator->Visible=true;
    		return array();
    	}
    	else
    	{
    		$this->partTypesValidator->Visible=false;
	    	$partTypeAliasId = $this->partTypes->getSelectedValue();
	    	$partTypeAlias = Factory::service("PartTypeAlias")->get($partTypeAliasId);
	    	$partInstances = Factory::service("Warehouse")->getPartTypesByWarehouseAndPartType($warehouse,$partTypeAlias->getPartType(),$pageNumber,$pageSize);
	    	
			$this->rowCount = Dao::getTotalRows();	    	
	     	if($this->rowCount > 0)
	     		$this->DataListPanel->Visible=true;
    	}
    	
		if(count($partTypeAlias) < 1)
			$this->PartCode->Text = $partTypeAlias->getPartType()->getName();
		else
			$this->PartCode->Text =  $partTypeAlias->getAlias() . " ( " . $partTypeAlias->getPartType()->getName() . " ) " ;
    	
    	$this->partTypes->Text = "";
		if(count($partInstances) < 1)
		{
			$this->DataListPanel->Visible=false;
			$this->setErrorMessage('No parts of type <b>' . $this->PartCode->Text . '</b> on location <b>' . $warehouse->getName() . '</b>');
		}
    	return $partInstances;
    }
    
	/**
	 * Get Barcode for the Non Serialized part. 
	 *
	 * @param unknown_type $id
	 * @return unknown
	 */
    public function getBarcode($id)
	{
		$partInstance = Factory::service("PartInstance")->getPartInstance($id);
		if(count($partInstance) > 0)
		{
			$partTypeAlias = Factory::service("PartType")->getPartTypeAliasByTypeForPartType($partInstance->getPartType()->getId(),2);
			if(count($partTypeAlias) > 0)
			{
				$barcode = $partTypeAlias[0]->getAlias();
			}
			else
			{
				$barcode="";
			}
			return $barcode;
		}
	}
}

?>