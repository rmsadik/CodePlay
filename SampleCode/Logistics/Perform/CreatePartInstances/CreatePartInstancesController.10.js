var CreatePIJS=new Class.create;CreatePIJS.prototype={errors:"",WHHolderId:"",openNewWindowParams:"width=750, menubar=0, toolbar=0, status=0, scrollbars=1, resizable=1",maxQty:100,closeAfterSave:!1,requestData:{pt:{},pis:{},whId:"",po:{id:"",pId:""}},aliasTypes:{},callbackIds:{registerPI:"",selectPT:"",checkWH:"",checkSN:""},initialize:function(e,a,t,s,i,r,l){this.WHHolderId=e,this.callbackIds.selectPT=a,this.callbackIds.checkSN=s,this.callbackIds.checkWH=i,this.callbackIds.registerPI=r,this.callbackIds.showPattern=l,this.maxQty=t,this.aliasTypes=this.getAliasTypes()},getAliasTypes:function(){var e={};for(e.aliasTypes={},e.list=$$("select[aliases=aliastypeList]").first(),e.i=0;e.i<e.list.options.length;e.i+=1)e.value=e.list.options[e.i].value.strip(),""!==e.value&&(e.aliasTypes[e.value]=e.list.options[e.i].text);return e.aliasTypes},toggleHasPO:function(e,a,t,s,i){$(e).checked?($(a).style.backgroundColor="#AADDFF",$(a).removeClassName("disabled").disabled=!1,$(s).show(),$(a).select(),$(t).style.backgroundColor="",$(t).addClassName("disabled").disabled=!0):($(a).style.backgroundColor="",$(a).value="",$(a).addClassName("disabled").disabled=!0,$(t).style.backgroundColor="#AADDFF",$(t).removeClassName("disabled").disabled=!1,$(s).update("").hide(),$(t).select()),pageJs.clearBeforeSelectPT(),$(i)&&""!==$(i).value&&($$("[fieldids=parttype]").first().disabled=!0,this.populateSelectedPT($(i).value))},addAlias:function(e,a,t,s){var i={};if(i.wrapper=$(e).up("[aliases=wrapper]"),i.wrapper.hasClassName("added"))return i.selectedAliasTypeId=$F(i.wrapper.down("[aliases=aliastypeid]")),void 0===i.selectedAliasTypeId||i.selectedAliasTypeId.blank()||$$("[aliases=wrapper] select option[value="+i.selectedAliasTypeId+"]").each(function(e){e.show().disabled=!1}),i.wrapper.remove();if(i.aliasTypeId=a,a!==!1||s!==!1||void 0===t||t.blank()?a!==!1||t!==!1||void 0===s||s.blank()||(i.aliasTypeId=s):i.aliasTypeId=t,void 0===i.aliasTypeId||i.aliasTypeId.blank()||$$("[aliases=wrapper] select option[value="+i.aliasTypeId+"]").each(function(e){e.hide().disabled=!0}),i.newWrapper=new Element("div",{aliases:"wrapper","class":"row added"}).update(i.wrapper.innerHTML),i.aliasBoxHolder=i.newWrapper.down("[fieldids=serialNo]").writeAttribute("fieldids","alias").writeAttribute("id",""),i.aliasBoxHolder.disabled=!1,i.aliasBoxHolder.value="",i.newWrapper.down("[aliases=aliastype]").remove(),i.newWrapper.down("[fieldids=serialNoValidator]").remove(),i.typeList=i.newWrapper.down("[aliases=aliastypeList]").writeAttribute("aliases","aliastypeid").observe("change",function(){pageJs.changeAliasType(this)}).observe("focus",function(){$(this).writeAttribute("previousValue",$F(this))}).show(),i.pattern="",void 0!==i.aliasTypeId&&""!==i.aliasTypeId)for(bsuiteJs.postAjax(this.callbackIds.showPattern,{partTypeId:this.requestData.pt.id,typeId:i.aliasTypeId},{onLoading:function(){},onComplete:function(e,a){i.result=bsuiteJs.getResp(a),null!==i.result&&void 0!==i.result&&(i.pattern=i.result.aliasPattern,i.regex=i.result.regex,i.format=i.result.format,""!=i.pattern?i.newWrapper.down("[fieldids=aliasPattern]").writeAttribute("pattern",i.pattern).writeAttribute("regex",i.regex).writeAttribute("format",i.format).update("Valid Pattern: "+i.pattern):i.newWrapper.down("[fieldids=aliasPattern]").writeAttribute("pattern","").writeAttribute("regex","").writeAttribute("format",i.format))}}),a===!1?(i.newWrapper.down("[aliases=boxLabel]").writeAttribute("aliases","keepAlias").update(""),i.newWrapper.down("[aliases=keepAlias]").observe("mouseover",function(e){bsuiteJs.showTooltip(e,"toolTip","Tick to keep this label for next registration<br />without leaving this page.")}),s!==!1?i.newWrapper.down("[aliases=moreOrLess]").insert({before:'<b class="manD">*</b>'}).remove():i.newWrapper.down("[aliases=moreOrLess]").update('<img src="/themes/images/delete.png" style="display:inline-block;width:15px;"/>').show()):(i.newWrapper.down("[aliases=boxLabel]").writeAttribute("aliases","keepAlias").update(""),i.newWrapper.down("[aliases=moreOrLess]").insert({before:'<b class="manD">*</b>'}).remove()),""!=i.pattern&&i.newWrapper.down("[fieldids=aliasPattern]").writeAttribute("pattern",i.pattern).update("Valid Pattern: "+i.pattern),i.typeList.disabled=!0,i.options=i.typeList.options,i.i=0;i.i<i.options.length;i.i+=1)i.options[i.i].value===i.aliasTypeId&&(i.options[i.i].selected=!0);else i.newWrapper.down("[aliases=moreOrLess]").update('<img src="/themes/images/delete.png" style="display:inline-block;width:15px;"/>').show(),i.newWrapper.down("[aliases=boxLabel]").writeAttribute("aliases","keepAlias").update('<input type="checkbox" aliases="keepAliasCheckBox"/>'),i.newWrapper.down("[aliases=keepAlias]").observe("mouseover",function(e){bsuiteJs.showTooltip(e,"toolTip","Tick to keep this label for next registration<br />without leaving this page.")}),i.newWrapper.down("[fieldids=aliasPattern]").writeAttribute("pattern","").writeAttribute("regex","").writeAttribute("format","");0===$$(".added[aliases=wrapper]").size()?i.wrapper.insert({after:i.newWrapper}):$$(".added[aliases=wrapper]").last().insert({after:i.newWrapper})},removeAlias:function(e){var a={};return a.oldValue=$(e).value,$(e).value="Removing Selected PIs ...",$(e).disabled=!0,a.newPIs=this.requestData.pis,void 0===$$('[aliases="pilist"]').first().down("tbody")?($(e).value=a.oldValue,void($(e).disabled=!1)):($$('[aliases="pilist"]').first().down("tbody").getElementsBySelector("tr").each(function(e){a.checked=e.down('input[pislist="checkbox"]').checked,a.sn=e.readAttribute("sn").strip(),a.checked===!0&&-1!==Object.keys(a.newPIs).indexOf(a.sn)&&delete a.newPIs[a.sn]}),this.requestData.pis=a.newPIs,this.displayScannedPIs(),$(e).value=a.oldValue,void($(e).disabled=!1))},changeAliasType:function(e){var a={};return"1"===$(e).options[$(e).selectedIndex].readAttribute("allowmulti")?void(e.readAttribute("previousValue").blank()||$$("[aliases=wrapper] select option[value="+e.readAttribute("previousValue")+"]").each(function(e){e.show().disabled=!1})):(a.notMultiAliasTypeSelected=null,$$(".added[aliases=wrapper] [aliases=aliastypeid]").each(function(t){e!==t&&$F(t)===$F(e)&&(a.notMultiAliasTypeSelected=$F(e))}),void(null!==a.notMultiAliasTypeSelected?(alert("You can NOT add multiple "+this.aliasTypes[a.notMultiAliasTypeSelected]+" to this part!"),$(e).selectedIndex=0,$(e).writeAttribute("previousValue",$F(e))):$$("[aliases=wrapper] select option[value="+$F(e)+"]").each(function(a){e!==a.up("select")&&(a.hide().disabled=!0)})))},selectPT:function(e){var a={};$(e).getElementsBySelector("li").each(function(e){e.hasClassName("selected")===!0&&(a.ptId=e.value)}),this.populateSelectedPT(a.ptId)},populateSelectedPT:function(e){var a={};bsuiteJs.postAjax(this.callbackIds.selectPT,{partTypeId:e,poId:this.requestData.po.id},{onLoading:function(){Modalbox.show("loading",{beforeLoad:function(){Modalbox.deactivate()},title:"Retrieving Part Type Information ..."})},onComplete:function(e,t){a.result=bsuiteJs.getResp(t),null!==a.result&&void 0!==a.result&&pageJs.afterSelectPT(a.result)}})},clearBeforeSelectPT:function(){this.requestData.pt={},this.requestData.pis={},this.displayScannedPIs(),$$('[aliases="aliastypeList"]').first().getElementsBySelector("option").each(function(e){e.show()}),$$("[fieldids=parttype]").first().value="",$$("[fieldids=contracts]").first().update(""),$$("[fieldids=statusList]").first().update(""),$$("[fieldids=serialNoValidator]").first().update(""),$$("[fieldids=aliasPattern]").first().update(""),$$("[fieldids=owner]").first().writeAttribute("value","").update(""),$$("[fieldids=qty]").first().addClassName("disabled").value="",$$("[fieldids=qty]").disabled=!1,$$("[fieldids=serialNo]").first().value="",$$("[fieldids=serialNo]").disabled=!0,$("PiCount").update("0"),$$(".added[aliases=wrapper]").each(function(e){e.remove()}),$$(".loadedAfterPT").each(function(e){e.hide()}),$$('[fieldids="pendingparts"]').each(function(e){e.hide()})},afterSelectPT:function(e){var a={};return this.requestData.pt=e.partType,a.partText=e.partType.partcode+": "+e.partType.name,void 0!==this.requestData.pt.kitTypeId&&""!==this.requestData.pt.kitTypeId?Modalbox.show(new Element("div",{style:"color:red; font-weight:bold;"}).update("Part Type("+a.partText+') has a Kit Type. To Register Kits, please use <a href="/buildkits"/ style="color:blue;"><u>Build Kits</u></a> </hr>page.'),{beforeLoad:function(){Modalbox.activate()},title:"Kit Type Error!"}):($$("[fieldids=pendingparts]").first().update(e.pendingPT),$$(".loadedAfterPT").each(function(e){e.show()}),$$('[fieldids="pendingparts"]').each(function(e){$("showPendingPartInfo").checked?e.show():e.hide()}),$$("[fieldids=parttype]").first().value=a.partText,$("showPendingPartInfo").checked&&$$("[fieldids=pendingparts]").first().show(),void 0!==e.partType.barcodeValidator&&null!==e.partType.barcodeValidator&&$$("[fieldids=serialNoValidator]").first().writeAttribute("regex",e.partType.barcodeValidator.regex).update("Valid Pattern: "+e.partType.barcodeValidator.pattern),$$("[fieldids=aliasPattern]").first().update(""),a.qtyHolder=$$("[fieldids=qty]").first(),a.snHolder=$$("[fieldids=serialNo]").first(),e.partType.serialised===!0?($$("[aliases=pilist]").each(function(e){e.show()}),$$('[aliases="boxLabel"]').each(function(e){e.show()}),a.qtyHolder.value="1",a.qtyHolder.addClassName("disabled").disabled=!0,a.snHolder.value="",a.snHolder.disabled=!1,e.partType.manAliasTypeIds.each(function(e){e.blank()||pageJs.addAlias($$("[aliases=wrapper]").first().down("[aliases=moreOrLess]"),e,!1,!1)}),e.partType.extraAliasTypeIds.each(function(e){e.blank()||pageJs.addAlias($$("[aliases=wrapper]").first().down("[aliases=moreOrLess]"),!1,e,!1)}),e.partType.poAliasTypeIds.each(function(e){e.blank()||pageJs.addAlias($$("[aliases=wrapper]").first().down("[aliases=moreOrLess]"),!1,!1,e)}),a.snHolder.select()):($$("[aliases=pilist]").each(function(e){e.hide()}),$$('[aliases="boxLabel"]').each(function(e){e.hide()}),a.qtyHolder.removeClassName("disabled").value="",a.qtyHolder.disabled=!1,a.snHolder.value=e.partType.bp,a.snHolder.disabled=!0),a.html="","[object Array]"===Object.prototype.toString.call(e.contracts)&&e.contracts.each(function(e){a.html+='<div value="'+e.id+'">'+e.name+"</div>"}),$$("[fieldids=contracts]").first().update(a.html),a.html="","[object Array]"===Object.prototype.toString.call(e.statuses)&&e.statuses.each(function(e){a.html+='<option value="'+e.id+'" '+(e.selected===!0?"selected":"")+">"+e.name+"</option>"}),$$("[fieldids=statusList]").first().update(a.html),$$("[fieldids=owner]").first().writeAttribute("value",e.owner.id).update(e.owner.name),void(void 0===tree||null===tree?loadTree():Modalbox.hide()))},validateBarcode:function(e){var a={};void 0===e||"[object String]"!==Object.prototype.toString.call(e)||e.blank()||(a.barcodeValidator=this.requestData.pt.barcodeValidator,void 0===a.barcodeValidator||void 0===a.barcodeValidator.regex||null===a.barcodeValidator.regex||a.barcodeValidator.regex.blank()||(a.validator=new bsuiteBarcode(a.barcodeValidator.regex),a.validator.validateBarcode(e)))},addPI:function(e){var a={};if(void 0===this.requestData.pt.id||null===this.requestData.pt.id)return alert("Please select a part type first!");if(a.snHolder=$$("[fieldids=serialNo]").first(),a.serialNo=$F(a.snHolder).strip().toUpperCase(),""===a.serialNo)return a.snHolder.select(),alert("Serial number is needed!");try{this.validateBarcode(a.serialNo)}catch(t){return a.snHolder.select(),alert(t)}if(-1!==Object.keys(this.requestData.pis).indexOf(a.serialNo))return a.snHolder.select(),alert("You have added this part(="+a.serialNo+") onto your list already!");if(a.qtyHoder=$$("[fieldids=qty]").first(),a.statusList=$$("[fieldids=statusList]").first(),!$F(a.qtyHoder).strip().match(/^\d+$/)||"NaN"===parseInt($F(a.qtyHoder)))return a.qtyHoder.select(),alert("Invalid qty(="+$F(a.qtyHoder)+")!");if(a.newPi={qty:parseInt($F(a.qtyHoder)),status:{id:$F(a.statusList),name:a.statusList.options[a.statusList.selectedIndex].text},aliases:{}},a.errors=[],a.aliasTypeIdsThatUsedLastTime=[],$$('.added[aliases="wrapper"]').each(function(e){a.typeList=e.down('[aliases="aliastypeid"]'),a.aliasContent=$F(e.down("[fieldids=alias]")).strip(),(a.aliasRegex=e.down('[fieldids="aliasPattern"][regex]').readAttribute("regex"))&&(a.aliasPattern=e.down('[fieldids="aliasPattern"][pattern]').readAttribute("pattern"),a.aliasFormat=e.down('[fieldids="aliasPattern"][format]').readAttribute("format")),a.manAlias=e.down("[class = manD]"),a.aliasTypeId=$F(a.typeList).strip(),""===a.aliasTypeId?a.errors.push("Please select a alias type!"):""===a.aliasContent&&a.manAlias&&a.errors.push(a.typeList.options[a.typeList.selectedIndex].text+": NONE is provided! <br /> "+a.aliasFormat),""!=a.aliasRegex&&""!==a.aliasContent&&(a.aliasContent.match(a.aliasRegex)?e.down("[fieldids=alias]").style.color="black":(e.down("[fieldids=alias]").style.color="red",a.errors.push("Format of alias "+a.typeList.options[a.typeList.selectedIndex].text+" is incorrect! <br /> Format should be "+a.aliasPattern))),-1===a.aliasTypeIdsThatUsedLastTime.indexOf(a.aliasTypeId)&&a.aliasTypeIdsThatUsedLastTime.push(a.aliasTypeId),(void 0===a.newPi.aliases[a.aliasTypeId]||null===a.newPi.aliases[a.aliasTypeId])&&(a.newPi.aliases[a.aliasTypeId]=[]),a.newPi.aliases[a.aliasTypeId].push(a.aliasContent),$$('.added[aliases="wrapper"]').each(function(e){e.down("[fieldids=alias]").disabled=!0})}),a.errors.size()>0)return $$('.added[aliases="wrapper"]').each(function(e){e.down("[fieldids=alias]").disabled=!1}),alert("Error: \n\n - "+a.errors.join("\n - "));if(this.requestData.pt.serialised!==!0)this.requestData.pis[a.serialNo]=a.newPi,pageJs.displayScannedPIs(),$$('.added[aliases="wrapper"]').each(function(e){e.down("[fieldids=alias]").disabled=!1});else{try{this.checkingUniqueAlias(a.newPi.aliases)}catch(t){return $$('.added[aliases="wrapper"]').each(function(e){e.down("[fieldids=alias]").disabled=!1}),alert(t)}a.oldBtnValue=$(e).value,$(e).disabled=!0,$(e).value="Validate Aliases ...",a.requestData={partTypeId:this.requestData.pt.id,serialNo:a.serialNo,aliases:a.newPi.aliases,printBoxLabel:$$('[aliases="boxLabel"]').first().down("input").checked},bsuiteJs.postAjax(this.callbackIds.checkSN,a.requestData,{onComplete:function(t,s){try{a.result=bsuiteJs.getResp(s),void 0!==a.result&&void 0!==a.result.aliases&&null!==a.result.aliases&&(a.newPi.qty="1",a.newPi.aliases="[object Array]"===Object.prototype.toString.call(a.result.aliases)&&0===a.result.aliases.size()?{}:a.result.aliases,pageJs.requestData.pis[a.serialNo]=a.newPi,pageJs.displayScannedPIs(),a.aliasTextBoxes=$$('[aliases="wrapper"] [aliases="alias"] input'),a.aliasTextBoxes.each(function(e){a.keepCheckBox=e.up('[aliases="wrapper"]').down('[aliases="keepAliasCheckBox"]'),(void 0===a.keepCheckBox||null===a.keepCheckBox||a.keepCheckBox.checked!==!0)&&(e.value="")}),a.aliasTextBoxes.first().select())}catch(i){alert(i)}$(e).value=a.oldBtnValue,$(e).disabled=!1,$$('.added[aliases="wrapper"]').each(function(e){e.down("[fieldids=alias]").disabled=!1})}})}},checkingUniqueAlias:function(e){var a={};if(0!==this.requestData.pt.manUniqueAliasTypeIds.size()&&(a.aliasTypeTranslator=this.aliasTypes,a.manUniqueAliasTypeIds=this.requestData.pt.manUniqueAliasTypeIds,a.scannedPIs=this.requestData.pis,a.duplidatedAlias=[],$H(e).each(function(e){a.typeId=e.key.strip(),-1!==a.manUniqueAliasTypeIds.indexOf(a.typeId)&&Object.keys(a.scannedPIs).each(function(t){if(a.scannedAliases=a.scannedPIs[t].aliases[a.typeId],"[object Array]"!==Object.prototype.toString.call(a.scannedAliases))throw"System Erorr: scanned aliases structure messed up!";a.scannedAliases.each(function(s){-1!==e.value.indexOf(s)&&a.duplidatedAlias.push(a.aliasTypeTranslator[a.typeId]+"(="+s.strip()+") for part:"+t)})})}),a.duplidatedAlias.size()>0))throw"There is/are unique alias found on the scanned part list:\n\n - "+a.duplidatedAlias.join("\n - ")},displayScannedPIs:function(){var e={};$$('[aliases="aliasList"]').first().update(""),$("PiCount").update("0"),0!==Object.keys(this.requestData.pis).size()&&($("PiCount").update(Object.keys(this.requestData.pis).size()),e.pis=this.requestData.pis,e.aliasTypeIds=[],$H(e.pis).each(function(a){$H(a.value.aliases).each(function(a){-1===e.aliasTypeIds.indexOf(a.key)&&e.aliasTypeIds.push(a.key)})}),e.aliasTypeIds.uniq(),e.aliasTypeTranslator=this.aliasTypes,e.html='<table class="DataList">',e.html+="<thead><tr><td><input type='checkbox' pislist='checkbox' onclick=\"pageJs.checkAllPIs(this);\"/></td><td>S/N</td><td>Qty</td><td>Status</td>",e.aliasTypeIds.each(function(a){e.html+="<td>"+e.aliasTypeTranslator[a]+"</td>"}),e.html+="</tr></thead><tbody>",e.rowNo=0,$H(e.pis).each(function(a){e.html+='<tr class="'+(e.rowNo%2===0?"DataListItem":"DataListAlterItem")+'" sn="'+a.key+'">',e.html+='<td><input type="checkbox" pislist="checkbox"/></td>',e.html+='<td pislist="sn">'+a.key+"</td>",e.html+="<td>"+a.value.qty+"</td>",e.html+="<td>"+a.value.status.name+"</td>",e.aliasTypeIds.each(function(t){e.value="",void 0!==a.value.aliases[t]&&null!==a.value.aliases[t]&&(e.value='<div class="multialias"><div>'+(a.value.aliases[t].size()>1?" - "+a.value.aliases[t].join("</div><div> - "):a.value.aliases[t].join(""))+"</div>"),e.html+="<td>"+e.value+"</td>"}),e.html+="</tr>",e.rowNo+=1}),e.html+="</tbody></table>",$$('[aliases="aliasList"]').first().update(e.html))},focusToNextBlankAlias:function(e){if(!(e.which&&13==e.which||e.keyCode&&13==e.keyCode))return!0;var a={};a.foundBlank=!1,$$("[aliases=wrapper] [aliases=alias] input").each(function(e){return a.foundBlank===!1&&e.value.blank()?(e.select(),void(a.foundBlank=!0)):void 0}),a.foundBlank===!1&&$$('[aliases="pilist"] [aliases=addPIBtn]').first().click()},printPendingParts:function(e){var a={};a.newDiv=new Element("div").update('<div class="row" fieldids="pendingparts">'+$(e).up('[fieldids="pendingparts"]').innerHTML+"</div>"),a.newDiv.down("table").writeAttribute("style","width:100%;").writeAttribute("border","1").insert({before:'<a style="float:right;" href="javascript: void(0);" onclick="window.print();">Print</a>'}),a.newDiv.down("input.printBtn").remove(),a.html='<head><title>Printing Pending Parts</title></head><body onload="window.print();">'+a.newDiv.innerHTML+"</body>",this.writeConsole(a.html,"PrintingWindow")},checkAllPIs:function(e){$(e).up("table").down("tbody").getElementsBySelector("tr").each(function(a){a.down('[pislist="checkbox"]').checked=$(e).checked})},registerPIs:function(){var e={};if(document.getElementById("errors").innerHTML="",void 0===this.requestData.pt.id||null===this.requestData.pt.id)return alert("Please select a part type first!");if(pageJs.requestData.pt.serialised===!1){if(this.requestData.pis={},$$('[aliases="pilist"] [aliases=addPIBtn]').first().click(),0===Object.keys(this.requestData.pis).size())return}else if(e.error="",Object.keys(this.requestData.pis).each(function(a){try{pageJs.validateBarcode(a)}catch(t){e.error+="\n - "+t}}),!e.error.blank())return alert("Invalid Barcode in your list: "+e.error);return 0===Object.keys(this.requestData.pis).size()?(alert("Please add some part instances first!"),void Modalbox.hide()):(e.html='<div process="header">Processing: <span process="total">'+Object.keys(pageJs.requestData.pis).size()+"</span> part instance(s)</div>",Modalbox.show(new Element("div").update(e.html),{beforeLoad:function(){Modalbox.deactivate()},afterLoad:function(){Modalbox.resizeToContent()},title:'<b class="manD">Processing Part Instances, please DO NOT close or REFRESH the browser!</b>',width:700}),void bsuiteJs.postAjax(this.callbackIds.checkWH,{warehouseId:$F($(this.WHHolderId))},{onComplete:function(a,t){try{e.result=bsuiteJs.getResp(t,!1,!0)}catch(s){return alert("Error Occurred: "+s),void Modalbox.hide()}return void 0===e.result.warehouse.id||null===e.result.warehouse.id?(alert("System Error: warehouse information missing!"),void Modalbox.hide()):(pageJs.requestData.whId=e.result.warehouse.id,void pageJs.processPIs())}}))},resetErrors:function(){this.errors=""},setErrors:function(e){return this.errors+=e,this.errors},processPIs:function(e){var a={};a.me=this,a.errors=this.errors,a.currentIndex=void 0===e||null===e?1:e,a.keys=Object.keys(this.requestData.pis),a.serialNo=a.keys[a.currentIndex-1],a.requestData={ptId:this.requestData.pt.id,po:this.requestData.po,whId:this.requestData.whId,pis:{}},a.requestData.pis[a.serialNo]=this.requestData.pis[a.serialNo],bsuiteJs.postAjax(this.callbackIds.registerPI,a.requestData,{onComplete:function(e,t){a.error="";try{a.result=bsuiteJs.getResp(t,!1,!0)}catch(s){a.error=s}""!==a.error&&(a.errors=pageJs.setErrors(a.error+"<br>")),a.currentIndex>=a.keys.size()?""==a.errors?(alert("All ("+a.keys.size()+") part instance(s) registered successfully!"),1==a.me.closeAfterSave?window.close():window.location=window.location.href):(document.getElementById("errors").innerHTML=a.errors,Modalbox.hide(),pageJs.resetErrors()):pageJs.processPIs(1*a.currentIndex+1)}})},enterToSelectPT:function(e,a){return e.which&&13==e.which||e.keyCode&&13==e.keyCode?void $(a).getElementsBySelector("li").each(function(e){return e.hasClassName("selected")?void $(e).click():void 0}):!0},_setCloseAfterSave:function(e){this.closeAfterSave=e.value},writeConsole:function(e,a){var t={};t.consoleRef=this.openNewWindow("",a.replace(" ","_"),this.openNewWindowParams),t.consoleRef.document.writeln("<html>"+e+"</html>"),t.consoleRef.document.close()},openNewWindow:function(e,a,t){void 0===t&&(t=this.openNewWindowParams);var s=window.open(e,a,t);return s.focus&&s.focus(),s}};