//this is the source file for the ReregisterPartInstancesController
var EditPIJS = new Class.create();
EditPIJS.prototype = {
	callBackIds: {}, //the ids for the callback
	isCurrUsrSysAdmin: false, //whether the current user is system admin
	snAliasTypeId: null, //the partinstance alias type id for serial number
	editPIPanelID: '', //the html id of the edit part instance details div
	aliasPanelID: '', //the html id of the part instance alias div
	partinstance: null, //the partinstance we are editing
	searchBarcodeTxtboxID: null, //the id of the html textbox for searching the serial number
	searchPiIdTxtboxID: null, //the id of the html textbox for searching the Pi ID
	searchBtnID: null, //the id of the html search btn
	closeAfterSave: false,

	//constructor
	initialize: function (isCurrUsrSysAdmin, snAliasTypeId, editPIPanelID, aliasPanelID, searchBarcodeTxtboxID, searchPiIdTxtboxID, searchBtnID) {
		this.isCurrUsrSysAdmin = (isCurrUsrSysAdmin === '1' ? true : false);
		this.snAliasTypeId = snAliasTypeId;
		this.editPIPanelID = editPIPanelID;
		this.aliasPanelID = aliasPanelID;
		this.searchBarcodeTxtboxID = searchBarcodeTxtboxID;
		this.searchPiIdTxtboxID = searchPiIdTxtboxID;
		this.searchBtnID = searchBtnID;
	},

	//setter for the callback ids
	setCallbackIds: function(getBtnId, changePTBtn, chkConflictBtn, savePIBtn) {
		this.callBackIds.getPIBtn = getBtnId;
		this.callBackIds.changePTBtn = changePTBtn;
		this.callBackIds.chkConflictBtn = chkConflictBtn;
		this.callBackIds.savePIBtn = savePIBtn;
		return this;
	},

	//getting the url for viewing the part instance history
	openHistory: function() {
		if(this.partinstance === undefined ||  this.partinstance === null || this.partinstance.id === undefined || this.partinstance.id === null) {
			alert('System Error: the part instance id not stored!');
			return;
		}
		window.open('/parthistory/searchparttext/' + this.partinstance.id);
	},

	//getting the partinstance
	getPI: function (btn) {
		var tmp = {};
		tmp.me = this;
		tmp.originalValue = $F(btn);
		tmp.btnDisabled = $(btn).disabled;

		$(tmp.me.searchBarcodeTxtboxID).value = $F(tmp.me.searchBarcodeTxtboxID).strip().toUpperCase();
		tmp.searchData = {};
		$(btn).up('.searchpane').getElementsBySelector('[searchpane]').each(function (item) {
			tmp.searchData[$(item).readAttribute('searchpane')] = $F(item);
		});

		bsuiteJs.postAjax(tmp.me.callBackIds.getPIBtn, tmp.searchData, {
			'onLoading': function (sender, param) {
				$(btn).value = 'Retrieving PI Info';
				$(btn).disabled = true;
				tmp.me.partinstance = null;
				//clear everything
				$$('.resetWhenChangePT').each(function (item) { item.update(''); });
				//hide everything
				$$('.editPIDetails').each(function (item) { item.addClassName('hidden'); });
				//clearing the PiId text box
				$(tmp.me.searchPiIdTxtboxID).value = '';
				//clearing hotmessage
				if($('HotMessage') && $('HotMessage').up()) {
					$('HotMessage').up().update('');
				}
			},
			'onComplete': function (sender, param) {
				try {
					tmp.result = bsuiteJs.getResp(param, false, true);
					if(tmp.result.piArray === undefined || tmp.result.piArray === null || tmp.result.piArray.size() === 0)
						throw 'No part instance found from the search!';

					//if we found multiple part instances
					if (tmp.result.piArray.size() > 1) {
						tmp.me._showMultiplePI(tmp.result.piArray);
					} else {
						tmp.me.partinstance = tmp.result.piArray[0];
						if(tmp.me.partinstance.parttype.serialized !== true)
							throw 'You can NOT edit a non-serialised part instance here!';
						tmp.me._showPIDetails(tmp.me.partinstance);

						if ($(btn).up().down('.viewHistoryBtn') === undefined || $(btn).up().down('.viewHistoryBtn') === null) {
							$(btn).insert({'after': new Element('span', {'class': 'viewHistoryBtn inlineblock'}).update('View History').observe('click', function (){
								tmp.me.openHistory();
							})});
						}
						//show the hidden panel
						$$('.editPIDetails.hidden').each(function (item) {item.removeClassName('hidden');});
						$('searchbarcode').value = tmp.me.partinstance.sn;

						$('newbarcode').focus();
					}

					//reactivate the search button
					$(btn).value = tmp.originalValue;
					$(btn).disabled = tmp.btnDisabled;

				} catch(e) {
					alert(e);
					//reactivate the search button
					$(btn).value = tmp.originalValue;
					$(btn).disabled = tmp.btnDisabled;
				}
			}
		});
		return this;
	},

	//changing the parttype
	changePT: function(txtbox) {
		var tmp = {};
		tmp.me = this;
		$(txtbox).getElementsBySelector('li.selected').each(function(item) {
			tmp.ptId = item.value;
		});
		bsuiteJs.postAjax(tmp.me.callBackIds.changePTBtn, {'piId': tmp.me.partinstance.id, 'ptId': tmp.ptId }, {
			'onLoading': function (sender, param) {
				$$('.resetWhenChangePT').each(function (item) {
					$(item).update('Retrieving PartType Information ...');
				});
			},
			'onComplete': function (sender, param) {
				try {
					tmp.result = bsuiteJs.getResp(param, false, true);
					if(tmp.result.piArray === undefined || tmp.result.piArray === null || tmp.result.piArray.size() === 0)
						throw 'No part instance found from the search!';

					tmp.me._showPIDetails(tmp.result.piArray[0]);
				} catch(e) {
					alert(e);
				}
			}
		});
	},

	//showing the part instance details
	_showPIDetails: function (partinstance) {
		var tmp = {};
		tmp.Qty = null;
		tmp.active = null;
		tmp.me = this;
		$(tmp.me.searchPiIdTxtboxID).value = partinstance.id;

		//loading the part instance information
		//show part type
		$$('[editpane="parttype"]').each(function(item) {
			item.writeAttribute('ptid', partinstance.parttype.id);
			item.writeAttribute('serialized', partinstance.parttype.serialized === true ? 1 : 0);
			item.value = partinstance.parttype.name;
		});
		tmp.contractsList = new Element('ul');
		partinstance.contracts.each(function(item) {
			tmp.contractsList.insert({'bottom': new Element('li').update(item.name)});
		});

		//This is to show the quantity and active to users. Qty and Active are not editable for normal users
		if (tmp.me.isCurrUsrSysAdmin !== true)
			{
				//If PI is not active, show the message to the user.
				if(this.partinstance.active === 0)
				{
					alert("This part instance is deactivated.\n Please contact technology if you want to reactivate it");
				}

				tmp.editPIPanel = $(tmp.me.editPIPanelID).update('')
				.insert({'bottom': tmp.me._getRowDiv('Owner Client: ', partinstance.owner.name) }) //Owner Client:
				.insert({'bottom': tmp.me._getRowDiv('User Contract(s): ', tmp.contractsList.wrap(new Element('div', {'class': 'contractlist roundcnr'})))  }) //User Contract:
				.insert({'bottom': tmp.me._getRowDiv('Part Location: ', partinstance.warehouse.path)  }) //Owner Client:
				.insert({'bottom': tmp.me._getRowDiv('Status: ', tmp.me._getSelectBox(partinstance.status.availStatuses.sort(), 'id', 'name', partinstance.status.id).writeAttribute('editpane', 'status'))  })        //status
				.insert({'bottom': tmp.me._getRowDiv('Qty: ', partinstance.qty)  })
				.insert({'bottom': tmp.me._getRowDiv('Active: ', partinstance.active === 1 ? 'Yes' : 'No')  })
				;

				tmp.newBarcode = null;
				if(partinstance.parttype.serialized === true) {
					tmp.newBarcode = new Element('input', {'id': 'newbarcode', 'type': 'text', 'editpane': "newbarcode", 'class': "clearonreset roundcnr txt fullwidth", 'placeholder': 'The new serial number', 'value': ''})
						.observe('keydown', function(event){
							return tmp.me.keydown(event, function(){
								//trying to refocus onto the next blank field
								tmp.emtpyAliasInputs = $$('[editpane="alias"]').filter(function(item){return $F(item).blank();});
								if(tmp.emtpyAliasInputs.size() >0 )
									tmp.emtpyAliasInputs.first().focus();
							});
						})
						;
					tmp.editPIPanel.insert({'bottom': tmp.me._getRowDiv(
							'New Serial Number: ',
							new Element('div').update(tmp.newBarcode).insert({'bottom': tmp.me._getSampleFormatDiv(partinstance.snFormats.pattern, partinstance.snFormats.sampleformat)}),
							tmp.me._getRowErrMsg('Leave blank to retain current Serial Number', 'infomsg')
						)
					}); //New Serial Number::
				}

				//loading the part instance alias information
				//Save button is only displayed if PI is active.
				if(partinstance.aliases !== null && this.partinstance.active === 1) {
					$(tmp.me.aliasPanelID).update(tmp.me._getAliasDiv(partinstance.aliases, partinstance.avialPIATs))
						.insert({'bottom': new Element('input', {'type': 'button', 'value': 'Save', 'id': 'savePIBtn'})
						.observe('click', function() {tmp.me.savePI();})
					});
				}
			}

			//This is for SYS Admin who will have all the rights. Qty and Active can be edited by Sys Admin
			else
			{
				tmp.Qty = new Element("input",{
					id : "qty",
					type : "text",
					editpane : "qty",
					"class" : "clearonreset roundcnr txt fullwidth",
					placeholder : "qty",
					value : partinstance.qty
				});

				tmp.active = new Element("input",{
					id : "active",
					type : "text",
					editpane : "active",
					"class" : "clearonreset roundcnr txt fullwidth",
					placeholder : "active",
					value : partinstance.active
				});

				tmp.editPIPanel = $(tmp.me.editPIPanelID).update('')
				.insert({'bottom': tmp.me._getRowDiv('Owner Client: ', partinstance.owner.name) }) //Owner Client:
				.insert({'bottom': tmp.me._getRowDiv('User Contract(s): ', tmp.contractsList.wrap(new Element('div', {'class': 'contractlist roundcnr'})))  }) //User Contract:
				.insert({'bottom': tmp.me._getRowDiv('Part Location: ', partinstance.warehouse.path)  }) //Owner Client:
				.insert({'bottom': tmp.me._getRowDiv('Status: ', tmp.me._getSelectBox(partinstance.status.availStatuses.sort(), 'id', 'name', partinstance.status.id).writeAttribute('editpane', 'status'))  })        //status
				.insert({'bottom' : tmp.me._getRowDiv("Qty: ", new Element("div").update(tmp.Qty).insert(), tmp.me._getRowErrMsg("Qty should be 1","infomsg")) })
				.insert({'bottom' : tmp.me._getRowDiv("Active: ", new Element("div").update(tmp.active).insert(), tmp.me._getRowErrMsg("Activate: 1 | Deactivate : 0","infomsg")) })
				;

				tmp.newBarcode = null;
				if(partinstance.parttype.serialized === true) {
					tmp.newBarcode = new Element('input', {'id': 'newbarcode', 'type': 'text', 'editpane': "newbarcode", 'class': "clearonreset roundcnr txt fullwidth", 'placeholder': 'The new serial number', 'value': ''})
						.observe('keydown', function(event){
							return tmp.me.keydown(event, function(){
								//trying to refocus onto the next blank field
								tmp.emtpyAliasInputs = $$('[editpane="alias"]').filter(function(item){return $F(item).blank();});
								if(tmp.emtpyAliasInputs.size() >0 )
									tmp.emtpyAliasInputs.first().focus();
							});
						})
						;
					tmp.editPIPanel.insert({'bottom': tmp.me._getRowDiv(
							'New Serial Number: ',
							new Element('div').update(tmp.newBarcode).insert({'bottom': tmp.me._getSampleFormatDiv(partinstance.snFormats.pattern, partinstance.snFormats.sampleformat)}),
							tmp.me._getRowErrMsg('Leave blank to retain current Serial Number', 'infomsg')
						)
					}); //New Serial Number::
				}

				//loading the part instance alias information
				if(partinstance.aliases !== null) {
					$(tmp.me.aliasPanelID).update(tmp.me._getAliasDiv(partinstance.aliases, partinstance.avialPIATs))
						.insert({'bottom': new Element('input', {'type': 'button', 'value': 'Save', 'id': 'savePIBtn'})
						.observe('click', function() {tmp.me.savePI();})
					});
				}

			}
	},
	//getting the msg div
	_getRowErrMsg: function (msg, classname) {
		var tmp = {};
		tmp.classname = (classname === undefined || classname === null) ? '' : classname;
		tmp.element = new Element('span', {'class': 'msg smltxt ' + tmp.classname}).update(msg);
		return tmp.element;
	},

	//saving the part isntance onto the server
	savePI: function () {
		var tmp = {};
		tmp.me = this;
		//collecting the user inputs
		tmp.newData = this._collectingData();
		if(tmp.newData === null)
			return;

		//confirm with the user about the change
		tmp.me._getDiffFromCollectedData(tmp.newData);
	},

	_getCfrmDivRow: function (field, actiontype, oldValue, newValue, isHeaderRow) {
		var tmp = {};
		tmp.element = new Element('div', {'class': 'aliastyperow ' + (isHeaderRow === true ? 'header' : 'cfrmChgRow')})
			.insert({'bottom': new Element('span', {'class': 'inlineblock actiontype'}).update(actiontype) })
			.insert({'bottom': new Element('span', {'class': 'inlineblock field'}).update(field) })
			.insert({'bottom': new Element('span', {'class': 'inlineblock oldValue'}).update(oldValue) })
			.insert({'bottom': new Element('span', {'class': 'inlineblock newValue'}).update(newValue) })
			;
		return tmp.element;
	},

	//getting the difference and confirm with user
	_getDiffFromCollectedData: function(collectedData) {
		var tmp = {};
		tmp.me = this;

		//getting the difference
		tmp.diffs = [];
		//checking part type change
		if(collectedData.parttype.id !== tmp.me.partinstance.parttype.id) {
			tmp.diffs.push({'field': 'Part Type', 'action':'Updating', 'oldValue': tmp.me.partinstance.parttype.name, 'newValue': collectedData.parttype.name});
		}
		//checking status change
		if(collectedData.status.id !== tmp.me.partinstance.status.id) {
			tmp.diffs.push({'field': 'Status', 'action':'Updating', 'oldValue': tmp.me.partinstance.status.name, 'newValue': collectedData.status.name});
		}

		//Do this only if Sys Admin
		if(tmp.me.isCurrUsrSysAdmin === true)
			{
				//checking quantity change
				if (collectedData.qty !== tmp.me.partinstance.qty) {
						tmp.diffs.push({'field' : 'Qty','action' : 'Updating','oldValue' : tmp.me.partinstance.qty, 'newValue' : collectedData.qty});
					}


				//checking active change
				if (collectedData.active == tmp.me.partinstance.active) { }
				else{
					tmp.diffs.push({'field' : 'Active','action' : 'Updating','oldValue' : tmp.me.partinstance.active, 'newValue' : collectedData.active});
				}
			}

		//checking aliases change
		$H(collectedData.aliases).each(function (aliasObj) {
			tmp.typeId = aliasObj.key;
			//this is a completely new part instance(s)
			if(tmp.me.partinstance.aliases[tmp.typeId] === undefined || tmp.me.partinstance.aliases[tmp.typeId] === null) {
				aliasObj.value.each(function (alias) {
					tmp.diffs.push({'field': tmp.me.partinstance.avialPIATs[tmp.typeId].name, 'action':'Creating', 'oldValue': '', 'newValue': alias.alias});
				});
			} else {
				aliasObj.value.each(function (alias) {
					if(alias.id.blank()) { //brandnew alias object
						tmp.diffs.push({'field': tmp.me.partinstance.avialPIATs[tmp.typeId].name, 'action':'Creating', 'oldValue': '', 'newValue': alias.alias});
					} else if (alias.deactivate === true) { //deleting an existing alias
						tmp.diffs.push({'field': tmp.me.partinstance.avialPIATs[tmp.typeId].name, 'action':'Removing', 'oldValue': alias.alias, 'newValue': ''});
					} else {
						tmp.me.partinstance.aliases[tmp.typeId].each(function(oldAlias) {
							if(alias.id === oldAlias.id && alias.alias !== oldAlias.alias)
								tmp.diffs.push({'field': tmp.me.partinstance.avialPIATs[tmp.typeId].name, 'action':'Updating', 'oldValue': oldAlias.alias , 'newValue': alias.alias});
						});
					}
				});
			}
		});
		if(tmp.diffs.size() === 0) {
			alert('Nothing changed!');
			return;
		}

		//confirm with the user about the change
		tmp.confirmChangeDiv = new Element('div', {'class': 'cfrmChgDiv'}).update(tmp.me._getCfrmDivRow('Field', 'Action', 'Old Value', 'New Value', true));
		tmp.rowNo = 0;
		tmp.editPIChgSummary = [];
		tmp.diffs.each(function (diff){
			tmp.editPIChgSummary.push(diff.action + ' ' + diff.field + ': [' + diff.oldValue + '] -> [' + diff.newValue + ']');
			tmp.confirmChangeDiv.insert({'bottom': tmp.me._getCfrmDivRow(diff.field, diff.action, diff.oldValue, diff.newValue, false).addClassName(((tmp.rowNo++) % 2 === 0 ? 'even': 'odd')) });
		});
		Modalbox.show(tmp.confirmChangeDiv.wrap('div'), {'title': 'Are you sure you want to change this part instance:', 'width': 600, 'afterLoad': function() {
			$(Modalbox.MBcontent).insert({'top': new Element('div', {'class': 'submitPIBtns'})
				.insert({'bottom': new Element('input', {'value': 'Yes', 'type': 'button', 'class': 'submitBtn'})
					.observe('click', function() {
						tmp.me._chkConflicts(collectedData, tmp.editPIChgSummary);
					})
				})
				.insert({'bottom': new Element('input', {'value': 'No', 'type': 'button', 'class': 'submitBtn'})
					.observe('click', function() { Modalbox.hide();})
				})
			});
			Modalbox.resizeToContent();
		}});
	},

	//checking whether there are conflicts for unique aliases and new serial number
	_chkConflicts: function (collectedData, editPIChgSummary) {
		var tmp = {};
		tmp.me = this;
		bsuiteJs.postAjax(tmp.me.callBackIds.chkConflictBtn, collectedData, {
			'onLoading': function (sender, param) {
				Modalbox.show('loading', {'title': 'Checking whether there are conflicts against unique aliases ...'});
			},
			'onComplete': function (sender, param) {
				try {
					tmp.result = bsuiteJs.getResp(param, false, true);
					//if we found no conflicts
					if(tmp.result.confictsPIATIds === undefined || tmp.result.confictsPIATIds === null || tmp.result.confictsPIATIds.size() === 0) {
						tmp.me._submitPI(collectedData, editPIChgSummary);
						return;
					}
					//if we found some conflicts, then display them
					tmp.me._displayConflicts(collectedData, tmp.result.confictsPIATIds, tmp.result.conficts, editPIChgSummary);
				} catch(e) {
					Modalbox.hide();
					alert(e);
				}
			}
		});
		return this;
	},

	//displaying the conflicts for the user to decide which one to keep
	_displayConflicts: function(collectedData, confictsPIATIds, confictedAliases, editPIChgSummary) {
		var tmp = {};
		tmp.me = this;
		tmp.avialPIATs = this.partinstance.avialPIATs;

		//displaying the header
		tmp.conflictDiv = new Element('div', {'class': 'conflictDiv'}).update(
			tmp.me._getConflictPIR('', 'S/N', 'Part Type', 'Location', 'Conflicted Aliases').addClassName('header')
		);

		//display the current part instance
		tmp.rowNo = 0;
		tmp.currPi = tmp.me.partinstance;
		tmp.currAliases = {};
		$H(collectedData.aliases).each(function (aliasObj) {
			tmp.typeId = aliasObj.key;
			tmp.aliasObj = aliasObj.value;
			if(confictsPIATIds.indexOf(tmp.typeId) >= 0) { // only grab the one that we think it's a confictsPIATIds alias type
				if(tmp.currAliases[tmp.typeId] === undefined || tmp.currAliases[tmp.typeId] === null)
					tmp.currAliases[tmp.typeId] = [];
				tmp.aliasObj.each(function(aliasObject) {
					if(aliasObject.deactivate === false) {
						tmp.aliasContent = aliasObject.alias;
						tmp.currAliases[tmp.typeId].push(tmp.aliasContent);
					}
				});
			}
		});
		tmp.conflictDiv.insert({'bottom': tmp.me._getConflictPIRow(tmp.currPi.id, tmp.currPi.sn + ((collectedData.newbarcode && !collectedData.newbarcode.blank()) ? ' -> ' + collectedData.newbarcode : ''),
				'<div class="smltxt errormsg currenteditpi">Currently being Edited</div> ' + collectedData.parttype.name,
				tmp.currPi.warehouse.path,
				tmp.currAliases,
				confictsPIATIds,
				tmp.currPi.parent).addClassName((tmp.rowNo++) % 2 === 0 ? 'even' : 'odd') });

		//displaying all conflicted the aliases
		tmp.samePT = true;
		tmp.sameParent = true;
		$H(confictedAliases).each(function(confAArray) {
			tmp.piId = confAArray.key;
			tmp.confAArray = confAArray.value;
			//getting each part instance row
			tmp.conflictDiv.insert({'bottom': tmp.me._getConflictPIRow(tmp.piId, tmp.confAArray.pi.sn,
					tmp.confAArray.pi.name,
					tmp.confAArray.pi.warehouse.path,
					tmp.confAArray['aliases'],
					confictsPIATIds,
					tmp.confAArray.pi.parent)
				.addClassName((tmp.rowNo++) % 2 === 0 ? 'even' : 'odd')
			});
			if(collectedData.parttype.id !== tmp.confAArray.pi.parttype.id) {
				tmp.samePT = false;
			}
			if(tmp.currPi.parent.id !== tmp.confAArray.pi.parent.id) {
				tmp.sameParent = false;
			}
		});

		//showing the div in Modalbox
		Modalbox.show(tmp.conflictDiv, {'width': '900', 'title': '<span style="color: red;">Conflicts found! Please select <u>ONE</u> PartInstance to <u>KEEP</u>!</span>', 'afterLoad': function(){
			tmp.conflictedPIIds = {};
			$(Modalbox.MBcontent).getElementsBySelector('.conflictAliasRow[partinstanceid] .piraidobtn input').each(function(item) {
				tmp.piId = item.value;
				tmp.conflictedPIIds[tmp.piId] = false;
				item.observe('click', function() { //selecting the merged into partinstance
					if(tmp.samePT === false) {
						alert('To merge part instances, they have to be the same part type. but they are NOT! Please edit the part instance first!');
						return;
					}
					if(tmp.sameParent === false) {
						alert('To merge part instances, they have to be either under the same parent OR under no parent at all!');
						return;
					}
					tmp.conflictedPIIds[$F(this)] = true;
					if(confirm('Are you sure you want to merge all other PI into the selected one?'))
						tmp.me._submitPI(collectedData, editPIChgSummary, tmp.conflictedPIIds);
				});
			});
			Modalbox.resizeToContent();
		}});
		return this;
	},

	//getting the row element for each conflicted part instance
	_getConflictPIRow: function(piId, pisn, piname, pilocation, aliases, confictsPIATIds, parentPi) {
		var tmp = {};
		tmp.avialPIATs = this.partinstance.avialPIATs;

		//getting each part instance alias row
		tmp.conflictRowAlias = new Element('div', {'class': 'conflictAlias'});
		$H(aliases).each(function(alias){
			tmp.aliasTypeId = alias.key;
			tmp.aliases = alias.value;
			tmp.conflictRowAlias.insert({'bottom': new Element('div', {'class': 'conflictAlias'})
				.insert({'bottom': new Element('span', {'class': 'conflictAliasType inlineblock'}).update(tmp.avialPIATs[tmp.aliasTypeId].name + ': ') })
				.insert({'bottom': new Element('span', {'class': 'conflictAliasContent inlineblock'}).update(tmp.aliases.join('<br />')) })
			});
		});
		return this._getConflictPIR(piId, pisn, piname, pilocation, tmp.conflictRowAlias, parentPi);
	},

	//getting each part instance row
	_getConflictPIR: function(piId, pisn, piname, pilocation, conflictAliases, parentPi) {
		return new Element('div', {'class': 'aliastyperow conflictAliasRow ', 'partinstanceid': piId})
			.insert({'bottom': new Element('span', {'class': 'piraidobtn inlineblock'}).update((!piId || piId.blank()) ? '' : new Element('input', {'type': 'radio', 'name': 'conflictedpi', 'value': piId})) })
			.insert({'bottom': new Element('span', {'class': 'pisn inlineblock'}).update(pisn) })
			.insert({'bottom': new Element('span', {'class': 'piname inlineblock'}).update(piname) })
			.insert({'bottom': new Element('span', {'class': 'pilocation inlineblock'}).update(
					(parentPi && parentPi.id ? '<div class="smltxt errormsg">Within another Part</div> ' : '') + pilocation
			) })
			.insert({'bottom': new Element('span', {'class': 'pialiasconflicts inlineblock'}).update(conflictAliases) })
			;
	},

	//submitting the collected information to the server
	_submitPI: function (collectedData, editPIChgSummary, conflictedPiIds) {
		var tmp = {};
		tmp.me = this;
		collectedData.editPIChgSummary = editPIChgSummary;
		collectedData.conflictedPIIds = conflictedPiIds;
		bsuiteJs.postAjax(tmp.me.callBackIds.savePIBtn, collectedData, {
			'onLoading': function (sender, param) {
				Modalbox.show('loading', {'title': 'Saving part instance ...'});
			},
			'onComplete': function (sender, param) {
				try {
					tmp.result = bsuiteJs.getResp(param, false, true);
					if(tmp.result.piArray !== undefined && tmp.result.piArray !== null && tmp.result.piArray.size() > 0) {
						alert('Part Instance Saved Successfully!');
						//tmp.partintance = tmp.result.piArray[0];
						//tmp.me._reloadPIDetails(tmp.partintance.id, tmp.partintance.aliases[tmp.me.snAliasTypeId][0].alias);
						//Modalbox.hide();
						if (tmp.me.closeAfterSave)
							window.close();
						else
							window.location.reload();
					}
				} catch(e) {
					Modalbox.hide();
					alert(e);
				}
			}
		});
		return this;
	},

	//reload PI details:
	_reloadPIDetails: function(piId, sn) {
		$(this.searchPiIdTxtboxID).value = piId;
		if(sn !== undefined && sn !== null)
			$(this.searchBarcodeTxtboxID).value = sn;
		this.getPI($(this.searchBtnID));
	},

	//collecting the new part instance data before submitting
	_collectingData: function () {
		var tmp = {};
		tmp.me = this;
		tmp.requestData = {'id': tmp.me.partinstance.id,
				'parttype': {}, //part type information user selected
				'status': {}, //status information user selected
				'aliases': {}, //the array of part instance aliases
				'avialPIATs': tmp.me.partinstance.avialPIATs //all the part instance alias types
			};
		$$('.errorRow').each(function(item){ item.removeClassName('errorRow'); });
		tmp.hasErr = false;
		//collecting the user inputted data
		$$('[editpane]').each(function(item) {
			switch(item.readAttribute('editpane')) {
				case 'parttype': {
					tmp.requestData.parttype = {'id': item.readAttribute('ptid'), 'name': $F(item), 'serialized': (item.readAttribute('serialized') == 1 ? true : false)};
					break;
				}

				case 'qty':	{

					if ($F(item) == "1"){ }
					else
					{
						tmp.row = $(item).up(".row");
						tmp.infoDiv = tmp.row.down(".info");
						tmp.infoDiv.update(tmp.me._getRowErrMsg(" Error!  Qty can only be 1"));
						tmp.hasErr = true;
						$(item).up(".row").addClassName("errorRow")
					};
					tmp.requestData.qty = $F(item);
					break;
				}

				case 'active':	{

					if ($F(item) == "0" || $F(item) == "1"){ }
					else
					{
						tmp.row = $(item).up(".row");
						tmp.infoDiv = tmp.row.down(".info");
						tmp.infoDiv.update(tmp.me._getRowErrMsg(" Error!  You can only use 1 or 0 to Activate or Deactivate a part"));
						tmp.hasErr = true;
						$(item).up(".row").addClassName("errorRow")
					};
					tmp.requestData.active = $F(item);
					break;
				}

				case 'status': {
					tmp.requestData.status = {'id': $F(item), 'name': item.options[item.selectedIndex].innerHTML};
					break;
				}
				case 'newbarcode': {
					tmp.newSerialNo = $F(item).strip().toUpperCase();
					tmp.oldSerialNo = $F(tmp.me.searchBarcodeTxtboxID).strip().toUpperCase();
					tmp.row = $(item).up('.row');
					tmp.infoDiv = tmp.row.down('.info');
					tmp.regexHolder = tmp.row.down('[pattern]');
					tmp.row.getElementsBySelector('.errormsg').each(function(item) { item.remove(); });
					tmp.regex = (tmp.regexHolder !== undefined && tmp.regexHolder !== null && !tmp.regexHolder.readAttribute('pattern').blank()) ? tmp.regexHolder.readAttribute('pattern') : '';
					if(tmp.newSerialNo.blank())
					{
						if(tmp.regex !== '' && tmp.me._matchPatter(tmp.oldSerialNo, tmp.regex) !== true) {
							tmp.infoDiv.update(tmp.me._getRowErrMsg('You need to change the Serial Number!', 'errormsg'));
							tmp.hasErr = true;
							$(item).up('.row').addClassName('errorRow');
						}
					}
					else if(tmp.newSerialNo === tmp.oldSerialNo) {
						tmp.infoDiv.update(tmp.me._getRowErrMsg('New serial number cannot be same as current serial number!', 'errormsg'));
						tmp.hasErr = true;
						$(item).up('.row').addClassName('errorRow');
					}

					else {
						if(tmp.regex !== '' && tmp.me._matchPatter(tmp.newSerialNo, tmp.regex) !== true) {
							tmp.infoDiv.update(tmp.me._getRowErrMsg('Invalid Serial Number provided!', 'errormsg'));
							tmp.hasErr = true;
							$(item).up('.row').addClassName('errorRow');
						}
					}
					if(tmp.hasErr === false && tmp.newSerialNo.blank() === false) {
						if(tmp.requestData.aliases[tmp.me.snAliasTypeId] === undefined || tmp.requestData.aliases[tmp.me.snAliasTypeId] === null)
							tmp.requestData.aliases[tmp.me.snAliasTypeId] = [];
						tmp.me.partinstance.aliases[tmp.me.snAliasTypeId].each(function(alias) {
							tmp.requestData.aliases[tmp.me.snAliasTypeId].push(tmp.me._newAliasObj(alias.id, alias.typeid, alias.alias, true));
						});
						tmp.requestData.aliases[tmp.me.snAliasTypeId].push(tmp.me._newAliasObj('', tmp.me.snAliasTypeId, tmp.newSerialNo, false));
					}
					tmp.requestData.newbarcode = tmp.newSerialNo;
					break;
				}
				case 'alias': {
					//todo need to check against the pattern
					tmp.row = $(item).up('.row.alias');
					tmp.del = tmp.row.readAttribute('delete');
					tmp.typeId = tmp.row.readAttribute('typeid');
					tmp.regexHolder = tmp.row.down('[pattern]');
					tmp.regex = (tmp.regexHolder !== undefined && tmp.regexHolder !== null && !tmp.regexHolder.readAttribute('pattern').blank()) ? tmp.regexHolder.readAttribute('pattern') : '';
					tmp.alias = $F(item).strip();
					tmp.infoDiv = tmp.row.down('.info');

					//remove all error msg
					tmp.row.getElementsBySelector('.errormsg').each(function(item) { item.remove(); });

					if(tmp.alias.blank()) {
						tmp.infoDiv.insert({'bottom': tmp.me._getRowErrMsg('Empty alias not allowed!', 'errormsg')});
						tmp.hasErr = true;
						$(item).up('.row').addClassName('errorRow');
					} else if (tmp.me._matchPatter(tmp.alias, tmp.regex) === false) {
						tmp.infoDiv.insert({'bottom': tmp.me._getRowErrMsg('Alias NOT matched with Valid Format!', 'errormsg')});
						tmp.hasErr = true;
						$(item).up('.row').addClassName('errorRow');
					} else {
						if(tmp.requestData.aliases[tmp.typeId] === undefined || tmp.requestData.aliases[tmp.typeId] === null || tmp.requestData.aliases[tmp.typeId] === '')
							tmp.requestData.aliases[tmp.typeId] = [];
						tmp.requestData.aliases[tmp.typeId].push(tmp.me._newAliasObj(tmp.row.readAttribute('piaid'),
								tmp.row.readAttribute('typeid'),
								tmp.alias,
								(tmp.del === undefined || tmp.del === null ? false : true)
							));
					}
					break;
				}
			}
		});

		tmp.collectedSerialNo = (tmp.requestData.aliases[tmp.me.snAliasTypeId] !== undefined && tmp.requestData.aliases[tmp.me.snAliasTypeId] !== null && tmp.requestData.aliases[tmp.me.snAliasTypeId].size() > 0);
		//if we change the part from serialized to non-serialized, deactivating all serial numbers!
		if(tmp.requestData.parttype.serialized === false) {
			//deactivate the user inputs
			if(tmp.collectedSerialNo) {
				tmp.size = tmp.me.partinstance.aliases[tmp.me.snAliasTypeId].size();
				for(tmp.i = 0; tmp.i < tmp.size; tmp.i++) {
					tmp.me.partinstance.aliases[tmp.me.snAliasTypeId][tmp.i].deactivate = false;
				}
			} else {
				//deactivate the original sn
				tmp.requestData.aliases[tmp.me.snAliasTypeId] = [];
				tmp.me.partinstance.aliases[tmp.me.snAliasTypeId].each(function (alias) {
					alias.deactivate = true;
					tmp.requestData.aliases[tmp.me.snAliasTypeId].push(alias);
				});
			}
		} else if(tmp.requestData.parttype.serialized === true && tmp.collectedSerialNo === false){ //if we are trying to keep the legall sn as before
			tmp.requestData.aliases[tmp.me.snAliasTypeId] = tmp.me.partinstance.aliases[tmp.me.snAliasTypeId];
		}
		if(tmp.hasErr === true) {
			alert('Error when saving, please fixing them before continue.');
		}
		return tmp.hasErr === true ? null : tmp.requestData;
	},

	//getting a new alias object
	_newAliasObj: function (id, typeid, alias, deactivate) {
		return {'id': id,  'typeid': typeid, 'alias': alias, 'deactivate': (deactivate === true ? true : false)};
	},

	//checking whether the alias is matched with the regex
	_matchPatter: function(alias, regex) {
		if (regex === undefined || regex === null || regex.blank())
			return true;
		return new RegExp(regex.substr(1, (regex.length - 2))).match(alias);
	},

	//showing the alias type list when user choose to add a new alias
	addAliasType: function (btn, addAliasType) {
		var tmp = {};
		tmp.me = this;

		//getting the used part instance alias type ids
		tmp.usedAliasTypeIds = [];
		$(tmp.me.aliasPanelID).getElementsBySelector('.row.alias[typeid]').each(function (item) {
			tmp.usedAliasTypeIds.push(item.readAttribute('typeid'));
		});
		tmp.usedAliasTypeIds = tmp.usedAliasTypeIds.uniq();

		//find the usable part instance alias type ids
		tmp.aliasTypes = [];
		$H(addAliasType).each(function (piatArray) {
			tmp.typeId = piatArray.key;
			tmp.piat = piatArray.value;
			if(piatArray.value.allowMulti === true || tmp.usedAliasTypeIds.indexOf(tmp.typeId) < 0) {
				if(piatArray.value.access === '1' || (tmp.me.isCurrUsrSysAdmin === true && piatArray.value.access === '2'))
					tmp.aliasTypes.push(piatArray.value);
			}
		});

		//ask the user now
		tmp.typeList = new Element('div', {'class': 'newaliastypelist'})
			.update(tmp.me._getAliasTypeRow('header aliastyperow', '', 'Name', 'Allow Multiple', 'Is Mandatory', 'Is Unique', 'Sample Format'));
		tmp.rowNo = 0;
		tmp.aliasTypes.each(function (type) {
			tmp.typeList.insert({'bottom': tmp.me._getAliasTypeRow('aliastyperow ' + ((tmp.rowNo++) % 2 === 0 ? 'even' : 'odd'),
					type.id,
					type.name,
					(type.allowMulti === true ? 'Y' : '&nbsp;'),
					(type.mandatory === true ? 'Y' : '&nbsp;'),
					(type.unique === true ? 'Y' : '&nbsp;'),
					type.sampleformat)
			});
		});
		Modalbox.show(tmp.typeList.wrap('div'), {'width': 600, 'title' : 'Please select a alias type from below: ', 'afterLoad': function() {
			$(Modalbox.MBwindow).getElementsBySelector('.aliastyperow[piatid]').each(function (item) {
				item.observe('click', function() {
					tmp.me.addAlias(item.readAttribute('piatid'));
				});
			});
		}});
	},

	//adding a alias row onto the alias list div
	addAlias: function (aliasTypeId) {
		var tmp = {};
		tmp.me = this;
		tmp.aliasTypes = tmp.me.partinstance.avialPIATs;
		tmp.newAliasTextBox = tmp.me._getAliasEditBox();
		$(this.aliasPanelID).down('.aliaslist').insert({'bottom': tmp.me._getAliasRow(
				'',
				tmp.aliasTypes[aliasTypeId].name,
				aliasTypeId,
				tmp.newAliasTextBox,
				tmp.me._getAliasDelBtn(),
				tmp.aliasTypes[aliasTypeId].pattern.pattern,
				tmp.aliasTypes[aliasTypeId].pattern.sampleformat)
		});
		Modalbox.hide({'afterHide': function() {
			tmp.newAliasTextBox.focus();
		}});
	},

	//getting the textbox for the alias edit row
	_getAliasEditBox: function(aliasContent, placehoder) {
		var tmp = {};
		tmp.me = this;
		tmp.aliasContent = ((aliasContent !== undefined && aliasContent !== null && !aliasContent.blank()) ? aliasContent : '');
		tmp.placehoder = ((placehoder !== undefined && placehoder !== null && !placehoder.blank()) ? placehoder : '');
		tmp.element = new Element('input', {'class': 'clearonreset roundcnr txt fullwidth', 'value': tmp.aliasContent, 'type': 'text', 'editpane': 'alias'})
		.observe('keydown', function(event) {
			tmp.me.keydown(event, function () {
				//trying to refocus onto the next blank field
				tmp.emtpyAliasInputs = $$('[editpane="alias"]').filter(function(item){return $F(item).blank();});
				if(tmp.emtpyAliasInputs.size() >0 )
					tmp.emtpyAliasInputs.first().focus();
				else
					$(tmp.me.aliasPanelID).down('#savePIBtn').click();
			});
		});
		if(tmp.placehoder !== '')
			tmp.element.writeAttribute('placeholder', tmp.placehoder);
		return tmp.element;
	},

	//getting the row element for the alias type list
	_getAliasTypeRow: function(cssclass, id, name, allowMulti, mandatory, unique, sampleformat) {
		var tmp = {};
		tmp.cssclass = (cssclass === undefined || cssclass === null) ? 'row' : cssclass;
		tmp.element = new Element('div', {'class': tmp.cssclass})
			.update(new Element('span', {'class': 'name inlineblock'}).update(name))
			.insert({'bottom': new Element('span', {'class': 'allowmulti inlineblock'}).update(allowMulti) })
			.insert({'bottom': new Element('span', {'class': 'mandatory inlineblock'}).update(mandatory) })
			.insert({'bottom': new Element('span', {'class': 'unique inlineblock'}).update(unique) })
			.insert({'bottom': new Element('span', {'class': 'sampleformat inlineblock'}).update(sampleformat) })
			;
		if(id !== undefined && id !== null && !id.blank())
			tmp.element.writeAttribute('piatid', id);
		return tmp.element;
	},

	//getting the delete button for the alias
	_getAliasDelBtn: function () {
		var tmp = {};
		tmp.me = this;
		return new Element('span', {'class': 'delBtn'}).update('&nbsp;').observe('click', function (){
			if(!confirm('Are you sure you want to delete this alias?'))
				return;
			tmp.aliasRow = $(this).up('.row.alias');
			if(tmp.aliasRow.readAttribute('piaid').blank()) {
				tmp.aliasRow.remove();
			} else {
				$(this).up('.row.alias').writeAttribute('delete', true).hide();
			}
		});
	},

	//getting the part instance alias div
	_getAliasDiv: function(aliases, availTypes) {
		var tmp = {};
		tmp.me = this;
		tmp.element = new Element('fieldset', {'class': 'aliaslist roundcnr'}).update(new Element('legend').update('Aliases'))
		.insert({'bottom': new Element('div')
			.insert({'bottom': new Element('input', {'class': 'addAliasBtn', 'type': 'button', 'value': 'Add Alias'}).observe('click', function () {
					tmp.me.addAliasType(this, availTypes);
				})
			})
			.insert({'bottom': new Element('span', {'class': 'manhint'}).update('* Mandatory Field') })
		});

		//getting the mandatory alias type ids
		tmp.manTypeIds = [];
		tmp.unqiueTypeIds = [];
		$H(availTypes).each(function (type) {
			if(type.value.mandatory === true)
				tmp.manTypeIds.push(type.value.id);
			if(type.value.unique === true)
				tmp.unqiueTypeIds.push(type.value.id);
		});

		//display what we've got for that part instance alias
		$H(aliases).each(function (aliasArray) {
			tmp.aliasTypeId = aliasArray.key;
			//check for mandatory alias type
			tmp.index = tmp.manTypeIds.indexOf(tmp.aliasTypeId);
			tmp.isMand = false;
			if(tmp.index >= 0) {
				tmp.manTypeIds.splice(tmp.index, 1);
				tmp.isMand = true;
			}
			//check for unique alias type
			tmp.index = tmp.unqiueTypeIds.indexOf(tmp.aliasTypeId);
			tmp.isUniq = false;
			if(tmp.index >= 0) {
				tmp.unqiueTypeIds.splice(tmp.index, 1);
				tmp.isUniq = true;
			}

			aliasArray.value.each(function (alias) {
				//dom element for the alias type
				tmp.aliastypebox = availTypes[tmp.aliasTypeId].name + (tmp.isMand === true ? ' <span class="manhint">*</span>' : '') + ': ';
				//dom element for the alias textbox
				tmp.aliastxtbox = alias.alias + (tmp.aliasTypeId === tmp.me.snAliasTypeId ? '' : '<input type="hidden" editpane="alias" value="' + alias.alias + '" />');
				//dom element for the alias delete btn
				tmp.aliasbtns = new Element('span');
				//dom element for pattern
				tmp.pattern = '';
				//dom element for sample format
				tmp.sampleformat = '';
				if (availTypes[tmp.aliasTypeId].access === '1' || (availTypes[tmp.aliasTypeId].access === '2' && tmp.me.isCurrUsrSysAdmin === true)) {
					tmp.aliastxtbox = tmp.me._getAliasEditBox(alias.alias);
					tmp.pattern = availTypes[tmp.aliasTypeId].pattern.pattern;
					tmp.sampleformat = availTypes[tmp.aliasTypeId].pattern.sampleformat;
					if(tmp.isMand !== true)
						tmp.aliasbtns.insert({'bottom': tmp.me._getAliasDelBtn() });
					if(tmp.isUniq === true)
						tmp.aliasbtns.insert({'bottom': new Element('span', {'class': 'smltxt infomsg'}).update((tmp.isMand === true ? 'Mandatory & ' : '') + 'Unique Alias') });
				}
				tmp.element.insert({'bottom': tmp.me._getAliasRow(alias.id,
						tmp.aliastypebox,
						tmp.aliasTypeId,
						tmp.aliastxtbox,
						tmp.aliasbtns,
						tmp.pattern,
						tmp.sampleformat
				)});
			});
		});

		//if we have more mandatory aliases to show, then display them
		tmp.manTypeIds.each(function (typeId) {

			//check for unique alias type
			tmp.index = tmp.unqiueTypeIds.indexOf(typeId);
			tmp.isUniq = false;
			if(tmp.index >= 0) {
				tmp.unqiueTypeIds.splice(tmp.index, 1);
				tmp.isUniq = true;
			}

			tmp.element.insert({'bottom': tmp.me._getAliasRow('',
				availTypes[typeId].name + ' <span class="manhint">*</span>' + ': ',
				typeId,
				tmp.me._getAliasEditBox('', 'Mandatory' + (tmp.isUniq === true ? ' & Unique' : '')+ ' alias!'),
				(tmp.isUniq === true ? new Element('span').insert({'bottom': new Element('span', {'class': 'smltxt infomsg'}).update('Mandatory & Unique Alias ') }) : ''),
				availTypes[typeId].pattern.pattern,
				availTypes[typeId].pattern.sampleformat
			)});
		});

		//if we have more unique aliases to show, then display them
		tmp.unqiueTypeIds.each(function (typeId) {
			tmp.element.insert({'bottom': tmp.me._getAliasRow('',
					availTypes[typeId].name + ': ',
					typeId,
					tmp.me._getAliasEditBox('', 'Unique alias!'),
					new Element('span').insert({'bottom': tmp.me._getAliasDelBtn() }).insert({'bottom': new Element('span', {'class': 'smltxt infomsg'}).update('Unique Alias ') }),
					availTypes[typeId].pattern.pattern,
					availTypes[typeId].pattern.sampleformat
			)});
		});
		return tmp.element;
	},

	//getting sample format div
	_getSampleFormatDiv: function (pattern, sampleFormat) {
		return(pattern !== undefined && pattern !== null && !pattern.blank()) ?
			new Element('div', {'class': 'aliaspattern smltxt', 'pattern': pattern}).update('Valid Format: ' + sampleFormat) :
		'';
	},

	//getting the row dom element for the part instance alias
	_getAliasRow: function (aliasId, aliastype, aliastypeId, aliascontent, btns, pattern, sampleFormat) {
		var tmp = {};
		tmp.patternElement = this._getSampleFormatDiv(pattern, sampleFormat);
		tmp.element = new Element('div', {'class': 'row alias', 'piaid': aliasId, 'typeid': aliastypeId})
			.insert({'bottom': new Element('span', {'class': 'title inlineblock'}).update(aliastype)	})
			.insert({'bottom': new Element('span', {'class': 'content inlineblock'})
				.update(aliascontent)
				.insert({'bottom': tmp.patternElement})
			})
			.insert({'bottom': new Element('span', {'class': 'info inlineblock'}).update(btns)	});
		return tmp.element;
	},

	//getting a select box dom element
	_getSelectBox: function(options, valuefield, namefield, selectedvalue) {
		var tmp = {};
		tmp.element = new Element('select', {'class': 'roundcnr fullwidth'});
		options.each(function(opt) {
			tmp.element.insert({'bottom': new Element('option', {'value': opt[valuefield], 'selected': (opt[valuefield] === selectedvalue ? true : false)}).update(opt[namefield])  });
		});
		return tmp.element;
	},

	//getting a row div
	_getRowDiv: function (title, content, info, rowclass) {
		var tmp = {};
		tmp.element =  new Element('div', {'class': 'row' + (rowclass !== undefined ? rowclass : '')})
			.insert({'bottom': new Element('span', {'class': 'title inlineblock'}).update(title) })
			.insert({'bottom': new Element('span', {'class': 'content inlineblock'}).update(content) });
		if(info !== undefined)
			tmp.element.insert({'bottom': new Element('span', {'class': 'info inlineblock'}).update(info) });
		return tmp.element;
	},

	//getting the multiple part instance list panel
	_showMultiplePI: function(piArray) {
		var tmp = {};
		tmp.me = this;
		tmp.list = new Element('div', {'class': 'multiResultWrapper'});
		tmp.rowNo = 0;
		tmp.list.insert({'bottom': tmp.me._getMultiDiv('', 'PartType', 'Location').addClassName('aliastyperow header')});
		piArray.each(function (item) {
			tmp.list.insert({'bottom': tmp.me._getMultiDiv(item.id, item.parttype.name, item.warehouse.path, '').addClassName('aliastyperow ' + ((tmp.rowNo++) % 2 === 0 ? 'even' : 'odd'))});
		});
		Modalbox.show(new Element('div').update(tmp.list), {'title': '<span style="color: red;">Multiple Part Instance Found, please select which you are trying to edit:</span>', 'width': '800', 'afterLoad': function () {
			$(Modalbox.MBcontent).getElementsBySelector('.multipi[partinstanceid]').each(function (item){
				tmp.piId = tmp.piId = $(item).readAttribute('partinstanceid');
				item.observe('click', function () {
					tmp.me._reloadPIDetails($(this).readAttribute('partinstanceid'));
					Modalbox.hide();
				});
			});
		}});
	},

	//getting the multiple list panel row
	_getMultiDiv: function (piid, name, location) {
		var tmp = {};
		tmp.element = new Element('div', {'class': 'multipi row', 'partinstanceid': piid})
			.insert({'bottom':
				new Element('span', {'class': 'inlineblock multipiname'}).update(name)
			})
			.insert({'bottom':
				new Element('span', {'class': 'inlineblock multipilocation'}).update('@ ' + location)
			});
		return tmp.element;
	},

	//set whether we close the window after saving
	_setCloseAfterSave: function (val) {
		this.closeAfterSave = val;
	},

	//do key enter
	keydown: function (event, enterFunc, nFunc) {
		//if it's not a enter key, then return true;
		if(!((event.which && event.which == 13) || (event.keyCode && event.keyCode == 13))) {
			if(typeof(nFunc) === 'function') {
				nFunc();
			}
			return true;
		}

		if(typeof(enterFunc) === 'function') {
			enterFunc();
		}
		return false;
	}
};