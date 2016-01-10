<?php
/**
 * Email Broadcasting Controller
 *
 * @package Hydra-Web
 * @subpackage Controller-BulkloadPage
 * @version 1.0
 */
class EmailBroadcastingController extends HydraPage
{
	/**
	 * @array emailBroadcastingReceivers
	 */
	private $emailBroadcastingReceivers= array("logisticsnetwork@bytecraft.com.au");
	
	/**
	 * @var facilityRequest
	 */
	private $facilityRequest;
	
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->facilityRequest = Factory::service("FacilityRequest")->get($this->Request["facilityRequestId"]);
		if(!$this->facilityRequest instanceof FacilityRequest)
			die("Invalid Facility Request!");
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
		if(!$this->getPage()->getIsPostBack())
		{
			$this->loadEmail();
		}
	}
	
	/**
	 * Load Email
	 *
	 * @param unknown_type $receivers
	 * @param unknown_type $action
	 */
	public function loadEmail($receivers="",$action=0)
    {
    	try
    	{
			$facilityRequest = $this->facilityRequest;
			if(!$facilityRequest instanceof FacilityRequest)
				return;
    		
			$fieldTask = $facilityRequest->getFieldTask();
			$partType = $facilityRequest->getPartType();
			$partTypeAlias = Factory::service("PartType")->getPartTypeAliasByTypeForPartType($partType->getId(),1);
	       	$slaTime =  Factory::service("FieldTaskTarget")->getClientClosedTaskTarget($fieldTask);
			$slaTime = count($slaTime)>0 ? $slaTime[0]->getTargetEndTime() : "";
				
    		$this->toAddress->Text = implode(";",$this->emailBroadcastingReceivers);
			$this->title->Text = "Pending Bytecraft Task ID: ".$fieldTask->getId() ." for part: (".$partType->getAlias().")$partType";
			$this->titleText->Text = $this->title->Text;
			
			$newLine = "\n";
	       	$facilityRequestDetails = "$newLine===== PART REQUISITION ======================$newLine";
	       	$partCode =trim($partType->getAlias());
			$facilityRequestDetails .= 'Part Code:'.($partCode!="" ? $partCode : "UNKNOWN"). $newLine;
	        $facilityRequestDetails .= 'Part Description:'.$partType. $newLine;
			
	       	$facilityRequestDetails .= "$newLine===== Field Task Details ======================$newLine";
			$facilityRequestDetails .= "Pending Bytecraft Task ID: ".$fieldTask->getId().$newLine;
	       	$facilityRequestDetails .= "Client Ref No.:".$fieldTask->getClientFieldTaskNumber().$newLine;
				
			$zsName = $fieldTask->getAddress()->getZone()->getZoneSet()->getName();
			$facilityRequestDetails .= "Zone Set:$zsName$newLine";
			$facilityRequestDetails .= "Contract Name: ".$fieldTask->getWorkType()->getContract()->getContractName()." - ".$fieldTask->getWorkType()->getTypeName().$newLine;
			$facilityRequestDetails .= "Site Code: ".$fieldTask->getSite()->getSiteCode().$newLine;
			$facilityRequestDetails .= "Site Name: ".$fieldTask->getSite()->getCommonName().$newLine;
	       	$facilityRequestDetails .= "Site Address: ".$fieldTask->getAddress().$newLine;
	       	$facilityRequestDetails .= "Task Priority: ".$fieldTask->getJobPriority().$newLine;
	       	$facilityRequestDetails .= "Task Creation Time: ".$fieldTask->getCreated()->getNewHydraDateConvertedToSiteTimeZone($fieldTask->getSite()).$newLine;
	        $facilityRequestDetails .= "Task SLA Close: ". $slaTime . $newLine;
	    
			$facilityRequestDetails .= "$newLine===== Comments ======================$newLine";
			$notesArray = explode(" [!",$facilityRequest->getComment());
			$facilityRequestDetails .= $this->getNotesToDisplay($notesArray) . $newLine;
			
			$this->body->Text = $facilityRequestDetails;
			$this->bodyText->Text = str_replace($newLine,"<br />",$facilityRequestDetails);
	        
    	}
    	catch(Exception $ex)
    	{
    		$this->setErrorMessage($ex);
    		$this->MainContent->Enabled=false;
    	}
    }
    
	/**
     * Get Notes to Display.
     *
     * @param string[] $notes
     * @return string[] $prepareNotes;
     */
    public function getNotesToDisplay($notesArray)
    {
    	$prepareNotes = "";
        if($notesArray != "")
        {
			for($i=count($notesArray)-1; $i>=0; --$i)
			{
				if(strlen(trim($notesArray[$i], "!]")) > 0)
				{
					$prepareNotes .= trim($notesArray[$i], "!]") . "\n";
				}
			}	
        }
   		return $prepareNotes;
    }
    
    /**
     * Send Email
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function sendEmail($sender,$param)
    {
    	$emailaddr = explode(";",trim($this->toAddress->Text));
    	
    	$timeZone =Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
    	$now = new HydraDate("now",$timeZone);
    	$body = trim($this->body->Text);
    	$comment = Core::getUser()->getPerson()." - ".$now." ($timeZone) - ".trim($this->comments->Text)." ( Emailed request to ".trim($this->toAddress->Text).")";
    	$body .="\n".$comment;
    	
    	$this->facilityRequest->setComment($this->facilityRequest->getComment()." [!".$comment."!]");
    	$this->facilityRequest->setAttended(true);
    	Factory::service("FacilityRequest")->save($this->facilityRequest);
    	
    	$this->loadEmail();
	    Factory::service("Message")->email($emailaddr,trim($this->title->Text),$body);
	    $this->setInfoMessage("Successfully send to ".trim($this->toAddress->Text)."!");
	    
	    $fieldTask = $this->facilityRequest->getFieldTask();
	    Logging::LogEmail($fieldTask->getId(),get_class($fieldTask),$body,Core::getUser()->getPerson(). " - $now($timeZone) - Sent Email to ".trim($this->toAddress->Text),$now);
    }
}
?>