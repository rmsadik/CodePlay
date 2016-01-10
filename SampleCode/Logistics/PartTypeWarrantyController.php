<?php
/**
 * PartType Warranty Controller Page 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version 1.0
 */
class PartTypeWarrantyController extends CRUDPage
{
	/**
	 * @var StringParserUtils
	 */
	private $stringParserUtils;
	
	/**
	 * @var StringParserUtils
	 */
	protected $totalRows = 0;
	
	/**
	 * @var StringParserUtils
	 */
	protected $reValidate = false;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->allowOutPutToExcel = true;
		$this->menuContext = 'parttypewarranty';
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_parttypeWarranty";
		$this->stringParserUtils = new StringParserUtils();
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
    	parent::onLoad($param);
        $this->setInfoMessage("");
        $this->setErrorMessage("");        
		$this->PaginationPanel->Visible = false;
	
		if(Core::getRole() == "System Admin")
			$this->AllowDeactivate->Value = true;
		else
			$this->AllowDeactivate->Value = false;
		
	    if(!$this->IsPostBack || $param == "reload")
        {        
			$this->AddPanel->Visible = false;
        }
    } 

    /**
     * Data Load
     *
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     */
	public function dataLoad($pageNumber=null,$pageSize=null)
    {
    
    }
    
    /**
     * Validate PartType
     *
     * @param unknown_type $id
     */
	public function validatePartType($id)
	{
		$partType = Factory::service("PartType")->getPartType($id);
		if((!$partType instanceof PartType) || $partType == null)
		{
			$this->setErrorMessage($this->getErrorMessage() . ' Valid Part Type Required!');
			return;
		}
	}
	    
	/**
	 * Validate WorkType
	 *
	 * @param unknown_type $id
	 */
	public function validateWorkType($id)
	{
		$workType = Factory::service("WorkType")->getWorkType($id);
		if((!$workType instanceof WorkType) || $workType == null)
		{
			$this->setErrorMessage($this->getErrorMessage() . ' Valid WorkType Required!');
			return;
		}
		
		// This is to populate part types based on the worktype selection on add panel
		if($this->AddPanel->Visible == true && $this->WorkType->Text!="")
		{
		    $contract = $workType->getContract();
			if(!$this->reValidate)
			{
				$this->populateStatuses($id);
				$this->populatePartTypes($contract);
			}
		}
	}    
	
	/**
	 * Validate Period
	 *
	 */
	public function validatePeriod()
	{
		if(!$this->stringParserUtils->isNumber($this->WarrantyPeriod->Text))
		{
			$this->setErrorMessage('Invalid Warranty Period. It has to Be Integer!');
		}
	}
    
	/**
	 * Populate Statuses
	 *
	 * @param unknown_type $workTypeId
	 */
	public function populateStatuses($workTypeId)
	{
		$wfName = Factory::service("WorkTypeWorkFlow")->getWorkFlowNameForAWorkType($workTypeId);
		$data = Factory::service("WorkFlowDefinition")->getAllStatusesForAWorkflow($wfName);
		$this->StatusList->DataSource = $data; 
		$this->StatusList->dataBind();
	}
	
	/**
	 * Populate PartTypes
	 *
	 * @param unknown_type $contract
	 */
	public function populatePartTypes($contract)
	{
	    $partTypes = $contract->getPartTypes();
		foreach ($partTypes as $partType)
		{
		    $data[$partType->getId()] = $partType->getAlias().":".$partType->getName();
		    $names[] = $partType->getAlias();
		}
		array_multisort($names,SORT_ASC,$data);
		$this->PartTypeList->DataSource = $data; 
		$this->PartTypeList->dataBind();
	}
	
	/**
	 * Populate Period Types
	 *
	 */
	public function populatePeriodTypes()
	{
		$data = array(1=>"Hr",2=>"Day",3=>"Week",4=>"Month",5=>"Year");
		$this->PeriodTypeList->DataSource = $data; 
		$this->PeriodTypeList->dataBind();
	}
	
	/**
	 * Clear All
	 *
	 */
	public function clearAll()
	{
		$this->SearchPartType->Text="";
		$this->SearchWorkType->Text="";
		
		$this->WorkType->Text="";
		$this->WarrantyPeriod->Text="";
		$this->StatusList->DataSource = array(); 
		$this->StatusList->dataBind();
		
		$this->populatePeriodTypes();
		
		$this->PartTypeList->DataSource = array(); 
		$this->PartTypeList->dataBind();
		
		$this->DataListPanel->Visible = false;
		$this->DataList->DataSource = array(); 
		$this->DataList->dataBind();
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
    	$this->setErrorMessage('');
    	$this->setInfoMessage('');
    	$this->AddPanel->Visible = false;
    	$this->DataListPanel->Visible=false;
    	
    	if($this->SearchPartType->Text == "" && $this->SearchWorkType->Text == "")
    	{
    	    $result = Factory::service("PartWarranty")->findAll();
    		$count = count($result);
    		
    		if($count > 1000)
    			$this->setErrorMessage("Error : Atleast one from Part Type or Contract-Worktype is required for search as there is too much data to display!");
    	}
    	
    	if($this->SearchPartType->Text != "")
    	{
	    	$this->validatePartType($this->SearchPartType->SelectedValue);
    	}	
    	if($this->SearchWorkType->Text != "")
    	{
    		$this->validateWorkType($this->SearchWorkType->SelectedValue);
    	}
    	if($this->getErrorMessage() != "")
    		return;
    	
    	if($this->SearchPartType->Text != "")
    	    $partType = Factory::service("PartType")->getPartType($this->SearchPartType->SelectedValue);
    	else
    	    $partType = null;
    	
    	if($this->SearchWorkType->Text != "")
    	    $workType = Factory::service("WorkType")->get($this->SearchWorkType->SelectedValue);
    	else
    	    $workType = null;
    	
    	$data = Factory::service("PartWarranty")->getWarrantyForPartTypeAndOrWorkType($partType,$workType,'array');
    
		$this->DataList->DataSource = $data; 
		$this->DataList->dataBind();
		$this->DataList->EditItemIndex = -1;
		$this->totalRows = count($this->DataList->DataSource);
    	
    	if($this->totalRows <= 0)
    	{
    		$this->setInfoMessage("No Data Found...!");
    	}		
    	else
    	{
    		$this->DataListPanel->Visible = true;
    		return $data;
    	}
    }
    
    /**
     * Add Details
     *
     */
	public function addDetails()
	{
    	$this->setErrorMessage('');
    	$this->setInfoMessage('');
    	
    	$this->reValidate = true;
    	$this->validateWorkType($this->WorkType->SelectedValue);
    	$this->validatePeriod();
    	
    	if($this->getErrorMessage() != "")
    		return;    	
    	
    	try
    	{
    		$errorStr = "Error Occurred : Warranty Details For The Below Combination Exists : <br/>";
    		$savedCount= 0;
    		
	    	$workTypeId = $this->WorkType->getSelectedValue();
	    	$workType = Factory::service("WorkType")->getWorkType($workTypeId);
	    	
			$period = $this->WarrantyPeriod->Text;
			$periodType = $this->PeriodTypeList->getSelectedItem()->getText();
			$status = $this->StatusList->getSelectedItem()->getText();
	    	$partTypeIds = $this->PartTypeList->getSelectedValues();
			
	    	$ptWarranty = new PartTypeWarranty();
	    	$ptWarranty->setWorktype($workType);
	    	$ptWarranty->setWarrantyPeriod($period);
	    	$ptWarranty->setWarrantyPeriodType($periodType);
	    	$ptWarranty->setStatus($status);
	    	
	    	foreach ($partTypeIds as $ptId)
	    	{
	    		$partType = Factory::service("PartType")->getPartType($ptId);
	    		$results = Factory::service("PartWarranty")->getWarrantyForPartTypeAndOrWorkType($partType,$workType,'object');
				if(count($results) > 0)
				{
					$errorStr .= "Work Type : '".$this->WorkType->getText()."', Part Type : '".$partType->__toString()."' <br/>";
				}
				else
				{
			    	$ptWarranty->setPartType($partType);
			    	Factory::service("PartTypeWarranty")->save($ptWarranty);
			    	$ptWarranty->setId(null);
			    	$savedCount++;
				}
	    	}
	    	
    		if($errorStr != "Error Occurred : Warranty Details For The Below Combination Exists : <br/>")
    			$this->setErrorMessage($errorStr);

    		$this->setInfoMessage("Saved Warranty Details For (".$savedCount." out of ".count($partTypeIds).")!");
		    $this->AddPanel->Visible = false;
    	}
    	catch (Exception $e)
    	{
    		$this->setErrorMessage("Error Occurred : ".$e->getMessage());
    	}
	}    
    
	/**
	 * Populate Add
	 *
	 */
    protected function populateAdd()
    {
    	$this->clearAll();
    	$this->DataListPanel->Visible=false;
    }
    
    /**
     * Toggle Active
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function toggleActive($sender, $param)
    {
    	$ptWarranty = Factory::service("PartTypeWarranty")->get($sender->Parent->Parent->DataKeys[$sender->Parent->ItemIndex]);
    	$ptWarranty->setActive($sender->Parent->Active->Checked);
		Factory::service("PartTypeWarranty")->save($ptWarranty);
    	$this->search(null, null);
		$this->setInfoMessage('Saved Successfully..!');
    }

    /**
     * Output to Excel
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function outputToExcel($sender, $param)
    {
   		$columnHeaderArray = array("Part Type",
						"Contract - Worktype",
						"Status",
    					"Warranty Period",
    					"Warranty Period Type",
   						"Updated By");
    	
    	//This is for output to excel, which requires all the data....
    	$totalSize = $this->DataList->ItemCount;
    	if($totalSize <= 0 )
    		$this->setErrorMessage("Can't Output To Excel, as There is No Data.");
    	else if($totalSize <= 350 )
        	$allData = $this->search(null, null);

    	if(isset($allData))
    	{
	    	$columnDataArray = array();
			foreach ($allData as $row)
			{
				$rowData = array($row['parttype'],$row['contractworktype'],$row['status'],$row['warrantyperiod'],$row['warrantyperiodtype'],$row['updatedby']); 
				array_push($columnDataArray, $rowData);
			}
					
	    	$this->toExcel("Part Type Warranty Details","","",$columnHeaderArray,$columnDataArray);
    	}
    }
}
?>