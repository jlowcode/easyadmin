/**
 * Easy Admin
 *
 * @copyright: Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license  : GNU/GPL http                         :                              //www.gnu.org/copyleft/gpl.html
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
		initialize: function (options) { 
            // Init options
			self = this;
			this.options = options;

			this.setElementsDatabasejoin();
			this.setElementType();
			this.setUpButtonSave();
			this.setUpButtonsPainel();

			Fabrik.addEvent('fabrik.list.submit.ajax.complete', function () {
				self.setUpButtonsPainel();
			});
		},

		/**
		 * Function to show the buttons from painel
		 * 
		 */
		setUpButtonsPainel: function () {
			const heading = jQuery('th.heading.fabrik_ordercell.fabrik_actions')[0];
			const btnGroup = this.options.actionMethod == 'inline' ? jQuery(heading).find('.btn-group')[0] : jQuery(heading).find('.dropdown-menu')[0];			

			this.setButtons(this.options.elements);
			this.setActionPanel(this.options.elements);
			jQuery(document).ready(function () {
				jQuery(document).on('mouseenter', '.heading.fabrik_ordercell', function () {
					jQuery(this).find(":button.elementAdminButton").show();
				}).on('mouseleave', '.heading.fabrik_ordercell', function () {
					jQuery(this).find(":button.elementAdminButton").hide();
				});
			});
		},

		// Create a button of an element edit link
		// @link link of the button
		createButton: function(index) {
			var sub = jQuery('<a href="#' + self.options.idModal + '" data-bs-toggle="modal">' + this.options.images.edit + '</a>');
			var button = jQuery('<li value="' + index + '" style="font-size: 12px;"></li>')
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
		 * Function to make the multiselect databasejoin single and to set up the element
		 * 
		 */
		setElementsDatabasejoin: function() {
			self = this;

			var multiSelectElement = jQuery('.select2-search__field');
			multiSelectElement.on('change', function() {
				jQuery(this).remove();
				self.options.inputSearch = jQuery(this);
			});

			jQuery(document).on('click', '.select2-selection__choice__remove', function() {
				self.options.inputSearch.on('change', function() {
					jQuery(this).remove();
					self.options.inputSearch = jQuery(this);
				});
				jQuery(this).parent().remove();
				jQuery('.select2-selection__rendered li').append(self.options.inputSearch);
				self.setUpElementList();
			});

			jQuery('.select2-selection').on('click', function() {
				var spanSelect2 = jQuery(this);
				if(spanSelect2.find('.select2-search__field').length == 0 && spanSelect2.find('.select2-selection__rendered li').length == 0) {
					spanSelect2.find('.select2-search').append(self.options.inputSearch);
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
			var searchElementList = jQuery('#easyadmin_modal___list .select2-search__field');
			searchElementList.on('change', function() {
				setTimeout(function() {
					var tid = jQuery('.select2-selection__choice').attr('title');
					
					if (!tid) {
						return;
					}

					var url = "index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=element&plugin=field&method=ajax_fields&showall=1&cid=1&t='" + tid + "'";
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
						
						jQuery('.child-element-list').each(function(index, element) {
							jQuery(element).empty();
						});

						opts.forEach(opt => {
							var o = {'value': opt.value};
							if (opt.value === self.options.labelList) {
								o.selected = 'selected';
							}
							Array.each(els, function (el) {
								new Element('option', o).set('text', opt.label).inject(el);
							});
						});
					});
				}, 200);
			});
		},

		/**
		 * Function that set the events to type element
		 * 
		 */
		setElementType: function() {
			var elType = jQuery('#easyadmin_modal___type');

			elType.on('change', function() {
				type = jQuery(this).val();

				jQuery('.modal-element').each(function(index, element) {
					elementClass = jQuery(this).prop('class');

					if(elementClass.indexOf('type-'+type) < 0) {
						jQuery(this).parent().addClass('fabrikHide');
					} else {
						jQuery(this).parent().removeClass('fabrikHide');
					}
				});
			});

			type = elType.val();
			jQuery('.modal-element').each(function(index, element) {
				elementClass = jQuery(this).prop('class');

				if(elementClass.indexOf('type-'+type) < 0) {
					jQuery(this).parent().addClass('fabrikHide');
				} else {
					jQuery(this).parent().removeClass('fabrikHide');
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

		saveEvent: function(mode) {
			valEls = {};
			inputs = jQuery('.fabrikinput');

			listId = jQuery('[name=listid]').val();
			db_table_name = jQuery('[name=db_table_name]').val();

			valEls['easyadmin_modal___mode'] = mode;
			valEls['easyadmin_modal___listid'] = listId;
			valEls['jform'] = {'db_table_name': db_table_name};
			valEls['easyadmin_modal___valIdEl'] = self.options.valIdEl;
			inputs.each(function() {
				id = this.id;

				switch (id) {
					case 'easyadmin_modal___name':
					case 'easyadmin_modal___type':
					case 'easyadmin_modal___default_value':
					case 'easyadmin_modal___options_dropdown':
					case 'easyadmin_modal___label':
					case 'easyadmin_modal___father':
					case 'easyadmin_modal___format':
					case 'easyadmin_modal___access_rating':
					case 'easyadmin_modal___name_list':
					case 'easyadmin_modal___description_list':
					case 'easyadmin_modal___ordering_list':
					case 'easyadmin_modal___ordering_type_list':
					case 'easyadmin_modal___default_layout':
						valEls[id] = jQuery(this).val()
						break;
					
					case 'easyadmin_modal___use_filter1':
					case 'easyadmin_modal___required1':
					case 'easyadmin_modal___ajax_upload1':
					case 'easyadmin_modal___make_thumbs1':
					case 'easyadmin_modal___multi_select1':
					case 'easyadmin_modal___multi_relation1':
						id = id.replace('1', '');
						valEls[id] = jQuery(this).prop('checked') ? true : '';
						break;
				}
			});

			valEls['easyadmin_modal___list'] = jQuery('[name="easyadmin_modal___list[]"]').val()[0];
			valEls['order_by'] = [valEls['easyadmin_modal___ordering_list']];
			valEls['order_dir'] = [valEls['easyadmin_modal___ordering_type_list']];

			if(valEls['easyadmin_modal___name_list'] == '') {
				alert(Joomla.JText._("PLG_EASY_ADMIN_ERROR_VALIDATE"));
				return;
			}

			var url = "index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&g=list&plugin=easyadmin&method=SaveModal";
			jQuery.ajax({
				url     : url,
				method	: 'post',
				data: valEls,
			}).done(function (r) {
				r = JSON.parse(r);
				
				if(!r['error']) {
					alert(Joomla.JText._("PLG_EASY_ADMIN_SUCCESS"));
					window.location.reload();
				} else {
					alert(r['message']);
				}
			});
		},

		// Set buttons to edit the elements
		// @links array of the links
		setButtons: function(links)  {
			for (var key in links) {
				if(links.hasOwnProperty(key) && links[key].enabled) {
					var element = jQuery('th.'+ links[key].fullname).children();
					element.css({'display': 'flex'});
					element.addClass("tooltip2");
					var button  = this.createButton(key);
					element.append(button);
					element.css({
						"min-width": "120px;"
					});
					
				}
			}
		},

		setActionPanel: function (elements) {
			if(this.options.actionMethod == 'inline') {
				this.setActionPanelInline(elements);
			} else if (this.options.actionMethod == 'dropdown') {
				this.setActionPanelDropdown(elements);
			} else {
				throw new Error(Joomla.JText._("PLG_EASY_ADMIN_ACTION_METHOD_ERROR"));
			}
		},

		setActionPanelInline: function (elements) {
			var self = this;

			var button = jQuery('<a class="btn fabrik_view fabrik__rowlink btn-default"><span>' + this.options.images.admin + '</span><span class="hidden">' + Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADMIN") +'</span></a>');
			var heading = jQuery('th.heading.fabrik_ordercell.fabrik_actions')[0];
			var btnGroup = jQuery(heading).find('.btn-group')[0];

			var editListButton = jQuery('<li><button href="#' + self.options.idModalList + '" data-bs-toggle="modal" type="button">' + Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_EDIT_LIST") + '</button></li>');
			var addElementButton = jQuery('<li><button href="#' + self.options.idModal + '" data-bs-toggle="modal" type="button">' + Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT") + '</button></li>');
			
			if(!btnGroup) {
				var newBtnGroup = jQuery('<div class="btn-group"></div>');
				jQuery(heading).find("span").append(newBtnGroup);
				btnGroup = jQuery(heading).find('.btn-group')[0];
			}
			var JBtnGroup = jQuery(btnGroup);

			this.setCssAndEventsButtons(editListButton, addElementButton);

			var div = jQuery("<div></div>");
			div.css({
				'font-size': '12px',
				'position': 'absolute',
				'z-index': 100,
				'background-color' : "#FFF",
				'display': 'none',
				'right' : '50%',
				'padding' : '10px',
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

			jQuery.each(elements, function(index, value) {
				var li = jQuery('<li value="' + index + '" style="font-size: 12px"></li>')
					.css({'font-size': '12px'})
					.appendTo(div);
				if(value.enabled) {
					var sub = jQuery('<a href="#' + self.options.idModal +'" data-bs-toggle="modal"></a>')
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

			div.append(editListButton);
			JBtnGroup.append(div);
		},

		setModalToEditElement: function (el) {
			var li = jQuery(el).parent();
			var idEl = li.prop('value');
			var options = self.options.elements[idEl];

			self.options.valIdEl = idEl;

			if(options.enabled) {
				jQuery.each(options, function(index, value) {
					el = jQuery('#easyadmin_modal___' + index);
					if(el.length > 0) {
						if(el.find('.switcher').length == 0) {
							el.val(value);

							if(index == 'label') {
								self.options.labelList = value;
							}

							if(index == 'father') {
								self.options.fatherList = value;
							}

							if(index == 'list') {
								var list = jQuery('<li class="select2-selection__choice" title="' + value + '" data-select2-id="18"><span class="select2-selection__choice__remove" role="presentation">×</span>' + value + '</li>');
								var option = jQuery('<option value="' + value + '" data-select2-id="18">' + value + '</option>');

								jQuery('[name="easyadmin_modal___list[]"] > option').remove();
								jQuery('[name="easyadmin_modal___list[]"]').append(option);
								jQuery('[name="easyadmin_modal___list[]"]').val(value);

								jQuery('#easyadmin_modal___' + index + ' .select2-selection__choice').remove();
								jQuery('#easyadmin_modal___' + index + ' .select2-selection__rendered').append(list);
								jQuery('#easyadmin_modal___' + index + ' .select2-search__field').trigger('change');
							}
						} else {
							value ? p = 1 : p = 0;
							elSw = el.find('#easyadmin_modal___' + index + p);
							elSw.prop('checked', true);
						}
					}
				})
			}

			jQuery('#easyadmin_modal___type').trigger('change');
		},

		setActionPanelDropdown: function (elements) {
			var self = this;

			var button = jQuery('<li class="nav-link"><a title="Admin"><span>' + this.options.images.admin +'</span> ' + Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADMIN") + '</a></li>');
			var heading = jQuery('th.heading.fabrik_ordercell.fabrik_actions')[0];
			var btnGroup = jQuery(heading).find('.dropdown-menu')[0];

			var editListButton = jQuery('<li class="subMenuAdmin" style="display: none; padding: 0px 10px;"><button href="#' + self.options.idModalList + '" data-bs-toggle="modal" type="button">' + Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_EDIT_LIST") +'</button></li>');
			var addElementButton = jQuery('<li class="subMenuAdmin" style="display: none; padding: 0px 10px;"><button href="#' + self.options.idModal + '" data-bs-toggle="modal" type="button">' + Joomla.JText._("PLG_FABRIK_LIST_EASY_ADMIN_ADD_ELEMENT") +'</button></li>');

			if(!btnGroup) {
				var newBtnGroup = jQuery('<div class="dropdown fabrik_action"><button class="btn btn-default btn-mini dropdown-toggle dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-angle-down" aria-hidden="true"></i></button><ul class="dropdown-menu dropdown-menu-end" style=""></ul></div>');
				jQuery(heading).find("span").append(newBtnGroup);
				btnGroup = jQuery(heading).find('.dropdown-menu')[0];
			}
			var JBtnGroup = jQuery(btnGroup);

			this.setCssAndEventsButtons(editListButton, addElementButton);

			button.on('click', function () {
				jQuery.each(jQuery(this).parent().find('.subMenuAdmin'), function () {
					if(jQuery(this).css('display') == 'none') {
						jQuery(this).css({'display': 'block' });
					} else {
						jQuery(this).css({'display': 'none' });
					}
				});
			});

			JBtnGroup.append(button);
            button.append(addElementButton);

			jQuery.each(elements, function(index, value) {
				var li = jQuery('<li value="' + index + '" style="font-size: 12px; display: none;" class="subMenuAdmin"></li>')
					.css({'font-size': '12px'})
					.appendTo(button);
				if(value.enabled) {
					var sub = jQuery('<a href="#' + self.options.idModal + '" data-bs-toggle="modal"></a>')
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

			button.append(editListButton);
		},

		setCssAndEventsButtons: function(editListButton, addElementButton) {
			var self = this;

			editListButton.on('click', () => {
				//window.open(self.options.listUrl,'_blank', menubar=false);
			});
			
			addElementButton.on('click', function() {
				self.options.valIdEl = 0;

				Els = jQuery('.fabrikinput');
				Els.each(function() {
					id = this.id;
					switch (id) {
						case 'easyadmin_modal___name':
						case 'easyadmin_modal___type':
						case 'easyadmin_modal___default_value':
						case 'easyadmin_modal___options_dropdown':
						case 'easyadmin_modal___label':
						case 'easyadmin_modal___father':
						case 'easyadmin_modal___format':
						case 'easyadmin_modal___access_rating':
							jQuery(this).val('')
							break;
						
						case 'easyadmin_modal___use_filter0':
						case 'easyadmin_modal___required0':
						case 'easyadmin_modal___ajax_upload0':
						case 'easyadmin_modal___make_thumbs0':
						case 'easyadmin_modal___multi_select0':
						case 'easyadmin_modal___multi_relation0':
							jQuery(this).prop('checked', true);
							break;
					}
				});

				jQuery('#easyadmin_modal___list .select2-selection__choice__remove').trigger('click');
				jQuery('#easyadmin_modal___label').empty();
				jQuery('#easyadmin_modal___father').empty();
			});

			editListButton.find('button').css({
				'min-height': '30px',
				'font-size': '12px',
				'width': '100%',
				'border-radius': '12px',
				'color': '#000',
				'background-color': '#e3ecf1',
				'margin-top': '20px'
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
		}		
	});

	return FbListEasyadmin;
});
