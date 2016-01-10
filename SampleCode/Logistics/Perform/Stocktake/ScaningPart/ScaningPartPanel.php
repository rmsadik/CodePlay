<?php
/**
 * ScaningPartPanel provides a searching function for any part instance: ,
 * if there are multiple instances found: then populate the selection list
 * otherwise call the postSelectFunction, which is assigned externally.
 *
 * @package	Hydra-Web
 * @subpackage	Customised-Control
 * @filesource
 * @version	1.0
 * @author  Lin He <lhe@bytecraft.com.au>
 *
 */
class ScaningPartPanel extends TTemplateControl
{
	/**
	 * @var groupingText
	 */
	public $groupingText="";

	/**
	 * @var cssStyle
	 */
	public $cssStyle="";

	/**
	 * @var searchBtnText
	 */
	public $searchBtnText="search";

	/**
	 * @var externalpartfunc
	 */
	public $externalpartfunc="";

	/**
	 * @var statusArray
	 */
	private $statusArray;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
	{
		if(!$this->getPage()->getIsPostBack())
		{
			$this->serialNo->focus();
			$this->generateSoundJavascript();
		}
		$this->partTypeAlias->Text = '';
		$this->serialNo->Text = '';
		$this->ajaxLabel->Text = "";
	}

	/**
	 * getter groupingText
	 *
	 * @return groupingText
	 */
	public function getGroupingText()
	{
		return $this->groupingText;
	}

