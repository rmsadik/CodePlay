var EditPIJS=new Class.create;EditPIJS.prototype={callBackIds:{},isCurrUsrSysAdmin:!1,snAliasTypeId:null,editPIPanelID:"",aliasPanelID:"",partinstance:null,searchBarcodeTxtboxID:null,searchPiIdTxtboxID:null,searchBtnID:null,closeAfterSave:!1,initialize:function(e,t,a,n,i,s,l){this.isCurrUsrSysAdmin="1"===e?!0:!1,this.snAliasTypeId=t,this.editPIPanelID=a,this.aliasPanelID=n,this.searchBarcodeTxtboxID=i,this.searchPiIdTxtboxID=s,this.searchBtnID=l},setCallbackIds:function(e,t,a,n){return this.callBackIds.getPIBtn=e,this.callBackIds.changePTBtn=t,this.callBackIds.chkConflictBtn=a,this.callBackIds.savePIBtn=n,this},openHistory:function(){return void 0===this.partinstance||null===this.partinstance||void 0===this.partinstance.id||null===this.partinstance.id?void alert("System Error: the part instance id not stored!"):void window.open("/parthistory/searchparttext/"+this.partinstance.id)},getPI:function(e){var t={};return t.me=this,t.originalValue=$F(e),t.btnDisabled=$(e).disabled,$(t.me.searchBarcodeTxtboxID).value=$F(t.me.searchBarcodeTxtboxID).strip().toUpperCase(),t.searchData={},$(e).up(".searchpane").getElementsBySelector("[searchpane]").each(function(e){t.searchData[$(e).readAttribute("searchpane")]=$F(e)}),bsuiteJs.postAjax(t.me.callBackIds.getPIBtn,t.searchData,{onLoading:function(){$(e).value="Retrieving PI Info",$(e).disabled=!0,t.me.partinstance=null,$$(".resetWhenChangePT").each(function(e){e.update("")}),$$(".editPIDetails").each(function(e){e.addClassName("hidden")}),$(t.me.searchPiIdTxtboxID).value="",$("HotMessage")&&$("HotMessage").up()&&$("HotMessage").up().update("")},onComplete:function(a,n){try{if(t.result=bsuiteJs.getResp(n,!1,!0),void 0===t.result.piArray||null===t.result.piArray||0===t.result.piArray.size())throw"No part instance found from the search!";if(t.result.piArray.size()>1)t.me._showMultiplePI(t.result.piArray);else{if(t.me.partinstance=t.result.piArray[0],t.me.partinstance.parttype.serialized!==!0)throw"You can NOT edit a non-serialised part instance here!";t.me._showPIDetails(t.me.partinstance),(void 0===$(e).up().down(".viewHistoryBtn")||null===$(e).up().down(".viewHistoryBtn"))&&$(e).insert({after:new Element("span",{"class":"viewHistoryBtn inlineblock"}).update("View History").observe("click",function(){t.me.openHistory()})}),$$(".editPIDetails.hidden").each(function(e){e.removeClassName("hidden")}),$("searchbarcode").value=t.me.partinstance.sn,$("newbarcode").focus()}$(e).value=t.originalValue,$(e).disabled=t.btnDisabled}catch(i){alert(i),$(e).value=t.originalValue,$(e).disabled=t.btnDisabled}}}),this},changePT:function(e){var t={};t.me=this,$(e).getElementsBySelector("li.selected").each(function(e){t.ptId=e.value}),bsuiteJs.postAjax(t.me.callBackIds.changePTBtn,{piId:t.me.partinstance.id,ptId:t.ptId},{onLoading:function(){$$(".resetWhenChangePT").each(function(e){$(e).update("Retrieving PartType Information ...")})},onComplete:function(e,a){try{if(t.result=bsuiteJs.getResp(a,!1,!0),void 0===t.result.piArray||null===t.result.piArray||0===t.result.piArray.size())throw"No part instance found from the search!";t.me._showPIDetails(t.result.piArray[0])}catch(n){alert(n)}}})},_showPIDetails:function(e){var t={};t.Qty=null,t.active=null,t.me=this,$(t.me.searchPiIdTxtboxID).value=e.id,$$('[editpane="parttype"]').each(function(t){t.writeAttribute("ptid",e.parttype.id),t.writeAttribute("serialized",e.parttype.serialized===!0?1:0),t.value=e.parttype.name}),t.contractsList=new Element("ul"),e.contracts.each(function(e){t.contractsList.insert({bottom:new Element("li").update(e.name)})}),t.me.isCurrUsrSysAdmin!==!0?(0===this.partinstance.active&&alert("This part instance is deactivated.\n Please contact technology if you want to reactivate it"),t.editPIPanel=$(t.me.editPIPanelID).update("").insert({bottom:t.me._getRowDiv("Owner Client: ",e.owner.name)}).insert({bottom:t.me._getRowDiv("User Contract(s): ",t.contractsList.wrap(new Element("div",{"class":"contractlist roundcnr"})))}).insert({bottom:t.me._getRowDiv("Part Location: ",e.warehouse.path)}).insert({bottom:t.me._getRowDiv("Status: ",t.me._getSelectBox(e.status.availStatuses.sort(),"id","name",e.status.id).writeAttribute("editpane","status"))}).insert({bottom:t.me._getRowDiv("Qty: ",e.qty)}).insert({bottom:t.me._getRowDiv("Active: ",1===e.active?"Yes":"No")}),t.newBarcode=null,e.parttype.serialized===!0&&(t.newBarcode=new Element("input",{id:"newbarcode",type:"text",editpane:"newbarcode","class":"clearonreset roundcnr txt fullwidth",placeholder:"The new serial number",value:""}).observe("keydown",function(e){return t.me.keydown(e,function(){t.emtpyAliasInputs=$$('[editpane="alias"]').filter(function(e){return $F(e).blank()}),t.emtpyAliasInputs.size()>0&&t.emtpyAliasInputs.first().focus()})}),t.editPIPanel.insert({bottom:t.me._getRowDiv("New Serial Number: ",new Element("div").update(t.newBarcode).insert({bottom:t.me._getSampleFormatDiv(e.snFormats.pattern,e.snFormats.sampleformat)}),t.me._getRowErrMsg("Leave blank to retain current Serial Number","infomsg"))})),null!==e.aliases&&1===this.partinstance.active&&$(t.me.aliasPanelID).update(t.me._getAliasDiv(e.aliases,e.avialPIATs)).insert({bottom:new Element("input",{type:"button",value:"Save",id:"savePIBtn"}).observe("click",function(){t.me.savePI()})})):(t.Qty=new Element("input",{id:"qty",type:"text",editpane:"qty","class":"clearonreset roundcnr txt fullwidth",placeholder:"qty",value:e.qty}),t.active=new Element("input",{id:"active",type:"text",editpane:"active","class":"clearonreset roundcnr txt fullwidth",placeholder:"active",value:e.active}),t.editPIPanel=$(t.me.editPIPanelID).update("").insert({bottom:t.me._getRowDiv("Owner Client: ",e.owner.name)}).insert({bottom:t.me._getRowDiv("User Contract(s): ",t.contractsList.wrap(new Element("div",{"class":"contractlist roundcnr"})))}).insert({bottom:t.me._getRowDiv("Part Location: ",e.warehouse.path)}).insert({bottom:t.me._getRowDiv("Status: ",t.me._getSelectBox(e.status.availStatuses.sort(),"id","name",e.status.id).writeAttribute("editpane","status"))}).insert({bottom:t.me._getRowDiv("Qty: ",new Element("div").update(t.Qty).insert(),t.me._getRowErrMsg("Qty should be 1","infomsg"))}).insert({bottom:t.me._getRowDiv("Active: ",new Element("div").update(t.active).insert(),t.me._getRowErrMsg("Activate: 1 | Deactivate : 0","infomsg"))}),t.newBarcode=null,e.parttype.serialized===!0&&(t.newBarcode=new Element("input",{id:"newbarcode",type:"text",editpane:"newbarcode","class":"clearonreset roundcnr txt fullwidth",placeholder:"The new serial number",value:""}).observe("keydown",function(e){return t.me.keydown(e,function(){t.emtpyAliasInputs=$$('[editpane="alias"]').filter(function(e){return $F(e).blank()}),t.emtpyAliasInputs.size()>0&&t.emtpyAliasInputs.first().focus()})}),t.editPIPanel.insert({bottom:t.me._getRowDiv("New Serial Number: ",new Element("div").update(t.newBarcode).insert({bottom:t.me._getSampleFormatDiv(e.snFormats.pattern,e.snFormats.sampleformat)}),t.me._getRowErrMsg("Leave blank to retain current Serial Number","infomsg"))})),null!==e.aliases&&$(t.me.aliasPanelID).update(t.me._getAliasDiv(e.aliases,e.avialPIATs)).insert({bottom:new Element("input",{type:"button",value:"Save",id:"savePIBtn"}).observe("click",function(){t.me.savePI()})}))},_getRowErrMsg:function(e,t){var a={};return a.classname=void 0===t||null===t?"":t,a.element=new Element("span",{"class":"msg smltxt "+a.classname}).update(e),a.element},savePI:function(){var e={};e.me=this,e.newData=this._collectingData(),null!==e.newData&&e.me._getDiffFromCollectedData(e.newData)},_getCfrmDivRow:function(e,t,a,n,i){var s={};return s.element=new Element("div",{"class":"aliastyperow "+(i===!0?"header":"cfrmChgRow")}).insert({bottom:new Element("span",{"class":"inlineblock actiontype"}).update(t)}).insert({bottom:new Element("span",{"class":"inlineblock field"}).update(e)}).insert({bottom:new Element("span",{"class":"inlineblock oldValue"}).update(a)}).insert({bottom:new Element("span",{"class":"inlineblock newValue"}).update(n)}),s.element},_getDiffFromCollectedData:function(e){var t={};return t.me=this,t.diffs=[],e.parttype.id!==t.me.partinstance.parttype.id&&t.diffs.push({field:"Part Type",action:"Updating",oldValue:t.me.partinstance.parttype.name,newValue:e.parttype.name}),e.status.id!==t.me.partinstance.status.id&&t.diffs.push({field:"Status",action:"Updating",oldValue:t.me.partinstance.status.name,newValue:e.status.name}),t.me.isCurrUsrSysAdmin===!0&&(e.qty!==t.me.partinstance.qty&&t.diffs.push({field:"Qty",action:"Updating",oldValue:t.me.partinstance.qty,newValue:e.qty}),e.active==t.me.partinstance.active||t.diffs.push({field:"Active",action:"Updating",oldValue:t.me.partinstance.active,newValue:e.active})),$H(e.aliases).each(function(e){t.typeId=e.key,e.value.each(void 0===t.me.partinstance.aliases[t.typeId]||null===t.me.partinstance.aliases[t.typeId]?function(e){t.diffs.push({field:t.me.partinstance.avialPIATs[t.typeId].name,action:"Creating",oldValue:"",newValue:e.alias})}:function(e){e.id.blank()?t.diffs.push({field:t.me.partinstance.avialPIATs[t.typeId].name,action:"Creating",oldValue:"",newValue:e.alias}):e.deactivate===!0?t.diffs.push({field:t.me.partinstance.avialPIATs[t.typeId].name,action:"Removing",oldValue:e.alias,newValue:""}):t.me.partinstance.aliases[t.typeId].each(function(a){e.id===a.id&&e.alias!==a.alias&&t.diffs.push({field:t.me.partinstance.avialPIATs[t.typeId].name,action:"Updating",oldValue:a.alias,newValue:e.alias})})})}),0===t.diffs.size()?void alert("Nothing changed!"):(t.confirmChangeDiv=new Element("div",{"class":"cfrmChgDiv"}).update(t.me._getCfrmDivRow("Field","Action","Old Value","New Value",!0)),t.rowNo=0,t.editPIChgSummary=[],t.diffs.each(function(e){t.editPIChgSummary.push(e.action+" "+e.field+": ["+e.oldValue+"] -> ["+e.newValue+"]"),t.confirmChangeDiv.insert({bottom:t.me._getCfrmDivRow(e.field,e.action,e.oldValue,e.newValue,!1).addClassName(t.rowNo++%2===0?"even":"odd")})}),void Modalbox.show(t.confirmChangeDiv.wrap("div"),{title:"Are you sure you want to change this part instance:",width:600,afterLoad:function(){$(Modalbox.MBcontent).insert({top:new Element("div",{"class":"submitPIBtns"}).insert({bottom:new Element("input",{value:"Yes",type:"button","class":"submitBtn"}).observe("click",function(){t.me._chkConflicts(e,t.editPIChgSummary)})}).insert({bottom:new Element("input",{value:"No",type:"button","class":"submitBtn"}).observe("click",function(){Modalbox.hide()})})}),Modalbox.resizeToContent()}}))},_chkConflicts:function(e,t){var a={};return a.me=this,bsuiteJs.postAjax(a.me.callBackIds.chkConflictBtn,e,{onLoading:function(){Modalbox.show("loading",{title:"Checking whether there are conflicts against unique aliases ..."})},onComplete:function(n,i){try{if(a.result=bsuiteJs.getResp(i,!1,!0),void 0===a.result.confictsPIATIds||null===a.result.confictsPIATIds||0===a.result.confictsPIATIds.size())return void a.me._submitPI(e,t);a.me._displayConflicts(e,a.result.confictsPIATIds,a.result.conficts,t)}catch(s){Modalbox.hide(),alert(s)}}}),this},_displayConflicts:function(e,t,a,n){var i={};return i.me=this,i.avialPIATs=this.partinstance.avialPIATs,i.conflictDiv=new Element("div",{"class":"conflictDiv"}).update(i.me._getConflictPIR("","S/N","Part Type","Location","Conflicted Aliases").addClassName("header")),i.rowNo=0,i.currPi=i.me.partinstance,i.currAliases={},$H(e.aliases).each(function(e){i.typeId=e.key,i.aliasObj=e.value,t.indexOf(i.typeId)>=0&&((void 0===i.currAliases[i.typeId]||null===i.currAliases[i.typeId])&&(i.currAliases[i.typeId]=[]),i.aliasObj.each(function(e){e.deactivate===!1&&(i.aliasContent=e.alias,i.currAliases[i.typeId].push(i.aliasContent))}))}),i.conflictDiv.insert({bottom:i.me._getConflictPIRow(i.currPi.id,i.currPi.sn+(e.newbarcode&&!e.newbarcode.blank()?" -> "+e.newbarcode:""),'<div class="smltxt errormsg currenteditpi">Currently being Edited</div> '+e.parttype.name,i.currPi.warehouse.path,i.currAliases,t,i.currPi.parent).addClassName(i.rowNo++%2===0?"even":"odd")}),i.samePT=!0,i.sameParent=!0,$H(a).each(function(a){i.piId=a.key,i.confAArray=a.value,i.conflictDiv.insert({bottom:i.me._getConflictPIRow(i.piId,i.confAArray.pi.sn,i.confAArray.pi.name,i.confAArray.pi.warehouse.path,i.confAArray.aliases,t,i.confAArray.pi.parent).addClassName(i.rowNo++%2===0?"even":"odd")}),e.parttype.id!==i.confAArray.pi.parttype.id&&(i.samePT=!1),i.currPi.parent.id!==i.confAArray.pi.parent.id&&(i.sameParent=!1)}),Modalbox.show(i.conflictDiv,{width:"900",title:'<span style="color: red;">Conflicts found! Please select <u>ONE</u> PartInstance to <u>KEEP</u>!</span>',afterLoad:function(){i.conflictedPIIds={},$(Modalbox.MBcontent).getElementsBySelector(".conflictAliasRow[partinstanceid] .piraidobtn input").each(function(t){i.piId=t.value,i.conflictedPIIds[i.piId]=!1,t.observe("click",function(){return i.samePT===!1?void alert("To merge part instances, they have to be the same part type. but they are NOT! Please edit the part instance first!"):i.sameParent===!1?void alert("To merge part instances, they have to be either under the same parent OR under no parent at all!"):(i.conflictedPIIds[$F(this)]=!0,void(confirm("Are you sure you want to merge all other PI into the selected one?")&&i.me._submitPI(e,n,i.conflictedPIIds)))})}),Modalbox.resizeToContent()}}),this},_getConflictPIRow:function(e,t,a,n,i,s,l){var r={};return r.avialPIATs=this.partinstance.avialPIATs,r.conflictRowAlias=new Element("div",{"class":"conflictAlias"}),$H(i).each(function(e){r.aliasTypeId=e.key,r.aliases=e.value,r.conflictRowAlias.insert({bottom:new Element("div",{"class":"conflictAlias"}).insert({bottom:new Element("span",{"class":"conflictAliasType inlineblock"}).update(r.avialPIATs[r.aliasTypeId].name+": ")}).insert({bottom:new Element("span",{"class":"conflictAliasContent inlineblock"}).update(r.aliases.join("<br />"))})})}),this._getConflictPIR(e,t,a,n,r.conflictRowAlias,l)},_getConflictPIR:function(e,t,a,n,i,s){return new Element("div",{"class":"aliastyperow conflictAliasRow ",partinstanceid:e}).insert({bottom:new Element("span",{"class":"piraidobtn inlineblock"}).update(!e||e.blank()?"":new Element("input",{type:"radio",name:"conflictedpi",value:e}))}).insert({bottom:new Element("span",{"class":"pisn inlineblock"}).update(t)}).insert({bottom:new Element("span",{"class":"piname inlineblock"}).update(a)}).insert({bottom:new Element("span",{"class":"pilocation inlineblock"}).update((s&&s.id?'<div class="smltxt errormsg">Within another Part</div> ':"")+n)}).insert({bottom:new Element("span",{"class":"pialiasconflicts inlineblock"}).update(i)})},_submitPI:function(e,t,a){var n={};return n.me=this,e.editPIChgSummary=t,e.conflictedPIIds=a,bsuiteJs.postAjax(n.me.callBackIds.savePIBtn,e,{onLoading:function(){Modalbox.show("loading",{title:"Saving part instance ..."})},onComplete:function(e,t){try{n.result=bsuiteJs.getResp(t,!1,!0),void 0!==n.result.piArray&&null!==n.result.piArray&&n.result.piArray.size()>0&&(alert("Part Instance Saved Successfully!"),n.me.closeAfterSave?window.close():window.location.reload())}catch(a){Modalbox.hide(),alert(a)}}}),this},_reloadPIDetails:function(e,t){$(this.searchPiIdTxtboxID).value=e,void 0!==t&&null!==t&&($(this.searchBarcodeTxtboxID).value=t),this.getPI($(this.searchBtnID))},_collectingData:function(){var e={};if(e.me=this,e.requestData={id:e.me.partinstance.id,parttype:{},status:{},aliases:{},avialPIATs:e.me.partinstance.avialPIATs},$$(".errorRow").each(function(e){e.removeClassName("errorRow")}),e.hasErr=!1,$$("[editpane]").each(function(t){switch(t.readAttribute("editpane")){case"parttype":e.requestData.parttype={id:t.readAttribute("ptid"),name:$F(t),serialized:1==t.readAttribute("serialized")?!0:!1};break;case"qty":"1"==$F(t)||(e.row=$(t).up(".row"),e.infoDiv=e.row.down(".info"),e.infoDiv.update(e.me._getRowErrMsg(" Error!  Qty can only be 1")),e.hasErr=!0,$(t).up(".row").addClassName("errorRow")),e.requestData.qty=$F(t);break;case"active":"0"==$F(t)||"1"==$F(t)||(e.row=$(t).up(".row"),e.infoDiv=e.row.down(".info"),e.infoDiv.update(e.me._getRowErrMsg(" Error!  You can only use 1 or 0 to Activate or Deactivate a part")),e.hasErr=!0,$(t).up(".row").addClassName("errorRow")),e.requestData.active=$F(t);break;case"status":e.requestData.status={id:$F(t),name:t.options[t.selectedIndex].innerHTML};break;case"newbarcode":e.newSerialNo=$F(t).strip().toUpperCase(),e.oldSerialNo=$F(e.me.searchBarcodeTxtboxID).strip().toUpperCase(),e.row=$(t).up(".row"),e.infoDiv=e.row.down(".info"),e.regexHolder=e.row.down("[pattern]"),e.row.getElementsBySelector(".errormsg").each(function(e){e.remove()}),e.regex=void 0===e.regexHolder||null===e.regexHolder||e.regexHolder.readAttribute("pattern").blank()?"":e.regexHolder.readAttribute("pattern"),e.newSerialNo.blank()?""!==e.regex&&e.me._matchPatter(e.oldSerialNo,e.regex)!==!0&&(e.infoDiv.update(e.me._getRowErrMsg("You need to change the Serial Number!","errormsg")),e.hasErr=!0,$(t).up(".row").addClassName("errorRow")):e.newSerialNo===e.oldSerialNo?(e.infoDiv.update(e.me._getRowErrMsg("New serial number cannot be same as current serial number!","errormsg")),e.hasErr=!0,$(t).up(".row").addClassName("errorRow")):""!==e.regex&&e.me._matchPatter(e.newSerialNo,e.regex)!==!0&&(e.infoDiv.update(e.me._getRowErrMsg("Invalid Serial Number provided!","errormsg")),e.hasErr=!0,$(t).up(".row").addClassName("errorRow")),e.hasErr===!1&&e.newSerialNo.blank()===!1&&((void 0===e.requestData.aliases[e.me.snAliasTypeId]||null===e.requestData.aliases[e.me.snAliasTypeId])&&(e.requestData.aliases[e.me.snAliasTypeId]=[]),e.me.partinstance.aliases[e.me.snAliasTypeId].each(function(t){e.requestData.aliases[e.me.snAliasTypeId].push(e.me._newAliasObj(t.id,t.typeid,t.alias,!0))}),e.requestData.aliases[e.me.snAliasTypeId].push(e.me._newAliasObj("",e.me.snAliasTypeId,e.newSerialNo,!1))),e.requestData.newbarcode=e.newSerialNo;break;case"alias":e.row=$(t).up(".row.alias"),e.del=e.row.readAttribute("delete"),e.typeId=e.row.readAttribute("typeid"),e.regexHolder=e.row.down("[pattern]"),e.regex=void 0===e.regexHolder||null===e.regexHolder||e.regexHolder.readAttribute("pattern").blank()?"":e.regexHolder.readAttribute("pattern"),e.alias=$F(t).strip(),e.infoDiv=e.row.down(".info"),e.row.getElementsBySelector(".errormsg").each(function(e){e.remove()}),e.alias.blank()?(e.infoDiv.insert({bottom:e.me._getRowErrMsg("Empty alias not allowed!","errormsg")}),e.hasErr=!0,$(t).up(".row").addClassName("errorRow")):e.me._matchPatter(e.alias,e.regex)===!1?(e.infoDiv.insert({bottom:e.me._getRowErrMsg("Alias NOT matched with Valid Format!","errormsg")}),e.hasErr=!0,$(t).up(".row").addClassName("errorRow")):((void 0===e.requestData.aliases[e.typeId]||null===e.requestData.aliases[e.typeId]||""===e.requestData.aliases[e.typeId])&&(e.requestData.aliases[e.typeId]=[]),e.requestData.aliases[e.typeId].push(e.me._newAliasObj(e.row.readAttribute("piaid"),e.row.readAttribute("typeid"),e.alias,void 0===e.del||null===e.del?!1:!0)))}}),e.collectedSerialNo=void 0!==e.requestData.aliases[e.me.snAliasTypeId]&&null!==e.requestData.aliases[e.me.snAliasTypeId]&&e.requestData.aliases[e.me.snAliasTypeId].size()>0,e.requestData.parttype.serialized===!1)if(e.collectedSerialNo)for(e.size=e.me.partinstance.aliases[e.me.snAliasTypeId].size(),e.i=0;e.i<e.size;e.i++)e.me.partinstance.aliases[e.me.snAliasTypeId][e.i].deactivate=!1;else e.requestData.aliases[e.me.snAliasTypeId]=[],e.me.partinstance.aliases[e.me.snAliasTypeId].each(function(t){t.deactivate=!0,e.requestData.aliases[e.me.snAliasTypeId].push(t)});else e.requestData.parttype.serialized===!0&&e.collectedSerialNo===!1&&(e.requestData.aliases[e.me.snAliasTypeId]=e.me.partinstance.aliases[e.me.snAliasTypeId]);return e.hasErr===!0&&alert("Error when saving, please fixing them before continue."),e.hasErr===!0?null:e.requestData},_newAliasObj:function(e,t,a,n){return{id:e,typeid:t,alias:a,deactivate:n===!0?!0:!1}},_matchPatter:function(e,t){return void 0===t||null===t||t.blank()?!0:new RegExp(t.substr(1,t.length-2)).match(e)},addAliasType:function(e,t){var a={};a.me=this,a.usedAliasTypeIds=[],$(a.me.aliasPanelID).getElementsBySelector(".row.alias[typeid]").each(function(e){a.usedAliasTypeIds.push(e.readAttribute("typeid"))}),a.usedAliasTypeIds=a.usedAliasTypeIds.uniq(),a.aliasTypes=[],$H(t).each(function(e){a.typeId=e.key,a.piat=e.value,(e.value.allowMulti===!0||a.usedAliasTypeIds.indexOf(a.typeId)<0)&&("1"===e.value.access||a.me.isCurrUsrSysAdmin===!0&&"2"===e.value.access)&&a.aliasTypes.push(e.value)}),a.typeList=new Element("div",{"class":"newaliastypelist"}).update(a.me._getAliasTypeRow("header aliastyperow","","Name","Allow Multiple","Is Mandatory","Is Unique","Sample Format")),a.rowNo=0,a.aliasTypes.each(function(e){a.typeList.insert({bottom:a.me._getAliasTypeRow("aliastyperow "+(a.rowNo++%2===0?"even":"odd"),e.id,e.name,e.allowMulti===!0?"Y":"&nbsp;",e.mandatory===!0?"Y":"&nbsp;",e.unique===!0?"Y":"&nbsp;",e.sampleformat)})}),Modalbox.show(a.typeList.wrap("div"),{width:600,title:"Please select a alias type from below: ",afterLoad:function(){$(Modalbox.MBwindow).getElementsBySelector(".aliastyperow[piatid]").each(function(e){e.observe("click",function(){a.me.addAlias(e.readAttribute("piatid"))})})}})},addAlias:function(e){var t={};t.me=this,t.aliasTypes=t.me.partinstance.avialPIATs,t.newAliasTextBox=t.me._getAliasEditBox(),$(this.aliasPanelID).down(".aliaslist").insert({bottom:t.me._getAliasRow("",t.aliasTypes[e].name,e,t.newAliasTextBox,t.me._getAliasDelBtn(),t.aliasTypes[e].pattern.pattern,t.aliasTypes[e].pattern.sampleformat)}),Modalbox.hide({afterHide:function(){t.newAliasTextBox.focus()}})},_getAliasEditBox:function(e,t){var a={};return a.me=this,a.aliasContent=void 0===e||null===e||e.blank()?"":e,a.placehoder=void 0===t||null===t||t.blank()?"":t,a.element=new Element("input",{"class":"clearonreset roundcnr txt fullwidth",value:a.aliasContent,type:"text",editpane:"alias"}).observe("keydown",function(e){a.me.keydown(e,function(){a.emtpyAliasInputs=$$('[editpane="alias"]').filter(function(e){return $F(e).blank()}),a.emtpyAliasInputs.size()>0?a.emtpyAliasInputs.first().focus():$(a.me.aliasPanelID).down("#savePIBtn").click()})}),""!==a.placehoder&&a.element.writeAttribute("placeholder",a.placehoder),a.element},_getAliasTypeRow:function(e,t,a,n,i,s,l){var r={};return r.cssclass=void 0===e||null===e?"row":e,r.element=new Element("div",{"class":r.cssclass}).update(new Element("span",{"class":"name inlineblock"}).update(a)).insert({bottom:new Element("span",{"class":"allowmulti inlineblock"}).update(n)}).insert({bottom:new Element("span",{"class":"mandatory inlineblock"}).update(i)}).insert({bottom:new Element("span",{"class":"unique inlineblock"}).update(s)}).insert({bottom:new Element("span",{"class":"sampleformat inlineblock"}).update(l)}),void 0===t||null===t||t.blank()||r.element.writeAttribute("piatid",t),r.element},_getAliasDelBtn:function(){var e={};return e.me=this,new Element("span",{"class":"delBtn"}).update("&nbsp;").observe("click",function(){confirm("Are you sure you want to delete this alias?")&&(e.aliasRow=$(this).up(".row.alias"),e.aliasRow.readAttribute("piaid").blank()?e.aliasRow.remove():$(this).up(".row.alias").writeAttribute("delete",!0).hide())})},_getAliasDiv:function(e,t){var a={};return a.me=this,a.element=new Element("fieldset",{"class":"aliaslist roundcnr"}).update(new Element("legend").update("Aliases")).insert({bottom:new Element("div").insert({bottom:new Element("input",{"class":"addAliasBtn",type:"button",value:"Add Alias"}).observe("click",function(){a.me.addAliasType(this,t)})}).insert({bottom:new Element("span",{"class":"manhint"}).update("* Mandatory Field")})}),a.manTypeIds=[],a.unqiueTypeIds=[],$H(t).each(function(e){e.value.mandatory===!0&&a.manTypeIds.push(e.value.id),e.value.unique===!0&&a.unqiueTypeIds.push(e.value.id)}),$H(e).each(function(e){a.aliasTypeId=e.key,a.index=a.manTypeIds.indexOf(a.aliasTypeId),a.isMand=!1,a.index>=0&&(a.manTypeIds.splice(a.index,1),a.isMand=!0),a.index=a.unqiueTypeIds.indexOf(a.aliasTypeId),a.isUniq=!1,a.index>=0&&(a.unqiueTypeIds.splice(a.index,1),a.isUniq=!0),e.value.each(function(e){a.aliastypebox=t[a.aliasTypeId].name+(a.isMand===!0?' <span class="manhint">*</span>':"")+": ",a.aliastxtbox=e.alias+(a.aliasTypeId===a.me.snAliasTypeId?"":'<input type="hidden" editpane="alias" value="'+e.alias+'" />'),a.aliasbtns=new Element("span"),a.pattern="",a.sampleformat="",("1"===t[a.aliasTypeId].access||"2"===t[a.aliasTypeId].access&&a.me.isCurrUsrSysAdmin===!0)&&(a.aliastxtbox=a.me._getAliasEditBox(e.alias),a.pattern=t[a.aliasTypeId].pattern.pattern,a.sampleformat=t[a.aliasTypeId].pattern.sampleformat,a.isMand!==!0&&a.aliasbtns.insert({bottom:a.me._getAliasDelBtn()}),a.isUniq===!0&&a.aliasbtns.insert({bottom:new Element("span",{"class":"smltxt infomsg"}).update((a.isMand===!0?"Mandatory & ":"")+"Unique Alias")})),a.element.insert({bottom:a.me._getAliasRow(e.id,a.aliastypebox,a.aliasTypeId,a.aliastxtbox,a.aliasbtns,a.pattern,a.sampleformat)})})}),a.manTypeIds.each(function(e){a.index=a.unqiueTypeIds.indexOf(e),a.isUniq=!1,a.index>=0&&(a.unqiueTypeIds.splice(a.index,1),a.isUniq=!0),a.element.insert({bottom:a.me._getAliasRow("",t[e].name+' <span class="manhint">*</span>: ',e,a.me._getAliasEditBox("","Mandatory"+(a.isUniq===!0?" & Unique":"")+" alias!"),a.isUniq===!0?new Element("span").insert({bottom:new Element("span",{"class":"smltxt infomsg"}).update("Mandatory & Unique Alias ")}):"",t[e].pattern.pattern,t[e].pattern.sampleformat)})}),a.unqiueTypeIds.each(function(e){a.element.insert({bottom:a.me._getAliasRow("",t[e].name+": ",e,a.me._getAliasEditBox("","Unique alias!"),new Element("span").insert({bottom:a.me._getAliasDelBtn()}).insert({bottom:new Element("span",{"class":"smltxt infomsg"}).update("Unique Alias ")}),t[e].pattern.pattern,t[e].pattern.sampleformat)})}),a.element},_getSampleFormatDiv:function(e,t){return void 0===e||null===e||e.blank()?"":new Element("div",{"class":"aliaspattern smltxt",pattern:e}).update("Valid Format: "+t)},_getAliasRow:function(e,t,a,n,i,s,l){var r={};return r.patternElement=this._getSampleFormatDiv(s,l),r.element=new Element("div",{"class":"row alias",piaid:e,typeid:a}).insert({bottom:new Element("span",{"class":"title inlineblock"}).update(t)}).insert({bottom:new Element("span",{"class":"content inlineblock"}).update(n).insert({bottom:r.patternElement})}).insert({bottom:new Element("span",{"class":"info inlineblock"}).update(i)}),r.element},_getSelectBox:function(e,t,a,n){var i={};return i.element=new Element("select",{"class":"roundcnr fullwidth"}),e.each(function(e){i.element.insert({bottom:new Element("option",{value:e[t],selected:e[t]===n?!0:!1}).update(e[a])})}),i.element},_getRowDiv:function(e,t,a,n){var i={};return i.element=new Element("div",{"class":"row"+(void 0!==n?n:"")}).insert({bottom:new Element("span",{"class":"title inlineblock"}).update(e)}).insert({bottom:new Element("span",{"class":"content inlineblock"}).update(t)}),void 0!==a&&i.element.insert({bottom:new Element("span",{"class":"info inlineblock"}).update(a)}),i.element},_showMultiplePI:function(e){var t={};t.me=this,t.list=new Element("div",{"class":"multiResultWrapper"}),t.rowNo=0,t.list.insert({bottom:t.me._getMultiDiv("","PartType","Location").addClassName("aliastyperow header")}),e.each(function(e){t.list.insert({bottom:t.me._getMultiDiv(e.id,e.parttype.name,e.warehouse.path,"").addClassName("aliastyperow "+(t.rowNo++%2===0?"even":"odd"))})}),Modalbox.show(new Element("div").update(t.list),{title:'<span style="color: red;">Multiple Part Instance Found, please select which you are trying to edit:</span>',width:"800",afterLoad:function(){$(Modalbox.MBcontent).getElementsBySelector(".multipi[partinstanceid]").each(function(e){t.piId=t.piId=$(e).readAttribute("partinstanceid"),e.observe("click",function(){t.me._reloadPIDetails($(this).readAttribute("partinstanceid")),Modalbox.hide()})})}})},_getMultiDiv:function(e,t,a){var n={};return n.element=new Element("div",{"class":"multipi row",partinstanceid:e}).insert({bottom:new Element("span",{"class":"inlineblock multipiname"}).update(t)}).insert({bottom:new Element("span",{"class":"inlineblock multipilocation"}).update("@ "+a)}),n.element},_setCloseAfterSave:function(e){this.closeAfterSave=e},keydown:function(e,t,a){return e.which&&13==e.which||e.keyCode&&13==e.keyCode?("function"==typeof t&&t(),!1):("function"==typeof a&&a(),!0)}};