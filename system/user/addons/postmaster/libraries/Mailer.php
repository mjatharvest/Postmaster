<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Mailer
 * *
 * @package		Postmaster
 * @subpackage	Library
 * @author		Justin Kimbrell
 * @copyright	Copyright (c) 2012, Objective HTML
 * @link 		http://www.objectivehtml.com/postmaster
 * @version		1.0.1
 * @build		20120831
 */

class Mailer {

	public $parcel;

	public function __construct($parcel)
	{
		
		ee()->load->library('email');

		if(version_compare(APP_VER, '2.6.0', '<'))
		{
			ee()->email->clear();
		}

		$config = array();

		if(isset($parcel->settings->ExpressionEngine))
		{
			foreach($parcel->settings->ExpressionEngine as $setting => $value)
			{
				$config[$setting] = $value;
			}
		}

		ee()->email->initialize($config);

		$this->to($parcel->to_email, $parcel->to_name);
		$this->from($parcel->from_email, $parcel->from_name);

		if(!empty($parcel->cc))
		{
			$this->cc($parcel->cc);
		}

		if(!empty($parcel->bcc))
		{
			$this->bcc($parcel->bcc);
		}

		ee()->email->subject($parcel->subject);

		/* Legacy - Handle HTML & plain text emails for legacy settings */
		if(isset($parcel->message) && !empty($parcel->message))
		{
			$this->message($parcel->message);
		}

		/* New in v1.4, send HTML emails via the API */
		if(isset($parcel->html_message) && !empty($parcel->html_message))
		{
			$this->message($parcel->html_message);
		}

		/* New in v1.4, send plain text emails via the API */
		if(isset($parcel->plain_message) && !empty($parcel->plain_message))
		{
			$this->alt_message($parcel->plain_message);
		}

		$this->parcel = $parcel;
	}

	public function to($email, $name = '')
	{
		ee()->email->to($email, $name);
	}

	public function from($email, $name = '')
	{
		ee()->email->from($email, $name);
	}

	public function cc($email, $name = '')
	{
		ee()->email->cc($email, $name);
	}

	public function bcc($email, $name = '')
	{
		ee()->email->bcc($email, $name);
	}

	public function subject($subject)
	{
		ee()->email->subject($subject);
	}

	public function message($message)
	{
		ee()->email->message($message);
	}

	public function alt_message($message)
	{
		ee()->email->set_alt_message($message);
	}

	public function send()
	{
		return ee()->email->send();
	}

	public function clear()
	{
		return ee()->email->clear();
	}

	public function print_debugger()
	{
		return ee()->email->print_debugger();
	}
}
