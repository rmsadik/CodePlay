var js = new Class.create();
js.prototype = {
	callbackIds: {},
	availStore: null,
	currentStore: null,

	//constructor
	initialize: function (initPushListCallback, savePushListCallback) {
		this.callbackIds.initPushList = initPushListCallback;
		this.callbackIds.savePushList = savePushListCallback;
	},
	
	//functions
	initPushList: function() {
		var tmp = {};

		bsuiteJs.postAjax(this.callbackIds.initPushList, {}, {
			'onLoading': function() {
				mb.showLoading('loading');
			},
			'onComplete': function (sender, param) {
				try {tmp.result = bsuiteJs.getResp(param, false, true);}
				catch(e) { 
					alert(e);
					mb.hide();
					return;
				}

				if (tmp.result.push === undefined || tmp.result.push === null) {
					alert('System Error: information missing!');
					mb.hide();
					return;
				}
				
				Ext.define('WarehouseRelationship', {
					extend: 'Ext.data.Model',
					fields: ['id', 'email', 'bread']
				});
				
				pageJs.availStore = {
						model: 'WarehouseRelationship',
						data: tmp.result.push.availPushList,
						sorters: ['bread']
				};
				pageJs.currentStore = {
						model: 'WarehouseRelationship',
						data: tmp.result.push.currPushList,
						sorters: ['bread']
				};
				
				Ext.onReady(function () {
					 Ext.widget({
					        title: tmp.result.push.fromWhBread,
					        xtype: 'panel',
					        layout: 'border',
					        items: [{
					            title: "Available Options (alias 'Bytecraft Warehouse Type' = 'FR_PushList')",
					            xtype: 'grid',
					            region: 'center',
					            margin: '5 0 5 5',
					            hideHeaders: false,
					            selModel: {
					                mode: 'MULTI'
					            },
					            viewConfig: {
					                plugins: {
					                    ptype: 'gridviewdragdrop',
					                    dragGroup: 'list',
					                    dropGroup: 'order'
					                }
					            },
					            store: pageJs.availStore,
					            columns: [
					                      {header: '', dataIndex: 'bread', flex: 1}
					            ]
					        }, {
					            title: 'Selected Options (Current Push List)',
					            xtype: 'grid',
					            region: 'east',
					            margin: '5 5 5 0',
					            split: true,
					            hideHeaders: false,
					            selModel: {
					            	mode: 'MULTI'
					            },
					            viewConfig: {
					                plugins: {
					                    ptype: 'gridviewdragdrop',
					                    dragGroup: 'order',
					                    dropGroup: 'list'
					                }
					            },
					            store: pageJs.currentStore,
					            columns: [
					                {header: 'Email?', dataIndex: 'email', width: 40},
					                {header: '', dataIndex: 'bread', flex: 1}
					            ],
					            width: 474
					        }],
					        width: 948,
					        height: 800,
					        renderTo: 'pushListDiv'
					    });
					});
					$('pushListDiv').addClassName('row');
					$('saveBtn').show();
					mb.hide();
				}
			});
	},
	
	savePushList: function() {
		var tmp = {};
		tmp.whRel = [];
		
		tmp.chks = $$('.chk');
		for (var i=0; i < tmp.chks.length; i++)
		{
			tmp.whRel.push({'id': tmp.chks[i].value, 'email': tmp.chks[i].checked});
		}

		bsuiteJs.postAjax(this.callbackIds.savePushList, {'params': Object.toJSON({'whRel': tmp.whRel})}, {
			'onLoading': function() {
				mb.showLoading('saving');
			},
			'onComplete': function (sender, param) {
				try {tmp.result = bsuiteJs.getResp(param, false, true);}
				catch(e) { 
					alert(e);
					mb.hide();
					return;
				}
				alert('Push List successfully saved...');
				mb.hide();
			}
		});
	},
	
	resetPage: function() {
		if (confirm("Are you sure you want to reload the page, all data will be lost?"))
		{
			location.reload();
		}
	}
};