	/**
	 * setter groupingText
	 *
	 * @var groupingText
	 */
	public function setGroupingText($groupingText)
	{
		$this->groupingText = $groupingText;
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
	 * getter searchBtnText
	 *
	 * @return searchBtnText
	 */
	public function getSearchBtnText()
	{
		return $this->searchBtnText;
	}

	/**
	 * setter searchBtnText
	 *
	 * @var searchBtnText
	 */
	public function setSearchBtnText($searchBtnText)
	{
		$this->searchBtnText = $searchBtnText;
	}

	/**
	 * getter externalpartfunc
	 *
	 * @return externalpartfunc
	 */
	public function getExternalpartfunc()
	{
		return $this->externalpartfunc;
	}

	/**
	 * setter externalpartfunc
	 *
	 * @var externalpartfunc
	 */
	public function setExternalpartfunc($externalpartfunc)
	{
		$this->externalpartfunc = $externalpartfunc;
	}

	/**
	 * Search part
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function searchPart($sender, $param)
	{
		$this->ajaxLabel->Text="";

		$cappedData = json_decode(trim($this->cappedData->Value));
		$index = trim($this->searchPartBtn_tableIndex->Value);
		if(!isset($cappedData->$index)){
			$cappedData->$index->{"partDetails"}="'$index' not set!";
			$this->setErrorSound();
		}
		list($serialNo,$partTypeAlias) = explode("_",$index);
		$serialNo = trim(strtoupper($serialNo));
		$partTypeAlias = trim($partTypeAlias);
		if($serialNo!="" && preg_match(BarcodeService::getBarcodeRegex(BarcodeService::BARCODE_REGEX_CHK_PART_INSTANCE), $serialNo) === 1)
		{
			try {
				BarcodeService::validateBarcode($serialNo, BarcodeService::BARCODE_REGEX_CHK_PART_INSTANCE);
			} catch (Exception $ex) {
				$cappedData->$index->{"partDetails"}="Invalid Barcode!";
				$this->cappedData->Value = json_encode($cappedData);
				$this->setErrorSound();
				return;
			}

			$sql="select distinct pi.id `partInstanceId`, pi.quantity, pi.partInstanceStatusId, pt.id `partTypeId`, concat(pta.alias,' - ',pt.name) `partName`, pt.serialised,
					pi.active as partInstanceActive, pt.active as partTypeActive
				from partinstance pi
				inner join parttype pt on (pt.id = pi.partTypeId)
				inner join partinstancealias pia on (pia.partInstanceId = pi.id and pia.active = 1 and pia.alias = '".addslashes($serialNo)."' and pia.partInstanceAliasTypeId in (1,9))
				left join parttypealias pta on (pta.partTypeId = pt.id and pta.active = 1 and pta.partTypeAliasTypeId = 1)
				limit 1";
			$result = Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC);

			if(count($result)==0){
				$cappedData->$index->{"partDetails"} = "Not Found!";
				$this->setErrorSound();
			}
			else
			{
				$row = $result[0];
				if($row["partInstanceActive"] && $row["partTypeActive"])
				{
					$addPartToList = true;
					$extraMsg = '';
					if ($row["serialised"])
					{
						//check if the scanned part has open tasks
						$openTasksByPiId = Factory::service("FieldTask")->getOpenTaskIdsForPIIds(array($row["partInstanceId"]));
						if (!empty($openTasksByPiId))
						{
							//check here if the part is on the shelf, then status can't be changed
							$shelfPiIds = unserialize($this->Page->shelfPartInstanceIds->Value);
							if (in_array($row["partInstanceId"], $shelfPiIds))
							{
								$blockStatusChange = true;

								$msg = 'The part has an open workshop task';
								if (count($openTasksByPiId[$row["partInstanceId"]]) > 1)
									$msg = 'The part has open workshop tasks ';

								$extraMsg = '<span style="font-weight:bold; color:red;">* ' . $msg . ' [' . implode(' | ', $this->Page->getTaskLinks($openTasksByPiId[$row["partInstanceId"]])) . '], unable to change the status.</span>';
							}
							else	//not on the shelf
							{
								$extraMsg = 'Invalid Part Instance.';
								$addPartToList = false;

								$pi = Factory::service("PartInstance")->get($row["partInstanceId"]);
								if ($pi instanceof PartInstance)
								{
									$msg = 'The part has an open workshop task';
									if (count($openTasksByPiId[$row["partInstanceId"]]) > 1)
										$msg = 'The part has open workshop tasks ';

									$extraMsg = '<span style="font-weight:bold; color:red;">* ' . $msg . ' [' . implode(' | ', $this->Page->getTaskLinks($openTasksByPiId[$row["partInstanceId"]])) . '] and is located here [' . Factory::service("Warehouse")->getWarehouseBreadcrumbs($pi->getWarehouse(), false, '/') . ']</span>';
								}
							}
						}
					}

					if ($addPartToList)
					{
						$cappedData->$index->{"partInstanceId"} = $row["partInstanceId"];
						$cappedData->$index->{"partTypeId"} = $row["partTypeId"];
						$cappedData->$index->{"partInstanceStatusId"} = $row["partInstanceStatusId"];
						$cappedData->$index->{"quantity"} = $row["quantity"];
						$cappedData->$index->{"serialised"} = $row["serialised"];
						$cappedData->$index->{"partDetails"} = $this->getPartDetailsTable($result, $index, $extraMsg);
						$this->setNotErrorSound();
					}
					else
					{
						$cappedData->$index->{"partDetails"} = $extraMsg;
						$this->setErrorSound();
					}
				}
				else
				{
					$error = "";
					$errArr = array();

					if ($row["partInstanceActive"] == 0)
						$errArr[] = 'Part Instance has been deactivated.';

					if ($row["partTypeActive"] == 0)
						$errArr[] = 'Part Type has been deactivated.';

					if (!empty($errArr))
					{
						$cappedData->$index->{"partDetails"} = implode('<br />', $errArr) . "<br />Please contact your state logistics for instruction.";
						$this->setErrorSound();
					}
				}
			}
		}
		else if($partTypeAlias!="" || preg_match(BarcodeService::getBarcodeRegex(BarcodeService::BARCODE_REGEX_CHK_PART_TYPE), $serialNo))
		{
			if(trim($partTypeAlias)=="")
				$partTypeAlias = $serialNo;
			$sql="select distinct '' `partInstanceId`, 0 `quantity`, '' `partInstanceStatusId`, pt.id `partTypeId`,concat(pta_pc.alias,' - ',pt.name) `partName`, pt.serialised, pt.active as partTypeActive
				from parttype pt
				inner join parttypealias pta on (pta.partTypeId = pt.id and pta.active = 1 and pta.alias = '".addslashes($partTypeAlias)."')
				left join parttypealias pta_pc on (pta_pc.partTypeId = pt.id and pta_pc.active = 1 and pta_pc.partTypeAliasTypeId = 1)
				where  pt.serialised=0
				group by pt.id";
			$result = Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC);
			if(count($result)==0){
				$cappedData->$index->{"partDetails"} = "Not Found! or the part is serialised and please use the serial number instead";
				$this->setErrorSound();
			}
			else
			{
				if($result[0]["partTypeActive"])
				{
					$cappedData->$index->{"partTypeId"} = $result[0]["partTypeId"];
					$cappedData->$index->{"partDetails"} = $this->getPartDetailsTable($result,$index);
					$this->ajaxLabel->Text="<script type='text/javascript'>if($('{$index}_qty')){ $('{$index}_qty').focus(); $('{$index}_qty').select();toggleFields(true);}</script>";
					$this->setQuantitySound();
				}
				else
				{
					$cappedData->$index->{"partDetails"} = "Part Type has been deactivated! Please contact your state logistics for instruction.";
					$this->setErrorSound();
				}
			}
		}
		else
		{
			$cappedData->$index->{"partDetails"} = "Nothing found.";
			$this->setErrorSound();
		}
		$this->errorMsg->Visible = true;
		$this->cappedData->Value = json_encode($cappedData);
	}

	/**
	 * Submit Data
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function submitData($sender,$param)
	{
		$this->Page->{$this->externalpartfunc}(trim($this->cappedData->Value));
	}

	/**
	 * Get Part Details Table
	 *
	 * @param unknown_type $result
	 * @param unknown_type $index
	 * @return unknown
	 */
	private function getPartDetailsTable($result, $index, $extraMsg = '')
	{
		$row = $result[0];

		$blockStatusChange = false;
		if ($extraMsg !== '')
			$blockStatusChange = true;

		$table = "<table width='100%' cellspacing='0'>";
			$table .= "<tr>";
				$table .= "<td width='20px'><b>Qty:</b></td>";
				$table .= "<td width='50px'><input type='text' style='width:80%;' onkeydown=\"return doEnterBehaviorForQty_".$this->getClientId()."(event,'$index');\" onchange=\"changeQty_".$this->getClientID()."('$index');\" ".( $row["serialised"]? "disabled='true'" : "" )." id='{$index}_qty' value='{$row["quantity"]}' /></td>";
				$table .= "<td width='20px'><b>Status:</b></td>";
				$table .= "<td width='50px' style='padding-right:5px;'>".$this->getStatusList($row["partInstanceStatusId"], $index, $row["partTypeId"], $blockStatusChange)."</td>";
				$table .= "<td>".$this->getPartTypeList($result,$index)."</td>";
			$table .= "</tr>";
		if ($extraMsg !== '')
		{
			$table .= "<tr>";
				$table .= "<td colspan='5'>" . $extraMsg . "</td>";
			$table .= "</tr>";
		}
		$table .= "</table>";
		return $table;
	}

	/**
	 * Get PartType List
	 *
	 * @param unknown_type $result
	 * @param unknown_type $index
	 * @return unknown
	 */
	private function getPartTypeList($result,$index)
	{
		$list = "<select id='{$index}_partTypeId' style='font-size:10px;' onchange=\"changeQty_".$this->getClientID()."('$index');\" >";
		$rowNo=0;
		foreach($result as $item)
		{
			$list .= "<option value='{$item["partTypeId"]}' name='{$index}_partTypeId_option'".($rowNo==0 ? " selected" : "").">{$item["partName"]}</option>";
			$rowNo++;
		}
		$list .= "</select>";
		return $list;
	}

	/**
	 * Get Status List
	 *
	 * @param unknown_type $selectedId
	 * @param unknown_type $index
	 * @param unknown_type $partTypeId
	 * @return unknown
	 */
	private function getStatusList($selectedId="",$index, $partTypeId, $blockStatusChange = false)
	{
		$partType = Factory::service("PartType")->get($partTypeId);
		$contractsForPartType = array();
		if($partType instanceOf PartType)
		{
			$contractsForPartType = Factory::service("PartType")->getContractsForPartType($partType);
		}

		//logic function to return part instance status list
		$this->statusArray = DropDownLogic::getPartInstanceStatusList(array(), $contractsForPartType);


		$options = '';
		foreach($this->statusArray as $item)
		{
			$id = $item->getId();
			$options .= "<option value='$id' name='{$index}_statusId_option'".($id==$selectedId ? " selected" : "").">".$item->getName()."</option>";
		}

		//check if we are to disable the status list
		$pisId = $this->Page->shelfPartInstanceStatusId->Value;
		$disabled = '';
		if ($pisId != '' || $blockStatusChange)
			$disabled = 'disabled';

		//check if we have a different status setting, highlight it
		$color = '';
		if ($pisId != '' && $selectedId != $pisId)
			$color = 'color:red';

		$list = "<select id='{$index}_statusId' $disabled style='$color;font-size:10px;' onchange=\"changeQty_".$this->getClientID()."('$index');\" >";
		$list .= $options;
		$list .= "</select>";
		return $list;
	}

	/**
	 * Set Not Error Sound
	 *
	 */
	public function setNotErrorSound()
	{
		$this->ajaxLabel->Text = "<script type='text/javascript'>PlayInfoSound();</script>";
	}

	/**
	 * Set Quantity Sound
	 *
	 */
	public function setQuantitySound()
	{
		$this->ajaxLabel->Text = "<script type='text/javascript'>PlayQuantitySound();</script>";
	}

	/**
	 * Set Error Sound
	 *
	 */
	public function setErrorSound()
	{
		$this->ajaxLabel->Text = "<script type='text/javascript'>PlayErrorSound();</script>";
	}

	function generateSoundJavascript()
	{
		//if IE
		if (preg_match('/(?i)msie [1-8]/',$this->getRequest()->getUserAgent()) || preg_match('/(?i)msie/',$this->getRequest()->getUserAgent()))
		{
			//less then IE9 use bgsound tag
			$errorSound = "document.getElementById('soundwav').src = '/themes/images/warning.wav';";
			$quantitySound = "document.getElementById('soundwav').src = '/themes/images/quantity.wav';";
			$infoSound = "document.getElementById('soundwav').src = '/themes/images/info.wav';";
			$extra = '';
		}
		else
		{
			//Other use HTML5 audio tag with wav
			$errorSound = "document.getElementById('soundogg').src = '/themes/images/warning.wav';";
			$quantitySound = "document.getElementById('soundogg').src = '/themes/images/quantity.wav';";
			$infoSound = "document.getElementById('soundogg').src = '/themes/images/info.wav';";
			$extra = "document.getElementById('soundogg').play();";
		}

		$script = "<script type='text/javascript'>
					function PlayErrorSound() {" . $errorSound . $extra . "}
					function PlayQuantitySound() {" . $quantitySound . $extra . "}
					function PlayInfoSound() {" . $infoSound . $extra . "}
					</script>";

		$this->ajaxSound->Text = $script;
	}
}

?>