<?php
/**
 * Custom Text Lookup Page
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 * @author  Mohamed Sathik <msathik@bytecraft.com.au>
 */
class CustomTextController extends CRUDPage
{
	/**
	 * @var totalRows
	 */
	private $totalRows;
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->roleLocks = "pages_all,pages_customtext";
	}
	
	/**
	 * onLoad
	 * 
	 * @param $param
	 */
	public function onLoad($param)
    {
       	parent::onLoad($param);
       	$this->setErrorMessage("");
       	$this->setInfoMessage("");
        $this->searching->setStyle("display:none;");

       	if(!$this->IsPostBack || $param == "reload")
        {        
			$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
			$this->_getEntityList(); 
			$this->dataLoad();
			
       	 	$this->ClassNameDataList->DataSource = CustomTextLogic::getDistinctClassNames(); 
       	 	$this->ClassNameDataList->dataBind(); 
       	 	
       	 	$active =array( 0=>array("id" => "ALL", "name" =>"ALL"), 1=>array("id" => 0, "name" =>"No"), 2=>array("id" => 1, "name" =>"Yes"));
       	 	$this->ActiveDataList->DataSource = $active; 
       	 	$this->ActiveDataList->dataBind(); 
       	 	
       	 	$workTypeArr = array();
       	 	$distinctWorkTypeArr = CustomTextLogic::getDistinctWorkTypes();
       	 	foreach($distinctWorkTypeArr as $rows)
       	 	{
       	 		$workTypeArr[] = $rows[0];	
       	 	}

       	 	$this->WorkTypesDataList->DataSource = WorkTypeLogic::getWorkTypeList($workTypeArr);
       	 	$this->WorkTypesDataList->dataBind(); 
			
        }
    }
    
    /**
     * Get Fields
     *
     */
    public function getFields()
    {
     	$className = $this->ClassNameDataList->getSelectedValue();
        
        $this->ActiveDataList->Enabled=true;
        $this->SearchButton->Enabled=true;
        $this->reset->Enabled=true;
     	$this->FieldNameDataList->Enabled=true;
        $this->WorkTypesDataList->Enabled=true;
     	
     	if($className)
     	{
     		$arr = array('field' => 'Please Select....');
	     	$this->FieldNameDataList->DataSource = array_merge($arr, CustomTextLogic::getDistinctFieldNames($className)); 
	       	$this->FieldNameDataList->dataBind(); 
     	}
     	
    }
    
    /**
     * new instance of CustomText
     * 
     * @return CustomText
     */
    protected function createNewEntity()
    {
    	return new CustomText();
    }

    /**
     * Return Custom Text details for id
     * 
     * @param $id
     * @return Custom Text object
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("CustomText")->get($id);
    }
    
    /**
     * Set the object values
     * 
     * @param $object
     * @param $params
     * @param &$focusObject
     */
    protected function setEntity(&$object,$params,&$focusObject = null)
    {
		$this->setErrorMessage("");
    	$workTypeIds = $params->workTypeList->getSelectedValues();
    	if(count($workTypeIds)==0)
    	{
			$this->setErrorMessage("Please select Atleast one worktype");
			return false;    		
    	}
    	else if($params->entityList->Text == "")
    	{
			$this->setErrorMessage("Please select the class/table name");
			return false;    		
    	}
    	else if($params->fieldList->Text == "")
    	{
			$this->setErrorMessage("Please select the field name");
			return false;    		
    	}
    	else if($params->text->Text == "")
    	{
			$this->setErrorMessage("Please Provide custom text");
			return false;    		
    	}
    	
    	$infoMsg="";
    	$errorMsg="";
    	for($i=0;$i<count($workTypeIds);$i++)
    	{
    		
	    	$workType = Factory::service("WorkType")->getWorkType($workTypeIds[$i]);
	    	try
	    	{
	    		Factory::service("CustomText")->add($params->entityList->getText(),$params->fieldList->getText(),$workType,$params->text->Text);
		    	$infoMsg .= "Successful Added/Updated '".$params->text->Text."'(".$params->entityList->getText()." ->".$params->fieldList->getText()." ) for '".$workType->getContract()->getContractName()." - ".$workType->getTypeName()."' </br> \n";
	    	}
	    	catch (Exception $e)
	    	{
		    	$errorMsg .= "Error Adding/Updating '".$params->text->Text."'(".$params->entityList->getText()." ->".$params->fieldList->getText().") for '".$workType->getContract()->getContractName()." - ".$workType->getTypeName()."' ".$e->getMessage()." </br> \n";
	    	}
    	}
    	$this->setInfoMessage($infoMsg);
    	$this->setErrorMessage($errorMsg);
    	
		$this->AddPanel->Visible = false;
		$this->DataList->EditItemIndex = -1;
		$this->dataLoad();

    }
    
    /**
     * Saves the object details
     * 
     * @param &$object
     */
    protected function saveEntity(&$object)
    {
		Factory::service("CustomText")->save($object);
    }

    /**
     * Reset or clear all fields
     * 
     * @param $params
     */
    protected function resetFields($params)
    {
//		$params->entityList->Text = "";
//		$params->fieldList->setSelectedvalue("");
//		$params->worktype->Text = "";
		$params->text->Text = "";
    }

    /**
     * Get CustomText details
     * 
     * @param $id
     * @param $type
     * 
     * @return CustomText object
     */
    protected function getFocusEntity($id,$type="")
    {
    	return Factory::service("CustomText")->get($id);
    }        
    
    /**
     * Setting up panel for Add
     */
   	public function populateAdd()
	{
		$this->bindWorkTypes($this->workTypeList);
	}

	/**
     * Setting up panel for Edit
     * 
     * @param $editItem
     */
	public function populateEdit($editItem)
	{
		//Added get last updated information
		$customText = $editItem->getData();
		$editItem->Updated->Text = $editItem->getData()->getUpdated();
		$editItem->UpdatedById->Text = $editItem->getData()->getUpdatedBy()->getPerson();
		$editItem->entityList->Enabled = false;
		$editItem->fieldList->Enabled = false;
		
		$this->bindWorkTypes($editItem->workTypeList);
		
    	$workTypeId = $customText->getWorkType()->getId();
    	$editItem->workTypeList->setSelectedValue($workTypeId);
    	
	}    
    
	/**
     * Retrieve all CustomText details 
     * 
     * @param $focusObject
     * @param $pageNumber
     * @param $pageSize
     * 
     * @return CustomText object
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	if($focusObject == null)
    	{
    		$this->DataList->setAllowCustomPaging(true);
    		$data = Factory::service("CustomText")->findAll(true,$pageNumber,$pageSize);
    		$this->ItemCountValue->Value = Dao::getTotalRows();
    	}
    	else 
    	{
    		$this->DataList->setAllowCustomPaging(false);
    		$data = $focusObject->getClass();
    		$this->ItemCountValue->Value = sizeof($data);
    	}
    	return $data;    	
    }
    
    /**
     * search functionality 
     * 
     * @param String $searchString
     * @param &$focusObject
     * @param $pageNumber
     * @param $pageSize
     * 
     * @return CustomText object 
     */
    protected function searchEntity($searchString,&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	if ($this->searchActiveFlag->getSelectedValue() != "All")
    	{
    		$active = $this->searchActiveFlag->getSelectedValue() == "No" ? "0" : "1";
	    	$searchString = "c.active = $active";
    	}
    	return Factory::service("CustomText")->find($searchString,false,$pageNumber,$pageSize);		
    }
    
    /**
     * Bind WorkTypes
     *
     * @param unknown_type $list
     */
	private function bindWorkTypes(&$list)
    {
    	$workTypes = Factory::service("WorkType")->findAll();
       	$worktype_names = array();
       	foreach($workTypes as $wt)
       	{
       		$worktype_names[]=$wt->getContract()." - ".$wt->getTypeName();
       	}
       	
       	array_multisort($worktype_names,SORT_ASC,$workTypes);
    	$list->DataSource = $workTypes;
    	$list->DataBind();
    }

    /**
     * Change Entity
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function changeEntity($sender, $param)
    {
    	$entities = $this->_getFields($sender->getSelectedValue());
    	$this->fieldList->DataSource = $entities;
    	$this->fieldList->DataBind();
    }
    
    /**
     * Get field list of the class
     *
     * @param string $className
     * 
     * @return array[]
     */
    private function _getFields($className)
    {
    	if(trim($className) === '')
    		return array();
    	
    	$entities = array();
    	$sql ="show columns from " . strtolower($className);
    	foreach (Dao::getResultsNative($sql) as $row)
    	{
    		if(!in_array($row[0], array('id', 'active', 'updated', 'updatedById', 'created', 'createdById')))
    			$entities[$row[0]] = $row[0];
    	}
    	
    	return $entities;
    }
    
    /**
     * Get entity list
     */
    private function _getEntityList()
    {
    	$entityFolderPath = $this->getApplication()->getBasePath() . "/../../../hydra-core/main/entity";
    	$entityClassArray = $this->_directoryToArray($entityFolderPath, true, array('.', '..', 'HydraEntity.php' ,'HydraVersionedEntity.php' ,'HydraTreeNode.php' ,'TreeTable.php' ,'.svn'));
    	array_unshift($entityClassArray, array('Please Select ...', ' '));
    	sort($entityClassArray,SORT_ASC);
    	$this->entityList->DataSource = $entityClassArray;
    	$this->entityList->DataBind();
    }
    
    /** Get all the entity names in an array
     *
     * @param string   $directory
     * @param boolean  $recursive
     * @param string[] $exclude
     * 
     * @return string[]
     */
	private function _directoryToArray($directory, $recursive=true, array $exclude=array())
	{
		$array_items = array();
		if ($handle = opendir($directory)) 
		{
			while (false !== ($file = readdir($handle))) 
			{
				if(!in_array($file,$exclude)) 
				{
					if (is_dir($directory. "/" . $file) && $recursive) 
						$array_items = array_merge($array_items, $this->_directoryToArray($directory. "/" . $file, $recursive,$exclude));
					
					if (is_file($directory . '/' . $file))
					{
						$entityName = str_replace('.php', '', $file);
						$array_items[] = array($entityName, $entityName);
					}
				}
			}
			closedir($handle);
		}
		return $array_items;
	}

	/**
	 * Search Data
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 * @return unknown
	 */
     protected function searchData($sender, $param)
	 {
	 	$this->resetReload();
	 	$className = $this->ClassNameDataList->getSelectedValue();
	 	$fieldName = $this->FieldNameDataList->getSelectedValue();
	 	
	 	if($fieldName == 'field')
	 	{
	 		$fieldName = '';	
	 	}
	 	$sql = " ";
	 	$active = $this->ActiveDataList->getSelectedValues();
    	$active = ($active[0]== "ALL")?"(0,1)":(($active[0]== 0)?"(0)":"(1)");
	 	
    	$sql .= " ct.active in " . $active; 
	 	
	 	if($className)
	 	{
	 		$sql .= " and ct.class = '" . $className . "' ";	
	 	}
	 	
	 	if($fieldName)
	 	{
	 		$sql .= " and ct.field = '" . $fieldName . "' ";	
	 	}
	 	
	 	$workTypes = $this->WorkTypesDataList->getSelectedValues();
		if(count($workTypes) > 0)
	 	{
	 		$sql .= " and ct.workTypeId in (" . implode(",", $workTypes) . ") ";	
	 	}
	 	
	 	$pageNumber = $this->DataList->CurrentPageIndex;
    	$pageSize = $this->DataList->pageSize;  
	 	
	 	//for pagination
//		$results_count = Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC);
		
//		$sql .= " limit " . ($pageNumber*$pageSize) . ",". ($pageSize+($pageNumber*$pageSize));
	 	$pageNumber = $pageNumber*$pageSize;
    	$pageSize = $pageSize+($pageNumber*$pageSize);  
		
//		Dao::$Debug=true;
		 $results =  Factory::service("CustomText")->findByCriteria($sql,array());
//		Dao::$Debug=false;	
		 	
		if(sizeof($results)== "" || count($results)==0)
			$this->setErrormessage("No Results found");

    	$count = (count($results)>0?count($results):1);
    	$this->DataList->CurrentPageIndex = $count;
    	
		$this->DataList->DataSource = $results; 
		$this->DataList->dataBind();
		
		$this->ListingPanel->Visible = true;
    	
    	$this->totalRows = count($results);
    	$this->DataList->VirtualItemCount=$count;
    	$this->DataList->DataBind();
    	$this->PaginationPanel->Visible=false;
    	
    	$this->DataList->setPageSize($count);
    	
		if($count > 30)
			$this->PaginationPanel->Visible=true;
		else
    		$this->PaginationPanel->Visible=false;

	 }
	     
	 /**
	  * Reset Reload
	  *
	  */
	public function resetReload()
    {
//    	$this->ListingPanel->Visible = false;
//		$this->PaginationPanel->Visible=false;
//		$this->AddPanel->style = "display:none";	
    }

}

?>