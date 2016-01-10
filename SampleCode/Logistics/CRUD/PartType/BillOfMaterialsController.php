<?php
/**
 * Bill of Material Controller page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class BillOfMaterialsController extends CRUDPage
{
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext="logistics_billOfMaterials";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_billOfMaterials";
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
       	parent::onLoad($param);
		$this->FirstPanel->setVisible(false);
		if(!$this->IsPostBack || $param == "reload")
        {
			$this->DataList->EditItemIndex = -1;
			$this->dataLoad();
			$this->disableFields();
			$this->displayAddEditButton();

			$partCode = '';
			if (!empty($this->Request['id']))
			{
				$this->getBillofMaterialsPageTitle($this->request['id']);
				$this->hiddenPageSource->setValue("");
			}
			else
			{
				$this->FirstPanel->setVisible(true);
				$this->BackButton->setVisible(false);
				$this->AddButton->setVisible(false);

				$this->hiddenPageSource->setValue("ORIGINAL");
				$this->recipeForPartType->focus();
			}
        }
    }

    /**
     * Disable Fields
     *
     */
    public function disableFields()
    {
		$this->AddPanel->Visible = false;
		$this->AddButton->setVisible(false);
		foreach($this->DataList->items as $item)
		{
			$item->deleteBtn->setVisible(false);
			$item->editBtn->setVisible(false);
		}
    }

    /**
     * Create new entity
     *
     * @return unknown
     */
    protected function createNewEntity()
    {
    	return new BillOfMaterials();
    }

    /**
     * Lookup entity
     *
     * @param unknown_type $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("PartType")->getBillOfMaterial($id);
    }

    /**
     * Populate Add
     *
     */
 	protected function populateAdd()
    {
    	$this->Radio1->Checked = true;
    	$this->Radio2->Checked = false;
    	$this->newBOMPartType->Text = '';
    	$this->newBOMPartTypeGroup->Text = '';
    	$this->newBOMQty->Text = '';
    	$this->newComment->Text = '';
    	$this->AddButton->setVisible(false);
    }

    /**
     * Set entity
     *
     * @param unknown_type $object
     * @param unknown_type $params
     * @param unknown_type $focusObject
     * @return unknown
     */
    protected function setEntity(&$object,$params,&$focusObject = null)
    {
    	$oldSubPartQuantity = "";
    	$oldSubPartComments = "";

    	$ptString = $params->newBOMPartType->getSelectedValue();
    	$ptgString = $params->newBOMPartTypeGroup->getSelectedValue();

    	$qtyString = $params->newBOMQty->getText();
    	$cmtString = $params->newComment->getText();

    	if ((empty($ptString) && empty($ptgString)) || (empty($ptString) && $params->Radio1->Checked) || (empty($ptgString) && $params->Radio2->Checked))
    	{
    		$this->setErrorMessage("Please enter a valid part/part group code.");
    		return false;
    	}
    	else if (!is_numeric($qtyString) || $qtyString < 0)
    	{
    		$this->setErrorMessage("Qty must be a positive number.");
    		return false;
    	}

    	if(!empty($ptString) && $params->Radio1->Checked)
    	{
	    	$subPartType = Factory::service("PartType")->getPartType($ptString);
	    	if (empty($subPartType))
	    	{
				$this->setErrorMessage("Invalid materials.");
				$this->dataListDataLoad();
				return false;
	    	}
	    	else if ($subPartType->getId() == $focusObject->getId())
	    	{
	    		$this->setErrorMessage("Can't add material to itself.");
	    		$this->dataListDataLoad();
				return false;
	    	}
	    	else
	    	{
	    		$bomList = Factory::service("BillOfMaterials")->findByCriteria("parttypeid =? and requiredparttypeid =?", array($focusObject->getId(),$subPartType->getId()));
				if(count($bomList)>0)
				{
					$oldSubPartQuantity = $bomList[0]->getQuantity();
					$oldSubPartComments = $bomList[0]->getComments();
					$partCheck = 1;
				}
				else
					$partCheck = 0;

				$hasId = $object->getId();
				if(($partCheck == 1 && $hasId == "") || ($partCheck == 1 && $hasId != "" && $oldSubPartQuantity == $qtyString && $oldSubPartComments == $cmtString))
				{
	    			if($hasId == "")
	    				$this->setErrorMessage("Selected Material '$subPartType'' already in List");
	    			else if($hasId != "" && $oldSubPartQuantity == $qtyString && $oldSubPartComments == $cmtString)
	    				$this->setErrorMessage("No changes made");

	    			$this->dataListDataLoad();
					return false;
	    		}
	    		else
	    		{
					$object->setRequiredPartType($subPartType);
			    	$object->setRequiredPartTypeGroup(null);
			    	$object->setQuantity($qtyString);
			    	$object->setPartType($focusObject);
			    	$object->setComments($cmtString);

					Factory::service("PartType")->saveBillOfMaterials($object);

					//$this->onLoad("reload");
					if (empty($hasId))
						$this->setInfoMessage("Bill of Materials added.");
					else
						$this->setInfoMessage("Bill of Materials updated.");

					$this->dataListDataLoad();
	    		}
	    	}
    	}
    	else if(!empty($ptgString) && $params->Radio2->Checked)
    	{
    		$subPartTypeGroup = Factory::service("PartType")->getPartTypeGroup($ptgString);
	    	if (empty($subPartTypeGroup))
	    	{
				$this->setErrorMessage("Invalid materials.");
				$this->dataListDataLoad();
				return false;
	    	}
	    	else
	    	{
	    		$bomList = Factory::service("BillOfMaterials")->findByCriteria("parttypeid =? and requiredparttypegroupid =?", array($focusObject->getId(),$subPartTypeGroup->getId()));
				if(count($bomList)>0)
				{
					$oldSubPartQuantity = $bomList[0]->getQuantity();
					$oldSubPartComments = $bomList[0]->getComments();
					$partGroupCheck = 1;
				}
				else
	    			$partGroupCheck = 0;

	    		$hasId = $object->getId();

	    		if(($partGroupCheck == 1 && $hasId == "") || ($partGroupCheck == 1 && $hasId != "" && $oldSubPartQuantity == $qtyString && $oldSubPartComments == $cmtString))
				{
					if($hasId == "")
	    				$this->setErrorMessage("Selected Material '$subPartTypeGroup' already in List");
	    			else if($hasId != "" && $oldSubPartQuantity == $qtyString && $oldSubPartComments == $cmtString)
	    				$this->setErrorMessage("No changes made");

					$this->dataListDataLoad();
					return false;
	    		}
	    		else
	    		{
	    			$object->setRequiredPartTypeGroup($subPartTypeGroup);
			    	$object->setRequiredPartType(null);
			    	$object->setQuantity($qtyString);
			    	$object->setPartType($focusObject);
			    	$object->setComments($cmtString);
					Factory::service("PartType")->saveBillOfMaterials($object);

					//$this->onLoad("reload");
					if (empty($hasId))
						$this->setInfoMessage("Bill of Materials added.");
					else
						$this->setInfoMessage("Bill of Materials updated.");

					$this->dataListDataLoad();
	    		}
	    	}
    	}
    	else return false;
    }

    /**
     * Populate Edit
     *
     * @param unknown_type $editItem
     */
    protected function populateEdit($editItem)
    {
    	$pt = $editItem->getData()->getRequiredPartType();
    	if($pt != NULL)
    	{
	    	$editItem->Radio1->Checked = true;
    		$editItem->newBOMPartType->loadPartTypeId($pt->getId());
    		$editItem->newBOMPartTypeGroup->setDisplay('none');
    	}
    	else
    	{
    		$ptg = $editItem->getData()->getRequiredPartTypeGroup();
			if($ptg != NULL)
			{
		    	$editItem->Radio2->Checked = true;
	    		$editItem->newBOMPartTypeGroup->loadPartTypeGroupId($ptg->getId());
	    		$editItem->newBOMPartType->setDisplay('none');
			}
    	}
    	$editItem->newBOMQty->setText($editItem->getData()->getQuantity());
    	$editItem->newComment->setText($editItem->getData()->getComments());
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
    	return Factory::service("PartType")->getPartType($id);
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
    	Factory::service("BillOfMaterials")->getFocusEntityDAOQuery()->eagerLoad('BillOfMaterials.requiredPartType');
    	$result = null;
    	if (!empty($focusObject))
    		$result = Factory::service("BillOfMaterials")->findByCriteria('partTypeId = ? and requiredPartType.active = 1', array($focusObject->getId()), false, $pageNumber,$pageSize);
    	return $result;
    }

    /**
     * Show full part details
     *
     * @param unknown_type $billofmaterials
     * @return unknown
     */
    public function showFullPartDetails($billofmaterials)
    {
		$partType = $billofmaterials->getRequiredPartType();
		if($partType != NULL)
		{
		    $partCode = '';
	    	$aliasArr = $partType->getPartTypeAlias();
	    	foreach ($aliasArr as $al)
	    	{
	    		if ($al->getPartTypeAliasType()->getId() == 1 && $al->getActive())
	    			$partCode = $al->getAlias();
	    	}
			return $partCode." : ".$partType->getName();
		}
		else
		{
			$partTypeGroup = $billofmaterials->getRequiredPartTypeGroup();
			if($partTypeGroup != NULL)
			{
				return $partTypeGroup->getId()." : ".$partTypeGroup->getName();
			}
		}
    }

    /**
     * Show Mark
     *
     * @param unknown_type $billofmaterials
     * @return unknown
     */
	public function showMark($billofmaterials)
    {
		if ($billofmaterials->getRequiredPartType() != NULL  &&   $billofmaterials->getRequiredPartTypeGroup() == NULL )
						return '<b>PT<b/>';
		else if ($billofmaterials->getRequiredPartType() == NULL  &&   $billofmaterials->getRequiredPartTypeGroup() != NULL )
						return '<b>PTG<b/>';
		else return '';
    }

    /**
     * Go to Next Screen
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function goToNextScreen($sender, $param)
    {
    	$partTypeId = $this->recipeForPartType->getSelectedValue();
    	//Check if valid partTypeId - if not then display error message
    	if (!$partTypeId)
    	{
    		$this->setErrorMessage("Part type information should be provided!");
    		$this->FirstPanel->setVisible(true);
    		return;
    	}

    	$this->getBillofMaterialsPageTitle($partTypeId);
		$this->focusObject->setValue($partTypeId);
		$this->DataList->EditItemIndex = -1;
		$this->dataLoad();
		$this->disableFields();

		$this->BackButton->setVisible(true);
		$this->displayAddEditButton();
    }

    /**
     * Go Back To Previous Page
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function goBackToPreviousPage($sender, $param)
    {
    	if($this->hiddenPageSource->getValue() != "" && $this->hiddenPageSource->getValue() == "ORIGINAL") // It should be coming from the BOM page //MRAHMAN
    	{
    		$this->response->Redirect("/billofmaterials");
    	}
    	else
    	{
    		$this->response->Redirect("/parttypes/search/".$this->focusObject->getValue());
    	}
    }

    /**
     * Delete
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function delete($sender, $param)
    {
    	$bomId = $id = $this->DataList->DataKeys[$param->Item->ItemIndex];
    	$bom = Factory::service("PartType")->getBillOfMaterial($bomId);
    	Factory::service("PartType")->deleteBom($bom);

    	$this->AddButton->setVisible(true);
    	$this->BackButton->setVisible(true);
    	$this->dataListDataLoad();
	}

	/**
	 * DataList DataLoad
	 *
	 */
	private function dataListDataLoad()
	{
		$this->AddPanel->Visible = false;
		$this->DataList->EditItemIndex = -1;
		$this->dataLoad();
		$this->disableFields();
		$this->BackButton->setVisible(true);
		$this->displayAddEditButton();
	}

	/**
	 * Display Add Edit Button
	 *
	 */
    private function displayAddEditButton()
    {
   		$sql="select ft.id
				from role_feature x
				inner join feature ft on (ft.id = x.featureId)
				where ft.name in ('feature_edit_bill_of_materials','pages_all')
				and x.roleId = ".Core::getRole()->getId();

		$result = Dao::getResultsNative($sql);
		if(count($result)>0)
		{
			$this->AddButton->setVisible(true);

			foreach($this->DataList->items as $item)
			{
				$item->deleteBtn->setVisible(true);
				$item->editBtn->setVisible(true);
			}
		}
    }

    /**
     * Cancel
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
 	public function cancel($sender,$param)
    {
		$this->AddPanel->Visible = false;
		$this->BackButton->setVisible(true);
		$this->DataList->EditItemIndex = -1;
		$this->dataLoad();
		$this->displayAddEditButton();
    }

    /**
     * Get Bill Of Materials Page Title
     *
     * @param unknown_type $partTypeId
     * @return unknown
     */
    public function getBillofMaterialsPageTitle($partTypeId)
    {
    	$selectedPartType = Factory::service("PartType")->getPartType($partTypeId);
    	$this->focusObject->setValue($partTypeId);

    	foreach($selectedPartType->getPartTypeAlias() as $partTypeAlias)
		{
			if(strtoupper($partTypeAlias->getPartTypeAliasType())=='CODE NAME')
				$partCode = $partTypeAlias->getAlias();
		}
		if(!empty($selectedPartType))
		{
			return $this->BillOfMaterialsLabel->Text = "for " . $selectedPartType->getName() . " ( " . $partCode . " ) ";
		}
    }
}

?>
