<?php
/**
 * ------------------------------------------------------------------------
 * JUDirectory for Joomla 2.5, 3.x
 * ------------------------------------------------------------------------
 *
 * @copyright      Copyright (C) 2010-2015 JoomUltra Co., Ltd. All Rights Reserved.
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 * @author         JoomUltra Co., Ltd
 * @website        http://www.joomultra.com
 * @----------------------------------------------------------------------@
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

JLoader::register('JFormFieldList', JPATH_LIBRARIES . '/joomla/form/fields/list.php');

class JFormFieldFilter_catlv1 extends JFormFieldList
{
	protected $type = 'Filter_catlv1';

	protected function getOptions()
	{
		
		$options = array();

		foreach ($this->element->children() AS $option)
		{

			
			if ($option->getName() != 'option')
			{
				continue;
			}

			
			$tmp = JHtml::_(
				'select.option', (string) $option['value'],
				JText::alt(trim((string) $option), preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)), 'value', 'text',
				((string) $option['disabled'] == 'true')
			);

			
			$tmp->class = (string) $option['class'];

			
			$tmp->onclick = (string) $option['onclick'];

			
			$options[] = $tmp;
		}

		reset($options);

		$categories = JUDirectoryHelper::getCatsByLevel(1);
		if ($categories)
		{
			foreach ($categories AS $category)
			{
				$options[] = JHtml::_(
					'select.option', $category->id,
					$category->title);
			}
		}

		return $options;
	}
}

?>