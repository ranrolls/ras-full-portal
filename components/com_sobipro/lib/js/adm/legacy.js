/**
 * @version: $Id: legacy.js 4387 2015-02-19 12:24:35Z Radek Suski $
 * @package: SobiPro Library

 * @author
 * Name: Sigrid Suski & Radek Suski, Sigsiu.NET GmbH
 * Email: sobi[at]sigsiu.net
 * Url: http://www.Sigsiu.NET

 * @copyright Copyright (C) 2006 - 2015 Sigsiu.NET GmbH (http://www.sigsiu.net). All rights reserved.
 * @license GNU/LGPL Version 3
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Lesser General Public License version 3 as published by the Free Software Foundation, and under the additional terms according section 7 of GPL v3.
 * See http://www.gnu.org/licenses/lgpl.html and http://sobipro.sigsiu.net/licenses.

 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

 * $Date: 2015-02-19 13:24:35 +0100 (Thu, 19 Feb 2015) $
 * $Revision: 4387 $
 * $Author: Radek Suski $
 * File location: components/com_sobipro/lib/js/adm/legacy.js $
 */

SobiPro.jQuery( document ).ready( function ()
{
	if ( SobiPro.jQuery( '#task' ).val().indexOf( 'field.' ) == -1 ) {
		if ( SobiPro.jQuery( '#SPAdminForm >:first-child' ).prop( 'tagName' ).toLocaleLowerCase() == 'div' ) {
			SobiPro.jQuery( '#SPAdminForm >:first-child' ).removeAttr( 'style' ).addClass( 'span2' );
			SobiPro.jQuery( '#SPAdminForm >:nth-child(2)' ).removeAttr( 'style' ).addClass( 'span10' );
		}
		else {
			SobiPro.jQuery( '#SPAdminForm' ).children().each( function ( i, e )
			{
				if ( SobiPro.jQuery( e ).prop( 'tagName' ).toLowerCase() == 'table' ) {
					var row = SobiPro.jQuery( e ).find( '>:first-child>:first-child' );
					SobiPro.jQuery( row ).find( '>:first-child' ).removeAttr( 'style' ).addClass( 'span2' );
					SobiPro.jQuery( row ).find( '>:nth-child(2)' ).removeAttr( 'style' ).addClass( 'span10' );
				}
			} );
		}
	}
	else {
		SobiPro.jQuery( '[class*="width-"]' ).removeAttr( 'style' );
	}
	try {
		if( Joomla == undefined ) {}
	} catch( e ) {
		function JLegacy() {}
		Joomla = new JLegacy();
	}
	Joomla.submitform = function ( task )
	{
		SobiPro.jQuery( '#task' ).val( task );
		SobiPro.jQuery( '#SPAdminForm' ).submit();
	}
} );
