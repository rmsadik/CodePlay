<?php
class HYExcelStyle
{
	private $id;
	private $name="";
	private $font="";
	private $interior="";
	private $numberFormat="";
	private $protection="";
	private $alignment="";
	private $borders =array();
	
	public function __construct($id,$name="")
	{
		$this->id = $id;
		$this->name = $name;
	}
	
	public function __toString()
	{
		$return ="\t\t<Style ss:ID=\"".$this->id."\"";
		if($this->name!="")
			$return .=" ss:Name=\"".$this->name."\"";
		$return .=">\n";
			if($this->alignment!="")
				$return .="\t\t\t".$this->alignment."\n";
			if($this->font!="")
				$return .="\t\t\t".$this->font."\n";
			if($this->interior!="")
				$return .="\t\t\t".$this->interior."\n";
			if($this->numberFormat!="")
				$return .="\t\t\t".$this->numberFormat."\n";
			if($this->protection!="")
				$return .="\t\t\t".$this->protection."\n";
			if(count($this->borders)>0)
			{
				$return .="\t\t\t<Borders>\n";
				foreach($this->borders as $border)
				{
					$return .="\t\t\t\t".$border."\n";
				}
				$return .="\t\t\t</Borders>\n";
			}
		$return .="\t\t</Style>\n";
		return $return;
	}
	
	public function setId($id)
	{
		$this->id = $id;
	}
	
	public function getId()
	{
		return $this->id;
	}
	
	public function setAlignment($horizontalPosition="Left",$verticalPosition="Bottom",$wrapText=false,$rotate="")
	{
		$this->alignment="<Alignment ss:Horizontal=\"$horizontalPosition\" ss:Vertical=\"$verticalPosition\" ss:WrapText=\"".($wrapText==true ? 1: 0)."\" ";
		if($rotate>"")
			$this->alignment .=" ss:Rotate=\"$rotate\" ";
		$this->alignment .="/>";
	}
	
	public function setFont($fontFamily="Swiss",$color="",$bold=false,$underLine=false,$fontSize=10)
	{
		$this->font="<Font";
		$this->font.=" x:Family=\"$fontFamily\" ss:Size=\"$fontSize\"";
		if($color!="")
			$this->font.=" ss:Color=\"$color\"";
		if($bold!=false)
			$this->font.=" ss:Bold=\"1\"";
		if($underLine!=false)
			$this->font.=" ss:Underline=\"Single\"";
		$this->font.="/>";
	}
	
	public function setInterior($color="",$pattern="Solid")
	{
		$this->interior="<Interior";
		if($color!="")
			$this->interior.=" ss:Color=\"$color\"";
		if($pattern!="")
			$this->interior.=" ss:Pattern=\"$pattern\"";
		$this->interior.="/>";
	}
	
	public function setNumberFormat($format="Currency")
	{
		$this->numberFormat = '<NumberFormat ss:Format="'.$format.'"/>';
	}
	
	public function setBorder($position="all",$color="#00000",$weight=1,$lineStyle="Continuous")
	{
		$position = ucfirst(strtolower($position));
		if($position=="All")
		{
			$this->borders["Left"] = "<Border ss:Position=\"Left\" ss:LineStyle=\"$lineStyle\" ss:Weight=\"$weight\" ss:Color=\"$color\" />";
			$this->borders["Right"] = "<Border ss:Position=\"Right\" ss:LineStyle=\"$lineStyle\" ss:Weight=\"$weight\" ss:Color=\"$color\" />";
			$this->borders["Top"] = "<Border ss:Position=\"Top\" ss:LineStyle=\"$lineStyle\" ss:Weight=\"$weight\" ss:Color=\"$color\" />";
			$this->borders["Bottom"] = "<Border ss:Position=\"Bottom\" ss:LineStyle=\"$lineStyle\" ss:Weight=\"$weight\" ss:Color=\"$color\" />";
		}
		else
			$this->borders[$position] = "<Border ss:Position=\"$position\" ss:LineStyle=\"$lineStyle\" ss:Weight=\"$weight\" ss:Color=\"$color\" />";
	}
	
	public function cloneStyle($newId,$name="")
	{
		$newStyle = clone $this;
		$newStyle->setId($newId);
		return $newStyle;
	}
}
?>