<?php
class HYExcelWorkSheet
{
	private $header;
	private $footer;
	private $workSheetName;
	
	public $hasVerticalMergeCells = false;
	
	private $workSheetOptions;
	private $workSheetProperties;

	/**
	 * @var array[] HYExcelRow
	 */
	private $rows = array();
	/**
	 * @var array[] HYExcelStyle
	 */
	public $styles = array();
	/**
	 * @var array[] HYExcelColumn
	 */
	public $columns = array();
	
	public function __construct($name)
	{
		$this->workSheetName = $name;
		$this->workSheetOptions = array();
		$this->workSheetProperties = array();
		
		//truncate the sheet name, MS Excel only support this number of chars for a worksheet
		$this->header ="<Worksheet ss:Name=\"".substr($name,0,30)."\">";
		$this->footer ="</Worksheet>";
	}

	public function __toString()
	{
		$workSheetOptions = "";
		if (!empty($this->workSheetOptions))
		{
			$workSheetOptions = "\n\t\t<WorksheetOptions xmlns=\"urn:schemas-microsoft-com:office:excel\">";
			$workSheetOptions .= join("\n\t\t\t\t", $this->workSheetOptions);
			$workSheetOptions .= "\n\t\t</WorksheetOptions>\n";
		}
		
		$return = "\t".$this->header."\n";
			$return .= (!empty($this->workSheetProperties) ? join("\n", $this->workSheetProperties) : ""); 
			$return .="\t\t<Table>\n";
				if(count($this->columns)>0)
				{
					foreach($this->columns as $column)
					{
						$return .=$column->__toString();
					}
				}
				if($this->hasVerticalMergeCells==true)
					$this->mergeCellsWhenToString();
		
				foreach($this->rows as $row)
				{
					$return .= $row->__toString();
				}
			$return .="\t\t</Table>";
			$return .= $workSheetOptions;
		$return .="\t".$this->footer."\n";
		return $return;
	}

	public function addRow(HYExcelRow $row)
	{
		$this->rows[] = $row;
		if($row->hasVerticalMergeCells==true)
			$this->hasVerticalMergeCells=true;
		foreach($row->styles as $style)
		{
			if($style instanceof HYExcelStyle )
			$this->setStyle($style);
		}
	}
	
	public function addAndSetRepeatedPrintRow(HYExcelRow $row)
	{
		$propertyName = "Print_Titles";
		
		$newRow = new HYExcelRow($row->getHeight());
		$styles = $row->getStyles();
		foreach ($styles as $s)
		{
			$newRow->setStyle($s);
		}
		$cells = $row->getCells();
		foreach ($cells as $c)
		{
			$c->setNamedCell($propertyName);
			$newRow->addCell($c);
		}
		$this->addRow($newRow);
		
		$this->workSheetProperties[] = "\n\t\t<Names><NamedRange ss:Name=\"".$propertyName."\" ss:RefersTo=\"='".$this->workSheetName."'!R".count($this->rows)."\"/></Names>";
	}

	public function setColumnWidth($columnNo,$width="")
	{
		if(isset($this->columns[$columnNo]))
		{
			$this->columns[$columnNo]->setWidth($width);
		}
		else
		{
			for($i=0;$i<$columnNo;$i++)
			{
				if(!isset($this->columns[$i]))
					$this->columns[$i]=new HYExcelColumn("");
			}
			$this->columns[$columnNo]=new HYExcelColumn($width);
		}
	}

	public function setStyle(HYExcelStyle $style)
	{
		//preventing duplicated HYExcelStyles
		$styleIds = array();
		foreach($this->styles as $s)
		{
			$styleIds[] = $s->getId();
		}
		if(!in_array($style->getId(),$styleIds))
			$this->styles[] = $style;
	}

	/**
	 * Merge vertical cell, when
	 *
	 * @param int $x - the column no of the cell1 and cell2
	 * @param int $y1 - the row no of the cell1
	 * @param int $y2 - the row no of the cell2
	 */
	public function verticalMerge($colNo,$rowNo_start,$rowNo_end)
	{
		if(!isset($this->rows[$rowNo_start]) || !isset($this->rows[$rowNo_end]))
			return;
		if(!isset($this->rows[$rowNo_start]->cells[$colNo]) || !isset($this->rows[$rowNo_end]->cells[$colNo]))
			return;
			
		if($this->rows[$rowNo_start]->cells[$colNo] instanceof HYExcelCellVerticalMergeUp)
			throw new Exception("Can't verticalMerge to a HYExcelCellVerticalMergeUp!");

		if($rowNo_end <= $rowNo_start)
			throw new Exception("Can't verticalMerge backwards!");

		//get no of cells to merge
		$noOfCellsToMerge = $rowNo_end - $rowNo_start;
		$this->rows[$rowNo_start]->cells[$colNo]->setNoOfCellsMergeDown($noOfCellsToMerge);

		for($i=1;$i<=$noOfCellsToMerge;$i++)
		{
			if(isset($this->rows[$rowNo_start+$i]->cells[$colNo+1]))
			{
				$this->rows[$rowNo_start+$i]->cells[$colNo+1]->setIndex($colNo+2);
			}
		}
	}

