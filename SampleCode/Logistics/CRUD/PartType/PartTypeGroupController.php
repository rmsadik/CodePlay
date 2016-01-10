<?php
/**
 *  PartType Group Controller 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class PartTypeGroupController extends CRUDPage
{
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext="logistics_PartTypeGroup";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_PartTypeGroup";
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
     * Create New Entity
     *
     * @return unknown
     */
    protected function createNewEntity()
    {
    	return new PartTypeGroup();
    }

    /**
     * Lookup Entity
     *
     * @param unknown_type $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("PartType")->get($id);
    }
    
    /**
     * Set Entity
     *
     * @param unknown_type $object
     * @param unknown_type $params
     * @param unknown_type $focusObject
     */
    protected function setEntity(&$object,$params,&$focusObject = null)
    {
    	
 		$object->setName($params->newPartTypeGroupName->Text);
 		$hasId = $object->getId();	
    	
 		if ($object instanceof PartTypeGroup)
 		{
 			Factory::service("PartType")->savePartTypeGroup($object);
 		}
    	if (empty($hasId))
			$this->setInfoMessage("New part type group added.");
		else
			$this->setInfoMessage("Part type group updated.");
   		
    	
    	if(!($this->AddPanel->Visible))
    	{
	    	$causeCategoryIds = $params->causeCategoryList->getSelectedValue();
			$partTypeGroup = Factory::service("PartType")->getPartTypeGroup($object->getId());
		
			$partTypeGroup->setName(trim($params->newPartTypeGroupName->Text));
 			Factory::service("PartType")->savePartTypeGroup($partTypeGroup);
			
	    	$causeCategory = Factory::service("CauseCategory")->get($causeCategoryIds);
	    	if($partTypeGroup instanceOf PartTypeGroup && $causeCategory instanceOf CauseCategory)
	    		{
	    			Factory::service("PartTypeGroup")->addCauseCategoryToPartTypeGroup($partTypeGroup,$causeCategory);
	    		}
	    	if ($object instanceof PartTypeGroup)
	 		{
	 			Factory::service("PartType")->savePartTypeGroup($object);
	 		}
    	}
    }
    
	/**
	 * Delete Part Type Group Cause Category Relationship
	 *
	 */
    public function deletePartTypeGroupCauseCategoryRelationship($sender, $param)
	{
		$partTypeGroupId = $this->partTypeGroupValues->Value;
	    $causeCategoryId = $this->causeCategoryValues->Value;
	    $editItem = $this->editItem->Value;
		$partTypeGroup = Factory::service("PartType")->getPartTypeGroup($partTypeGroupId);
	    $causeCategory = Factory::service("CauseCategory")->get($causeCategoryId);
	    Factory::service("PartTypeGroup")->removeCauseCategoryFromPartTypeGroup($partTypeGroup, $causeCategory);
	   	//$this->loadCauseCategoryDetails($partTypeGroup , $editItem);
	   $this->dataLoad();
	}
	
	
    /**
	 * Loads Cause Category details for Part Type Group
	 * 
	 * @param unknown_type $partTypeGroup
	 */
	public function loadCauseCategoryDetails($partTypeGroup , $editItem)
	{
		$causeCategorys = $partTypeGroup->getCauseCategories();
	    $partTypeGroupId = $partTypeGroup->getId();
	    $html = '';
	    if(count($causeCategorys)>0)
	    {
	        //Display delete button only if System Admin
	        if(UserAccountService::isSystemAdmin())
	            $deleteAbility = true;
	        else
	            $deleteAbility = false;
	        
	        $html .="<table  class='DataList' rowId='".$partTypeGroupId."'>";
	        $html .="<thead>";
	        $html .="<tr>";
	        $html .="<td>Current Cause Categories</td>";
	        
	        if ($deleteAbility ===true)
	            $html .="<td width='5%'>&nbsp;</td>";
	        $html .="</tr>";
	        $html .="</thead>";
	        $html .="<tbody>";
	        $rowNo =0;
	       
	        foreach($causeCategorys as $causeCategory)
	        {
	        	if($causeCategory instanceof CauseCategory)
	        	{
	        	    $causeCategoryId = $causeCategory->getId();
	        	    $html .="<tr class='".($rowNo %2==0? "DataListItem" : "DataListAlterItem")."' id='".$causeCategoryId."'>";
	        	    $html .="<td>".$causeCategory->getCode()." - ".$causeCategory->getName()."</td>";
 
	        	    //If sys admin then delete capability
	        	    if ($deleteAbility ===true)
	        	    $html .="<td><input type='image' src='/themes/images/delete.png' onClick=\" if(confirm('Are you sure to delete this Cause Category?')){ ".$this->getId()."_deletePartTypeGroupCauseCategoryLink($partTypeGroupId,$causeCategoryId);}  return false;\"/></td>"; 
	        		$html .="</tr>";
	        	    $rowNo++;
	        	}
	        }
	        $html .="</tbody>";
	        $html.="</table>";
	    }
	    
	   $editItem->resultLabel->setText($html);
    
	}
	
	
    /**
     * Save Entity
     *
     * @param unknown_type $object
     */
    	
    protected function saveEntity(&$object)
    {
    	
		

    }
    
    /**
     * Reset Fields
     *
     * @param unknown_type $params
     */
    protected function resetFields($params)
    {
    	$params->newPartTypeGroupName->Text = "";
    }
    
    /**
     * Get all of Entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	if (isset($this->Request['id']))
    	{
  			$partTypeG = Factory::service("PartType")->getPartTypeGroup($this->Request['id']);
			$result = array($partTypeG); 

    	}
    	else
    	{
    	 	$result = Factory::service("PartType")->getPartTypeGroups($pageNumber,$pageSize);
			usort($result, "PartTypeGroupController::sortByName");     		
    	}
		return $result;
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
    	$result = Factory::service("PartType")->searchPartTypeGroup($searchString,$pageNumber,$pageSize);
		usort($result, "PartTypeGroupController::sortByName");    	
    	return $result;
    }
    
    
	/**
     * Populate Add
     *
     */
    public function populateAdd()
    {
    	$this->newPartTypeGroupName->Text="";
    }
    
    /**
     * Populate Edit
     *
     */
    
 	public function populateEdit($editItem)
    {
    	$partTypeGroup = $editItem->getData();
    	$editItem->newPartTypeGroupName->Text=$partTypeGroup->getName();
    	$this->bindListCauseCategory($editItem->causeCategoryList);
    	$this->loadCauseCategoryDetails($partTypeGroup, $editItem);
    }
    
    /**
     * Sort By Name
     *
     * @param unknown_type $a
     * @param unknown_type $b
     * @return unknown
     */
    public static function sortByName($a, $b)
    {
    	$cmp = strcmp($a->getName(), $b->getName());
    	if ($cmp == 0)
    		$cmp = ($a->getId() > $b->getId() ? 1 : -1);
    	return $cmp;
    }
	
    /**
     * Redirect to PartType
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function redirectToPartType($sender, $param)
    {
   		$this->response->Redirect("/parttypes/true");
    }
	
    /**
     * Delete PartType Group
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function deletePartTypeGroup($sender, $param)
    {
    	$partTypeGroupId = $id = $this->DataList->DataKeys[$param->Item->ItemIndex];
    	$partTypeGroup = Factory::service("PartType")->getPartTypeGroup($partTypeGroupId);  	
    	Factory::service("PartType")->deletePartTypeGroup($partTypeGroup);
    	$causeCategories = $partTypeGroup->getCauseCategories();
    	foreach($causeCategories as $causeCategory)
    	{
   			Factory::service("PartTypeGroup")->removeCauseCategoryFromPartTypeGroup($partTypeGroup, $causeCategory);
    	}
    	$this->onLoad("reload");
    }
    
    
	/**
     * Binding Cause Category
     *
     * @param $list
     */
    
	private function bindListCauseCategory(&$list)
    {
		
    	$causeCategory = Factory::service("CauseCategory")->findAll();
 
  		foreach($causeCategory as $cc)
	    {
	  		$data[]=array("id"=>$cc->getId(), "name"=>$cc->getId(). " - ". $cc->getCode(). " - ". $cc->getName());
		}
	    
       	$list->DataSource = $data;
    	$list->DataBind();
    }
    
    
 
    /**
     * List Cause Category
     *
     * @param ActionType $actionType
     * @return unknown
     */
    public function listCauseCategory(PartTypeGroup $partTypeGroup)
    {
    	$causeCategorys = $partTypeGroup->getCauseCategorys();
    	
    	$partTypeGroupId = $partTypeGroup->getId();
    	
    	$html ="<a href='javascript:void(0)' id='causeCategoryBtn_$partTypeGroupId' onclick=\"showOrHideCauseCategory('causeCategoryBtn_$partTypeGroupId','causeCategoryDiv_$partTypeGroupId','".count($causeCategorys)."'); return false;\">Show Cause Categories (".count($causeCategorys).")</a>";
    	$html .="<div id='causeCategoryDiv_$partTypeGroupId' style='display:none;'>";
	    	$html .="<ul style='list-style:disc; padding-left: 15px;'>";
		    	foreach($causeCategorys as $causeCategory)
		    	{
			    	$html .="<li>".$causeCategory->getName()."</li>";
		    	}
	    	$html .="</ul>";
    	$html .="</div>";
    	return $html;
 
    }
}

?>