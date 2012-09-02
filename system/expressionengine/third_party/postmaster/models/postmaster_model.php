<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Postmaster_model extends CI_Model {
	
	public function __construct()
	{
		parent::__construct();
	}

	public function add_to_queue($parsed_object, $parcel, $date = FALSE)
	{
		$this->db->where(array(
			'parcel_id'     => $parcel->id,
			'channel_id'    => $parcel->channel_id,
			'author_id'     => $parcel->entry->author_id,
			'entry_id'      => $parcel->entry->entry_id,
		));

		$existing = $this->db->get('postmaster_queue');

		if($existing->num_rows() == 0)
		{
			if(!$date)
			{
				$date = $this->postmaster_lib->get_send_date($parsed_object);
			}

			$data = array(
				'parcel_id'     => $parcel->id,
				'channel_id'    => $parcel->channel_id,
				'author_id'     => $parcel->entry->author_id,
				'entry_id'      => $parcel->entry->entry_id,
				'gmt_date'      => $this->localize->now,
				'gmt_send_date' => $date,
				'service'       => $parcel->service,
				'to_name'       => $parsed_object->to_name,
				'to_email'      => $parsed_object->to_email,
				'from_name'     => $parsed_object->from_name,
				'from_email'    => $parsed_object->from_email,
				'cc'            => $parsed_object->cc,
				'bcc'           => $parsed_object->bcc,
				'subject'       => $parsed_object->subject,
				'message'       => $parsed_object->message,
				'send_every'    => $parsed_object->send_every
			);

			$this->db->insert('postmaster_queue', $data);
		}
	}
	
	public function blacklist($email)
	{
		$this->unsubscribe($email);

		if(!$this->is_blacklisted($email))
		{
			$this->db->insert('postmaster_blacklist', array(
				'gmt_date'   => $this->localize->now,
				'ip_address' => $this->input->ip_address(),
				'email'      => $email
			));
		}
	}

	public function convert_data($data, $index)
	{
		$array = array();

		foreach($data->result() as $row)
		{
			$array[$row->$index] = $row;
		}
		
		return $array;
	}

	public function convert_string($string, $pool)
	{
		$explode_array = explode('|', $string);

		$array  = array();
		
		foreach($explode_array as $index => $row)
		{
			if(isset($pool[$row]))
			{
				$array[] = $pool[$row];
			}
		}

		return $array;
	}
	
	public function create_hook($hook)
	{		
		$extension = array(
			'class'    => 'Postmaster_ext',
			'method'   => 'trigger_hook',
			'hook'     => !empty($hook['installed_hook']) ? $hook['installed_hook'] : $hook['user_defined_hook'],
			'priority' => $hook['priority'],
			'version'  => POSTMASTER_VERSION,
			'enabled'  => 'y'
		);
		
		$this->db->insert('extensions', $extension);
		
		$hook['extension_id'] = $this->db->insert_id();
		
		$this->db->insert('postmaster_hooks', $hook);
	}
	
	public function get_installed_hooks($hook, $json_decode = TRUE)
	{		
		$this->db->order_by('priority', 'asc');
		$this->db->where("(installed_hook != '' AND installed_hook = '$hook') OR (installed_hook = '' AND user_defined_hook = '$hook')", NULL, FALSE);
		
		$return = array();
		
		foreach($this->db->get('postmaster_hooks')->result_array() as $index => $hook)
		{
			$return[$index] = $hook;
			
			if($json_decode)
			{
				$return[$index]['settings'] = json_decode($hook['settings']);
			}
		}
		
		return $return;
	}

	public function edit_hook($id, $hook)
	{
		$entry = $this->channel_data->get('postmaster_hooks', array(
			'where' => array(
				'id' => $id
			)
		))->row_array();
		
		$extension = array(
			'class'    => 'Postmaster_ext',
			'method'   => 'trigger_hook',
			'hook'     => !empty($hook['installed_hook']) ? $hook['installed_hook'] : $hook['user_defined_hook'],
			'priority' => $hook['priority'],
			'version'  => POSTMASTER_VERSION,
			'enabled'  => 'y'
		);
		
		$this->db->where('extension_id', $entry['extension_id']);
		$this->db->update('extensions', $extension);
		
		$this->db->where('id', $id);
		$this->db->update('postmaster_hooks', $hook);
	}
	
	public function delete_hook($id)
	{
		$entry    = $this->get_hook($id)->row_array();
		
		$this->db->where('extension_id', $entry['extension_id']);
		$this->db->delete('extensions');
		
		$this->db->where('id', $id);
		$this->db->delete('postmaster_hooks');
	}
	
	public function create_parcel($parcel)
	{
		$this->EE->db->insert('postmaster_parcels', $parcel);
	}

	public function edit_parcel($parcel, $id)
	{
		$this->EE->db->where('id', $id);
		$this->EE->db->update('postmaster_parcels', $parcel);
	}
	
	public function delete_parcel($id)
	{
		$this->db->where('id', $id);
		$this->db->delete('postmaster_parcels');
	}
	
	public function duplicate_parcel($id)
	{
		$this->duplicate('postmaster_parcels', $id);
	}
	
	public function duplicate_hook($id)
	{
		$entry_id = $this->duplicate('postmaster_hooks', $id);
		$entry    = $this->get_hook($id)->row_array();
		
		$ext_entry_id = $this->duplicate('extensions', $entry['extension_id'], 'extension_id');
				
		$this->db->where('id', $entry_id);
		$this->db->update('postmaster_hooks', array(
			'extension_id' => $ext_entry_id
		));
	}
	
	public function duplicate($table, $id, $id_field = 'id', $debug = FALSE)
	{		
		$entry = $this->channel_data->get($table, array(
			'where' => array(
				$id_field => $id
			)
		))->row_array();
		
		unset($entry[$id_field]);

		$this->db->insert($table, $entry);
		
		return $this->db->insert_id();
	}
	
	public function get_editor_settings($key = FALSE)
	{
		$settings 		 = $this->db->get('postmaster_editor_settings')->result();
		$settings_array  = array();

		foreach($settings as $setting)
		{
			$settings_array[$setting->key] = $setting->value;
		}

		if($key)
		{
			return $settings_array[$key];
		}

		return $settings_array;
	}
	
	public function get_editor_settings_json()
	{
		$settings = $this->get_editor_settings();

		foreach($settings as $index => $setting)
		{
			if($setting == 'true' || $setting == 'false')
			{
				$settings[$index] = $setting == 'true' ? TRUE : FALSE;
			}

			if(preg_match("/\d/", $setting))
			{
				$settings[$index] = (int) $setting;
			}
		}

		return json_encode($settings);
	}

	public function get_email_queue($start = 'now', $end = FALSE)
	{
		if($start == 'now')
		{
			$start = $this->localize->now;
		}

		$this->db->where('gmt_send_date <=', $start);

		if($end)
		{
			$this->db->where('gmt_send_date >=', $end);
		}

		return $this->db->get('postmaster_queue');
	}
	
	public function get_entry($entry_id)
	{
		$entry = $this->channel_data->get_channel_entry($entry_id);
		
		if($entry->num_rows() == 1)
		{
			return $entry->row();
		}

		return FALSE;
	}
	
	public function get_parcel($id)
	{
		$this->db->where('id', $id);

		return $this->db->get('postmaster_parcels')->row();
	}

	public function get_parcels()
	{
		$parcels = $this->db->get('postmaster_parcels')->result();

		$channels      = $this->convert_data($this->channel_data->get_channels(), 'channel_id');
		$categories    = $this->convert_data($this->channel_data->get_categories(), 'cat_id');
		$member_groups = $this->convert_data($this->channel_data->get_member_groups(), 'group_id');
		
		foreach($parcels as $index => $parcel)
		{
			if(isset($channels[$parcel->channel_id]))
			{
				$parcels[$index]->channel_name 	 = $channels[$parcel->channel_id]->channel_name;
			}
			else
			{
				$parcels[$index]->channel_name = '<i>This channel no longer exists.</i>';
			}

			$parcels[$index]->trigger 		 = explode('|', $parcel->trigger);
			$parcels[$index]->categories  	 = $this->convert_string($parcel->categories, $categories);
			$parcels[$index]->member_groups  = $this->convert_string($parcel->member_groups, $member_groups);
			$parcels[$index]->statuses  	 = $parcel->statuses != NULL ? explode('|', $parcel->statuses) : array();
			$parcels[$index]->settings 		 = json_decode($parcel->settings);
			$parcels[$index]->edit_url		 = $this->postmaster_lib->cp_url('edit_parcel') . '&id='.$parcel->id;
			$parcels[$index]->duplicate_url	 = $this->postmaster_lib->cp_url('duplicate_parcel_action') . '&id='.$parcel->id.'&return=index';
			$parcels[$index]->delete_url	 = $this->postmaster_lib->cp_url('delete_parcel_action') . '&id='.$parcel->id.'&return=index';
		}

		return $parcels;
	}
	
	public function get_hook($id)
	{
		$this->db->where('id', $id);
		
		return $this->db->get('postmaster_hooks');
	}
	
	public function get_hooks()
	{
		return $this->db->get('postmaster_hooks');
	}
	
	public function is_blacklisted($email)
	{
		$this->db->where('email', $email);
		$existing = $this->db->get('postmaster_blacklist');

		if($existing->num_rows() == 0)
		{
			return FALSE;
		}

		return TRUE;
	}
	
	public function remove_from_queue($id)
	{
		$this->db->where('id', $id);
		$this->db->delete('postmaster_queue');
	}

	public function save_editor_settings($settings)
	{
		foreach($settings as $key => $value)
		{
			$this->db->where('key', $key);
			$this->db->update('postmaster_editor_settings', array(
				'value' => $value
			));
		}
	}

	public function save_response($response)
	{
		if(is_object($response->parcel))
		{
			$response->parcel = json_encode($response->parcel);
		}

		$this->db->insert('postmaster_mailbox', (array) $response);
	}

	public function unsubscribe($email)
	{
		$this->db->where('to_email', $email);
		$this->db->delete('postmaster_queue');
	}
}