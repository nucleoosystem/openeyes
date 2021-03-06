/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2016
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2016, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
 /**
 *  HOW TO USE
	Create the search by adding
		<?php $this->widget('application.widgets.AutoCompleteSearch'); ?>
	to the html. Then call
		OpenEyes.UI.AutoCompleteSearch.init({
			input: $('#oe-autocompletesearch'),
			url: URL,
			onSelect: function(){
				let AutoCompleteResponse = OpenEyes.UI.AutoCompleteSearch.getResponse();
				// DESIRED ACTION
			}
		});
	Change URL to suit. To define what happens when the user clicks on a search option, add you code in the onSlect function
	AutoCompleteResponse will return an object of the option clicked.

  	To have more than 1 search box add
  		<?php $this->widget('application.widgets.AutoCompleteSearch',['field_name' => NAME]); ?>
  	to the html. Then call
		OpenEyes.UI.AutoCompleteSearch.init($('NAME'), URL);
	Set NAME to what ever you want. If used correctly you will have 
		<?php $this->widget('application.widgets.AutoCompleteSearch'); ?> and <?php $this->widget('application.widgets.AutoCompleteSearch',['field_name' => NAME]); ?>
	on the same page along with
		OpenEyes.UI.AutoCompleteSearch.init({input: $('#oe-autocompletesearch'), ...}); and OpenEyes.UI.AutoCompleteSearch.init({input: $('NAME'), ...});

  	To create the search without the auto complete add
  		<?php $this->widget('application.widgets.AutoCompleteSearch'); ?>
  	to the html

 */
var OpenEyes = OpenEyes || {};

OpenEyes.UI = OpenEyes.UI || {};

(function(exports) {

    'use strict';

    var search_term;
    var searching = false;
    var xhr;
    var response;
    var current_focus;
    var item_clicked;
    var inputbox;
    var onSelect = [];
    
    function initAutocomplete(input, autocomplete_url) {
    	input.on('input',function(){
    		inputbox = input;
    		inputbox.parent().find('.alert-box').addClass('hidden');
    		search_term = this.value.trim();

    		// if input is empty
    		if (search_term.length < 2){
    			setTimeout(function(){
    				if(search_term.length === 1){
    					inputbox.parent().find('.min-chars').removeClass('hidden');
    				}
	    			hideMe();
	    			return false;
	    		}, 1000);
    		} else {
	    		// cancel the current search and start a new one
				if(searching) {xhr.abort();}
				searching = true;

				xhr = $.getJSON(autocomplete_url, {
				    term: search_term,
				    ajax: 'ajax'
				}, function(data,status){
					if(status === 'success'){
						response = data;
						if(response.length > 0){
							successResponse(response);
						} else {
							inputbox.parent().find('.no-result').removeClass('hidden');
						}
						searching = false;
						current_focus = -1;
					} else if(status === 'error' || status === 'timeout'){
						console.warning('Error with AutoCompleteSearch');
					}
				});
    		}
    	});

    	input.parent().find(".oe-autocomplete").on('click', '.oe-menu-item', function(){
    		item_clicked = response[$(this).index()];
            inputbox.val('');
            onSelect[inputbox.selector.replace(/[^A-z]/, '')]();
    		hideMe();
    	});

    	input.keydown(function(e){
    		if (e.keyCode === 40) {
    			// if the arrow down key is pressed
    			if(current_focus < (response.length - 1)){
    				current_focus++;
    			}
    		} else if (e.keyCode === 38) {
    			// if the arrow up key is pressed
    			if(current_focus !== 0){
    				current_focus--;
    			}
    		} else if (e.keyCode === 13) {
    			// if the enter key is pressed
    			if (current_focus > -1) {
    				$('.oe-menu-item a:eq('+current_focus+')').trigger('click');
    			}
    		}

    		$('.oe-autocomplete a').removeClass('hint');
    		$('.oe-autocomplete a:eq('+current_focus+')').addClass('hint');
    	});

    	$(document).click(hideMe);
    }

    function successResponse(response){
	    $(".oe-autocomplete").empty();
	    var search_options = ``;

        $.each(response,function(index, value){
        	search_options += `<li class="oe-menu-item" role="presentation"><a id="ui-id-`+index+`" tabindex="-1">`;
        	if(value.fullname !== undefined){
        		search_options += matchSearchTerm(value.fullname);
        	}

        	if(value.first_name !== undefined && value.last_name !== undefined){
        		search_options += matchSearchTerm(value.first_name)+` `
        		+matchSearchTerm(value.last_name)+`
        		(`+value.age+`) `+value.gender+`<br>`
        		+value.nhsnum+`<br><br>Hospital No.: `+matchSearchTerm(value.hos_num)+`
				<br>Date of birth: `+value.dob;
        	}

            if(value.label !== undefined){
                search_options += matchSearchTerm(value.label);
            }

            if(typeof value === 'string'){
                search_options += matchSearchTerm(value);
            }

     		search_options += `</a></li>`;
        });

        inputbox.parent().find(".oe-autocomplete").append(search_options).css({'position':'absolute', 'top':inputbox.outerHeight()}).removeClass('hidden');
    }

    function matchSearchTerm(str){
    	var myRegExp = new RegExp(search_term,'ig');
    	var matches = str.match(myRegExp);
    	if(matches && matches.length > 0){
	    	$.each(matches,function(index, match){
	    		str = str.replace(match, `<span class="autocomplete-match">`+match+`</span>`);
	    	});
    	}

    	return str.trim();
    }

	function hideMe() {
		$('.oe-autocomplete').addClass('hidden');
	}

    function set_onSelect(input, f){
        var input_selector = input.selector.replace(/[^A-z]/, '');
        onSelect[input_selector] = f;
    }

    exports.AutoCompleteSearch = {
    	init: function (options) {
    		if(options.input){
                set_onSelect(options.input, options.onSelect);
	    		initAutocomplete(options.input, options.url);
	    		return exports.AutoCompleteSearch;
    		}
    	},
        getResponse: function(){
            return item_clicked;
        }
    };

})(OpenEyes.UI);