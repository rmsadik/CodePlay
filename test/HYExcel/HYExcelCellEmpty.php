<?php
/**
 * HYExcelCellEmpty just reserving a space when do vertical merging
 *
 */
class HYExcelCellEmpty extends HYExcelCell
{
	public function __construct()
	{}
	public function __toString()
	{return "";}
}
?>