<com:TActivePanel GroupingText="<%= $this->groupingText %>" style="<%= $this->cssStyle %>">
    <com:TActiveLabel ID="errorMsg" style="font-weight:bold;color:red;"/>
    <com:TActiveLabel ID="infoMsg" style="font-weight:bold;color:green;"/>
        <com:TClientScript>
            var scannedData_<%= $this->getClientId() %> = {};
            var rowNo=0;
            var alertConfirmed = false;

            function doEnterBarcode_<%=$this->getClientId() %>(event,btnId)
            {
                if((event.which && event.which == 13) || (event.keyCode && event.keyCode == 13))
                {
                    if ($('<%= $this->serialNo->getClientId() %>').value == '')
                    {
                        //this is to ensure it doesn't do the page submit, weird but it works
                        setTimeout(function() {alert('Please provide the BS|BX|BP')}, 100);
                        return false;
                    }
                    $(btnId).click();
                    return false;
                }
                else
                {
                    if(event.which){
                        var key = String.fromCharCode(event.which);
                        var keycode = event.which;
                    }else{
                        var key = String.fromCharCode(event.keyCode);
                        var keycode = event.keyCode;
                    }

                    //check and remove all char that are not alphaNumeric or nav keys
                    var re = /^[a-zA-Z_0-9]$/; // all alphaNumeric keys

                    var keys = new Array(37,40,39,38,46,45,35,34,33,8,20,16,96); //all nav keys includes delete

                    var isValidKey = false
                    var length = keys.length;
                    for(var i = 0; i < length; i++) {
                        if(keys[i] == keycode) isValidKey = true;
                    }
                    if (!re.test(key) && !isValidKey)
                    {
                        return false;
                    }

                }
                return true;
            }

            function doEnterPartCode_<%=$this->getClientId() %>(event,btnId)
            {
                if((event.which && event.which == 13) || (event.keyCode && event.keyCode == 13))
                {
                    if ($('<%= $this->partTypeAlias->getClientId() %>').value == '')
                    {
                        //this is to ensure it doesn't do the page submit, weird but it works
                        setTimeout(function() {alert('Please provide the Part Code|FRU')}, 100);
                        return false;
                    }
                    $(btnId).click();
                    return false;
                }
                else
                {
                    if(event.which){
                        var keycode = event.which;
                    }else{
                        var keycode = event.keyCode;
                    }
                    if(220 == keycode) return false; // check and remove any '\'
                }
                return true;
            }

            function doEnterBehaviorForQty_<%=$this->getClientId() %>(event,tableIndex)
            {
                if((event.which && event.which == 13) || (event.keyCode && event.keyCode == 13))
                {
                    setTimeout(function() {changeQty_<%=$this->getClientId() %>(tableIndex);}, 100);
                    return false;
                }
                return true;
            }

            function addSearchingPartDetails_<%= $this->getClientId()%>()
            {
                var serialNo =$('<%= $this->serialNo->getClientId() %>');
                var partType =$('<%= $this->partTypeAlias->getClientId() %>');

                serialNoValue = serialNo.value.replace(' ','');
                partTypeValue = partType.value.replace(' ','');

                serialNoValue = serialNoValue.replace(/^\s+|\s+$/g, "");
                partTypeValue = partTypeValue.replace(/^\s+|\s+$/g, "");

                if (serialNoValue=="" && partTypeValue=="")
                {
                    alert('Please provide the BS|BX|BP or the Part Code|FRU');
                    return false;
                }
                else
                {
                    toggleFields(true);

                    var tableIndex = serialNoValue + "_" + partTypeValue + "_" + (rowNo++);

                    scannedData_<%= $this->getClientId() %>[tableIndex] = {'partInstanceId':'','partTypeId':'','partInstanceStatusId':'','quantity':0};

                    $('<%= $this->cappedData->getClientId() %>').value = Object.toJSON(scannedData_<%= $this->getClientId() %>);
                    $('<%= $this->searchPartBtn_tableIndex->getClientId() %>').value = tableIndex;
                    $('<%= $this->ajaxSearchPartBtn->getClientId() %>').click();
                }
            }

            function displayResultTable_<%= $this->getClientId()%>()
            {
                $('dataDisplayPanel_<%= $this->getClientId() %>').innerHTML ="";

                var header ="<br><input type='button' value='<%= $this->searchBtnText %>' onClick='submitData_<%=$this->getClientId() %>();return false;' /><table width='100%' style='font-size:10px;'><tr style='background:#000000;color:#ffffff;height:24px;'><td width='20px'>&nbsp;</td><td width='150px'>Scanned Data</td><td>Part Details</td></tr>";
                var footer = "</table><input type='button' value='<%= $this->searchBtnText %>' onClick='submitData_<%=$this->getClientId() %>();return false;' />";
                var body = "";
                var count=0;

                for ( var i in scannedData_<%= $this->getClientId() %>)
                {
                    var oneRowBody = "";
                    var row = scannedData_<%= $this->getClientId() %>[i];
                    var indes = i.split("_");
                    var serialNo = indes[0];
                    var partTypeAlias = indes[1];

                    oneRowBody += "<tr id='row_" + i + "'";
                        if(count %2==1) oneRowBody +=" style='background:#cccccc;'"
                    oneRowBody += ">";
                        oneRowBody += "<td>";
                            oneRowBody += "<a onclick=\"deleteRow_<%=$this->getClientId() %>('" + i + "');return false;\" > <img src=\"/themes/images/delete.png\" /></a>";
                        oneRowBody += "</td>";
                        oneRowBody += "<td>";
                            oneRowBody += "<b>S/N:</b> " + serialNo;
                            oneRowBody += "<br /><b>Part Type:</b> " + partTypeAlias;
                        oneRowBody += "</td>";
                        oneRowBody += "<td id='"+i + "_details'>";
                            if(typeof row.partDetails =="undefined")
                                oneRowBody += "<img src='/themes/images/ajax-loader.gif' />";
                            else
                                oneRowBody += row.partDetails;
                        oneRowBody += "</td>";
                    oneRowBody += "</tr>";
                    body = oneRowBody + body;
                    count++;
                }
                if(count==0)
                    return;

                var countInfo = "<div><b>" + count + "</b> part(s) captured</div>";
                $('dataDisplayPanel_<%= $this->getClientId() %>').innerHTML = countInfo + header +  body + footer;

                if (row.serialised)
                {
                    forceSubmitAlert();
                }
                else
                {
                    toggleFields(false);
                }
            }

            function deleteRow_<%=$this->getClientId() %>(tableIndex)
            {
                if(!confirm('Are you sure you want to delete this item?'))
                    return false;

                delete scannedData_<%= $this->getClientId() %>[tableIndex];
                displayResultTable_<%= $this->getClientId()%>();

                rowNo--;
                toggleFields(false);
            }

            function submitData_<%=$this->getClientId() %>()
            {
                var finalData = {};
                for(var i in scannedData_<%= $this->getClientId() %>)
                {
                    var partInstanceId =scannedData_<%= $this->getClientId() %>[i]['partInstanceId'];
                    var partTypeId =scannedData_<%= $this->getClientId() %>[i]['partTypeId'];

                    if(partInstanceId=="" && partTypeId=="")
                    {
                        var indes = i.split("_");
                        alert('Error on Line:\n\nSerial Number: ' + indes[0] + "\nPartType: "+ indes[1]);
                        $('row_'+ i).style.backgroundColor="#ff0000";
                        $('row_'+ i).style.color="#ffffff";
                        return;
                    }

                    var qty = $(i + '_qty').value;

                    //check qty for TOM Rose, RT #: 12415
                    if(qty==0 || qty=='')
                    {
                        var indes = i.split("_");
                        alert('Error on Line:\n\nSerial Number: ' + indes[0] + "\nPartType: "+ indes[1] + 'Qty can not be 0!');
                        $('row_'+ i).style.backgroundColor="#ff0000";
                        $('row_'+ i).style.color="#ffffff";
                        return;
                    }

                    var statusId = $(i + '_statusId').value;
                    var partTypeId = $(i + '_partTypeId').value;
                    finalData[i]={'partInstanceId': partInstanceId,'partTypeId': partTypeId, 'partInstanceStatusId': statusId, 'quantity':qty}
                }
                mb.showLoading('loading');
                $('<%= $this->cappedData->getClientId() %>').value = Object.toJSON(finalData);
                $('<%= $this->submitDataBtn->getClientId() %>').click();
            }

            function changeQty_<%=$this->getClientId() %>(tableIndex)
            {
                var indes = tableIndex.split("_");
                var serialNo = indes[0];
                var partTypeAlias = indes[1];
                var rowNo=indes[2];
                //change rowbackground to normal
                if(rowNo %2==1)
                {
                    $('row_'+ tableIndex).style.backgroundColor = "#cccccc";
                }
                else
                {
                    $('row_'+ tableIndex).style.backgroundColor = "#ffffff";
                }
                $('row_'+ tableIndex).style.color="#000000";

                var originalHTML = scannedData_<%= $this->getClientId() %>[tableIndex].partDetails;

                //change Qty
                var originalQty =scannedData_<%= $this->getClientId() %>[tableIndex].quantity;
                var newQty = $(tableIndex+'_qty').value;

                if(newQty.length > 10)
                {
                    $(tableIndex+'_qty').value = 0;
                    return false;
                }
                else if (newQty <= 0 || newQty == '' || isNaN(newQty))
                {
                    //this is to ensure it doesn't do the page submit, weird but it works
                    setTimeout(function() {
                       alert('New qty MUST not be EMPTY and MUST be a number greater than 0!');
                       $(tableIndex + '_qty').focus();
                    }, 100);

                    $('row_'+ tableIndex).style.backgroundColor="#ff0000";
                    $('row_'+ tableIndex).style.color="#ffffff";
                    $(tableIndex + '_qty').value = '';
                    return false;
                }
                var newHTML = originalHTML.replace("id='" + tableIndex + "_qty' value='" + originalQty + "' />","id='" + tableIndex + "_qty' value='" + newQty + "' disabled='true'/>");
                $(tableIndex+'_qty').disabled='true';

                //change parttype list
                var newPartType = $(tableIndex+'_partTypeId').value
                newHTML = newHTML.replace("<option name='" + tableIndex + "_partTypeId_option' selected>","<option name='" + tableIndex + "_partTypeId_option'>");
                newHTML = newHTML.replace("<option value='" + newPartType + "' name='" + tableIndex + "_partTypeId_option'>","<option value='" + newPartType + "' name='" + tableIndex + "_partTypeId_option' selected>");
                newHTML = newHTML.replace("<select id='" + tableIndex + "_partTypeId'","<select id='" + tableIndex + "_partTypeId' disabled='true'");
                $(tableIndex+'_partTypeId').disabled='true';

                //change status list
                var newStatus = $(tableIndex+'_statusId').value
                newHTML = newHTML.replace("name='" + tableIndex + "_statusId_option' selected>","<name='" + tableIndex + "_statusId_option'>");
                newHTML = newHTML.replace("<option value='" + newStatus + "' name='" + tableIndex + "_statusId_option'>","<option value='" + newStatus + "' name='" + tableIndex + "_statusId_option' selected>");
                newHTML = newHTML.replace("<select id='" + tableIndex + "_statusId'","<select id='" + tableIndex + "_statusId' disabled='true'");

                $(tableIndex+'_statusId').disabled='true';

                scannedData_<%= $this->getClientId() %>[tableIndex].partDetails = newHTML;
                $('<%= $this->cappedData->getClientId() %>').value = Object.toJSON(scannedData_<%= $this->getClientId() %>);

                forceSubmitAlert();
            }

            function toggleFields(disabled)
            {
                var serialNo = $('<%= $this->serialNo->getClientId() %>');
                var partType = $('<%= $this->partTypeAlias->getClientId() %>');
                var searchBtn = $('<%= $this->searchPartBtn->getClientId() %>');

                serialNo.disabled = disabled;
                partType.disabled = disabled;
                searchBtn.disabled = disabled;

                if (disabled)
                {
                    serialNo.blur();
                    partType.blur();
                }
                else
                {
                    serialNo.focus();

                    serialNo.value = "";
                    partType.value = "";
                }
                alertConfirmed = false;
            }

            function forceSubmitAlert()
            {
                if (rowNo >= 10 && alertConfirmed == false)
                {
                    $('<%= $this->serialNo->getClientId() %>').value = '';
                    $('<%= $this->partTypeAlias->getClientId() %>').value = '';

	                PlayErrorSound();
	                toggleFields(true);
                    alert("You have scanned >= 10 items, please click the 'Save Scanned Parts' button before continuing.");
                    alertConfirmed = true;
                    return;
                }
                if (alertConfirmed == false)
                    toggleFields(false);
            }
        </com:TClientScript>

        <com:TActiveHiddenField ID="cappedData" />
        <com:TActiveHiddenField ID="searchPartBtn_tableIndex" />

        <com:TActiveButton ID="playErrorSoundBtn" OnClick="setErrorSound" style="display:none;" />
        <com:TButton ID="submitDataBtn" OnClick="submitData" style="display:none;" />

        <table width="100%" cellspacing="0" cellpadding="0">
            <tr valign="top">
                <td width="35%">
                    Barcode (BS | BX | BP):<com:TTextBox ID="serialNo" Attributes.onkeydown="return doEnterBarcode_<%=$this->getClientId() %>(event,'<%= $this->searchPartBtn->getClientId()%>');"/>
                </td>
                <td align="left">
                    Part Type (Part Code | FRU):<com:TTextBox ID="partTypeAlias" Attributes.onkeydown="return doEnterPartCode_<%=$this->getClientId() %>(event,'<%= $this->searchPartBtn->getClientId()%>')"/>
                    <com:TActiveButton ID="searchPartBtn" Text="Search" Attributes.OnClick="addSearchingPartDetails_<%= $this->getClientId()%>();return false;"/>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <com:TActiveButton ID="ajaxSearchPartBtn" OnClick="searchPart" style="display:none;">
                        <prop:ClientSide
                            OnLoading=""
                            OnComplete="
                                        scannedData_<%= $this->getClientId() %> = $('<%= $this->cappedData->getClientId() %>').value.evalJSON(true);
                                        displayResultTable_<%= $this->getClientId()%>();"
                        />
                    </com:TActiveButton>
                </td>
                &nbsp;
            </tr>
            <tr>
                <td colspan="2" id="dataDisplayPanel_<%= $this->getClientId() %>"></td>
            </tr>
         </table>
         <bgsound id="soundwav">
         <audio autoplay id="soundogg" preload="auto" />

         <com:TActiveLabel ID="ajaxLabel" />
         <com:TActiveLabel ID="ajaxSound" />

</com:TActivePanel>