<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_users
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Registration model class for Users.
 *
 * @since  1.6
 */
class UsersModelRegistration extends JModelForm
{
	/**
	 * @var    object  The user registration data.
	 * @since  1.6
	 */
	protected $data;

	/**
	 * Method to activate a user account.
	 *
	 * @param   string  $token  The activation token.
	 *
	 * @return  mixed    False on failure, user object on success.
	 *
	 * @since   1.6
	 */
	public function activate($token)
	{
		$config = JFactory::getConfig();
		$userParams = JComponentHelper::getParams('com_users');
		$db = $this->getDbo();

		// Get the user id based on the token.
		$query = $db->getQuery(true);
		$query->select($db->quoteName('id'))
			->from($db->quoteName('#__users'))
			->where($db->quoteName('activation') . ' = ' . $db->quote($token))
			->where($db->quoteName('block') . ' = ' . 1)
			->where($db->quoteName('lastvisitDate') . ' = ' . $db->quote($db->getNullDate()));
		$db->setQuery($query);

		try
		{
			$userId = (int) $db->loadResult();
		}
		catch (RuntimeException $e)
		{
			$this->setError(JText::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);

			return false;
		}

		// Check for a valid user id.
		if (!$userId)
		{
			$this->setError(JText::_('COM_USERS_ACTIVATION_TOKEN_NOT_FOUND'));

			return false;
		}

		// Load the users plugin group.
		JPluginHelper::importPlugin('user');

		// Activate the user.
		$user = JFactory::getUser($userId);

		// Admin activation is on and user is verifying their email
		if (($userParams->get('useractivation') == 2) && !$user->getParam('activate', 0))
		{
			$uri = JUri::getInstance();

			// Compile the admin notification mail values.
			$data = $user->getProperties();
			$data['activation'] = JApplicationHelper::getHash(JUserHelper::genRandomPassword());
			$user->set('activation', $data['activation']);
			$data['siteurl'] = JUri::base();
			$base = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));
			$data['activate'] = $base . JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);
			$data['fromname'] = $config->get('fromname');
			$data['mailfrom'] = $config->get('mailfrom');
			$data['sitename'] = $config->get('sitename');
			$user->setParam('activate', 1);
			$emailSubject = JText::sprintf(
				'COM_USERS_EMAIL_ACTIVATE_WITH_ADMIN_ACTIVATION_SUBJECT',
				$data['name'],
				$data['sitename']
			);

			$emailBody = JText::sprintf(
				'COM_USERS_EMAIL_ACTIVATE_WITH_ADMIN_ACTIVATION_BODY',
				$data['sitename'],
				$data['name'],
				$data['email'],
				$data['username'],
				$data['activate']
			);

			// Get all admin users
			$query->clear()
				->select($db->quoteName(array('name', 'email', 'sendEmail', 'id')))
				->from($db->quoteName('#__users'))
				->where($db->quoteName('sendEmail') . ' = ' . 1);

			$db->setQuery($query);

			try
			{
				$rows = $db->loadObjectList();
			}
			catch (RuntimeException $e)
			{
				$this->setError(JText::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);

				return false;
			}

			// Send mail to all users with users creating permissions and receiving system emails
			foreach ($rows as $row)
			{
				$usercreator = JFactory::getUser($row->id);

				if ($usercreator->authorise('core.create', 'com_users'))
				{
					$return = JFactory::getMailer()->sendMail($data['mailfrom'], $data['fromname'], $row->email, $emailSubject, $emailBody);

					// Check for an error.
					if ($return !== true)
					{
						$this->setError(JText::_('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'));

						return false;
					}
				}
			}
		}
		// Admin activation is on and admin is activating the account
		elseif (($userParams->get('useractivation') == 2) && $user->getParam('activate', 0))
		{
			$user->set('activation', '');
			$user->set('block', '0');

			// Compile the user activated notification mail values.
			$data = $user->getProperties();
			$user->setParam('activate', 0);
			$data['fromname'] = $config->get('fromname');
			$data['mailfrom'] = $config->get('mailfrom');
			$data['sitename'] = $config->get('sitename');
			$data['siteurl'] = JUri::base();
			$emailSubject = JText::sprintf(
				'COM_USERS_EMAIL_ACTIVATED_BY_ADMIN_ACTIVATION_SUBJECT',
				$data['name'],
				$data['sitename']
			);

			$emailBody = JText::sprintf(
				'COM_USERS_EMAIL_ACTIVATED_BY_ADMIN_ACTIVATION_BODY',
				$data['name'],
				$data['siteurl'],
				$data['username']
			);

			$return = JFactory::getMailer()->sendMail($data['mailfrom'], $data['fromname'], $data['email'], $emailSubject, $emailBody);

			// Check for an error.
			if ($return !== true)
			{
				$this->setError(JText::_('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'));

				return false;
			}
		}
		else
		{
			$user->set('activation', '');
			$user->set('block', '0');
		}

		// Store the user object.
		if (!$user->save())
		{
			$this->setError(JText::sprintf('COM_USERS_REGISTRATION_ACTIVATION_SAVE_FAILED', $user->getError()));

			return false;
		}

		return $user;
	}

	/**
	 * Method to get the registration form data.
	 *
	 * The base form data is loaded and then an event is fired
	 * for users plugins to extend the data.
	 *
	 * @return  mixed  Data object on success, false on failure.
	 *
	 * @since   1.6
	 */
	public function getData()
	{
		if ($this->data === null)
		{
			$this->data = new stdClass;
			$app = JFactory::getApplication();
			$params = JComponentHelper::getParams('com_users');

			// Override the base user data with any data in the session.
			$temp = (array) $app->getUserState('com_users.registration.data', array());

			foreach ($temp as $k => $v)
			{
				$this->data->$k = $v;
			}

			// Get the groups the user should be added to after registration.
			$this->data->groups = array();

			// Get the default new user group, Registered if not specified.
			$system = $params->get('new_usertype', 2);

			$this->data->groups[] = $system;

			// Unset the passwords.
			unset($this->data->password1);
			unset($this->data->password2);

			// Get the dispatcher and load the users plugins.
			$dispatcher = JEventDispatcher::getInstance();
			JPluginHelper::importPlugin('user');

			// Trigger the data preparation event.
			$results = $dispatcher->trigger('onContentPrepareData', array('com_users.registration', $this->data));

			// Check for errors encountered while preparing the data.
			if (count($results) && in_array(false, $results, true))
			{
				$this->setError($dispatcher->getError());
				$this->data = false;
			}
		}

		return $this->data;
	}

	/**
	 * Method to get the registration form.
	 *
	 * The base form is loaded from XML and then an event is fired
	 * for users plugins to extend the form with extra fields.
	 *
	 * @param   array    $data      An optional array of data for the form to interogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  JForm  A JForm object on success, false on failure
	 *
	 * @since   1.6
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_users.registration', 'registration', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.6
	 */
	protected function loadFormData()
	{
		$data = $this->getData();

		$this->preprocessData('com_users.registration', $data);

		return $data;
	}

	/**
	 * Override preprocessForm to load the user plugin group instead of content.
	 *
	 * @param   JForm   $form   A JForm object.
	 * @param   mixed   $data   The data expected for the form.
	 * @param   string  $group  The name of the plugin group to import (defaults to "content").
	 *
	 * @return  void
	 *
	 * @since   1.6
	 * @throws  Exception if there is an error in the form event.
	 */
	protected function preprocessForm(JForm $form, $data, $group = 'user')
	{
		$userParams = JComponentHelper::getParams('com_users');

		// Add the choice for site language at registration time
		if ($userParams->get('site_language') == 1 && $userParams->get('frontend_userparams') == 1)
		{
			$form->loadFile('sitelang', false);
		}

		parent::preprocessForm($form, $data, $group);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	protected function populateState()
	{
		// Get the application object.
		$app = JFactory::getApplication();
		$params = $app->getParams('com_users');

		// Load the parameters.
		$this->setState('params', $params);
	}

	/**
	 * Method to save the form data.
	 *
	 * @param   array  $temp  The form data.
	 *
	 * @return  mixed  The user id on success, false on failure.
	 *
	 * @since   1.6
	 */
	public function register($temp)
	{
		$params = JComponentHelper::getParams('com_users');

		// Initialise the table with JUser.
		$user = new JUser;
		$data = (array) $this->getData();

		// Merge in the registration data.
		foreach ($temp as $k => $v)
		{
			$data[$k] = $v;
		}

		// Prepare the data for the user object.
		$data['email'] = JStringPunycode::emailToPunycode($data['email1']);
		$data['password'] = $data['password1'];
		$useractivation = $params->get('useractivation');
		$sendpassword = $params->get('sendpassword', 1);

		// Check if the user needs to activate their account.
		if (($useractivation == 1) || ($useractivation == 2))
		{
			$data['activation'] = JApplicationHelper::getHash(JUserHelper::genRandomPassword());
			$data['block'] = 1;
		}

		// Bind the data.
		if (!$user->bind($data))
		{
			$this->setError(JText::sprintf('COM_USERS_REGISTRATION_BIND_FAILED', $user->getError()));

			return false;
		}

		// Load the users plugin group.
		JPluginHelper::importPlugin('user');

		// Store the data.
		if (!$user->save())
		{
			$this->setError(JText::sprintf('COM_USERS_REGISTRATION_SAVE_FAILED', $user->getError()));

			return false;
		}

		$config = JFactory::getConfig();
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		// Compile the notification mail values.
		$data = $user->getProperties();
		$data['fromname'] = $config->get('fromname');
		$data['mailfrom'] = $config->get('mailfrom');
		$data['sitename'] = $config->get('sitename');
		$data['siteurl'] = JUri::root();

		// Handle account activation/confirmation emails.
		if ($useractivation == 2)
		{
			// Set the link to confirm the user email.
			$uri = JUri::getInstance();
			$base = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));
			$data['activate'] = $base . JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

			$emailSubject = JText::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$data['name'],
				$data['sitename']
			);
                    #################################################################
 

			if ($sendpassword)
			{
				$emailBody = JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ADMIN_ACTIVATION_BODY',
					$data['name'],
					$data['sitename'],
					$data['activate'],
					$data['siteurl'],
					$data['username'],
					$data['password_clear']
				);
			}
			else
			{
				$emailBody = JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ADMIN_ACTIVATION_BODY_NOPW',
					$data['name'],
					$data['sitename'],
					$data['activate'],
					$data['siteurl'],
					$data['username']
				);
			}
		}
		elseif ($useractivation == 1)
		{
			// Set the link to activate the user account.
			$uri = JUri::getInstance();
			$base = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));
			$data['activate'] = $base . JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

			$emailSubject = JText::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$data['name'],
				$data['sitename']
			);

