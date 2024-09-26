/**
 * Easy Admin
 *
 * @copyright: Copyright (C) 2024 Jlowcode Org - All rights reserved.
 * @license  : GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */
define(["jquery","fab/list-plugin"],(function(jQuery,FbListPlugin){var FbListEasyadmin=new Class({Extends:FbListPlugin,options:{inputSearch:"",labelList:"",fatherList:"",valIdEl:0},initialize:function(e){var a=this;this.options=e,jQuery(".modal-body").css("overflow-y","scroll"),Fabrik.addEvent("fabrik.list.submit.ajax.complete",(function(){a.setUpButtonsPainel()})),Fabrik.addEvent("fabrik.list.loaded",(function(){a.setElementAdminsList(),a.setElementType(),a.setElementApproveByVotes(),a.setElementShowInList(),a.setElementLabelAdvancedLink(),a.setUpButtonSave(),a.setUpButtonsPainel(),a.setUpElementList(),window.location.search.indexOf("manage")>0&&jQuery("#button_"+a.options.idModalList).trigger("click")}))},setUpButtonsPainel:function(){const e=jQuery("th.heading.fabrik_ordercell.fabrik_actions")[0];"inline"==this.options.actionMethod?jQuery(e).find(".btn-group")[0]:jQuery(e).find(".dropdown-menu")[0];this.setButtons(this.options.elements.published),this.setActionPanel(this.options.elements),jQuery(document).ready((function(){jQuery(document).on("mouseenter",".heading.fabrik_ordercell",(function(){jQuery(this).find(":button.elementAdminButton").show()})).on("mouseleave",".heading.fabrik_ordercell",(function(){jQuery(this).find(":button.elementAdminButton").hide()}))}))},createButton:function(e){var a=this,i=jQuery('<a href="#'+a.options.idModal+'" data-bs-toggle="modal">'+this.options.images.edit+"</a>"),s=jQuery('<li value="'+e+'" style="font-size: 12px; min-width: 30px;"></li>').css({cursor:"pointer"});return i.appendTo(s),i.on("click",(function(){a.setModalToEditElement(this)})),s},setElementDatabasejoin:function(){var e=this;jQuery("#modal-elements .select2-search__field").on("change",(function(){jQuery(this).remove(),e.options.inputSearch=jQuery(this)})),jQuery(document).on("click","#modal-elements .select2-selection__choice__remove",(function(){e.options.inputSearch.on("change",(function(){jQuery(this).remove(),e.options.inputSearch=jQuery(this)})),jQuery(this).parent().remove(),jQuery("#modal-elements .select2-selection__rendered li").append(e.options.inputSearch),e.setUpElementList()})),jQuery("#modal-elements .select2-selection").on("click",(function(){var a=jQuery(this);0==a.find("#modal-elements .select2-search__field").length&&0==a.find("#modal-elements .select2-selection__rendered li").length&&(a.find("#modal-elements .select2-search").append(e.options.inputSearch),e.setUpElementList())})),this.setUpElementList()},setUpElementList:function(){var e=this,a="#"+e.options.dbPrefix+"fabrik_easyadmin_modal___listas",i=jQuery(a+"-auto-complete"),s=jQuery(a);i.on("focusout",(function(){setTimeout((function(){e.searchElementList()}),500)})),0==jQuery(".refresh_label").length&&(elRefresh=jQuery(e.options.images.refresh),elRefresh.addClass("refresh_label"),elRefresh.css("margin-left","5px"),elRefresh.on("click",(function(){e.searchElementList()})),s.closest(".fabrikElementContainer").find(".form-label").after(elRefresh))},searchElementList:function(){var self=this,baseUri=this.options.baseUri,idEl="#"+self.options.dbPrefix+"fabrik_easyadmin_modal___listas",tid=jQuery(idEl).val();if(tid){var db_table_name=tid,url=baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=element&plugin=field&method=ajax_fields&showall=1&cid=1&t='"+db_table_name+"'";jQuery.ajax({url:url,method:"get",data:{showRaw:!1,k:2}}).done((function(r){var opts=eval(r),els=document.getElementsByClassName("child-element-list"),notShow=["id","created_by","created_date","created_ip","indexing_text","updated_by","updated_date"];jQuery(".child-element-list").each((function(e,a){jQuery(a).empty()})),Array.each(els,(function(e){x=0,opts.forEach((a=>{if(!notShow.includes(a.value)){e.id.indexOf("label")>0?val=self.options.labelList:val=self.options.fatherList;var i={value:a.value};a.value===val?(i.selected="selected",x=1):"name"==a.value&&e.id.indexOf("___label")>0&&0==x&&(i.selected="selected"),new Element("option",i).set("text",a.label).inject(e)}}))}))}))}},setElementAdminsList:function(){var e={},a=jQuery("#easyadmin_modal___viewLevel_list").val(),i=this.options.baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=list&plugin=easyadmin&method=getUsersAdmins";e.viewLevel=a,jQuery.ajax({url:i,method:"post",data:e}).done((function(e){var a=document.querySelectorAll('[name="easyadmin_modal___admins_list[]"]'),i=[];e=JSON.parse(e);for(var s=0;s<a[0].options.length;s++)i.push(a[0].options[s].value);for(const n of e)if(!i.includes(n.id)){var t=[];newOption=new Option(n.name,n.id,!1,!1),jQuery(a).append(newOption).trigger("change");for(s=0;s<a[0].options.length;s++)t.push(a[0].options[s].value);jQuery(a).val(t).trigger("change")}}))},setElementType:function(e=""){var a=jQuery("#easyadmin_modal___type"+e),i=""==e?"#modal-elements":".modalContent";a.on("change",(function(){type=jQuery(this).val(),jQuery(i+" .modal-element").each((function(e,a){elementClass=jQuery(this).prop("class"),elementClass.indexOf("element-")<0&&(elementClass.indexOf("type-"+type)<0?jQuery(this).parent().addClass("fabrikHide"):jQuery(this).parent().removeClass("fabrikHide"))}))})),type=a.val(),jQuery(i+" .modal-element").each((function(e,a){elementClass=jQuery(this).prop("class"),elementClass.indexOf("element-")<0&&(elementClass.indexOf("type-"+type)<0?jQuery(this).parent().addClass("fabrikHide"):jQuery(this).parent().removeClass("fabrikHide"))}))},setElementVisibilityList:function(){var e=this;jQuery("#easyadmin_modal___visibility_list").on("change",(function(a,i){e.showHideElements("visibility_list","list","dropdown")})),jQuery("#easyadmin_modal___visibility_list").trigger("change")},setElementApproveByVotes:function(){var e=this;jQuery('input[name="easyadmin_modal___approve_by_votes_list"]').on("change",(function(a,i){e.showHideElements("approve_by_votes_list","list","yesno")})),jQuery('input[name="easyadmin_modal___approve_by_votes_list"]').trigger("change")},setElementShowInList:function(){var e=this;jQuery('input[name="easyadmin_modal___show_in_list"]').on("change",(function(a,i){0==e.options.valIdEl&&void 0===i||e.showHideElements("show_in_list","element","yesno")}))},setElementLabelAdvancedLink:function(e=""){var a=this,i=jQuery('label[for="easyadmin_modal___label_advanced_link'+e+'"]');i.on("click",(function(i,s){id=jQuery(this).attr("for").split("___")[1],e=null!=s?s.sufix:"",e=id.indexOf("_wfl")>0?"_wfl":"",a.showHideElements(id,"element","label",s,e)})),i.hover((function(){$(this).css({cursor:"pointer"})}),(function(){$(this).css({cursor:"default"})}))},showHideElements:function(e,a,i,s="",t=""){var n=""==t?"#modal-elements":".modalContent";"list"==a&&(n="#modal-list"),jQuery(n+" .modal-"+a).each((function(){if(elementClass=jQuery(this).prop("class"),elementClass.indexOf(a+"-"+e)>0){switch(i){case"yesno":show=!!jQuery("#easyadmin_modal___"+e+"1").prop("checked")||"";break;case"dropdown":show=jQuery("#easyadmin_modal___"+e).val();break;case"label":"edit-element"==s.button?show=!1:show=jQuery(this).parent().prop("class").indexOf("fabrikHide")>0&&"link"==jQuery("#easyadmin_modal___type").val()}"visibility_list"==e&&(show="3"==show),show?jQuery(this).parent().removeClass("fabrikHide"):jQuery(this).parent().addClass("fabrikHide")}}))},setUpButtonSave:function(){elSaveElements=jQuery("#easyadmin_modal___submit_elements"),elSaveList=jQuery("#easyadmin_modal___submit_list"),elSaveElements.on("click",(()=>this.saveEvent("elements"))),elSaveList.on("click",(()=>this.saveEvent("list")))},saveEvent:function(e){if(self=this,valEls={},inputs=jQuery(".fabrikinput"),listId=jQuery("[name=listid]").val(),db_table_name=jQuery("[name=db_table_name]").val(),history_type=jQuery("[name=history_type]").val(),valEls.easyadmin_modal___mode=e,valEls.easyadmin_modal___listid=listId,valEls.easyadmin_modal___history_type=history_type,valEls.easyadmin_modal___valIdEl=self.options.valIdEl,valEls.jform={db_table_name:db_table_name},inputs.each((function(){switch(id=this.id,id){case"easyadmin_modal___name":case"easyadmin_modal___type":case"easyadmin_modal___text_format":case"easyadmin_modal___default_value":case"easyadmin_modal___label":case"easyadmin_modal___father":case"easyadmin_modal___format":case"easyadmin_modal___related_list":case"easyadmin_modal___thumb_link":case"easyadmin_modal___title_link":case"easyadmin_modal___description_link":case"easyadmin_modal___subject_link":case"easyadmin_modal___creator_link":case"easyadmin_modal___date_link":case"easyadmin_modal___format_link":case"easyadmin_modal___coverage_link":case"easyadmin_modal___publisher_link":case"easyadmin_modal___identifier_link":case"easyadmin_modal___language_link":case"easyadmin_modal___type_link":case"easyadmin_modal___contributor_link":case"easyadmin_modal___relation_link":case"easyadmin_modal___rights_link":case"easyadmin_modal___source_link":case"easyadmin_modal___access_rating":case"easyadmin_modal___name_list":case"easyadmin_modal___description_list":case"easyadmin_modal___ordering_list":case"easyadmin_modal___ordering_type_list":case"easyadmin_modal___collab_list":case"easyadmin_modal___width_list":case"easyadmin_modal___layout_mode":case"easyadmin_modal___visibility_list":case"easyadmin_modal___votes_to_approve_list":case"easyadmin_modal___votes_to_disapprove_list":case"easyadmin_modal___default_layout":case"easyadmin_modal___width_field":case"easyadmin_modal___ordering_elements":case"easyadmin_modal___viewLevel_list":case self.options.dbPrefix+"fabrik_easyadmin_modal___listas":valEls[id]=jQuery(this).val();break;case"easyadmin_modal___use_filter1":case"easyadmin_modal___required1":case"easyadmin_modal___trash1":case"easyadmin_modal___show_in_list1":case"easyadmin_modal___ajax_upload1":case"easyadmin_modal___make_thumbs1":case"easyadmin_modal___multi_select1":case"easyadmin_modal___multi_relation1":case"easyadmin_modal___trash_list1":case"easyadmin_modal___workflow_list1":case"easyadmin_modal___approve_by_votes_list1":id=id.replace("1",""),valEls[id]=!!jQuery(this).prop("checked")||"";break;case"easyadmin_modal___options_dropdown":valEls[id]=jQuery(this).val().join(",")}})),valEls.easyadmin_modal___admins_list=jQuery('[name="easyadmin_modal___admins_list[]"]').val(),"list"==e&&(valEls.order_by=[valEls.easyadmin_modal___ordering_list],valEls.order_dir=[valEls.easyadmin_modal___ordering_type_list]),""!=valEls.easyadmin_modal___name_list){if("link"==valEls.easyadmin_modal___type&&0==valEls.easyadmin_modal___valIdEl){var a="";optsOrderingEls=jQuery("#easyadmin_modal___ordering_elements option"),optsOrderingEls.each((function(e,i){a="ID"==i.label||"Criado por"==i.label?i.value:a})),valEls.easyadmin_modal___ordering_elements=a}var i=self.options.baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=list&plugin=easyadmin&method=SaveModal",s=!1;if(self.options.workflow&&"list"!=e){var t=self.options.baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=form&plugin=workflow&method=hasPermission";jQuery.ajax({url:t,method:"post",data:valEls}).done((function(e){if(s=JSON.parse(e),valEls.hasPermission=s?"1":"0",s)a=valEls;else{i=self.options.baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=list&plugin=easyadmin&method=validateElements";var a={formData:valEls,requestWorkflow:"1",listid:valEls.easyadmin_modal___listid}}jQuery.ajax({url:i,method:"post",data:a}).done((function(e){e=JSON.parse(e),urlLog=self.options.baseUri+"index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=form&plugin=workflow&method=createLog",e.error?alert(e.message):self.requestWorkflow(urlLog,valEls,s)}))}))}else self.requestWorkflow(i,valEls,!0)}else alert(Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ERROR_VALIDATE"))},requestWorkflow:function(e,a,i){jQuery.ajax({url:e,method:"post",data:a}).done((function(e){(e=JSON.parse(e)).error?alert(e.message):(msg=i?Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_SUCCESS"):e.message,alert(msg),window.location.replace(self.options.baseUri+window.location.pathname.replace("/","")))}))},setButtons:function(e){for(var a in e)if(e.hasOwnProperty(a)&&e[a].enabled){var i=jQuery("th."+e[a].fullname).children();i.css({display:"flex"}),i.addClass("tooltip2");var s=this.createButton(a);i.append(s),i.css({"min-width":"120px;"})}},setActionPanel:function(e){if("inline"==this.options.actionMethod)this.setActionPanelInline(e);else{if("dropdown"!=this.options.actionMethod)throw new Error(Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ACTION_METHOD_ERROR"));this.setActionPanelDropdown(e)}},setActionPanelInline:function(e){var a=this,i=jQuery('<a class="btn fabrik__rowlink btn-default"><span>'+this.options.images.settings+'</span><span class="hidden">'+Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADMIN")+"</span></a>"),s=jQuery("th.heading.fabrik_ordercell.fabrik_actions")[0],t=jQuery(s).find(".btn-group")[0],n=jQuery('<li><button id="button_'+a.options.idModalList+'" href="#'+a.options.idModalList+'" data-bs-toggle="modal" type="button">'+Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_EDIT_LIST")+"</button></li>"),o=jQuery('<li><button href="#'+a.options.idModal+'" data-bs-toggle="modal" type="button">'+Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT")+"</button></li>");if(!t){var l=jQuery('<div class="btn-group"></div>');jQuery(s).find("span").append(l),t=jQuery(s).find(".btn-group")[0]}var _=jQuery(t);this.setCssAndEventsButtons(n,o);var d=jQuery("<div></div>");d.css({"font-size":"12px",position:"absolute","z-index":100,"background-color":"#FFF",display:"none",right:"50%",padding:"10px",border:"2px solid #eee","border-radius":"4px","text-align":"left",width:"150px"}),i.on("click",(function(){"none"==jQuery(d).css("display")?jQuery(d).css({display:"block"}):jQuery(d).css({display:"none"})})),_.append(i),d.append(o),jQuery.each(e,(function(e,i){if("trash"==e){var s=jQuery('<li style="font-size: 12px; margin: 10px 0px 0px 8px;"></li>').appendTo(d);jQuery(a.options.images.trash).appendTo(s);jQuery("<b>").text(Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_TRASH")).css({"padding-left":"5px",color:"#011627","vertical-align":"middle"}).appendTo(s)}jQuery.each(i,(function(e,i){var s=jQuery('<li value="'+e+'" style="font-size: 12px"></li>').appendTo(d);if(i.enabled)var t=jQuery('<a href="#'+a.options.idModal+'" data-bs-toggle="modal"></a>').text(a.options.elementsNames[e]).css({cursor:"pointer","padding-left":"10px"}).appendTo(s);else t=jQuery("<b/>").text(a.options.elementsNames[e]).css({"padding-left":"10px",color:"#999"}).appendTo(s);t.on("click",(function(){a.setModalToEditElement(this)}))}))})),a.options.owner_id==a.options.user.id&&d.append(n),_.append(d)},setModalToEditElement:function(e){var a=this,i=jQuery(e).parent().prop("value"),s=a.options.allElements[i];a.options.valIdEl=i,s.enabled&&(jQuery("#easyadmin_modal___history_type").val(s.type),jQuery.each(s,(function(i,s){if(e=jQuery("#easyadmin_modal___"+i),el2=jQuery("#"+a.options.dbPrefix+"fabrik_easyadmin_modal___"+i),e.length>0||el2.length>0)if(0==(e=0==e.length?el2:e).find(".switcher").length)switch(e.val(s),i){case"label":a.options.labelList=s;break;case"father":a.options.fatherList=s;break;case"listas":jQuery("#"+a.options.dbPrefix+"fabrik_easyadmin_modal___listas").val(s),jQuery("#"+a.options.dbPrefix+"fabrik_easyadmin_modal___listas-auto-complete").val(s);break;case"width_field":case"ordering_elements":a.showHideElements("show_in_list","element","yesno");break;case"options_dropdown":vals=s.split(","),vals.forEach((function(e){jQuery("#easyadmin_modal___"+i).append(jQuery("<option>",{value:e.trim(),text:e.trim(),selected:!0}))}))}else p=s?1:0,elSw=e.find("#easyadmin_modal___"+i+p),elSw.prop("checked",!0)}))),("treeview"==jQuery("#easyadmin_modal___type").val()||"autocomplete"==jQuery("#easyadmin_modal___type").val())&&jQuery("#jlow_fabrik_easyadmin_modal___listas-auto-complete").prop("disabled","disabled"),jQuery("#easyadmin_modal___type").trigger("change"),jQuery("#easyadmin_modal___options_dropdown").trigger("chosen:updated"),jQuery('label[for="easyadmin_modal___label_advanced_link"]').trigger("click"),jQuery("#"+a.options.dbPrefix+"fabrik_easyadmin_modal___listas-auto-complete").trigger("focusout"),jQuery("#easyadmin_modal___options_dropdown").parent().find("#easyadmin_modal___options_dropdown_chosen").css("width","95%")},setActionPanelDropdown:function(e){var a=this,i=jQuery("th.heading.fabrik_ordercell.fabrik_actions")[0],s=jQuery(i).find(".dropdown-menu").css("width","100%")[0],t=jQuery('<li class="subMenuAdmin" style="border-bottom: 2px solid #eee; padding: 0px 10px 5px 10px;"><button  id="button_'+a.options.idModalList+'" href="#'+a.options.idModalList+'" data-bs-toggle="modal" type="button">'+Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_EDIT_LIST")+"</button></li>"),n=jQuery('<li class="subMenuAdmin" style="border-top: 2px solid #eee; padding: 5px 10px 0px 10px;"><button href="#'+a.options.idModal+'" data-bs-toggle="modal" type="button">'+Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT")+"</button></li>");if(s)jQuery(s).parent().find(".fabrikImg").remove(),jQuery(s).parent().find(".dropdown-toggle").append(a.options.images.settings);else{var o=jQuery('<div class="dropdown fabrik_action"><button class="btn btn-default btn-mini dropdown-toggle dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'+a.options.images.settings+'</button><ul class="dropdown-menu dropdown-menu-end" style="width:100%"></ul></div>');jQuery(i).find("span").append(o),s=jQuery(i).find(".dropdown-menu")[0]}var l=jQuery(s);this.setCssAndEventsButtons(t,n),l.append(n),jQuery.each(e,(function(e,i){if("trash"==e){var s=jQuery('<li style="font-size: 12px; margin: 10px 0px 0px 8px;" class="subMenuAdmin"></li>').appendTo(l);jQuery(a.options.images.trash).appendTo(s);jQuery("<b>").text(Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_TRASH")).css({"padding-left":"5px",color:"#011627","vertical-align":"middle"}).appendTo(s)}jQuery.each(i,(function(e,i){var s=jQuery('<li value="'+e+'" style="font-size: 12px;" class="subMenuAdmin"></li>').appendTo(l);if(i.enabled)var t=jQuery('<a href="#'+a.options.idModal+'" data-bs-toggle="modal"></a>').text(a.options.elementsNames[e]).css({cursor:"pointer","padding-left":"10px"}).appendTo(s);else t=jQuery("<b/>").text(a.options.elementsNames[e]).css({"padding-left":"10px",color:"#999"}).appendTo(s);t.on("click",(function(){a.setModalToEditElement(this)}))}))})),a.options.owner_id==a.options.user.id&&l.append(t)},setCssAndEventsButtons:function(e,a){var i=this;a.on("click",(function(){i.options.valIdEl=0,Els=jQuery(".fabrikinput"),Els.each((function(){switch(id=this.id,id){case"easyadmin_modal___name":case"easyadmin_modal___default_value":case"easyadmin_modal___options_dropdown":case"easyadmin_modal___label":case"easyadmin_modal___father":case"easyadmin_modal___format":case"easyadmin_modal___related_list":case"easyadmin_modal___thumb_link":case"easyadmin_modal___title_link":case"easyadmin_modal___description_link":case"easyadmin_modal___subject_link":case"easyadmin_modal___creator_link":case"easyadmin_modal___date_link":case"easyadmin_modal___format_link":case"easyadmin_modal___coverage_link":case"easyadmin_modal___publisher_link":case"easyadmin_modal___identifier_link":case"easyadmin_modal___language_link":case"easyadmin_modal___type_link":case"easyadmin_modal___contributor_link":case"easyadmin_modal___relation_link":case"easyadmin_modal___rights_link":case"easyadmin_modal___source_link":case"easyadmin_modal___access_rating":case"easyadmin_modal___width_field":case"easyadmin_modal___ordering_elements":case i.options.dbPrefix+"fabrik_easyadmin_modal___listas":case i.options.dbPrefix+"fabrik_easyadmin_modal___listas-auto-complete":jQuery(this).val("");break;case"easyadmin_modal___use_filter0":case"easyadmin_modal___required0":case"easyadmin_modal___trash0":case"easyadmin_modal___show_in_list0":case"easyadmin_modal___ajax_upload0":case"easyadmin_modal___make_thumbs0":case"easyadmin_modal___multi_select0":case"easyadmin_modal___multi_relation0":jQuery(this).prop("checked",!0);break;case"easyadmin_modal___type":case"easyadmin_modal___text_format":jQuery(this).val("text")}})),jQuery("#jlow_fabrik_easyadmin_modal___listas-auto-complete").prop("disabled",!1),jQuery("#easyadmin_modal___type").trigger("change"),jQuery('input[name="easyadmin_modal___show_in_list"]').trigger("change",{button:"new-element"}),jQuery('label[for="easyadmin_modal___label_advanced_link"]').trigger("click"),jQuery("#easyadmin_modal___options_dropdown").trigger("chosen:updated"),jQuery("#easyadmin_modal___label").empty(),jQuery("#easyadmin_modal___father").empty(),jQuery("#easyadmin_modal___options_dropdown").parent().find("#easyadmin_modal___options_dropdown_chosen").css("width","95%")})),e.find("button").css({"min-height":"30px","font-size":"12px",width:"100%","border-radius":"12px",color:"#000","background-color":"#e3ecf1","margin-top":"20px"}),a.find("button").css({"min-height":"30px","font-size":"12px",width:"100%","border-radius":"12px",color:"#fff","background-color":"#003EA1","margin-bottom":"5px"})}});return FbListEasyadmin}));