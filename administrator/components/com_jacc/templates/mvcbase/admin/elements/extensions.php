<?php
/**
 * @version		$Id:extensions.php 1 ##date##Z ##sauthor## $
 * @author	   	##author##
 * @package    ##Component##
 * @subpackage Controllers
 * @copyright  	Copyright (C) ##year##, ##author##. All rights reserved.
 * @license ##license##
 */
defined('_JEXEC') or die;


require_once (JPATH_ADMINISTRATOR.'/components/com_##component##/helpers/##component##.php' );

class JElementExtensions extends JElement
{
	/**
	 * Element name
	 *
	 * @access	protected
	 * @var		string
	 */
	var	$_name = 'Extensions';

	function fetchElement($name, $value, &$node, $control_name)
	{
	
		$extensions = ##Component##Helper::getExtensions();
		$options = array();
		foreach ($extensions as $extension) {   
		
			$option = new stdClass();
			$option->text = JText::_(ucfirst((string) $extension->name));
			$option->value = (string) $extension->name;
			$options[] = $option;
			
		}		
		
		return JHTML::_('select.genericlist', $options, ''.$control_name.'['.$name.']', 'class="inputbox"', 'value', 'text', $value, $control_name.$name );
	}
}