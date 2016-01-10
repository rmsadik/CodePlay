<com:TPanel style="<%= $this->cssStyle %>;">
	<script type="text/javascript">
		function deleteContract_<%=$this->getId()%>(id,name,contractHTMLId,showContractsBtnId,currentContractGroupId,contractcount,selectedContractGroup,listcount)
		{	
			if ((selectedContractGroup == currentContractGroupId && contractcount >= 2 ) ||(selectedContractGroup != currentContractGroupId && listcount >= 2) )
			{
				if(!confirm('Do you want to delete ' + name + ' from this part type?'))
					return false;
			
				mb.showLoading('deleting contract');
				
				var exsitingIds = $(contractHTMLId).value.split(',');
				var idx = exsitingIds.indexOf('' + id);
				if(idx!=-1){exsitingIds.splice(idx, 1);}
				$(contractHTMLId).value = exsitingIds.join(',');
				$(showContractsBtnId).click();
				listcount--;
				
				if(selectedContractGroup == currentContractGroupId)
					contractcount--;
			}
			else
			{
				if(selectedContractGroup == currentContractGroupId)
					alert ("Selected Contract Group must have at least one contract");
				else
					alert ("At least one contract must be selected");
			}
		}
		
		function addContract_<%=$this->getId()%>(contractListId,contractHTMLId,showContractsBtnId,externalCode)
		{ 
			mb.showLoading('adding contract');
			
			// The logic was updated to add multiple contract but not the same from the contractlist
			var newId = '';
			var myContractListObj = document.getElementById(contractListId);
			
			for(i=0; i<myContractListObj.options.length; i++) {
				if (myContractListObj.options[i].selected == true) {
					if(newId != '') {
						newId += ",";
					}
					newId +=  myContractListObj.options[i].value;
				}
			}
			var exsitingIds = $(contractHTMLId).value.replace(' ','');
			
			if(exsitingIds=='')
			{
				exsitingIds = newId;
			}
			else
			{
				// Old and New data Together
				exsitingIds = exsitingIds + ',' + newId;
				
				// Remove Duplicates
			 	var exsitingIds_array = exsitingIds.split(',');
			 	var newIdArray = new Array();
				
				for(var i=0;i<exsitingIds_array.length;i++)
				{
					var foundIdx = newIdArray.indexOf(exsitingIds_array[i]);
					if (foundIdx == -1) {
						newIdArray.push(exsitingIds_array[i]);
					}
				}
				// Rebuild array
				exsitingIds = newIdArray.join(',');
			}
			
			$(contractHTMLId).value = exsitingIds;
			$(showContractsBtnId).click();
		}
		
		
		function hideAliasList_<%=$this->getId()%>(show)
		{
			cleanListBox_<%=$this->getId()%>();
		}
	</script>

	<com:TActivePanel ID="aliasPane" GroupingText="Part Instance Aliases" >
		<com:TActiveLabel ID="aliasListLabel" style="width:60%;margin: 5px;" />
	</com:TActivePanel>
	
	<com:TPanel ID="contractGroupPane" GroupingText="Contract Groups"
		style="padding:7px;" >
		<br>
		<com:TActiveCheckBox ID="contractGroupCheck"
		    Text=" Link to Selected Contract Group"
		    OnCheckedChanged="linkContractGroup" >     
		</com:TActiveCheckBox>
		<com:TActiveDropDownList ID="contractGroupList" DataValueField="id" AutoPostBack="true"
			DataTextField="groupname" promptText="Please Select...."
			style="width:85%;margin: 5px;" OnSelectedIndexChanged = "getSelectedContractGroupList">
			<prop:ClientSide OnLoading="$( 'addBtn').hide();"
							OnComplete="$('addBtn').show();" />
		</com:TActiveDropDownList>
	</com:TPanel>
	<com:TPanel ID="contractPane" GroupingText="Contracts"
		style="padding:7px;">		
		<com:TActiveListBox ID="contractList" AutoPostBack="false" 
			DataValueField="id" DataTextField="contractname" 
			SelectionMode="Multiple" style="width:95%;margin: 5px;" Rows="8"/>
		<i style="font-size: 9px; width: 95%; margin: 0 5px 5px 5px;">Ctrl
		+ click to select multiple</i>

		 <input type="Button" id="addBtn" value="Add" onclick="addContract_<%=$this->getId()%>('<%= $this->contractList->getClientId() %>','<%= $this->contractIds->getClientId() %>','<%= $this->showContractsBtn->getClientId()%>','<%= $this->partTypeExtCode->getClientId() %>');return false;" /> 
		
		<img id="addContractLoading_<%=$this->getId()%>" src="/themes/images/ajax-loader.gif" style="display: none;"/>
		<com:TActiveHiddenField ID="contractIds" />
		<com:TActiveHiddenField ID="partTypeExtCode" />
		<com:TActiveHiddenField ID="clientId" />
		<com:TActiveLabel ID="contractListLabel"
			style="width:65%;margin: 5px;" />
		<com:TRequiredFieldValidator ControlToValidate="contractIds"
			ErrorMessage="At least ONE Contract Required" ValidationGroup="<%= $this->validationGroup %>" EnableClientScript="true" Display="Dynamic"/>
		<com:TActiveButton ID="showContractsBtn" style="display:none;"
				OnClick="showContracts">
				<prop:ClientSide 
				    OnLoading="$( 'addContractLoading_<%=$this->getId()%>').show();"
					OnComplete="mb.hide(); $('addContractLoading_<%=$this->getId()%>').hide();" />
			</com:TActiveButton>
	</com:TPanel>
</com:TPanel>