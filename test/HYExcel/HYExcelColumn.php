<?php
class HYExcelColumn
{
	private $autoFitWidth;
	private $width="";
	
	public function __construct($width="")
	{
		$this->setWidth($width);
	}
	
	public function __toString()
	{
		$return = "\t\t\t<Column";
		if($this->width!="")
			$return .= " ss:AutoFitWidth=\"0\" ss:Width=\"".$this->width."\"";
		else
			$return .= " ss:AutoFitWidth=\"1\" ";
		$return .= "/>\n";
		return $return;
	}
	
	public function setWidth($width="")
	{
		if($width=="")
		{
			$this->autoFitWidth=1;
			$this->width="";
		}
		else
		{
			$this->width = $width;
			$this->autoFitWidth=0;
		}
	}
}
?>