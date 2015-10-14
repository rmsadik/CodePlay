<?php
class CRUDPage extends HydraPage
{
	public $menuContext;
	protected $openFirst = false;
	protected $allowOutPutToExcel =false;
	protected $focusOnSearch = true;
	protected $dontHardcodeService;


    protected function createNewEntity()
    {
    	return null;
    }

    protected function lookupEntity($id)
    {
    	return null;
    }

    protected function getFocusEntity($id,$type="")
    {
    	return null;
    }

    protected function setEntity(&$object,$params,&$focusObject = null)
    {

    }

    protected function saveEntity(&$object)
    {

    }

    protected function resetFields($params)
    {

    }

    protected function populateAdd()
    {

    }

    protected function populateEdit($editItem)
    {

    }

    protected function howBigWasThatQuery()
    {
    	return Dao::getTotalRows();
    }

    /**
     * This is the function that will be called, when we load the data list, without any searching criteria
     * @param mix $focusObject - the object return from CRUDPage::getFocusEntity
     * @param int $pageNumber - the page number for the returned result set
     * @param int $pageSize - the page size for the returned result set
     *
     * @return Array
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	return null;
    }

    /**
     * get data array when we supply some searching criterias
     * @param mix $searchString - the searching criterias
     * @param mix $focusObject - the object return from CRUDPage::getFocusEntity
     * @param int $pageNumber - the page number for the returned result set
     * @param int $pageSize - the page size for the returned result set
     *
     * @return Array
     */
    protected function searchEntity($searchString,&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	return null;
    }

	public function __construct()
	{
		parent::__construct();
		$this->dontHardcodeService = Factory::service("DontHardcode");
	}

	public function onLoad($param)
    {
       	parent::onLoad($param);

       	// This is for output to excel.....
       	if($this->allowOutPutToExcel == true)
       		$this->MainContent->findControl("ScriptGenerator")->Text = "";

        $this->setInfoMessage("");
        $this->setErrorMessage("");
		$this->PaginationPanel->Visible = false;

		if(isset($this->Request['id']))
		{
			$this->focusObject->Value = $this->Request['id'];
		}
		if(isset($this->Request['searchby']))
		{
			$this->focusObjectArgument->Value = $this->Request['searchby'];
		}

		$entity = null;
		$argument = $this->focusObjectArgument->Value;
		if ($this->focusObject->Value != '') //to avoid unnecessary query to DB
		{
			$entity = $this->getFocusEntity($this->focusObject->Value,$argument);
		}

		$this->createMenu($entity,$argument);

		try
		{
			if($this->focusOnSearch)
				$this->SearchText->focus();
		}
		catch (Exception $e)
		{

		}
    }

    public function add($sender, $param)
    {
    	$this->AddPanel->Visible = true;
    	$this->DataList->EditItemIndex = -1;
    	$this->dataLoad();

       	if($this->AddPanel->Visible == true)
    	{
    		$params = $this;
    	}
    	else
    	{
    		$params = $param->Item;
    	}

    	$this->resetFields($params);
    	$this->populateAdd();
    }

    public function edit($sender,$param)
    {
	    if($param != null)
			$itemIndex = $param->Item->ItemIndex;
		else
			$itemIndex = 0;

		$this->AddPanel->Visible = false;
		$this->DataList->SelectedItemIndex = -1;
		$this->DataList->EditItemIndex = $itemIndex;
		$this->dataLoad();

		$this->populateEdit($this->DataList->getEditItem());
    }

    protected function toPerformSearch()
    {
    	return $this->SearchString->Value == "";
    }

