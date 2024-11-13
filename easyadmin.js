/**
 * Easy Admin
 *
 * @copyright: Copyright (C) 2024 Jlowcode Org - All rights reserved.
 * @license  : GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */
define(['jquery', 'fab/list-plugin'], function (jQuery, FbListPlugin) {
	var FbListEasyadmin = new Class({
		Extends: FbListPlugin,

		options: {
			inputSearch: '',
			labelList: '',
			fatherList: '',
			valIdEl: 0
		},

		/**
		 * Init function
		 * 
		 */
		initialize: function (options) {
            // Init options
			var self = this;
			this.options = options;

			jQuery(".modal-body").css("overflow-y", "scroll");
			jQuery(".table").css("table-layout", "fixed");
			jQuery('#easyadmin_modal___description_list').addClass('fabrikinput');

			Fabrik.addEvent('fabrik.list.submit.ajax.complete', function () {
				self.setUpButtonsPainel();
			});

			Fabrik.addEvent('fabrik.list.loaded', function () {
				self.setElementAdminsList();
				self.setElementType();
				//self.setElementVisibilityList();
				self.setElementApproveByVotes();
				self.setElementShowInList();
				self.setElementLabelAdvancedLink();
				self.setUpButtonSave();
				self.setUpButtonsPainel();
				self.setUpElementList();
                self.sortColumns();

				if(window.location.search.indexOf('manage') > 0) {
					jQuery("#button_" + self.options.idModalList).trigger('click');
				}

				//When 'manage' variable present we need remove
				jQuery('#modal-list .btn-close').on('click', function() {
					if(window.location.search.indexOf('manage') > 0) {
						window.location.replace(self.options.baseUri + window.location.pathname.replace('/', ''));
					}
				});
			});
		},

		/**
		 * Function to show the buttons from painel
		 * 
		 */
		setUpButtonsPainel: function () {
			const heading = jQuery('th.heading.fabrik_ordercell.fabrik_actions')[0];
			const btnGroup = this.options.actionMethod == 'inline' ? jQuery(heading).find('.btn-group')[0] : jQuery(heading).find('.dropdown-menu')[0];			

			this.setButtons(this.options.elements.published);
			this.setActionPanel(this.options.elements);
			jQuery(document).ready(function () {
				jQuery(document).on('mouseenter', '.heading.fabrik_ordercell', function () {
					jQuery(this).find(":button.elementAdminButton").show();
				}).on('mouseleave', '.heading.fabrik_ordercell', function () {
					jQuery(this).find(":button.elementAdminButton").hide();
				});
			});
		},

		/**
		 * Create a button of an element edit link
		 * 
		 */
		createButton: function(index) {
			var self = this;
			var sub = jQuery('<a href="#' + self.options.idModal + '" data-bs-toggle="modal">' + this.options.images.edit + '</a>');
			var button = jQuery('<li value="' + index + '" style="font-size: 12px; min-width: 30px;"></li>')
			.css({
				'cursor': 'pointer',
			});
			sub.appendTo(button);

			sub.on('click', function() {
				self.setModalToEditElement(this);
			});

			return button;
		},

		/**
		 * Function to make the multiselect databasejoin single and to set up the list element
		 * 
	 	 * @deprecated  	since 4.2 		This method was remove because the list element changed to autocomplete
		 */
		setElementDatabasejoin: function() {
			var self = this;

			var multiSelectElement = jQuery('#modal-elements .select2-search__field');
			multiSelectElement.on('change', function() {
				jQuery(this).remove();
				self.options.inputSearch = jQuery(this);
			});

			jQuery(document).on('click', '#modal-elements .select2-selection__choice__remove', function() {
				self.options.inputSearch.on('change', function() {
					jQuery(this).remove();
					self.options.inputSearch = jQuery(this);
				});
				jQuery(this).parent().remove();
				jQuery('#modal-elements .select2-selection__rendered li').append(self.options.inputSearch);
				self.setUpElementList();
			});

			jQuery('#modal-elements .select2-selection').on('click', function() {
				var spanSelect2 = jQuery(this);
				if(spanSelect2.find('#modal-elements .select2-search__field').length == 0 && spanSelect2.find('#modal-elements .select2-selection__rendered li').length == 0) {
					spanSelect2.find('#modal-elements .select2-search').append(self.options.inputSearch);
					self.setUpElementList();
				}
			});

			this.setUpElementList();
		},

		/**
		 * Function to set up the element field.
		 * Adding the event to render the options for label element
		 * 
		 */
		setUpElementList: function() {
			var self = this;
			var idEl = '#' + self.options.dbPrefix + 'fabrik_easyadmin_modal___listas';
			var searchElementList = jQuery(idEl + '-auto-complete');
			var elLabel = jQuery(idEl);

			searchElementList.on('focusout', function() {
				setTimeout(function() {
					self.searchElementList();
				}, 500);
			});

			if(jQuery('.refresh_label').length == 0) {
				elRefresh = jQuery(self.options.images.refresh);
				elRefresh.addClass('refresh_label');
				elRefresh.css('margin-left', '5px');
				elRefresh.on('click', function(){self.searchElementList()});
				elLabel.closest('.fabrikElementContainer').find('.form-label').after(elRefresh);
			}
		},

		/**
		 * Function that search the value of the list and make the options
		 * 
		 */
		searchElementList: function() {
			var self = this;
			var baseUri = this.options.baseUri;
			var idEl = '#' + self.options.dbPrefix + 'fabrik_easyadmin_modal___listas';

			var tid = jQuery(idEl).val();
			if (!tid) {
				return;
			}

			var db_table_name = tid;
			var url = baseUri + "index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=element&plugin=field&method=ajax_fields&showall=1&cid=1&t='" + db_table_name + "'";
			jQuery.ajax({
				url     : url,
				method	: 'get',
				data: {
					'showRaw': false,
					'k': 2
				},
			}).done(function (r) {
				var opts = eval(r);
				var els = document.getElementsByClassName('child-element-list');
				var notShow = ["id", "created_by", "created_date", "created_ip", "indexing_text", "updated_by", "updated_date"];

				jQuery('.child-element-list').each(function(index, element) {
					jQuery(element).empty();
				});

				Array.each(els, function (el) {
					x = 0;
					opts.forEach(opt => {
						if(!notShow.includes(opt.value)) {
							el.id.indexOf("label") > 0 ? val = self.options.labelList : val = self.options.fatherList;
							var o = {'value': opt.value};

							if (opt.value === val) {
								o.selected = 'selected';
								x = 1;
							} else if(opt.value == "name" && el.id.indexOf("___label") > 0 && x == 0) {
								o.selected = "selected";
							}
							new Element('option', o).set('text', opt.label).inject(el);
						}
					});
				});
			});
		},

		/**
		 * Function that set the list admins element to take the admin users
		 * 
		 */
		setElementAdminsList: function () {
			var data = {};
			var viewLevel = jQuery('#easyadmin_modal___viewLevel_list').val();
			var url = this.options.baseUri + "index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=list&plugin=easyadmin&method=getUsersAdmins";

			data['viewLevel'] = viewLevel;

			jQuery.ajax({
				url     : url,
				method	: 'post',
				data: data,
			}).done(function (r) {
				var select2 = document.querySelectorAll('[name="easyadmin_modal___admins_list[]"]');
				var valuesCheck = [];
				r = JSON.parse(r);

				for (var i = 0; i < select2[0].options.length; i++) {
					valuesCheck.push(select2[0].options[i].value);
				}
				
				for (const key of r) {
					if(!valuesCheck.includes(key['id'])) {
						var values = [];
						newOption = new Option(key['name'], key['id'], false, false);
		
						jQuery(select2).append(newOption).trigger('change');
						for (var i = 0; i < select2[0].options.length; i++) {
							values.push(select2[0].options[i].value);
						}
						jQuery(select2).val(values).trigger('change');
					}	
				}
			});
		},

		/**
		 * Function that set the events to type element
		 * 
		 */
		setElementType: function(sufix='') {
			var elType = jQuery('#easyadmin_modal___type' + sufix);
			var modal = sufix == '' ? '#modal-elements' : '.modalContent';

			elType.on('change', function() {
				type = jQuery(this).val();

				jQuery(modal + ' .modal-element').each(function(index, element) {
					elementClass = jQuery(this).prop('class');

					if(elementClass.indexOf('element-') < 0) {
						if(elementClass.indexOf('type-' + type) < 0) {
							jQuery(this).parent().addClass('fabrikHide');
						} else {
							jQuery(this).parent().removeClass('fabrikHide');
						}
					}
				});
			});

			type = elType.val();
			jQuery(modal + ' .modal-element').each(function(index, element) {
				elementClass = jQuery(this).prop('class');

				if(elementClass.indexOf('element-') < 0) {
					if(elementClass.indexOf('type-' + type) < 0) {
						jQuery(this).parent().addClass('fabrikHide');
					} else {
						jQuery(this).parent().removeClass('fabrikHide');
					}
				}
			});
		},

		/**
		 * Function that set the events to visibility list element
		 * Function don't needed
		 * 
		 */
		setElementVisibilityList: function() {
			var self = this;
			var elVisibilityList = jQuery('#easyadmin_modal___visibility_list');

			elVisibilityList.on('change', function(e, params) {
				self.showHideElements('visibility_list', 'list', 'dropdown');
			});

			jQuery('#easyadmin_modal___visibility_list').trigger('change');
		},

		/**
		 * Function that set the events to approve by votes list element
		 * 
		 */
		setElementApproveByVotes: function() {
			var self = this;
			var elApproveByVotes = jQuery('input[name="easyadmin_modal___approve_by_votes_list"]');

			elApproveByVotes.on('change', function(e, params) {
				self.showHideElements('approve_by_votes_list', 'list', 'yesno');
			});

			jQuery('input[name="easyadmin_modal___approve_by_votes_list"]').trigger('change');
		},

		/**
		 * Function that set the events to show in list element
		 * 
		 */
		setElementShowInList: function() {
			var self = this;
			var elShowInList = jQuery('input[name="easyadmin_modal___show_in_list"]');

			elShowInList.on('change', function(e, params) {
				if(self.options.valIdEl == 0 && params === undefined) {
					return;
				}

				self.showHideElements('show_in_list', 'element', 'yesno');
			});
		},

		/**
		 * Function that set the events to show in list element
		 * 
		 */
		setElementLabelAdvancedLink: function(sufix = '') {
			var self = this;
			var elLabelAdvancedLink = jQuery('label[for="easyadmin_modal___label_advanced_link' + sufix +'"]');

			elLabelAdvancedLink.on('click', function(e, params) {
				id = jQuery(this).attr('for').split('___')[1];
				sufix = params != null ? params.sufix : '';
				sufix = id.indexOf('_wfl') > 0 ? '_wfl' : '';
				self.showHideElements(id, 'element', 'label', params, sufix);
			});

			elLabelAdvancedLink.hover(
				function() {
					$(this).css({
					  'cursor': 'pointer'
					});
				},
				function() {
					$(this).css({
					  'cursor': 'default'
					});
				}
			)
		},

		/**
		 * Function that show elements when another element change
		 * 
		 */
		showHideElements: function(name, modal, type, params='', sufix = '') {
			var modalRef = sufix == '' ? '#modal-elements' : '.modalContent';
			if(modal == 'list') {
				modalRef = '#modal-list';
			}

			jQuery(modalRef + ' .modal-' + modal).each(function() {
				elementClass = jQuery(this).prop('class');
				if(elementClass.indexOf(modal + '-' + name) > 0) {
					switch (type) {
						case 'yesno':
							show = jQuery('#easyadmin_modal___' + name + '1').prop('checked') ? true : '';							
							break;

						case 'dropdown':
							show = jQuery('#easyadmin_modal___' + name).val();
							break;

						case 'label':
							if(params.button == 'edit-element') {
								show = false;
							} else {
								show = jQuery(this).parent().prop('class').indexOf('fabrikHide') > 0 && jQuery('#easyadmin_modal___type').val() == 'link' ? true : false;
							}
							break;
					}

					if(name == 'visibility_list') {
						show = show == '3' ? true : false; 
					}

					if(!show) {
						jQuery(this).parent().addClass('fabrikHide');
					} else {
						jQuery(this).parent().removeClass('fabrikHide');
					}
				}
			});
		},

		/**
		 * Function that set the ajax event to save button
		 * 
		 */
		setUpButtonSave: function() {
			var self = this;
			elSaveElements = jQuery('#easyadmin_modal___submit_elements');
			elSaveList = jQuery("#easyadmin_modal___submit_list");

			elSaveElements.on('click', () => this.saveEvent('elements'));
			elSaveList.on('click', () => this.saveEvent('list'));
		},

		/**
		 * Function that call the save method by ajax
		 * 
		 */
		saveEvent: function(mode, columns = '') {
			self = this;
			valEls = {};
			inputs = jQuery('.fabrikinput');

			listId = jQuery('[name=listid]').val();
			db_table_name = jQuery('[name=db_table_name]').val();
			history_type = jQuery('[name=history_type]').val();

			valEls['easyadmin_modal___mode'] = mode;
			valEls['easyadmin_modal___listid'] = listId;
			valEls['easyadmin_modal___history_type'] = history_type;
			valEls['jform'] = {'db_table_name': db_table_name};
            valEls['easyadmin_modal___valIdEl'] = mode == 'columns' ? columns.idAtual : self.options.valIdEl;

			inputs.each(function() {
				id = this.id;
				switch (id) {					
					case 'easyadmin_modal___use_filter1':
					case 'easyadmin_modal___required1':
					case 'easyadmin_modal___trash1':
					case 'easyadmin_modal___show_in_list1':
					case 'easyadmin_modal___show_down_thumb1':
					case 'easyadmin_modal___ajax_upload1':
					case 'easyadmin_modal___make_thumbs1':
					case 'easyadmin_modal___multi_select1':
					case 'easyadmin_modal___tags1':
					case 'easyadmin_modal___multi_relation1':
					case 'easyadmin_modal___trash_list1':
					case 'easyadmin_modal___workflow_list1':
					case 'easyadmin_modal___approve_by_votes_list1':
					case 'easyadmin_modal___comparison_list1':
                    case 'easyadmin_modal___white_space1':
						id = id.replace('1', '');
						valEls[id] = jQuery(this).prop('checked') ? true : '';
						break;

					case 'easyadmin_modal___options_dropdown':
						valEls[id] = jQuery(this).val().join(',');
						break;

					case 'easyadmin_modal___description_list':
						valEls[id] = tinyMCE.activeEditor.getContent();
						break;

					default:
						valEls[id] = jQuery(this).val();
						break;
				}
			});

			// Databasejoins values
			valEls['easyadmin_modal___admins_list'] = jQuery('[name="easyadmin_modal___admins_list[]"]').val();

			if(mode == 'list') {
				valEls['order_by'] = [valEls['easyadmin_modal___ordering_list']];
				valEls['order_dir'] = [valEls['easyadmin_modal___ordering_type_list']];
			}

			// We need save the link element and ordering it to the first position of the form
			if(valEls['easyadmin_modal___type'] == 'link' && valEls['easyadmin_modal___valIdEl'] == 0) {
				var valOrder = '';
				optsOrderingEls = jQuery('#easyadmin_modal___ordering_elements option');
				optsOrderingEls.each(function(i, el) {
					valOrder = el.label == 'ID' || el.label == 'Criado por' ? el.value : valOrder;
				});
				valEls['easyadmin_modal___ordering_elements'] = valOrder;
			}

            mode == 'columns' ? valEls['easyadmin_modal___ordering_elements'] = columns.idOrder : '';
			tinyMCE.activeEditor.save();

			var url = self.options.baseUri + "index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=list&plugin=easyadmin&method=SaveModal";
			var hasPermission = false;
            if(self.options.workflow && mode == 'elements') {
				var urlGetPermission = self.options.baseUri + "index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=form&plugin=workflow&method=hasPermission";
				jQuery.ajax({
					url     : urlGetPermission,
					method	: 'post',
					data: valEls,
				}).done(function (r) {
					hasPermission = JSON.parse(r);
					valEls['hasPermission'] = hasPermission ? '1' : '0';
					if(!hasPermission) {
						url = self.options.baseUri + "index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=list&plugin=easyadmin&method=validateElements";
						var dataSend = {
							'formData': valEls,
							'requestWorkflow': '1',
							'listid': valEls['easyadmin_modal___listid']
						}
					} else {
						var dataSend = valEls;
					}

					jQuery.ajax({
						url     : url,
						method	: 'post',
						data	: dataSend
					}).done(function (r) {
						r = JSON.parse(r);
						urlLog = self.options.baseUri + "index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=form&plugin=workflow&method=createLog";

						if(!r['error']) {
							self.requestWorkflow(urlLog, valEls, hasPermission);
						} else {
							alert(r['message']);
						}
					});
				});
			} else {
				self.requestWorkflow(url, valEls, true);
			}
		},

		/**
		 * This function send requests when workflow is enabled
		 * 
		 */
		requestWorkflow: function(url, data, typeMsg) {
			jQuery.ajax({
				url     : url,
				method	: 'post',
				data	: data,
			}).done(function (r) {
				r = JSON.parse(r);

				if(!r['error']) {
					msg = typeMsg ? Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_SUCCESS") : r['message'];
					alert(msg);

					window.location.replace(self.options.baseUri + window.location.pathname.replace('/', ''));
				} else {
					alert(r['message']);
				}
			});
		},

		/**
		 * Set buttons to edit the elements
		 * 
		 */
		setButtons: function(links)  {
			for (var key in links) {
				if(links.hasOwnProperty(key) && links[key].enabled) {
					var element = jQuery('th.' + links[key].fullname).children();
					element.css({'display': 'flex'});
					element.addClass("tooltip2");
					var button = this.createButton(key);
					element.append(button);
					element.css({
						"min-width": "120px;"
					});
					
				}
			}
		},

		/**
		 * Function that redirect to correctly function to build the painel
		 * 
		 */
		setActionPanel: function (elements) {
			if(this.options.actionMethod == 'inline') {
				this.setActionPanelInline(elements);
			} else if (this.options.actionMethod == 'dropdown') {
				this.setActionPanelDropdown(elements);
			} else {
				throw new Error(Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ACTION_METHOD_ERROR"));
			}
		},

		/**
		 * Function that build the painel to inline option
		 * 
		 */
		setActionPanelInline: function (allElements) {
			var self = this;

			var button = jQuery('<a class="btn fabrik__rowlink btn-default"><span>' + this.options.images.settings + '</span><span class="hidden">' + Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADMIN") + '</span></a>');
			var heading = jQuery('th.heading.fabrik_ordercell.fabrik_actions')[0];
			var btnGroup = jQuery(heading).find('.btn-group')[0];
			var divTrash = jQuery('<div></div>').on('mouseover', function() {
				jQuery('.trashEl').css('display', 'block');
			}).on('mouseout', function() {
				jQuery('.trashEl').css('display', 'none');
			});

			var editListButton = jQuery('<li><button id="button_' + self.options.idModalList + '" href="#' + self.options.idModalList + '" data-bs-toggle="modal" type="button">' + Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_EDIT_LIST") + '</button></li>');
			var addElementButton = jQuery('<li><button href="#' + self.options.idModal + '" data-bs-toggle="modal" type="button">' + Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT") + '</button></li>');
			
			if(!btnGroup) {
				var newBtnGroup = jQuery('<div class="btn-group"></div>');
				jQuery(heading).find("span").append(newBtnGroup);
				btnGroup = jQuery(heading).find('.btn-group')[0];
			}
			var JBtnGroup = jQuery(btnGroup);
			JBtnGroup.css('width', '200px');
			JBtnGroup.css('height', '500px');
			JBtnGroup.css('overflow-y', 'auto');
			JBtnGroup.css('overflow-x', 'auto');

			this.setCssAndEventsButtons(editListButton, addElementButton);

			var div = jQuery("<div></div>");
			div.css({
				'font-size': '12px',
				'position': 'absolute',
				'z-index': 100,
				'background-color': "#FFF",
				'display': 'none',
				'right': '50%',
				'padding': '10px',
				'border': '2px solid #eee',
				'border-radius': '4px',
				'text-align': 'left',
				'width': '150px',
			});
			
			button.on('click', function () {
				if(jQuery(div).css('display') == 'none') {
					jQuery(div).css({'display': 'block' });
				} else {
					jQuery(div).css({'display': 'none' });
				}
			});

			JBtnGroup.append(button);
			div.append(addElementButton);
			if(self.options.owner_id == self.options.user.id) {
				div.append(editListButton);
			}

			jQuery.each(allElements, function(state, elements) {
				if(state == 'trash') {
					var liSubTitle = jQuery('<li style="font-size: 12px; margin: 10px 0px 0px 8px;"></li>')
						.appendTo(divTrash);
						jQuery(self.options.images.trash).appendTo(liSubTitle);
					var sub = jQuery('<b>')
						.text(Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_TRASH"))
						.css({
							'padding-left': '5px',
							'color': '#011627',
							'vertical-align': 'middle'
						})
						.appendTo(liSubTitle);
				}
			
				jQuery.each(elements, function(index, value) {
					var display = state == 'trash' ? 'display: none' : '';
					var classTrash = state == 'trash' ? 'trashEl' : '';
					var li = jQuery('<li value="' + index + '" style="padding-left:10px; font-size: 12px; ' + display + '" class="' + classTrash +'"></li>')
						.appendTo(state == 'trash' ? divTrash : div);
					if(value.enabled) {
						var sub = jQuery('<a style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;" href="#' + self.options.idModal + '" data-bs-toggle="modal"></a>')
						.text(self.options.elementsNames[index])
						.css({
							'cursor': 'pointer',
							'padding-left': '10px'
						})
						.appendTo(li);
					} else {
						var sub = jQuery('<b/>')
						.text(self.options.elementsNames[index])
						.css({
							'padding-left': '10px',
							'color': '#999'
						})
						.appendTo(li);
					}

					sub.on('click', function() {
						self.setModalToEditElement(this);
					});
				});
			});

			divTrash.appendTo(div);

			JBtnGroup.append(div);
		},

		/**
		 * Function that set the data when click on the elements in painel
		 * 
		 */
		setModalToEditElement: function (el) {
			var self = this;
			var li = jQuery(el).parent();
			var idEl = li.prop('value');
			var options = self.options.allElements[idEl];

			self.options.valIdEl = idEl;

			if(options.enabled) {
				jQuery('#easyadmin_modal___history_type').val(options.type);

				jQuery.each(options, function(index, value) {
					el = jQuery('#easyadmin_modal___' + index);
					el2 = jQuery('#' + self.options.dbPrefix + 'fabrik_easyadmin_modal___' + index);
					if(el.length > 0 || el2.length > 0) {
						el = el.length == 0 ? el2 : el; 
						if(el.find('.switcher').length == 0) {
							el.val(value);

							switch (index) {
								case 'label':
									self.options.labelList = value;
									break;

								case 'father':
									self.options.fatherList = value;
									break;

								case 'listas':
									jQuery('#' + self.options.dbPrefix + 'fabrik_easyadmin_modal___listas').val(value);
									jQuery('#' + self.options.dbPrefix + 'fabrik_easyadmin_modal___listas-auto-complete').val(value);
									break;

								case 'width_field':
                                case 'white_space':
								case 'ordering_elements':
									self.showHideElements('show_in_list', 'element', 'yesno');
									break;

								case 'options_dropdown':
									vals = value.split(',');
									vals.forEach(function(option) {
										jQuery('#easyadmin_modal___' + index).append(
											jQuery('<option>', {
												value: option.trim(),
												text: option.trim(),
												selected: true
											})
										);
									});
									break;
							}
						} else {
							value ? p = 1 : p = 0;
							elSw = el.find('#easyadmin_modal___' + index + p);
							elSw.prop('checked', true);
						}
					}
				})
			}

			jQuery('#easyadmin_modal___type').val() == 'treeview' || jQuery('#easyadmin_modal___type').val() == 'autocomplete' ? jQuery('#jlow_fabrik_easyadmin_modal___listas-auto-complete').prop('disabled', 'disabled') : null;

			jQuery('#easyadmin_modal___type').trigger('change');
			jQuery('#easyadmin_modal___options_dropdown').trigger("chosen:updated");
			jQuery('label[for="easyadmin_modal___label_advanced_link"]').trigger('click');
			jQuery('#' + self.options.dbPrefix + 'fabrik_easyadmin_modal___listas-auto-complete').trigger('focusout');
			jQuery('#easyadmin_modal___options_dropdown').parent().find('#easyadmin_modal___options_dropdown_chosen').css('width', '95%');
		},

		/**
		 * Function that build the painel to dropdown option
		 * 
		 */
		setActionPanelDropdown: function (allElements) {
			var self = this;

			var heading = jQuery('th.heading.fabrik_ordercell.fabrik_actions')[0];
			var btnGroup = jQuery(heading).find('.dropdown-menu').css('width', '100%')[0];
			var divTrash = jQuery('<div></div>').on('mouseover', function() {
				jQuery('.trashEl').css('display', 'block');
			}).on('mouseout', function() {
				jQuery('.trashEl').css('display', 'none');
			});

			var editListButton = jQuery('<li class="subMenuAdmin" style="padding: 0px 10px 5px 10px;"><button  id="button_' + self.options.idModalList + '" href="#' + self.options.idModalList + '" data-bs-toggle="modal" type="button">' + Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_EDIT_LIST") + '</button></li>');
			var addElementButton = jQuery('<li class="subMenuAdmin" style="border-top: 2px solid #eee; padding: 5px 10px 0px 10px;"><button href="#' + self.options.idModal + '" data-bs-toggle="modal" type="button">' + Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT") + '</button></li>');

			if(!btnGroup) {
				var newBtnGroup = jQuery('<div class="dropdown fabrik_action"><button class="btn btn-default btn-mini dropdown-toggle dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' + self.options.images.settings + '</button><ul class="dropdown-menu dropdown-menu-end" style="width:100%"></ul></div>');
				jQuery(heading).find("span").append(newBtnGroup);
				btnGroup = jQuery(heading).find('.dropdown-menu')[0];
			} else {
				jQuery(btnGroup).parent().find('.fabrikImg').remove();
				jQuery(btnGroup).parent().find('.dropdown-toggle').append(self.options.images.settings);
			}

			var JBtnGroup = jQuery(btnGroup);
			JBtnGroup.css('width', '200px');
			JBtnGroup.css('max-height', '500px');
			JBtnGroup.css('overflow-y', 'auto');

			this.setCssAndEventsButtons(editListButton, addElementButton);
            JBtnGroup.append(addElementButton);
			if(self.options.owner_id == self.options.user.id) {
				JBtnGroup.append(editListButton);
			}

			jQuery.each(allElements, function(state, elements) {
				var state = state;
				if(state == 'trash') {
					var liSubTitle = jQuery('<li style="font-size: 12px; margin: 10px 0px 0px 8px;" class="subMenuAdmin"></li>')
						.appendTo(divTrash);
					jQuery(self.options.images.trash).appendTo(liSubTitle);
					var sub = jQuery('<b>')
						.text(Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_TRASH"))
						.css({
							'padding-left': '5px',
							'color': '#011627',
							'vertical-align': 'middle'
						})
						.appendTo(liSubTitle);
				}
				
				jQuery.each(elements, function(index, value) {
					var display = state == 'trash' ? 'display: none' : '';
					var classTrash = state == 'trash' ? 'trashEl' : '';
					var li = jQuery('<li value="' + index + '" style="font-size: 12px; ' + display + '" class="subMenuAdmin ' + classTrash + '"></li>')
						.appendTo(state == 'trash' ? divTrash : JBtnGroup);
					if(value.enabled) {
						var sub = jQuery('<a style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;" href="#' + self.options.idModal + '" data-bs-toggle="modal"></a>')
						.text(self.options.elementsNames[index])
						.css({
							'cursor': 'pointer',
							'padding-left': '10px',
						})
						.appendTo(li);
					} else {
						var sub = jQuery('<b/>')
						.text(self.options.elementsNames[index])
						.css({
							'padding-left': '10px',
							'color': '#999'
						})
						.appendTo(li);
					}

					sub.on('click', function() {
						self.setModalToEditElement(this);
					});
				});
			});

			divTrash.appendTo(JBtnGroup);
		},

		/**
		 * Function that set the events on edit list button and add element button 
		 * 
		 */
		setCssAndEventsButtons: function(editListButton, addElementButton) {
			var self = this;

			addElementButton.on('click', function() {
				self.options.valIdEl = 0;
				jQuery('[name=history_type]').val('')

				Els = jQuery('.fabrikinput');
				Els.each(function() {
					id = this.id;
					switch (id) {
						case 'easyadmin_modal___use_filter0':
						case 'easyadmin_modal___required0':
						case 'easyadmin_modal___trash0':
						case 'easyadmin_modal___show_in_list0':
						case 'easyadmin_modal___show_down_thumb0':
						case 'easyadmin_modal___ajax_upload0':
						case 'easyadmin_modal___make_thumbs0':
						case 'easyadmin_modal___multi_select0':
						case 'easyadmin_modal___tags1':
						case 'easyadmin_modal___multi_relation0':
							jQuery(this).prop('checked', true);
							break;

						case 'easyadmin_modal___type':
						case 'easyadmin_modal___text_format':
							jQuery(this).val('text');
							break;
                        
						case 'easyadmin_modal___format_long_text':
                            jQuery(this).val('0');
                            break;

						default:
							if(!jQuery(this).hasClass('input-list')) {
								jQuery(this).val('');
							}
							break;
					}
				});

			    jQuery('#jlow_fabrik_easyadmin_modal___listas-auto-complete').prop('disabled', false);

				jQuery('#easyadmin_modal___type').trigger('change');
				jQuery('input[name="easyadmin_modal___show_in_list"]').trigger('change', {button: 'new-element'});
				jQuery('label[for="easyadmin_modal___label_advanced_link"]').trigger('click');
				jQuery('#easyadmin_modal___options_dropdown').trigger("chosen:updated");

                jQuery('#easyadmin_modal___label').empty();
				jQuery('#easyadmin_modal___father').empty();
				
                jQuery('#easyadmin_modal___options_dropdown').parent().find('#easyadmin_modal___options_dropdown_chosen').css('width', '95%');
			});

			editListButton.find('button').css({
				'min-height': '30px',
				'font-size': '12px',
				'width': '100%',
				'border-radius': '12px',
				'color': '#011627',
				'background-color': '#e3ecf1',
			});

			addElementButton.find('button').css({
				'min-height': '30px',
				'font-size': '12px',
				'width': '100%',
				'border-radius': '12px',
				'color': '#fff',
				'background-color': '#003EA1',
				'margin-bottom': '5px'
			});
		},

		sortColumns: function () {
			var self = this;

			// Certifique-se de que o Sortable está disponível
			if (typeof Sortable !== 'undefined') {
				const table = document.getElementById('list_' + jQuery('[name=listid]').val() + '_com_fabrik_' + jQuery('[name=listid]').val());
				const initialOrder = self.getColumnOrder(table);
				if (table) {
					// Seleciona o thead para tornar as colunas ordenáveis
					const thead = table.querySelector('thead tr');
					// Aplica o SortableJS ao cabeçalho
					Sortable.create(thead, {
						animation: 150,
						onEnd: function (evt) {

							var result = [];
							const oldIndex = evt.oldIndex;
							const newIndex = evt.newIndex;
							// Reordena as células no tbody
							table.querySelectorAll('tbody tr').forEach(function (row) {
								const cells = Array.from(row.children);
								const movedCell = cells.splice(oldIndex, 1)[0];
								cells.splice(newIndex, 0, movedCell);
								// Atualiza a ordem das células
								row.innerHTML = '';
								cells.forEach(function (cell) {
									row.appendChild(cell);
								});

							});

							const currentOrder = self.getColumnOrder(table);
							if (oldIndex != newIndex && newIndex != 0) {
								showSpinner();
								result.push({
									idOrder: currentOrder[newIndex-1].order.replace('_order', ''),
									idAtual: initialOrder[oldIndex].order.replace('_order', '')
								});
								self.saveEvent('columns', result[0]);
							}
						}
					});
				}
			}
		},

		getColumnOrder: function (table) {
			// Seleciona a primeira linha de cabeçalho da tabela
			const headerRow = table.querySelector("thead tr");
			const columns = headerRow.querySelectorAll("th");

			// Cria um array com os nomes das colunas
			return Array.from(columns).map((column) => ({
				name: column.classList[2],
				order: column.classList[3]
			}));
		}
	});

	return FbListEasyadmin;
});