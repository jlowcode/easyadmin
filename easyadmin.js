/**
 * Easy Admin
 *
 * @copyright: Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license  : GNU/GPL http                         :                              //www.gnu.org/copyleft/gpl.html
 */
define(['jquery', 'fab/list-plugin'], function (jQuery, FbListPlugin) {
	var FbListEasyadmin = new Class({
		Extends: FbListPlugin,

		initialize: function (options) { 
            // Init options
			this.options = options;
			const { baseUri } = options;

			const heading = jQuery('th.heading.fabrik_ordercell.fabrik_actions')[0];
			const btnGroup = options.actionMethod == 'inline' ? jQuery(heading).find('.btn-group')[0] : jQuery(heading).find('.dropdown-menu')[0];
			
			if(btnGroup) {
				// Adicionando o html do modal na página
				const form = document.querySelector('.fabrikForm');

				var modalContent = document.createElement('div');
				modalContent.innerHTML = this.htmlModal();
				form.appendChild(modalContent);

				this.setButtons(options.elements, baseUri);
				this.setActionPanel(options.elements);
				jQuery(document).ready(function () {
					jQuery(document).on('mouseenter', '.heading.fabrik_ordercell', function () {
						jQuery(this).find(":button.elementAdminButton").show();
					}).on('mouseleave', '.heading.fabrik_ordercell', function () {
						jQuery(this).find(":button.elementAdminButton").hide();
					});
				});
			} else {
				return;
			}
			
			// JQuery responsável por montar o modal na tela
			jQuery("a[rel=modal]").click(function(ev){
				ev.preventDefault();
				var id = jQuery(this).attr("href");
				var alturaTela  = jQuery(document).height();
				var larguraTela = jQuery(window).width();
		
				jQuery('#mascara').css({'width':larguraTela, 'height':alturaTela});
				jQuery('#mascara').fadeIn(200);
				jQuery('#mascara').fadeTo("slow", 0.2);
		
				var left = (jQuery(window).width() / 2 ) - (jQuery(id).width() / 2 );
				var top  = (jQuery(window).height() / 2 ) - (jQuery(id).height() / 2 );
		
				jQuery(id).css({'left':left, 'top':top});
				jQuery(id).show();
			});
		
			jQuery('#mascara').click(function(){		
				jQuery(this).fadeOut("slow");
				jQuery('.window').fadeOut("slow");
			});
		
			jQuery('.fechar').click(function(ev){		
				ev.preventDefault();		
				jQuery('#mascara').fadeOut(200, "linear");
				jQuery('.window').fadeOut(200, "linear");
				window.location.reload();
			});
		},

		// Create a button of an element edit link
		// @link link of the button
		createButton: function(link, id, baseUri) {
			var button = jQuery('<a rel="modal" data-cooltipz-dir="right" href="#janela" class="tooltiptext2">' + this.options.images.edit +'</a>');
			button.on('click', () => {
				document.querySelector('#iframe-url').src = link;
			});
			return button;
		},

		// Set buttons to edit the elements
		// @links array of the links
		setButtons: function(links, baseUri)  {
			for (var key in links) {
				if(links.hasOwnProperty(key)) {
					var element = jQuery('th.'+key);
					element.addClass("tooltip2"); //Adiciona class do tooltip
					var button  = this.createButton(links[key], key+"_admin_button", baseUri);
					element.append(button);
					element.css({
						"min-width": "120px;"
					});
					
				}
			}
		},

		setActionPanel: function (links) {
			if(this.options.actionMethod == 'inline') {
				this.setActionPanelInline(links);
			} else if (this.options.actionMethod == 'dropdown') {
				this.setActionPanelDropdown(links);
			} else {
				throw new Error(Joomla.JText._("PLG_EASY_ADMIN_ACTION_METHOD_ERROR"));
			}
		},

		setActionPanelInline: function (links) {
			var self = this;
			var button = jQuery('<a class="btn fabrik_view fabrik__rowlink btn-default"><span>' + this.options.images.admin + '</span><span class="hidden">Admin</span></a>');
			var heading = jQuery('th.heading.fabrik_ordercell.fabrik_actions')[0];
			var btnGroup = jQuery(heading).find('.btn-group')[0];
			var JBtnGroup = jQuery(btnGroup);
			var editListButton = jQuery('<li><button type="button">Editar Lista</button></li>');
			
			editListButton.on('click', () => {
				window.open(self.options.listUrl, '_blank', menubar=false);
			});
			
			editListButton.find('button').css({
				'min-height': '30px',
				'font-size': '12px',
				'width': '100%',
				'border-radius': '12px',
				'color': '#fff',
				'background-color': '#003EA1',
			});

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

			jQuery.each(links, function( index, value ) {
				var li = jQuery('<li style="font-size: 12px"></li>')
					.css({'font-size': '12px'})
					.appendTo(div);
				var aaa = jQuery('<a/>')
					.text('- ' + self.options.elementsNames[index])
					.css({
						'cursor': 'pointer',
						'padding-left': '10px',
					})
					.appendTo(li);
				aaa.on('click', () => {
					window.open(value, '_blank', menubar=false);
				});
			});

			div.append(editListButton);
			JBtnGroup.append(div);
		},

		setActionPanelDropdown: function (links) {
			var self = this;

			var button = jQuery('<li class="nav-link"><a title="Admin"><span>' + this.options.images.admin +'</span> Admin</a></li>');
			var heading = jQuery('th.heading.fabrik_ordercell.fabrik_actions')[0];
			var btnGroup = jQuery(heading).find('.dropdown-menu')[0];
			var JBtnGroup = jQuery(btnGroup);
			var editListButton = jQuery('<li class="subMenuAdmin" style="display: none; padding: 0px 10px;"><button type="button">Editar Lista</button></li>');
			
			editListButton.on('click', () => {
				window.open(self.options.listUrl,'_blank', menubar=false);
			});
			
			editListButton.find('button').css({
				'min-height': '30px',
				'font-size': '12px',
				'width': '100%',
				'border-radius': '12px',
				'color': '#fff',
				'background-color': '#003EA1',
			});

			button.on('click', function () {
				jQuery.each(jQuery(this).parent().find('.subMenuAdmin'), function () {
					if(jQuery(this).css('display') == 'none') {
						jQuery(this).css({'display': 'block' });
					} else {
						jQuery(this).css({'display': 'none' });
					}
				});
			});

			jQuery.each(links, function(index, value) {
				var li = jQuery('<li style="font-size: 12px; display: none;" class="subMenuAdmin"></li>')
					.css({'font-size': '12px'})
					.appendTo(button);
				var aaa = jQuery('<a/>')
					.text('- ' + self.options.elementsNames[index])
					.css({
						'cursor': 'pointer',
						'padding-left': '10px',
					})
					.appendTo(li);
				aaa.on('click', () => {
					window.open(value,'_blank', menubar=false);
				});
			});

			button.append(editListButton);
			JBtnGroup.append(button);
		},

		htmlModal: function() {
			return `
			<style>
				.window{	
					display: none;
					width: 90%;
					height: 90vh;
					position: absolute;
					background: #FFF ;
					left: 0;
					top:0;
					z-index: 9900;
					border-radius: 10px;			
				}
				
				#mascara{				
					display: none;
					position: absolute;
					opacity: 0.2;
					background: #000 ;
					left: 0;
					top:0;
					z-index: 9000;			
				}
				
				.fechar{				
					display: block;
					text-align: right;
				}

				.tooltip2 {
					position: relative;
					border-bottom: 1px dotted black;
					cursor: pointer;
				}

				.tooltip2 .tooltiptext2 {
					visibility: hidden;
					background-color: #ccc;
					color: #fff;
					text-align: center;
					padding: 5px;
					border-radius: 6px;
				}

				.tooltip2:hover .tooltiptext2 {
					visibility: visible;
				}
			</style>
			<!-- <a href="#janela" rel="Modal">Abrir Janela Modal</a> -->
			<div class="window" id="janela">
				<a href="#" class="fechar" style="margin-right:10px; ">X Fechar</a>
				<iframe id="iframe-url" height="100%" width="100%" src="#"></iframe>
			</div>
			<div id="mascara"></div>
			`
		}
		
		
	});

	return FbListEasyadmin;
});