    public function dataLoad($pageNumber=null,$pageSize=null)
    {
    	if($pageNumber == null)
    		$pageNumber = $this->DataList->CurrentPageIndex + 1;

    	if($pageSize == null)
    		$pageSize = $this->DataList->pageSize;

       	$focusObject = $this->focusObject->Value;
       	$focusObjectArgument = $this->focusObjectArgument->Value;
     	if($focusObject == "")
     		$focusObject = null;
     	else
     		$focusObject = $this->getFocusEntity($focusObject,$focusObjectArgument);

     	if ($this->toPerformSearch())
     		$data = $this->getAllOfEntity($focusObject,$pageNumber,$pageSize);
     	else
     		$data = $this->searchEntity($this->SearchString->Value,$focusObject,$pageNumber,$pageSize);

     	$size = sizeof($data);

     	$this->DataList->DataSource = $data;
    	$totalSize = $this->howBigWasThatQuery();

     	$this->DataList->VirtualItemCount = $totalSize;



     	if($this->openFirst && $size == 1)
     	{
			$this->DataList->EditItemIndex = 0;
     	}

     	$this->DataList->dataBind();

        if($this->openFirst && $size == 1)
     	{
			$this->populateEdit($this->DataList->getEditItem());
     	}

    	if($this->DataList->getPageCount() > 1)
	    	$this->PaginationPanel->Visible = true;



     	return $data;

    }

    protected function postDataLoad()
    {

    }

    public function save($sender,$param)
    {
    	if($this->AddPanel->Visible == true)
    	{
    		$entity = $this->createNewEntity();
    		$params = $this;
    	}
    	else
    	{
    		$params = $param->Item;
			$entity = $this->lookupEntity($this->DataList->DataKeys[$params->ItemIndex]);
    	}

       	$focusObject = $this->focusObject->Value;
       	$focusObjectArgument = $this->focusObjectArgument->Value;
     	if($focusObject == "")
     		$focusObject = null;
     	else
     		$focusObject = $this->getFocusEntity($focusObject,$focusObjectArgument);


    	try{
    		$this->setEntity($entity,$params,$focusObject);
	    	$this->saveEntity($entity);
    	}
    	catch(Exception $e){
    		if($this->AddPanel->Visible == false)
    		{
				$this->edit($sender,$param);
    		}
    		$this->setErrorMessage($e->getMessage());
    		$this->dataLoad();
    		return;
    	}

    	if($this->AddPanel->Visible == true)
	        $this->AddPanel->Visible = false;
    	else
	        $this->DataList->EditItemIndex = -1;

    	$this->resetFields($params);

		$this->dataLoad();
    }

    public function cancel($sender,$param)
    {
		$this->AddPanel->Visible = false;
		$this->DataList->EditItemIndex = -1;
		$this->dataLoad();
    }

    public function search($sender,$param)
    {
     	$searchQueryString = $this->SearchText->Text;
		$this->SearchString->Value = $searchQueryString;
		$this->DataList->EditItemIndex = -1;
		$this->DataList->CurrentPageIndex  = 0;
		$this->dataLoad();
    }

    public function pageChanged($sender, $param)
    {
    	$this->AddPanel->Visible = false;
    	$this->DataList->EditItemIndex = -1;
      	$this->DataList->CurrentPageIndex = $param->NewPageIndex;
      	$this->dataLoad();
    }

    public function createMenu(&$focusObject=null,$focusArgument="")
    {

    }

    protected function entitiesToArray(array $entities)
    {
		$selected = array();
		foreach($entities as $entity)
			$selected[] = $entity->getId();

		return $selected;
    }

    // I AM A WINNER !!!!
    protected function saveManyToMany(&$object,$controlIds,$entityArray,$add,$remove,$service,$get)
    {

	    foreach($entityArray as $entity)
		{

			$result = array_search($entity->getId(),$controlIds);
			if($result === false)
			{
				$object->$remove($entity);
			} else {
				unset($controlIds[$result]);
			}
		}

		foreach($controlIds as $controlId)
		{
			$temp = $service->$get($controlId);
			$object->$add($temp);
		}
    }

    /**
     * Toggle the Active flag in DataList
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	protected function toggleActive($sender,$param)
	{
		$entity = $this->lookupEntity($sender->Parent->Parent->DataKeys[$sender->Parent->ItemIndex]);
    	$entity->setActive($sender->Parent->Active->Checked);
		$this->saveEntity($entity);

		$this->dataLoad();
	}

	/**
	 * Bind data to a DropDownList
	 *
	 * @param TDropDownList $listToBind
	 * @param array[] HydraEntity $dataSource
	 * @param HydraEntity $selectedItem
	 * @param bool $enable
	 */
	protected function bindDropDownList(&$listToBind, $dataSource, $selectedItem = null, $enable = true)
	{
		$listToBind->DataSource = $dataSource;
        $listToBind->dataBind();

        if($selectedItem!=null)
        {
        	$listToBind->setSelectedValue($selectedItem->getId());
        }
        $listToBind->Enabled=$enable;
	}

