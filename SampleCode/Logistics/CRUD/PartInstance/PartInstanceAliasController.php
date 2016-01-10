<?php
/**
 * PartInstance Alias Controller page
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class PartInstanceAliasController extends CRUDPage
{
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext="logistics_partInstanceAlias";
		$this->roleLocks = "pages_all,page_logistics_partInstanceAlias";
	}
	
	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
       	parent::onLoad($param);
		$this->AddButton->Visible=True;
       	if($this->Request['searchparttext'] != Null)
			$this->BackBtn->Visible=false;
       	
       	if(!$this->IsPostBack || $param == "reload")
        {        
			$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
			$this->dataLoad();
        }	
    }
    
    /**
     * Create new Entity
     *
     * @return unknown
     */
    protected function createNewEntity()
    {
    	return new PartInstanceAlias();
    }

    /**
     * Lookup entity
     *
     * @param unknown_type $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("PartInstance")->getPartInstanceAlias($id);
    }
    
    /**
     * Set entity
     *
     * @param unknown_type $object
     * @param unknown_type $params
     * @param unknown_type $focusObject
     */
    protected function setEntity(&$object,$params,&$focusObject = null)
    {
    	$object->setAlias($params->newPartInstanceAliasAlias->Text);
    	$PartInstanceAliasType = Factory::service("PartInstance")->getPartInstanceAliasType($params->newPartInstanceAliasType->getSelectedValue());
    	$object->setPartInstanceAliasType($PartInstanceAliasType);
    	$object->setPartInstance($focusObject);
    }
    
    /**
     * Save entity
     *
     * @param unknown_type $object
     */
    protected function saveEntity(&$object)
    {
    	try {
			Factory::service("PartInstance")->savePartInstanceAlias($object);
    	}
    	catch (HydraGenericUserException $e)
    	{
    		$this->setErrorMessage($e->getMessage());
    	}
    }

    /**
     * Populate Add
     *
     */
    protected function populateAdd()
    {
    	if(count(Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($this->Request['id'],1))>0)
	    	$this->newPartInstanceAliasType->DataSource = $this->getPartInstanceAliasTypeList();
	    else	
    		$this->newPartInstanceAliasType->DataSource = Factory::service("PartInstance")->getPartInstanceAliasTypes();
    	
    	$this->newPartInstanceAliasType->dataBind();
    	$this->AddButton->Visible=False;
    
    }
    
    /**
     * Get Existing Mandatory alias type list
     *
     * @param PartInstance $partInstance
     * @return unknown
     */
    public function getExistingMandatoryAliasTypeList(PartInstance $partInstance)
    {
    	$partType = $partInstance->getPartType();
    	$patterns = Factory::service('Lu_PartType_PartInstanceAliasPattern')->getMandatoryUniquePatternsForPtPiat($partType, null, true, null);
    	$mandatoryAliasIds = array(1);
    	foreach ($patterns as $pattern)
    	{
    		$mandatoryAliasIds[] = $pattern->getPartInstanceAliasType()->getId();
    	}
    	return $mandatoryAliasIds;
    }

    /**
     * Check Mandatory Alias Exists
     *
     * @param PartInstance $partInstance
     * @param unknown_type $partInstanceAliasTypeId
     * @return unknown
     */
    public function checkMandatoryAliasExists(PartInstance $partInstance, $partInstanceAliasTypeId)
    {
    	$compulsoryAliasIds="";
    	$compulsoryAliasIds=$this->getExistingMandatoryAliasTypeList($partInstance);
    	$index=array_search($partInstanceAliasTypeId,$compulsoryAliasIds);
    	if($index===false)
    		return false;
    	else 
    		return true;		
    }
    
    /**
     * Get the PartInstanceAliasTypes except SerialNo.
     *
     * @return unknown
     */
    public function getPartInstanceAliasTypeList()
    {
    	$data = array();
    	$partInstance = Factory::service("PartInstance")->getPartInstance($this->Request['id']);
    	$partInstanceAliasTypes = Factory::service("PartInstance")->getPartInstanceAliasTypes();
    	
    	foreach($partInstanceAliasTypes as $partInstanceAliasType)
    	{
    		if ($this->checkMandatoryAliasExists($partInstance,$partInstanceAliasType->getId())===false)
    		{
    			$data[]=array("id"=>$partInstanceAliasType->getId(),"name"=>$partInstanceAliasType->getName());
    		}	
    		
    	}
    	
    	array_unique($data);
    	if (!empty($data))
    	{
    		usort($data, create_function('$a, $b', 'return $a[\'id\'] - $b[\'id\'];'));
    	}
    	return $data;	
    }
    
    /**
     * Populate Edit
     *
     * @param unknown_type $editItem
     */    
    protected function populateEdit($editItem)
    {
    	$this->DataList->getEditItem()->newPartInstanceAliasType->DataSource = $this->getPartInstanceAliasTypeList();
    	$this->DataList->getEditItem()->newPartInstanceAliasType->dataBind();

    	$editItem->newPartInstanceAliasType->setSelectedValue($editItem->getData()->getPartInstanceAliasType()->getId());
    }    
    
    /**
     * Reset Fields
     *
     * @param unknown_type $params
     */
    protected function resetFields($params)
    {
    	$params->newPartInstanceAliasAlias->Text = "";
    }
    
    /**
     * Get Focus entity
     *
     * @param unknown_type $id
     * @param unknown_type $type
     * @return unknown
     */
    protected function getFocusEntity($id,$type="")
    {
    	return Factory::service("PartInstance")->getPartInstance($id);
    }    
    
    /**
     * Get all of entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	if($focusObject != null)
    		$res = Factory::service("PartInstance")->searchPartInstanceAliasesByPartInstanceId($focusObject->getId(),$pageNumber,$pageSize);
    	else
    		$res = Factory::service("PartInstance")->getPartInstanceAliases($pageNumber,$pageSize);
    	if (!empty($res))
			usort($res, create_function('$a, $b', 'return $a->getPartInstanceAliasType()->getId() - $b->getPartInstanceAliasType()->getId();'));
    	return $res;
    }
   
    /**
     * Redirect to partInstance
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function redirectToPartInstance($sender, $param)
    {
   		if($this->Request['searchparttext'] != Null)
    		$this->response->redirect("/partinstances/searchpart/".$this->Request['searchparttext']);
    	else
    		$this->response->Redirect("/partinstances/true");
    }

    /**
     * Delete Part instance alias
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function deletePartInstanceAlias($sender, $param)
    {
	    $partInstanceAliasId = $id = $this->DataList->DataKeys[$param->Item->ItemIndex];
	    $partInstanceAlias = Factory::service("PartInstance")->getPartInstanceAlias($partInstanceAliasId);
	    // except Serial Number, allow deletion/deactivation
	    if ($partInstanceAlias->getPartInstanceAliasType()->getId() != 1)
	    {
	    	$aliasName = $partInstanceAlias->getPartInstanceAliasType()->getName();
	    	$alias = $partInstanceAlias->getAlias();
		    Factory::service("PartInstance")->deletePartInstanceAlias($partInstanceAlias);
	    	$this->setInfoMessage($aliasName.' '.$alias.' deleted.');
	    }
	    else
	    {
	    	$this->setErrorMessage("Unable to delete Serial Number.");
	    }
		$this->dataLoad();
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

	/**
	 * Redirect to PartInstance admin
	 *
	 */
	public function redirectToPartInstanceAdmin()
	{
		$piId = $this->Request['id'];
		$searchstring = $this->Request['searchstring'];
		$this->Response->redirect('/partinstances/details/'.$piId.'/'.$searchstring);		
	}
}
?>