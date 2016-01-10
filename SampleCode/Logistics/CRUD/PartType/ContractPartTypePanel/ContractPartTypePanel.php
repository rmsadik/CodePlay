<?php
/**
 * Contract PartType Panel
 * 
 * @package	Hydra-Web
 * @subpackage	Panel-Page
 * @version	1.0
 */
class ContractPartTypePanel extends TTemplateControl
{
	/**
	 * @var cssStyle
	 */
	public $cssStyle="";
	
	/**
	 * @var validationGroup
	 */
	public $validationGroup="";
	
	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
	{
		if(!$this->getPage()->getIsPostBack())
		{
			$this->getContractGroupList();
			$this->getContractList();
		}
	} 
	
	/**
     * This function is used to just get contractgrouplist for the dropdown
     */
	private function getContractGroupList()
	{
		$this->contractGroupList->DataSource = Factory::service("ContractGroup")->findAll();		
		$this->contractGroupList->DataBind();
		$this->contractGroupList->Enabled = false;
	}
	
	/**
     * Update contractlist selection based on contract group change from dropdown
     */
	public function getSelectedContractGroupList()
	{
		$contractgroupID = $this->contractGroupList->getSelectedValue();
		$contractArray = Factory::service("Contract")->findAll();		
		$contractGroupArray = Factory::service("Contract")->findByCriteria("c.contractGroupId = " .$contractgroupID );
		$temp = array();
		$count = 0;
	    foreach($contractArray as $ca)
        {
        	foreach($contractGroupArray as $cg)
        	{
        		if ($cg->getID() == $ca->getID())
        		{
        			$temp[]=$cg->getID();
        			$count++;
        		}
        	}
        }
        $this->contractList->setSelectedValues($temp);
	}
	
	/**
	 * Get ContractList
	 *
	 */
	private function getContractList()
	{
		$this->contractList->DataSource = Factory::service("Contract")->findAll();
		$this->contractList->DataBind();
	}	
	
	/**
	 * Get Contracts for Client
	 *
	 * @param unknown_type $clientId
	 */
	public function getContractsForClient($clientId)
	{
		$contractsArray = array();
	    $contractGroups = Factory::service("ContractGroup")->findByCriteria("clientId = ".$clientId);
	    if (count($contractGroups)>0)
	    {
		    foreach ($contractGroups as $cg)
		    {
		        $contractsArray = $cg->getContracts();
		    }
	    }
	    $this->contractList->DataSource = $contractsArray;
	    $this->contractList->DataBind();
	}
	
	/**
	 * Load Data
	 *
	 * @param array $contractIds
	 * @param unknown_type $partType
	 * @param unknown_type $contractGroupId
	 * @param unknown_type $clientId
	 */
	public function loadData(Array $contractIds,$partType,$contractGroupId, $clientId=null)
	{
		if ($partType->getSerialised() == true)
			$this->aliasPane->style="display:block";
		else 
			$this->aliasPane->style="display:none";
	    if ($clientId !='')
	        $this->getContractsForClient($clientId);
	    else
	    {
    		$this->getContractGroupList();
    	    if($contractGroupId != "" || $contractGroupId != NULL)
    	    {
    	        $this->contractGroupList->setSelectedValue($contractGroupId);
    	        $this->contractGroupCheck->setChecked(true);
    	        $this->contractGroupList->Enabled = true;
    	    }
    	    $this->getContractList();
	    }
    	if(count($contractIds)>0)
    	{
    	    $this->contractIds->Value =implode(",",$contractIds);
    	    $this->showContracts(null,null);
    	}
	    
	    $this->showAliases($partType);
	}
	
	/**
	 * Clear
	 *
	 */
	public function clear()
	{
		$this->contractIds->Value = "";
		$this->contractGroupList->setSelectedIndex(-1);
		$this->contractList->setSelectedIndex(-1);
		$this->contractGroupCheck->setChecked(0);
		$this->showContracts(null,null);
	}
	
	/**
	 * getter cssStyle
	 *
	 * @return cssStyle
	 */
	public function getCssStyle()
	{
		return $this->cssStyle;
	}
	
	/**
	 * setter cssStyle
	 *
	 * @var cssStyle
	 */
	public function setCssStyle($cssStyle)
	{
		$this->cssStyle = $cssStyle;
	}
	
	/**
	 * getter validationGroup
	 *
	 * @return validationGroup
	 */
	public function getValidationGroup()
	{
		return $this->validationGroup;
	}
	
	/**
	 * setter validationGroup
	 *
	 * @var validationGroup
	 */
	public function setValidationGroup($validationGroup)
	{
		$this->validationGroup = $validationGroup;
	}
	