	public function editItemMenuChanged($sender, $param)
	{
		$href = $sender->getSelectedValue();
		if(strpos($href,'_') !== 0)
			$this->Response->redirect($href.$this->DataList->DataKeys[$sender->Parent->ItemIndex]);
		else
			$this->Response->redirect(substr($href,1));
	}

    public function getStyle($index)
    {
    	if($index % 2 == 0)
    		return 'DataListItem';
    	else
    		return 'DataListAlterItem';
    }


	public function onInit($param)
	{
		parent::onInit($param);
		if($this->allowOutPutToExcel == true)
		{
			$ttable = new TTable();
			$ttable->setID('OutputToExcelTable');
			$ttable->setWidth("100%");

			$trow = new TTableRow();
			$trow->setID('OutputToExcelRow');
			$trow->setHorizontalAlign("Right");

			$tcell = new TTableCell();
			$tcell->setID('OutputToExcelCell');

			$outPutToExcelButton = new TButton();
			$outPutToExcelButton->setID("OutputToExcelButton");
			$outPutToExcelButton->setText("To Excel");
			$outPutToExcelButton->setStyle("float:right;");
			$outPutToExcelButton->setTabIndex(10000);

			$this->ListingPanel->getControls()->insertAt(1,$ttable);
			$this->ListingPanel->findControl('OutputToExcelTable')->Controls[] = $trow;
			$this->ListingPanel->findControl('OutputToExcelTable')->findControl('OutputToExcelRow')->Controls[] = $tcell;
			$this->ListingPanel->findControl('OutputToExcelTable')->findControl('OutputToExcelRow')->findControl('OutputToExcelCell')->Controls[] = $outPutToExcelButton;
			$this->ListingPanel->findControl('OutputToExcelTable')->findControl('OutputToExcelRow')->findControl('OutputToExcelCell')->findControl('OutputToExcelButton')->onClick = "Page.outputToExcel";

			// This is required for OutputToExcel Functionality...
			$scriptGeneratorActiveLabel = new TActiveLabel();
			$scriptGeneratorActiveLabel->setID("ScriptGenerator");
			$this->ListingPanel->getControls()->insertAt(0,$scriptGeneratorActiveLabel);
		}
	}

