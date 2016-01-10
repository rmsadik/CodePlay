//this is the source file for the StorageLocationReservationController

function getCookie(c_name) {
	var c_value = document.cookie;
	var c_start = c_value.indexOf(" " + c_name + "=");
	if (c_start == -1) {
		c_start = c_value.indexOf(c_name + "=");
	}
	if (c_start == -1) {
		c_value = null;
	} else {
		c_start = c_value.indexOf("=", c_start) + 1;
		var c_end = c_value.indexOf(";", c_start);
		if (c_end == -1) {
			c_end = c_value.length;
		}
		c_value = unescape(c_value.substring(c_start, c_end));
	}
	return c_value;
}

var mouseX;
var mouseY;
function getcords(e) {
	mouseX = Event.pointerX(e);
	mouseY = Event.pointerY(e);
}
Event.observe(document, 'mousemove', getcords);

function popup(msg) {
	$('detailsBox').innerHTML = msg;
	$('detailsBox').style.display = '';
	$('detailsBox').style.top = (mouseY + 10) + 'px';
	$('detailsBox').style.left = (mouseX + 10) + 'px';
}

function kill() {
	$('detailsBox').innerHTML = '';
	$('detailsBox').style.display = 'none';
}

var ResvPageJs = new Class.create();
ResvPageJs.prototype = {
	searchRequest : {
		search_callbackId : '', // the search call back id
		sortingField : 'slaEnd', // the name of the sorting field
		sortingDirection : 'asc', // the order of the sorting direction: asc |
									// asc
		pageNo : 1
	// the current page number for the result
	},
	owner : { // the owner information of the current user for the facility
				// requests
		id : '',
		name : '',
		position : ''
	},
	selectRange : { // the range of shift select
		startRowNo : '',
		endRowNo : ''
	},
	refreshInfo : {
		maxExeTime : 600, // the total seconds for refresh time in seconds
		timer : null, // the PeriodicalExecuter
		reloadPaneId : 'refreshPanel', // the html id of the reload button
		refreshCallBackId : '' // the call back id of the refreshing data
	},

	skipCheck : false, // skips unchecking checkboxes during actions
	skipQtyCheck : false,
	totalRowsHoderId : '', // this is just a holder for the total rows returned
							// from the searching.
	resultTableId : '', // the table id of the search results
	reponseHolderId : '', // the response holder for the result from ajax call
	selectedRequestIds : [], // the selected request ids
	resPaneWith : 700, // the width of the modalbox
	delImage : '/themes/images/delete_mini.gif', // the image file path for
													// delete/unreserving.
	loadingImage : '/themes/images/ajax-loader.gif', // the image file path
														// for loading.
	openNewWindowParams : 'width=750, menubar=0, toolbar=0, status=0, scrollbars=1, resizable=1', // the
																									// default
																									// value
																									// for
																									// open
																									// a
																									// new
																									// window
	floatHeadId : 'floatHead', // the id of the div that contains the floating
								// table head
	foundNewFrIds : '',// to set color for the new frs

	// constructor
	initialize : function(reponseHolderId, refreshTime, resultTableId,
			totalRowsHolderId, getMoreResultBtnId, whHolderId, showAvailBtnId,
			showRsrvdBtnId, getCommentsBtnId, sendEmailBtnId,
			changeStatusBtnId, status_cancel, status_attended, ftStatus_avail,
			ftStatus_transit, refreshCallBackId, takeFRBtnId, showPushBtnId,
			showExtraAvailBtnId, showEmailPanelId, checkPartBtnId,
			maxExecutionTime, reservationLabelBtnId, autoTake,
			createTnDnBtn, showTransitNotePanelBtn,
			fieldTaskEditBaseUrl, createMoveToTechBtn, showMoveToTechPanelBtn,
			createWarningBtn,updateFRHatBtn
			) {
		this.reponseHolderId = reponseHolderId;
		this.refreshInfo.maxExeTime = refreshTime;
		this.resultTableId = resultTableId;
		this.totalRowsHolderId = totalRowsHolderId;
		this.getMoreResultBtnId = getMoreResultBtnId;
		this.whHolderId = whHolderId; // the holder for default warehouse
										// facility name
		this.showAvailBtnId = showAvailBtnId; // the call back function id for
												// show avail parts
		this.showRsrvdBtnId = showRsrvdBtnId; // the call back function id for
												// show rsrvd parts
		this.getCommentsBtnId = getCommentsBtnId; // the call back function id
													// for show comments
		this.sendEmailBtnId = sendEmailBtnId; // the call back function id for
												// send email
		this.changeStatusBtnId = changeStatusBtnId; // the call back function id
													// for cancel
		this.status_cancel = status_cancel; // the cancel status for facility
											// request
		this.status_attended = status_attended; // the attended status for
												// facility request
		this.ftStatus_avail = ftStatus_avail; // the avail status for field
												// task
		this.ftStatus_transit = ftStatus_transit; // the transit status for
													// field task
		this.refreshInfo.refreshCallBackId = refreshCallBackId; // the call back
																// id for
																// refreshing
																// the page
		this.takeFRBtnId = takeFRBtnId; // the call back id for take FR
		this.showPushBtnId = showPushBtnId; // the call back id for showing push
											// FR panel
		this.showExtraAvailBtnId = showExtraAvailBtnId; // the call back
														// function id for show
														// avail parts from
														// other stores
		this.showEmailPanelId = showEmailPanelId; // the call back function id
													// for show email panel
		this.checkPartBtnId = checkPartBtnId; // Checks that part is correct
												// part type for request
		this.maxExecutionTime = (maxExecutionTime * 1000); // max execution of
															// a request
		this.skipCheck = false;
		this.reservationLabelBtnId = reservationLabelBtnId;
		this.autoTake = autoTake;
		this.createTnDnBtn = createTnDnBtn;
		this.showTransitNotePanelBtn = showTransitNotePanelBtn;
		this.fieldTaskEditBaseUrl = fieldTaskEditBaseUrl;
		this.createMoveToTechBtn = createMoveToTechBtn;
		this.showMoveToTechPanelBtn = showMoveToTechPanelBtn;
		this.createWarningBtn = createWarningBtn;
		this.updateFRHatBtn = updateFRHatBtn;

	},

	// set the owner information
	setOwnerInfo : function(ownerId, ownerName, position) {
		this.owner.id = ownerId;
		this.owner.name = ownerName;
		this.owner.position = position;
	},

	checkAutoTake : function(autoTake) {
		var autoTake = $(autoTake);
		if (autoTake != null) {
			if (autoTake.checked == true) {
				return true;
			}
		}
		return false;
	},

	showConfirmTransitNote : function() {
		var tmp = {};

		tmp.request = new Prado.CallbackRequest(this.showTransitNotePanelBtn, {
			'onComplete' : function(sender, parameter) {
			}
		});

		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();
	},

	preConfirmMoveToTech : function(message, createMoveToTechCallbackId) {
		var tmp = {};
		tmp.message=message[0].unescapeHTML();
		tmp.newhtml = "<div confirmbox='confirmtake'>"
		tmp.newhtml += "<b>Please confirm below:</b><br /><br />";
		tmp.newhtml += tmp.message +"<br />";
		tmp.newhtml += "<input type='button' newpreference='saveBtn' onclick=\"pageJs.showConfirmMoveToTech(''); return false;\" value='Proceed'>";
		tmp.newhtml += "<input type='button' onclick='Modalbox.hide(); return false;' value='Cancel'>";
		tmp.newhtml += "</div>";
		Modalbox.show(new Element('div').update(tmp.newhtml), {
			'title' : 'Confirm Taking Task',
			'width' : '1000'
		});
		return false;
	},

	showConfirmMoveToTech : function(checkflag) {
		var tmp = {};
		tmp.me = this;
		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds();
		} catch (e) {
			this.showError(e);
			return;
		}

		tmp.resultTableId = '';
		if (tmp.me.checkRervedForSelected(tmp.selectedRquestIds) != "") {
			tmp.errormessage = "Some of the facility requests have zero reserved parts against them!\n";
			tmp.errormessage += "You must have reservations against all the requests to Move To Tech."
			alert(tmp.errormessage);
		} else {
			tmp.request = new Prado.CallbackRequest(
					this.showMoveToTechPanelBtn, {
						'onComplete' : function(sender, parameter) {
							try {
								tmp.result = pageJs.analyzeResp();
								if (checkflag) {
									// confirm task with the FRs
									tmp.me.preConfirmMoveToTech(tmp.result,
											tmp.me.createMoveToTechBtn);
								} else {
									// then move to tech
									tmp.me.createMoveToTech(
											tmp.me.createMoveToTechBtn, true,
											true);
								}

							} catch (e) {
								this.showError(e);
								return;
							}
						}
					});

			tmp.callParams = {
				'selectedIds' : tmp.selectedRquestIds
			};
			tmp.request.setCallbackParameter(tmp.callParams);
			tmp.request.setRequestTimeOut(this.maxExecutionTime);
			tmp.request.dispatch();
		}

	},

	getWarehouseDetails : function(){
		var tmp = {};
		tmp.me= this;
		tmp.result='';
		tmp.callParams = {};
		tmp.request = new Prado.CallbackRequest(this.updateFRHatBtn, {
			'onComplete' : function(sender, parameter) {
				Modalbox.hide();
				try {
					tmp.result = parameter.evalJSON();
					if(tmp.result.errors && tmp.result.errors.size() > 0)
						throw tmp.result.errors.join('\n');

					tmp.result = tmp.result.resultData;
					pageJs.setOwnerInfo(tmp.result['id'], tmp.result['name'], tmp.result['position']);

				} catch (e) {
					this.showError(e);
					return;
				}

			}
		});

		tmp.callParams['id'] = tmp.result['id'];
		tmp.callParams['name'] = tmp.result['name'];
		tmp.callParams['position'] = tmp.result['position'];

		tmp.request.setCallbackParameter(tmp.callParams);
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();


	},

	createTnDn : function(noteType, callbackId, confirm, warehouseToName, transitNoteNo) {
		var tmp = {};
		tmp.me = this;
		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds();
		} catch (e) {
			this.showError(e);
			return;
		}
		tmp.showTransitNotePanelBtn = this.showTransitNotePanelBtn;

		tmp.resultTableId = '';
		if (tmp.me.checkRervedForSelected(tmp.selectedRquestIds) != "") {
			tmp.errormessage = "Some of the facility requests have zero reserved parts against them!\n";
			tmp.errormessage += "You must have reservations against all the requests you have selected."
			alert(tmp.errormessage);
		} else {
			mb.showLoading('generating note (' + noteType + ')');

			// send the request
			tmp.request = new Prado.CallbackRequest(callbackId, {
				'onComplete' : function(sender, parameter) {
					Modalbox.hide();

					try {
						tmp.result = pageJs.analyzeResp();
						if (confirm) {
							pageJs.showConfirmTransitNote();
						}

					} catch (e) {
					}

				}
			});
			tmp.callParams = {
				'selectedIds' : tmp.selectedRquestIds
			};

			tmp.callParams['noteType'] = noteType;
			tmp.callParams['checkTransitNote'] = confirm;
			tmp.callParams['warehouseToId'] = $(warehouseToName).value;
			tmp.callParams['selectedTransitNoteNo'] = transitNoteNo;

			tmp.request.setCallbackParameter(tmp.callParams);
			tmp.request.setRequestTimeOut(this.maxExecutionTime);
			tmp.request.dispatch();
		}
	},

	createMoveToTech : function(callbackId, confirm, warehouseToName) {
		var tmp = {};
		var tmp2 = {};
		tmp.me = this;
		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds();
		} catch (e) {
			this.showError(e);
			return;
		}

		tmp.errormessage = "";
		tmp.resultTableId = this.resultTableId;
		tmp.showTransitNotePanelBtn = this.showTransitNotePanelBtn;

		tmp.selectedRquestIds
				.each(function(id) {

					tmp.currentRow = $$(
							'table#' + tmp.resultTableId + ' tr[requestid="'
									+ id + '"]').first();
					tmp.currentRow = tmp.currentRow
							.down('[resultrow="reserved"]');
					tmp.countReserved = tmp.currentRow
							.down('[atag="reserved"]').innerHTML;
					if (tmp.countReserved == 0) {
						tmp.errormessage = "Some of the facility requests have zero reserved parts against them!\n";
						tmp.errormessage += "You must have reservations against all the requests your moving to Tech."
					}

				});

		if (tmp.errormessage != "") {
			alert(tmp.errormessage);
		} else {
			mb.showLoading('moving to technician');

			// send the request
			tmp.request = new Prado.CallbackRequest(callbackId, {
				'onComplete' : function(sender, parameter) {
					Modalbox.hide();

					try {
						tmp.result = pageJs.analyzeResp();

					} catch (e) {
						this.showError(e);
						return;
					}

				}
			});

			tmp.callParams = {
				'selectedIds' : tmp.selectedRquestIds
			};
			tmp.callParams['checkMoveToTech'] = confirm;
			tmp.callParams['moveToTechWarehouseToId'] = $(warehouseToName).value;

			tmp.request.setCallbackParameter(tmp.callParams);
			tmp.request.setRequestTimeOut(this.maxExecutionTime);
			tmp.request.dispatch();

		}

	},

	// change on title actions. Called from the dropdown list in the title of
	// the result list
	changeTitleAction : function(action, clickedBtn, emailCallBackId,
			printPickListCallBackId, pushFTStatusCallBackId, currentUser,
			takeFRCallBackId, showPushFRCallBackId, reservationLabelBtn,
			autoTake, createTnDnId, createMoveToTechId) {
		switch (action) {
		case 'picklist': {
			this.printPickList(printPickListCallBackId, clickedBtn,
					currentUser, autoTake);
			break;
		}
		case 'label': {
			this.printLabel(undefined, reservationLabelBtn, autoTake);
			break;
		}
		case 'email': {
			this.email(undefined, emailCallBackId, clickedBtn);
			break;
		}
		case 'take': {
			this.takeFR(takeFRCallBackId);
			break;
		}
		case 'push': {
			this.showPushFR(showPushFRCallBackId);
			break;
		}
		case this.status_attended:
		case this.status_cancel: {
			this.showChangeStatusDiv(undefined, this.changeStatusBtnId, action,
					clickedBtn);
			break;
		}
		case 'pushToAvail': {
			this.pushFT(pushFTStatusCallBackId, this.ftStatus_avail);
			break;
		}
		case 'createTransitNote': {
			this.createTnDn('TN', createTnDnId, true, false, '');
			break;
		}
		case 'createDispatchNote': {
			this.createTnDn('DN', createTnDnId, true, false, '');
			break;
		}
		case 'moveToTech': {
			this.showConfirmMoveToTech(true);
			break;
		}
		default: {
			this.showError("Invalid selection for action: ".action);
		}
		}
	},

	pushFTWrapper : function(action, pushFTStatusCallBackId, requestId) {
		switch (action) {
		case 'pushToAvail': {
			this.pushFT(pushFTStatusCallBackId, this.ftStatus_avail, requestId);
			break;
		}
		default: {
			this.showError('Please select a status');
		}
		}
	},

	// displaying the sorting directions for the selected column
	displaySorting : function() {
		var tmp = {};
		tmp.sortingField = this.searchRequest.sortingField;
		tmp.sortingDirection = this.searchRequest.sortingDirection;

		// removing all existing order displays
		$$('table#' + this.resultTableId + ' th b.sortOrder').each(
				function(item) {
					item.remove();
				});

		// adding back the one we wants to display
		$$('table#' + this.resultTableId + ' th')
				.each(
						function(item) {
							// display the one that we are sorting on
							if (item.readAttribute('resulttableheader') !== null) {
								if (item.readAttribute('resulttableheader')
										.strip() === tmp.sortingField) {
									if (tmp.sortingDirection === 'asc')
										tmp.nextSortingDirect = '&uarr;';
									else
										tmp.nextSortingDirect = '&darr;';

									item.innerHTML += ' <b class="sortOrder">'
											+ tmp.nextSortingDirect + '</b>';
								}
							}
						});
	},

	// reorder from the table header
	reOrder : function(field) {
		var sortOrder = 'asc';
		if (this.searchRequest.sortingField === $(field).readAttribute(
				'resulttableheader')) {
			if (this.searchRequest.sortingDirection === 'asc')
				sortOrder = 'desc';
			else
				sortOrder = 'asc';
		} else
			this.searchRequest.sortingField = $(field).readAttribute(
					'resulttableheader');

		this.searchRequest.sortingDirection = sortOrder;
		this.search(this.searchRequest.search_callbackId, true);
	},

	// search data, posting request to the backend
	search : function(callbackId, resetResultTable) {
		this.searchRequest.search_callbackId = callbackId;
		try {
			var tmp = {};
			// clear the selected ids
			this.checkAll(new Element('input', {
				'type' : 'checkbox',
				'checked' : false
			}));// de-select all

			// stop refresh
			this.stopTimer();

			// remove the refresh panel
			if ($(this.refreshInfo.reloadPaneId) !== undefined
					&& $(this.refreshInfo.reloadPaneId) !== null)
				$(this.refreshInfo.reloadPaneId).remove();

			// if resetResultTable is true. it's the first time for searching!
			if (resetResultTable === true) {
				this.searchRequest.pageNo = 1;
				// blocking user inputs when searching...
				mb.showLoading('searching');

				// clean up the tbody
				tmp.resultTableBody = $(this.resultTableId).down('tbody');
				tmp.resultTableBody.select('tr').each(function(item) {
					item.remove();
				});

				// hide floating table head
				if ($(this.floatHeadId) !== null
						&& $(this.floatHeadId) !== undefined)
					$(this.floatHeadId).remove();
			}

			tmp.request = new Prado.CallbackRequest(callbackId, {
				'onComplete' : function(sender, parameter) {
					pageJs.postSearch()
				}
			});
			tmp.request.setCallbackParameter({
				'searchParams' : this.searchRequest
			});
			tmp.request.setRequestTimeOut(this.maxExecutionTime);
			tmp.request.dispatch();
		} catch (err) {
			// console.error(err);
		}
		return false;
	},
	// get the next page result
	nextPage : function() {
		this.skipCheck = true;
		var tmp = $(this.getMoreResultBtnId);

		// stop refresh
		this.stopTimer();

		this.searchRequest.pageNo = this.searchRequest.pageNo + 1;
		tmp.disabled = true;
		tmp.writeAttribute({
			'orginalvalue' : tmp.value
		});
		tmp.value = 'Getting results ...';
		this.search(this.searchRequest.search_callbackId);

	},

	// load the content when scroll the windows bar
	loadWhenScroll : function() {

		var tmp = {};
		// float the thead
		tmp.resultTable = $(pageJs.resultTableId);
		tmp.tableHead = tmp.resultTable.down('thead');

		// load next page when scroll
		tmp.topPos = $(pageJs.getMoreResultBtnId).viewportOffset()[1];
		if (tmp.topPos <= 0 || $(pageJs.getMoreResultBtnId).disabled === true)
			return false;

		if (tmp.topPos <= (document.viewport.getHeight() - 20)) {
			pageJs.nextPage();
		}
	},

	// load the menu when scrolling
	loadFormItemWhenScroll : function() {
		var tmp = {};
		tmp.floatHeadId = pageJs.floatHeadId;
		tmp.resultTable = $(pageJs.resultTableId);
		tmp.tableHead = tmp.resultTable.down('thead');
		var menuheight = $('form-item').cumulativeScrollOffset();

		if ($('form-item') != null
				&& navigator.userAgent.indexOf('Chrome') == -1
				&& $('content').getHeight() > 1500) {
			if (menuheight[1] > 250) {
				$('form-item').setStyle({
					position : 'fixed',
					top : '47px',
					height : '33px',
					'width' : '960px'

				});

				/*
				 * if(menuheight[1]>650) { if($(tmp.floatHeadId) === null ||
				 * $(tmp.floatHeadId) === undefined) { tmp.floatHead = new
				 * Element('div', {'id': 'floatHead', 'style': 'position:
				 * fixed;width: ' + $('resultList').up('div').getWidth() + 'px;
				 * top: 95px;'}).update("<table class='DataList'><thead>" +
				 * tmp.tableHead.innerHTML + "</thead></table>");
				 * tmp.floatHead.select('th').last().update('Back to Top');
				 * tmp.resultTable.up().insert({after: tmp.floatHead}); } } else {
				 * if($(tmp.floatHeadId) !== null && $(tmp.floatHeadId) !==
				 * undefined) $(tmp.floatHeadId).remove(); }
				 */
			} else {
				$('form-item').removeAttribute('style');

				if ($(tmp.floatHeadId) !== null
						&& $(tmp.floatHeadId) !== undefined)
					$(tmp.floatHeadId).remove();
			}
		} else {
			// if chrome browser
			if (menuheight[1] > 650) {
				if ($(tmp.floatHeadId) === null
						|| $(tmp.floatHeadId) === undefined) {
					tmp.floatHead = new Element('div', {
						'id' : 'floatHead',
						'style' : 'position: fixed;width: '
								+ $('resultList').up('div').getWidth()
								+ 'px; top: 0;'
					}).update("<table class='DataList'><thead>"
							+ tmp.tableHead.innerHTML + "</thead></table>");
					tmp.floatHead.select('th').last().update('Back to Top');
					tmp.resultTable.up().insert({
						after : tmp.floatHead
					});
				}
			} else {
				if ($(tmp.floatHeadId) !== null
						&& $(tmp.floatHeadId) !== undefined)
					$(tmp.floatHeadId).remove();
			}
		}
	},

	// analyze response
	analyzeResp : function(sliencemode) {
		var tmp = {};
		tmp.resultHTML = $(this.reponseHolderId).innerHTML;

		if (tmp.resultHTML.strip().empty())
			return [];

		try {
			tmp.result = tmp.resultHTML.evalJSON();
		} catch (e) {
			this.showError('Invalid JSON Message!');
		}

		if (tmp.result.errors !== undefined && tmp.result.errors.size() > 0) {
			if (sliencemode === undefined || sliencemode === false)
				this.showError(tmp.result.errors);
			throw tmp.result.errors.join(' ');
		}
		if (tmp.result.resultData !== undefined)
			return tmp.result.resultData;
		return [];
	},

	// post search: dealing with the response after searching.
	postSearch : function() {
		try {
			var tmp = {};
			// hide the searching div..
			try {
				Modalbox.hide();
			} catch (er) {
			}
			;

			// clear the selected ids
			// this.checkAll(new Element('input',{'type': 'checkbox', 'checked':
			// true}));//de-select all

			// show the result table
			tmp.resultTableBody = $(this.resultTableId).down('tbody');
			tmp.resultTableBodyTRLength = tmp.resultTableBody.select('tr').length;
			tmp.result = this.analyzeResp();
			for (tmp.i = 0; tmp.i < tmp.result.length; tmp.i = tmp.i + 1) {
				tmp.newRowNo = tmp.resultTableBodyTRLength + tmp.i;
				tmp.newRow = this.getResultTR(tmp.result[tmp.i], tmp.newRowNo,
						false);
				if (tmp.newRowNo === 0)
					tmp.resultTableBody.update(tmp.newRow);
				else
					tmp.resultTableBody.select('tr').last().insert({
						after : tmp.newRow
					});
			}

			// show sorting direction
			this.displaySorting();

			// if the new row number is less than total rows, display the fetch
			// next button
			$(this.getMoreResultBtnId).hide();
			$(this.getMoreResultBtnId).disabled = true;
			if (tmp.resultTableBody.getElementsBySelector('tr').size() < ($(this.totalRowsHolderId).innerHTML
					.strip() * 1)) {
				// reset getMoreResultBtnId
				$(this.getMoreResultBtnId).disabled = false;
				try {
					if ($(this.getMoreResultBtnId)
							.readAttribute('orginalvalue') != null) {
						if ($(this.getMoreResultBtnId).readAttribute(
								'orginalvalue').strip() !== '')
							$(this.getMoreResultBtnId).value = $(
									this.getMoreResultBtnId).readAttribute(
									'orginalvalue');
					}
				} catch (er) {
				}
				$(this.getMoreResultBtnId).show();
			}

			// show the result table
			$(this.resultTableId).up().show();

			// start the timer
			if (this.refreshInfo.timer === null)
				this.startTimer();
		} catch (err) {

		}

		this.skipCheck = false;
		return false;
	},

	// get result tr
	getResultTR : function(resultRow, rowNo, checked) {
		var rowAttributes;
		if (resultRow.status === 'cancel' || resultRow.status === 'closed'
				|| resultRow.status === 'complete') {
			rowAttributes = "background:#FFCCCC;";
		}
		if (resultRow.status === 'new') {
			rowAttributes = "background:#d0f4b7;";
		}
		// if new rows found on refresh then
		if (this.foundNewFrIds !== null) {
			var frs = this.foundNewFrIds.split(',');
			for ( var i = 0; i < frs.length; i++) {
				if (frs[i] == resultRow.id)
					rowAttributes = "background:#b7d0f4;";
			}
		}

		var tmp = '<tr style="' + rowAttributes + '"  class="resultRow '
				+ (rowNo % 2 === 0 ? 'DataListItem' : 'DataListAlterItem')
				+ '" requestid="' + resultRow.id + '" fieldtaskid="'
				+ resultRow.fieldTaskId + '" rowno="' + rowNo + '" >';

		if (checked == true) {
			tmp += '<td resultrow="id"><input class="chkbox" checked type="checkbox" value="'
					+ resultRow.id
					+ '" onclick="pageJs.selectOneItem(this.value, this.checked, event);" title="Select this request"/></td>';
		} else {
			tmp += '<td resultrow="id"><input class="chkbox" type="checkbox" value="'
					+ resultRow.id
					+ '" onclick="pageJs.selectOneItem(this.value, this.checked, event);" title="Select this request"/></td>';
		}

		if (resultRow.FrCount > 1) {
			var frCount = '(' + resultRow.FrCount + ')';
		} else {
			var frCount = '';
		}

		var ptHmHtml = '';
		if (resultRow.ptHotMessage != '')
			ptHmHtml = '<img src="/themes/images/red_flag_16.png" onmouseover="document.getElementById(\'HotMessage' + resultRow.id + '\').style.display=\'block\';" onmouseout="document.getElementById(\'HotMessage' + resultRow.id + '\').style.display=\'none\';" />' + resultRow.ptHotMessage + '<br />';

		tmp += '<td resultrow="parttype">' // part code column
				+ '<div>'
				+ ptHmHtml.replace(/&lt;/g, "<").replace(/&gt;/g, ">")
				+ '<span resultrow="partcode" title = "PartCode">'
				+ resultRow.partCode
				+ '</span> - <span resultrow="requestedQty" Title="Requested Quantity">'
				+ resultRow.qty
				+ '</span></div>'
				+ '<div><span resultrow="partname">'
				+ resultRow.partName
				+ '</span></div>'
				+ '</td>'
				+ '<td resultrow="fieldtask">' // part code column
				+ '<div><a onMouseover=\'popup("'
				+ resultRow.popupMessage.unescapeHTML()
				+ '");\' onMouseout="kill();" resultrow="fieldtaskid" href="javascript: void(0);" onclick="'
				+ "pageJs.openNewWindow('"
				+ this.fieldTaskEditBaseUrl
				+ resultRow.fieldTaskId
				+ "'); return false;"
				+ '">'
				+ resultRow.fieldTaskId
				+ '</a> - <span resultrow="taskstatus" title="FieldTask Status" >'
				+ resultRow.taskStatus
				+ '</span> <span resultrow="taskstatus" title="No Of Open Facility Request" >'
				+ frCount + '</span></div>'
				+ '<div><b>S: </b><span resultrow="site">' + resultRow.site
				+ '</span></div>'
				+ '<div><b>C: </b><span resultrow="worktype">'
				+ resultRow.worktype + '</span></div>'
				+ '<div><b>Z: </b><span resultrow="zoneset">'
				+ resultRow.zoneset + '</span></div>'
				+ '<div><b>Billable: </b><span resultrow="zoneset">'
				+ resultRow.billable + '</span></div>' + '</td>';

		if (resultRow.slaEnd.indexOf('CLIENT') != -1) {
			tmp = tmp
					+ '<td resultrow="slaend" style="font-weight:bold;color:#FF0000">'; // sla
																						// end
																						// column
		} else {
			tmp = tmp + '<td resultrow="slaend">'; // sla end column
		}
		tmp = tmp + resultRow.slaEnd;
		tmp = tmp + '</td>';

		var Pcolor = 'blue';
		var Priority = 'P99';
		var PriorityTitle = 'Priority not available';
		if (resultRow.frPriority !== null) {
			Priority = resultRow.frPriority['priority'];
			Pcolor = resultRow.frPriority['colour'];
			PriorityTitle = resultRow.frPriority['title'];
		}
		tmp = tmp + '<td resultrow="frpriority"><label title="Priority '+Priority+'"><font color='+Pcolor+' size="6px"><b>'+Priority+'</b></font></label></td>'; //FR Priority

		tmp = tmp + '<td resultrow="availqty">'; // avail qty
		if (resultRow.availQty == null) {
			resultRow.availQty = 0;
		}

		var countGood = 0;
		var countBad = 0;
		if (resultRow.availQty != 0) {
			var split = resultRow.availQty.split(":");
			countGood = split[0];
			countBad = split[1];

		}

		//comp avail qty
		if (resultRow.compatibleAvailQty == null) {
			resultRow.compatibleAvailQty = 0;
		}
		var countCompGood = 0;
		var countCompBad = 0;
		if (resultRow.compatibleAvailQty !== 0) {
			var split = resultRow.compatibleAvailQty.split(":");
			countCompGood = split[0];
			countCompBad = split[1];
		}

		tmp = tmp
				+ '<a href="javascript: void(0);" style="color:green;font-weight:bold" title="No. of Good Part(s)" onclick="'
				+ "return pageJs.viewAvailList('" + resultRow.id + "', '"
		+ this.showAvailBtnId + "',1,false);" + '" >' + countGood + '</a>';
		tmp = tmp
				+ '<br><a href="javascript: void(0);" style="color:red;font-weight:bold" title="No. of Not Good Part(s)" onclick="'
				+ "return pageJs.viewAvailList('" + resultRow.id + "', '"
		+ this.showAvailBtnId + "',0,false);" + '" >' + countBad + '</a><br>';

		if(countGood*1 === 0 && (countCompGood*1 >0 || countCompBad*1 >0))
		{
			//start of Compatible avail qty
		tmp = tmp
			+ '<br>'
			+ '<hr>'
			+ '<br>';

			tmp = tmp
			+ '<a href="javascript: void(0);" style="color:green;font-weight:bold" title="No. of Compatible Good Part(s)" onclick="'
			+ "return pageJs.viewAvailList('" + resultRow.id + "', '"
			+ this.showAvailBtnId + "',1,true);" + '" >C-'+countCompGood*1 + '</a>';
			tmp = tmp
			+ '<br><a href="javascript: void(0);" style="color:red;font-weight:bold" title="No. of Compatible Not Good Part(s)" onclick="'
			+ "return pageJs.viewAvailList('" + resultRow.id + "', '"
			+ this.showAvailBtnId + "',0,true);" + '" >C-'+countCompBad*1 + '</a>';
			//end of Compatible avail qty
		}
		tmp = tmp
		+'</td>';
		//end of avail qty

		tmp = tmp
				+ '<td resultrow="reserved">' // reserved qty
				+ '<a href="javascript:void(0);" atag="reserved" title="No. of Reserved Part(s)" onclick="'
				+ "return pageJs.viewResrvdList('" + resultRow.id + "', '"
				+ this.showRsrvdBtnId + "');" + '" >';

		if (resultRow.reserved !== null)
			tmp = tmp + resultRow.reserved;
		else
			tmp = tmp + '0';
		tmp = tmp + '</a></td>' + '<td resultrow="status">' // facility request
															// status
				+ resultRow.status;

		if (resultRow.status != 'new') {
			tmp = tmp + '<br>(' + resultRow.updatedFullName + ')';
		}

		tmp = tmp + '</td>';

		tmp = tmp + '<td resultrow="statusElapsedTime">' // facility request
															// Updated Elapsed
															// Time
				+ resultRow.updatedElapsedTime;
		tmp = tmp + '</td>';

		tmp = tmp
				+ '<td resultrow="owner" ownerpos="'
				+ resultRow.ownerPos
				+ '" ownerid="'
				+ resultRow.ownerId
				+ '">' // facility request owner
				+ '<div resultrow="ownername">'
				+ resultRow.owner
				+ '</div>'
				+ '<a href="javascript: void(0);" onclick="pageJs.takeFR(pageJs.takeFRBtnId, '
				+ resultRow.id
				+ ');" style="margin: 0 10px 0 0;" title="Take this request">take</a>'
				+ '<a href="javascript: void(0);" onclick="pageJs.showPushFR(pageJs.showPushBtnId, '
				+ resultRow.id
				+ ');" title="Push this request to somewhere else">push</a>'
				+ '</td>'
				+ '<td resultrow="btns">' // buttons
				+ '<input type="image" resultrow="commentsBtn" src="/themes/images/comment_icon.png" onclick="'
				+ "return pageJs.showComments('"
				+ resultRow.id
				+ "', '"
				+ this.getCommentsBtnId
				+ "', this);"
				+ '" title="Show/Add Comments"/> '
				+ '<input type="image" resultrow="emailBtn" src="/themes/images/mail.gif" onclick="'
				+ "return pageJs.email('" + resultRow.id + "', '"
				+ this.showEmailPanelId + "', this);"
				+ '" title="Send an email"/> ';

		if (resultRow.sendToWarehouseId === null)
			tmp = tmp
					+ '<input type="image" resultrow="deliveryLookupBtn" src="/themes/images/history_disabled.gif" onclick="'
					+ "pageJs.openNewWindow('/partdeliverylookup'); return false;"
					+ '"  title="set a delivery look reference"/> ';

		tmp = tmp + '<input resultrow="cancelBtn" type="image" src="'
				+ this.delImage + '" onclick="'
				+ "pageJs.showChangeStatusDiv('" + resultRow.id + "', '"
				+ this.changeStatusBtnId + "', '" + this.status_cancel
				+ "', this); return false;"
				+ '"  title="cancelling the current request" style="';

		if (resultRow.cancelable === false)
			tmp = tmp + 'display: none;';
		tmp = tmp + '" />';

		tmp = tmp
				+ '<input resultrow="reopenBtn" type="image" src="/themes/images/big_yes.gif" onclick="'
				+ "pageJs.showChangeStatusDiv('" + resultRow.id + "', '"
				+ this.changeStatusBtnId + "', '" + this.status_attended
				+ "', this); return false;"
				+ '"  title="reopening the current request" style="';
		if (resultRow.reopenable === false)
			tmp = tmp + 'display: none;';
		tmp = tmp + '" />';

		tmp = tmp
				+ '<input type="image" src="/themes/images/print.png"  onclick="'
				+ " pageJs.printLabel('"
				+ resultRow.id
				+ "', '"
				+ this.reservationLabelBtnId
				+ "', '"
				+ this.autoTake
				+ "');return false;"
				+ '" title="Print Label For Rsvd / PickList"  style="margin: 0 0 0 5px;" />';
		tmp = tmp + '</td>';

		+'</tr>';

		return tmp;
	},

	printLabel : function(requestId, callbackId, autoTake) {
		// gathering data
		var tmp = {};
		tmp.pageBody = '';
		try {
			tmp.selectedRquestIds = this.getSelectedIds(requestId);
		} catch (e) {
			this.showError(e);
			return;
		}
		tmp.getBrowser = this.getBrowser();
		tmp.resultTableId = this.resultTableId;
		tmp.reponseHolderId = this.reponseHolderId;
		tmp.openNewWindowParams = this.openNewWindowParams;
		// gathering data
		tmp._newLine = '<br>';

		// show UI for disabled printing Btn, once btn clicked
		mb.showLoading('generating the reservation label');

		// send the request
		tmp.request = new Prado.CallbackRequest(
				callbackId,
				{
					'onComplete' : function(sender, parameter) {
						try {
							Modalbox.hide();

							var ob = $(tmp.reponseHolderId).innerHTML
									.evalJSON();
							tmp.pageBody = '';

							ob
									.each(function(value, index) {

										tmp.currentRow = $$(
												'table#' + tmp.resultTableId
														+ ' tr[requestid="'
														+ ob[index]['fr']
														+ '"]').first();

										tmp.pageBody += tmp._newLine;
										tmp.pageBody += tmp._newLine;
										tmp.pageBody += tmp._newLine;

										tmp.pageBody += tmp._newLine;
										tmp.pageBody += tmp._newLine;
										tmp.pageBody += tmp._newLine;
										tmp.pageBody += tmp._newLine;
										tmp.pageBody += tmp._newLine;
										tmp.pageBody += tmp._newLine;
										tmp.pageBody += tmp._newLine;
										tmp.pageBody += tmp._newLine;
										tmp.pageBody += tmp._newLine;
										tmp.pageBody += tmp._newLine;

										tmp.pageBody += 'FACILITY REQUEST'
												+ tmp._newLine;
										tmp.pageBody += '<br>'
												+ Barcode
														.DrawCode39Barcode(
																tmp.currentRow
																		.down('[resultrow="fieldtaskid"]').innerHTML,
																0)
												+ tmp._newLine;

										if (ob[index]['shipTo'] != null) {
											tmp.pageBody += '<b>SHIP TO:</b><br /> '
												+ ob[index]['shipTo'].replace(/&lt;/g, "<").replace(/&gt;/g, ">")
											+ tmp._newLine;
										}

										tmp.pageBody += '<br><b>FIELD TASK: '
												+ tmp.currentRow
														.down('[resultrow="fieldtaskid"]').innerHTML
												+ '</b>' + tmp._newLine;
										tmp.pageBody += '<b>SITE: '
												+ tmp.currentRow
														.down('[resultrow="site"]').innerHTML
												+ '</b>' + tmp._newLine;
										tmp.pageBody += '<b>CONTRACT: '
												+ tmp.currentRow
														.down('[resultrow="worktype"]').innerHTML
												+ '</b>' + tmp._newLine;
										tmp.pageBody += '<b>ZONE SET: '
												+ tmp.currentRow
														.down('[resultrow="zoneset"]').innerHTML
												+ '</b>' + tmp._newLine;
										tmp.pageBody += 'SLA END: '
												+ tmp.currentRow
														.down('[resultrow="slaend"]').innerHTML
												+ tmp._newLine;
										tmp.pageBody += 'PART: '
												+ tmp.currentRow
														.down('[resultrow="partname"]').innerHTML
												+ tmp._newLine;
										tmp.pageBody += 'PART CODE: <b>'
												+ tmp.currentRow
														.down('[resultrow="partcode"]').innerHTML
												+ '</b>' + tmp._newLine;
										tmp.pageBody += 'REQUESTED Qty: '
												+ tmp.currentRow
														.down('[resultrow="requestedQty"]').innerHTML
												+ tmp._newLine;
										if (ob[index]['location'] != null) {
											tmp.pageBody += 'LOCATION: '
													+ ob[index]['location']
													+ tmp._newLine;
										}
										if (ob[index]['comment'] != null) {
											tmp.pageBody += 'COMMENT: '
													+ ob[index]['comment']
													+ tmp._newLine;
										}

										tmp.pageBody += tmp._newLine
												+ '------------------------------------------------------------------------------------------------------';
										tmp.pageBody += tmp._newLine
												+ '</br></br>';

										if (index < ob.length - 1) {
											tmp.pageBody += '<hr style="page-break-after:always; visibility: hidden">';
										}

									});

							var win = window.open('', '',
									tmp.openNewWindowParams);
							win.document
									.writeln('<html><head><title>Facility Request </title></head>'
											+ '<body>'
											+ tmp.pageBody
											+ '</body></html>');

							win.window.print();
							win.document.close();
							pageJs.updateListForAutoTake(autoTake,
									tmp.selectedRquestIds);

						} catch (er) {

						}
					}
				});
		tmp.request.setCallbackParameter({
			'selectedIds' : tmp.selectedRquestIds
		});
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();

	},

	// show the error message
	showError : function(msgArray) {
		if (msgArray === null || typeof (msgArray) === "undefined"
				|| msgArray === undefined) {
			alert('Not a Valid Action. Please try again.');
		} else {
			if (typeof msgArray === 'object')
				alert(msgArray);
			else
				alert(msgArray.stripTags());
		}
	},

	// selecting all the items on the page.
	checkAll : function(checkBox) {
		if (!this.skipCheck) {
			var tmp = {};
			tmp.resultTable = $$('table#' + this.resultTableId).first();
			tmp.checkboxes = tmp.resultTable
					.select('td[resultrow="id"] input.chkbox');
			for (tmp.i = 0; tmp.i < tmp.checkboxes.size(); tmp.i = tmp.i + 1) {
				if (tmp.checkboxes[tmp.i].disabled !== true)
					this.selectOneItem(tmp.checkboxes[tmp.i].value,
							checkBox.checked);
			}

			// check or uncheck the first one on the table header
			tmp.resultTable.getElementsBySelector('thead input.chkbox').first().checked = checkBox.checked;
		}
	},

	// select one item; selected = true: select that item; otherwise, it's a
	// de-selecting action
	selectOneItem : function(requestId, selected, event) {
		var tmp = {};

		tmp.selectedRow = $(this.resultTableId).down(
				'tr[requestid="' + requestId + '"]');
		tmp.selectedRow.down('td[resultrow="id"] input.chkbox').checked = selected;
		tmp.rowNo = tmp.selectedRow.readAttribute('rowno').strip();
		// shift select range
		if (event !== undefined && event.shiftKey === true
				&& !this.selectRange.startRowNo.empty()) {
			this.selectRange.endRowNo = tmp.rowNo;
			for (tmp.i = this.selectRange.startRowNo * 1; tmp.i <= this.selectRange.endRowNo * 1; tmp.i = tmp.i + 1) {
				try {
					tmp.requestId = $(this.resultTableId).down(
							'tr[rowno="' + tmp.i + '"]').readAttribute(
							'requestid').strip();
					this.selectOneItem(tmp.requestId, selected);
				} catch (er) {
				}
			}
			this.selectRange.startRowNo = this.selectRange.endRowNo = '';
			return;
		}

		this.selectRange.startRowNo = tmp.rowNo;
		if (selected === true) // select
			this.selectedRequestIds.push(requestId);
		else // de-select
		{
			tmp.newSelectedIds = [];
			this.selectedRequestIds.each(function(id) {
				if (id !== requestId)
					tmp.newSelectedIds.push(id);
			});
			this.selectedRequestIds = tmp.newSelectedIds;
		}
	},

	// view avail/compatible avail parts list
	viewAvailListOtherStores : function(requestId, callbackId, goodParts, compatibleParts) {
		var tmp = {};
		// gathering data
		try {
			tmp.selectedRquestIds = requestId;
		} catch (e) {
			this.showError(e);
			return;
		}

		if(compatibleParts)
			mb.showLoading('getting the compatible available parts list');
		else
		mb.showLoading('getting the available parts list');

		tmp.request = new Prado.CallbackRequest(callbackId, {
			'onComplete' : function(sender, parameter) {
				pageJs.showAvailListOtherStores(goodParts, compatibleParts);
			}
		});
		tmp.callParams = {
			'selectedIds' : tmp.selectedRquestIds
		};
		tmp.callParams['otherStores'] = true;
		tmp.callParams['goodParts'] = goodParts;
		tmp.callParams['compatibleParts'] = compatibleParts;
		tmp.request.setCallbackParameter(tmp.callParams);
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();
	},

	// display the avail list in Modalbox
	showAvailListOtherStores : function(goodParts) {
		var type = '';
		if (goodParts == 1) {
			type = '(Good) ';
		} else {
			type = '(Not Good) ';
		}
		Modalbox.show(new Element('div')
				.update($(this.reponseHolderId).innerHTML), {
			beforeLoad : function() {
				Modalbox.activate();
			},
			title : type + 'Part(s) in Other Locations',
			width : pageJs.resPaneWith
		});
	},

	// view avail/compatible avail parts list
	viewAvailList : function(requestId, callbackId, goodParts, compatibleParts) {
		var tmp = {};
		// gathering data
		try {
			tmp.selectedRquestIds = requestId;
		} catch (e) {
			this.showError(e);
			return;
		}

		if(compatibleParts)
			mb.showLoading('getting the compatible available parts list');
		else
		mb.showLoading('getting the available parts list');
		tmp.request = new Prado.CallbackRequest(callbackId, {
			'onComplete' : function(sender, parameter) {
				if(compatibleParts)
					pageJs.showAvailList(goodParts,'Compatible');
				else
					pageJs.showAvailList(goodParts,'');
			}
		});

		tmp.callParams = {
			'selectedIds' : tmp.selectedRquestIds
		};
		tmp.callParams['goodParts'] = goodParts;
		tmp.callParams['compatibleParts'] = compatibleParts;
		tmp.request.setCallbackParameter(tmp.callParams);
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();
	},

	// display the avail list in Modalbox
	showAvailList : function(goodParts,prefix) {
		var type = '';
		if (goodParts == 1) {
			type = '(Good) ';
		} else {
			type = '(Not Good) ';
		}
		Modalbox
				.show(
						new Element('div')
								.update($(this.reponseHolderId).innerHTML),
						{
							beforeLoad : function() {
								Modalbox.activate();
							},
							title : type
									+ prefix +' Part(s) in '
									+ $(this.whHolderId).options[$(this.whHolderId).selectedIndex].text
									+ ': ',
							width : pageJs.resPaneWith
						});
	},

	// view Resrvd parts list
	viewResrvdList : function(requestId, callbackId) {
		var tmp = {};
		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds(requestId);
		} catch (e) {
			this.showError(e);
			return;
		}

		mb.showLoading('getting reserved parts list');

		tmp.request = new Prado.CallbackRequest(callbackId, {
			'onComplete' : function(sender, parameter) {
				pageJs.showResrvdList()
			}
		});
		tmp.request.setCallbackParameter({
			'selectedIds' : tmp.selectedRquestIds
		});
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();
	},

	// display the Resrvd list in Modalbox
	showResrvdList : function() {
		var tmp = {};
		Modalbox.show(new Element('div')
				.update($(this.reponseHolderId).innerHTML), {
			beforeLoad : function() {
				Modalbox.activate();
			},
			title : 'Reserved Part(s) for selected request:',
			width: 700
		});
	},

	// search for part instance to reserve
	reservePI : function(requestId, callbackId, unsrvCallbackId, clickedBtn,
			callbackIdCheck, checkPartType, checkPartErrors, errorBL) {

		var tmp = {};
		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds(requestId);
		} catch (e) {
			this.showError(e);
			return;
		}

		// getting the new tr with elements
		tmp.resrvInfo = {};
		tmp.resrvNewPartTR = $(clickedBtn).up('tr[resrpartpan="reservedTr"]');
		tmp.resrvNewPartBarcode = tmp.resrvNewPartTR
				.down('input[resrpartpan="reservedSerialNoSearch"]');
		tmp.resrvNewPartBL = tmp.resrvNewPartTR
				.down('input[resrpartpan="reservedBLNoSearch"]');
		tmp.resrvComments = tmp.resrvNewPartTR
				.down('input[resrpartpan="reservedComments"]');
		tmp.resrvNewPartQty = tmp.resrvNewPartTR
				.down('input[resrpartpan="reservedQty"]');
		tmp.bpregex = new RegExp(tmp.resrvNewPartTR
				.down('input[resrpartpan="bpregex"]').value.strip());
		tmp.partregex = new RegExp(tmp.resrvNewPartTR
				.down('input[resrpartpan="partregex"]').value.strip());

		// forming up the information
		tmp.resrvInfo.barcode = tmp.resrvNewPartBarcode.value.strip();
		tmp.resrvInfo.BL = tmp.resrvNewPartBL.value.strip();
		tmp.resrvInfo.comments = tmp.resrvComments.value.strip();
		tmp.resrvInfo.qty = tmp.resrvNewPartQty.value;
		if (!tmp.resrvInfo.qty.match(/^\d+$/)) {
			tmp.resrvNewPartQty.focus();
			this.showError('Invalid qty!');
			return;
		}

		//if BP and no BL then focus on BL
		tmp.barcode = tmp.resrvInfo.barcode;
		if(tmp.barcode.match(/BP|BCP/i) != "" && tmp.barcode.match(/BP|BCP/i) !== null)
		{
		   if(tmp.resrvInfo.BL === '' || tmp.resrvInfo.BL === null)
		   {
			   tmp.resrvNewPartBL.focus();
			   this.skipQtyCheck=false;
			   return false;
		   }
		   else
		   {
			   if(!this.skipQtyCheck)
			   {
				   this.skipQtyCheck=true;
				   tmp.resrvNewPartQty.focus();
				   return false;
			   }
		   }
		}
		// check barcode to see whether it's a valid part barcode or parttype
		// barcode
		if (tmp.resrvInfo.barcode.match(tmp.partregex) === null) {
			tmp.resrvNewPartBarcode.value = '';
			tmp.resrvNewPartBarcode.focus();
			this.showError('Invalid barcode(= ' + tmp.resrvInfo.barcode + ')!');
			return;
		}

		$(clickedBtn).disabled = true;
		$(clickedBtn).value = 'reserving...';
		var process = false;
		tmp.request = new Prado.CallbackRequest(callbackIdCheck, {
			'onComplete' : function(sender, parameter) {

				process = true;

				if($(errorBL).value != "")
				{
					process = false;
					alert($(errorBL).value);
					$(clickedBtn).disabled = false;
					$(clickedBtn).value = 'Add';
					tmp.resrvNewPartBL.focus();
				}

				if(process)
				{
					if($(checkPartErrors).value != "")
					{
						process = false;
						$(clickedBtn).disabled = false;
						$(clickedBtn).value = 'Add';
						alert($(checkPartErrors).value);
					}
				}

				if(process)
				{
					if($(checkPartType).value != '')
					{
						process = false;
						$(clickedBtn).disabled = false;
						$(clickedBtn).value = 'Add';
						if(confirm($(checkPartType).value)) {
							process = true;
						}
					}
				}

				if (process) {

					// disable serial number input field
					$(clickedBtn).disabled = true;
					tmp.clickedBtnValue = $(clickedBtn).value;
					$(clickedBtn).value = 'reserving...';
					tmp.resrvNewPartBarcode.disabled = true;
					tmp.resrvNewPartQty.disabled = true;

					// cleanup
					tmp.resrvNewPartBarcode.value = '';
					tmp.resrvNewPartQty.value = '1';

					// send the request
					Modalbox.deactivate(); // deactive the ui
					tmp.request = new Prado.CallbackRequest(callbackId, {
						'onComplete' : function(sender, parameter) {

							$(clickedBtn).value = tmp.clickedBtnValue;
							$(clickedBtn).disabled = false;
							tmp.resrvNewPartBarcode.disabled = false;
							tmp.resrvNewPartQty.disabled = false;
							Modalbox.activate();

							pageJs.updateRsrvdPartList(requestId,
									tmp.resrvNewPartTR.up(), unsrvCallbackId);

							tmp.resrvNewPartBarcode.focus();

						}
					});
					tmp.request.setCallbackParameter({
						'selectedIds' : tmp.selectedRquestIds,
						'resrvInfo' : tmp.resrvInfo
					});
					tmp.request.dispatch();

				}

			}
		});

		tmp.request.setCallbackParameter({
			'selectedIds' : tmp.selectedRquestIds,
			'resrvInfo' : tmp.resrvInfo
		});
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();
	},

	// post script for reservePI function
	updateRsrvdPartList : function(requestId, tbody, unsrvCallbackId) {
		var tmp = {};
		// if there is no data return, assume the results contains error!
		try {
			tmp.result = this.analyzeResp();
		} catch (e) {
			return;
		}

		// udpate the result List
		if (tmp.result.requests !== undefined) {
			tmp.result.requests.each(function(request) {
				pageJs.reloadRow(request.id, request, false);
			});
		}
		this.viewResrvdList(requestId, this.showRsrvdBtnId);

	},
	// reapply the css style to result table, so they will look like zebra style
	resApplyClassName : function(tbody) {
		var tmp = {};
		tmp.tbody = tbody;
		tmp.i = 0;
		tmp.tbody.select('tr').each(function(item) {
			// removes the original class name
			item.removeClassName(tmp.trClass);
			if (tmp.i % 2 === 0)
				tmp.trClass = 'ResultDataListAlterItem';
			else
				tmp.trClass = 'ResultDataListItem';
			item.removeClassName(tmp.trClass);

			// add the new classname
			item.addClassName(tmp.trClass);

			tmp.i++;
		});
	},
	// unreserve pi
	unresrvPI : function(requestId, callbackId, clickedBtn) {
		var tmp = {};
		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds(requestId);
		} catch (e) {
			this.showError(e);
			return;
		}
		$(clickedBtn).disabled = true;
		$(clickedBtn).src = this.loadingImage;
		tmp.delImage = this.delImage;

		tmp.resrvNewPartTR = $(clickedBtn).up('tr');
		tmp.unResrvInfo = {};
		tmp.unResrvInfo.piId = tmp.resrvNewPartTR.readAttribute('rsvdpiid');
		tmp.unResrvInfo.comment = prompt(
				"Please provide reason for unreserving this part",
				"");
		if (tmp.unResrvInfo.comment === null) {
			$(clickedBtn).src = tmp.delImage;
			$(clickedBtn).disabled = false;
			return false;
		}
		tmp.unResrvInfo.comment = tmp.unResrvInfo.comment.strip();
		if (tmp.unResrvInfo.comment.empty()) {
			this.showError("Please provide reason for unreserving this part");
			$(clickedBtn).src = tmp.delImage;
			$(clickedBtn).disabled = false;
			return false;
		}

		// send the request
		tmp.request = new Prado.CallbackRequest(callbackId, {
			'onComplete' : function(sender, parameter) {
				$(clickedBtn).src = tmp.delImage;
				$(clickedBtn).disabled = false;
				pageJs.updateRsrvdPartList(requestId, tmp.resrvNewPartTR.up());
			}
		});
		tmp.request.setCallbackParameter({
			'selectedIds' : tmp.selectedRquestIds,
			'unResrvInfo' : tmp.unResrvInfo
		});
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();
	},
	// scanBarcode do click button for barcode scanning
	enterEvent : function(event, buttonToClick) {
		if ((event.which && event.which == 13)
				|| (event.keyCode && event.keyCode == 13)) {
			buttonToClick.click();
			return false;
		}
		return true;
	},
	updateListForAutoTake : function(autoTake, selectedRequestIds) {
		var tmp = {};
		if (pageJs.checkAutoTake(autoTake)) {
			tmp.request = new Prado.CallbackRequest(
					pageJs.refreshInfo.refreshCallBackId, {
						'onComplete' : function(sender, parameter) {

							try {
								tmp.result = pageJs.analyzeResp();
								tmp.result.requests
										.each(function(request) {
											pageJs.reloadRow(request.id,
													request, true);
										});
							} catch (er) {
							}

						}
					});
			tmp.request.setCallbackParameter({
				'ids' : selectedRequestIds,
				'firstid' : '',
				'searchRequest' : pageJs.searchRequest
			});
			tmp.request.dispatch();
		}
	},
	// click event for print pick list
	printPickList : function(callbackId, clickedBtn, currentUser, autoTake) {
		var tmp = {};
		// gathering data
		tmp.selectedRquestIds = this.selectedRequestIds;

		// check if selected any
		if (tmp.selectedRquestIds.length === 0) {
			this.showError('Please select some items first!');
			return;
		}

		// show UI for disabled printing Btn, once btn clicked
		mb.showLoading('generating pick list');

		// send the request
		tmp.request = new Prado.CallbackRequest(callbackId, {
			'onComplete' : function(sender, parameter) {
				// open the new window for printing
				pageJs.writeConsole('Pick List For Selected Request(s) - By '
						+ currentUser, $(pageJs.reponseHolderId).innerHTML);
				Modalbox.hide();
				pageJs.updateListForAutoTake(autoTake, tmp.selectedRquestIds);

			}
		});
		tmp.request.setCallbackParameter({
			'selectedIds' : this.selectedRequestIds
		});
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();
	},
	// writes a content to a new open widow
	writeConsole : function(title, content) {
		var top = {};
		top.consoleRef = this.openNewWindow('', 'newWindow',
				this.openNewWindowParams);
		top.consoleRef.document.writeln('<html><head><title>' + title
				+ '</title></head>' + '<body>' + content + '</body></html>');
		top.consoleRef.document.close();
	},
	// open part delivery lookup page
	openNewWindow : function(url, title, params) {
		if (params === undefined)
			params = this.openNewWindowParams;
		params = '';
		var tmp = window.open(url, title, params);
		if (tmp.focus) {
			tmp.focus();
		}
		return tmp;
	},

	// show comments div
	showComments : function(requestId, callbackId, clickedbtn) {
		var tmp = {};
		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds(requestId);
		} catch (e) {
			this.showError(e);
			return;
		}

		mb.showLoading('getting comments for the selected request');
		tmp.request = new Prado.CallbackRequest(callbackId, {
			'onComplete' : function(sender, parameter) {
				try {
					Modalbox.show(new Element('div')
							.update($(pageJs.reponseHolderId).innerHTML), {
						beforeLoad : function() {
							Modalbox.activate();
						},
						title : 'Comments for selected request',
						width : pageJs.resPaneWith
					});
				} catch (e) {
				}
			}
		});
		tmp.request.setCallbackParameter({
			'selectedIds' : tmp.selectedRquestIds
		});
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();

		return false;
	},
	// submit new comments
	sumbmitNewComments : function(requestId, callbackId, clickedBtn) {
		var tmp = {};
		tmp.selectedRquestIds = [];
		tmp.selectedRquestIds.push(requestId);

		// disabled the clicked button
		$(clickedBtn).disabled = true;
		tmp.clickedBtnValue = $(clickedBtn).value;
		$(clickedBtn).value = 'adding ...';

		// capture the new comments users typed in
		tmp.commentsDiv = $(clickedBtn).up('div[detailspanel="detailspanel"]');
		tmp.newCommentHolder = tmp.commentsDiv
				.down('[detailsPanel="newComments"]');
		tmp.newComment = tmp.newCommentHolder.value.strip();
		if (tmp.newComment.empty()) {
			this.showError('Empty comments NOT allow!');
			$(clickedBtn).disabled = false;
			$(clickedBtn).value = tmp.clickedBtnValue;
			return false;
		}

		tmp.request = new Prado.CallbackRequest(callbackId, {
			'onComplete' : function(sender, parameter) {
				$(clickedBtn).disabled = false;
				$(clickedBtn).value = tmp.clickedBtnValue;
				pageJs.updateCommentsList(tmp.commentsDiv
						.down('table[detailspanel="commentsList"]'));
			}
		});
		tmp.request.setCallbackParameter({
			'selectedIds' : tmp.selectedRquestIds,
			'newComment' : tmp.newComment
		});
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();

		tmp.newCommentHolder.value = '';
		return false;
	},
	// post adding new comments
	updateCommentsList : function(commentsTable) {
		var tmp = {};
		tmp.tbody = commentsTable.down('tbody');

		try {
			tmp.result = this.analyzeResp();
		} catch (e) {
			return this.showError(e);
		}
		tmp.lastRow = tmp.tbody.getElementsBySelector('tr').first();
		tmp.newRowClassName = 'ResultDataListItem';
		if (tmp.lastRow.hasClassName(tmp.newRowClassName))
			tmp.newRowClassName = 'ResultDataListAlterItem';
		tmp.newRow = '<tr class="' + tmp.newRowClassName + '"><td>'
				+ tmp.result.user + '</td><td>' + tmp.result.time + '</td><td>'
				+ tmp.result.comment + '</td></tr>';
		tmp.lastRow.insert({
			before : tmp.newRow
		});

		Modalbox.resizeToContent();
		// update the result list
		if (tmp.result.requests !== undefined && tmp.result.requests.size() > 0)
			tmp.result.requests.each(function(resultRow) {
				pageJs.reloadRow(resultRow.id, resultRow, false);
			});
	},

	// sendEmail
	email : function(requestId, callbackId, clickedBtn) {
		var tmp = {};
		tmp.emailTo_newLine = '\n';

		tmp.resultTableId = this.resultTableId;

		// if there is a id passed in, it means it was called from the email
		// button from each line item.
		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds(requestId);
		} catch (e) {
			this.showError(e);
			return;
		}

		// gathering data
		tmp.emailBody = tmp.emailTo_newLine;
		tmp.rowNo = 1;
		var tasksSelected = '';
		tmp.selectedRquestIds
				.each(function(id) {
					tmp.currentRow = $$(
							'table#' + tmp.resultTableId + ' tr[requestid="'
									+ id + '"]').first();
					tmp.emailSubject = 'Task: '
							+ tmp.currentRow.down('[resultrow="fieldtaskid"]').innerHTML
							+ ' Requires Part Code: '
							+ tmp.currentRow.down('[resultrow="partcode"]').innerHTML
							+ ' Qty: '
							+ tmp.currentRow.down('[resultrow="requestedQty"]').innerHTML;
					if (tasksSelected != '') {
						tasksSelected += ", "
								+ tmp.currentRow
										.down('[resultrow="fieldtaskid"]').innerHTML;
					} else {
						tasksSelected += tmp.currentRow
								.down('[resultrow="fieldtaskid"]').innerHTML;
					}
					tmp.emailBody += tmp.emailTo_newLine;
					tmp.emailBody += '== REQUEST NO.: ' + tmp.rowNo
							+ '====================' + tmp.emailTo_newLine;
					tmp.emailBody += 'PART: '
							+ tmp.currentRow.down('[resultrow="partname"]').innerHTML
							+ tmp.emailTo_newLine;
					tmp.emailBody += 'PART CODE: '
							+ tmp.currentRow.down('[resultrow="partcode"]').innerHTML
							+ tmp.emailTo_newLine;
					tmp.emailBody += 'REQUESTED Qty: '
							+ tmp.currentRow.down('[resultrow="requestedQty"]').innerHTML
							+ tmp.emailTo_newLine;
					tmp.emailBody += 'FIELD TASK: '
							+ tmp.currentRow.down('[resultrow="fieldtaskid"]').innerHTML
							+ tmp.emailTo_newLine;
					tmp.emailBody += 'SLA END: '
							+ tmp.currentRow.down('[resultrow="slaend"]').innerHTML
							+ tmp.emailTo_newLine;
					tmp.emailBody += 'SITE: '
							+ tmp.currentRow.down('[resultrow="site"]').innerHTML
							+ tmp.emailTo_newLine;
					tmp.emailBody += 'CONTRACT: '
							+ tmp.currentRow.down('[resultrow="worktype"]').innerHTML
							+ tmp.emailTo_newLine;
					tmp.emailBody += 'ZONE SET: '
							+ tmp.currentRow.down('[resultrow="zoneset"]').innerHTML
							+ tmp.emailTo_newLine;
					tmp.emailBody += '===================='
							+ tmp.emailTo_newLine;

					tmp.rowNo++;
				});
		if (tmp.rowNo > 2) {
			tmp.emailSubject = "Multiple Facility Requests for Tasks: "
					+ tasksSelected;
		}

		Modalbox.hide();
		tmp.request = new Prado.CallbackRequest(callbackId);
		tmp.callParams = {
			'selectedIds' : tmp.selectedRquestIds
		};
		tmp.callParams['body'] = tmp.emailBody;
		tmp.callParams['subject'] = tmp.emailSubject;
		tmp.request.setCallbackParameter(tmp.callParams);
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();

		return false;
	},

	// show the status changing Div
	showChangeStatusDiv : function(requestId, callbackId, newstatus, clickedBtn) {
		var tmp = {};
		// if there is a id passed in, it means it was called from the email
		// button from each line item.
		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds(requestId);
		} catch (e) {
			this.showError(e);
			return;
		}

		// checking ownership
		tmp.errors = [];
		tmp.selectedRquestIds
				.each(function(id) {
					tmp.ownerPos = $(pageJs.resultTableId).down(
							'tr[requestid=' + id + '] td[resultrow="owner"]')
							.readAttribute('ownerPos').strip();
					tmp.ftStatus = $(pageJs.resultTableId).down(
							'tr[requestid=' + id + ']').down(
							'[resultrow="taskstatus"]').innerHTML;
					if (!tmp.ownerPos.startsWith(pageJs.owner.position.strip())) {
						tmp.emsg = '<div>';
						tmp.emsg += "<a href='javascript: void(0);' onclick=\"$$('tr[requestid="
								+ id
								+ "]').first().scrollTo(); return false;\" title='Click here to scroll to that request'>Request</a>: ";
						tmp.emsg += 'You do NOT have access to this request, take it before you do anything!';
						tmp.emsg += '</div>';
						tmp.errors.push(tmp.emsg)
					}
				});
		if (tmp.errors.size() > 0)
			Modalbox.show(new Element('div').update(tmp.errors.join(''))
					.setStyle({
						'color' : 'red',
						'font-weight' : 'bold'
					}), {
				afterLoad : function() {
					Modalbox.resizeToContent();
				},
				title : 'Error Occurred ... ',
				width : pageJs.resPaneWith
			});
		else {
			if (newstatus == 'cancel') {
				var confirmation = confirm('This task is in status '
						+ tmp.ftStatus
						+ ' .\nAre you sure you want to cancel this Facility Request?.\n\n');

				if (confirmation)
					this
							.showComfirmDiv(
									'Please provide a reason for changing the status to: '
											+ newstatus,
									"if($(this).up('div[commentdiv=commentdiv]').down('[commentdiv=comments]').value.strip().empty()){alert('Comments is compulsory!');return false;} pageJs.submitEachRequest(0, '"
											+ tmp.selectedRquestIds.join(',')
											+ "', '"
											+ callbackId
											+ "', Object.toJSON({comment: $(this).up('div[commentdiv=commentdiv]').down('[commentdiv=comments]').value.strip(), newStatus: '"
											+ newstatus + "'}))");
			} else
				this
						.showComfirmDiv(
								'Please provide a reason for changing the status to: '
										+ newstatus,
								"if($(this).up('div[commentdiv=commentdiv]').down('[commentdiv=comments]').value.strip().empty()){alert('Comments is compulsory!');return false;} pageJs.submitEachRequest(0, '"
										+ tmp.selectedRquestIds.join(',')
										+ "', '"
										+ callbackId
										+ "', Object.toJSON({comment: $(this).up('div[commentdiv=commentdiv]').down('[commentdiv=comments]').value.strip(), newStatus: '"
										+ newstatus + "'}))");
		}
	},
	// submit each id
	submitEachRequest : function(currentIndex, ids, callbackId, info, errors) {
		var tmp = {};
		tmp.callbackId = callbackId;
		tmp.requestIds = ids.split(',');
		if (tmp.requestIds.size() === 0)
			return this.showError("select some request(s) first!");

		tmp.info = Object.toJSON({});
		if (info !== undefined)
			tmp.info = info;
		tmp.errors = [];
		if (errors !== undefined)
			tmp.errors = errors;

		// show the processing div
		if (currentIndex === 0) {
			tmp.newDiv = '<div processingdiv="processingdiv">Processing <b processingdiv="processingindex">1</b> of <b processingdiv="totalrequestcout">'
					+ tmp.requestIds.size() + '</b></div>';
			Modalbox.show(new Element('div').update(tmp.newDiv), {
				beforeLoad : function() {
					Modalbox.deactivate();
				},
				title : 'Processing request(s) ... ',
				width : pageJs.resPaneWith
			});
		}

		tmp.currentRequestId = [];
		tmp.currentRequestId.push(tmp.requestIds[currentIndex]);
		tmp.request = new Prado.CallbackRequest(callbackId, {
			'onComplete' : function(sender, parameter) {
				tmp.errors = pageJs.postSubmitEachRequest(currentIndex, ids,
						callbackId, info, tmp.errors);
			}
		});

		tmp.request.setCallbackParameter({
			'selectedIds' : tmp.currentRequestId,
			'info' : tmp.info.evalJSON(),
			'retainSelectedIds' : tmp.currentRequestId
		});
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();

	},

	// postsumbit each request
	postSubmitEachRequest : function(currentIndex, ids, callbackId, info,
			errors) {
		var tmp = {};
		tmp.info = info;
		tmp.requestIds = ids.split(',');
		tmp.callbackId = callbackId;
		tmp.info = info;
		tmp.errors = errors;

		// update the result list with new data
		try {
			tmp.result = this.analyzeResp(true);
			if (tmp.result.requests !== undefined) {
				tmp.result.requests.each(function(request) {
					pageJs.reloadRow(request.id, request, true);
				});
			}
		} catch (e) {
			tmp.errors.push({
				'requestId' : tmp.requestIds[currentIndex],
				'emsg' : e
			});
		}

		// if we have more to run, we run for the next one
		if ((currentIndex * 1) < (tmp.requestIds.size() - 1)) {
			tmp.processingIndexDiv = $$(
					'div[processingdiv="processingdiv"] [processingdiv="processingindex"]')
					.first();
			if (tmp.processingIndexDiv !== undefined
					&& tmp.processingIndexDiv !== null)
				tmp.processingIndexDiv.update((currentIndex * 1) + 1);
			this.submitEachRequest((currentIndex * 1) + 1, tmp.requestIds
					.join(','), tmp.callbackId, tmp.info, tmp.errors);
		}
		// display the end result, when it's all finished
		else {
			tmp.newDiv = '<b style="color: green;"> Updated successfully! </b>';
			if (tmp.errors.size() > 0) {
				tmp.newDiv = '<b style="color: red;"> Error(s) occurred: </b>';
				tmp.newDiv += '<table class="ResultDataList">';
				tmp.newDiv += '<thead>';
				tmp.newDiv += '<tr>';
				tmp.newDiv += '<th>Row Info</th>';
				tmp.newDiv += '<th>Result</th>';
				tmp.newDiv += '</tr>';
				tmp.newDiv += '</thead>';
				tmp.newDiv += '<tbody>';
				tmp.i = 0;
				tmp.errors
						.each(function(error) {
							tmp.newTRClass = (tmp.i % 2 === 0 ? 'ResultDataListAlterItem'
									: 'ResultDataListItem');
							tmp.newDiv += '<tr class="' + tmp.newTRClass + '">';
							tmp.newDiv += "<td><a href='javascript: void(0);' onclick=\"$$('tr[requestid="
									+ error.requestId
									+ "]').first().scrollTo(); return false;\">Row(id="
									+ error.requestId + ")</a>: </td>";
							tmp.newDiv += '<td>' + error.emsg + '</td>';
							tmp.newDiv += '</tr>';
							tmp.i = (tmp.i * 1) + 1;
						});
				tmp.newDiv += '</tbody>';
				tmp.newDiv += '</table>';
				tmp.newDiv += '<br /><input type="button" value="close" onclick="Modalbox.hide();" />';
			}

			if (tmp.errors.size() > 0) {
				Modalbox.show(new Element('div').update(tmp.newDiv), {
					beforeLoad : function() {
						Modalbox.activate();
					},
					afterLoad : function() {
						Modalbox.resizeToContent();
					},
					title : 'Actions finished!',
					width : pageJs.resPaneWith
				});
			} else {
				Modalbox.show(new Element('div').update(tmp.newDiv), {
					beforeLoad : function() {
						Modalbox.activate();
					},
					afterLoad : function() {
						Modalbox.resizeToContent();
						setTimeout('Modalbox.hide();', '1000');
					},
					title : 'Actions finished!',
					width : pageJs.resPaneWith
				});
			}
			// this.checkAll(new Element('input',{'type': 'checkbox', 'checked':
			// false}));//deselect all
		}

		return tmp.errors;
	},

	// show Comfirm Div
	showComfirmDiv : function(divTitle, callbackfunction) {
		var tmp = {};
		tmp.newDiv = '<div commentdiv="commentdiv">';
		tmp.newDiv += '<h3 commentdiv="title">' + divTitle + '</h3>';
		tmp.newDiv += '<textarea commentdiv="comments" style="width: 98%;"></textarea>';
		tmp.newDiv += '<input commentdiv="submitBtn" value="YES" type="button" onclick="'
				+ callbackfunction + '; return false;"/>';
		tmp.newDiv += '<input value="NO" onclick="Modalbox.hide(); return false;" type="button"/>';
		tmp.newDiv += '</div>';
		Modalbox.show(new Element('div').update(tmp.newDiv), {
			'title' : divTitle,
			'width' : pageJs.resPaneWith
		});
	},
	// show perferences div
	showPreferences : function(callbackId, clickedBtn) {
		this
				.showError("This function is NOT ready yet!\nPlease watch this space here!");
		return false;
	},

	// show Comfirm Div
	confirmPushFT : function(message, confirmCallbackBtnId, newstatus,
			requestId) {
		var tmp = {};
		tmp.message= message[0].unescapeHTML();
		tmp.newhtml = "<div confirmbox='confirmAvailable'>"
		tmp.newhtml += "<b>Please confirm below :</b><br /><br />";
		tmp.newhtml += "<b>Are you sure you want to push the selected fieldtask(s) to "
				+ newstatus + "?</b><br /><br />";
		tmp.newhtml += "<b>Warning:</b><br />";
		tmp.newhtml += tmp.message+ "<br />";
		tmp.newhtml += "<input type='button' newpreference='saveBtn' onclick=\"pageJs.pushFT('"
				+ confirmCallbackBtnId
				+ "','"
				+ newstatus
				+ "', "
				+ requestId
				+ ", true); return false;\" value='Proceed'>";
		tmp.newhtml += "<input type='button' onclick='Modalbox.hide(); return false;' value='Cancel'>";
		tmp.newhtml += "</div>";
		Modalbox.show(new Element('div').update(tmp.newhtml), {
			'title' : 'Confirm Task',
			'width' : '1000'
		});
		return false;

	},

	showConfirmPushFt : function(message, confirmCallbackBtnId, callbackId,
			newstatus, requestId, checkflag) {
		var tmp = {};
		tmp.me = this;
		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds(requestId);
		} catch (e) {
			this.showError(e);
			return;
		}

		tmp.request = new Prado.CallbackRequest(this.createWarningBtn, {
			'onComplete' : function(sender, parameter) {
				try {
					tmp.result = pageJs.analyzeResp();
					if (checkflag) {
						// confirm task with the FRs
						tmp.me.confirmPushFT(tmp.result, callbackId, newstatus,
								requestId);
					} else {
						// then push FT
						tmp.me.pushFT(callbackId, newstatus, requestId, true);
					}

				} catch (e) {
					this.showError(e);
					return;
				}
			}
		});

		tmp.callParams = {
			'selectedIds' : tmp.selectedRquestIds
		};
		tmp.request.setCallbackParameter(tmp.callParams);
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();
	},

	checkRervedForSelected : function(selectedRquestIds) {
		var tmp = {};
		tmp.selectedRquestIds = selectedRquestIds;
		tmp.errormessage = "";
		tmp.resultTableId = this.resultTableId;
		tmp.selectedRquestIds
				.each(function(id) {

					tmp.currentRow = $$(
							'table#' + tmp.resultTableId + ' tr[requestid="'
									+ id + '"]').first();
					tmp.currentRow = tmp.currentRow
							.down('[resultrow="reserved"]');
					tmp.countReserved = tmp.currentRow
							.down('[atag="reserved"]').innerHTML;
					if (tmp.countReserved == 0) {
						tmp.errormessage += "Some of the facility requests have zero reserved parts against them!\n";
					}

				});
		return tmp.errormessage;

	},
	// push FT to avail
	pushFT : function(callbackId, newstatus, requestId, confirmFlag) {
		var tmp = {};
		tmp.me = this;
		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds(requestId);
		} catch (e) {
			this.showError(e);
			return;
		}

		tmp.resultTableId = '';
		if (tmp.me.checkRervedForSelected(tmp.selectedRquestIds) != "") {
			tmp.errormessage = "Some of the facility requests have zero reserved parts against them!\n";
			tmp.errormessage += "You must have reservations against all the requests to Push To Available."
			alert(tmp.errormessage);
		} else {
			tmp.message = '';
			if (!confirmFlag || confirmFlag === undefined) {
				tmp.me.showConfirmPushFt(tmp.message, this.createWarningBtn,
						callbackId, newstatus, requestId, true);
				return false;
			}

			tmp.resultTable = $(this.resultTableId);
			tmp.fieldTaskArray = {};
			// looping through all ids:
			// - select those request that has the same field task id, as we are
			// pushing the field task status here!!!
			this.selectedRequestIds.each(function(id) {
				tmp.resultRowTr = tmp.resultTable.down('tr[requestid="' + id
						+ '"]');
				if (tmp.resultRowTr !== undefined) {
					tmp.fieldTaskId = tmp.resultRowTr
							.readAttribute('fieldtaskid');
					// push the field task id into array for processing
					if (tmp.fieldTaskArray[tmp.fieldTaskId] === undefined)
						tmp.fieldTaskArray[tmp.fieldTaskId] = [];

					tmp.fieldTaskArray[tmp.fieldTaskId].push(id);
				}
			});

			// start submission
			this.submitEachRequest(0, tmp.selectedRquestIds.join(','),
					callbackId, Object.toJSON({
						'newstatus' : newstatus,
						'fieldTaskArray' : tmp.fieldTaskArray
					}));

		}

	},

	// on change event for preference list
	changePreferences : function(list, callBackId, chgCallBackId,
			search_callbackId) {
		var tmp = {};
		tmp.selectedPvalue = $(list).value.strip();
		if (tmp.selectedPvalue.empty())
			return false;

		// list all preference for deleting
		if (tmp.selectedPvalue === 'chg') {
			this.showPreferenceList(callBackId, list);
			return;
		}

		// change preferences selections
		mb.showLoading('changing view');

		tmp.request = new Prado.CallbackRequest(chgCallBackId, {
			'onComplete' : function(sender, parameter) {

				// search with normal search
				pageJs.search(search_callbackId, true);
			}
		});
		tmp.request.setCallbackParameter({
			'name' : tmp.selectedPvalue
		});
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();
	},

	// showing the confirmation for adding a new preference
	confirmAddView : function(addNewPCallbackId, viewPListCallbackId,
			dropDownListId) {
		var tmp = {};
		tmp.newhtml = "<div newpreference='newpreferencediv'>"
		tmp.newhtml += "Please give the current seach criteria a name that you can use for the future:<br />";
		tmp.newhtml += "<input newpreference='newname' style='width:95%;' onkeydown=\"pageJs.enterEvent(event, $(this).up('div[newpreference=newpreferencediv]').down('input[newpreference=saveBtn]'));\">";
		tmp.newhtml += "<input type='button' newpreference='saveBtn' onclick=\"pageJs.addNewPreference('"
				+ addNewPCallbackId
				+ "', '"
				+ viewPListCallbackId
				+ "', '"
				+ dropDownListId + "', this); return false;\" value='save'>";
		tmp.newhtml += "<input type='button' onclick='Modalbox.hide(); return false;' value='cancel'>";
		tmp.newhtml += "</div>";
		Modalbox.show(new Element('div').update(tmp.newhtml), {
			'title' : 'Saving New Search Criteria',
			'width' : pageJs.resPaneWith
		});
		return false;
	},

	// adding a new preference
	addNewPreference : function(callBackId, viewPreferenceCallBackId,
			dropDownListId, clickedBtn) {
		var tmp = {};
		tmp.newname = $(clickedBtn).up('div').down(
				'input[newpreference="newname"]').value.strip();
		if (tmp.newname.empty()) {
			this.showError("A preference name is needed!");
			return;
		}

		// loop through the droplist list to see whether we've got the name
		// already
		tmp.nameExsits = false;
		$(dropDownListId)
				.select('option')
				.each(
						function(item) {
							if (item.value.strip() === tmp.newname) {
								if (!confirm('There is one preference called: '
										+ tmp.newname
										+ ' in your list, do you wish to overwrite that?')) {
									tmp.nameExsits = false;
									return false;
								} else {
									tmp.nameExsits = true;
								}
							}
						});

		// not found or overwrite the exsiting name
		if (tmp.nameExsits === true || tmp.nameExsits === false) {
			$(clickedBtn).disabled = true;
			tmp.clickedBtnvalue = $(clickedBtn).value;
			$(clickedBtn).value = "saving...";

			tmp.request = new Prado.CallbackRequest(
					callBackId,
					{
						'onComplete' : function(sender, parameter) {
							$(clickedBtn).disabled = false;
							$(clickedBtn).value = tmp.clickedBtnvalue;
							tmp.newhtml = 'New Preference Saved!';
							try {
								tmp.result = pageJs.analyzeResp(true);
							} catch (e) {
								tmp.newhtml = '<b style="color: red;">' + e
										+ '</b>';
							}

							tmp.newhtml += '<input type="button" value="OK" onclick="Modalbox.hide(); return false;"/>';
							Modalbox.show(new Element('div')
									.update(tmp.newhtml), {
								'title' : 'Saved',
								'width' : pageJs.resPaneWith
							});

							// update the preference list
							if (tmp.result !== undefined
									&& tmp.result.name !== undefined
									&& tmp.nameExsits === false)
								$(dropDownListId).select('option').last()
										.insert(
												{
													before : '<option value="'
															+ tmp.result.name
															+ '">'
															+ tmp.result.name
															+ '</option>'
												});
							// pageJs.showPreferenceList(viewPreferenceCallBackId);

						}
					});
			tmp.request.setCallbackParameter({
				'name' : tmp.newname,
				'action' : 'add'
			});
			tmp.request.setRequestTimeOut(this.maxExecutionTime);
			tmp.request.dispatch();
		}
	},

	// deleting a preference
	delPreference : function(callBackId, dropDownListId, clickedBtn) {
		var tmp = {};
		tmp.newname = $(clickedBtn).value.strip();
		if (!confirm('Are you sure you want to delete this preference(='
				+ tmp.newname + ')?'))
			return false;

		mb.showLoading('deleting');

		tmp.request = new Prado.CallbackRequest(
				callBackId,
				{
					'onComplete' : function(sender, parameter) {
						tmp.newhtml = 'Selected Preference Deleted Successfully!';
						try {
							tmp.result = pageJs.analyzeResp(true);
						} catch (e) {
							tmp.newhtml = "<b style='color: red;'> " + e
									+ "</b>";
						}

						tmp.newhtml += '<input type="button" value="OK" onclick="Modalbox.hide(); return false;"/>';
						Modalbox.show(new Element('div').update(tmp.newhtml), {
							'title' : 'Deleting Result',
							'width' : pageJs.resPaneWith
						});

						// update the preference list
						if (tmp.result !== undefined
								&& tmp.result.name !== undefined)
							$(dropDownListId).select(
									'option[value="' + tmp.result.name + '"]')
									.first().remove();

					}
				});
		tmp.request.setCallbackParameter({
			'name' : tmp.newname,
			'action' : 'del'
		});
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();

		return false;
	},

	// showPreferenceList
	showPreferenceList : function(callBackId, dropDownListId) {
		var tmp = {};

		mb.showLoading('getting preference list');

		// reset preference list
		this.resetPreferenceList(dropDownListId);

		tmp.request = new Prado.CallbackRequest(callBackId, {
			'onComplete' : function(sender, parameter) {
				Modalbox.show(new Element('div')
						.update($(pageJs.reponseHolderId).innerHTML), {
					beforeLoad : function() {
						Modalbox.activate();
					},
					title : 'Your View Preferences: ',
					width : pageJs.resPaneWith
				});
			}
		});
		tmp.request.setCallbackParameter({
			'name' : tmp.newname,
			'action' : 'add'
		});
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();
	},

	resetPreferenceList : function(dropDownListId) {
		// unselect the item from the list
		$(dropDownListId).select('option').each(function(item) {
			if (item.value.strip() === '')
				item.selected = true;
			else
				item.selected = false;
		});
	},

	// start referesh
	startTimer : function() {
		var tmp = {};
		// get all reuqest ids
		this.checkAll(new Element('input', {
			'type' : 'checkbox',
			'checked' : true
		}));// select all
		tmp.selectedIds = this.selectedRequestIds;
		this.checkAll(new Element('input', {
			'type' : 'checkbox',
			'checked' : false
		}));// de-select all

		try {
			if ($(this.resultTableId).down('tbody').select('tr.resultRow')
					.first() != null) {
				tmp.firstId = $(this.resultTableId).down('tbody').select(
						'tr.resultRow').first().readAttribute('requestid')
						.strip();
			} else {
				tmp.firstId = '';
			}
		} catch (e) {
			tmp.firstId = '';
		}

		if (tmp.selectedIds.size() <= 0) {
			tmp.selectedIds = '';
		}
		this.refreshInfo.timer = new PeriodicalExecuter(function() {
			tmp.request = new Prado.CallbackRequest(
					pageJs.refreshInfo.refreshCallBackId, {
						'onComplete' : function(sender, parameter) {
							pageJs.postRefresh();
						}
					});
			tmp.request.setCallbackParameter({
				'ids' : tmp.selectedIds,
				'firstid' : tmp.firstId,
				'searchRequest' : pageJs.searchRequest
			});
			tmp.request.dispatch();
		}, this.refreshInfo.maxExeTime);

	},

	// stop referesh
	stopTimer : function() {
		if (this.refreshInfo.timer !== null)
			this.refreshInfo.timer.stop();

		this.refreshInfo.timer = null;
	},

	// called by startTimer's onComplete
	postRefresh : function() {
		var tmp = {};
		tmp.resultTableBody = $(this.resultTableId).down('tbody');
		tmp.updateContentCSS = 'updatedContent';
		tmp.updateRowCSS = 'updatedRow';
		try {
			tmp.result = this.analyzeResp();

			// check whether there is new request coming in
			if (tmp.result.hasnew !== undefined && tmp.result.hasnew === true) {
				tmp.btnPane = tmp.resultTableBody.up('div#resultPanelWrapper')
						.down('#btnPane');
				if ($(this.refreshInfo.reloadPaneId) === undefined
						|| $(this.refreshInfo.reloadPaneId) === null) {
					this.foundNewFrIds = tmp.result.hasnew_frId;
					tmp.refreshBtn = '<a style="margin-right: 25px;" href="javascript: void(0);" id="'
							+ this.refreshInfo.reloadPaneId
							+ '" title="Reload the page with new requests" onclick="pageJs.search(pageJs.searchRequest.search_callbackId, true); return false;"><img src="/themes/images/refresh.gif" /> <b>Found new request(s)</b></a>';
					tmp.btnPane.down().insert({
						before : tmp.refreshBtn
					});
				}
			}

			tmp.result.requests
					.each(function(item) {
						tmp.foundDiff = false;
						tmp.row = tmp.resultTableBody.select(
								'tr.resultRow[requestid="' + item.id + '"]')
								.first();
						// check ft status
						tmp.foundDiff = pageJs.updateContent(item.taskStatus
								.strip(), tmp.row
								.down('[resultrow="taskstatus"]'),
								tmp.updateContentCSS, tmp.foundDiff, true);

						// check fr status
						tmp.foundDiff = pageJs.updateContent(item.status
								.strip(), tmp.row.down('[resultrow="status"]'),
								tmp.updateContentCSS, tmp.foundDiff, false);

						// check reserved
						if (item.reserved === null)
							item.reserved = '0';
						tmp.foundDiff = pageJs.updateContent(item.reserved
								.strip(), tmp.row
								.down('[resultrow="reserved"]').down('a'),
								tmp.updateContentCSS, tmp.foundDiff, true);

						// check sla end
						if (item.slaEnd === null)
							item.slaEnd = '';
						tmp.foundDiff = pageJs.updateContent(item.slaEnd
								.strip(), tmp.row.down('[resultrow="slaend"]'),
								tmp.updateContentCSS, tmp.foundDiff, true);

						// check ownership
						if (item.owner === null)
							item.owner = '';
						tmp.foundDiff = pageJs.updateContent(
								item.owner.strip(), tmp.row
										.down('div[resultrow="ownername"]'),
								tmp.updateContentCSS, tmp.foundDiff, true);

						if (tmp.foundDiff === true) {
							tmp.row.addClassName(tmp.updateRowCSS);
							// hide all buttons
							tmp.row
									.down('[resultrow="btns"]')
									.update(
											'<input type="image" src="/themes/images/refresh.gif" refreshdiv="button" onclick="return pageJs.reloadRow('
													+ item.id
													+ ', $(this.next('
													+ "'[refreshdiv=data]'"
													+ ')).innerHTML.strip().evalJSON(),false);" title="refresh this row"/><span refreshdiv="data" style="display: none;">'
													+ Object.toJSON(item)
													+ '</span>');

							// disable checkbox
							pageJs.selectOneItem(item.id, false);
							tmp.row.down('[resultrow="id"]').down(
									'input[type="checkbox"]').disabled = true;
						}
					});
		} catch (e) {
			// as this is refresh reques, so we don't want to show the users the
			// error!
			Logger.error(e);
		}
	},

	// update content of the different status
	updateContent : function(expectedValue, element, newClassName,
			foundDiffBefore, exactMatch) {
		var tmp = {};
		var changed = false;
		tmp.original = element.innerHTML.strip();

		if (exactMatch) {
			if (expectedValue !== tmp.original) {
				changed = true;
			}
		} else {
			if (tmp.original.indexOf(expectedValue) == -1) {
				changed = true;
			}
		}

		if (changed) {
			element.addClassName(newClassName);
			element.update(expectedValue);
			element.writeAttribute({
				'title' : tmp.original + ' => ' + expectedValue
			});

			return true; // found difference!
		}

		return foundDiffBefore;
	},

	// update the current row
	reloadRow : function(requestId, newData, checked) {
		try {
			var tmp = {};
			tmp.row = $(this.resultTableId).down(
					'tr[requestid="' + requestId + '"]');
			if (tmp.row === undefined || tmp.row === null)
				return false;

			tmp.data = newData;
			tmp.rowNo = tmp.row.readAttribute('rowno');
			tmp.newHtml = this.getResultTR(tmp.data, tmp.rowNo, checked);
			tmp.rowPrevious = tmp.row.previous();
			tmp.rowNext = tmp.row.next();
			tmp.row.remove();
			if (tmp.rowPrevious === undefined || tmp.rowPrevious === null) {
				if (tmp.rowNext === undefined || tmp.rowNext === null) {
					// only one record
					tmp.row = $(this.resultTableId).down('tbody');
					tmp.row.insert({
						top : tmp.newHtml
					});
				} else {
					tmp.rowNext.insert({
						before : tmp.newHtml
					});
				}
			} else {
				tmp.rowPrevious.insert({
					after : tmp.newHtml
				});
			}
			return false;
		} catch (e) {
			// console.debug(e);
		}
	},

	// take facility request
	takeFR : function(callbackId, requestId) {
		var tmp = {};

		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds(requestId);
		} catch (e) {
			this.showError(e);
			return;
		}

		if (!confirm('Are you sure you want to take selected request(s)?'))
			return;

		this.submitEachRequest(0, tmp.selectedRquestIds.join(','), callbackId);
	},

	// show the pushing panel
	showPushFR : function(callBackId, requestId) {
		var tmp = {};

		// gathering data
		try {
			tmp.selectedRquestIds = this.getSelectedIds(requestId);
		} catch (e) {
			this.showError(e);
			return;
		}
		var lastPushLocation = getCookie('lastPushLocation');
		mb.showLoading('loading');
		tmp.request = new Prado.CallbackRequest(callBackId, {
			'onComplete' : function(sender, parameter) {
				Modalbox.show(new Element('div')
						.update($(pageJs.reponseHolderId).innerHTML), {
					beforeLoad : function() {
						Modalbox.activate();
					},
					title : 'Choose where to push selected FR(s) to: ',
					width : pageJs.resPaneWith
				});
			}
		});

		tmp.callParams = {
			'selectedIds' : tmp.selectedRquestIds
		};

		if (lastPushLocation != null) {
			tmp.callParams['lastPushLocation'] = lastPushLocation;
		}

		tmp.request.setCallbackParameter(tmp.callParams);
		tmp.request.setRequestTimeOut(this.maxExecutionTime);
		tmp.request.dispatch();
	},

	// push FR action
	pushFR : function(callbackId, requestIds, clickedBtn) {
		var tmp = {};
		tmp.selectedRquestIds = requestIds.split(',');
		if (tmp.selectedRquestIds.length === 0)
			return this.showError('Please select some items first!');
		tmp.pushDiv = $(clickedBtn).up('div[pushfrdiv="pushfrdiv"]');
		tmp.comments = tmp.pushDiv.down('[pushfrdiv="comments"]').value.strip();
		if (tmp.comments.empty())
			return this.showError('Comments are required!');
		tmp.newOwnerId = tmp.pushDiv.down('[pushfrdiv="newOwnerId"]').value
				.strip();
		if (tmp.newOwnerId.empty())
			return this.showError('A new owner is required!');

		tmp.info = {
			'newOwnerId' : tmp.newOwnerId,
			'comments' : tmp.comments
		};

		document.cookie = "lastPushLocation=" + tmp.newOwnerId + "; path=/";

		this.submitEachRequest(0, tmp.selectedRquestIds.join(','), callbackId,
				Object.toJSON(tmp.info));
	},

	// get the selected request ids
	getSelectedIds : function(requestId) {
		var tmp = {};

		if (requestId !== undefined) {
			tmp.selectedRquestIds = [ requestId ];
		} else {
			// gathering data
			tmp.selectedRquestIds = this.selectedRequestIds;
		}

		// check if selected any
		if (tmp.selectedRquestIds.length === 0)
			throw 'Please select some items first!';

		return tmp.selectedRquestIds;
	},

	// get the right browser
	getBrowser : function() {
		var nVer = navigator.appVersion;
		var nAgt = navigator.userAgent;
		var browserName = navigator.appName;
		var fullVersion = '' + parseFloat(navigator.appVersion);
		var majorVersion = parseInt(navigator.appVersion, 10);
		var nameOffset, verOffset, ix;

		// In Opera, the true version is after "Opera" or after "Version"
		if ((verOffset = nAgt.indexOf("Opera")) != -1) {
			browserName = "Opera";
			fullVersion = nAgt.substring(verOffset + 6);
			if ((verOffset = nAgt.indexOf("Version")) != -1)
				fullVersion = nAgt.substring(verOffset + 8);
		}
		// In MSIE, the true version is after "MSIE" in userAgent
		else if ((verOffset = nAgt.indexOf("MSIE")) != -1) {
			browserName = "Microsoft Internet Explorer";
			fullVersion = nAgt.substring(verOffset + 5);
		}
		// In Chrome, the true version is after "Chrome"
		else if ((verOffset = nAgt.indexOf("Chrome")) != -1) {
			browserName = "Chrome";
			fullVersion = nAgt.substring(verOffset + 7);
		}
		// In Safari, the true version is after "Safari" or after "Version"
		else if ((verOffset = nAgt.indexOf("Safari")) != -1) {
			browserName = "Safari";
			fullVersion = nAgt.substring(verOffset + 7);
			if ((verOffset = nAgt.indexOf("Version")) != -1)
				fullVersion = nAgt.substring(verOffset + 8);
		}
		// In Firefox, the true version is after "Firefox"
		else if ((verOffset = nAgt.indexOf("Firefox")) != -1) {
			browserName = "Firefox";
			fullVersion = nAgt.substring(verOffset + 8);
		}
		// In most other browsers, "name/version" is at the end of userAgent
		else if ((nameOffset = nAgt.lastIndexOf(' ') + 1) < (verOffset = nAgt
				.lastIndexOf('/'))) {
			browserName = nAgt.substring(nameOffset, verOffset);
			fullVersion = nAgt.substring(verOffset + 1);
			if (browserName.toLowerCase() == browserName.toUpperCase()) {
				browserName = navigator.appName;
			}
		}
		// trim the fullVersion string at semicolon/space if present
		if ((ix = fullVersion.indexOf(";")) != -1)
			fullVersion = fullVersion.substring(0, ix);
		if ((ix = fullVersion.indexOf(" ")) != -1)
			fullVersion = fullVersion.substring(0, ix);

		majorVersion = parseInt('' + fullVersion, 10);
		if (isNaN(majorVersion)) {
			fullVersion = '' + parseFloat(navigator.appVersion);
			majorVersion = parseInt(navigator.appVersion, 10);
		}

		return browserName;
	}

};
