<?php
/**
 * Warehouse Controller
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class WarehouseController extends HydraPage
{
	/**
	 * @var menuContext
	 */
	public $menuContext ='storagelocation';

	/**
	 * @var hasGotDeleteWarehouseFeature
	 */
	private $hasGotDeleteWarehouseFeature;

	/**
	 * @var readOnly
	 */
	private $readOnly;

	/**
	 * @var canEditBelowOrWhIds
	 */
	private $canEditBelowOrWhIds;

	/**
	 * @var dontHardCodeTableParamName
	 */
	private $dontHardCodeTableParamName;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->roleLocks="pages_all,pages_logistics,page_logistics_storeageLocation";
		$this->hasGotDeleteWarehouseFeature = $this->hasDeleteWarehouseFeature("pages_all,menu_all,pages_logistic_deleteWarehouse");
		$this->readOnly = false;
		$this->dontHardCodeTableParamName = "ExcludeWarehouseIdsFromStockCount";
	}

	/**
	 * On Pre Initialization
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
    public function onLoad($param)
    {
        parent::onLoad($param);
        $this->jsLbl->Text = "";

        $this->activeLbl->style = 'display:block';
        $this->SearchActive->style = 'display:block';

        /// CHECK IF THE PAGE IS READ ONLY and DISABLE THE ADD LOCATION BUTTON ///
        $urlArray = explode("/", $_SERVER['REQUEST_URI']);
        if($urlArray[1] == "warehouselookup")
        {
        	$this->readOnly = true;
        	$this->addSubLocationButton1->enabled = false;
        }

        if (!$this->getIsPostBack() && !$this->getIsCallBack())
        {
        	$this->defaultBreadcrumbSeparator->Value = "/";

        	$isSysAdmin = UserAccountService::isSystemAdmin();
        	if ($isSysAdmin || Factory::service("UserPreference")->getOption(Core::getUser(),'allowIgnoreStockCount_RestrictedAreaWarehouse') == 1)
        	{
        		$categories = Factory::service("WarehouseCategory")->findAll();

        		if ($isSysAdmin)
        		{
        			$this->isSysAdmin->Value = 1;
        		}
        	}
        	else
        	{
        		$categories = Factory::service("WarehouseCategory")->findByCriteria("id not in (".WarehouseCategory::ID_STOCK_DISC.",".WarehouseCategory::ID_RESTRICTED_AREA.",". WarehouseCategory::ID_TRANSITNOTE .")");
        	}

        	$this->bindList($this->SearchCategory,$categories);
        	$this->bindList($this->EditWarehouseCategoryList,$categories);

        	$this->bindList($this->reportingWarehouse, WarehouseLogic::getBytecraftFacilityWarehouses());

        	$this->_bindPartsStatusList();

        	$this->firstLoad->Value=1;
        	$this->bindBarcodeList();

        	//get the default Printer!
        	$printer = $this->getPrinter();
        	if(!$printer instanceof Printer)
        	{
        		$this->PrintLabels->Enabled=false;
        		$this->PrintLabels->Text="No Printer";
        	}

        	//gets warehouse category information and stores in hidden values
        	$this->siteWarehouseCategoryId->Value = WarehouseCategory::ID_SITE_WAREHOUSE;
        	$this->siteWarehouseTnCategoryId->Value = WarehouseCategory::ID_SITE_WAREHOUSE_TN;
        	$this->thirdPartyCategoryId->Value = WarehouseCategory::ID_3RD_PARTY_REPAIRER;
        	$this->agentCategoryId->Value = WarehouseCategory::ID_AGENT;

        	if(isset($this->Request["id"]) && trim($this->Request["id"]) > 0)
        	{
        		$warehouse = Factory::service("Warehouse")->getWarehouse(trim($this->Request["id"]));
        		if ($warehouse instanceof Warehouse)
        		{
        			//check here if we're trying to draw the tree with too many children, if so redirect to parent
        			$maxChildren = Factory::service("DontHardcode")->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'maxChildrenForTreeOnLoad',false);
        			$childrenCount = Factory::service('Warehouse')->getWarehouseChildrenCount($warehouse, 1);
        			if ($childrenCount > $maxChildren && $warehouse->getId() != 1)
        			{
	        			$this->response->Redirect("/storagelocation/" . $warehouse->getParent()->getId());
	        			return;
        			}
        			$this->whTree->whIdPath->Value = $warehouse->getId();
        			$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('mb.showLoading("loading")'));
        		}
        	}
		}
		$this->defaultBreadcrumbSeparator->Value = Warehouse::DEFAULT_BREADCRUMB_SEPARATOR;
    }

    private function _bindPartsStatusList($status = null, $enabled = true)
    {
    	$statuses = DropDownLogic::getPartInstanceStatusList(array('id' => '666', 'Name' => 'Inherit From Parent'), array(), $status);
    	array_splice($statuses, 1, 0, array(array('id' => '0', 'Name' => 'Status To Remain')));
    	$this->bindList($this->partsStatusList, $statuses, null);
    	$this->partsStatusList->Enabled = $enabled;
    }

    /**
     * Set Info Message
     *
     * @param unknown_type $msg
     */
    public function setInfoMessage($msg)
    {
    	$this->activeInfoLabel->Text = $msg;
    }

    /**
     * Set Error Message
     *
     * @param unknown_type $msg
     */
    public function setErrorMessage($msg)
    {
    	$this->activeErrorLabel->Text = $msg;
    }

    /**
     * Bind Barcode List
     *
     */
    private function bindBarcodeList()
    {
    	$filters = array(
							array("id"=>1,"name"=>"Current Only"),
							array("id"=>2,"name"=>"All Leaves Below")
						);
	    $this->bindList($this->PrintLabelList,$filters);
	    $this->PrintLabelList->setSelectedIndex(1);
 		$this->WarehouseLabelQty->Text = 1;
    }

    /**
     * Bind List
     *
     * @param unknown_type $listToBind
     * @param unknown_type $dataSource
     * @param unknown_type $selectedItem
     * @param unknown_type $enable
     */
	protected function bindList(&$listToBind, $dataSource, $selectedItem = null, $enable = true)
	{
		$listToBind->DataSource = $dataSource;
        $listToBind->dataBind();
        if($selectedItem!=null)
        {
        	$listToBind->setSelectedValue($selectedItem->getId());
        }
        $listToBind->Enabled=$enable;
	}

	/**
	 * Get Loading Image
	 *
	 * @return unknown
	 */
	public function getLoadingImage()
	{
		return "<div style='padding: 20px;'><img src='/themes/images/ajax-loader.gif' /> loading...</div>";
	}

	/**
	 * Calculates the name of sibling warehouses to ensure we don't end up with duplicate warehouse names at the same level
	 */
	public function calculateSiblings($sender, $param, $warehouseId = null)
	{
		if ($warehouseId == null)
		{
			$rootWarehouseIds = explode("/", $this->whTree->whIdPath->Value);
			$warehouseId = array_pop($rootWarehouseIds);
		}
		$this->siblingWarehouseNames->Value = '';
		if (trim($this->searchWarehouseIds->Value) == '')
		{
    		//get the names of all the sibling warehouses
    		$this->siblingWarehouseNames->Value = implode(Warehouse::DEFAULT_BREADCRUMB_SEPARATOR, Factory::service("Warehouse")->getWarehouseSiblingNameArray($warehouseId));
		}

		$this->_bindPartsStatusList();
	}

	/**
	 * Get List of Storage Location
	 *
	 */
    public function getListOfStorageLocations()
    {
    	$rootWarehouseIds = explode("/",$this->whTree->whIdPath->Value);
    	$rootWarehouseId = array_pop($rootWarehouseIds);

    	if (trim($this->searchWarehouseIds->Value) == '')
    	{
	    	$this->canEditBelowOrWhIds = $this->canAddEditThisOrBelowThisWarehouse($rootWarehouseId);
	    	if ($this->canEditBelowOrWhIds === true && $this->readOnly == false)
	    	{
	    	   	$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('mb.hide()','toggleAddButton(false)','toggleShowButton(false)'));
	    	}
	    	$wh = Factory::service("Warehouse")->getWarehouse($rootWarehouseId);
	    	if ($wh instanceof Warehouse)
	    	{
	    		$this->parentIgnoreInStockCount->Value = $wh->getIgnoreStockCount();
	    		$this->parentIgnoreInStocktake->Value = $wh->getIgnoreStocktake();
	    	}
    	}
    	else
    	{
    		$this->Path->Text = '';
    	}

    	if ($this->Page->displayInfo->Value == 0)
    		return;

    	$this->storageLocationList->Text = $this->getLoadingImage();

    	///////////////////////////////////////////////////////////////////////////////////////////////

    	if (trim($this->searchWarehouseIds->Value) != "")		//we are searching
    	{
    		$this->addSubLocationButton1->Enabled = false; 		//we can't add if in searching mode

    		$where = trim($this->searchWarehouseIds->Value);
    	}
    	else
    	{
	    	$where = "ware.parentId = $rootWarehouseId";
    	}

    	$searchActive = false;
    	if ($this->getSearchActiveFeature() === true)
	    	$searchActive = true;

        $active = trim($this->SearchActive->getSelectedValue());
        if ($active != "")
        {
        	$where .= " AND ware.active=" . $active;
        }

    	$sql = "select
    					ware.id as id,
    					ware.name as name,
    					ware.parts_allow as partsAllow,
    					wacate.name as whCat,
    					wa.alias as BL,
    					concat(addr.addressName, ' ', addr.line1,' ',addr.line2,' ',addr.suburb,' ',addr.postcode,' ',st.name,' ',con.name) as address,
    					wa12.alias as facName,
    					ware.active as active,
    					ware.warehousecode as whCode,
    					ware.ignorestockcount as ignoreStockCount,
    					ware.ignorestocktake as ignoreStocktake
    			from warehouse ware
    			left join warehousealias wa on (wa.warehouseId = ware.id and wa.active = 1 and wa.warehouseAliasTypeId = ".WarehouseAliasType::ALIASTYPEID_BARCODE.")
    			left join warehousealias wa12 on (wa12.warehouseId = ware.id and wa12.active = 1 and wa12.warehouseAliasTypeId = ".WarehouseAliasType::ALIASTYPEID_FACILITY_NAME.")
    			left join warehousecategory wacate on (wacate.id = ware.warehouseCategoryId and wacate.active = 1)
    			left join facility fa on (fa.id = ware.facilityId and fa.active = 1)
    			left join address addr on (fa.addressId = addr.id and addr.active = 1)
    			left join state st on (st.id = addr.stateId and st.active = 1)
    			left join country con on (con.id = addr.countryId and con.active = 1)
    			WHERE " . $where .
    			(UserAccountFilterService::showRestrictedArea() ? " " : " AND (ware.warehouseCategoryId IS NULL OR ware.warehouseCategoryId !=".WarehouseCategory::ID_RESTRICTED_AREA.")")."
    			ORDER BY ware.active DESC, ware.name ASC";
    	$result = Dao::getResultsNative($sql, array(), PDO::FETCH_ASSOC);
    	if (count($result) == 0)
    	{
    		$text = '<table width="100%" cellspacing="2" cellpadding="0">';
    		$text .= '<tr height="50px"><td align="center">';

    		if (trim($this->searchWarehouseIds->Value) != "")
    		{
    			$text .= 'No matching warehouses were found...';
    		}
    		else if ($this->readOnly == false && $this->canEditBelowOrWhIds == true)
    		{
    			$text .= '<a href="" OnClick="addNode(); return false;">There are no child warehouses. Click to Add a Location Here.</a>';
    		}
    		else
    		{
    			$text .= 'There are no child warehouses';
    		}

    		$text .= '</tr> </table>';

    		$this->storageLocationList->setText($text);
    	}
    	else
    	{
    		if (trim($this->searchWarehouseIds->Value) != "")		//we are searching
    		{
	    		$searchWhResults = array();
	    		foreach ($result as $row)
	    		{
	    			$searchWhResults[] = $row['id'];
	    		}
	    		$this->canEditBelowOrWhIds = $this->canAddEditThisOrBelowThisWarehouse($searchWhResults);
    		}

    		$table = '<table width="100%" cellspacing="2" cellpadding="0" class="DataList" style="margin:0px;padding:0px;">
	    				<thead style="height:21px;">
	    					<tr>
	    						<th>Name</th>
	    						<th width="4%" colspan="2">&nbsp;</th>
	    						<th width="15%">Category</th>
	    						<th width="13%">Barcode</th>
	    						<th width="17%" colspan="2">&nbsp;</th>
    							<th width="3%">&nbsp;</th>';

    		if ($searchActive)
    			$table .= '<th width="3%" style="text-align:center;">A?</th>';

    		$table .= '</tr>
	    			</thead>
	    			<tbody>';

    		$rowNo=0;

	    	$showLostStockIcon = (UserAccountService::isSystemAdmin()) ? true : false;
		    foreach($result as $row)
		    {
	    		$id = $row['id'];

		    	$whCode = '';
		    	if ($row['whCode'] !== '')
		    	{
		    		$whCode = '<span style="font-size:11px;font-style:italic;"> (' . $row['whCode'] . ')</span>';
		    	}

	    		$table .= '<tr id='.$id.' ' . ($rowNo % 2 ==0 ? 'class="DataListItem"' : 'class="DataListAlterItem"') . '>
	    					<td onClick="popup(' . $id . ');" style="padding: 8px 0 8px 0;"><span style="font-weight:bold;">' . $row['name'] . $whCode . '</span>';

	    		if ($row['address'] != '' && $row['address'] != null)
	    		{
	    			$facilityName = '';
	    			if (!is_null($row['facName']) && $row['facName'] != '')
	    			{
	    				$facilityName = '<span style="font-style:italic;">' . $row['whCode'] . '</span> ';
	    			}
	    			$table .= '<br /><font style="font-size:9px;">' . $facilityName . $row['address'] . '</font>';
	    		}

	    		$table .= ' </td>
	    					<td align="center">' . ($row['partsAllow']==1 ? '<img alt="Allow Parts" title="This location can store parts." src="../../../themes/images/small_yes.gif"/>' : '&nbsp;') . '</td>';

	    		$ignoreHtml = '&nbsp;';
	    		if ($row['ignoreStockCount'] == 1 || $row['ignoreStocktake'] == 1)
	    		{
	    			if ($row['ignoreStockCount'] == 1 && $row['ignoreStocktake'] == 1)
	    			{
		    			$img = 'dash-badge.png';
	    				$whichIgnore = 'Ignore in Stock Count & Ignore in Stocktake';
	    			}
	    			else if ($row['ignoreStockCount'] == 1)
	    			{
		    			$img = 'info-badge.png';
	    				$whichIgnore = 'Ignore in Stock Count';
	    			}
	    			else if ($row['ignoreStocktake'] == 1)
	    			{
		    			$img = 'excl-badge.png';
	    				$whichIgnore = 'Ignore in Stocktake';
	    			}
	    			$ignoreHtml = '<img alt="' . $whichIgnore . '" title="This location is set to ' . $whichIgnore . '" src="../../../themes/images/icons/' . $img . '"/>';
	    		}
		    	$table .=  '<td>' . $ignoreHtml . '</td>
		    				<td>' . $row['whCat'] . '</td>
	    					<td>' . $row['BL'] . '</td>';

	    		//Display part qty & default options for selected warehouse
	    		$table .= "<td colspan=2>
	    						<a href='' id='showDetailsBtn_$id' onclick=\"showDetails('$id','showDetailsBtn_$id');return false;\">Show Details</a>
	    					</td>";

	    		//view only
	    		if ($this->readOnly || $this->canEditBelowOrWhIds == false || (is_array($this->canEditBelowOrWhIds) && !in_array($id, $this->canEditBelowOrWhIds)))
	    		{
		    		$activeChkEnabled = 'disabled';
	    			$table .= '<td style="text-align:center;"><img title="View Record" src="/themes/images/magnifying.png" style="cursor:pointer;" onclick="editNode(' . $id . ', \'view\');"/></td>';
	    		}
	    		else //edit
	    		{
	    			$table .= '<td style="text-align:center;"><img title="Edit Record" src="/themes/images/edit.png" style="cursor:pointer;" onclick="editNode(' . $id . ');"/></td>';

		    		$activeChkEnabled = 'disabled';
	    			//we are system admin or have the search active feature, and can edit the warehouse, so enable the toggle
	    			if ($searchActive && ($this->canEditBelowOrWhIds !== false || (is_array($this->canEditBelowOrWhIds) && in_array($id, $this->canEditBelowOrWhIds))))
	    				$activeChkEnabled = '';
	    		}

	    		if ($searchActive)
	    		{
			    	//active toggle
		    		$checked = '';
		    		if ($row['active']==1)
		    		{
			    		$checked = 'checked';
		    		}
	    		   	$table .= '<td style="text-align:center;"><input type="checkbox" value="' . $id . '" id="toggleActive_' . $id . '" ' . $checked . ' ' . $activeChkEnabled . ' title="Warehouse Active?" onclick="toggleWarehouseActive(' . "'toggleActive_$id'" . ', false);"></td>';
	    		}

	    		$table .= "</tr>";
	    		$rowNo++;
		    }
	    	$table .= '</tbody>
		    			</table><br /><br /><br />';

	    	$this->storageLocationList->Text=$table;
    	}
    	$this->jsLbl->Text = '<script type="text/javascript">mb.hide();</script>';
    }

    /**
     * Toggle Active
     *
     */
    public function toggleActive()
    {
        $warehouseId = $this->warehouseValues->Value;
        $warehouse = Factory::service("Warehouse")->get($warehouseId);
        if ($warehouse->getActive()==1)
        {
        	$warehouse->setActive(0);
        }
        else
        {
        	$warehouse->setActive(1);
        }
        Factory::service("Warehouse")->saveWarehouse($warehouse);
    }

    /**
     * Checks if the warehouse has any users with linked ('DefaultWarehouse', 'DefaultMobileWarehouse', 'DefaultWorkshop', 'OptionsForDefaultWarehouse', 'OptionsForDefaultWorkshop') preferences
     * @param int $whId
     */
    private function _getWarehousePreferenceCount($whId)
    {
    	//count of default warehouse users
    	$sql = "SELECT COUNT(ua.id)
    			FROM useraccount ua
		    	INNER JOIN userpreference up ON ua.id=up.useraccountid AND up.active=1 AND up.lu_userpreferenceid IN (55,69,70,79,80) AND (up.value='$whId' OR up.value LIKE '$whId,%' OR up.value LIKE '%,$whId,%' OR up.value LIKE '%,$whId')
		    	INNER JOIN person p ON p.id=ua.personid AND p.active=1
    			WHERE ua.active=1";
    	$res = Dao::getSingleResultNative($sql);
    	if ($res !== false && $res[0] > 0)
    		return $res[0];

    	return 0;
    }
    /**
     * Toggle Warehouse Active
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function toggleWarehouseActive($sender, $param)
    {
    	$results = $errors = array();
    	try
    	{
	    	$params = json_decode($param->CallbackParameter->params, true);
	    	$whId = $params['whId'];
	    	$checked = $params['checked'];
	    	$confirmed = $params['confirmed'];

	    	$wh = Factory::service("Warehouse")->getWarehouse($whId);
	    	if (!$wh instanceof Warehouse)
	    	{
	    		throw new Exception("Invalid warehouse...");
	    	}

	    	$checkWhIds = array_merge(array($wh->getId()), Factory::service("Warehouse")->getWarehouseChildrenIds($wh));

    		$msgs = array("<span style='font-size:14px;font-weight:bold;'>" . Factory::service("Warehouse")->getWarehouseBreadCrumbs($wh, true, '/') . '</span>');
	    	if ($checked == false)	//deactivating
	    	{
 		    	$childParts = Factory::service("PartInstance")->countPartsInWarehouseUnderAllSubWarehouses($wh);
 		    	if ($childParts > 0)
 		    	{
 		    		throw new Exception("The warehouse (and/or sub-warehouses) have (" . $childParts . ") parts within, unable to deactivate...");
 		    	}

 		    	//check for open/transit notes
 		    	$notes = Factory::service("TransitNote")->findByCriteria("transitnotestatus IN ('open','transit') AND destinationid IN (" . implode(',', $checkWhIds) . ")");
 		    	if (count($notes) > 0)
 		    	{
 		    		$tnNos = array();
 		    		foreach ($notes as $note)
 		    			$tnNos[] = $note->getTransitNoteNo();

 		    		$isOrAre = 'are';
 		    		$noteOrNotes = 'notes';
 		    		if (count($notes) == 1)
 		    		{
 		    			$isOrAre = 'is';
 		    			$noteOrNotes = 'note';
 		    		}
 		    		throw new Exception("There $isOrAre " . count($notes) . " consignment $noteOrNotes [" . implode(', ', $tnNos) . "] where the warehouse (or one of its sub-warehouses) is the recipient, unable to deactivate...");
 		    	}

		    	$childrenCount = Factory::service('Warehouse')->getWarehouseChildrenCount($wh);
		    	$childOrChildren = 'child';
	    		$thisOrTheseKids = 'These';
	    		$whOrWhs = 'warehouses';
	    		$plusChildren = ' (including children) ';
	    		if ($childrenCount == 1)
	    		{
		    		$childOrChildren = 'child';
		    		$thisOrTheseKids = 'This';
		    		$whOrWhs = 'warehouse';
		    		$plusChildren = ' ';
	    		}

	    		$mslCount = Factory::service("PartTypeMinimumLevel")->countByCriteria('warehouseid IN (' . implode(',', $checkWhIds) . ')');
	    		$entryOrEntriesMsl = 'entries';
	    		$thisOrTheseMsl = 'These';
	    		if ($mslCount == 1)
	    		{
	    			$entryOrEntriesMsl = 'entry';
	    			$thisOrTheseMsl = 'This';
	    		}

	    		$roCount = Factory::service("PartTypeReorderingLevel")->countByCriteria('warehouseid IN (' . implode(',', $checkWhIds) . ')');
	    		$entryOrEntriesRo = 'entries';
	    		$thisOrTheseRo = 'These';
	    		if ($roCount == 1)
	    		{
	    			$entryOrEntriesRo = 'entry';
	    			$thisOrTheseRo = 'This';
	    		}

	    		$userOrUsers = 'user';
		    	$whPrefCount = $this->_getWarehousePreferenceCount($wh->getId());
		    	if ($whPrefCount > 0)
		    		$userOrUsers = 'users';

	    		$transferPartsAllow = false;
	    		$parent = $wh->getParent();
	    		$siblingCount = Factory::service('Warehouse')->getWarehouseChildrenCount($parent);
	    		if ($siblingCount <= 1 && $parent->getParts_allow() == false && $wh->getParts_allow()) //if we have no other siblings, parent is false, and child is true
		    		$transferPartsAllow = true;

		    	if ($confirmed == false)
		    	{
			    	if ($childrenCount > 0)
			    	{
			    		$msgs[] = "<span style='font-weight:bold;'>The warehouse has " . $childrenCount . " " . $childOrChildren . " " . $whOrWhs . ".<span style='color:red;font-weight:bold;'><br />" . $thisOrTheseKids . " will be deactivated!</span></span>";
			    	}

			    	if ($mslCount > 0)
			    	{
			    		$msgs[] = "<span style='font-weight:bold;'>The warehouse" . $plusChildren . "has " . $mslCount . " MSL " . $entryOrEntriesMsl . ".<span style='color:red;font-weight:bold;'><br />" . $thisOrTheseMsl . " will be deactivated!</span></span>";
			    	}

			    	if ($roCount > 0)
			    	{
			    		$msgs[] = "<span style='font-weight:bold;'>The warehouse" . $plusChildren . "has " . $roCount . " Reordering Level " . $entryOrEntriesRo . ".<span style='color:red;font-weight:bold;'><br />" . $thisOrTheseRo . " will be deactivated!</span></span>";
			    	}

			    	if ($whPrefCount > 0)
			    	{
			    		$msgs[] = "<span style='font-weight:bold;'>The warehouse has " . $whPrefCount . " linked " . $userOrUsers . " as User Preferences.<br /><span style='color:red;font-weight:bold;'> DefaultWarehouse (+ options) | DefaultMobileWarehouse | DefaultWorkshop (+ options)</span></span>";
			    	}

			    	if ($transferPartsAllow)
			    	{
			    		$msgs[] = "<span style='font-weight:bold;'>Parts Allow will be transferred back to the parent [" . Factory::service("Warehouse")->getWarehouseBreadCrumbs($parent, false, '/') . "]</span>";
			    	}

			    	$msgs[] = "<span style='font-size:14px;font-weight:bold;'>Are you sure you want to <span style='color:red;'>DEACTIVATE</span> this warehouse?</span>";
		    	}
		    	else
		    	{
		    		Factory::service("Warehouse")->deactivateWarehouse($wh);
			    	$msgs[] = "<span style='font-size:14px;font-weight:bold;'>Successfully DEACTIVATED.</span>";

		    		if ($transferPartsAllow)
		    		{
		    			$parent->setParts_allow(true);
		    			Factory::service("Warehouse")->save($parent);
				    	$msgs[] = "<span style='font-size:14px;font-weight:bold;'>Parts Allow transferred back to parent.</span>";
		    		}

		    		//cancel any active stocktakes
		    		try
		    		{
		    			Factory::service("StockTake")->cancelStockTake($wh);
		    		}
		    		catch (Exception $e) {} //ignore as this would be thrown if there are no stocktakes

		    	}
	    	}
	    	else					//activating
	    	{
	    		$transferPartsAllow = false;
	    		$parent = $wh->getParent();
	    		$siblingCount = Factory::service('Warehouse')->getWarehouseChildrenCount($parent);
	    		if ($siblingCount == 0 && $parent->getParts_allow() == true && $wh->getParts_allow()) //if we have no other siblings, parent is true, and child is true
	    			$transferPartsAllow = true;

	    		if ($confirmed == false)
	    		{
	    			if ($wh->getActive() == 1)
	    			{
		    			$msgs[] = "<span style='font-weight:bold;'>The warehouse is already active...</span>";
		    			$results['reloadOnly'] = true;
	    			}
	    			else
	    			{
	    				if ($transferPartsAllow)
	    				{
	    					$msgs[] = "<span style='font-weight:bold;'>Parts Allow will be removed from the parent [" . Factory::service("Warehouse")->getWarehouseBreadCrumbs($parent, false, '/') . "]</span>";
	    				}

				    	$msgs[] = "<span style='font-size:14px;font-weight:bold;'>Are you sure you want to <span style='color:red;'>RE-ACTIVATE</span> this warehouse?</span>";
	    			}
	    		}
	    		else
	    		{
		    		$wh->setActive(1);
		    		Factory::service("Warehouse")->save($wh);
			    	$msgs[] = "<span style='font-size:14px;font-weight:bold;'>Successfully RE-ACTIVATED.</span>";

		    		if ($transferPartsAllow)
		    		{
		    			$parent->setParts_allow(false);
		    			Factory::service("Warehouse")->save($parent);
		    			$msgs[] = "<span style='font-size:14px;font-weight:bold;'>Parts Allow removed from parent.</span>";
		    		}

	    		}
	    	}
	    	$results['msg'] = implode('<br /><br />', $msgs);
    	}
    	catch(Exception $ex)
    	{
    		$errors[] = $ex->getMessage();
    	}
    	$param->ResponseData = Core::getJSONResponse($results, $errors);
    }

    /**
     * Set up display variables for parts qty & options
     * @param unknown_type $sender
     * @param unknown_type $param
     * @throws Exception
     */
    public function showDetails($sender, $param)
    {
    	$results = $errors = array();
    	try
    	{
    		if(!isset($param->CallbackParameter->warehouseId) || ($warehouseId = trim($param->CallbackParameter->warehouseId)) === '')
    		{
    			throw new Exception('No Selected Warehouse!');
    		}

    		//count of warehouse
    		$warehouse = Factory::service("Warehouse")->get($warehouseId);
            $positionPlace=$warehouse->getPosition();

            //get all the children inactive or active....
            $pAllowSql = "select id from warehouse where position like '$positionPlace%'  and (active=0 or active=1) ";
            $childernList= Dao::getResultsNative($pAllowSql);
            $childrenCount=count($childernList);

    		$cntWarehouse = 0;
    		$children = Factory::service('Warehouse')->getWarehouseChildrenIds($warehouse);
    		$countChildWhse = count($children);
    		if ($countChildWhse > 0)
    		{
    		    foreach ($children as $child)
    		    {
    		        $partsParentWhse = "select sum(pi.quantity) from partinstance pi where pi.active = 1 and pi.warehouseId = $child";
    		        $partsParentResult = Dao::getResultsNative($partsParentWhse);
    		        $sumPartsChildWhse = $partsParentResult[0][0];
    		        if ($sumPartsChildWhse > 0)
    		        	$cntWarehouse++;
    		    }
    		}

    		$showLostStockIcon = (UserAccountService::isSystemAdmin()) ? true : false;
    		$showAliasIcon = $this->crossMatchExistingFeatureWithFeatureList(array("pages_all", "pages_logistics","page_logistics_storageLocationAlias"));
    		$showMslIcon = $this->crossMatchExistingFeatureWithFeatureList(array("pages_all","pages_logistics","page_logistics_storeageLocationMiniumLevel"));

    		$showPushListIcon = $this->crossMatchExistingFeatureWithFeatureList(array("pages_all","page_facilityRequestPushList"));

    		//check here if the warehouse is one of the push list warehouses
    		$isPushListWarehouse = false;
    		$pushListWarehouses = WarehouseLogic::getFacilityRequestPushListWarehouses();
    		foreach ($pushListWarehouses as $wh)
    		{
    			if ($warehouseId == $wh[0])
    			{
    				$isPushListWarehouse = true;
    				break;
    			}
    		}

    		//total part instance quantity
    		$parts = Factory::service("Warehouse")->getPartInstanceCountForWarehouse($warehouseId);

    		//count of default warehouse users
    		$defUsers = $this->_getWarehousePreferenceCount($warehouseId);

	    	$pAllowSql = "select name, parts_allow from warehouse where id = $warehouseId";
	    	$pAllowResult = Dao::getResultsNative($pAllowSql);
	    	$warehouseName = $pAllowResult[0][0];
	    	$partsAllow = $pAllowResult[0][1];

	    	//Set up qty variable for display
	    	if($this->readOnly == false)
	    		$qty = ($parts > 0) ? 'Part Qty:&nbsp;&nbsp;<a href="/stock/warehouse/'.$warehouseId.'/" title="To Parts Listing Page" target="_blank">' . $parts . '</a><br />' : 'Part Qty:&nbsp;&nbsp;0<br />';
	    	else
	    		$qty = ($parts > 0) ? '&nbsp;&nbsp;' . $parts . '<br />' : '&nbsp;<br />';

	    	//Set up return variables in results array
	    	$results[] = array('warehouseId' => $warehouseId, 'warehouseName'=> $warehouseName, 'qty'=>$qty, 'parts'=>$parts, 'defaultUsers'=>$defUsers);
	    	//return for onchange function
	    	$results['onchange'] = 'onchange="gotoPage(this,'.$warehouseId.',\''.addslashes($warehouseName).'\','.$parts.','.$defUsers.','.$countChildWhse.','.$childrenCount.')";';
	    	//default options
	    	$html[] = array();
	    	$i = 0;

	    	if ($this->readOnly == false && $this->canAddEditThisOrBelowThisWarehouse($warehouseId) === true)
	    	{
	    		$html[$i][0] = 2;
	    		$html[$i][1] = "Edit Record";
	    		$i++;
	    		$html[$i][0] = 3;
	    		$html[$i][1] = "Move Location";
	    		$i++;
	    	}
	    	else
	    	{
	    		$html[$i][0] = 11;
	    		$html[$i][1]= "View Record";
	    		$i++;
	    	}

	    	if ($showAliasIcon)
	    	{
	    		$html[$i][0] = 4;
	    		$html[$i][1] = "To Alias";
	    		$i++;
	    	}

	    	if ($showMslIcon)
	    	{
	    		$html[$i][0] = 5;
	    		$html[$i][1]= "To MSL";
	    		$i++;
	    		$html[$i][0] = 6;
	    		$html[$i][1] = "To Group MSL";
	    		$i++;
	    	}

	    	if ($showLostStockIcon)
	    	{
	    		$html[$i][0] = 7;
	    		$html[$i][1] = 'To Lost Stock WH';

	    		$i++;
	    	}

	    	if ($showPushListIcon && $isPushListWarehouse)
	    	{
	    		$html[$i][0] = 11;
	    		$html[$i][1] = 'To FR Push List';

	    		$i++;
	    	}

	    	if(($this->hasGotDeleteWarehouseFeature && $cntWarehouse==0) && $this->readOnly == false) // additional check for readonly page
	    	{
	    		$html[$i][0] = 8;
	    		$html[$i][1] = 'Deactivate Record';

	    		//removing this feature as per ELizabeth/Wayne discussion
// 	    	    if(($partsAllow==1) && ($parts > 0))
// 	    		{
// 	    			$html[$i][0] = 8;
// 	    			$html[$i][1] = 'Deactivate Parts';
// 	    	    }
	    	    $i++;
	    	}

            if($warehouse->getActive()==0)
            {
            	$html[$i][0] = 10;
                $html[$i][1] = 'Activate';
            }

	    	if (UserAccountService::isSystemAdmin() && $defUsers > 0)
	    	{
	    		$html[$i][0] = 9;
	    		$html[$i][1] = 'Default Users';
	    	}
	    	//set up results array with default options
	    	$results['defaultOptions'] = $html;
    	}
    	catch(Exception $ex)
    	{
    		$errors[] = $ex->getMessage();
    	}
		$param->ResponseData = Core::getJSONResponse($results, $errors);
    }

    /**
     * Has Delete Warehouse Feature
     *
     * @param unknown_type $feature
     * @return unknown
     */
	public function hasDeleteWarehouseFeature($feature)
	{
		if(strpos($feature,',') !== false)	$feature = explode(',',$feature);
		else $feature = array($feature);

		if(Core::getRole() == null)return false;

		$features = Core::getRole()->getFeatures();
		foreach($features as $f)
		{
			foreach($feature as $s)
			{
				if(trim($s) == $f->getName())
				{
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Cross Match Existing Feature With Feature List
	 *
	 * @param unknown_type $featureArray
	 * @return unknown
	 */
	private function crossMatchExistingFeatureWithFeatureList($featureArray)
	{
		$finalFound = 0;
		if(count($featureArray) == 0 || empty($featureArray))
		{
			return false;
		}

		if(Core::getRole() == null)
		{
			return false;
		}
		else
		{
			$eFeatures = Core::getRole()->getFeatures();
		}

		foreach($eFeatures as $sFeature)
		{
			$ff = 0;
			for($i = 0; $i < count($featureArray); $i++)
			{
				if($sFeature->getName() == $featureArray[$i])
				{
					$ff = 1;
					break;
				}
			}
			if($ff == 1)
			{
				$finalFound = 1;
				break;
			}
		}

		if($finalFound == 1)
		{
			return true;
		}

		return false;
	}

	/**
	 * Deactivate Parts
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
    public function deactivateparts($sender, $param)
    {
    	$sql = "UPDATE partinstance SET active=0, updated=NOW(), updatedById=". Core::getUser()->getId() . " WHERE warehouseid=". $this->deactivateparts->Value;
    	Dao::execSql($sql);
    	$this->setInfoMessage("Parts Deactivated");
    }

    /**
     * Activate Parts Parent
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function activatepartsParent($sender, $param)
    {
    	$warehouse = Factory::service("Warehouse")->get($this->activateparts->getValue());
    	$warehouse->setActive(1);
    	Factory::service("Warehouse")->saveWarehouse($warehouse);
    	$this->setInfoMessage("Parent Part Activated");
    }

    /**
     * Activate Parts Children
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function activatepartsChildren($sender, $param)
    {
         $warehouse = Factory::service("Warehouse")->get($this->activateparts->getValue());
         $positionPlace=$warehouse->getPosition();
         //get all the children inactive or active....
         $pAllowSql = "select id from warehouse where position like '$positionPlace%'  and (active=0 or active=1) ";
         $childernList= Dao::getResultsNative($pAllowSql);
         $chilrenCount=count($childernList);
         if($childernList>0)
         {
          foreach ($childernList as $child)
          {
            $warehouse = Factory::service("Warehouse")->get($child[0]);
            $warehouse->setActive(1);
            Factory::service("Warehouse")->saveWarehouse($warehouse);
      	  }
        }
        $this->setInfoMessage("Parts Activated Including Children");
    }

    /**
     * Delete Sub Storage Location
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function deleteSubStorageLocation($sender, $param)
	{
		try
		{
			$this->setInfoMessage("");
			$this->setErrorMessage("");
			$deleteWarehouseId = trim($this->EditWarehouseId->Value);
			$deleteWarehouse = Factory::service("Warehouse")->getWarehouse($deleteWarehouseId);
			if($deleteWarehouse instanceof Warehouse)
			{
				$deleteWarehouse->setActive(false);
				Factory::service("Warehouse")->save($deleteWarehouse);
			}
			$this->setInfoMessage("Warehouse '".$deleteWarehouse->getName()."' has been deleted!");
		}
		catch(Exception $ex)
		{
			$this->setErrorMessage($ex->getMessage());
		}
	}

	/**
	 * Get Show Parts Allow CheckBox
	 *
	 * @param unknown_type $feature
	 * @return unknown
	 */
	public function getShowPartsAllowCheckBox($feature)
	{
		if(strpos($feature,',') !== false)
			$feature = explode(',',$feature);
		else
			$feature = array($feature);

		if(Core::getRole() == null) return false;

		$features = Core::getRole()->getFeatures();
		$result = false;
		foreach($features as $f)
		{
			foreach($feature as $s)
			{
				if(trim($s) == $f->getName())
				{
					return true;
				}
			}
		}
		return $result;
	}

	/**
	 * Load EditNote
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function loadEditNote($sender, $param)
	{
		$this->EditWarehouse->Checked = false;
		$this->PrintLabelList->SelectedIndex = 0;

		$editWarehouseId = trim($this->EditWarehouseId->Value);
		$editWarehouse = Factory::service("Warehouse")->getWarehouse($editWarehouseId);
		if ($editWarehouse instanceof Warehouse)
		{
			//get the names of all the sibling warehouses, excluding itself
			$parent = $editWarehouse->getParent();
			if ($parent instanceof Warehouse)
			{
				$this->siblingWarehouseNames->Value = implode(Warehouse::DEFAULT_BREADCRUMB_SEPARATOR, Factory::service("Warehouse")->getWarehouseSiblingNameArray($parent->getId(), $editWarehouse->getName()));
			}

			$this->EditWarehouseName->Text=$editWarehouse->getName();
			$this->EditMoveable->Checked = $editWarehouse->getMoveable();
			$this->EditAllowParts->Checked = $editWarehouse->getParts_allow();
			$this->oldPartsAllow->Value = (int)$this->EditAllowParts->Checked;

			$this->EditAllowParts->Enabled = false;
			//if we have the feature for toggling AND can edit this warehouse by Edit Warehouse Filter, && whether we have the Edit Warehouse Filter OR are Sys Admin
			if ($this->getPartsAllowFeature() && $this->canAddEditThisOrBelowThisWarehouse($editWarehouse->getId()) == true)
			{
				$this->EditAllowParts->Enabled = true;

				$this->whPartsCount->Value = Factory::service("PartInstance")->countByCriteria("warehouseid=?", array($editWarehouse->getId()));
			}

			$this->positionLikeChildren->Value = Factory::dao("Warehouse")->countByCriteria("position LIKE '" . $editWarehouse->getPosition() . "%'") - 1;
			if ($this->positionLikeChildren->Value == 0)
			{
				$this->IgnoreStockCount_ApplyToChildren_Panel->Visible = false;
				$this->IgnoreStocktake_ApplyToChildren_Panel->Visible = false;
				$this->IgnoreStockCount_ApplyToChildren_Lbl->Text = '';
			}
			else
			{
				$this->IgnoreStockCount_ApplyToChildren_Panel->Visible = true;
				$this->IgnoreStocktake_ApplyToChildren_Panel->Visible = true;

				if ($this->positionLikeChildren->Value == 1)
				{
					$this->IgnoreStockCount_ApplyToChildren_Lbl->Text = '* apply to ' . $this->positionLikeChildren->Value . ' sub-warehouse?';
				}
				else
				{
					$this->IgnoreStockCount_ApplyToChildren_Lbl->Text = '* apply to all ' . $this->positionLikeChildren->Value . ' sub-warehouses?';
				}

			}
			$this->IgnoreStocktake_ApplyToChildren_Lbl->Text = $this->IgnoreStockCount_ApplyToChildren_Lbl->Text;

			$this->IgnoreStockCount_ApplyToChildren->Checked = false;
			$this->IgnoreStocktake_ApplyToChildren->Checked = false;
			$this->IgnoreStockCount->Checked = $editWarehouse->getIgnoreStockCount();

			$this->IgnoreStocktake->Checked = $editWarehouse->getIgnoreStocktake();

			$this->whCodeTxt->Text = $editWarehouse->getWarehouseCode();

			//check access
			$isSysAdmin = UserAccountService::isSystemAdmin();
			if ($isSysAdmin || Factory::service("UserPreference")->getOption(Core::getUser(),'allowIgnoreStockCount_RestrictedAreaWarehouse') == 1)
			{
				$this->IgnoreStockCount_ApplyToChildren->Enabled = true;
				$this->IgnoreStocktake_ApplyToChildren->Enabled = true;
				$this->IgnoreStockCount->Enabled = true;
				$this->IgnoreStocktake->Enabled = true;
			}
			else
			{
				$this->IgnoreStockCount_ApplyToChildren->Enabled = false;
				$this->IgnoreStocktake_ApplyToChildren->Enabled = false;
				$this->IgnoreStockCount_ApplyToChildren->Checked = false;
				$this->IgnoreStocktake_ApplyToChildren->Checked = false;
				$this->IgnoreStockCount->Enabled = false;
				$this->IgnoreStocktake->Enabled = false;
			}

			$this->IsMainStore->Checked = (bool)(trim($editWarehouse->getAlias(WarehouseAliasType::ALIASTYPEID_IS_MAIN_STORE)));
    		$this->EditWarehouseStocktakeFrequency->Text = trim($editWarehouse->getAlias(WarehouseAliasType::ALIASTYPEID_STOCKTAKE_FREQUENCY));
    		$this->EditWarehouseStocktakeNextDue->Text =  trim($editWarehouse->getAlias(WarehouseAliasType::ALIASTYPEID_NEXT_STOCKTAKE_DATE));
    		$this->EditWarehouseLastStocktake->Text =  trim($editWarehouse->getAlias(WarehouseAliasType::ALIASTYPEID_LAST_STOCKTAKE_DATE));

			//load the category
			$warehouseCategory = $editWarehouse->getWarehouseCategory();
			if($warehouseCategory instanceof WarehouseCategory)
			{
				//if user trying to edit "Black Hole" warehouse and he/she is not a System Admin
				try
				{
					$this->EditWarehouseCategoryList->setSelectedValue($warehouseCategory->getId());
				}
				catch(Exception $ex)
				{
					$this->EditWarehouseCategoryList->setSelectedIndex(0);
				}
			}
			else
			{
				$this->EditWarehouseCategoryList->setSelectedIndex(0);
			}

			//load the site for Warehouse
			$this->loadSiteDetails($editWarehouse);

			//load address
			$facility = $editWarehouse->getFacility();
			if($facility instanceof Facility)
			{
				$this->EditWarehouse->Checked = true;

		    	$this->facilityName->Text = '';
		    	$alias = $editWarehouse->getAlias(WarehouseAliasType::ALIASTYPEID_FACILITY_NAME);
		    	if ($alias != null)
		    	{
		    		$this->facilityName->Text = $alias;
		    	}

		    	$this->deliveryInst->Text = '';
		    	$alias = $editWarehouse->getAlias(WarehouseAliasType::ALIASTYPEID_SPEC_DEL_INST_ID);
		    	if ($alias != null)
		    	{
		    		$this->deliveryInst->Text = $alias;
		    	}

		    	$address = $facility->getAddress();
		    	if($address instanceof Address)
		    	{
		    		$this->editAddress->AddressName->Text = $address->getAddressName();
		    		$this->editAddress->Line1->Text = $address->getLine1();
		    		$this->editAddress->Line2->Text = $address->getLine2();
		    		$this->editAddress->Suburb->Text = $address->getSuburb();
		    		$this->editAddress->Postcode->Text = $address->getPostcode();
		    		$this->editAddress->AddressCode->Text = $address->getAddressCode();
		    		$this->editAddress->AddressType->Text = $address->getAddressType();
					$this->editAddress->bindAddressGroupList($address->getAddressGroup()->getId());

					$country = $address->getCountry();
					$this->bindList($this->editAddress->Country,Factory::service("Country")->findAll());

					if($country instanceof Country)
		    			$this->editAddress->Country->setSelectedValue($country->getId());

		    		$state = $address->getState();
		    		$this->bindList($this->editAddress->State,Factory::service("State")->findAll());

		    		if($state instanceof State)
		    			$this->editAddress->State->setSelectedValue($state->getId());

		    		$this->editAddress->bindTimeZoneDataList($country, $state, $address->getSuburb());
		    	}
			}
			/// Address Load complete ///

			//load linked company
			$company = $editWarehouse->getCompany();
			$id = '';
			if ($company instanceof Company)
			{
				$id = $company->getId();
				$this->linkedCompany->setSelectedValue($company);
				$this->hiddenCompanyId->Value = $id;
				$this->Page->companyLink->NavigateUrl = '/company/' . $id;
	    		$this->Page->companyLink->Style = 'display:block;';
			}
			else
			{
				$this->linkedCompany->Text = '';
				$this->hiddenCompanyId->Value = '';
	    		$this->Page->companyLink->Style = 'display:none;';
			}
			$this->companySelected($id);
			/// Company load complete ///

			//enable/disable the status
			$this->partsStatusList->Enabled = false;
			$pos = $editWarehouse->getPosition();
			$intExtCheck = substr($pos, 0, 5);
			if (($intExtCheck == '10000' || $intExtCheck == '10001') && strlen($pos) >= 10) //we are under Internal or External, and we are past the state level
			{
				$this->partsStatusList->Enabled = true;
			}

			//load the parts status
			$status = null;
			$this->partsSatusLbl->Visible = true;
			$statusId = $editWarehouse->getAlias(WarehouseAliasType::$aliasTypeId_partsStatus);
			if ($statusId != null && $statusId != '0')
			{
			    $status = Factory::service("PartInstanceStatus")->get($statusId);
			}

			$this->_bindPartsStatusList($status);

			if ($statusId != null)
			{
				if ($statusId == '0')
				{
					$this->partsStatusList->setSelectedValue(0);
					$this->partsStatusId->Value = 0;
				}
				else
				{
				    if ($status instanceof PartInstanceStatus)
				    {
						$this->partsStatusList->setSelectedValue($status->getId());
						$this->partsStatusId->Value = $status->getId();
						if (in_array($status->getId(), Factory::service("PartInstanceStatus")->getRestrictedStatusIds()))
						{
							$this->partsStatusList->Enabled = false;
						}
				    }
				}
			}
			else
			{
				$this->partsSatusLbl->Visible = false;
				$this->partsStatusId->Value = '';
				$this->partsStatusList->setSelectedIndex(0);
			}

			//get the inherited status
			$this->inheritStatus->Value = '';
			$whStatusId = Factory::service("Warehouse")->getWarehousePartsStatusId($editWarehouse, false);
			if ($whStatusId == 0 && $whStatusId != '')
			{
				$this->inheritStatus->Value = '0_Status to Remain';
			}
			else
			{
				$whStatus = Factory::service("PartInstanceStatus")->get($whStatusId);
		        if ($whStatus instanceof PartInstanceStatus)
		        {
					$this->inheritStatus->Value = $whStatus->getId() . '_' . $whStatus->getName();
		        }
			}

			//hide the status label if disabled
			if (!$this->partsStatusList->Enabled)
			{
				$this->partsSatusLbl->Visible = false;
			}

			//load reporting warehouse
			$this->reportingWarehouse->setSelectedIndex(0);
			$repWh = Factory::service("WarehouseRelationship")->findByCriteria('fromWarehouseid=? AND type=?', array($editWarehouse->getId(), WarehouseRelationship::TYPE_REPWH));
			if (count($repWh) > 0)
			{
				$wh = $repWh[0]->getToWarehouse();
				if ($wh instanceof Warehouse)
				{
					try
					{
						$this->reportingWarehouse->setSelectedValue($wh->getId());
// 						if ($repWh[0]->getSendEmail())
// 						{
// 							$this->RepWhSendEmailChk->Checked = true;
// 						}
					}
					catch(Exception $e){Debug::inspect($e->getMessage());} //make sure the value is in the list to be selected, if not force it to be reselected.
				}
			}

			///////////////////If readonly mode then disable all the controls of the edit panel/////////////////////////////////
			if($this->readOnly == true) // block all
			{
				$this->EditWarehouseName->Enabled = false;
				$this->EditAllowParts->Enabled = false;
				$this->EditMoveable->Enabled = false;
				$this->IgnoreStockCount->Enabled = false;
				$this->IgnoreStocktake->Enabled = false;
				$this->IsMainStore->Enabled = false;

				$this->EditWarehouseStocktakeFrequency->Enabled = false;
				$this->EditWarehouseStocktakeNextDue->Enabled = false;

				$this->EditWarehouseCategoryList->Enabled = false;
				$this->WarehouseLabelQty->Enabled = false;
				$this->PrintLabelList->Enabled = false;
				$this->linkedCompany->Enabled = false;
				$this->linkedCompany->getAttributes()->add("style", "background-color:;");
				$this->partsStatusList->Enabled = false;

				$this->editSite->Enabled = false;
				$this->editSite->getAttributes()->add("style", "background-color:;");

				if($this->EditWarehouse->Checked == true)
				{
					$this->EditWarehouse->Enabled = false;
					$this->facilityName->Enabled = false;
					$this->deliveryInst->Enabled = false;
					$this->editAddress->Enabled = false;

					if($this->useCompanyAddress->Checked == true || $this->linkedCompany->getSelectedValue() != null ) // the warehouse is using the company address so disable all the controls
					{
						$this->useCompanyAddress->Enabled = false;
						$this->Page->companyLink->setStyle("display:none;");
						$this->removeCompanyLinkButton->setStyle('display:none;'); // hide the delete option of the company
						//$this->linkedCompany->getAttributes()->add("readonly", "readonly");
					}
				}
			}
			$info = EntityLogic::getCreatedUpdatedInfoInTimezone($editWarehouse, Factory::service("TimeZone")->getUserTimezoneFromCompany());
			$this->createdUpdatedLbl->Text =  $info['created'] . '<br />' . $info['updated'];
		}

		if($this->canTickTransitNote($editWarehouseId) && $this->readOnly == false) // check if not readonly mood and have user priviledge
		{
			$this->EditWarehouse->Enabled = $this->canTickTransitNote($editWarehouseId);
		}
		else
		{
			$this->EditWarehouse->Enabled = false;
		}
		$this->jsLbl->Text = '<script type="text/javascript">mb.hide();</script>';
	}

	/**
	 * Loads site details for Warehouse
	 *
	 * @param unknown_type $warehouse
	 */
	public function loadSiteDetails($warehouse)
	{
	    $sites = $warehouse->getSites();
	    $warehouseId = $warehouse->getId();
	    $html = '';
	    if(count($sites)>0)
	    {
	        //Display delete button only if System Admin
	        if(UserAccountService::isSystemAdmin())
	            $deleteAbility = true;
	        else
	            $deleteAbility = false;

	        $html .="<table class='DataList'>";
	        $html .="<thead>";
	        $html .="<tr>";
	        $html .="<td>Sites</td>";

	        if ($deleteAbility ===true)
	            $html .="<td width='5%'>&nbsp;</td>";
	        $html .="</tr>";
	        $html .="</thead>";
	        $html .="<tbody>";
	        $rowNo =0;

	        foreach($sites as $site)
	        {
	        	if($site instanceof Site)
	        	{
	        	    $siteId = $site->getId();
	        	    $html .="<tr class='".($rowNo %2==0? "DataListItem" : "DataListAlterItem")."'>";
	        	    $html .="<td>".$site->getSiteCode()." - ".$site->getCommonName()."</td>";

	        	    //If sys admin then delete capability
	        	    if ($deleteAbility ===true)
	        	        $html .="<td><input type='image' src='/themes/images/delete.png' onClick='".$this->getId()."_deleteSiteLink($warehouseId,$siteId); return false;'/></td>";

	        	    $html .="</tr>";
	        	    $rowNo++;
	        	}
	        }
	        $html .="</tbody>";
	        $html.="</table>";
	    }

	    $this->resultLabel->Text = $html;
	}

	/**
	 * See Warehouse FullPath
	 *
	 * @param unknown_type $value
	 */
	public function setWarehouseFullPath($value)
	{
		$this->hidden_warehouse_fullpath->setValue($value);
		try
		{
			$jsText = "<script type = 'text/javascript'>
							treeJs.getTree().expandPath('/" . $this->whTree->getPathFromRoot($this->whTree->getHiddenRootIdValue(), $value) . "');
					   </script>";
			$this->jsLbl->setText($jsText);
		}
		catch (Exception $e)
		{
			$this->setErrorMessage($e->getMessage());
		}
	}

	/**
	 * Reset Warehouse Full Path
	 *
	 */
	public function resetWarehouseFullPath()
	{
		$this->hidden_warehouse_fullpath->setValue('');
	}

	/**
	 * Add or Edit Sub Storage Location
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $params
	 */
	public function addOrEditSubStorageLocation($sender,$params)
	{
		try
		{
			$errMsg = '<br />';
			$infoMsg = "";
			$this->setInfoMessage("");
			$this->setErrorMessage("");

			//if this is a new warehouse
			$warehouseId = trim($this->EditWarehouseId->Value);
			if ($warehouseId == '')
			{
				$warehouse = new Warehouse();

				$rootArray = explode("/", trim($this->whTree->whIdPath->Value));
				$warehouseParent = Factory::service("Warehouse")->getWarehouse(array_pop($rootArray)); //get the parent from the tree
			}
			else
			{
				$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId);
				if (!$warehouse instanceof warehouse)
					throw new Exception("Invalid warehouse!");

				$warehouseParent = $warehouse->getParent();
			}


			//get the old warehouse name
			$oldWhName = $warehouse->getName();

			$warehouse->setName($this->EditWarehouseName->Text);

	    	$moveable = $this->EditMoveable->Checked ? 1 : 0;
	    	if ($moveable != $warehouse->getMoveable())
	    	{
		    	$infoMsg .= "<br />'Moveable' has been set to '".($this->EditMoveable->Checked ? "Yes" : "No") . "'";
		    	$warehouse->setMoveable($this->EditMoveable->Checked);
	    	}

	    	//set the allow parts flag
	    	$allowParts = $this->EditAllowParts->Checked ? true : false;
	    	$warehouse->setParts_allow($allowParts);
	    	if($warehouse->getParts_allow() != $allowParts)
	    	{
	    		$infoMsg .= "<br />'Allow Parts' has been set to '".($allowParts ? "Yes" : "No")."'. ";
	    	}

	    	//set the category
			$warehouseCategory = Factory::service("WarehouseCategory")->get($this->EditWarehouseCategoryList->getSelectedValue());
			$warehouse->setWarehouseCategory($warehouseCategory);

			//user has selected to link to a company
			if ($this->hiddenCompanyId->Value != '')
			{
				$oldCompanyId = '';
				$oldCompany = $warehouse->getCompany();
				if ($oldCompany instanceof Company)
					$oldCompanyId = $oldCompany->getId();

				$linkedCompany = Factory::service("Company")->getCompany($this->hiddenCompanyId->Value);
				if ($linkedCompany instanceof Company)
				{
					if ($oldCompanyId != $linkedCompany->getId())
					{
						$warehouse->setCompany($linkedCompany);
						$infoMsg .= "<br />'Linked Company' has been set to ': " . $linkedCompany->getName() . "'";
					}
				}
			}
			else
			{
				$warehouse->setCompany(null);
				$this->Page->linkedCompany->Text = '';
			}

			$facilityName = $deliveryInst = '';

			//set the address
	    	if($this->EditWarehouse->checked)
	    	{
				$canProceed = 0;
	    		if($warehouseId != "")	// it is an existing warehouse. So we can do the facility check
				{
					$errorMessage = Factory::service("Warehouse")->checkCanSetWarehouseAsFacility($warehouseId);
					if($errorMessage !== true)
						throw new Exception($errorMessage);
					else
						$canProceed = 1;
				}
				else	// its a new warehouse.
				{
					$errorMessage = Factory::service("Warehouse")->checkCanSetWarehouseAsFacility($warehouseParent->getId(), "up_include");
					if($errorMessage !== true)
						throw new Exception($errorMessage);
					else
						$canProceed = 1;
				}

				$facilityName = $this->facilityName->Text;
				$deliveryInst = $this->deliveryInst->Text;
	    		if($canProceed == 1)
	    		{
					$facility = $warehouse->getFacility();
					if(!$facility instanceof Facility)
						$facility = new Facility();

					//user has selected a company and has chosen to use the company address
					if ($this->hiddenCompanyId->Value != '' && $this->useCompanyAddress->Checked)
					{
						$linkedCompany = Factory::service("Company")->getCompany($this->hiddenCompanyId->Value);
						if ($linkedCompany instanceof Company)
						{
							$companyAddress = $linkedCompany->getAddress();
							if(!$companyAddress instanceof Address)
								throw new Exception('Unable to use Company address...');

							$address = $companyAddress;
						}
					}
					else
					{
						$address = new Address();
					    $address->setAddressName($this->editAddress->AddressName->Text);
						$address->setLine1($this->editAddress->Line1->Text);
						$address->setLine2($this->editAddress->Line2->Text);
						$address->setSuburb($this->editAddress->Suburb->Text);
						$address->setPostcode($this->editAddress->Postcode->Text);
						$address->setAddressCode($this->editAddress->AddressCode->Text);
						$address->setAddressType($this->editAddress->AddressType->Text);

				        $addressGroup = Factory::service("AddressGroup")->get($this->editAddress->AddressGroup->getSelectedValue());
						$address->setAddressGroup($addressGroup);

				        $country = Factory::service("Country")->get($this->editAddress->Country->getSelectedValue());
						$address->setCountry($country);

				        $state = Factory::service("State")->get($this->editAddress->State->getSelectedValue());
						$address->setState($state);

						$address->setTimezone($this->editAddress->TimeZone->getSelectedValue());

						//save the address
						Factory::service("Address")->save($address);
					}

					//set the address to facility
		    		$facility->setAddress($address);
		    		Factory::service("Facility")->save($facility);

		    		//set the facility
		    		$warehouse->setFacility($facility);
	    		}
	    	}
	    	else
	    	{
    			$warehouse->setFacility(null);
	    	}

		    //find the Lost Stock Warehouse
		    $tempParent = $warehouseParent;
		    $lostStockWarehouse = $warehouseParent->getLostStockWarehouse();
		    while(!(empty($tempParent)) && empty($lostStockWarehouse))
			{
				$tempParent=$tempParent->getParent();
				if (!(empty($tempParent)))
				{
					$lostStockWarehouse=$tempParent->getLostStockWarehouse();
					$tempFacility=$tempParent->getFacility();
					if (!(empty($tempFacility))) break;
				}
			}

			//only set if the current warehouse doesn't have one, and the one calculated is valid
	    	if ($lostStockWarehouse instanceof Warehouse && !$warehouse->getLostStockWarehouse() instanceof Warehouse )
	    	{
	    		$warehouse->setLostStockWarehouse($lostStockWarehouse);
	    	}

			//get all chidren before add or edit this warehouse
		    $childrenCount = Factory::service('Warehouse')->getWarehouseChildrenCount($warehouseParent, 1);

		    //assign this warehouse to the parent, when this is adding a new warehouse
		    if ($warehouseId == '')
		    {
		    	Factory::service("Warehouse")->addWarehouse($warehouseParent, $warehouse);
		    }

	    	//set ignore in stock count value
	    	$ignoreStockCountCheck = $this->IgnoreStockCount->Checked ? 1 : 0;
	    	$ignoreStockCountValue = $warehouse->getIgnoreStockCount();
	    	if ($ignoreStockCountCheck != $ignoreStockCountValue || $this->IgnoreStockCount_ApplyToChildren->Checked)
	    	{
	    		$infoMsg .= "<br />'Ignore Stock Count' has been set to '" . ($ignoreStockCountCheck == 1 ? "Yes" : "No") . "'";
	    		$warehouse->setIgnoreStockCount($ignoreStockCountCheck);

	    		//update the warehouse and children at once
	    		if ($this->IgnoreStockCount_ApplyToChildren->Checked)
	    		{
	    			$rows = Factory::service("Warehouse")->updateIgnoreInValuesForChildren($warehouse, $ignoreStockCountCheck, Warehouse::COLUMN_NAME_IGNORESTOCKCOUNT);
	    			if ($rows > 1)
	    			{
	    				$infoMsg .= ' including ' . ($rows-1) . ' sub-warehouse(s)';
	    			}
	    			else
	    			{
	    				$infoMsg .= ' including sub-warehouse(s)';
	    			}
	    		}
	    	}

	    	//set ignore in stocktake value
	    	$ignoreStocktakeCheck = $this->IgnoreStocktake->Checked ? 1 : 0;
	    	$ignoreStocktakeValue = $warehouse->getIgnoreStocktake();
	    	if ($ignoreStocktakeCheck != $ignoreStocktakeValue || $this->IgnoreStocktake_ApplyToChildren->Checked)
	    	{
	    		$infoMsg .= "<br />'Ignore Stocktake' has been set to '" . ($ignoreStocktakeCheck == 1 ? "Yes" : "No") . "'";
	    		$warehouse->setIgnoreStocktake($ignoreStocktakeCheck);

	    		//update the warehouse and children at once
	    		if ($this->IgnoreStocktake_ApplyToChildren->Checked)
	    		{
	    			$rows = Factory::service("Warehouse")->updateIgnoreInValuesForChildren($warehouse, $ignoreStocktakeCheck, Warehouse::COLUMN_NAME_IGNORESTOCKTAKE);
	    			if ($rows > 1)
	    			{
	    				$infoMsg .= ' including ' . ($rows-1) . ' sub-warehouse(s)';
	    			}
	    			else
	    			{
	    				$infoMsg .= ' including sub-warehouse(s)';
	    			}
	    		}
	    	}

	    	//warehouse code
			$oldWhCode = $warehouse->getWarehouseCode();
			$newWhCode = trim($this->whCodeTxt->Text);
			if ($oldWhCode != $newWhCode)
			{
	    		$infoMsg .= "<br />'Warehouse Code' has been set to '" . $newWhCode . "'";
				$warehouse->setWarehouseCode($newWhCode);
			}

	    	//save the warehouse
	    	Factory::service("Warehouse")->save($warehouse);

	    	//set or remove the facility name
	    	if ($facilityName != '')
	    	{
	    		Factory::service("WarehouseAlias")->updateWarehouseAlias($warehouse->getId(), $facilityName, WarehouseAliasType::ALIASTYPEID_FACILITY_NAME);
	    	}
	    	else
	    	{
	    		Factory::service("WarehouseAlias")->deleteWarehouseAlias($warehouse->getId(), WarehouseAliasType::ALIASTYPEID_FACILITY_NAME);
	    	}

	    	//set or remove the delivery instructions
	    	if ($deliveryInst != '')
	    	{
	    		Factory::service("WarehouseAlias")->updateWarehouseAlias($warehouse->getId(), $deliveryInst, WarehouseAliasType::ALIASTYPEID_SPEC_DEL_INST_ID);
	    	}
	    	else
	    	{
	    		Factory::service("WarehouseAlias")->deleteWarehouseAlias($warehouse->getId(), WarehouseAliasType::ALIASTYPEID_SPEC_DEL_INST_ID);
	    	}

	    	//set or remove the reporting warehouse relationship
    		Factory::service("WarehouseRelationship")->deleteWhRelationship($warehouse, null, WarehouseRelationship::TYPE_REPWH);
    		$reportingWhId = $this->reportingWarehouse->getSelectedValue();
	    	if ($reportingWhId != '')
	    	{
	    		$repWh = Factory::service("Warehouse")->getWarehouse($reportingWhId);
	    		if (!$repWh instanceof Warehouse)
	    		{
	    			throw new Exception("Invalid Reporting Warehouse selected");
	    		}
	    		Factory::service("WarehouseRelationship")->createWhRelationship($warehouse, $repWh, WarehouseRelationship::TYPE_REPWH, true); //force sendEmail to true
	    	}

			//set the site
			if(trim($this->editSite->Text) != '')
			{
				$siteId = trim($this->Page->hiddenSiteId->Value);
				if($siteId != '')
				{
				    $site = Factory::service("Site")->get($siteId);
				    $siteWarehouses = $site->getWarehouses();

				    //If site is linked to any other warehouse then throw exception!
					if (count($siteWarehouses) > 0)
					{
					    if (UserAccountService::isSystemAdmin())
					    {
					    	$siteMsg = "Site cannot be linked to multiple Warehouses! To link SITE: ".$site->getCommonName()." to WAREHOUSE: ".$warehouse->getName()." please remove the link with WAREHOUSE: ".$siteWarehouses[0]->getName();
					    }
					    else
					    {
					    	$siteMsg = "Site cannot be linked to multiple Warehouses! To link SITE: ".$site->getCommonName()." to WAREHOUSE: ".$warehouse->getName()." please contact Technology to remove the link with WAREHOUSE: ".$siteWarehouses[0]->getName();
					    }
				        throw new Exception($siteMsg);
					}
					else
					{
				        //Create site-warehouse link
	    	    		$this->createSiteWarehouseRelationship($siteId, $warehouse->getId());
					}
				}
			}

			//generate new BL Barcode
	    	$barcode = trim($warehouse->getAlias(2));
	    	if($barcode =="")
	    	{
				$sequence = Factory::service("Sequence")->get(5);
				if($sequence instanceof Sequence)
				{
					$bcl = Factory::service("Sequence")->getNextNumberAsBarcode($sequence);
					$errMsg .= Factory::service("WarehouseAlias")->updateWarehouseAlias($warehouse->getId(), $bcl, 2) . '<br />';
				}
	    	}

			//set frequency
	    	$frequency = trim($this->EditWarehouseStocktakeFrequency->Text);
	    	$frequencyValue = trim($warehouse->getAlias(9));
			if($frequency != '' && $frequency != $frequencyValue)
			{
				$errMsg .= Factory::service("WarehouseAlias")->updateWarehouseAlias($warehouse->getId(), $frequency, 9) . '<br />';
				$infoMsg .= "<br />'Stocktake Frequency' has been set to '" . $frequency . "' weeks";
			}

			//set next stock take due
	    	$nextStockTakeDue = trim($this->EditWarehouseStocktakeNextDue->Text);
	    	$stocktakeValue = trim($warehouse->getAlias(8));
			if($nextStockTakeDue != '' && $nextStockTakeDue != $stocktakeValue)
			{
				$errMsg .= Factory::service("WarehouseAlias")->updateWarehouseAlias($warehouse->getId(), $nextStockTakeDue, 8) . '<br />';
				$infoMsg .= "<br />'Next Stocktake' has been set to '" . $nextStockTakeDue . "'";
			}

			//set the parts status for warehouse
			$currentStatusId = $warehouse->getAlias(WarehouseAliasType::$aliasTypeId_partsStatus);
			$newStatusId = trim($this->partsStatusList->getSelectedValue());
			if ($currentStatusId != $newStatusId) //we've got something to change
			{
				$countPis = 0;
				if ($newStatusId == '666') //we are setting to inherit
				{
	    			$newStatus = 'Inherit from Parent';
					$errMsg .= Factory::service("WarehouseAlias")->deleteWarehouseAlias($warehouse->getId(), WarehouseAliasType::$aliasTypeId_partsStatus) . '<br />';

    				$inheritStatus = $this->inheritStatus->Value;
    				if ($inheritStatus != '')
    				{
	    				$inheritStatus = explode('_', $this->inheritStatus->Value);

						$updateStatus = Factory::service("PartInstanceStatus")->get($inheritStatus[0]);
				    	if ($updateStatus instanceof PartInstanceStatus)
				    	{
				    		$countPis = $this->updatePartInstanceStatuses($warehouse->getId(), $inheritStatus[0]);
				   		}
    				}
				}
				else if ($newStatusId == '0') //we are setting to remain
				{
					$errMsg .= Factory::service("WarehouseAlias")->updateWarehouseAlias($warehouse->getId(), $newStatusId, WarehouseAliasType::$aliasTypeId_partsStatus) . '<br />';
					$newStatus = 'Status to Remain';
				}
				else //we are setting to a valid status
				{
			    	$newStatus = Factory::service("PartInstanceStatus")->get($newStatusId);
			    	if ($newStatus instanceof PartInstanceStatus)
			    	{
			    		$errMsg .= Factory::service("WarehouseAlias")->updateWarehouseAlias($warehouse->getId(), $newStatus->getId(), WarehouseAliasType::$aliasTypeId_partsStatus) . '<br />';
			    		$countPis = $this->updatePartInstanceStatuses($warehouse->getId(), $newStatusId);
			    		$updateStatus = $newStatus->getName();
			   		}
				}
    			$infoMsg .= "<br />'Parts Status' has been set to '" . $newStatus . "'";

    			if ($countPis > 0)
    			{
    				$infoMsg .= " - $countPis part instance(s) have had their status changed to '" . $updateStatus . "'";;
    			}
			}

			//set is main store
	    	$isMainStore = $this->IsMainStore->Checked ? 1 : 0;
	    	$isMainStoreValue = trim($warehouse->getAlias(WarehouseAliasType::ALIASTYPEID_IS_MAIN_STORE));

    		if ($isMainStore != (int)$isMainStoreValue)
    		{
    			$errMsg .= Factory::service("WarehouseAlias")->updateWarehouseAlias($warehouse->getId(), $isMainStore, WarehouseAliasType::ALIASTYPEID_IS_MAIN_STORE) . '<br />';
    			$infoMsg .= "<br />'IsMainStore' has been set to '".($this->IsMainStore->Checked ? "Yes" : "No") . "'";
    		}

    		$infoMsg = "Warehouse '".$warehouse->getName()."' has been saved successfully! ". $infoMsg;
	    	$this->setInfoMessage($infoMsg);
	    	$this->setErrorMessage($errMsg);

	    	Factory::service("Warehouse")->updateWarehouseBreadcrumbsAndState($warehouse, array('oldWhName' => $oldWhName));

	    	//get the names of all the sibling warehouses
			$parent = $warehouse->getParent();
			if ($parent instanceof Warehouse)
			{
		    	$this->siblingWarehouseNames->Value = implode(Warehouse::DEFAULT_BREADCRUMB_SEPARATOR, Factory::service("Warehouse")->getWarehouseSiblingNameArray($parent->getId()));
			}
            $this->EditButton->setEnabled(true);

            //total part instance quantity at parent
            $parts = Factory::service("Warehouse")->getPartInstanceCountForWarehouse($warehouseParent->getId());

			//if this is a new warehouse under the current warehouse and is the first child
            if ($childrenCount == 0 && $warehouseId == "")
            {
            	//if the new warehouse is parts allow and the parent has parts in it, then move all the part instances to the new warehouse, and transfer parts allow flag once completed
            	if ($allowParts && $parts > 0)
            	{
            		$this->successMessage->Value = $infoMsg;
            		$this->errorMessage->Value = $errMsg;
            		$this->jsLbl->Text = "<script type=\"text/javascript\">moveParts(" . $warehouseParent->getId() . "," . $warehouse->getId() . ");</script>";
            		return;
            	}
            	else if ($allowParts && $warehouseParent->getParts_allow()) //if the parent is parts allow then remove it
            	{
            		$warehouseParent->setParts_allow(false);
            		Factory::service("Warehouse")->save($warehouseParent);
            	}
            }
	    	$this->jsLbl->Text = "<script type=\"text/javascript\">reloadNode();</script>";
		}
		catch(Exception $ex)
		{
			$this->jsLbl->Text = '<script type="text/javascript">mb.hide();toggleEditButton(false);</script>';
		    if (strpos($ex->getMessage(),"Zone"))
		    {
		    	$this->setErrorMessage("Invalid Suburb, Post Code, State, and Country combination! Please check information entered.<br /><br />");
		    }
			else
			{
				$this->setErrorMessage("Error Occurred: ".$ex->getMessage());
			}
		}
	}

	/**
	 * Finish ajax processing, show messages
	 */
    public function finishProcessingPart()
    {
		$toWarehouse = Factory::service("Warehouse")->get($this->newWarehouseForMove->Value);
	    $warehouseParent = Factory::service("Warehouse")->get($this->fromWarehouseForMove->Value);

	    if($toWarehouse instanceOf Warehouse && $warehouseParent instanceOf Warehouse)
	    {
    		if(!$this->exceptionMessage->Value)
    		{
    			$warehouseParent->setParts_allow(false);
    			Factory::service("Warehouse")->save($warehouseParent);
    		}
	    }

    	$this->successMessage->Value = $this->successMessage->Value . "<br>All part instances under '".$warehouseParent->getName()."' have been moved to '".$toWarehouse->getName()."'";

    	if ($this->exceptionMessage->Value || $this->errorMessage->Value)
    	{
    		$this->setErrorMessage($this->exceptionMessage->Value . $this->errorMessage->Value);
    	}

    	$this->setInfoMessage($this->successMessage->Value);
    }

    /**
     * call javascript function to hide modal box and call finishProcessingNote()
     */
	public function finishProcessingParts()
	{
    	$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessingPart();</script>";
    }

    /**
     * cycle through via ajax until all the parts have moved
     *
     */
	public function processMoveParts()
    {
   	 	$numberOfPartsProcessedPerAjaxCallForMovePart = Factory::service("DontHardcode")->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'numberOfPartsProcessedPerAjaxCallForMovePart',false);
    	if (!is_numeric($numberOfPartsProcessedPerAjaxCallForMovePart))
    	{
    		$numberOfPartsProcessedPerAjaxCallForMovePart = 1;
    	}

    	try
    	{
	    	$count = 0;
	    	$toWarehouse = Factory::service("Warehouse")->get($this->newWarehouseForMove->Value);
    		$partInstances = Factory::service("PartInstance")->getPartInstancesForWarehouse(Factory::service("Warehouse")->get($this->fromWarehouseForMove->Value), false);
    		foreach($partInstances as $partInstance)
    		{
    			Factory::service("PartInstance")->movePartInstanceToWarehouse($partInstance,$partInstance->getQuantity(),$toWarehouse,false, null,"Moved Via 'Admin Warehouse'.");
    			if($count == $numberOfPartsProcessedPerAjaxCallForMovePart)
	    		{
	    			return array('stop' => false);
	    		}
	    		$count++;
    		}

    		if (count($partInstances) == 0)
    		{
    			return array('stop' => true);
    		}
    	}
    	catch (Exception $e)
    	{
    		$this->exceptionMessage->Value .= "<br>" . $e->getMessage();
    		return array('stop' => false);
    	}
    	return array('stop' => false);
    }

    /**
     * Update PartInstance Statuses
     *
     * @param unknown_type $warehouseId
     * @param unknown_type $newStatusId
     * @param unknown_type $performUpdate
     * @return unknown
     */
	public function updatePartInstanceStatuses($warehouseId, $newStatusId, $performUpdate = true)
	{
		$whIds = Factory::service("Warehouse")->getPartsStatusWarehouseIdsForSubWarehouses($warehouseId);
		return Factory::service("PartInstance")->bulkChangePartInstanceStatusesInWarehouses($whIds, $newStatusId, $performUpdate);
	}

	/**
	 * Can Tick TransitNote
	 *
	 * @param unknown_type $warehouseId
	 * @return unknown
	 */
    public function canTickTransitNote($warehouseId)
    {
    	if(UserAccountService::isSystemAdmin())
    		return true;

		//if this is a new warehouse
		if($warehouseId=="")
			return true;

		$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId);
		if(!$warehouse instanceof Warehouse)
			return false;

		$facility = $warehouse->getFacility();
		if(!$facility instanceof Facility)
			return true;

		$sql="select distinct id from facilityrequest where active = 1 and facilityId = ".$facility->getId();
		$result= Dao::getResultsNative($sql);
		if(count($result)>0)
			return false;

		$sql="select distinct id from transitnote where active = 1 and (destinationId = ".$warehouse->getId()." or sourceId = ".$warehouse->getId().")";
		$result= Dao::getResultsNative($sql);
		if(count($result)>0)
			return false;

		return true;
    }

	private function deleteAllSiteWarehouseRelationships($warehouseId)
	{
		$sql = "delete from site_warehouse where warehouseId = $warehouseId";
		Dao::execSql($sql);
	}

	//Delete site-warehouse link
	/**
	 * Delete Site Warehouse Link
	 *
	 */
	public function deleteSiteWarehouseRelationship()
	{
	    $siteId = $this->siteValues->Value;
	    $warehouseId = $this->warehouseValues->Value;
	    $site = Factory::service("Site")->get($siteId);
	    $warehouse = Factory::service("Warehouse")->get($warehouseId);
	    Factory::service("Site")->rmWarehouse($site, $warehouse);
	    $this->loadSiteDetails($warehouse);
	}

	/**
	 * Create Site Warehouse Relationship
	 *
	 * @param unknown_type $siteId
	 * @param unknown_type $warehouseId
	 */
	private function createSiteWarehouseRelationship($siteId,$warehouseId)
	{
		$currentUserAccountId = Core::getUser()->getId();
		$sql = "insert into site_warehouse (`siteId`,`warehouseId`,`created`,`createdById`)
						value('$siteId','$warehouseId',NOW(),'$currentUserAccountId')";
		Dao::execSql($sql);
	}

	/**
	 * Get Printer
	 *
	 * @return unknown
	 */
	private function getPrinter()
    {
    	return Factory::service("Barcode")->getPrinter(Core::getUser(),true);
    }

    /**
     * Get Reset Search Button
     *
     * @return unknown
     */
	public function getResetSearchButton()
	{
		if ($this->readOnly == false)
		{
			return '<input type="Button" Value=" Reset " onClick="window.location=\'/storagelocation/\'; return false;"/>';
		}
		return '<input type="Button" Value=" Reset " onClick="window.location=\'/warehouselookup/\'; return false;"/>';
	}

	/**
	 * Get Search Active Feature
	 *
	 * @return unknown
	 */
	public function getSearchActiveFeature()
	{
	    if (UserAccountService::isSystemAdmin())
	        return true;

	    $features = Core::getRole()->getFeatures();
	    foreach($features as $feature)
	    {
	        if ($feature->getName() == 'feature_searchActive_storageLocation')
	           return true;
	    }
	    return false;
	}

	/**
	 * Get Toggle Parts Allow Feature
	 *
	 * @return unknown
	 */
	public function getPartsAllowFeature()
	{
		if (UserAccountService::isSystemAdmin())
			return true;

		$features = Core::getRole()->getFeatures();
		foreach($features as $feature)
		{
			if ($feature->getName() == 'feature_togglePartsAllow_storageLocation')
				return true;
		}
		return false;
	}

	/**
	 * Search
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function search($sender, $param)
	{
		$this->setInfoMessage("");
		$this->setErrorMessage("");

		$this->TreePanel->setStyle("display:none;");
		$this->addSubLocationButton1->Enabled = false;
		$this->showLocationBtn->Enabled = false;
		$this->TreeLabel->Text ="Path is hidden, as we are in Searching Mode! To change mode: ".$this->getResetSearchButton();

		$join = '';
		if ($this->SearchAlias->Text != '')
		{
			$join .= " INNER JOIN warehousealias wa ON (wa.warehouseid=ware.id AND wa.active = 1 AND wa.alias LIKE '%".$this->SearchAlias->Text."%') ";
		}

		$activeWhere = " WHERE ware.active IN (0,1)";
		$active = trim($this->SearchActive->getSelectedValue());
		if ($active != "")
		{
			$activeWhere = " WHERE ware.active=" . $active;
		}

		$where = '';
		if ($this->SearchName->Text!="") $where .=" AND ware.name LIKE '%".$this->SearchName->Text."%'";
		if ($this->SearchWhCode->Text!="") $where .=" AND ware.warehousecode LIKE '%".$this->SearchWhCode->Text."%'";

		$allowParts = trim($this->SearchAllowParts->getSelectedValue());
		if($allowParts!="")$where .=" AND ware.parts_allow=$allowParts";

		$moveable = trim($this->SearchMoveable->getSelectedValue());
		if($moveable!="")$where .=" AND ware.moveable= $moveable";

		$categoryIds = $this->SearchCategory->getSelectedValues();
		if(count($categoryIds)>0)
			$where .=" AND ware.warehouseCategoryId IN (".(implode(',',$categoryIds)).")";

		if ($where == '' && $join == '')
		{
			$this->setInfoMessage("Nothing to Search!");
			$this->searchWarehouseIds->Value = "0";
			return;
		}

		$sql = "SELECT ware.id FROM warehouse ware" . $join . $activeWhere . $where;
		$result = Dao::getResultsNative($sql);

		if (count($result)==0)
		{
			$this->setInfoMessage("No Data Found!");
			$this->searchWarehouseIds->Value = "0";
			$this->jsLbl->Text = '<script type="text/javascript">mb.hide();</script>';
			return;
		}

		$ids = array();
		foreach(Dao::getResultsNative($sql) as $row)
		{
			$ids[] = $row[0];
		}
		$this->searchWarehouseIds->Value = "ware.id in (".implode(",",$ids).")";
		$this->jsLbl->Text = '<script type="text/javascript">activateSearch();</script>';
	}

	/**
	 * Get Warehousepath
	 *
	 */
	public function getWarehousePath()
	{
		$warehouseId = trim($this->WarehouseIDField->Value);
		$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId);

		if(!$warehouse instanceof Warehouse)
			$path = "Invalid warehouse!";
		else
		{
			$foundPartsStatus = false;

			$path = "Path: ".Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,"/");

			if ($warehouse->getWarehouseCode() !== '')
			{
				$path .= "<br />Warehouse Code: " . $warehouse->getWarehouseCode();
			}
			$sql = "select wat.name,if(wa.warehouseAliasTypeId=9,concat(wa.alias,' week(s)'),wa.alias), wat.id from warehousealias wa left join warehousealiastype wat on (wat.id = wa.warehouseAliasTypeId) where wa.warehouseId = $warehouseId and wa.active = 1 and wa.warehouseAliasTypeId!=2";
			$result = Dao::getResultsNative($sql);
			foreach($result as $row)
			{
				if (!is_null($row[1]) && $row[1] != '')
				{
					$val = $row[1];
					if ($row[2] == WarehouseAliasType::$aliasTypeId_partsStatus)
					{
						if ($val == "0") $val = 'Status to Remain';
						else
						{
							$sql = "SELECT name FROM partinstancestatus WHERE id={$row[1]}";
							$result = Dao::getSingleResultNative($sql);
							if ($result !== false)
								$val = $result[0];
						}
						$foundPartsStatus = true;
					}
					$path .= "<br />{$row[0]}: " . $val;
				}
			}

			if (!$foundPartsStatus) //lets see if its inheriting from somewhere, if we didn't find a status above
			{
				$whStatusId = Factory::service("Warehouse")->getWarehousePartsStatusId($warehouse, false);
				if ($whStatusId == 0 && $whStatusId != '')
				{
					$path .= "<br />Parts Status: Status to Remain (Inherited)";
				}
				else
				{
					$whStatus = Factory::service("PartInstanceStatus")->get($whStatusId);
			        if ($whStatus instanceof PartInstanceStatus)
			        {
						$path .= "<br />Parts Status: {$whStatus->getName()} (Inherited)";
			        }
				}
			}
		}

		$this->PathFieldPhp->Value = $path;
		$this->WarehouseIDFieldPhp->Value = $warehouseId;
	}

	/**
	 * Label List Count
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function LabelListCount($sender, $param)
    {
    	if($this->PrintLabelList->SelectedValue==1)
    	{
 			$this->WarehouseLabelQty->Text=1;
    	}
    	else
    	{
 			$this->WarehouseLabelQty->Text=1;
    	}
    }

    /**
     * Get Labels
     *
     * @param unknown_type $warehouse
     * @return unknown
     */
	private function getLabels($warehouse)
    {
    	$label=array();
		if(!$warehouse instanceof Warehouse)
			$this->setErrorMessage("Invalid warehouse!");
		else
		{
			$results=Factory::service("Warehouse")->getWarehouseAliaseOfType(2,$warehouse);
			if (sizeof($results)>0)
			{
				$label['barcode']=$results[0]->getAlias();
			}

			$thenameholder=trim($warehouse->getName());
			$temp_name=$thenameholder;
			$temp_warehouse=$warehouse;


			while (($temp_warehouse->getParent() instanceof Warehouse) and
				($temp_warehouse->getWarehouseCategory()->getId()!=WarehouseCategory::ID_TECH)
			    and (strlen($temp_name)<25))
			{
				$parent=$temp_warehouse->getParent();
				$temp_name=trim($parent->getName()).".".$thenameholder;
				if (strlen($temp_name)<25)
				{
					$thenameholder=trim($temp_name);
					if (is_null($parent->getFacility())) $temp_warehouse=$parent;
					else break;
				}
			}
			$label['name']=$thenameholder;
		}
		return $label;
    }

    /**
     * Print WarehouseLabels
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function PrintWarehouseLabels($sender, $param)
    {
     	$this->setInfoMessage("");
    	$this->setErrorMessage("");
    	if($this->PrintLabelList->SelectedValue!=0)
    	{
    		$warehouseId = $this->EditWarehouseId->getValue();
			$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId);
			$printer = $this->getPrinter();
			if($printer instanceof Printer && $printer->getActive() == 0)
			{
				$this->setInfoMessage('');
	    		$this->setErrorMessage("Printer linked to your profile has been deactivated. Please select a valid printer on your preferences page and save it.");
				return false;
			}

	    	if(!$printer instanceof Printer)
	    		$this->setErrorMessage("No Default Printer has been set for you! Please change your preferences");
	    	else
	    	{
				$labels=$this->getLabels($warehouse);
				if (sizeof($labels)>0)
				{
					if (trim($labels['barcode'])>'')
					{
						try
						{
							$i=0;
							$qty=0;
							if (!(is_numeric($this->WarehouseLabelQty->Text)))
							{
								$this->setInfoMessage("");
								$this->setErrorMessage("Please enter a valid Qty to print!");
		    					return;
							}
							if ($this->WarehouseLabelQty->Text<1) $qty=1;
							else $qty=intval($this->WarehouseLabelQty->Text);

							//$this->setInfoMessage("barcode ".$labels['barcode']." name ".$labels['name']." Qty=".$qty);
							Factory::service("Barcode")->printWarehouseLabel($labels['barcode'],$labels['name'],$qty,"Zebra-Text",true);
							$this->setInfoMessage("Barcode(s) will be printed: ".$printer->getName()." @ ".$printer->getLocation()." Qty=".$qty);
							if($this->PrintLabelList->SelectedValue==1)
							{
								$this->PrintLabelList->setSelectedIndex(0);
//								$this->WarehouseLabelQty->Enabled=true;
	 							$this->WarehouseLabelQty->Text=1;
							}
						}
						catch(Exception $ex)
	    				{
	    					$this->setErrorMessage($ex->getMessage());
	    					return;
	    				}
					}
				}
		    	if($this->PrintLabelList->SelectedValue==2)
		    	{
		    		$allChildren = Factory::service('Warehouse')->getWarehouseChildrenIds($warehouse);
		    		if (sizeof($allChildren>0))
		    		{
		    			foreach ($allChildren as $children)
		    			{
		    				$childwarehouse=Factory::service("Warehouse")->getWarehouse($children);
		    				if ($childwarehouse->getParts_allow())
		    				{
		    					$labels=$this->getLabels($childwarehouse);
								if (sizeof($labels)>0)
								{
									if (trim($labels['barcode'])>'')
									{
										try
										{
											//$this->setInfoMessage("barcode ".$labels['barcode']." name ".$labels['name']);
											Factory::service("Barcode")->printWarehouseLabel($labels['barcode'],$labels['name'],1,"Zebra-Text",true);
				    						$this->setInfoMessage("Barcode(s) will be printed: ".$printer->getName()." @ ".$printer->getLocation()." Qty=1");
										}
										catch(Exception $ex)
		    							{
		    								$this->setErrorMessage($ex->getMessage());
		    								return;
		    							}
									}
								}
		    				}
		    			}
		    			$this->PrintLabelList->setSelectedIndex(0);
 						$this->WarehouseLabelQty->Text=1;
		    		}
		    		else $this->setErrorMessage("There aren't any leaves below to print.");
		    	}
	    	}
    	}
    }

    /**
     * Return true or false depending on whether the user can add/edit warehouses below this location
     * @param mixed $warehouseId
     * @return boolean
     */
    public function canAddEditThisOrBelowThisWarehouse($warehouseIds)
    {
		//sys admin can do anything!
    	if (UserAccountService::isSystemAdmin())
    	{
    		return true;
    	}

    	//must have a Admin Warehouse filter to pass go
    	$editWarehouseFilterValue = Factory::service("UserAccountFilter")->getFilterValue(Core::getUser(), "AdminWarehouse", Core::getRole());
    	if ($editWarehouseFilterValue === null)
    	{
    		return false;
    	}

    	//must have a valid entry in there at least
    	$canEditWarehouseIds = explode(",", trim($editWarehouseFilterValue));
    	if (count($canEditWarehouseIds) == 0)
    	{
    		return false;
    	}

    	$positions = array();
    	foreach ($canEditWarehouseIds as $canEditWarehouseId)
    	{
    		$warehouse = Factory::service("Warehouse")->getWarehouse($canEditWarehouseId);
    		if (!$warehouse instanceof Warehouse)
    		{
    			continue;
    		}
    		$positions[] = $warehouse->getPosition();
    	}

    	if (count($positions) == 0)
    	{
    		return false;
    	}

    	//check for matching active too
    	$activeSql = '';
    	$active = trim($this->SearchActive->getSelectedValue());
    	if ($active != "")
    	{
	    	$activeSql = "w.active=$active AND";
    	}

		if (is_array($warehouseIds))
		{
	    	$sql = "SELECT w.id FROM warehouse w WHERE $activeSql w.id IN (" . implode(',', $warehouseIds) . ") AND (w.position LIKE '" . implode("%' OR w.position LIKE '", $positions) . "%')";
		}
		else
		{
			$warehouseId = $warehouseIds;
	    	$sql = "SELECT w.id FROM warehouse w WHERE $activeSql (w.id=" . $warehouseId . " OR w.parentid=" . $warehouseId . ") AND (w.position LIKE '" . implode("%' OR w.position LIKE '", $positions) . "%')";
		}
    	$result = Dao::getResultsNative($sql);

    	$whIds = array();
    	foreach ($result as $r)
    	{
    		$whIds[] = $r[0];
    	}

    	if (!is_array($warehouseIds) && in_array($warehouseId, $whIds))		//this means we can edit all below our current location
    	{
    		return true;
    	}
    	return $whIds;														//the individual warehouse ids below the current node we can edit (empty if no editing)
    }

    /**
     * Company Selected
     *
     * @param unknown_type $id
     */
    public function companySelected($id)
    {
    	$this->Page->hiddenCompanyId->Value = '';
		if ($id != null)
		{
	    	$this->Page->hiddenCompanyId->Value = $id;
			$this->Page->removeCompanyLinkButton->Style = 'display:block;';
	    	$this->Page->companyLink->NavigateUrl = '/company/' . $id;
	    	$this->Page->companyLink->Style = 'display:block;';

			$company = Factory::service("Company")->getCompany($id);
			if ($company instanceof Company)
			{
				$address = $company->getAddress();
				$this->useAddressBelow->Enabled = false;

				$this->companyAddress->AddressName->Text = $address->getAddressName();
				$this->companyAddress->Line1->Text = $address->getLine1();
				$this->companyAddress->Line2->Text = $address->getLine2();
				$this->companyAddress->Suburb->Text = $address->getSuburb();
				$this->companyAddress->Postcode->Text = $address->getPostcode();

				$country = $address->getCountry();
				if ($country instanceof Country)
				{
					$state = $address->getState();
					$this->companyAddress->bindCountryDataList($country->getId());
			        $this->companyAddress->bindStateDataList($state->getId(), $country->getId());
					$this->companyAddress->bindTimeZoneDataList($country, $state, $address->getSuburb());

					if ($this->companyAddress->TimeZone->SelectedIndex > -1)
					{
				       	$defaultTimeZone = $this->companyAddress->TimeZone->Items[$this->companyAddress->TimeZone->SelectedIndex]->Value;
						$addressTimeZone = $address->getTimeZone();

						if($addressTimeZone != $defaultTimeZone)
						{
							for($i=0; $i<count($this->companyAddress->TimeZone->DataSource); $i++)
							{
								if($this->companyAddress->TimeZone->Items[$i]->Value == $addressTimeZone)
								{
									$this->companyAddress->TimeZone->SelectedIndex=$i;
								}
							}
						}
					}
				}
				else
				{
					$this->companyAddress->bindCountryDataList();
			        $this->companyAddress->bindStateDataList();
					$this->companyAddress->bindTimeZoneDataList();
				}
			}
		}
		$this->jsLbl->Text = '<script type="text/javascript">toggleViewWrapper();</script>';
    }

    /**
     * Company Suggest
     *
     * @param unknown_type $text
     * @return unknown
     */
    public function companySuggest($text)
    {
    	if ($this->Page->hiddenCompanyId->Value != null)
    		$this->jsLbl->Text = '<script type="text/javascript">toggleViewWrapper();</script>';

    	$this->useAddressBelow->Enabled = true;
    	$this->Page->hiddenCompanyId->Value = null;
    	$this->Page->companyLink->Style = 'display:none;';
    	$this->Page->removeCompanyLinkButton->Style = 'display:none;';
    	$result = Factory::service("Company")->search($text);
    	return $result;
    }

    /**
     * Remove Company Link
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function removeCompanyLink($sender, $param)
    {
    	if ($this->Page->hiddenCompanyId->Value != null)
    	{
	    	$this->Page->hiddenCompanyId->Value = '';
	    	$this->useAddressBelow->Enabled = true;
	    	$this->Page->companyLink->Style = 'display:none;';
	    	$this->Page->removeCompanyLinkButton->Style = 'display:none;';
	    	$this->Page->linkedCompany->Text = '';
    		$this->jsLbl->Text = '<script type="text/javascript">toggleViewWrapper();</script>';
    	}
    }

    /**
     * Parts Status Change
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function partsStatusChange($sender, $param)
    {
    	$statusId = trim($sender->getSelectedValue());
    	$this->partsUpdateCount->Value = 0;
    	$this->partsSatusLbl->Visible = true;

    	if ($statusId == '') //inherit status
    	{
    		$this->partsSatusLbl->Visible = false;
	    	$statusId = explode('_', $this->inheritStatus->Value);
	    	$statusId = $statusId[0];
    	}

    	if ($statusId != '') //we have either a valid status or, the inherited status
    	{
    		$whId = trim($this->EditWarehouseId->Value);
    		if ($whId != '')
    		{
		    	$countPis = $this->updatePartInstanceStatuses($whId, $statusId, false); //get the part count for the number of parts that are to be changed
		    	$this->partsUpdateCount->Value = $countPis;
    		}
    	}
    }
}

?>