    /**
     * Outputs the List to Excel Format...
     *
     * @param String $workBookName
     * @param String $workSheetName
     * @param String $titleHeader
     * @param Array[] $columnHeaderArray
     * @param Array[] $columnDataArray[$cellData, $cellDataType="", $cellLength="", $formula="",$rowHeight=""]
     */
	public function toExcel($workBookName, $workSheetName="", $titleHeader="", $columnHeaderArray, $columnDataArray)
    {

		/*
     	 *
     	Changed this page to just write data to the page and change 'headers'
     	Like V2. EM - 1 Dec 2009
    	*/

    	$standardFileName="";
    	if($this->allowOutPutToExcel != true)
	    {
	    	print "<p>No data to be reported on.</p>";
	    	return;
	    }

     	$workBookName = str_replace(" ","_",$workBookName);
    	$workSheetName = str_replace(" ","_",$workSheetName);

    	if($workSheetName == "")
    		$workSheetName = $workBookName;

    	if($titleHeader == "")
    		$titleHeader = $workSheetName;

    	// This is because excel freeks out if there are more than certain chars in worksheet/workbookname...
    	if(strlen($workBookName) >= 100)
    		$workBookName = substr($workBookName,0,99);

    	if(strlen($workSheetName) >= 30)
    		$workSheetName = substr($workSheetName,0,29);

    	$standardFileName .= $workBookName.".xls";
    	header("Content-type: application/ms-excel");
    	header("Content-disposition: attachment; filename=".$standardFileName);

    	print"<table border=\"1\">";

    	// added 5/11/2010 - print out a header on top
    	print"<tr>";
		print"<th colspan='".count($columnHeaderArray)."' style='background-color:#99CCFF;'>".$titleHeader."</th>";
    	print"</tr>";
    	print"<tr>";
		print"<td colspan='".count($columnHeaderArray)."'>&nbsp;</td>";
    	print"</tr>";

    	print"<TR>";
		foreach($columnHeaderArray as $header)
			print"<th>".$header."</th>";						//0
    	print"</tr>";
		try
		{
			foreach ($columnDataArray as $row)
			{
				print"\n<tr>";
				$i=0;
				$data="";
				foreach ($row as $data)
				{
					//Debug::inspect( $data) ."<br />";

					$cellData="";
					if(is_array($data))
					{
						if($data[0]>"")
							$cellData = $data[0];
						else
							$cellData = "";
						print"\n\t<td>". $this->cleanCellData($cellData)."</td>";
					}
					else
					{
						if($data>"")
							$cellData = $data;
						else
							$cellData = "";
						print"\n\t<td>". $this->cleanCellData($cellData)."</td>";

					}
					$i++;
				}
				print"\n</tr>\n";
			}
			print"</table>";
			die();

		}
		catch (Exception $e)
		{
			print $e->getMessage();
		}
	  	print"</table>";
  		die();



/*
 * 		Pete's method
 *
    	if($this->allowOutPutToExcel == true)
    	{
	    	// Setting Up Data If anything is null........
	    	$workBookName = str_replace(" ","_",$workBookName);
	    	$workSheetName = str_replace(" ","_",$workSheetName);

	    	if($workSheetName == "")
	    		$workSheetName = $workBookName;

	    	if($titleHeader == "")
	    		$titleHeader = $workSheetName;

	    	// This is because excel freeks out if there are more than certain chars in worksheet/workbookname...
	    	if(strlen($workBookName) >= 100)
	    		$workBookName = substr($workBookName,0,99);

    		if(strlen($workSheetName) >= 30)
	    		$workSheetName = substr($workSheetName,0,29);

	    	$titleHeaderColumns  = count($columnHeaderArray)-1;

	    	// Setting Up worksheet Details & Style.....
	    	$workBook = new HYExcelWorkBook();
	    	$workSheet = new HYExcelWorkSheet($workSheetName);

	    	$titleHeaderStyle = new HYExcelStyle("title");
			$titleHeaderStyle->setFont("Swiss","#00000",true,true);
			$titleHeaderStyle->setAlignment("Center","Bottom",false);

			$columnHeaderStyle = new HYExcelStyle("header");
			$columnHeaderStyle->setFont("Swiss","#ff0000",true,true);
			$columnHeaderStyle->setInterior("#cccccc");

			for ($i=0; $i<=$titleHeaderColumns; $i++)
				$setColumnWidthArray[$i] = 150;

	    	$blankRow = new HYExcelRow();
			$blankRow->addCell(new HYExcelCell("","String"));

			$titleRow = new HYExcelRow();
			$titleRow->addCell(new HYExcelCell($titleHeader,"String",$titleHeaderStyle,$titleHeaderColumns));

			// Adding Rows to the worksheet.....
			$workSheet->addRow($blankRow);
			$workSheet->addRow($titleRow);
			$workSheet->addRow($blankRow);

			$headerRow = new HYExcelRow();
			foreach($columnHeaderArray as $header)
				$headerRow->addCell(new HYExcelCell($header,"String",$columnHeaderStyle));
			$workSheet->addRow($headerRow);

			foreach ($columnDataArray as $row)
			{
				$i=0;
				$dataRow = new HYExcelRow();
				foreach ($row as $data)
				{
					// If $data is not array that means its just a string format cell data with default cellwidth and rowheight and no formulas.....
					if(!is_array($data))
						$dataRow->addCell(new HYExcelCell($data));
					// If $data is an array that means its can be either string format data or formula and may also have custome cellwidth or rowheigth.....
					// Format array($cellData, $cellDataType="", $cellLength="", $formula="", $rowHeight="")
					else
					{
						if(array_key_exists(0,$data))
							$cellData = $data[0];
						else
							$cellData = "";

						if(array_key_exists(1,$data))
							$cellDataType = $data[1];
						else
							$cellDataType = "";

						if(array_key_exists(2,$data))
							$cellLength = $data[2]*5;
						else
							$cellLength = "";

						if(array_key_exists(3,$data))
							$cellFormula = $data[3];
						else
							$cellFormula = "";

						if(array_key_exists(4,$data))
							$rowHeight = $data[4]*17;
						else
							$rowHeight = "";

						if($setColumnWidthArray[$i] < $cellLength)
							$setColumnWidthArray[$i] = $cellLength;

						if($rowHeight != "" && $rowHeight > 0)
							$dataRow->setHeight($rowHeight);

						$dataRow->addCell(new HYExcelCell($cellData,$cellDataType,null,0,$cellFormula));
					}

					$i++;
				}
				$workSheet->addRow($dataRow);
			}

			foreach ($setColumnWidthArray as $col => $columnWidth)
				$workSheet->setColumnWidth($col , $columnWidth);

			// Adding worksheet to workbook......
			$workBook->addWorkSheet($workSheet);

			// Opening up the workbook......
			$contentServer = new ContentServer();
			$assetId = $contentServer->registerAsset(ContentServer::TYPE_REPORT, $workBookName.".xls", $workBook->__toString());
			$this->ListingPanel->findControl("ScriptGenerator")->Text = "<script type=\"text/javascript\">window.open('/report/download/$assetId');</script>";
			$this->dataLoad();
    	}*/
    }

