<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Cartthrob_subscription_rebilled_postmaster_hook extends Base_hook {
	
	protected $title = 'CartThrob Subscription Rebilled';
	
	protected $cart;
	
	public function __construct()
	{
		parent::__construct();
		
		if(isset(ee()->cartthrob))
		{
			$this->cart = ee()->cartthrob->cart;
		}
	}
	
	public function trigger($subscription = array())
	{		
		$member = ee()->postmaster_model->get_member($subscription['member_id'], 'member');
		
		return parent::send($subscription, $member);
	}
	
	public function post_process($vars = array())
	{
		$responses = $this->responses;
		
		if($this->end_script($responses))
		{
			$update = array('status' => 'hold');
			
			if ($error_message)
			{
				$update['error_message'] = $error_message;
			}
			
			if ($increment_rebill_attempts)
			{
				$update['rebill_attempts'] = $subscription['rebill_attempts'] + 1;
			}
			
			ee()->subscription_model->update($update, $subscription['id']);
		}
	}
}