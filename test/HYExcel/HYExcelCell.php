<?php
class HYExcelCell
{
	/**
	 * @var String - the type of the data:String, Number..
	 */
	private $type;
	/**
	 * @var String - the actuall data within the cell
	 */
	private $data;
	/**
	 * @var HYExcelStyle
	 */
	public $style=null;
	private $noOfCellsMergeAcross =0;
	
	/**
	 * @var integer - used, only when vertical merge happens
	 */
	private $index = 0;
	/**
	 * @var integer - used, only when vertical merge happens
	 */
	private $noOfCellsMergeDown = 0;
	
	private $formula="";
	
	private $namedCell;
	
	public function __construct($data,$type="",$style=null,$noOfCellsMergeAcross=0,$formula="")
	{
		$this->data = $data;
		if($type=="")
			$type = "String";
		$this->type = $type;
		if($style instanceof HYExcelStyle)
			$this->style= $style;
		if($noOfCellsMergeAcross>0)
			$this->noOfCellsMergeAcross = $noOfCellsMergeAcross;
			
		if($formula!="")
			$this->setFormula($formula);
		$this->namedCell = '';
	}
	
	public function __toString()
	{
		$return ="<Cell";
			if($this->formula !="")
				$return .=" ss:Formula=\"".$this->formula."\" ";
			if($this->style instanceof HYExcelStyle)
				$return .=" ss:StyleID=\"".$this->style->getId()."\" ";
			if($this->noOfCellsMergeAcross>0)
				$return .=" ss:MergeAcross=\"".$this->noOfCellsMergeAcross."\" ";
			if($this->noOfCellsMergeDown>0)
				$return .=" ss:MergeDown=\"".$this->noOfCellsMergeDown."\" ";
			if($this->index>0)
				$return .=" ss:Index=\"".$this->index."\" ";
			
			if($this->formula == "")
			{
				$return .="><Data ss:Type=\"".$this->type."\">";
					$return .=utf8_encode(str_replace(htmlentities("&#10;"),"&#10;",htmlentities($this->data)));
				$return .="</Data>";
			}
			else
			{
				$return .= ">";
			}
			
			if (!empty($this->namedCell))
			{
				$return .= "<NamedCell ss:Name=\"".$this->namedCell."\" />";
			}
			$return .="</Cell>";
				
		return $return;
	}
	
	public function setIndex($index)
	{
		$this->index = $index;
	}
	
	public function setNoOfCellsMergeDown($noOfCellsMergeDown)
	{
		$this->noOfCellsMergeDown = $noOfCellsMergeDown;
	}
	
	public function setFormula($formula)
	{
		$this->formula = $formula;
		$this->data = "";
	}
	
	public function getNoOfCellMergeAcross()
	{
		return $this->noOfCellsMergeAcross;
	}

	public function setNamedCell($name)
	{
		$this->namedCell = $name;
	}
}
?>