	/**
	 * This will freeze the panes...
	 * @param unknown_type $rowNo
	 * @param unknown_type $colNo
	 */
	public function freezePanels($rowNo,$colNo)
	{
		if($rowNo!=0 && $colNo!=0)
		{
			$this->workSheetOptions[] = "<Selected/>
				<FreezePanes/>
				<FrozenNoSplit/>
				<SplitHorizontal>$rowNo</SplitHorizontal>
				<TopRowBottomPane>$rowNo</TopRowBottomPane>
				<SplitVertical>$colNo</SplitVertical>
				<LeftColumnRightPane>$colNo</LeftColumnRightPane>
				<ActivePane>0</ActivePane>
				<Panes>
					<Pane>
						<Number>3</Number>
					</Pane>
					<Pane>
						<Number>1</Number>
					</Pane>
					<Pane>
						<Number>2</Number>
					</Pane>
					<Pane>
						<Number>0</Number>
					</Pane>
				</Panes>
				<ProtectObjects>False</ProtectObjects>
				<ProtectScenarios>False</ProtectScenarios>";
		}
	}
	
	/**
	 * Formatting options for page layout
	 *
	 * @param array $options - formatting options
	 */
	public function applyFormattingOptions($options = array())
	{
		
		if (!empty($options))
		{

			$orientation = (isset($options['orientation']) ? $options['orientation'] : 'Landscape');
			
			$extraOrientation = '';
			if (isset($options['centerHorizontal']) && $options['centerHorizontal'])
				$extraOrientation .= ' x:CenterHorizontal="1"';
			if (isset($options['centerVertical']) && $options['centerVertical'])
				$extraOrientation .= ' x:CenterVertical="1"';
			
			$freezePanes = (isset($options['freezePanes']) ? $options['freezePanes'] : false);
			$tabColorIndex = (isset($options['tabColorIndex']) ? $options['tabColorIndex'] : false);
			$tabColorIndexID = (isset($options['tabColorIndexID']) ? $options['tabColorIndexID'] : 52);
			
			$headerRowNo = (isset($options['headerRow']) ? $options['headerRow'] : 0);

			$overrideMargin = "";
			$marginArr = array();
			// all the measurement below are in inch!
			if (!empty($options['marginTop']) && is_numeric($options['marginTop']))
				$marginArr[] = 'x:Top="'.$options['marginTop'].'"';
			if (!empty($options['marginBottom']) && is_numeric($options['marginBottom']))
				$marginArr[] = 'x:Bottom="'.$options['marginBottom'].'"';
			if (!empty($options['marginLeft']) && is_numeric($options['marginLeft']))
				$marginArr[] = 'x:Left="'.$options['marginLeft'].'"';
			if (!empty($options['marginRight']) && is_numeric($options['marginRight']))
				$marginArr[] = 'x:Right="'.$options['marginRight'].'"';
			if (!empty($marginArr))
				$overrideMargin = "<PageMargins ".join(" ", $marginArr)."/>";

			$this->workSheetOptions[] =	"	<PageSetup>
						   						<Layout x:Orientation=\"$orientation\" $extraOrientation/>
						   						$overrideMargin
						   					</PageSetup>";
			$this->workSheetOptions[] = "  	<Print>
										    	<ValidPrinterInfo/>
										    	<PaperSizeIndex>9</PaperSizeIndex>
										    	<HorizontalResolution>720</HorizontalResolution>
										    	<VerticalResolution>700</VerticalResolution>
										   	</Print>";
			$this->workSheetOptions[] = " <TabColorIndex>$tabColorIndexID</TabColorIndex>";
			
			if ($freezePanes)
			{
				$this->workSheetOptions[] = "<Selected/>
											<FreezePanes/>
											<FrozenNoSplit/>
											<SplitHorizontal>$headerRowNo</SplitHorizontal>
											<TopRowBottomPane>$headerRowNo</TopRowBottomPane>
											<ActivePane>2</ActivePane>
											<Panes>
												<Pane>
													<Number>3</Number>
												</Pane>
												<Pane>
													<Number>1</Number>
												</Pane>
												<Pane>
													<Number>2</Number>
												</Pane>
												<Pane>
													<Number>0</Number>
												</Pane>
											</Panes>
											<ProtectObjects>False</ProtectObjects>
											<ProtectScenarios>False</ProtectScenarios>";
			}
		}
	}

	private function mergeCellsWhenToString($debug=false)
	{
		$maxColumnNo = 0;
		$maxRowNo = count($this->rows);
		foreach($this->rows as $row)
		{
			if(count($row->cells)>$maxColumnNo)
				$maxColumnNo = count($row->cells);
		}
		
		//foreach column
		for($colNo=0;$colNo<$maxColumnNo;$colNo++)
		{
if($debug)	echo "Col:$colNo\n";
			$row_start = $row_end = 0;
			for($rowNo = 0;$rowNo<$maxRowNo;$rowNo++)
			{
if($debug)		echo "&nbsp;&nbsp;&nbsp;Row:$rowNo \n";
if($debug)		echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\$row_start=$row_start,\$row_end=$row_end,\$rowNo=$rowNo \n";
				
				if(isset($this->rows[$rowNo]->cells[$colNo]) && $this->rows[$rowNo]->cells[$colNo] instanceof HYExcelCellVerticalMergeUp)
				{
					$row_end=$rowNo;
if($debug)			echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\$row_end moving to  $row_end\n";
				}
				else
					$row_start = $row_end;
				
				if($row_start!=$row_end)
				{
					$this->verticalMerge($colNo,$row_start,$row_end);
if($debug)			echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;merging: \$this->verticalMerge(\$colNo=$colNo,\$row_start=$row_start,\$row_end=$row_end);\n";
				}
				else
				{
					$row_start = $row_end = $rowNo;
if($debug)			echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$row_start = $row_end = $rowNo; \n";
				}
if($debug)		echo "\n";
			}
if($debug)	echo "=======================\n\n";
		}
	}
}
?>