/**
 Copyright (c) 2010-2015 SpryMedia Limited
 http://datatables.net

 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:

 The above copyright notice and this permission notice shall be included in
 all copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 THE SOFTWARE.
*/

/**
 * This pagination plug-in provides a `dt-tag select` menu with the list of the page
 * numbers that are available for viewing.
 *
 *  @name Select list
 *  @summary Show a `dt-tag select` list of pages the user can pick from.
 *  @author _jneilliii_
 *
 *  @example
 *    $(document).ready(function() {
 *        $('#example').dataTable( {
 *            "sPaginationType": "listbox"
 *        } );
 *    } );
 */

$.fn.dataTableExt.oPagination.listbox = {
	/*
	 * Function: oPagination.listbox.fnInit
	 * Purpose:  Initalise dom elements required for pagination with listbox input
	 * Returns:  -
	 * Inputs:   object:oSettings - dataTables settings object
	 *             node:nPaging - the DIV which contains this pagination control
	 *             function:fnCallbackDraw - draw function which must be called on update
	 */
	"fnInit": function (oSettings, nPaging, fnCallbackDraw) {
		var nInput = document.createElement('select');
		var nPage = document.createElement('span');
		var nOf = document.createElement('span');
		nOf.className = "paginate_of";
		nPage.className = "paginate_page";
		if (oSettings.sTableId !== '') {
			nPaging.setAttribute('id', oSettings.sTableId + '_paginate');
		}
		nInput.style.display = "inline";
		nPage.innerHTML = "Page ";
		nPaging.appendChild(nPage);
		nPaging.appendChild(nInput);
		nPaging.appendChild(nOf);
		$(nInput).change(function (e) { // Set DataTables page property and redraw the grid on listbox change event.
			window.scroll(0,0); //scroll to top of page
			if (this.value === "" || this.value.match(/[^0-9]/)) { /* Nothing entered or non-numeric character */
				return;
			}
			var iNewStart = oSettings._iDisplayLength * (this.value - 1);
			if (iNewStart > oSettings.fnRecordsDisplay()) { /* Display overrun */
				oSettings._iDisplayStart = (Math.ceil((oSettings.fnRecordsDisplay() - 1) / oSettings._iDisplayLength) - 1) * oSettings._iDisplayLength;
				fnCallbackDraw(oSettings);
				return;
			}
			oSettings._iDisplayStart = iNewStart;
			fnCallbackDraw(oSettings);
		}); /* Take the brutal approach to cancelling text selection */
		$('span', nPaging).bind('mousedown', function () {
			return false;
		});
		$('span', nPaging).bind('selectstart', function () {
			return false;
		});
	},
	 
	/*
	 * Function: oPagination.listbox.fnUpdate
	 * Purpose:  Update the listbox element
	 * Returns:  -
	 * Inputs:   object:oSettings - dataTables settings object
	 *             function:fnCallbackDraw - draw function which must be called on update
	 */
	"fnUpdate": function (oSettings, fnCallbackDraw) {
		if (!oSettings.aanFeatures.p) {
			return;
		}
		var iPages = Math.ceil((oSettings.fnRecordsDisplay()) / oSettings._iDisplayLength);
		var iCurrentPage = Math.ceil(oSettings._iDisplayStart / oSettings._iDisplayLength) + 1; /* Loop over each instance of the pager */
		var an = oSettings.aanFeatures.p;
		for (var i = 0, iLen = an.length; i < iLen; i++) {
			var spans = an[i].getElementsByTagName('span');
			var inputs = an[i].getElementsByTagName('select');
			var elSel = inputs[0];
			if(elSel.options.length != iPages) {
				elSel.options.length = 0; //clear the listbox contents
				for (var j = 0; j < iPages; j++) { //add the pages
					var oOption = document.createElement('option');
					oOption.text = j + 1;
					oOption.value = j + 1;
					try {
						elSel.add(oOption, null); // standards compliant; doesn't work in IE
					} catch (ex) {
						elSel.add(oOption); // IE only
					}
				}
				spans[1].innerHTML = "&nbsp;of&nbsp;" + iPages;
			}
		  elSel.value = iCurrentPage;
		}
	}
};
