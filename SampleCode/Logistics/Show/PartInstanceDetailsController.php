<?php
/**
 * Part Instance Details Controller Page
 *
 * @package Hydra-Web
 * @subpackage Controller-Page
 * @version 1.0
 */
class PartInstanceDetailsController extends CRUDPage
{
	/**
	 * @var unknown_type
	 */
	protected $querySize;

	/**
	 * @var unknown_type
	 */
	public $partTypeId;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->openFirst = true;
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_partInstanceDetails";
		$this->querySize = 0;
	}

	/**
	 * On Pre Initialization
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
		if ($this->Request['for']=='field')
		{
			if(Core::getRole()->getId()==6 || Core::getRole()->getId() == 30) //agent Technician OR Agent Logistics
			$this->getPage()->setMasterClass("Application.layouts.AgentViewLayout");
			else if(Core::getRole()->getId()==7) //client Technician
			$this->getPage()->setMasterClass("Application.layouts.ClientViewLayout");
			else
			$this->getPage()->setMasterClass("Application.layouts.FieldTechLayout");
			$this->menuContext = 'showpartdetails';
		}
		else if ($this->Request['for']=='staging')
		{
			$this->getPage()->setMasterClass("Application.layouts.StagingLayout");
			$this->menuContext = '/staging/showpartdetails';
		}
		else if ($this->Request['for']=='calldesk')
		{
			$this->getPage()->setMasterClass("Application.layouts.CallDeskLayout");
			$this->menuContext = '/calldesk/showpartdetails';
		}
		else
		{
			$this->getPage()->setMasterClass("Application.layouts.LogisticsLayout");
			$this->menuContext = 'showpartdetails';
		}
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
		$this->PaginationPanel->Visible = true;
		$this->Page->jsLbl->Text = '';
		if(!$this->IsPostBack || $param == "reload")
		{
			$tmpSearchString = $this->SearchString->getValue();
			if(isset($this->Request['id']) && empty($tmpSearchString))
			{
				$this->focusObject->Value = $this->Request['id'];
				$this->searchActiveFlag->setSelectedValue('All');
				$fE = $this->getFocusEntity($this->Request['id'],$this->Request['searchby']);
				$data = $this->searchEntity($this->focusObject->getValue(),$fE, 0, 1);
				$size = sizeof($data);
				if($size>0)
				{
					$this->DataList->DataSource = $data;
					$this->DataList->VirtualItemCount = $size;
					$this->DataList->EditItemIndex = 0;
					$this->DataList->dataBind();
					$this->populateEdit($this->DataList->getEditItem());
					$this->postDataLoad();
				}
			}
			else
			{
				$this->AddPanel->Visible = false;
				$this->DataList->EditItemIndex = -1;
				$this->dataLoad();
			}

			$aliasTypes = Factory::service("PartInstanceAliasType")->findAll();
			usort($aliasTypes, create_function('$a, $b', 'return $a->getId() - $b->getId();'));

			$this->aliasType->DataSource = $aliasTypes;
			$this->aliasType->DataBind();

			if($_SERVER ["PATH_INFO"] ==  "/showpartdetails" || $_SERVER ["PATH_INFO"] ==  "/displaypartdetails/field/" || $_SERVER ["PATH_INFO"] ==  "/calldesk/showpartdetails/" || $_SERVER ["PATH_INFO"] == "/staging/showpartdetails/")
				$this->outputToExcel->Visible=false;
		}
		else
		{
			//echo "fsdfsdf";
		}
	}

	/**
	 * DataLoad
	 *
	 * @param unknown_type $pageNumber
	 * @param unknown_type $pageSize
	 */
	public function dataLoad($pageNumber=null,$pageSize=null)
    {
    	parent::dataLoad();
    	//$this->setErrorMessage('');
    	if(count($this->DataList->DataSource) > 0)
    		$this->ListingPanel->Visible=true;
    	else
    		$this->ListingPanel->Visible=false;
    }

    /**
     * Results Per Page Changed
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function resultsPerPageChanged($sender, $param)
    {
    	$this->AddPanel->Visible = false;
    	$this->DataList->EditItemIndex = -1;
		$this->DataList->pageSize = $param -> NewPageResults;
      	$this->dataLoad();
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
		return array();
	}

	/**
	 * How Big Was That Query
	 *
	 * @return unknown
	 */
	protected function howBigWasThatQuery()
	{
		return $this->querySize;
	}

	/**
	 * Breadcrumb WarehouseId
	 *
	 * @param unknown_type $id
	 * @return unknown
	 */
	protected function breadCrumbWarehouseId($id)
	{
		$warehouse = Factory::service("Warehouse")->getWarehouse($id);
		return $this->breadCrumbWarehouse($warehouse);
	}

	/**
	 * Breadcrumb Warehouse
	 *
	 * @param Warehouse $warehouse
	 * @return unknown
	 */
	protected function breadCrumbWarehouse(Warehouse $warehouse)
	{
		return Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,"/");
	}

	/**
	 * Get LocationId
	 *
	 * @param unknown_type $partInstanceId
	 * @return unknown
	 */
	public function getLocationById($partInstanceId)
	{
		$partInstance = Factory::service("PartInstance")->get($partInstanceId);
		if(!$partInstance instanceof PartInstance)
		return;
		return $this->getLocation($partInstance);
	}

	/**
	 * Get Location
	 *
	 * @param PartInstance $partInstance
	 * @return unknown
	 */
	private function getLocation(PartInstance $partInstance)
	{
		try
		{
			$location = $this->breadCrumbWarehouse($partInstance->getRootWarehouse());
			if($partInstance->getDirectParent() instanceof PartInstance )
			$location  = "(within another part at location :$location)";

			$site = $partInstance->getSite();
			if($site instanceof Site && $site->getSiteCode() != null && $site->getCommonName())
			$location .= ' (' . $site->getSiteCode() . ':' . $site->getCommonName() . ')';
		}
		catch (Exception $e)
		{
			return '<span style="color:red;">' . $e->getMessage() . '</span>';
		}

		return $location;
	}

	/**
	 * Get Focus Entity
	 *
	 * @param unknown_type $id
	 * @param unknown_type $type
	 * @return unknown
	 */
	protected function getFocusEntity($id,$type="")
	{
		switch(strtolower($type))
		{
			case "id":
				return $id;
				break;
			case "barcode":
				$this->SearchText->Text = $id;
				$this->SearchString->Value = $id;
				return null;
				break;
			default:
				break;
		}
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

		$serialNoIndexCtr = 5;
		$piAliasCtr = 3;
		Dao::$AutoActiveEnabled=false;

		$searchString = trim($searchString);

		//required values in order to retrieve data in getPartInstanceDetailsByAliasOrPartInstance function
		$activeFlag = $this->searchActiveFlag->getSelectedValue();
		$partInstance = Factory::service("PartInstance")->getPartInstance($focusObject);
		$aliasTypeId = $this->aliasType->getSelectedValue();
		$aliasType = Factory::service("PartInstance")->getPartInstanceAliasType($aliasTypeId);
		$pageSearchString = $this->SearchString->Value;

		//0 - to get all the data regardless of pagination.(exporting data to excel)
		//1 - to get all the data based on pagination.(for data list)
		for($i=0; $i<=1;$i++)
		{
			//modified to use service function rather than direct sql
			$result = Factory::service("PartInstance")->getPartInstanceDetailsByAliasOrPartInstance($searchString,$pageSearchString,$partInstance,$pageNumber,$pageSize,$activeFlag,$aliasType,$i,$this->querySize);
			// added/moved by Octa Karsono 12/09/2011
			// now grab all other aliases/serialnos separately, displaying active/inactive flag
			foreach ($result as $keyIndex => $row)
			{
				//modified to use service function rather than direct sql
				$partInstanceAliasTypeId=1;
				$tmpRes = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($row[0],$partInstanceAliasTypeId);
				$tmpAlias = Factory::service("PartInstance")->searchPartInstanceAliasesByPartInstanceId($row[0]);
				$serialNos = array();
				$piAliases = array();
				foreach ($tmpAlias as $tmpRow)
				{
					if ($tmpRow instanceof PartInstanceAlias)
					{
						$active = $tmpRow->getActive();
						$alias = $tmpRow->getAlias();
						$piatId = $tmpRow->getPartInstanceAliasType()->getId();
						$piatName = $tmpRow->getPartInstanceAliasType()->getName();
						if ($active == 0)
						{
							if ($i==1)
							{
								if ($piatId == 1)
									$serialNos[] = $alias.' <img src="/themes/images/deactivate.jpg" alt="Inactive" title="Inactive" />';
								else
									$piAliases[] = "<b>".$piatName.": </b>".$alias.' <img src="/themes/images/deactivate.jpg" alt="Inactive" title="Inactive" />';
							}
							else
							{
								if ($piatId == 1)
									$serialNos[] = $alias."(Deactivated)";
								else
									$piAliases[] = "<b>".$piatName.": </b>".$alias."(Deactivated)";
							}
						}
						else
						{
							if ($piatId == 1)
								array_unshift($serialNos,$alias);
							else
							{
								$val = "<b>".$piatName.": </b>".$alias;
								array_unshift($piAliases,$val);
							}
						}

					}
				}
				if($i==1)
				{
					$result[$keyIndex][$serialNoIndexCtr] = join('<br/>', $serialNos);
					$result[$keyIndex][$piAliasCtr] = join('<br/>', $piAliases);
				}
				else
				{
					$result[$keyIndex][$serialNoIndexCtr] = join(' , ', $serialNos);
					$result[$keyIndex][$piAliasCtr] = join(' , ',  $piAliases);
				}
			}


			if (empty($result) && !empty($searchString))
			{
				$this->setInfoMessage("No result matches ".$searchString.'.');
			}

			if($i!=1)
			{
				if($_SERVER ["PATH_INFO"] ==  "/showpartdetails")
				{
	     			$this->searchData->Value = serialize($result);
					$this->outputToExcel->Visible=true;
				}
			}

		}

		Dao::$AutoActiveEnabled=true;
		return $result;
	}

	/**
	 * Get Active Flag
	 *
	 * @param unknown_type $active
	 * @return unknown
	 */
	public function getActiveFlag($active)
	{
		if ($active == 1)
		{
			return "/themes/images/small_yes.gif";
		}
		else
		{
			return "/themes/images/small_no.gif";
		}

	}

	/**
	 * Implode Walk
	 *
	 * @param unknown_type $array
	 * @param unknown_type $method
	 * @param unknown_type $delimiter
	 * @param unknown_type $params
	 * @return unknown
	 */
	protected function implodeWalk($array,$method,$delimiter,$params=array())
	{
		$string = "";
		$first = true;
		foreach($array as $item)
		{
			if(!$first)
			$string .= $delimiter;
			else
			$first = false;

			$string .= call_user_func_array(array(&$item,$method),$params);
		}
		return $string;
	}

	/**
	 * Populate Edit
	 *
	 * @param unknown_type $editItem
	 */
	protected function populateEdit($editItem)
	{
		$data = $editItem->getData();
		$id = $data[0];

		$partInstance = Factory::service("PartInstance")->get($id);
		$partType = $partInstance->getPartType();
		$htMessage ="";

		if ($partInstance->getActive() == 1)
			$editItem->partInstanceActive->Text="<img src='/themes/images/small_yes.gif' />";
		else
			$editItem->partInstanceActive->Text="<img src='/themes/images/small_no.gif' /> <font style='color:#ff0000;font-weight:bold;'>This part has been deactivated</font>";

		$editItem->partTypeOwner->Text = $partType->getOwnerClient();
		$editItem->partTypeKitType->Text = $partType->getKitType();
		$warehouse = $partInstance->getWarehouse();
		$editItem->partInstanceWarehouse->Text = $this->getLocation($partInstance);
		$sharedContracts = $partType->getContracts();

		$editItem->partInstanceStatus->Text = $partInstance->getPartInstanceStatus();
		$editItem->partInstanceQuantity->Text = $partInstance->getQuantity();

		//modified to use service function rather than direct sql
		$aliases = Factory::service("PartInstanceAlias")->getPartInstanceAliasDetailsByPartInstance($partInstance);
		$aliasText = "";
		$first = true;
		$hotMessage = false;
		foreach($aliases as $alias)
		{
			if(!$first)
			{
				$aliasText .= '<br/>';
			}
			else
			{
				$first = false;
			}

			if ($alias[5] === StringUtils::VALUE_TYPE_BOOL)
			{
			    $valueType = ($alias[1]==1)?'Checked':'';
			    $type = "<input type='checkbox' ".$valueType." disabled='disabled'>";
			    $aliasText .= '<b>' . $alias[0] . '</b>: ' . $type;
			}
			else
			{
				$deactivatedText = "<font style='color:#ff0000;font-weight:bold;'> -- Deactivated by {$alias[3]} @ {$alias[4]}(UTC)</font>";
    			if (strrpos($alias[0], "Hot Message") === false)
    			{
    				$aliasText .= '<b>' . $alias[0] . '</b>: ' . $alias[1] . ($alias[2]==0 ? $deactivatedText : "");
    			}
    			else
    			{
    				if ($alias[2] == 1) //active
    				{
    					$aliasText .= '<b>' . $alias[0].': </b>' . $alias[1];
    					$hotMessage = true;
    					$htMessage = '<img src="../../../themes/images/blue_flag_16.png" > <b style="color:orange;">' . $alias[1] . '</b><br/>';
    					$editItem->hotMessage->Text = $htMessage;
    				}
    				else
    				{
    					$aliasText .= '<b>' . $alias[0] . '</b>: ' . $alias[1] . $deactivatedText;
    				}
    			}
			}

		}
		$editItem->partInstanceAliases->Text = $aliasText;
		//get PO info if exists
		$poInfo = Factory::service("PurchaseOrder")->getPoInfoForPartInstance($partInstance->getId());
		if (count($poInfo) > 0)
		{
			$poText = '';
			foreach ($poInfo[0] as $key => $info)
				$poText .= "$key: $info<br/>";

			$editItem->partInstancePoLbl->Style = 'display:block;';
			$editItem->partInstancePoInfo->Text = $poText;
		}

		$editItem->partTypeName->Text = $partType->getName();
		$editItem->partTypeDescription->Text = $partType->getDescription();
		$editItem->partTypeMake->Text = $partType->getMake();
		$editItem->partTypeModel->Text = $partType->getModel();
		$editItem->partTypeVersion->Text = $partType->getVersion();

		if ($partType->getRepairable() == 1)
		{
			$editItem->partTypeRepairable->setImageUrl("/themes/images/small_yes.gif");
		}
		else
		{
			$editItem->partTypeRepairable->setImageUrl("/themes/images/small_no.gif");
		}

		$partTypeGroups = $partType->getPartTypeGroups();
		$editItem->partTypeGroups->Text = $this->implodeWalk($partTypeGroups,"getName",'<br/>');

		$contracts = $partType->getContracts();
		$editItem->partTypeContracts->Text = $this->implodeWalk($contracts,'getContractName','<br/>');

		$editItem->partTypeManufacturer->Text = $partType->getManufacturer();

		$suppliers = $partType->getSuppliers();
		$editItem->partTypeSuppliers->Text = $this->implodeWalk($suppliers,'getName','<br/>');

		$parttypeid=$partType->getId();
		$editItem->editPartTypeLink->NavigateUrl = "/parttypes/search/".$partType->getId();

		//modified to use service function rather than direct sql
		$aliases = Factory::service("PartTypeAlias")->getPartTypeAliasDetailsByPartType($partType);

		$rowspan = array();
		for($i=0;$i<count($aliases);$i++)
		{
			isset($rowspan[$aliases[$i][0]])? $rowspan[$aliases[$i][0]]++ : $rowspan[$aliases[$i][0]]=1;
		}

		if(count($aliases) > 0)
		{
			$aliasText='<table border="0" cellspacing="0" cellpadding="0" >';
			$aliasText .= '<tr>';
			$aliasText .='<td width="30%" style="font-weight:bold" rowspan="'.$rowspan[$aliases[0][0]].'" >'. $aliases[0][0].'</td>';
			$aliasText .='<td class="parttypealiases" >'.$aliases[0][1].'</td>';
			$aliasText .='<td>'.($aliases[0][2]==0 ? "<font style='color:#ff0000;font-weight:bold;'> Deactivated by {$aliases[0][3]} @ {$aliases[0][4]}(UTC)</font>" : "" ).'</td>';
			$aliasText .= '</tr>';
			for($a=1;$a<count($aliases);$a++)
			{
			$aliasText .= '<tr>';
					if($aliases[$a][0]!=$aliases[$a-1][0])
					{
					$aliasText .= '</tr>';
					$aliasText .= '<tr height="5px" ><td colspan="2" style="border-bottom:1px dashed black;">&nbsp;</td></tr><tr height="5px" ><td>&nbsp;</td></tr><tr>';
					$aliasText .='<td width="30%" style="font-weight:bold" rowspan="'.$rowspan[$aliases[$a][0]].'" >'. $aliases[$a][0].'</td>';
					}
					if ($aliases[$a][5]=== StringUtils::VALUE_TYPE_BOOL)
					{
					$valueType = ($aliases[$a][1]==1)?'Checked':'';
					$type = "<input type='checkbox' ".$valueType." disabled='disabled'>";
					$aliasText .= '<td class="parttypealiases" >'.$type.' '.($aliases[$a][2]==0 ? " - <font style='font-size:10px;color:#ff0000;font-weight:bold;'> Deactivated by {$aliases[$a][3]} @ {$aliases[$a][4]}(UTC)</font>" : "" ).'</td>';
					}
					else
					{
					if(strrpos($aliases[$a][0],"Hot Message")===false){
					$aliasText .='<td class="parttypealiases" >'.$aliases[$a][1].($aliases[$a][2]==0 ? " - <font style='font-size:10px;color:#ff0000;font-weight:bold;'> Deactivated by {$aliases[$a][3]} @ {$aliases[$a][4]}(UTC)</font>" : "" ).'</td>';
					}else{
						if ($aliases[$a][2] == 1) //active
						{
						$aliasText .='<td class="parttypealiases" >' . $aliases[$a][1] .'</td>';
							//$aliasText .='<td class="parttypealiases" >'.$aliases[$a][1].($aliases[$a][2]==0 ? " - <font style='font-size:10px;color:#ff0000;font-weight:bold;'> Deactivated by {$aliases[$a][3]} @ {$aliases[$a][4]}(UTC)</font>" : "" ).'</td>';
							$hotMessage = true;
							$editItem->hotMessage->Text = $htMessage . '<b style="color:orange"><img src="../../../themes/images/red_flag_16.png" > ' . $aliases[$a][1] .'</b>';
    					}

    					else
    					{
    						$aliasText .='<td class="parttypealiases" >'.$aliases[$a][1]." - <font style='font-size:10px;color:#ff0000;font-weight:bold;'> Deactivated by {$aliases[$a][3]} @ {$aliases[$a][4]}(UTC)</font>" .'</td>';
							}

							}
							}
							$aliasText .= '</tr>';
							}
								$aliasText.='</table>';
		}

		if(!$hotMessage)
		{
			$this->Page->jsLbl->Text = '<script type="text/javascript">hideHotMessageRow();</script>';
		}

		$editItem->partTypeAlias->Text = $aliasText;

		if ($partType->getSerialised() == 1)
			$editItem->partTypeSerialised->setImageUrl("/themes/images/small_yes.gif");
		else
			$editItem->partTypeSerialised->setImageUrl("/themes/images/small_no.gif");

		if ($partType->getActive() == 1)
			$editItem->partTypeActive->setImageUrl("/themes/images/small_yes.gif");
		else
			$editItem->partTypeActive->setImageUrl("/themes/images/small_no.gif");

		if ($partType->getDepreciationMethod() instanceof DepreciationMethod)
			$editItem->depreciable->setImageUrl("/themes/images/small_yes.gif");
		else
			$editItem->depreciable->setImageUrl("/themes/images/small_no.gif");

		//getting part instance aliases, plus info
		$html = "";
		$patterns = Factory::service("Lu_PartType_PartInstanceAliasPattern")->getMandatoryUniquePatternsForPtPiat($partType,null,null,null);
		if (count($patterns) > 0)
		{
			$html .= "<ul style='list-style:disc inside; margin-left:5px'>";
			foreach ($patterns as $p)
			{
				$attributes = array();
				if ($p->getIsMandatory())
				{
					$attributes[] = 'MANDATORY';
				}
				if ($p->getIsUnique())
				{
					$attributes[] = 'UNIQUE';
				}
				if ($p->getSampleFormat() !== '')
				{
					$attributes[] = "SAMPLE: '" . $p->getSampleFormat() . "'";
				}
				$html .= "<li><b>" . $p->getPartInstanceAliasType()->getName() . "</b>";
				if (!empty($attributes))
				{
					$html .= " (" . implode(' <b>/</b> ', $attributes) . ")";
				}
				$html .= "</li>";
			}
			$html .= "</ul>";
		}
		$editItem->mandatoryFields->Text = $html;

		//check to see whether you can see the lostPartBtn
		if((Factory::service("UserAccountFilter")->hasFilter(Core::getUser(),Core::getRole(),"ViewRestrictedWarehouse") || UserAccountService::isSystemAdmin()) && $partInstance->getActive())
		{
			$editItem->lostPartBtn->Visible=true;
		}

		//Display the View Parts Within link if the part has any children.
		$childParts = Factory::service("PartInstance")->getChilrenForPartInstance($partInstance);
		if(count($childParts)>0)
		{
			$editItem->partsWithin->Visible = true;
		}
	}

	/**
	 * Get Parent Details
	 *
	 * @param unknown_type $partInstanceId
	 * @return unknown
	 */
	public function getParentDetails($partInstanceId)
	{
		$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
		if(!$partInstance instanceof PartInstance)
		return "";

		$parent = $partInstance->getParent();
		if(!$parent instanceof PartInstance)
		return "";
		$return = $this->getPartInstanceDetails($parent,"Direct Parent Details");
		try
		{
			$rootParent = $partInstance->getRootParent();
			if(!$rootParent instanceof PartInstance)
			$return .= "";
			else if($rootParent->getId()==$parent->getId())
			$return .= "";
			else
			$return .=$this->getPartInstanceDetails($rootParent,"Root Parent Details");
		}
		catch (Exception $e)
		{
			return '';
		}

		return $return;
	}

	/**
	 * Get PartInstance Details
	 *
	 * @param PartInstance $partInstance
	 * @param unknown_type $title
	 * @return unknown
	 */
	private function getPartInstanceDetails(PartInstance $partInstance,$title="")
	{
		$partType = $partInstance->getPartType();
		$row = 0;
		$return='            <table width="100%" border="0" class="DataList">
                                                                        <thead>
                                                                                    <tr>
                                                                                                <th colspan="2">
                                                                                                            '.$title.'
                                                                                                </th>
                                                                                    </tr>
                                                                                    <tr>
                                                                                                <th width="30%">Name</th>
                                                                                                <th width="60%">Value</th>
                                                                                    </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                                    <tr class='.($this->getStyle($row++)).'>
                                                                                                <td>Active:</td>
                                                                                                <td>
                                                                                                            '.($partInstance->getActive()? "<img src='/themes/images/small_yes.gif' />" : "<img src='/themes/images/small_no.gif' /> <font style='color:#ff0000;font-weight:bold;'>This part has been deactivated</font>").'
                                                                                                </td>
                                                                                    </tr>
                                                                                    <tr class='.($this->getStyle($row++)).'>
                                                                                                <td>Part Type:</td>
                                                                                                <td>
                                                                                                            '.$partType.'
                                                                                                </td>
                                                                                    </tr>
                                                                                    <tr class='.($this->getStyle($row++)).'>
                                                                                                <td>Owner Client:</td>
                                                                                                <td>
                                                                                                            '.$partType->getOwnerClient().'
                                                                                                </td>
                                                                                    </tr>
                                                                                    <tr class='.($this->getStyle($row++)).'>
                                                                                                <td>Status:</td>
                                                                                                <td>
                                                                                                            '.$partInstance->getPartInstanceStatus().'
                                                                                                </td>
                                                                                    </tr>
                                                                                    <tr class='.($this->getStyle($row++)).'>
                                                                                                <td>Qty:</td>
                                                                                                <td>
                                                                                                            '.$partInstance->getQuantity().'
                                                                                                </td>
                                                                                    </tr>
                                                                                    <tr class='.($this->getStyle($row++)).'>
                                                                                                <td>Serial Number:</td>
                                                                                                <td>
                                                                                                            '.$partInstance.'
                                                                                                </td>
                                                                                    <tr>
                                                                        </tbody>
                                                            </table>
                                                <br/>';
		return $return;
	}

	/**
	 * Lost This Parts
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function lostThisPart($sender,$param)
	{
		$this->setErrorMessage("");
		$this->setInfoMessage("");
		$partInstanceId = trim($param->CommandParameter);
		$partInstance = Factory::service("PartInstance")->get($partInstanceId);
		$itemIndex = $this->DataList->getEditItemIndex();
		$this->AddPanel->Visible = false;
		$this->DataList->SelectedItemIndex = -1;
		$this->DataList->EditItemIndex = $itemIndex;

		if(!$partInstance instanceof PartInstance)
		{
			$this->setErrorMessage("Invalid Part Instance to Lose!");
			$this->dataLoad();
			$this->populateEdit($this->DataList->getEditItem());
			return;
		}


		try
		{
			Factory::service("StockTake")->lostPartInstance($partInstance);
			$this->setInfoMessage("Selected Part Instance is now officially lost!");
		}
		catch(Exception $ex)
		{
			$this->setErrorMessage($ex->getMessage());
		}

		$this->dataLoad();
		$this->populateEdit($this->DataList->getEditItem());
	}

	/**
	 * Get Edit PartType URL
	 *
	 * @return unknown
	 */
	public function getEditPartTypeUrl()
	{
		return "parttypes/search/".$this->partTypeId;
	}

	/**
	 * Output Excel
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function outputToExcel($sender, $param)
    {
    	$result = unserialize($this->searchData->Value);
    	$columnHeaderArray = array(
    								"Part Code",
									"Name",
			    					"Active",
			    					"Aliases",
			    					"Barcode",
			    					"Location"
    	);

    	$totalSize = sizeof($result);
    	if($totalSize <= 0 )
    		$this->setErrorMessage("No data to be exported to excel.");
    	else
	    	$allData = $result;

    	if(isset($allData))
    	{
			foreach($result as $key => $row)
			{
				$result[$key][0] = $row[2];//partcode
				$result[$key][2] = $row[6];//Active
				$result[$key][4] = $row[5];//Barcode
				$result[$key][3] = str_replace(array("<b>","</b>"), "", $row[3]);
				$result[$key][5] = $this->getLocationById($row[0]);//Location

				unset($result[$key][6]);//unset Barcode.
			}

			$fileName = "Part Instance List";
			$this->allowOutPutToExcel=true;
		    $this->toExcel($fileName, $fileName, $fileName, $columnHeaderArray, $result);
			$this->allowOutPutToExcel=false;
    	}
    }

}

?>
