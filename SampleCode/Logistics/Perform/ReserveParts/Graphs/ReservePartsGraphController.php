<?php
/**
 * Reserve Parts Graph Controller
 *
 * @author : Jeremy Todter
 * @version : 1.0
 * @category : Reserve Parts
 * @uses : Highcharts
 * @ignore : Report Generation.
 *
 */
class ReservePartsGraphController extends HydraPage
{
	public $countDownTimer;

	private $_data = array();
	private $_openStatuses = array();
	private $_priorities = array();
	private $_priorityData = array();
	private $_availableGraphs = array();
	private $_selectedGraphs = array();
	private $_selectedStatuses = array();

	private $_isWormUser = false;
	private $_reportServerRedirectEnabled;

	/**
	 * On Pre initialization
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
		if (Core::getRole()->getName() === "Worm")
		{
			$this->_isWormUser = true;
			$this->getPage()->setMasterClass("Application.layouts.PlainHighChartLayout");
		}
		else
		{
			$this->getPage()->setMasterClass("Application.layouts.HighChartLayout");
		}
	}

	public function __construct()
	{
		parent::__construct();

		try
		{
			$this->_reportServerRedirectEnabled = (bool)Config::getAdminConf('ReportRedirection', 'Enable_' . __CLASS__);
		}
		catch (Exception $e) {}

		if ($this->_reportServerRedirectEnabled)
		{
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON
		}

		$this->roleLocks = "pages_all,pages_logistics,page_logistics_storageLocationReservation";

		$this->_openStatuses = FacilityRequest::getAllOpenStatuses();

		$this->_availableGraphs['National'] = 'National';
	}

	/**
	 * Enter description here...
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);

		$graphType = 'column';
		$graphStacking = '';
		$graphLabels = 'false';

		$dhc = Factory::service('DontHardCode')->searchDontHardcodeByParamNameAndFilterName('FacilityRequestLogic', 'FR_Graph_Config');
		$this->Page->cycleRateSecsLbl->Text = $dhc['cycleRateSecs'];

		//fetch all the daya first
		$this->_generateData($dhc['slaCutoffTime']);


		//generate and populate the preference panel so we know what to draw
		$graphPrefs = $this->_generatePrefPanel();
		if ($graphPrefs != null)					//we only draw if we've selected some stuff
		{
			$this->_selectedGraphs = array();
			$chkArr = get_object_vars($graphPrefs->chks); //convert to array, the selected graphs
			foreach ($chkArr as $locId)
			{
				if ($locId != 'ALL')
					$this->_selectedGraphs[] = $locId;
			}

			$jsOptions = array();
			//generate the graphs, we need to do all for drill-down
			foreach ($this->_availableGraphs as $graphLocation => $graphInfo)
			{
				//parent graph (National + States)
				$jsOptions[] = $this->_getGraphJSOptions($graphType, $graphStacking, $graphLabels, 'Status', $graphLocation);
				if (is_array($graphInfo))
				{
					//facility level
					foreach ($graphInfo as $graphLocation => $graphName)
					{
						$jsOptions[] = $this->_getGraphJSOptions($graphType, $graphStacking, $graphLabels, 'Priority', $graphLocation);
					}
				}
			}
			//do the actual drawing
			$this->_renderGraph($jsOptions, $dhc['cycleRateSecs']);
		}
	}

	/**
	 * override any header theme and reset to basic
	 */
	public function setBasicTheme()
	{
		$currentTheme = $this->getPage()->getTheme();
		$currentThemeName = $currentTheme->getName();
		$currentThemePath = $currentTheme->getBasePath();
		$currentThemeUrl = $currentTheme->getBaseUrl();

		$themeName = 'basic';
		$newThemePath = str_replace($currentThemeName,$themeName,$currentTheme->getBasePath());
		$newThemeUrl = str_replace($currentThemeName,$themeName,$currentTheme->getBaseUrl());
		$this->getPage()->setTheme(new TTheme($newThemePath,$newThemeUrl));
	}
	private function _generatePrefPanel()
	{
		$chkArr = $statusArr = array();

		$prefs = Factory::service('UserPreference')->getOption(Core::getUser(), "facilityRequestGraphPrefs");
		if ($prefs !== null)
		{
			try
			{
				$prefs = unserialize($prefs);
				$chkArr = get_object_vars($prefs->chks); //convert to array
				$statusArr = get_object_vars($prefs->status); //convert to array
			}
			catch (Exception $e)
			{
				$this->setErrorMessage($e->getMessage());
				$prefs = null;
			}
		}

		$this->_selectedStatuses = array_values($statusArr);

// 		Debug::inspect($chkArr);

		$statusAllChecked = '';
		if (array_key_exists('frStatusChk_ALL', $statusArr))
			$statusAllChecked = 'checked';

		$stateAllChecked = '';
		if (array_key_exists('frStateChk_ALL', $chkArr))
			$stateAllChecked = 'checked';

		$html = array();
		$html[] = '<table width="100%" border="0">';
		$html[] = 		'<tr>';
		$html[] = 			'<td style="vertical-align:top;  padding-left:5px; padding-right:10px; border-right:1px solid gray;">';
		$html[] = 				'<table width="100%" border="0">';
		$html[] = 					'<tr>';
		$html[] = 						'<td style="font-weight:bold; text-decoration:underline;">FR Status</td>';
		$html[] = 						'<td style="width:10px;"><input type="checkbox" id="frStatusChk_ALL" ' . $statusAllChecked . ' class="frStatusChk_ALL parentChk" data-childClass="frStatusChk" onclick="return chkChange(this);"/></td>';
		$html[] = 					'</tr>';
		foreach ($this->_openStatuses as $status)
		{
			$checked = '';
			if ($statusAllChecked || (array_key_exists('frStatusChk_' . $status, $statusArr)))
				$checked = 'checked';

			$html[] = 				'<tr>';
			$html[] = 					'<td>' . $status . '</td>';
			$html[] = 					'<td><input type="checkbox" value="' . $status . '" id="frStatusChk_' . $status . '" ' . $checked . ' class="frStatusChk" onclick="return chkChange(this);"/></td>';
			$html[] = 				'</tr>';
		}
		$html[] = 				'</table>';
		$html[] = 			'</td>';
		$html[] = 			'<td style="vertical-align:top;  padding-left:5px; padding-right:10px; border-right:1px solid gray;">';
		$html[] = 				'<table width="100%" border="0">';


		//National/States COL
		$html[] = 					'<tr>';
		$html[] = 						'<td style="font-weight:bold; text-decoration:underline;">National/States</td>';
		$html[] = 						'<td style="width:10px;"><input type="checkbox" id="frStateChk_ALL" ' . $stateAllChecked . ' class="frStateChk_ALL parentChk" data-childClass="frStateChk" onclick="return chkChange(this);"/></td>';
		$html[] = 					'</tr>';
		foreach (array_keys($this->_availableGraphs) as $stateId)
		{
			$name = $stateId;
			if ($stateId != 'National')
				$name = Factory::service("State")->get($stateId)->getName();

			$checked = '';
			if ($stateAllChecked || (array_key_exists('frStateChk_' . $stateId, $chkArr)))
				$checked = 'checked';

			$html[] = 				'<tr>';
			$html[] = 					'<td>' . $name . '</td>';
			$html[] = 					'<td><input type="checkbox" value="' . $stateId . '" id="frStateChk_' . $stateId . '" ' . $checked . ' class="frStateChk" data-linkedClass="frWhChk_' . $stateId . '" onclick="return chkChange(this);"/></td>';
			$html[] = 				'</tr>';
		}
		$html[] = 				'</table>';
		$html[] = 			'</td>';

		$html[] = 			'<tr><td colspan="7">&nbsp;</td></tr>';

		$html[] = 			'<tr>';


		//WH COLS
		foreach ($this->_availableGraphs as $stateId => $graphInfo)
		{
			if ($stateId == 'National')
				continue;

			$whAllChecked = '';
			if (array_key_exists('frWhChk_' . $stateId . '_ALL', $chkArr))
				$whAllChecked = 'checked';

			$html[] = 					'<td style="vertical-align:top;  padding-left:5px; padding-right:10px; border-right:1px solid gray;">';
			$html[] = 						'<table width="100%" border="0">';
			$html[] = 							'<tr>';
			$html[] = 								'<td style="text-decoration:underline;"><b>' . Factory::service("State")->get($stateId)->getName() . '</b></td>';
			$html[] = 								'<td style="width:10px;"><input type="checkbox" value="' . $stateId . '" ' . $whAllChecked . ' id="frWhChk_' . $stateId . '_ALL" class="frWhChk_' . $stateId . '_ALL parentWhChk" data-childClass="frWhChk_' . $stateId . '" onclick="return chkChange(this);" /></td>';
			$html[] = 							'</tr>';

			if (is_array($graphInfo))
			{
				//facility level
				foreach ($graphInfo as $whId => $whName)
				{
					$checked = '';
					if ($whAllChecked || (array_key_exists('frWhChk_' . $whId, $chkArr)))
						$checked = 'checked';

					$html[] = 					'<tr>';
					$html[] = 						'<td>' . $whName . '</td>';
					$html[] = 						'<td style="width:10px;"><input type="checkbox" value="' . $whId . '" ' . $checked . ' id="frWhChk_' . $whId . '" class="frWhChk_' . $stateId . '" onclick="return chkChange(this);"/></td>';
					$html[] = 					'</tr>';
				}
			}
			$html[] = 						'</table>';
			$html[] = 					'</td>';
		}

		$html[] = 		'</tr>';
		$html[] = '</table>';

		$this->prefPanelContent->getControls()->add(implode('', $html));

		return $prefs;
	}