	/**
	 * Add Contract
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function addContract($sender,$param)
	{
		$newArray = $this->contractList->getSelectedValues();
		$contractIds  = trim($this->contractIds->Value);
		if(trim($contractIds)!="")
			$contractIds = explode(",",$contractIds);
		else
			$contractIds = array();
		foreach($newArray as $newId)
		{
			if(!in_array($newId,$contractIds))
				$contractIds[] = $newId;
		}
		
		$this->contractIds->Value = implode(",",$contractIds);
		$this->showContracts($sender,$param);
	}
	
	/**
	 * Show Contracts
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function showContracts($sender,$param)
	{	
		$this->contractListLabel->Text="";
		$contractIds  = trim($this->contractIds->Value);
		if($contractIds=="")
			return ;
			
		$contractIds = explode(",",$contractIds);
		sort($contractIds);
		$html ="<table width='95%'>";
			$html .="<tr style='background:black; color:white; font-weight:bold; height:24px;'>";
				$html .="<th>Contract</th><th width='35px'>&nbsp;</th>";
			$html .="</tr>";
			$rowNo = 0;
			
			$currentContractGroup = $this->contractGroupList->getSelectedValue();
			$contractcount=0;
			$listcount = 0;
			foreach($contractIds as $contractId)
			{ 
				$contract = Factory::service("Contract")->get($contractId);
				$contractGroupIds = $contract->getContractGroup();
				$selectedContractGroup = $contractGroupIds->getID();
				
				if($selectedContractGroup == $currentContractGroup)
					$contractcount++;
			}
			// Validation to check if at least one contract has been added for the selected contract group
			if(($this->contractGroupCheck->getChecked() == true && $contractcount > 0) || $this->contractGroupCheck->getChecked() == false)
			{
				$listcount = count($contractIds);
				
				foreach($contractIds as $contractId)
				{ 
					$contract = Factory::service("Contract")->get($contractId);
					$contractGroupIds = $contract->getContractGroup();
					$selectedContractGroup = $contractGroupIds->getID();
					
					if(!$contract instanceof Contract)
						continue;
						
					$html .="<tr ".($rowNo %2==0 ? "" : "style='background: #cccccc;'").">";
						$html .="<td>$contract</td>";
						$html .="<td><input type='image' src='/themes/images/delete.png' onclick=\"deleteContract_".$this->getId()."($contractId,'$contract','".$this->contractIds->getClientId()."','".$this->showContractsBtn->getClientId()."','$currentContractGroup','$contractcount','$selectedContractGroup','$listcount');return false;\" /></td>";
					$html .="</tr>";
					$rowNo++;
				}
			}
		$html .="</table>";
		
		$this->contractListLabel->Text = $html;
	}
	
	/**
	 * Get Related ContractIds
	 *
	 * @return unknown
	 */
	public function getRelatedContractIds()
	{
		$contractIds  = trim($this->contractIds->Value);
		if(trim($contractIds)!="")
			$contractIds = explode(",",$contractIds);
		else
			$contractIds = array();
		$contractIds = array_unique($contractIds);
		return $contractIds;
	}
	
	/**
	 * Show Aliases
	 *
	 * @param unknown_type $partType
	 */
	public function showAliases($partType)
	{
		$luPtPiArray = Factory::service("Lu_PartType_PartInstanceAliasPattern")->getMandatoryUniquePatternsForPtPiat($partType,null,null,null);
		$ptId = $partType->getId();
		$html = "";
    	if (count($luPtPiArray)>0)
    	{
    		$this->aliasPane->style="display:block";
			$html .="<table width='95%'>";
			$html .="<tr style='background:black; color:white; font-weight:bold; height:24px;'>";
			$html .="<th>Alias</th><th>Mandatory?</th><th>Unique?</th>";
			$html .="</tr>";
			
			$rowNo = 0;
			
			foreach($luPtPiArray as $luPtPi)
			{
				$aliasType = $luPtPi->getPartInstanceAliasType()->getName();
				$mandatory = $luPtPi->getIsMandatory();
				$manChk = ($mandatory==1)?'checked':'';
				$unique = $luPtPi->getIsUnique();
				$unqChk = ($unique==1)?'checked':'';
			
				$html .="<tr ".($rowNo %2==0 ? "" : "style='background: #cccccc;'").">";
				$html .="<td>$aliasType</td>";
				$html .="<td><input type='checkbox' disabled $manChk></td>";
				$html .="<td><input type='checkbox' disabled $unqChk></td>";
				$html .="</tr>";
				$rowNo++;
			}
			$html .="</table>";
			
    	}
    	else
    		$html .= "No Alias has been added! <br />";
    	$html .= "<a href='#' onclick='var nextWnd = window.open(\"/compulsorypartinstancealiastype/$ptId\"); nextWnd.focus();'><img src='/themes/images/toalias.gif' alt='To Alias' title='Compulsory Part Instance Alias'></a>";
    	$this->aliasListLabel->Text = $html;
	}

	/**
	 * Link ContractGroup
	 *
	 * @param unknown_type $sender
	 */
	public function linkContractGroup($sender)
	{
		if($this->contractGroupCheck->getChecked() == true)
			$this->contractGroupList->Enabled = true;
		else
			$this->contractGroupList->Enabled = false;
	}
}

?>