if ($sendpassword)
{
/*$emailBody = JText::sprintf('COM_USERS_EMAIL_REGISTERED_WITH_ACTIVATION_BODY',
$data['name'],
$data['sitename'],
$data['activate'],
$data['siteurl'],$data['username'],
$data['password_clear']
); */

$serverurl =  $_SERVER['HTTP_HOST'];
 
$body = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width; maximum-scale=1.0;">
<title>RAS</title>

<style type="text/css">
 body{ margin:0px; padding:0px;}
@media only screen and (max-width:598px){
table[class="mainWd"]{ width:100% !important; }
.img{ width:100% !important; }
}
@media only screen and (max-width:599px){
table{ float:none !important; }
table[class="mainWd"]{ width:100% !important; }
table[class="table-width"]{ float:left !important}
.img{ width:100% !important; }
@media only screen and (max-width:480px){
td[class="wd660"]{ width:100% !important; float:left !important; text-align:center !important; }
.img1{ display:none !important}
td[class="wd360"]{ width:100% !important; float:left !important; text-align:center; margin-bottom:20px; }	
table[class="full_480"]{ width:220px !important;  text-align:center !important;  float:none !important;  }	
td[class="mob_hide"]{ display:none !important; }
}
 
.img {width:100% !important; }
.img {width:100% !important; }
</style>
</head>

<body style="background:#cccccc;-moz-text-size-adjust:none; -webkit-text-size-adjust:none; -ms-text-size-adjust:none;  ">
<table width="100%" border="0" align="center" cellpadding="0" cellspacing="0" >
<tr><td align="center">
	<table width="650" border="0" align="center" cellpadding="0" cellspacing="0" class="mainWd" >
    
<tr><td height="25" align="center" valign="middle" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; color:#ffffff; background:#2a4c75">Can’t see this email? View it in your browser. </td></tr> 
    

  
  
  <tr>
    <td align="left" valign="top" class="bg" bgcolor="#ffffff">
	<table width="100%" border="0" align="center" cellpadding="0" cellspacing="0">
    
<tr>    <td height="20" align="left" valign="top"> <img src="http://'.$serverurl.'/images/banner123.jpg" alt=" " class="img" border="0" align="left" style="display:block;width:100%"></td>    </tr>
 
    
     
     <tr><td height="20" align="center" valign="top"> </td></tr>
     
     <tr><td   align="center" valign="top">
       <table width="96%" border="0" align="center" cellpadding="0" cellspacing="0">
       <tr><td align="left" valign="top"><p><span style="font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:20px; color:#343434; font-weight:normal;">Dear <span style="color:#f77635;">user,</span><br /><br /> Welcome to RAS. Your registration process is successfully completed.<br /> 
         </span></p>
           <p><span style="font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:20px; color:#343434; font-weight:normal;">Your privacy matters to us.</span>
             <br /><br />
             
             <!--<a href="http://'.$data['activate'].' target="_blank" ">'.$data['activate'].'</a>-->             <span style="font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:20px; color:#343434; font-weight:normal;">Here are your login details:</span></p>

       </td></tr> 
 <tr><td height="5" align="center" valign="top"> </td></tr>
 
<tr>
  <td align="left" valign="top" style="border:1px dashed #1e7fc0; padding:10px;">
<span style="font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:25px; color:#fd742f; font-weight:normal; ">

<span style="color:#000000;">Login: </span><a href="../../../xampp/htdocs/ras/'.$data['siteurl'].'my-account">'.$data['siteurl'].'my-account</a><br /><br />
<span style="color:#000000;">Username:</span> '.$data['username'].'<br /> 
<span style="color:#000000;">Password:</span> '.$data['password_clear'].'</span>

 </td></tr>
  <tr><td height="20" align="center" valign="top"> </td></tr>
<tr>
  <td align="left" valign="top">
<span style="font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:25px; color:#e10000; font-weight:normal; ">
<span style="color:#000000;"><strong>* Please Note</strong>: Your account will get activated once the administrator approves it. You’ll receive a notification for this in your email address.</span><br /><br />
<span style="font-family:Arial, Helvetica, sans-serif; font-size:26px; line-height:20px; color:#454545; font-weight:bold;">Get started now!<br /><br />  </span>

<span style="font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:20px; color:#454545; font-weight:normal;">Best regards,<br /> 
Team RAS</span>
 
 </td></tr>

 <tr><td height="20" align="center" valign="top"> </td></tr>
</table>
      </td>    
       </tr>
       
       
  <tr><td align="center" valign="top" bgcolor="#e10000" > 
  <br /> 
 <span style="font-family:Arial, Helvetica, sans-serif; font-size:26px; line-height:20px; color:#ffffff; font-weight:bold;"> Contact Us</span><br /><br />
  
 <span style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#ffffff; font-weight:normal;">  <strong style="text-transform:uppercase"> Restaurant Association of Singapore.</strong> <br />
29 Tai Seng Avenue, #06-04A Natural Cool Lifestyle Hub, Singapore 534119.</span>
   
  
  </td></tr>
    <tr><td height="20" align="center" valign="top" bgcolor="#e10000" > </td></tr>
  <tr>
    <td align="center" valign="top" bgcolor="#e10000">
  <table width="60%" border="0" align="center" cellpadding="0" cellspacing="0" class="full_480" >
      <tr>
        <td align="center" valign="top"><table width="48%" border="0" align="left" cellpadding="0" cellspacing="0" class="full_480" >
      <tr>	<td width="13"><img src="http://'.$serverurl.'/images/icon_mob.jpg" width="9" height="13" /></td><td width="5"></td>     
	<td width="180" align="left" valign="middle" style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#ffffff;"> Call us on: (65) 6479 7723 </td> </tr>
      </table>
      
      <table width="48%" border="0" align="right" cellpadding="0" cellspacing="0"  class="full_480">
      <tr> 
     <td width="13"><img src="http://'.$serverurl.'/images/icon_mail.jpg" width="13" height="10" /></td><td width="5"></td>     
     <td align="left" valign="middle" style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#ffffff;"> Email us: <a href="mailto:info@ras.org.sg" style="font-size:12px; color:#ffffff;">info@ras.org.sg</a></td> </tr>
      </table>
       </td></tr> 
       
       
     <tr> 

	
     </tr>

       
       </table></td></tr>
       <tr><td height="20" align="center" valign="top" bgcolor="#e10000" > </td></tr>
  </table>


</td>
  </tr>
 
   
	 
 
       <tr> <td align="center" valign="middle" height="37 " bgcolor="#2a4c75" > <span style="font-family:Arial, Helvetica, sans-serif; font-size:12px  ; color:#ffffff;-webkit-text-size-adjust: none;">Copyright © 2015. RAS All rights reserved </span></td>  
         </tr> 
     

  
  
  </table>
  </td>
  </tr>
<tr>
  <td align="center">&nbsp;</td>
</tr>
</table>

</body>
</html>'; 

$mailer  = JFactory::getMailer();
$config  = JFactory::getConfig();
$subject = 'Welcome to Restaurant Association of Singapore';
$from    = $config->get('mailfrom');
$fromname = $config->get('fromname' ); 

$to = $data['email'];

$sender = array( 
    $from,
    $fromname
);

$mailer->isHTML(true);
$mailer->setSender($sender); 

$mailer->addRecipient($to);
$mailer->Encoding = 'base64';
$mailer->setSubject($subject);

$mailer->setBody($body);

$return = $mailer->Send();
 
 

}
	else
	{
			$emailBody = JText::sprintf(
			'COM_USERS_EMAIL_REGISTERED_WITH_ACTIVATION_BODY_NOPW',
			$data['name'],
			$data['sitename'],
			$data['activate'],
			$data['siteurl'],
			$data['username']
			);
	}
}
		else
		{
			$emailSubject = JText::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$data['name'],
				$data['sitename']
			);

			if ($sendpassword)
			{
				$emailBody = JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_BODY',
					$data['name'],
					$data['sitename'],
					$data['siteurl'],
					$data['username'],
					$data['password_clear']
				);
			}
			else
			{
				$emailBody = JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_BODY_NOPW',
					$data['name'],
					$data['sitename'],
					$data['siteurl']
				);
			}
		}
   
             
	// Send the registration email.
	//$return = JFactory::getMailer()->sendMail($data['mailfrom'], $data['fromname'], $data['email'], $emailSubject, $emailBody);
		 
          

		// Send Notification mail to administrators
		if (($params->get('useractivation') < 2) && ($params->get('mail_to_admin') == 1))
		{
			$emailSubject = JText::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$data['name'],
				$data['sitename']
			);

			$emailBodyAdmin = JText::sprintf(
				'COM_USERS_EMAIL_REGISTERED_NOTIFICATION_TO_ADMIN_BODY',
				$data['name'],
				$data['username'],
				$data['siteurl']
			);

			// Get all admin users
			$query->clear()
				->select($db->quoteName(array('name', 'email', 'sendEmail')))
				->from($db->quoteName('#__users'))
				->where($db->quoteName('sendEmail') . ' = ' . 1);

			$db->setQuery($query);

			try
			{
				$rows = $db->loadObjectList();
			}
			catch (RuntimeException $e)
			{
				$this->setError(JText::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);

				return false;
			}

    
	// Send mail to all superadministrators id
	foreach ($rows as $row)
	{
	$return = JFactory::getMailer()->sendMail($data['mailfrom'], $data['fromname'], $row->email, $emailSubject, $emailBodyAdmin);




				// Check for an error.
				if ($return !== true)
				{
					$this->setError(JText::_('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'));

					return false;
				}
			}
		}

		// Check for an error.
		if ($return !== true)
		{
			$this->setError(JText::_('COM_USERS_REGISTRATION_SEND_MAIL_FAILED'));

			// Send a system message to administrators receiving system mails
			$db = JFactory::getDbo();
			$query->clear()
				->select($db->quoteName(array('name', 'email', 'sendEmail', 'id')))
				->from($db->quoteName('#__users'))
				->where($db->quoteName('block') . ' = ' . (int) 0)
				->where($db->quoteName('sendEmail') . ' = ' . (int) 1);
			$db->setQuery($query);

			try
			{
				$sendEmail = $db->loadColumn();
			}
			catch (RuntimeException $e)
			{
				$this->setError(JText::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);

				return false;
			}

			if (count($sendEmail) > 0)
			{
				$jdate = new JDate;

				// Build the query to add the messages
				foreach ($sendEmail as $userid)
				{
					$values = array(
						$db->quote($userid),
						$db->quote($userid),
						$db->quote($jdate->toSql()),
						$db->quote(JText::_('COM_USERS_MAIL_SEND_FAILURE_SUBJECT')),
						$db->quote(JText::sprintf('COM_USERS_MAIL_SEND_FAILURE_BODY', $return, $data['username']))
					);
					$query->clear()
						->insert($db->quoteName('#__messages'))
						->columns($db->quoteName(array('user_id_from', 'user_id_to', 'date_time', 'subject', 'message')))
						->values(implode(',', $values));
					$db->setQuery($query);

					try
					{
						$db->execute();
					}
					catch (RuntimeException $e)
					{
						$this->setError(JText::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);

						return false;
					}
				}
			}

			return false;
		}

		if ($useractivation == 1)
		{
			return "useractivate";
		}
		elseif ($useractivation == 2)
		{
			return "adminactivate";
		}
		else
		{
			return $user->id;
		}
	}
}
 

 