/**
 * Easy Admin
 *
 * @copyright: Copyright (C) 2024 Jlowcode Org - All rights reserved.
 * @license  : GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */
define(["jquery","fab/list-plugin"],(function(jQuery,FbListPlugin){var FbListEasyadmin=new Class({Extends:FbListPlugin,options:{inputSearch:"",labelList:"",fatherList:"",valIdEl:0},initialize:function(e){var a=this;this.options=e,jQuery(".modal-body").css("overflow-y","scroll"),jQuery(".table").css("table-layout","fixed"),jQuery("#easyadmin_modal___description_list").addClass("fabrikinput"),Fabrik.addEvent("fabrik.list.submit.ajax.complete",(function(){a.setUpButtonsPainel()})),Fabrik.addEvent("fabrik.list.loaded",(function(){a.setElementAdminsList(),a.setElementType(),a.setElementApproveByVotes(),a.setElementShowInList(),a.setElementLabelAdvancedLink(),a.setUpButtonSave(),a.setUpButtonsPainel(),a.setUpElementList(),a.sortColumns(),window.location.search.indexOf("manage")>0&&jQuery("#button_"+a.options.idModalList).trigger("click"),jQuery("#modal-list .btn-close").on("click",(function(){window.location.search.indexOf("manage")>0&&window.location.replace(a.options.baseUri+window.location.pathname.replace("/",""))}))}))},setUpButtonsPainel:function(){const e=jQuery("th.heading.fabrik_ordercell.fabrik_actions")[0];"inline"==this.options.actionMethod?jQuery(e).find(".btn-group")[0]:jQuery(e).find(".dropdown-menu")[0];this.setButtons(this.options.elements.published),this.setActionPanel(this.options.elements),jQuery(document).ready((function(){jQuery(document).on("mouseenter",".heading.fabrik_ordercell",(function(){jQuery(this).find(":button.elementAdminButton").show()})).on("mouseleave",".heading.fabrik_ordercell",(function(){jQuery(this).find(":button.elementAdminButton").hide()}))}))},createButton:function(e,a){var t=this,s=jQuery('<a href="#'+t.options.idModal+'" data-bs-toggle="modal">'+a.text()+"</a>"),i=jQuery('<li value="'+e+'" style="min-width: 30px;"></li>').css({cursor:"pointer"});return s.appendTo(i),a.contents().filter((function(){return!this.classList||!this.classList.contains("fabrikorder")&&!this.classList.contains("fabrikorder-asc")&&!this.classList.contains("fabrikorder-desc")})).remove(),a.contents().filter((function(){return!!this.classList&&(this.classList.contains("fabrikorder")||!this.classList.contains("fabrikorder-asc")||!this.classList.contains("fabrikorder-desc"))})).after(i),s.on("click",(function(){t.setModalToEditElement(this)})),i},setElementDatabasejoin:function(){var e=this;jQuery("#modal-elements .select2-search__field").on("change",(function(){jQuery(this).remove(),e.options.inputSearch=jQuery(this)})),jQuery(document).on("click","#modal-elements .select2-selection__choice__remove",(function(){e.options.inputSearch.on("change",(function(){jQuery(this).remove(),e.options.inputSearch=jQuery(this)})),jQuery(this).parent().remove(),jQuery("#modal-elements .select2-selection__rendered li").append(e.options.inputSearch),e.setUpElementList()})),jQuery("#modal-elements .select2-selection").on("click",(function(){var a=jQuery(this);0==a.find("#modal-elements .select2-search__field").length&&0==a.find("#modal-elements .select2-selection__rendered li").length&&(a.find("#modal-elements .select2-search").append(e.options.inputSearch),e.setUpElementList())})),this.setUpElementList()},setUpElementList:function(){var e=this,a="#"+e.options.dbPrefix+"fabrik_easyadmin_modal___listas",t=jQuery(a+"-auto-complete"),s=jQuery(a);t.on("focusout",(function(){setTimeout((function(){e.searchElementList()}),500)})),0==jQuery(".refresh_label").length&&(elRefresh=jQuery(e.options.images.refresh),elRefresh.addClass("refresh_label"),elRefresh.css("margin-left","5px"),elRefresh.on("click",(function(){e.searchElementList()})),s.closest(".fabrikElementContainer").find(".form-label").after(elRefresh))},searchElementList:function(){var self=this,baseUri=this.options.baseUri,idEl="#"+self.options.dbPrefix+"fabrik_easyadmin_modal___listas",tid=jQuery(idEl).val();if(tid){var db_table_name=tid,url=baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=element&plugin=field&method=ajax_fields&showall=1&cid=1&t='"+db_table_name+"'";jQuery.ajax({url:url,method:"get",data:{showRaw:!1,k:2}}).done((function(r){var opts=eval(r),els=document.getElementsByClassName("child-element-list"),notShow=["id","created_by","created_date","created_ip","indexing_text","updated_by","updated_date"];jQuery(".child-element-list").each((function(e,a){jQuery(a).empty()})),Array.each(els,(function(e){x=0,opts.forEach((a=>{if(!notShow.includes(a.value)){e.id.indexOf("label")>0?val=self.options.labelList:val=self.options.fatherList;var t={value:a.value};a.value===val?(t.selected="selected",x=1):"name"==a.value&&e.id.indexOf("___label")>0&&0==x&&(t.selected="selected"),new Element("option",t).set("text",a.label).inject(e)}}))}))}))}},setElementAdminsList:function(){var e={},a=jQuery("#easyadmin_modal___viewLevel_list").val(),t=this.options.baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=list&plugin=easyadmin&method=getUsersAdmins";e.viewLevel=a,jQuery.ajax({url:t,method:"post",data:e}).done((function(e){var a=document.querySelectorAll('[name="easyadmin_modal___admins_list[]"]'),t=[];e=JSON.parse(e);for(var s=0;s<a[0].options.length;s++)t.push(a[0].options[s].value);for(const n of e)if(!t.includes(n.id)){var i=[];newOption=new Option(n.name,n.id,!1,!1),jQuery(a).append(newOption).trigger("change");for(s=0;s<a[0].options.length;s++)i.push(a[0].options[s].value);jQuery(a).val(i).trigger("change")}}))},setElementType:function(e=""){var a=jQuery("#easyadmin_modal___type"+e),t=""==e?"#modal-elements":".modalContent";a.on("change",(function(){type=jQuery(this).val(),jQuery(t+" .modal-element").each((function(e,a){elementClass=jQuery(this).prop("class"),elementClass.indexOf("element-")<0&&(elementClass.indexOf("type-"+type)<0?jQuery(this).parent().addClass("fabrikHide"):jQuery(this).parent().removeClass("fabrikHide"))}))})),type=a.val(),jQuery(t+" .modal-element").each((function(e,a){elementClass=jQuery(this).prop("class"),elementClass.indexOf("element-")<0&&(elementClass.indexOf("type-"+type)<0?jQuery(this).parent().addClass("fabrikHide"):jQuery(this).parent().removeClass("fabrikHide"))}))},setElementVisibilityList:function(){var e=this;jQuery("#easyadmin_modal___visibility_list").on("change",(function(a,t){e.showHideElements("visibility_list","list","dropdown")})),jQuery("#easyadmin_modal___visibility_list").trigger("change")},setElementApproveByVotes:function(){var e=this;jQuery('input[name="easyadmin_modal___approve_by_votes_list"]').on("change",(function(a,t){e.showHideElements("approve_by_votes_list","list","yesno")})),jQuery('input[name="easyadmin_modal___approve_by_votes_list"]').trigger("change")},setElementShowInList:function(){var e=this;jQuery('input[name="easyadmin_modal___show_in_list"]').on("change",(function(a,t){0==e.options.valIdEl&&void 0===t||e.showHideElements("show_in_list","element","yesno")}))},setElementLabelAdvancedLink:function(e=""){var a=this,t=jQuery('label[for="easyadmin_modal___label_advanced_link'+e+'"]');t.on("click",(function(t,s){id=jQuery(this).attr("for").split("___")[1],e=null!=s?s.sufix:"",e=id.indexOf("_wfl")>0?"_wfl":"",a.showHideElements(id,"element","label",s,e)})),t.hover((function(){$(this).css({cursor:"pointer"})}),(function(){$(this).css({cursor:"default"})}))},showHideElements:function(e,a,t,s="",i=""){var n=""==i?"#modal-elements":".modalContent";"list"==a&&(n="#modal-list"),jQuery(n+" .modal-"+a).each((function(){if(elementClass=jQuery(this).prop("class"),elementClass.indexOf(a+"-"+e)>0){switch(t){case"yesno":show=!!jQuery("#easyadmin_modal___"+e+"1").prop("checked")||"";break;case"dropdown":show=jQuery("#easyadmin_modal___"+e).val();break;case"label":"edit-element"==s.button?show=!1:show=jQuery(this).parent().prop("class").indexOf("fabrikHide")>0&&"link"==jQuery("#easyadmin_modal___type").val()}"visibility_list"==e&&(show="3"==show),show?jQuery(this).parent().removeClass("fabrikHide"):jQuery(this).parent().addClass("fabrikHide")}}))},setUpButtonSave:function(){elSaveElements=jQuery("#easyadmin_modal___submit_elements"),elSaveList=jQuery("#easyadmin_modal___submit_list"),elSaveElements.on("click",(()=>this.saveEvent("elements"))),elSaveList.on("click",(()=>this.saveEvent("list")))},saveEvent:function(e,a=""){if(self=this,valEls={},inputs=jQuery(".fabrikinput"),listId=jQuery("[name=listid]").val(),db_table_name=jQuery("[name=db_table_name]").val(),history_type=jQuery("[name=history_type]").val(),valEls.easyadmin_modal___mode=e,valEls.easyadmin_modal___listid=listId,valEls.easyadmin_modal___history_type=history_type,valEls.jform={db_table_name:db_table_name},valEls.easyadmin_modal___valIdEl="columns"==e?a.idAtual:self.options.valIdEl,inputs.each((function(){switch(id=this.id,id){case"easyadmin_modal___use_filter1":case"easyadmin_modal___required1":case"easyadmin_modal___trash1":case"easyadmin_modal___show_in_list1":case"easyadmin_modal___show_down_thumb1":case"easyadmin_modal___ajax_upload1":case"easyadmin_modal___make_thumbs1":case"easyadmin_modal___multi_select1":case"easyadmin_modal___tags1":case"easyadmin_modal___multi_relation1":case"easyadmin_modal___trash_list1":case"easyadmin_modal___workflow_list1":case"easyadmin_modal___approve_by_votes_list1":case"easyadmin_modal___comparison_list1":case"easyadmin_modal___white_space1":id=id.replace("1",""),valEls[id]=!!jQuery(this).prop("checked")||"";break;case"easyadmin_modal___options_dropdown":valEls[id]=jQuery(this).val().join(",");break;case"easyadmin_modal___description_list":valEls[id]=tinyMCE.activeEditor.getContent();break;default:valEls[id]=jQuery(this).val()}})),valEls.easyadmin_modal___admins_list=jQuery('[name="easyadmin_modal___admins_list[]"]').val(),"list"==e&&(valEls.order_by=[valEls.easyadmin_modal___ordering_list],valEls.order_dir=[valEls.easyadmin_modal___ordering_type_list]),"link"==valEls.easyadmin_modal___type&&0==valEls.easyadmin_modal___valIdEl){var t="";optsOrderingEls=jQuery("#easyadmin_modal___ordering_elements option"),optsOrderingEls.each((function(e,a){t="ID"==a.label||"Criado por"==a.label?a.value:t})),valEls.easyadmin_modal___ordering_elements=t}"columns"==e&&(valEls.easyadmin_modal___ordering_elements=a.idOrder),tinyMCE.activeEditor.save();var s=self.options.baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=list&plugin=easyadmin&method=SaveModal&id=0",i=!1;if(self.options.workflow&&"elements"==e){var n=self.options.baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=form&plugin=workflow&method=hasPermission&id=0";jQuery.ajax({url:n,method:"post",data:valEls}).done((function(e){if(i=JSON.parse(e),valEls.hasPermission=i?"1":"0",i)a=valEls;else{s=self.options.baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=list&plugin=easyadmin&method=validateElements";var a={formData:valEls,requestWorkflow:"1",listid:valEls.easyadmin_modal___listid}}jQuery.ajax({url:s,method:"post",data:a}).done((function(e){e=JSON.parse(e),urlLog=self.options.baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=form&plugin=workflow&method=createLog",e.error?alert(e.message):self.requestWorkflow(urlLog,valEls,i)}))}))}else self.requestWorkflow(s,valEls,!0)},requestWorkflow:function(e,a,t){jQuery.ajax({url:e,method:"post",data:a}).done((function(e){(e=JSON.parse(e)).error?alert(e.message):(msg=t?Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_SUCCESS"):e.message,alert(msg),window.location.replace(self.options.baseUri+window.location.pathname.replace("/","")))}))},setButtons:function(e){for(var a in e)if(e.hasOwnProperty(a)&&e[a].enabled&&e[a].show_in_list){var t=jQuery("th."+e[a].fullname).children();t.css({display:"flex"}),t.addClass("tooltip2"),this.createButton(a,t),t.css({"min-width":"120px;"})}},setActionPanel:function(e){if("inline"==this.options.actionMethod)this.setActionPanelInline(e);else{if("dropdown"!=this.options.actionMethod)throw new Error(Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ACTION_METHOD_ERROR"));this.setActionPanelDropdown(e)}},setActionPanelInline:function(e){var a=this,t=jQuery('<a class="btn fabrik__rowlink btn-default"><span>'+this.options.images.settings+'</span><span class="hidden">'+Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADMIN")+"</span></a>"),s=jQuery("th.heading.fabrik_ordercell.fabrik_actions")[0],i=jQuery(s).find(".btn-group")[0],n=jQuery("<div></div>").on("mouseover",(function(){jQuery(".trashEl").css("display","block")})).on("mouseout",(function(){jQuery(".trashEl").css("display","none")})),o=jQuery('<li><button id="button_'+a.options.idModalList+'" href="#'+a.options.idModalList+'" data-bs-toggle="modal" type="button">'+Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_EDIT_LIST")+"</button></li>"),l=jQuery('<li><button href="#'+a.options.idModal+'" data-bs-toggle="modal" type="button">'+Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT")+"</button></li>");if(!i){var r=jQuery('<div class="btn-group"></div>');jQuery(s).find("span").append(r),i=jQuery(s).find(".btn-group")[0]}var d=jQuery(i);d.css("width","200px"),d.css("height","300px"),d.css("overflow-y","auto"),d.css("overflow-x","auto"),this.setCssAndEventsButtons(o,l);var _=jQuery("<div></div>");_.css({"font-size":"12px",position:"absolute","z-index":100,"background-color":"#FFF",display:"none",right:"50%",padding:"10px",border:"2px solid #eee","border-radius":"4px","text-align":"left",width:"150px"}),t.on("click",(function(){"none"==jQuery(_).css("display")?jQuery(_).css({display:"block"}):jQuery(_).css({display:"none"})})),d.append(t),_.append(l),a.options.owner_id==a.options.user.id&&_.append(o),jQuery.each(e,(function(e,t){if("trash"==e){var s=jQuery('<li style="font-size: 12px; margin: 10px 0px 0px 8px;"></li>').appendTo(n);jQuery(a.options.images.trash).appendTo(s);jQuery("<b>").text(Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_TRASH")).css({"padding-left":"5px",color:"#011627","vertical-align":"middle"}).appendTo(s)}jQuery.each(t,(function(t,s){var i=jQuery('<li value="'+t+'" style="padding-left:10px; font-size: 12px; '+("trash"==e?"display: none":"")+'" class="'+("trash"==e?"trashEl":"")+'"></li>').appendTo("trash"==e?n:_);if(s.enabled)var o=jQuery('<a style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;" href="#'+a.options.idModal+'" data-bs-toggle="modal"></a>').text(a.options.elementsNames[t]).css({cursor:"pointer","padding-left":"10px"}).appendTo(i);else o=jQuery("<b/>").text(a.options.elementsNames[t]).css({"padding-left":"10px",color:"#999"}).appendTo(i);o.on("click",(function(){a.setModalToEditElement(this)}))}))})),n.appendTo(_),d.append(_)},setModalToEditElement:function(e){var a=this,t=jQuery(e).parent().prop("value"),s=a.options.allElements[t];a.options.valIdEl=t,s.enabled&&(jQuery("#easyadmin_modal___history_type").val(s.type),jQuery.each(s,(function(t,s){if(e=jQuery("#easyadmin_modal___"+t),el2=jQuery("#"+a.options.dbPrefix+"fabrik_easyadmin_modal___"+t),e.length>0||el2.length>0)if(0==(e=0==e.length?el2:e).find(".switcher").length)switch(e.val(s),t){case"label":a.options.labelList=s;break;case"father":a.options.fatherList=s;break;case"listas":jQuery("#"+a.options.dbPrefix+"fabrik_easyadmin_modal___listas").val(s),jQuery("#"+a.options.dbPrefix+"fabrik_easyadmin_modal___listas-auto-complete").val(s);break;case"width_field":case"ordering_elements":a.showHideElements("show_in_list","element","yesno");break;case"options_dropdown":vals=s.split(","),vals.forEach((function(e){jQuery("#easyadmin_modal___"+t).append(jQuery("<option>",{value:e.trim(),text:e.trim(),selected:!0}))}))}else p=s?1:0,elSw=e.find("#easyadmin_modal___"+t+p),elSw.prop("checked",!0)})));var i=jQuery("#easyadmin_modal___type").val();("treeview"==i||"autocomplete"==i)&&jQuery("#jlow_fabrik_easyadmin_modal___listas-auto-complete").prop("disabled","disabled"),jQuery("#easyadmin_modal___type").trigger("change"),jQuery("#easyadmin_modal___options_dropdown").trigger("chosen:updated"),jQuery('label[for="easyadmin_modal___label_advanced_link"]').trigger("click"),jQuery("#"+a.options.dbPrefix+"fabrik_easyadmin_modal___listas-auto-complete").trigger("focusout"),jQuery("#easyadmin_modal___options_dropdown").parent().find("#easyadmin_modal___options_dropdown_chosen").css("width","95%");var n=jQuery("#easyadmin_modal___white_space");"url"==s.text_format||"link"==s.type?(n.find("#easyadmin_modal___white_space0").prop("checked",!0),n.closest(".fabrikElementContainer").addClass("fabrikHide")):n.closest(".fabrikElementContainer").removeClass("fabrikHide")},setActionPanelDropdown:function(e){var a=this,t=jQuery("th.heading.fabrik_ordercell.fabrik_actions")[0],s=jQuery(t).find(".dropdown-menu").css("width","100%")[0],i=jQuery("<div></div>").on("mouseover",(function(){jQuery(".trashEl").css("display","block")})).on("mouseout",(function(){jQuery(".trashEl").css("display","none")})),n=jQuery('<li class="subMenuAdmin" style="padding: 0px 10px 5px 10px;"><button  id="button_'+a.options.idModalList+'" href="#'+a.options.idModalList+'" data-bs-toggle="modal" type="button">'+Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_EDIT_LIST")+"</button></li>"),o=jQuery('<li class="subMenuAdmin" style="border-top: 2px solid #eee; padding: 5px 10px 0px 10px;"><button href="#'+a.options.idModal+'" data-bs-toggle="modal" type="button">'+Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT")+"</button></li>");if(s)jQuery(s).parent().find(".fabrikImg").remove(),jQuery(s).parent().find(".dropdown-toggle").append(a.options.images.settings);else{var l=jQuery('<div class="dropdown fabrik_action"><button class="btn btn-default btn-mini dropdown-toggle dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'+a.options.images.settings+'</button><ul class="dropdown-menu dropdown-menu-end" style="width:100%"></ul></div>');jQuery(t).find("span").append(l),s=jQuery(t).find(".dropdown-menu")[0]}var r=jQuery(s);r.css("width","200px"),r.css("max-height","300px"),r.css("overflow-y","auto"),this.setCssAndEventsButtons(n,o),r.append(o),a.options.owner_id==a.options.user.id&&r.append(n),jQuery.each(e,(function(e,t){if("trash"==(e=e)){var s=jQuery('<li style="font-size: 12px; margin: 10px 0px 0px 8px;" class="subMenuAdmin"></li>').appendTo(i);jQuery(a.options.images.trash).appendTo(s);jQuery("<b>").text(Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_TRASH")).css({"padding-left":"5px",color:"#011627","vertical-align":"middle"}).appendTo(s)}jQuery.each(t,(function(t,s){var n=jQuery('<li value="'+t+'" style="font-size: 12px; '+("trash"==e?"display: none":"")+'" class="subMenuAdmin '+("trash"==e?"trashEl":"")+'"></li>').appendTo("trash"==e?i:r);if(s.enabled)var o=jQuery('<a style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;" href="#'+a.options.idModal+'" data-bs-toggle="modal"></a>').text(a.options.elementsNames[t]).css({cursor:"pointer","padding-left":"10px"}).appendTo(n);else o=jQuery("<b/>").text(a.options.elementsNames[t]).css({"padding-left":"10px",color:"#999"}).appendTo(n);o.on("click",(function(){a.setModalToEditElement(this)}))}))})),i.appendTo(r)},setCssAndEventsButtons:function(e,a){var t=this;a.on("click",(function(){t.options.valIdEl=0,jQuery("[name=history_type]").val(""),Els=jQuery(".fabrikinput"),Els.each((function(){switch(id=this.id,id){case"easyadmin_modal___use_filter0":case"easyadmin_modal___required0":case"easyadmin_modal___trash0":case"easyadmin_modal___show_in_list0":case"easyadmin_modal___show_down_thumb0":case"easyadmin_modal___ajax_upload0":case"easyadmin_modal___make_thumbs0":case"easyadmin_modal___multi_select0":case"easyadmin_modal___tags1":case"easyadmin_modal___multi_relation0":jQuery(this).prop("checked",!0);break;case"easyadmin_modal___type":case"easyadmin_modal___text_format":jQuery(this).val("text");break;case"easyadmin_modal___format_long_text":jQuery(this).val("0");break;default:jQuery(this).hasClass("input-list")||jQuery(this).val("")}})),jQuery("#jlow_fabrik_easyadmin_modal___listas-auto-complete").prop("disabled",!1),jQuery("#easyadmin_modal___type").trigger("change"),jQuery('input[name="easyadmin_modal___show_in_list"]').trigger("change",{button:"new-element"}),jQuery('label[for="easyadmin_modal___label_advanced_link"]').trigger("click"),jQuery("#easyadmin_modal___options_dropdown").trigger("chosen:updated"),jQuery("#easyadmin_modal___label").empty(),jQuery("#easyadmin_modal___father").empty(),jQuery("#easyadmin_modal___options_dropdown").parent().find("#easyadmin_modal___options_dropdown_chosen").css("width","95%")})),e.find("button").css({"min-height":"30px","font-size":"12px",width:"100%","border-radius":"12px",color:"#011627","background-color":"#e3ecf1"}),a.find("button").css({"min-height":"30px","font-size":"12px",width:"100%","border-radius":"12px",color:"#fff","background-color":"#003EA1","margin-bottom":"5px"})},sortColumns:function(){var e=this;if("undefined"!=typeof Sortable){const a=document.getElementById("list_"+jQuery("[name=listid]").val()+"_com_fabrik_"+jQuery("[name=listid]").val()),t=e.getColumnOrder(a);if(a){const s=a.querySelector("thead tr");Sortable.create(s,{animation:150,onEnd:function(s){var i=[];const n=s.oldIndex,o=s.newIndex;a.querySelectorAll("tbody tr").forEach((function(e){const a=Array.from(e.children),t=a.splice(n,1)[0];a.splice(o,0,t),e.innerHTML="",a.forEach((function(a){e.appendChild(a)}))}));const l=e.getColumnOrder(a);n!=o&&0!=o&&(showSpinner(),i.push({idOrder:l[o-1].order.replace("_order",""),idAtual:t[n].order.replace("_order","")}),e.saveEvent("columns",i[0]))}})}}},getColumnOrder:function(e){const a=e.querySelector("thead tr").querySelectorAll("th");return Array.from(a).map((e=>({name:e.classList[2],order:e.classList[3]})))}});return FbListEasyadmin}));