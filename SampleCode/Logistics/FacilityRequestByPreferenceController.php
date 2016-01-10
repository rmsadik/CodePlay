<?php
/**
 * Facility Request By Preference Controller Page 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version 1.0
 */
class FacilityRequestByPreferenceController extends HydraPage
{
	/**
	 * @var UserAccount
	 */
	private $userAccount;
	
	/**
	 * @var contracts
	 */
	private $contracts;

	/**
	 * @var areas
	 */
	private $areas;

	/**
	 * @var contractIds
	 */
	private $contractIds;

	/**
	 * @var areaIds
	 */
	private $areaIds;

	/**
	 * @var preferedContractIds
	 */
	private $preferedContractIds;

	/**
	 * @var preferedAreaIds
	 */
	private $preferedAreaIds;
	
	/**
	 * @var menuContext
	 */
	public $menuContext;
	
	/**
	 * Enter description here...
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->roleLocks = "pages_all,pages_logistics,pages_logistics_facilityrequestPreference";
		$this->menuContext = 'facilityrequestPreference';
		$this->userAccount = new UserAccount();
		$this->contracts = array();
		$this->areas = array();
		$this->preferedContractIds = array();
		$this->preferedAreaIds = array();
		$this->contractIds = "";
		$this->areaIds = "";
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
      $this->userAccount = $this->User->getUserAccount();
      parent::onLoad($param);
      if(!$this->IsPostBack)
      {
	    $data=array();
      	$this->contracts = Factory::service("ContractGroup")->getcontracts();
        if(count($this->contracts) > 0)
        {
	        foreach($this->contracts as $contract)
	    		$data[]=array('id'=>$contract->getId(), 'name'=>$contract->getContractName());
        }
      	
	    $this->ContractCheckBoxList->DataSource = $data;
	    $this->ContractCheckBoxList->DataBind();
	    $data=array();
      	$this->areas = Factory::service("Country")->getAreas();
        if(count($this->areas) > 0)
        {
	        foreach($this->areas as $area)
	    		$data[]=array('id'=>$area->getId(), 'name'=>$area->getName());
        }
      	
	    $this->AreaCheckBoxList->DataSource = $data;
	    $this->AreaCheckBoxList->DataBind();
      	
        //Set the prefered selected contracts 
      	if(Factory::service("UserPreference")->getOption($this->userAccount,'facilityRequestSelectedContracts') != null)
		{
			$savedContractIds = Factory::service("UserPreference")->getOption($this->userAccount,'facilityRequestSelectedContracts'); 
			$j=0;
			while(($nextPos = strpos($savedContractIds, ",")) != false)
			{
				$contractId= substr($savedContractIds,0,$nextPos);
				$this->preferedContractIds[$j]=$contractId;
				$noOfChars=strlen($savedContractIds);
				$savedContractIds = substr($savedContractIds,$nextPos+1,$noOfChars);
				++$j;
			}				
	        
			$i=0;
			foreach($this->contracts as $contract)
			{
				foreach($this->preferedContractIds as $preferedContractId)
				{
					if($preferedContractId == $contract->getId())
						$this->ContractCheckBoxList->Items[$i]->Selected = true;					
				}
			++$i;	
			}
		}
			
	  //Set the prefered selected areas 	
        if(Factory::service("UserPreference")->getOption($this->userAccount,'facilityRequestSelectedAreas') != null)
		{
			$savedAreaIds = Factory::service("UserPreference")->getOption($this->userAccount,'facilityRequestSelectedAreas'); 
			$j=0;
			while(($nextPos = strpos($savedAreaIds, ",")) != false)
			{
				$areaId= substr($savedAreaIds,0,$nextPos);
				$this->preferedAreaIds[$j]=$areaId;
				$noOfChars=strlen($savedAreaIds);
				$savedAreaIds = substr($savedAreaIds,$nextPos+1,$noOfChars);
				++$j;
			}			

			$i=0;
			foreach($this->areas as $area)
			{
				foreach($this->preferedAreaIds as $preferedAreaId)
				{
					if($preferedAreaId == $area->getId())
						$this->AreaCheckBoxList->Items[$i]->Selected=true;					
				}
			++$i;	
			}		        
		}
       }
     }
    
	/**
	 * Set the contract preferences.  
	 *
	 * @param unknown_type $sender
	 */
    public function ContractCheckBoxListChanged($sender)
    {
	    $data = array();
	    $data =$sender->SelectedIndices;
	    foreach($data as $contract)
	    	$this->contractIds = $this->contractIds . $this->ContractCheckBoxList->Items[$contract]->Value . ',';
    }
	
    /**
     * Set the area preferences 
     *
     * 
     */
	public function AreaCheckBoxListChanged($sender)
    {
	    $data = array();
	    $data =$sender->SelectedIndices;
	    foreach($data as $area)
	    	$this->areaIds = $this->areaIds . $this->AreaCheckBoxList->Items[$area]->Value . ',';
    }
    
	/**
	 * Save contract and area preferences. 
     * 
     * @param String contractIds, areaIds
	 */   
 	public function setFacilityRequestPreferences()
    {
	   $this->ContractCheckBoxListChanged($this->ContractCheckBoxList);
	   $this->AreaCheckBoxListChanged($this->AreaCheckBoxList);
       Factory::service("UserPreference")->setOption($this->userAccount,'facilityRequestSelectedContracts',$this->contractIds);
       Factory::service("UserPreference")->setOption($this->userAccount,'facilityRequestSelectedAreas',$this->areaIds);
       $this->response->Redirect("/reserveparts/true");
    }
}
?>