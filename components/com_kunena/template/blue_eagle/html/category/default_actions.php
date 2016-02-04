<?php
/**
 * Kunena Component
 * @package Kunena.Template.Blue_Eagle
 * @subpackage Category
 *
 * @copyright (C) 2008 - 2015 Kunena Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.kunena.org
 **/
defined ( '_JEXEC' ) or die ();
?>
<?php if (!empty ($this->categoryButtons)) : ?>
<td class="klist-actions-forum">

<?php

define( '_JEXEC', 1 );
define('JPATH_BASE', dirname(__FILE__) );//this is when we are in the root
define( 'DS', DIRECTORY_SEPARATOR );

require_once ( JPATH_BASE .DS.'includes'.DS.'defines.php' );
require_once ( JPATH_BASE .DS.'includes'.DS.'framework.php' );

$mainframe =& JFactory::getApplication('site');
$mainframe->initialise();
$user = JFactory::getUser();
$user_id = $user->username;
//echo "vishal".$user_id;
 if($user_id=='admin'){

?>

	<div class="kmessage-buttons-row"><?php echo implode(' ', $this->categoryButtons) ?></div>

 
<?php } ?>

</td>




<?php endif ?>