	public function savePrefs()
	{
		try
		{
			if ($this->_reportServerRedirectEnabled)
				Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

			Factory::service('UserPreference')->setOption(Core::getUser(), "facilityRequestGraphPrefs", serialize(json_decode($this->Page->jsonPreferences->Value)));
			$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('window.location.href = window.location.href;'));
		}
		catch (Exception $e)
		{
			$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('alert("An error has occurred;\n\n' . $e->getMessage() . '");', 'mb.hide();'));

			if ($this->_reportServerRedirectEnabled)
				Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON
		}
	}

	private function _generateData($slaCutoffTime)
	{
		$statusArray = $series = array();
		foreach ($this->_openStatuses as $status)
		{
			$statusArray[$status] = 0;
			$series[$status] = array('name' => $status, 'data' => array());
		}

		$dhc = Factory::service('DontHardCode')->searchDontHardcodeByParamNameAndFilterName('FacilityRequestLogic', 'FR_Graph_Priorities');
		foreach ($dhc as $data)
		{
			$pri = key($data);
			$this->_priorities[$pri] = $statusArray;
			$this->_priorityData[$pri] = $data[$pri];
		}

		$sql = "SELECT
					fr.id as frId,
				  	fr.status as status,
				  	IF (wOwner.id IS NULL, wFac.name, wOwner.name) as `wh`,
				  	IF (wOwner.id IS NULL, wFac.id, wOwner.id) as whId,
				  	IF (wOwner.id IS NULL, sFac.name, sOwner.name) as `state`,
				  	IF (wOwner.id IS NULL, sFac.id, sOwner.id) as stateId,
					a.timezone as tz,
		          	(	SELECT
							CONCAT(fttg.targetEndTime)
						FROM fieldtasktarget fttg
						WHERE fttg.active = 1 AND fttg.fieldtaskId=fr.fieldtaskId
						ORDER BY fttg.targetEndTime DESC LIMIT 1
					) AS sla
				FROM facilityrequest fr
				LEFT JOIN warehouse wOwner ON wOwner.id=fr.ownerid AND wOwner.active=1
				LEFT JOIN state sOwner ON sOwner.id=wOwner.stateid AND sOwner.active=1
				INNER JOIN facility fac ON fac.id=fr.facilityid AND fac.active=1
				INNER JOIN warehouse wFac ON wFac.facilityid=fac.id AND wFac.active=1
				INNER JOIN state sFac ON sFac.id=wFac.stateid AND sFac.active=1
				INNER JOIN fieldtask ft ON fr.fieldtaskid=ft.id AND ft.active=1
				INNER JOIN address a ON a.id=ft.addressid AND a.active=1
				WHERE fr.status IN ('" . implode("','", $this->_openStatuses) . "') and fr.active=1
				GROUP BY fr.id
				ORDER BY `state` ASC, `wh` ASC";
// 		Debug::inspect($sql);
		$res = Dao::getResultsNative($sql, array(), PDO::FETCH_ASSOC);
// 		Debug::inspect($res);

		$data = $tzNowTimes = array();
		foreach ($res as $r)
		{
			$status = trim($r['status']);
			$wh = trim($r['wh']);
			$whId = trim($r['whId']);
			$state = trim($r['state']);
			$stateId = trim($r['stateId']);
			$sla = trim($r['sla']);
			$tz = trim($r['tz']);

			if (!array_key_exists($stateId, $data)) 	//check if we've got the state yet in the national array
			{
				$data[$stateId] = array('name' => $state, 'status' => $statusArray, 'kids' => array());			//create the state array at national level
				$this->_availableGraphs[$stateId] = array();
			}

			if (!array_key_exists($status, $data[$stateId]['status'])) 	//check if we've got the status counter starting within the state
				$data[$stateId]['status'][$status] = 0;

			$data[$stateId]['status'][$status]++;	//increment the status count

			if (!array_key_exists($whId, $data[$stateId]['kids'])) 	//check if we've got the warehouse within the state
			{
				$data[$stateId]['kids'][$whId] = array('name' => $wh, 'status' => $statusArray, 'sla' => $this->_priorities);
				$this->_availableGraphs[$stateId][$whId] = $wh;
			}

			if (!array_key_exists($status, $data[$stateId]['kids'][$whId]['status'])) 	//check if we've got the status counter starting within warehouse
				$data[$stateId]['kids'][$whId]['status'][$status] = 0;

			$data[$stateId]['kids'][$whId]['status'][$status]++;

			if (!array_key_exists($tz, $tzNowTimes))		//get the now times in timezone so we don't fetch for each individual FR
			{
				$now = new HydraDate();
				$now->setTimeZone($tz);
				$tzNowTimes[$tz] = $now;
			}

			$priData = FacilityRequestLogic::getFacilityRequestPrioritiesFromSLA($sla, $tz, $tzNowTimes[$tz], $dhc, $slaCutoffTime); //get priority info based on SLA and timezone
			if (!array_key_exists($status, $data[$stateId]['kids'][$whId]['sla'][key($priData)])) 	//check if we've got the status counter starting within warehouse (priorities)
				$data[$stateId]['kids'][$whId]['sla'][key($priData)][$status] = 0;

			$data[$stateId]['kids'][$whId]['sla'][key($priData)][$status]++;

			if (!array_key_exists('title', $this->_priorityData[key($priData)]))
				$this->_priorityData[key($priData)]['title'] = $priData[key($priData)]['title'];
		}
		$this->_data = $data;
// 		Debug::inspect($data);
	}

	private function _getGraphJsOptions($graphType, $graphStacking, $graphLabels, $graphMode, $graphLocation)
	{
		$legendEnabled = 'true';

		$xAxisStyle = '';
		if ($graphLocation === 'National')	//set the labels larger
		{
			$xAxisStyle = "style: {fontWeight: 'bold', fontSize: '24px'},";
		}
		else if ($graphMode === 'Priority')
		{
			$xAxisStyle = "style: {fontWeight: 'bold', fontSize: '48px'},";
		}
		else
		{
			$xAxisStyle = "style: {fontWeight: 'bold', fontSize: '20px'},";
		}

		$series = array();
		foreach ($this->_openStatuses as $status)
		{
			$visible = false;
			if (in_array($status, $this->_selectedStatuses))
				$visible = true;

			$series[$status] = array('name' => $status, 'data' => array(), 'visible' => $visible);
		}

		if ($graphMode === 'Status')
		{
			$graphTitle = Factory::service("State")->get($graphLocation);
		}
		else
		{
			$graphTitle = Factory::service("Warehouse")->get($graphLocation);
		}

		$xAxis = array();
		foreach ($this->_data as $stateId => $stateData)
		{
			if ($graphLocation === 'National')
			{
				$graphTitle = $graphLocation;
				$xAxis[] = $stateData['name'];
			}

			if ($graphMode === 'Status')
			{
				foreach ($stateData['status'] as $status => $count)
				{
					if ($graphLocation === 'National')
					{
						$series[$status]['data'][] = array('name' => $stateData['name'], 'y' => $count, 'drillId' => $stateId);
					}
					else if ($graphLocation == $stateId)
					{
						foreach ($stateData['kids'] as $whId => $whData)
						{
							$xAxis[] = $whData['name'];
							foreach ($whData['status'] as $whStatus => $whCount)
							{
								if ($status == $whStatus)
								{
									$series[$status]['data'][] = array('name' => $whData['name'], 'y' => $whCount, 'drillId' => $whId);
								}
							}
						}
					}
				}
			}
			else //Priority mode
			{
				if (array_key_exists($graphLocation, $stateData['kids']))
				{
					$whData = $stateData['kids'][$graphLocation];
					foreach ($whData['sla'] as $priority => $priorityData)
					{
						$xAxis[] = $priority;

						foreach ($priorityData as $status => $statusCount)
						{
							$series[$status]['data'][] = array(	'name' => $priority, 'y' => $statusCount);
						}
					}
				}
			}
		}

		//check here if we need to rotate the labels at all
		$xAxisRotation = '0';
		$marginLeft = 'null';
		if ($graphMode === 'Status')
		{
			if (count($xAxis) / count($this->_openStatuses) > 7) //if we've got more than 7 on the x axis then rotate
			{
				$xAxisRotation = '-45';
				$marginLeft = '120';
			}
		}

		$xAxisCategories = 'categories: ' . json_encode($xAxis) . ',';
		$series = json_encode(array_values($series));

		$js = "
					options['$graphLocation'] = {
									chart: {
										renderTo: 'ctl0_MainContent_frGraphPanel',
										type: '$graphType',
										spacingLeft: 10,
        								spacingRight: 20,
        								marginLeft: $marginLeft
							        },
							        title: {
							            text: '$graphTitle',
							            style: {fontWeight: 'bold', fontSize: '40px'},
							            useHTML: true
							        },
									subtitle: {
					                    text: 'Facility Request Count By $graphMode',
					                    style: {fontWeight: 'bold', fontSize: '20px'},
					                    useHTML: true
					                },
									legend: {
					                    enabled: $legendEnabled
					                },
									plotOptions: {
					                    series: {
											cursor: 'pointer',
											stacking: '$graphStacking',
					                        dataLabels: {
					                        	verticalAlign: 'bottom',
					                        	style: {fontSize: '20px'},
					                        	borderRadius: 5,
							                    backgroundColor: 'rgba(252, 255, 197, 0.7)',
							                    borderWidth: 1,
							                    borderColor: '#AAA',
							                    padding: 2,
					                        	enabled: $graphLabels
											},
							                point: {
							                    events: {
							                        click: function () {
							                            if (this.drillId != null)
							                            {
							                            	cycleCounter.reset();
							                            	cycleGraph(this.drillId);
							                            }
							                            else
							                            {
							                            	alert('Further drill-down functionality to come...');
							                            }
							                        }
							                    }
							                }
					                    }
					                },
							        xAxis: {
										labels: {
											$xAxisStyle
							                rotation: $xAxisRotation,
							                events: {
							                    click: function () {}
											},
											formatter: function () {
												if (priorityData[this.value] != null)
												{
													return '<div style=\"height:100px;\"><span style=\"color:' + priorityData[this.value]['colour'] + ';\">' + this.value + '</span><br /><span style=\"font-size:14px;\">' + priorityData[this.value]['legend'] + '</span></div>';
												}
												else
												{
													return this.value;
												}
											}
							            },
										showEmpty: false,
										$xAxisCategories
										type: 'category'
							        },
							        yAxis: {
							        	labels:{
							        		style: {fontWeight: 'bold', fontSize: '26px'}
							        	},
							        	allowDecimals: false,
										showEmpty: false,
							            title: {
							                text: ''
							            }
							        },
							        series: $series
							    };";
		return $js;
	}

	private function _renderGraph($jsOptions, $cycleRateSecs)
	{
		$js =  "var cycleCounter;
				var chart;
				var options = [];
				var selections = ['" . implode("','", $this->_selectedGraphs) . "'];
				var originalSelections = ['" . implode("','", $this->_selectedGraphs) . "'];
				var priorityData = " . json_encode($this->_priorityData) . ";";

		$js .= implode('', $jsOptions);
		$js .= "
				jQuery(document).ready(function()
				{
					document.getElementById('prefPanelWrapper').style.display = 'none';
					document.getElementById('infoPanel').style.display = 'none';
					document.getElementById('restartBtn').style.display = '';
					document.getElementById('reloadBtn').style.display = '';

					cycleGraph();

					cycleCounter = new Countdown({
		                	seconds: $cycleRateSecs,  // number of seconds to count down
		                	onCounterEnd: function(theTimer) {cycleGraph();} // final action
		            	});
	            	cycleCounter.start();
				});";
// 		Debug::inspect($js);
		$this->frGraphPanel->Style = 'width:100%; height:800px; display:block;';
		$this->frGraphPanel->getControls()->add(JavascriptLogic::getScriptTagWithContent(array($js)));
	}
}

?>