//this is the source file for the FieldTaskPartReturnController
var js = new Class.create();
js.prototype = {
	statusInfo: {},	//holds the worktype specific status info
	WHHolderId: '', //the selected warehouse holder html id
	requestData: {
		ftId: {},
		pt: {}, //the part type
		pis: {}, //the list of the part instances
		whId: '', //the warehouse id of part instances
		po: {
			id: '', //the purchase order Id
			pId: '' //the purchase order part id
		}
	},
	aliasTypes: {}, //the alias type translator: id => name
	callbackIds: {
		registerPI: '', // handler for registering PI
		selectPT: '', // handler for selecting the part type
		checkWH: '', // handler for checking WH
		checkSN: ''   // handler for checking serial number existence
	},
	//constructor
	initialize: function (WHHolderId, searchFieldTaskCallback, updateTaskStatusCallback, searchReturnedPartCallback, balancePartsCallback) {
		this.callbackIds.searchFieldTask = searchFieldTaskCallback;
		this.callbackIds.updateTaskStatus = updateTaskStatusCallback;
		this.callbackIds.searchReturnedPart = searchReturnedPartCallback;
		this.callbackIds.balanceParts = balancePartsCallback;
		this.WHHolderId = WHHolderId;
	},

	//functions
	searchFieldTask: function() {
		var tmp = {};
		if ($F('txtTaskNo') === '')
		{
			return alert('Please enter a Task No / Client Ref!');
		}

		bsuiteJs.postAjax(this.callbackIds.searchFieldTask, {'txtTaskNo': $F('txtTaskNo')}, {
			'onLoading': function() {
				Modalbox.show('loading', {beforeLoad: function(){Modalbox.deactivate();}, 'title': 'finding field task, please be patient...'});
			},
			'onComplete': function (sender, param) {
				try {tmp.result = bsuiteJs.getResp(param, false, true);}
				catch(e) {
					alert(e);
					Modalbox.hide();
					return;
				}

				if (tmp.result.ft.id === undefined || tmp.result.ft.id === null) {
					alert('System Error: field task information missing!');
					Modalbox.hide();
					return;
				}

				if (tmp.result.ft.errMsg != undefined || tmp.result.ft.errMsg != null) {
					alert(tmp.result.ft.errMsg);
					Modalbox.hide();
					return;
				}

				//set the hidden value to see if we update the ftp at the end of it all
				if (tmp.result.ft.updateFieldTaskPropertyOnFinish != undefined && tmp.result.ft.updateFieldTaskPropertyOnFinish == true)
				{
					$('updateFieldTaskPropertyOnFinish').value = 1;
				}

				$('ftId').value = tmp.result.ft.id;
				$('currentStatus').value = tmp.result.ft.status;
				$('nextStatuses').value = tmp.result.ft.nextStatuses.join();
				$('statusInfo').value = Object.toJSON(tmp.result.ft.statusInfo);
				this.statusInfo = $F('statusInfo').evalJSON();

				pageJs.bindStatusList(tmp.result.ft.nextStatuses);

				$('resetPanel').removeClassName('hidden');
				$('searchTaskDiv').removeClassName('currentRow').addClassName('row');

				//its in RECD@LOGISTICS, so show the serial field, otherwise we show the status field
				if ($('currentStatus').value == this.statusInfo.scan)
				{
					$('taskStatusDiv').removeClassName('hidden');
					$('serialNoDiv').removeClassName('row').removeClassName('hidden').addClassName('currentRow');
					$('txtSerialNo').focus();
				}
				else
				{
					$('taskStatusDiv').removeClassName('row').removeClassName('hidden').addClassName('currentRow');
					$('btnUpdateStatus').focus();
				}
				$('txtTaskNo').disabled = true;
				$('btnTaskNo').disabled = true;
				$('btnUpdateStatus').disabled = true;

				Modalbox.hide();
			}
		});
	},
	bindStatusList: function(statuses) {
		var html = '';
		if (Object.prototype.toString.call(statuses) === '[object Array]') {
			statuses.each(function(status) {
				html += '<option value="' + status + '" ' + '>' + status + '</option>';
			});
		}
		$('taskStatusList').update(html);
	},
	updateStatusChange: function() {
		$('btnUpdateStatus').disabled = true;
		if ($F('taskStatusList') != $F('currentStatus'))
		{
			$('btnUpdateStatus').disabled = false;
		}
	},
	updateStatus: function() {
		var tmp = {};
		tmp.taskNotes = '';
		if ($F('ftId') === '')
		{
			return alert('Invalid Field Task ID');
		}

		this.statusInfo = $F('statusInfo').evalJSON();

		tmp.failed = false;

		//we're changing to the failed status, so we need to generate the notes to add to the task
		if ($F('taskStatusList') == this.statusInfo.fail)
		{
			tmp.failed = true;

			tmp.taskNotes = "";
			//we had a parent match, so never got to do the parts matching
			if ($('parentStatusImg').src.indexOf('redbutton') != -1)
			{
				tmp.taskNotes += "\n\nThe returned part type, did not match the field task part type";
			}
			else
			{
				tmp.recipe = $('recipe').value.evalJSON();
				for (var i=0; i<tmp.recipe.length; i++)
				{
					if (tmp.recipe[i].passed == false)
					{
						tmp.taskNotes += "\n----------------------------------------------------------------------";
						tmp.taskNotes += "\nKit Part: " + tmp.recipe[i].pc + " ::: " + tmp.recipe[i].name;
						if (tmp.recipe[i].serialised)
						{
							tmp.taskNotes += "\nReceived: " + tmp.recipe[i].found.ptInfo;
							tmp.taskNotes += "\nSerial No: " + tmp.recipe[i].found.serialNo + " (" + tmp.recipe[i].found.status + ")";
						}
						else
						{
							tmp.taskNotes += "\nExpecting: " + tmp.recipe[i].qty +
											 "\nReceived : " + (parseInt(tmp.recipe[i].found.good) + parseInt(tmp.recipe[i].found.notGood)) + " (Good: " + tmp.recipe[i].found.good + ", Not Good: " + tmp.recipe[i].found.notGood + ")";
						}
						if (i == tmp.recipe.length-1)
						{
							tmp.taskNotes += "\n----------------------------------------------------------------------";
						}
					}
				}
			}
			if (tmp.taskNotes != '')
			{
				tmp.taskNotes = "The task has failed the BOM TEST for the following reason(s);\n\n" + "Returned Part: " + $('parentPiInfo').value + tmp.taskNotes;
				alert(tmp.taskNotes);
			}
		}
		else if ($F('taskStatusList') == this.statusInfo.finish || $F('taskStatusList') == this.statusInfo.fail || $F('taskStatusList') == this.statusInfo.cancel)
		{
			//we're either passing or failing the test, so we need to update the returned part instance to the field task property
			tmp.updateFtpPiId = false;
			if (($F('taskStatusList') == this.statusInfo.finish || $F('taskStatusList') == this.statusInfo.fail) && $F('updateFieldTaskPropertyOnFinish') == 1)
			{
				tmp.updateFtpPiId = $F('parentPiId');
			}
			if (!confirm("Are you sure you want to update the status to " + $F('taskStatusList') + "?"))
				return;
		}

		bsuiteJs.postAjax(this.callbackIds.updateTaskStatus, {'ftId': $F('ftId'), 'status': $F('taskStatusList'), 'piId': $F('parentPiId'), 'taskNotes': tmp.taskNotes, 'updateFtpPiId': tmp.updateFtpPiId, 'failed': tmp.failed}, {
			'onLoading': function() {
				Modalbox.show('loading', {beforeLoad: function(){Modalbox.deactivate();}, 'title': 'updating task status, please be patient...'});
			},
			'onComplete': function (sender, param) {
				try {tmp.result = bsuiteJs.getResp(param, false, true);}
				catch(e) {
					alert(e);
					Modalbox.hide();
					return;
				}

				if (tmp.result.ft.id === undefined || tmp.result.ft.id === null) {
					alert('System Error: field task information missing!');
					Modalbox.hide();
					return;
				}

				if (tmp.result.ft.errMsg != undefined || tmp.result.ft.errMsg != null) {
					alert(tmp.result.ft.errMsg);
					Modalbox.hide();
					return;
				}

				$('currentStatus').value = tmp.result.ft.status;
				$('nextStatuses').value = tmp.result.ft.nextStatuses.join();

				pageJs.bindStatusList(tmp.result.ft.nextStatuses);

				this.statusInfo = $F('statusInfo').evalJSON();
				if (tmp.result.ft.status == this.statusInfo.scan)			//this is the first step, so show the serial number field
				{
					$('taskStatusDiv').removeClassName('currentRow').addClassName('row');
					$('serialNoDiv').removeClassName('row').removeClassName('hidden').addClassName('currentRow');
					$('taskStatusList').disabled = true;
					$('btnUpdateStatus').disabled = true;
					$('txtSerialNo').focus();
				}
				else if (tmp.result.ft.status == this.statusInfo.test)		//now we start the test
				{
					$$('.partsMatchDiv').each(function(el) {
						el.removeClassName('hidden');
					});

					$('taskStatusDiv').removeClassName('currentRow').addClassName('row');
					$('recipeDiv').removeClassName('row').removeClassName('hidden').addClassName('currentRow');
					$('taskStatusList').disabled = true;
					$('btnUpdateStatus').disabled = true;

					if ($('btnFinishTest') && $('parentStatusImg').src.indexOf('redbutton') != -1)
					if ($('btnFinishTest'))
					{
						$('btnFinishTest').disabled = false;
					}
				}
				else if ($F('taskStatusList') == this.statusInfo.fail || $F('taskStatusList') == this.statusInfo.finish || $F('taskStatusList') == this.statusInfo.cancel)
				{
					tmp.alertMsg = '';
					if (tmp.result.ft.alertMsg != undefined || tmp.result.ft.alertMsg != '')
					{
						tmp.alertMsg = tmp.result.ft.alertMsg + "\n\n";
					}

					alert(tmp.alertMsg + "This task (" + $F('ftId') + ") can no longer be edited from this page, as the status is: " + $F('taskStatusList') + "\n\nThe page will now reload...");
					location.reload(true);
				}
				else
				{
					$('taskStatusDiv').removeClassName('currentRow').addClassName('row');
					$('serialNoDiv').removeClassName('row').removeClassName('hidden').addClassName('currentRow');
					$('taskStatusList').disabled = true;
					$('btnUpdateStatus').disabled = true;
					$('btnSerialNo').focus();
				}

				Modalbox.hide();
			}
		});
	},
	searchReturnedPart: function() {
		var tmp = {};
		if ($F('txtSerialNo') === '')
		{
			return alert('Please enter a serial no to search...');
		}

		bsuiteJs.postAjax(this.callbackIds.searchReturnedPart, {'ftId': $F('ftId'), 'txtSerialNo': $F('txtSerialNo')}, {
			'onLoading': function() {
				Modalbox.show('loading', {beforeLoad: function(){Modalbox.deactivate();}, 'title': 'finding part instance, please be patient...'});
			},
			'onComplete': function (sender, param) {
				$('registerEditPanel').addClassName('hidden');
				$('registerEditLink').stopObserving('click');

				try {tmp.result = bsuiteJs.getResp(param, false, true);}
				catch(e) {
					alert(e);
					Modalbox.hide();

					if (e.indexOf('Unable to find a matching part instance for serial no') != -1)
					{
						$('registerEditLink').observe('click', function() {window.open('/registerparts'); return false;});
						$('registerEditLink').update("Register Part Instance");
						$('registerEditPanel').removeClassName('hidden');
					}
					else if (e.indexOf('Multiple part instances found for serial no') != -1)
					{
						$('registerEditLink').observe('click', function() {window.open('/reregisterparts'); return false;});
						$('registerEditLink').update("Edit Part Instance");
						$('registerEditPanel').removeClassName('hidden');
					}
					return;
				}

				if (tmp.result.ft.id === undefined || tmp.result.ft.id === null) {
					alert('System Error: field task information missing!');
					Modalbox.hide();
					return;
				}

				if (tmp.result.ft.recipe === undefined || tmp.result.ft.recipe === null) {
					alert('System Error: recipe information missing!');
					Modalbox.hide();
					return;
				}

				Modalbox.hide();

				$('recipe').value = Object.toJSON(tmp.result.ft.recipe); 	//save the recipe for final stages later

				if (tmp.result.ft.parentMatch == false)	//we didn't have a part type match on the task
				{
					$('parentStatusImg').src = $('parentStatusImg').src.replace("greenbutton","redbutton");
					$('parentStatusImg').observe('mouseover', function(e) {bsuiteJs.showTooltip(e, 'toolTip', "FAILED: " + tmp.result.ft.errMsg);});
				}
				else
				{
					for (var i=0; i<tmp.result.ft.recipe.length; i++)
					{
						var ingredient = tmp.result.ft.recipe[i];

						var hpImg = '&nbsp;';
						if (ingredient.hp)
						{
							hpImg = '<img src="/themes/images/red_flag_16.png" onmouseover="bsuiteJs.showTooltip(event, \'toolTip\', \'This is a high priority part...\')"/>';
						}

						var sImg = '&nbsp;';
						if (ingredient.serialised)
						{
							sImg = '<img src="/themes/images/s.gif" onmouseover="bsuiteJs.showTooltip(event, \'toolTip\', \'This is a serialised part...\')"/>';
						}

						tmp.html = '<div class="recipeDisplayDiv">';
						tmp.html +=  '<table border="0" width="100%;" style="text-align:center; vertical-align:middle;">';
						tmp.html += 	'<tr>';
						tmp.html += 		'<td style="width:26px;">' + hpImg + '</td>';
						tmp.html += 		'<td style="width:56px; font-weight:bold;"><div class="partCode">' + ingredient.pc + '</div></td>';
						tmp.html += 		'<td style="width:26px;">' + sImg + '</td>';
						tmp.html += 		'<td style="text-align:center; padding-right:10px;" class="partName"><div>' + ingredient.name + '</div></td>';
						tmp.html += 		'<td style="width:30px;"><img src="/themes/images/bluebutton.png" class="statusImg" onclick="pageJs.showCapturePanel(this, ' + i + ');" onmouseover="bsuiteJs.showTooltip(event, \'toolTip\', \'Click here to verify returned part...\')"/></td>';
						tmp.html += 	'<tr>';
						tmp.html +=  '</table>';
						tmp.html += '</div>';
						tmp.html +=  "<input type=\"hidden\" value='" + Object.toJSON(ingredient) + "' />";

						if (ingredient.serialised)
						{
							tmp.html += '<div class="recipeCaptureDiv hidden">';
							tmp.html +=  '<table border="0" style="width:100%; text-align:center; vertical-align:middle;">';
							tmp.html += 	'<tr>';
							tmp.html += 		'<td style="width:26px;">' + hpImg + '</td>';
							tmp.html += 		'<td style="width:56px; font-weight:bold;"><div class="partCode">' + ingredient.pc + '</div></td>';
							tmp.html += 		'<td style="width:26px;">' + sImg + '</td>';
							tmp.html += 		'<td style="width:220px; text-align:right;"><b>Serial No:</b> <input type="text" class="serialCapture" style="width:100px;" onkeydown="pageJs.keyPressInput(event, this);" onkeyup="pageJs.keyUpInput(event, this);"/></td>';
							tmp.html += 		'<td style="text-align:right; padding-right:20px;"><b>Status:</b> <select onchange=""><option value="1">Good</option><option value="2">Not Good</option></select></td>';
							tmp.html += 		'<td style="width:30px;"><input type="button" value="Go" disabled onclick="pageJs.validateRecipePart(this);" /></td>';
							tmp.html += 	'<tr>';
							tmp.html +=  '</table>';
							tmp.html += '</div>';
						}
						else
						{
							tmp.html += '<div class="recipeCaptureDiv hidden">';
							tmp.html +=  '<table border="0" style="width:100%; text-align:center; vertical-align:middle;">';
							tmp.html += 	'<tr>';
							tmp.html += 		'<td style="width:26px;">' + hpImg + '</td>';
							tmp.html += 		'<td style="width:56px; font-weight:bold;"><div class="partCode">' + ingredient.pc + '</div></td>';
							tmp.html += 		'<td style="width:26px;">' + sImg + '</td>';
							tmp.html += 		'<td style="width:220px; text-align:right;"><b>Good Qty:</b> <input type="text" style="width:50px;" class="qtyCapture" onkeydown="pageJs.keyPressInput(event, this);" onkeyup="pageJs.keyUpInput(event, this);" /></td>';
							tmp.html += 		'<td style="text-align:right; padding-right:20px;"><b>Not Good Qty:</b> <input type="text" style="width:50px;" class="qtyCapture" onkeydown="pageJs.keyPressInput(event, this);" onkeyup="pageJs.keyUpInput(event, this);"/></td>';
							tmp.html += 		'<td style="width:30px;"><input type="button" value="Go" disabled onclick="pageJs.validateRecipePart(this); "/></td>';
							tmp.html += 	'<tr>';
							tmp.html +=  '</table>';
							tmp.html += '</div>';
						}

						$('recipeDiv').insert({bottom: new Element('div', {'class':'partsMatchDiv roundedCorners hidden'}).update(tmp.html)});
					}
				}
				$('recipeDiv').insert({bottom: new Element('div', {}).update('<div style="text-align:right; padding:2px 22px 0 0;"><input type="button" id="btnFinishTest" value="Finish Test" disabled onclick="pageJs.finishTest();"/></div>')});

				$('recipeDiv').removeClassName('hidden');

				for (var piId in tmp.result.ft.parentInfo)
				{
					$('parentPiId').value = piId;
					var split = tmp.result.ft.parentInfo[piId].split(':::');
					$('parentLbl').update(split[0] + '<br />' + split[1]);
					$('parentPiInfo').value = "(" + $F('txtSerialNo') + ") " + split[0] + ' ::: ' + split[1];
					break;
				}

				if (tmp.result.ft.bomMatch == false)
				{
					$('bomMatchImg').removeClassName('hidden');
				}

				$('serialNoDiv').insert({after: $('taskStatusDiv').remove()}); //move the status box down
				$('scanKitMsg').addClassName('hidden');
				$('txtSerialNo').disabled = true;
				$('btnSerialNo').disabled = true;

				$('serialNoDiv').removeClassName('currentRow').addClassName('row');

				$('taskStatusDiv').removeClassName('row').addClassName('currentRow');
				$('taskStatusList').disabled = false;
				$('taskStatusList').focus();

				/*
				if (tmp.result.ft.recipe.length == 0)
				{
					$('btnFinishTest').disabled = false;
					$('taskStatusList').disabled = true;
					$('btnUpdateStatus').disabled = true;

					$('recipeDiv').removeClassName('row').addClassName('currentRow');
					$('btnFinishTest').focus();
				}
				else
				{
					$('taskStatusDiv').removeClassName('row').addClassName('currentRow');
					$('taskStatusList').disabled = false;
					$('taskStatusList').focus();
				}
				*/
			}
		});
	},
	toggleIndicatorBtn: function(img, pass) {
		if (pass)
		{
			img.src = "/themes/images/greenbutton.png";
		}
		else
		{
			img.src = "/themes/images/redbutton.png";
		}
	},
	validateRecipePart: function(el) {
		var tmp = {};
		tmp.pass = true;
		tmp.msg = "PASSED...";
		tmp.img = el.up(4).previous('div').down(3).next(3).down('img');
		tmp.ingredient = el.up(4).previous('input').value.evalJSON();

		tmp.recipe = $('recipe').value.evalJSON();
		for (var i=0; i<tmp.recipe.length; i++)
		{
			if (tmp.recipe[i].ptId == tmp.ingredient.ptId)
			{
				tmp.recipeIndex = i;
			}
		}
		tmp.recipe[tmp.recipeIndex].found = {};

		if (!tmp.ingredient.serialised)  										//we don't have to post any AJAX, just do the validation here
		{
			tmp.good = el.up().previous(1).down('input');
			tmp.notGood = el.up().previous().down('input');

			if (tmp.ingredient.qty > (parseInt(tmp.good.value) + parseInt(tmp.notGood.value))) 		//ie we are missing some parts
			{
				//if (tmp.ingredient.hp)											//it is a high priority part
				{
					tmp.pass = false;
					tmp.msg = "FAILED: The quantities do not add up...";
				}
			}

			tmp.recipe[tmp.recipeIndex].passed = tmp.pass;

			tmp.img.observe('mouseover', function(e) {bsuiteJs.showTooltip(e, 'toolTip', tmp.msg);});
			pageJs.toggleIndicatorBtn(tmp.img, tmp.pass);		//the indicator light img
			pageJs.hideCapturePanels();

			tmp.recipe[tmp.recipeIndex].found.good = tmp.good.value;
			tmp.recipe[tmp.recipeIndex].found.notGood = tmp.notGood.value;

			$('recipe').value = Object.toJSON(tmp.recipe);

			pageJs.validateTestPass();
		}
		else //serialised
		{
			var serialNo = el.up().previous(1).down('input').value;
			if (serialNo === '')
			{
				return alert('Please scan/enter serial no...');
			}

			bsuiteJs.postAjax(this.callbackIds.searchReturnedPart, {'ftId': $F('ftId'), 'txtSerialNo': serialNo, 'matchPartTypeId': tmp.ingredient.ptId}, {
				'onLoading': function() {
					Modalbox.show('loading', {beforeLoad: function(){Modalbox.deactivate();}, 'title': 'matching part instance, please be patient...'});
				},
				'onComplete': function (sender, param) {
					$('registerEditPanel').addClassName('hidden');
					$('registerEditLink').stopObserving('click');

					try {tmp.result = bsuiteJs.getResp(param, false, true);}
					catch(e) {
						alert(e);
						Modalbox.hide();

						if (e.indexOf('Unable to find a matching part instance for serial no') != -1)
						{
							$('registerEditLink').observe('click', function() {window.open('/registerparts'); return false;});
							$('registerEditLink').update("Register Part Instance");
							$('registerEditPanel').removeClassName('hidden');
						}
						else if (e.indexOf('Multiple part instances found for serial no') != -1)
						{
							$('registerEditLink').observe('click', function() {window.open('/reregisterparts'); return false;});
							$('registerEditLink').update("Edit Part Instance");
							$('registerEditPanel').removeClassName('hidden');
						}
						return;
					}

					if (tmp.result.pi.id === undefined || tmp.result.pi.id === null) {
						alert('System Error: part instance information missing!');
						Modalbox.hide();
						return;
					}

					Modalbox.hide();

					if (tmp.result.pi.errMsg != undefined || tmp.result.pi.errMsg != null) {
						tmp.pass = false;
						tmp.msg = "FAILED: " + tmp.result.pi.errMsg;
					}

					tmp.img.observe('mouseover', function(e) {bsuiteJs.showTooltip(e, 'toolTip', tmp.msg);});
					pageJs.toggleIndicatorBtn(tmp.img, tmp.pass);		//the indicator light img
					pageJs.hideCapturePanels();

					tmp.recipe[tmp.recipeIndex].found.piId = tmp.result.pi.id;
					tmp.recipe[tmp.recipeIndex].found.ptInfo = tmp.result.pi.ptInfo;
					tmp.recipe[tmp.recipeIndex].found.statusId = el.up().previous().down('select').value;

					tmp.sel = el.up().previous().down('select');

					tmp.recipe[tmp.recipeIndex].found.status = tmp.sel[tmp.sel.selectedIndex].text;
					tmp.recipe[tmp.recipeIndex].found.serialNo = tmp.result.pi.serialNo;
					tmp.recipe[tmp.recipeIndex].found.piId = tmp.result.pi.id;
					tmp.recipe[tmp.recipeIndex].passed = tmp.pass;
					$('recipe').value = Object.toJSON(tmp.recipe);

					pageJs.validateTestPass();
				}
			});
		}
	},
	balanceParts: function() {
		var tmp = {};
		if ($F($(this.WHHolderId)) === '')
		{
			return alert('Please select a warehouse location!');
		}

		bsuiteJs.postAjax(this.callbackIds.balanceParts, {'whId': $F($(this.WHHolderId)), 'ftId': $F('ftId'), 'piId': $F('parentPiId'), 'recipe': $F('recipe')}, {
			'onLoading': function() {
				Modalbox.show('loading', {beforeLoad: function(){Modalbox.deactivate();}, 'title': 'balancing part(s), please be patient...'});
			},
			'onComplete': function (sender, param) {
				try {tmp.result = bsuiteJs.getResp(param, false, true);}
				catch(e) {
					alert(e);
					Modalbox.hide();
					return;
				}

				if (tmp.result.ft.id === undefined || tmp.result.ft.id === null) {
					alert('System Error: field task information missing!');
					Modalbox.hide();
					return;
				}

				if (tmp.result.ft.errMsg != undefined || tmp.result.ft.errMsg != null) {
					alert(tmp.result.ft.errMsg);
					Modalbox.hide();
					return;
				}

				Modalbox.hide();

				alert('Successfully balanced, task notes added, please update the status accordingly...');

				//pageJs.bindStatusList(tmp.result.ft.nextStatuses);

				$('treePanel').addClassName('hidden');
				$('taskStatusDiv').removeClassName('row').removeClassName('hidden').addClassName('currentRow');
				$('btnUpdateStatus').disabled = false;
				$('btnUpdateStatus').focus();
			}
		});
	},
	showCapturePanel: function (el, index) {
		pageJs.hideCapturePanels(); //hides all first then shows below
		el.up('div').up('div').addClassName('currentPartsMatchDiv');
		el.up('div').addClassName('hidden');
		el.up('div').next('div').removeClassName('hidden');
		el.up('div').next('div').down('input').focus();

		$('registerEditPanel').style.top = Element.cumulativeOffset(el.up('div').up('div')).top - 294 + 'px';
		$('registerEditPanel').addClassName('hidden');
	},
	hideCapturePanels: function () {
		$$('.recipeCaptureDiv').each(function(el) {
			el.addClassName('hidden');
			el.up('div').removeClassName('currentPartsMatchDiv');
			el.previous('div').removeClassName('hidden');
		});
	},
	validateTestPass: function () {
		var disabled = false;
		$$('.recipeDisplayDiv').each(function(el) {
			if (el.down(3).next(3).down('img').src.indexOf('bluebutton') != -1)
			{
				disabled = true;
			}
		});
		$('btnFinishTest').disabled = disabled;
	},
	finishTest: function () {

		var confirmAlert = true;
		var msg = "The test has PASSED, please select a location from the tree and click 'Balance Parts'";
		var passed = true;

		$('registerEditPanel').addClassName('hidden');

		//check if the parent part match was a fail
		if ($('parentStatusImg').src.indexOf('redbutton') != -1)
		{
			passed = false;
			msg = "The test has FAILED, please update the task status accordingly.\n\nThe additional task notes will be appended to reflect the test results.";
		}
		else //check each of the recipe parts
		{
			$$('.recipeDisplayDiv').each(function(el) {
				if (el.hasClassName('hidden') == false)
				{
					if (el.down(3).next(3).down('img').src.indexOf('redbutton') != -1)
					{
						passed = false;
						msg = "The test has FAILED, please update the task status accordingly.\n\nThe additional task notes will be appended to reflect the test results.";
					}
					else if (el.down(3).next(3).down('img').src.indexOf('bluebutton') != -1)
					{
						alert('The test is not COMPLETED, please continue until all BLUE buttons are either RED or GREEN...');
						confirmAlert = false
					}
				}
				else
				{
					alert('The test is not COMPLETED, please continue until all BLUE buttons are either RED or GREEN...');
					confirmAlert = false;
				}
			});
		}

		if (confirmAlert == true)
		{
			if (confirm("Are you sure you want to finish the test?\n\n" + msg))
			{
				$('btnFinishTest').disabled = true;

				if (passed) 		//we need to display the tree
				{
					$('treePanel').removeClassName('hidden').removeClassName('row').addClassName('currentRow');
					$('balancePanel').removeClassName('row').addClassName('currentRow');

					$('taskStatusDiv').removeClassName('currentRow').addClassName('row');
					$('taskStatusList').disabled = false;
					$('taskStatusList').focus();
				}
				else 				//display the task status div
				{
					$('recipeDiv').insert({after: $('taskStatusDiv').remove()}); //move the status box down

					$('taskStatusDiv').removeClassName('row').addClassName('currentRow');
					$('taskStatusList').disabled = false;
					$('taskStatusList').focus();
				}
				$('recipeDiv').removeClassName('currentRow').addClassName('row');

				$$('.recipeDisplayDiv').each(function(el) {
					el.down(3).next(3).down('img').cursor = 'default';
					el.down(3).next(3).down('img').onclick = null;
				});
			}
		}
	},
	keyPressInput: function (event, el) {
		//if it's not a enter key, then return true;
		if(!((event.which && event.which == 13) || (event.keyCode && event.keyCode == 13)))
		{
			return true;
		}

		if (el.id == 'txtTaskNo')									//task number input
		{
			if (el.value == '')
			{
				alert('Please enter a Task No / Client Ref!')
				return;
			}
			$('btnTaskNo').click();
		}
		else if (el.id == 'txtSerialNo')							//this is the part returned serial field
		{
			if (el.value == '')
			{
				alert('Please scan/enter a serial no...');
				return;
			}
			$('btnSerialNo').click();
		}
		else if (el.hasClassName("serialCapture"))					//we're scanning a serialised part in the parts list
		{
			if (el.value == '')
			{
				alert('Please scan/enter a serial no...');
				return;
			}
			el.up().next(1).down('input').click();
		}
		else if (el.hasClassName("qtyCapture"))						//we're entering a non-serialised qty in the parts list
		{
			if (!el.value.match(/^[0-9][0-9]*$/))
			{
				alert('Please enter a valid quantity...');
				return;
			}

			var btn = el.up().next().down('input');
			if (btn && btn.type == 'button')						//this means we are on the Not Good Qty
			{
				btn.click();										//click the button
			}
			else													//we're on the Good Qty
			{
				el.up().next().down('input').focus();				//so focus to the Not Good
			}
		}
	},
	keyUpInput: function (event, el) {
		if(!((event.which && event.which == 13) || (event.keyCode && event.keyCode == 13))) 	//if it's not a enter key
		{
			if (el.hasClassName("serialCapture"))												//we're in the serialised part field
			{
				if (el.value == '')
				{
					el.up().next(1).down('input').disabled = true;
				}
				else
				{
					el.up().next(1).down('input').disabled = false;
				}
			}
			else if (el.hasClassName("qtyCapture"))												//we're in the non-serialised qty
			{
				var btn = el.up().next().down('input');
				if (btn && btn.type == 'button')												//this means we are on the Not Good Qty
				{
					var good = el.up().previous().down('input');
					var notGood = el;
				}
				else																			//we're on the Good Qty
				{
					var good = el;
					var notGood = el.up().next().down('input');
					var btn = el.up().next(1).down('input');
				}

				if (good.value.match(/^[0-9][0-9]*$/) && notGood.value.match(/^[0-9][0-9]*$/))
				{
					btn.disabled = false;
				}
				else
				{
					btn.disabled = true;
				}
			}
		}
	},
	modal: function () {
		var tmp = {};
		tmp.html = '<div process="header">Processing: <span process="currIndex">1</span> / <span process="total">dszhgxdgh</span> part instance(s)</div>';
		tmp.html += '<div process="errors" style="height: 400px;overflow: auto;">';
			tmp.html += '<table class="DataList">';
				tmp.html += '<thead><tr><td>Barcode</td><td>Error</td></tr></thead><tbody></tbody>';
			tmp.html += '</table>';
		tmp.html += '</div>';
		Modalbox.show(new Element('div').update(tmp.html), {beforeLoad: function(){},
			afterLoad: function(){},
			transitions: false,
			'title': '<b class="manD">Processing Part Instances, please DO NOT close or REFRESH the browser!</b>'
		});
	},
	resetPage: function() {
		if (confirm("Are you sure you want to reload the page, all data will be lost?"))
		{
			location.reload();
		}
	}
};