<?php
/**
 * @version: $Id: url.php 4387 2015-02-19 12:24:35Z Radek Suski $
 * @package: SobiPro Component for Joomla!
 * @author
 * Name: Sigrid Suski & Radek Suski, Sigsiu.NET GmbH
 * Email: sobi[at]sigsiu.net
 * Url: http://www.Sigsiu.NET
 * @copyright Copyright (C) 2006 - 2015 Sigsiu.NET GmbH (http://www.sigsiu.net). All rights reserved.
 * @license GNU/GPL Version 3
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License version 3 as published by the Free Software Foundation, and under the additional terms according section 7 of GPL v3.
 * See http://www.gnu.org/licenses/gpl.html and http://sobipro.sigsiu.net/licenses.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 * $Date: 2015-02-19 13:24:35 +0100 (Thu, 19 Feb 2015) $
 * $Revision: 4387 $
 * $Author: Radek Suski $
 * $HeadURL: file:///opt/svn/SobiPro/Component/branches/SobiPro-1.1/Site/opt/fields/adm/url.php $
 */

defined( 'SOBIPRO' ) || exit( 'Restricted access' );
SPLoader::loadClass( 'opt.fields.url' );

/**
 * @author Radek Suski
 * @version 1.0
 * @created 14-Sep-2009 11:37:11
 */
class SPField_UrlAdm extends SPField_Url
{
	/**
	 * @var string
	 */
	public $cssClass = "inputbox";

	public function onFieldEdit()
	{
		$this->allowedProtocols = is_array( $this->allowedProtocols ) ? implode( ',', $this->allowedProtocols ) : null;
	}

	public function save( &$attr )
	{
		if ( isset( $attr[ 'allowedProtocols' ] ) && $attr[ 'allowedProtocols' ] ) {
			$attr[ 'allowedProtocols' ] = explode( ',', $attr[ 'allowedProtocols' ] );
			if ( count( $attr[ 'allowedProtocols' ] ) ) {
				foreach ( $attr[ 'allowedProtocols' ] as $ap => $apvalue ) {
					$attr[ 'allowedProtocols' ][ $ap ] = trim( $apvalue );
				}
			}
		}
		else {
			$attr[ 'allowedProtocols' ] = array();
		}
		$myAttr = $this->getAttr();
		$properties = array();
		if ( count( $myAttr ) ) {
			foreach ( $myAttr as $property ) {
				$properties[ $property ] = isset( $attr[ $property ] ) ? ( $attr[ $property ] ) : null;
			}
		}
		$attr[ 'params' ] = $properties;
	}
}
