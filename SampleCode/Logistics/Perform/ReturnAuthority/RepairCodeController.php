<?php
/**
 * Repair Code Controller
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class RepairCodeController extends CRUDPage 
{
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->roleLocks = "pages_all,pages_logistics_repaircode";
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
			$this->dataLoad();
        }
    }

    /**
     * Get All of Entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$sql = "select * from lu_repaircode order by code";
    	$results=Dao::getResultsNative($sql);
    	return $results;
    }
    
    /**
     * Populate Add
     *
     */
	public function populateAdd()
	{
		$this->rcId->Value = '';
		$this->code->Text='';
		$this->definition->Text='';
		$this->costToBc->Text='';
		$this->tmPartsCost->Text='';
		$this->travelCost->Text='';
		$this->labourCost->Text='';
		$this->notes->Text='';
	}
	
	/**
	 * Populate Edit
	 *
	 * @param unknown_type $editItem
	 */
	public function populateEdit($editItem)   
	{  
  	 	$rc = $editItem->getData();
  	 	$this->rcId->Value = trim($rc[0]);
		$editItem->code->Text = $rc[1];
		$editItem->definition->Text = $rc[2];
		$editItem->causeCode->Text = $rc[3];
		$editItem->costToBc->Text = $rc[5];
		$editItem->tmPartsCost->Text = $rc[6];
		$editItem->travelCost->Text = $rc[7];
		$editItem->labourCost->Text = $rc[8];
		$editItem->notes->Text = $rc[9];
		$editItem->onCharge->Text = $rc[4];
    }

    /**
     * Set Entity
     *
     * @param unknown_type $object
     * @param unknown_type $params
     * @param unknown_type $focusObject
     */
	protected function setEntity(&$object,$params,&$focusObject=null)
    {
    	$code=Dao::fixSQLVals(strtoupper(trim($params->code->Text)));
    	$onCharge=$params->onCharge->Text;
    	$definition=Dao::fixSQLVals($params->definition->Text);
    	$causeCode=Dao::fixSQLVals($params->causeCode->Text);
    	$costToBc=floatval(Dao::fixSQLVals($params->costToBc->Text));
    	$tmPartsCost=floatval(Dao::fixSQLVals($params->tmPartsCost->Text));
    	$travelCost=Dao::fixSQLVals($params->travelCost->Text);
    	$labourCost=Dao::fixSQLVals($params->labourCost->Text);
    	$notes=Dao::fixSQLVals($params->notes->Text);
    	$rcId = $this->rcId->Value;
    	if($rcId =='')
    	{
	    	if($this->isExistCode($code)) 
	    	{
	    		$this->setErrorMessage("This Code exists already, please pick up another one.");
	    		return;
	    	}
	    	$sql="Insert into lu_repaircode (code, definition, causecode, onCharge, costToBc, tmPartsCost, travelCost, labourCost, notes) 
	    			Values('$code', '$definition', '$causeCode', '$onCharge', $costToBc, $tmPartsCost, '$travelCost', '$labourCost', '$notes')";
    	}
    	else 
    	$sql="Update lu_repaircode Set code='$code', definition='$definition', causecode='$causeCode', onCharge='$onCharge', 
    			costToBc=$costToBc, tmPartsCost=$tmPartsCost, travelCost='$travelCost', labourCost='$labourCost', notes='$notes' 
    			Where id= $rcId ";	
    	Dao::execSql($sql); 
    }

    /**
     * Is Exist Code
     *
     * @param unknown_type $code
     * @return unknown
     */
    public function isExistCode($code)
    {
    	$sql="select * from lu_repaircode where code='$code'";
    	$results=Dao::getResultsNative($sql);
    	if (count($results)> 0) return true ;
    	 return false;
    }
    
    /**
     * Delete
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function delete($sender,$param)
    {    	
    //	$userId = Core::getUser()->getId();
    	$rcId = $param->CommandParameter;
    	$sql="Delete From lu_repaircode Where id = $rcId ";
    	Dao::execSql($sql);
    	$this->onLoad("reload");
    }
    
}
?>