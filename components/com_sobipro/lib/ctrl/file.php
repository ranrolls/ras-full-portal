<?php
/**
 * @version: $Id: file.php 4387 2015-02-19 12:24:35Z Radek Suski $
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
 * $HeadURL: file:///opt/svn/SobiPro/Component/branches/SobiPro-1.1/Site/lib/ctrl/file.php $
 */

defined( 'SOBIPRO' ) || exit( 'Restricted access' );
SPLoader::loadController( 'controller' );

/**
 * @author Radek Suski
 * @version 1.0
 * @created Mon, Dec 3, 2012 13:00:53
 */
class SPFileUploader extends SPController
{
	/**
	 */
	public function execute()
	{
		$this->_task = strlen( $this->_task ) ? $this->_task : $this->_defTask;
		switch ( $this->_task ) {
			case 'upload':
				$this->upload();
				break;
		}

	}

	protected function upload()
	{
		$ident = SPRequest::cmd( 'ident', null, 'post' );
		$data = SPRequest::file( $ident, 'tmp_name' );
		$secret = md5( Sobi::Cfg( 'secret' ) );
		if ( $data ) {
			$properties = SPRequest::file( $ident );
			$fileName = md5( SPRequest::file( $ident, 'name' ) . time() . $secret );
			$path = SPLoader::dirPath( "tmp.files.{$secret}", 'front', false ) . '/' . $fileName;
			/** @var $file SPFile */
			$file = SPFactory::Instance( 'base.fs.file' );
			if ( !( $file->upload( $data, $path ) ) ) {
				$this->message( array( 'type' => 'error', 'text' => SPLang::e( 'CANNOT_UPLOAD_FILE' ), 'id' => '' ) );
			}
			$path = $file->getPathname();
			$type = $this->check( $path );
			$properties[ 'tmp_name' ] = $path;
			SPFs::write( $path . '.var', SPConfig::serialize( $properties ) );
			$response = array(
					'type' => 'success',
					'text' => Sobi::Txt( 'FILE_UPLOADED', $properties[ 'name' ], $type ),
					'id' => 'file://' . $fileName,
					'data' => array( 'name' => $properties[ 'name' ], 'type' => $properties[ 'type' ], 'size' => $properties[ 'size' ] )
			);
		}
		else {
			$response = array(
					'type' => 'error',
					'text' => SPLang::e( 'CANNOT_UPLOAD_FILE_NO_DATA' ),
					'id' => '',
			);
		}
//		$field = SPRequest::cmd( 'field', null );
		$this->message( $response );
	}

	protected function check( $file )
	{
		$allowed = SPLoader::loadIniFile( 'etc.files' );
		$mType = SPFactory::Instance( 'services.fileinfo', $file )->mimeType();
		if ( strlen( $mType ) && !( in_array( $mType, $allowed ) ) ) {
			SPFs::delete( $file );
			$this->message( array( 'type' => 'error', 'text' => SPLang::e( 'FILE_WRONG_TYPE', $mType ), 'id' => '' ) );
		}
		return $mType;
	}

	protected function message( $response )
	{
		SPFactory::mainframe()
				->cleanBuffer()
				->customHeader();
		echo json_encode( $response );
		exit;
	}
}
