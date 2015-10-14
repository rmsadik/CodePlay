<?php
class HYExcelRow
{
	public $cells;
	private $height="";
	private $style=null;
	private $autoFitHeight=0;
	
	public $hasVerticalMergeCells = false;
	
	public $styles = array();
	
	public function __construct($height="",$style=null)
	{
		$this->height = $height;
		$this->autoFitHeight = ($height=="" ? 1:0);
		if($style instanceof HYExcelStyle )
		{
			$this->style=$style;
			$this->setStyle($style);
		}
	}
	
	public function __toString()
	{
		$return = "\t\t\t<Row";
		if($this->height!="")
			$return .= " ss:Height=\"".$this->height."\"";
		else
			$return .= " ss:AutoFitHeight=\"1\"";
			
		if($this->style instanceof HYExcelStyle )
			$return .= " ss:StyleId=\"".$this->style->getId()."\"";
		$return .= ">\n";
		
		foreach($this->cells as $cell)
		{
			$cellString = $cell->__toString();
			if($cellString!="")
				$return .= "\t\t\t\t".$cell->__toString()."\n";
		}
		$return .= "\t\t\t</Row>\n";
		return $return;
	}
	
	public function addCell(HYExcelCell $cell)
	{
		$this->cells[] = $cell;
		//reserving the space
		for($i=0;$i<$cell->getNoOfCellMergeAcross();$i++)
		{
			$this->cells[] = new HYExcelCellEmpty();
		}
		
		if($cell instanceof HYExcelCellVerticalMergeUp)
			$this->hasVerticalMergeCells=true;
		
		if($cell->style instanceof HYExcelStyle)
			$this->setStyle($cell->style);
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
	
	public function getStyles()
	{
		return $this->styles;
	}
	
	public function getCells()
	{
		return $this->cells;
	}
	
	public function getCell($index)
	{
		if(!isset($this->cells[$index]))
			return null;
		return $this->cells[$index];
	}
		
	public function removeCell($index)
	{
		if(!isset($this->cells[$index]))
			return null;
		$array = array();
		foreach($this->cells as $in=>$cell)
		{
			if($in!=$index)
				$array[] = $cell;
		}
		$this->cells = $array;
	}
	
	public function setHeight($height)
	{
		$this->height = $height;
	}
	
	public function getHeight()
	{
		return $this->height;
	}
}
?>