//this is the source file for the FieldTaskListController
var CreatePIJS = new Class.create();
CreatePIJS.prototype = {
	errors: '',	//hold errors
	WHHolderId: '', //the selected warehouse holder html id
	openNewWindowParams: 'width=750, menubar=0, toolbar=0, status=0, scrollbars=1, resizable=1', //the default value for open a new window
	maxQty: 100, //The max quantity that the user can register at one time
	closeAfterSave: false,
	requestData: {
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
	initialize: function (WHHolderId, selectPTCallbackId, maxQty, checkSN, checkWH, registerPI,showPattern) {
		this.WHHolderId = WHHolderId;
		this.callbackIds.selectPT = selectPTCallbackId;
		this.callbackIds.checkSN = checkSN;
		this.callbackIds.checkWH = checkWH;
		this.callbackIds.registerPI = registerPI;
		this.callbackIds.showPattern = showPattern;
		this.maxQty = maxQty;

		this.aliasTypes = this.getAliasTypes();
	},
	getAliasTypes: function() {
		var tmp = {};
		tmp.aliasTypes = {};
		tmp.list = $$('select[aliases=aliastypeList]').first();
		for(tmp.i = 0; tmp.i < tmp.list.options.length; tmp.i += 1) {
			tmp.value = tmp.list.options[tmp.i].value.strip();
			if (tmp.value !== '') {
				tmp.aliasTypes[tmp.value] = tmp.list.options[tmp.i].text;
			}
		}
		return tmp.aliasTypes;
	},
	//toggle Has Purchase Order
	toggleHasPO: function (clickedBtn, poBox, ptBox, poLabel, regPtId) {
		if ($(clickedBtn).checked) {
			$(poBox).style.backgroundColor = '#AADDFF';
			$(poBox).removeClassName('disabled').disabled = false;
			$(poLabel).show();
			$(poBox).select();

			$(ptBox).style.backgroundColor = '';
			$(ptBox).addClassName('disabled').disabled = true;
		} else {
			$(poBox).style.backgroundColor = '';
			$(poBox).value = '';
			$(poBox).addClassName('disabled').disabled = true;

			$(ptBox).style.backgroundColor = '#AADDFF';
			$(ptBox).removeClassName('disabled').disabled = false;
			$(poLabel).update('').hide();
			$(ptBox).select();
		}
		pageJs.clearBeforeSelectPT();

		if ($(regPtId))
		{
			if ($(regPtId).value !== '')
			{
				$$('[fieldids=parttype]').first().disabled = true;
				this.populateSelectedPT($(regPtId).value)
			}
		}
	},
	addAlias: function (clickedBtn, manTypeId, extraAliasTypeId,poAliasTypeId) {
		var tmp = {};

		//if it's called to remove one row
		tmp.wrapper = $(clickedBtn).up('[aliases=wrapper]');

		if (tmp.wrapper.hasClassName('added')) {
			tmp.selectedAliasTypeId = $F(tmp.wrapper.down('[aliases=aliastypeid]'));
			if(tmp.selectedAliasTypeId !== undefined && !tmp.selectedAliasTypeId.blank()) {
				$$('[aliases=wrapper] select option[value=' +tmp.selectedAliasTypeId + ']').each(function(option){
					option.show().disabled = false;
				});
			}
			return tmp.wrapper.remove();
		}

		tmp.aliasTypeId = manTypeId;

		//we're adding an extra alias type id, slightly different
		if (manTypeId === false && poAliasTypeId === false && extraAliasTypeId !== undefined && !extraAliasTypeId.blank())
		{
			tmp.aliasTypeId = extraAliasTypeId;
		}
		else if (manTypeId === false && extraAliasTypeId === false && poAliasTypeId !== undefined && !poAliasTypeId.blank())
		{
			tmp.aliasTypeId = poAliasTypeId;
		}

		//if we are populating a mandatory alias row
		if(tmp.aliasTypeId !== undefined && !tmp.aliasTypeId.blank()) {
			$$('[aliases=wrapper] select option[value=' + tmp.aliasTypeId + ']').each(function(option){
				option.hide().disabled = true;
			});
		}

		//else it's called to add one row
		tmp.newWrapper = new Element('div', {'aliases': 'wrapper', 'class': 'row added'}).update(tmp.wrapper.innerHTML);
		tmp.aliasBoxHolder = tmp.newWrapper.down('[fieldids=serialNo]').writeAttribute('fieldids', 'alias').writeAttribute('id', '');
		tmp.aliasBoxHolder.disabled = false;
		tmp.aliasBoxHolder.value = '';
		tmp.newWrapper.down('[aliases=aliastype]').remove();
		tmp.newWrapper.down('[fieldids=serialNoValidator]').remove();
		tmp.typeList = tmp.newWrapper.down('[aliases=aliastypeList]').writeAttribute('aliases', 'aliastypeid').observe('change', function(){pageJs.changeAliasType(this);}).observe('focus', function(){$(this).writeAttribute('previousValue', $F(this));}).show();

		tmp.pattern = '';
		//if we are populating a mandatory alias row
		if(tmp.aliasTypeId !== undefined && tmp.aliasTypeId !== '')
		{
			bsuiteJs.postAjax(this.callbackIds.showPattern, {'partTypeId': this.requestData.pt.id, 'typeId': tmp.aliasTypeId}, {
				'onLoading': function() {
				},
				'onComplete': function (sender, param) {
					tmp.result = bsuiteJs.getResp(param);
					if(tmp.result !== null && tmp.result !== undefined)
					{
						tmp.pattern = tmp.result['aliasPattern'];
						tmp.regex = tmp.result['regex'];
						tmp.format = tmp.result['format'];
						if (tmp.pattern != '')
							tmp.newWrapper.down('[fieldids=aliasPattern]').writeAttribute('pattern', tmp.pattern).writeAttribute('regex', tmp.regex).writeAttribute('format', tmp.format).update('Valid Pattern: '+tmp.pattern);
						else
							tmp.newWrapper.down('[fieldids=aliasPattern]').writeAttribute('pattern', '').writeAttribute('regex', '').writeAttribute('format', tmp.format);
					}
				}
			});

			//we're adding an extra alias type
			if (manTypeId === false)
			{
				tmp.newWrapper.down('[aliases=boxLabel]').writeAttribute('aliases', 'keepAlias').update('');
				tmp.newWrapper.down('[aliases=keepAlias]').observe('mouseover', function(event){bsuiteJs.showTooltip(event, 'toolTip', 'Tick to keep this label for next registration<br />without leaving this page.');});
				//tmp.newWrapper.down('[aliases=keepAliasCheckBox]').checked = true;
				//tmp.newWrapper.down('[aliases=keepAliasCheckBox]').disabled = true;
				if (poAliasTypeId !== false)
					tmp.newWrapper.down('[aliases=moreOrLess]').insert({before: '<b class="manD">*</b>'}).remove();
				else
					tmp.newWrapper.down('[aliases=moreOrLess]').update('<img src="/themes/images/delete.png" style="display:inline-block;width:15px;"/>').show();
			}
			else
			{
				tmp.newWrapper.down('[aliases=boxLabel]').writeAttribute('aliases', 'keepAlias').update('');
				tmp.newWrapper.down('[aliases=moreOrLess]').insert({before: '<b class="manD">*</b>'}).remove();
			}

			//tmp.newWrapper.down('[aliases=moreOrLess]').insert({before: '<b class="manD">*</b>'}).remove();
			if (tmp.pattern !='')
				tmp.newWrapper.down('[fieldids=aliasPattern]').writeAttribute('pattern', tmp.pattern).update('Valid Pattern: '+tmp.pattern);

			tmp.typeList.disabled = true;
			tmp.options = tmp.typeList.options;
			for(tmp.i = 0; tmp.i < tmp.options.length; tmp.i += 1) {
				if (tmp.options[tmp.i].value === tmp.aliasTypeId) {
					tmp.options[tmp.i].selected = true;
				}
			}
		}
		else
		{
			tmp.newWrapper.down('[aliases=moreOrLess]').update('<img src="/themes/images/delete.png" style="display:inline-block;width:15px;"/>').show();
			tmp.newWrapper.down('[aliases=boxLabel]').writeAttribute('aliases', 'keepAlias').update('<input type="checkbox" aliases="keepAliasCheckBox"/>');
			tmp.newWrapper.down('[aliases=keepAlias]').observe('mouseover', function(event){bsuiteJs.showTooltip(event, 'toolTip', 'Tick to keep this label for next registration<br />without leaving this page.');});
			tmp.newWrapper.down('[fieldids=aliasPattern]').writeAttribute('pattern', '').writeAttribute('regex', '').writeAttribute('format', '');
		}

		//insert into dom
		if($$('.added[aliases=wrapper]').size() === 0)
		{
			tmp.wrapper.insert({after: tmp.newWrapper});
		}
		else
		{
			$$('.added[aliases=wrapper]').last().insert({after: tmp.newWrapper});
		}
	},
	removeAlias: function (clickedBtn) {
		var tmp = {};
		tmp.oldValue = $(clickedBtn).value;
		$(clickedBtn).value = 'Removing Selected PIs ...';
		$(clickedBtn).disabled = true;

		//collect which sn has been selected
		tmp.newPIs = this.requestData.pis;
		if ($$('[aliases="pilist"]').first().down('tbody') === undefined)
		{
			$(clickedBtn).value = tmp.oldValue;
			$(clickedBtn).disabled = false;
			return;
		}
		$$('[aliases="pilist"]').first().down('tbody').getElementsBySelector('tr').each(function(item) {
			tmp.checked = item.down('input[pislist="checkbox"]').checked;
			tmp.sn = item.readAttribute('sn').strip();
			if (tmp.checked === true && Object.keys(tmp.newPIs).indexOf(tmp.sn) !== -1)
				delete tmp.newPIs[tmp.sn];
		});
		this.requestData.pis = tmp.newPIs;
		this.displayScannedPIs();

		$(clickedBtn).value = tmp.oldValue;
		$(clickedBtn).disabled = false;
	},
	changeAliasType: function(listBox) {
		var tmp = {};
		//if the selected one is allow multiple, then just let it through
		if( $(listBox).options[$(listBox).selectedIndex].readAttribute('allowmulti') === '1') {
			if (!listBox.readAttribute('previousValue').blank()) {
				$$('[aliases=wrapper] select option[value=' + listBox.readAttribute('previousValue') + ']').each(function(selectedOption) {
					selectedOption.show().disabled = false;
				});
			}
			return;
		}

		tmp.notMultiAliasTypeSelected = null;
		$$('.added[aliases=wrapper] [aliases=aliastypeid]').each(function(selectBox) {
			if (listBox !== selectBox && $F(selectBox) === $F(listBox))
				tmp.notMultiAliasTypeSelected = $F(listBox);
		});

		if (tmp.notMultiAliasTypeSelected !== null) {
			alert('You can NOT add multiple ' + this.aliasTypes[tmp.notMultiAliasTypeSelected] + ' to this part!');
			$(listBox).selectedIndex = 0;
			$(listBox).writeAttribute('previousValue', $F(listBox));
		} else {
			$$('[aliases=wrapper] select option[value=' + $F(listBox) + ']').each(function(selectedOption) {
				if(listBox !== selectedOption.up('select')) {
					selectedOption.hide().disabled = true;
				}
			});
		}
	},
	selectPT: function (autoCompleteResult) {
		var tmp = {};
		$(autoCompleteResult).getElementsBySelector('li').each(function(item) {
			if(item.hasClassName('selected') === true)
				tmp.ptId = item.value;
		});
		this.populateSelectedPT(tmp.ptId)
	},
	populateSelectedPT: function (ptId) {
		var tmp = {};
		bsuiteJs.postAjax(this.callbackIds.selectPT, {'partTypeId': ptId, 'poId': this.requestData.po.id}, {
			'onLoading': function() {
				Modalbox.show('loading', {beforeLoad: function(){Modalbox.deactivate();}, 'title': 'Retrieving Part Type Information ...'});
			},
			'onComplete': function (sender, param) {
				tmp.result = bsuiteJs.getResp(param);
				if(tmp.result !== null && tmp.result !== undefined)
					pageJs.afterSelectPT(tmp.result);
			}
		});
	},
	clearBeforeSelectPT: function () {
		//clear selected part type
		this.requestData.pt = {};

		//clear all scanned part instances
		this.requestData.pis = {};
		this.displayScannedPIs();

		//show all options again
		$$('[aliases="aliastypeList"]').first().getElementsBySelector('option').each(function(item){
			item.show();
		});

		$$('[fieldids=parttype]').first().value = '';
		$$('[fieldids=contracts]').first().update('');
		$$('[fieldids=statusList]').first().update('');
		$$('[fieldids=serialNoValidator]').first().update('');
		$$('[fieldids=aliasPattern]').first().update('');
		$$('[fieldids=owner]').first().writeAttribute('value', '').update('');
		$$('[fieldids=qty]').first().addClassName('disabled').value = '';
		$$('[fieldids=qty]').disabled = false;
		$$('[fieldids=serialNo]').first().value = '';
		$$('[fieldids=serialNo]').disabled = true;
		$('PiCount').update('0');

		//clear all the added aliases row
		$$('.added[aliases=wrapper]').each(function(item){item.remove();});

		//hide all after select PT elements
		$$('.loadedAfterPT').each(function(item){item.hide();});
		$$('[fieldids="pendingparts"]').each(function(item){item.hide();});
	},
	afterSelectPT: function (result) {
		var tmp = {};

		//popluate the part type id
		this.requestData.pt = result.partType;
		tmp.partText = result.partType.partcode + ': ' + result.partType.name;
		if(this.requestData.pt.kitTypeId !== undefined && this.requestData.pt.kitTypeId !== '')
			return Modalbox.show(new Element('div', {'style': 'color:red; font-weight:bold;'}).update('Part Type(' + tmp.partText + ') has a Kit Type. To Register Kits, please use <a href="/buildkits"/ style="color:blue;"><u>Build Kits</u></a> </hr>page.'), {beforeLoad: function(){Modalbox.activate();}, title: 'Kit Type Error!'});

		//update the pending info
		$$('[fieldids=pendingparts]').first().update(result.pendingPT);

		$$('.loadedAfterPT').each(function(item){item.show();});
		//show or hide the pendding parts information
		$$('[fieldids="pendingparts"]').each(function(item){
			if($('showPendingPartInfo').checked) item.show();
			else item.hide();
		});

		$$('[fieldids=parttype]').first().value = tmp.partText;
		if ($('showPendingPartInfo').checked)
			$$('[fieldids=pendingparts]').first().show();
		if(result.partType.barcodeValidator !== undefined && result.partType.barcodeValidator !== null)
			$$('[fieldids=serialNoValidator]').first().writeAttribute('regex', result.partType.barcodeValidator.regex).update('Valid Pattern: ' + result.partType.barcodeValidator.pattern);
		$$('[fieldids=aliasPattern]').first().update('');
		tmp.qtyHolder = $$('[fieldids=qty]').first();
		tmp.snHolder = $$('[fieldids=serialNo]').first();
		if (result.partType.serialised === true) {
			$$('[aliases=pilist]').each(function(item){item.show();});
			//show box label
			$$('[aliases="boxLabel"]').each(function(item){item.show();})
			tmp.qtyHolder.value = '1';
			tmp.qtyHolder.addClassName('disabled').disabled = true;
			tmp.snHolder.value = '';
			tmp.snHolder.disabled = false;
			result.partType.manAliasTypeIds.each(function(manAliasTypeId)
			{
				if(!manAliasTypeId.blank())
				{
					pageJs.addAlias($$('[aliases=wrapper]').first().down('[aliases=moreOrLess]'), manAliasTypeId,false,false);
				}
			});
			result.partType.extraAliasTypeIds.each(function(extraAliasTypeId)
			{
				if(!extraAliasTypeId.blank())
				{
					pageJs.addAlias($$('[aliases=wrapper]').first().down('[aliases=moreOrLess]'), false, extraAliasTypeId,false);
				}
			});
			result.partType.poAliasTypeIds.each(function(item)
			{
						if(!item.blank())
						{
							pageJs.addAlias($$('[aliases=wrapper]').first().down('[aliases=moreOrLess]'), false, false,item);
						}
			});
			tmp.snHolder.select();
		} else {
			$$('[aliases=pilist]').each(function(item){item.hide();});
			//hide box label
			$$('[aliases="boxLabel"]').each(function(item){item.hide();})
			tmp.qtyHolder.removeClassName('disabled').value = '';
			tmp.qtyHolder.disabled = false;
			tmp.snHolder.value = result.partType.bp;
			tmp.snHolder.disabled = true;
		}

		//populate contracts
		tmp.html = '';
		if( Object.prototype.toString.call(result.contracts) === '[object Array]' ) {
			result.contracts.each(function(contract){
				tmp.html += '<div value="' + contract.id + '">' + contract.name + '</div>';
			});
		}
		$$('[fieldids=contracts]').first().update(tmp.html);

		//populate statuses
		tmp.html = '';
		if( Object.prototype.toString.call(result.statuses) === '[object Array]' ) {
			result.statuses.each(function(status){
				tmp.html += '<option value="' + status.id + '" ' + (status.selected === true ? 'selected' : '') + '>' + status.name + '</option>';
			});
		}
		$$('[fieldids=statusList]').first().update(tmp.html);

		//populate owner
		$$('[fieldids=owner]').first().writeAttribute('value', result.owner.id).update(result.owner.name);

		//did we generate the WH tree already
		if(tree === undefined || tree === null) {
			loadTree();
		} else {
			Modalbox.hide();
		}
	},
	validateBarcode: function(barcode) {
		var tmp = {};
		if (barcode === undefined || Object.prototype.toString.call(barcode) !== "[object String]" || barcode.blank())
			return;

		tmp.barcodeValidator = this.requestData.pt.barcodeValidator;
		if (tmp.barcodeValidator === undefined || tmp.barcodeValidator.regex === undefined || tmp.barcodeValidator.regex === null || tmp.barcodeValidator.regex.blank())
			return;

		tmp.validator = new bsuiteBarcode(tmp.barcodeValidator.regex);
		tmp.validator.validateBarcode(barcode);
	},
	addPI: function(clickedBtn) {
		var tmp = {};
		if (this.requestData.pt.id === undefined || this.requestData.pt.id === null)
			return alert('Please select a part type first!');

		//validate the barcode
		tmp.snHolder = $$('[fieldids=serialNo]').first();
		tmp.serialNo = $F(tmp.snHolder).strip().toUpperCase();
		if (tmp.serialNo === '') {
			tmp.snHolder.select();
			return alert('Serial number is needed!');
		}
		try {this.validateBarcode(tmp.serialNo);} catch(e) {
			tmp.snHolder.select();
			return alert(e);
		}

		//checking whether our scanned list has that serial number already
		if (Object.keys(this.requestData.pis).indexOf(tmp.serialNo) !== -1) {
			tmp.snHolder.select();
			return alert('You have added this part(=' + tmp.serialNo + ') onto your list already!');
		}

		//initialise the request data for that serial number
		tmp.qtyHoder = $$('[fieldids=qty]').first();
		tmp.statusList = $$('[fieldids=statusList]').first();
		if (!$F(tmp.qtyHoder).strip().match(/^\d+$/) || parseInt($F(tmp.qtyHoder)) === 'NaN') {
			tmp.qtyHoder.select();
			return alert('Invalid qty(=' + $F(tmp.qtyHoder) + ')!');
		}

		//collects all the aliases content
		tmp.newPi = {'qty': parseInt($F(tmp.qtyHoder)), 'status': {'id': $F(tmp.statusList), 'name': tmp.statusList.options[tmp.statusList.selectedIndex].text}, 'aliases': {}};
		tmp.errors = [];
		tmp.aliasTypeIdsThatUsedLastTime = [];
		$$('.added[aliases="wrapper"]').each(function(item) {
			tmp.typeList = item.down('[aliases="aliastypeid"]');
			tmp.aliasContent = $F(item.down('[fieldids=alias]')).strip();

			if (tmp.aliasRegex = (item.down('[fieldids="aliasPattern"][regex]')).readAttribute('regex'))
				{
				tmp.aliasPattern = (item.down('[fieldids="aliasPattern"][pattern]')).readAttribute('pattern');
				tmp.aliasFormat = (item.down('[fieldids="aliasPattern"][format]')).readAttribute('format');
				}

			tmp.manAlias = item.down('[class = manD]');

			tmp.aliasTypeId = $F(tmp.typeList).strip();
			if (tmp.aliasTypeId === '')
				tmp.errors.push('Please select a alias type!');
			else if ((tmp.aliasContent === '')&&(tmp.manAlias))
			{
				tmp.errors.push(tmp.typeList.options[tmp.typeList.selectedIndex].text + ': NONE is provided! <br /> '+tmp.aliasFormat);
			}

			if ((tmp.aliasRegex != '')&&(tmp.aliasContent!==''))
			{
				if (!tmp.aliasContent.match(tmp.aliasRegex))
				{
					item.down('[fieldids=alias]').style.color='red';
					//item.down('[fieldids=alias]').focus();
					tmp.errors.push('Format of alias '+tmp.typeList.options[tmp.typeList.selectedIndex].text+' is incorrect! <br /> Format should be '+tmp.aliasPattern);
				}
				else
				{
					item.down('[fieldids=alias]').style.color='black';
				}

			}

			//hold the the alias types that user selected
			if (tmp.aliasTypeIdsThatUsedLastTime.indexOf(tmp.aliasTypeId) === -1) {
				tmp.aliasTypeIdsThatUsedLastTime.push(tmp.aliasTypeId);
			}

			if(tmp.newPi.aliases[tmp.aliasTypeId] === undefined || tmp.newPi.aliases[tmp.aliasTypeId] === null)
				tmp.newPi.aliases[tmp.aliasTypeId] = [];
			tmp.newPi.aliases[tmp.aliasTypeId].push(tmp.aliasContent);

			//enabled these alias box
			$$('.added[aliases="wrapper"]').each(function(item){item.down('[fieldids=alias]').disabled = true;});
		});

		if (tmp.errors.size() > 0) {
			//enabled these alias box
			$$('.added[aliases="wrapper"]').each(function(item){item.down('[fieldids=alias]').disabled = false;});
			return alert( 'Error: \n\n - ' + tmp.errors.join('\n - '));
		}

		//adding the PI to the request Data
		if (this.requestData.pt.serialised !== true) { //if this is non serialised part, then don't check any alias
			this.requestData.pis[tmp.serialNo] = tmp.newPi;
			pageJs.displayScannedPIs();
			$$('.added[aliases="wrapper"]').each(function(item){item.down('[fieldids=alias]').disabled = false;});
		} else { //if this is a serialised part, then check the unique aliases and serial number
			//checking all Unique aliases
			try {this.checkingUniqueAlias(tmp.newPi.aliases);} catch(e) {
				//enabled these alias box
				$$('.added[aliases="wrapper"]').each(function(item){item.down('[fieldids=alias]').disabled = false;});
				return alert(e);
			}
			//marking the add pi button to be disabled
			tmp.oldBtnValue = $(clickedBtn).value;
			$(clickedBtn).disabled = true;
			$(clickedBtn).value = 'Validate Aliases ...';
			tmp.requestData = {'partTypeId': this.requestData.pt.id, 'serialNo': tmp.serialNo, 'aliases': tmp.newPi.aliases, 'printBoxLabel': $$('[aliases="boxLabel"]').first().down('input').checked};
			bsuiteJs.postAjax(this.callbackIds.checkSN, tmp.requestData, {
				'onComplete': function (sender, param) {
					try {
						tmp.result = bsuiteJs.getResp(param);
						if (tmp.result !== undefined && tmp.result.aliases !== undefined && tmp.result.aliases !== null) {
							tmp.newPi.qty = '1';
							if( Object.prototype.toString.call(tmp.result.aliases) === '[object Array]' && tmp.result.aliases.size() === 0)
								tmp.newPi.aliases = {};
							else
								tmp.newPi.aliases = tmp.result.aliases;
							pageJs.requestData.pis[tmp.serialNo] = tmp.newPi;
							pageJs.displayScannedPIs();

							tmp.aliasTextBoxes = $$('[aliases="wrapper"] [aliases="alias"] input');
							tmp.aliasTextBoxes.each(function(item) {
								tmp.keepCheckBox = item.up('[aliases="wrapper"]').down('[aliases="keepAliasCheckBox"]');
								if(tmp.keepCheckBox === undefined || tmp.keepCheckBox === null || tmp.keepCheckBox.checked !== true) {
									item.value = '';
								}
							});
							tmp.aliasTextBoxes.first().select();
						}
					} catch (e) {
						alert(e);
					}
					//resuming the add pi button
					$(clickedBtn).value = tmp.oldBtnValue;
					$(clickedBtn).disabled = false;

					//enabled these alias box
					$$('.added[aliases="wrapper"]').each(function(item){item.down('[fieldids=alias]').disabled = false;});
				}
			});
		}
	},
	checkingUniqueAlias: function(newaliases) {
		var tmp = {};
		//if there none mandatory alias type set for that part type
		if(this.requestData.pt.manUniqueAliasTypeIds.size() === 0)
			return;

		// check against the parts that we've already scanned
		tmp.aliasTypeTranslator = this.aliasTypes;
		tmp.manUniqueAliasTypeIds = this.requestData.pt.manUniqueAliasTypeIds

		tmp.scannedPIs = this.requestData.pis;
		tmp.duplidatedAlias = [];
		$H(newaliases).each(function (item) {
			tmp.typeId = item.key.strip();


			//the alias type is a mandatory one, else don't care
			if (tmp.manUniqueAliasTypeIds.indexOf(tmp.typeId) !== -1) {
				Object.keys(tmp.scannedPIs).each(function(scannedSN) {
					tmp.scannedAliases = tmp.scannedPIs[scannedSN]['aliases'][tmp.typeId];
					if( Object.prototype.toString.call( tmp.scannedAliases ) !== '[object Array]' )
						throw 'System Erorr: scanned aliases structure messed up!';
					tmp.scannedAliases.each(function(alias) {
						if(item.value.indexOf(alias) !== -1)
							tmp.duplidatedAlias.push(tmp.aliasTypeTranslator[tmp.typeId] + '(=' + alias.strip() + ') for part:' + scannedSN);
					});
				});
			}
		});
		if (tmp.duplidatedAlias.size() > 0)
			throw "There is/are unique alias found on the scanned part list:\n\n - " + tmp.duplidatedAlias.join("\n - ");
	},
	displayScannedPIs: function() {
		var tmp = {};
		$$('[aliases="aliasList"]').first().update('');
		$('PiCount').update('0');
		if(Object.keys(this.requestData.pis).size() === 0)
			return;

		$('PiCount').update(Object.keys(this.requestData.pis).size());
		tmp.pis = this.requestData.pis; tmp.aliasTypeIds = [];
		$H(tmp.pis).each(function(item){
			$H(item.value.aliases).each(function(value) {
				if(tmp.aliasTypeIds.indexOf(value.key) === -1)
					tmp.aliasTypeIds.push(value.key);
			});
		});
		tmp.aliasTypeIds.uniq();

		tmp.aliasTypeTranslator = this.aliasTypes;
		tmp.html = '<table class="DataList">';
			tmp.html += "<thead><tr><td><input type='checkbox' pislist='checkbox' onclick=\"pageJs.checkAllPIs(this);\"/></td><td>S/N</td><td>Qty</td><td>Status</td>";
			tmp.aliasTypeIds.each(function(typeId) {
				tmp.html += '<td>' + tmp.aliasTypeTranslator[typeId] + '</td>';
			});
			tmp.html += '</tr></thead><tbody>';

			tmp.rowNo = 0;
			$H(tmp.pis).each(function (item) {
				tmp.html += '<tr class="' + (tmp.rowNo % 2 === 0 ? 'DataListItem' : 'DataListAlterItem') + '" sn="' + item.key + '">';
					tmp.html += '<td><input type="checkbox" pislist="checkbox"/></td>';
					tmp.html += '<td pislist="sn">' + item.key + '</td>';
					tmp.html += '<td>' + item.value.qty + '</td>';
					tmp.html += '<td>' + item.value.status.name + '</td>';
					tmp.aliasTypeIds.each(function(typeId) {
						tmp.value = '';
						if (item.value.aliases[typeId] !== undefined && item.value.aliases[typeId] !== null) {
							tmp.value = '<div class="multialias"><div>' + (item.value.aliases[typeId].size() > 1 ? (' - ' + item.value.aliases[typeId].join('</div><div> - ')) : item.value.aliases[typeId].join('')) + '</div>';
						}
						tmp.html += '<td>' + tmp.value  + '</td>';
					});
				tmp.html += '</tr>';
				tmp.rowNo += 1;
			});
		tmp.html += '</tbody></table>';
		$$('[aliases="aliasList"]').first().update(tmp.html);
	},
	//do enter on alias box //refocus to the next blank alias
	focusToNextBlankAlias: function (event) {
		//if it's not a enter key, then return true;
		if(!((event.which && event.which == 13) || (event.keyCode && event.keyCode == 13)))
			return true;

		var tmp = {};
		tmp.foundBlank = false;
		$$('[aliases=wrapper] [aliases=alias] input').each(function (item) {
			if (tmp.foundBlank === false && item.value.blank()) {
				item.select();
				tmp.foundBlank = true;
				return;
			}
		});
		if(tmp.foundBlank === false)
		{
			$$('[aliases="pilist"] [aliases=addPIBtn]').first().click();
		}
	},
	printPendingParts: function(clickedBtn) {
		var tmp = {};
		tmp.newDiv = new Element('div').update('<div class="row" fieldids="pendingparts">' + $(clickedBtn).up('[fieldids="pendingparts"]').innerHTML + '</div>');
		tmp.newDiv.down('table').writeAttribute('style', "width:100%;").writeAttribute('border', '1').insert({before: '<a style="float:right;" href="javascript: void(0);" onclick="window.print();">Print</a>'});
		tmp.newDiv.down('input.printBtn').remove();
		tmp.html = '<head><title>Printing Pending Parts</title></head><body onload="window.print();">' + tmp.newDiv.innerHTML + '</body>';
		this.writeConsole(tmp.html, 'PrintingWindow');
	},
	checkAllPIs: function(checkbox) {
		$(checkbox).up('table').down('tbody').getElementsBySelector('tr').each(function(item) {
			item.down('[pislist="checkbox"]').checked = $(checkbox).checked;
		});
	},
	registerPIs: function() {
		var tmp = {};
		document.getElementById('errors').innerHTML = '';
		if (this.requestData.pt.id === undefined || this.requestData.pt.id === null)
			return alert('Please select a part type first!');

		//if this is a non-serialised parts
		if(pageJs.requestData.pt.serialised === false) {
			this.requestData.pis = {};
			$$('[aliases="pilist"] [aliases=addPIBtn]').first().click();
			if (Object.keys(this.requestData.pis).size() === 0)
				return; //TODO: need to notify the user, but don't know what to say to the user here!
		} else { //if it's serialised
			//last check for all the serial number, just in case it's cached before.
			tmp.error = '';
			Object.keys(this.requestData.pis).each(function (serialNo) {
				try {pageJs.validateBarcode(serialNo);} catch(e) {
					tmp.error += '\n - ' + e;
				}
			});
			if(!tmp.error.blank())
				return alert('Invalid Barcode in your list: ' + tmp.error);
		}

		if (Object.keys(this.requestData.pis).size() === 0) {
			alert('Please add some part instances first!');
			Modalbox.hide();
			return;
		}

		tmp.html = '<div process="header">Processing: <span process="total">' + Object.keys(pageJs.requestData.pis).size() + '</span> part instance(s)</div>';

		Modalbox.show(new Element('div').update(tmp.html), {beforeLoad: function(){Modalbox.deactivate();},
			afterLoad: function(){Modalbox.resizeToContent();},
			'title': '<b class="manD">Processing Part Instances, please DO NOT close or REFRESH the browser!</b>',
			width: 700
		});

		//process scanned part instances
		//checking the Warehouse
		bsuiteJs.postAjax(this.callbackIds.checkWH, {'warehouseId': $F($(this.WHHolderId))}, {
			'onComplete': function (sender, param) {
				try {tmp.result = bsuiteJs.getResp(param, false, true);}
				catch(e) {
					alert('Error Occurred: ' + e);
					Modalbox.hide();
					return;
				}
				if(tmp.result.warehouse.id === undefined || tmp.result.warehouse.id === null) {
					alert('System Error: warehouse information missing!');
					Modalbox.hide();
					return;
				}

				//blocking the UI
				//tmp.html = '<h3>Start to register part instance(s) under: ' + tmp.result.warehouse.breadcrumbs + '</h3>';

				pageJs.requestData.whId = tmp.result.warehouse.id;
				pageJs.processPIs();
			}
		});
	},
	resetErrors: function(){
		this.errors = '';
	},
	setErrors: function(error){
		this.errors += error;
		return this.errors;
	},
	processPIs: function (currentIndex) {
		var tmp = {};
		tmp.me = this;
		tmp.errors = this.errors;


		tmp.currentIndex = (currentIndex === undefined || currentIndex === null) ? 1 : currentIndex;
		tmp.keys = Object.keys(this.requestData.pis);
		tmp.serialNo = tmp.keys[tmp.currentIndex - 1];
		tmp.requestData = {'ptId': this.requestData.pt.id, 'po': this.requestData.po, 'whId': this.requestData.whId, 'pis': {}};
		tmp.requestData.pis[tmp.serialNo] =  this.requestData.pis[tmp.serialNo];
		bsuiteJs.postAjax(this.callbackIds.registerPI, tmp.requestData, {
			'onComplete': function (sender, param) {
				tmp.error = '';
				try {tmp.result = bsuiteJs.getResp(param, false, true);} catch(e) { tmp.error = e; }

				//if there is an error
				if (tmp.error !== '') {
					tmp.errors = pageJs.setErrors(tmp.error + '<br>');
				}
				//success, and no more to process
				if (tmp.currentIndex >= tmp.keys.size()) {
					//all done with no errors
					if (tmp.errors == '')
					{
						alert('All (' + tmp.keys.size() + ') part instance(s) registered successfully!');
						if (tmp.me.closeAfterSave == true)
							window.close();
						else
							window.location = window.location.href;
					}
					else
					{
						document.getElementById('errors').innerHTML = tmp.errors;
						Modalbox.hide();
						pageJs.resetErrors();
					}

				} else {
					//go for the next one
					pageJs.processPIs(tmp.currentIndex * 1 + 1);
				}
			}
		});
	},
	enterToSelectPT: function(event, selectBox) {
		if(!((event.which && event.which == 13) || (event.keyCode && event.keyCode == 13)))
			return true;

		$(selectBox).getElementsBySelector('li').each(function(item){
			if (item.hasClassName('selected')) {
				$(item).click();
				return;
			}
		});
	},

	//set whether we close the window after saving
	_setCloseAfterSave: function (el) {
		this.closeAfterSave = el.value;
	},

	//writes a content to a new open widow
	writeConsole: function (content, pageTitle) {
		var top = {};
		top.consoleRef = this.openNewWindow('', pageTitle.replace(' ', '_') , this.openNewWindowParams);
		top.consoleRef.document.writeln('<html>' + content + '</html>');
		top.consoleRef.document.close();
	},
	//open part delivery lookup page
	openNewWindow: function(url, title, params)
	{
		if(params === undefined)
			params = this.openNewWindowParams;
		var tmp = window.open(url, title, params);
		if(tmp.focus){tmp.focus();}
		return tmp;
	}
};