    /**
     * Outputs the List to CSV Format...
     *
     * @param String $workBookName
     * @param String $workSheetName
     * @param String $titleHeader
     * @param Array[] $columnHeaderArray
     * @param Array[] $columnDataArray[$cellData, $cellDataType="", $cellLength="", $formula="",$rowHeight=""]
     */
	public function toCSV($workBookName, $workSheetName="", $titleHeader="", $columnHeaderArray, $columnDataArray)
    {
    	$standardFileName="";
    	if($this->allowOutPutToExcel != true)
	    {
	    	print "<p>No data to be reported on.</p>";
	    	return;
	    }

     	$workBookName = str_replace(" ","_",$workBookName);
    	$workSheetName = str_replace(" ","_",$workSheetName);

    	if($workSheetName == "")
    		$workSheetName = $workBookName;

    	if($titleHeader == "")
    		$titleHeader = $workSheetName;

    	// This is because excel freeks out if there are more than certain chars in worksheet/workbookname...
    	if(strlen($workBookName) >= 100)
    		$workBookName = substr($workBookName,0,99);

    	if(strlen($workSheetName) >= 30)
    		$workSheetName = substr($workSheetName,0,29);

    	$standardFileName .= $workBookName.".csv";
    	header("Content-type: application/ms-excel");
    	header("Content-disposition: attachment; filename=".$standardFileName);

		foreach($columnHeaderArray as $header)
			print"".$header.",";

		print"\n";
		try
		{
			foreach ($columnDataArray as $row)
			{
				$i=0;
				$data="";
				foreach ($row as $data)
				{
					$cellData="";
					if(is_array($data))
					{
						if($data[0]>"")
							$cellData = trim($data[0]);
						else
							$cellData = " ";

						print str_replace(',', ';', $cellData).",";
					}
					else
					{
						if($data>"")
							$cellData = trim($data);
						else
							$cellData = " ";

						print str_replace(',', ';', $cellData).",";

					}
					$i++;
				}
				print"\n";
			}
			die();

		}
		catch (Exception $e)
		{
			print $e->getMessage();
		}
  		die();

    }

    protected function createUserAccountLookup($uaid)
    {
    	return "COALESCE(
    				(SELECT group_concat(concat(p.firstname, ' ', p.lastname) SEPARATOR ', ') FROM useraccount u inner join person p on p.id = u.personid where u.id = ".$uaid."),
  					(SELECT group_concat(concat(c.ident) SEPARATOR ', ') FROM credential c where c.userAccountId = ".$uaid." and authDomainId = 1),
  					'Unknown')";
    }

    private function cleanCellData($cellData)
    {
    	$cellData = str_replace("<", " < ",$cellData);
    	$cellData = str_replace(">", " > ",$cellData);

    	return $cellData;
    }


    /**
     * Get selected DataList item
     *
     * @return Entity
     */
	public function getSelectedItem()
	{
		$item = $this;

		if($this->AddPanel->Visible==false)
			$item = $this->DataList->getEditItem();

		return $item;
	}
}

?>