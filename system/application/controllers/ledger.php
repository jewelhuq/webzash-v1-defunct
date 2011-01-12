<?php

class Ledger extends Controller {

	function Ledger()
	{
		parent::Controller();
		$this->load->model('Ledger_model');
		$this->load->model('Group_model');
		return;
	}

	function index()
	{
		redirect('ledger/add');
		return;
	}

	function add()
	{
		$this->template->set('page_title', 'New Ledger');

		/* Check access */
		if ( ! check_access('create ledger'))
		{
			$this->messages->add('Permission denied.', 'error');
			redirect('account');
			return;
		}

		/* Check for account lock */
		if ($this->config->item('account_locked') == 1)
		{
			$this->messages->add('Account is locked.', 'error');
			redirect('account');
			return;
		}

		/* Form fields */
		$data['ledger_name'] = array(
			'name' => 'ledger_name',
			'id' => 'ledger_name',
			'maxlength' => '100',
			'size' => '40',
			'value' => '',
		);
		$data['ledger_group_id'] = $this->Group_model->get_ledger_groups();
		$data['op_balance'] = array(
			'name' => 'op_balance',
			'id' => 'op_balance',
			'maxlength' => '15',
			'size' => '15',
			'value' => '',
		);
		$data['ledger_group_active'] = 0;
		$data['op_balance_dc'] = "D";
		$data['ledger_type_cashbank'] = FALSE;

		/* Form validations */
		$this->form_validation->set_rules('ledger_name', 'Ledger name', 'trim|required|min_length[2]|max_length[100]|unique[ledgers.name]');
		$this->form_validation->set_rules('ledger_group_id', 'Parent group', 'trim|required|is_natural_no_zero');
		$this->form_validation->set_rules('op_balance', 'Opening balance', 'trim|currency');
		$this->form_validation->set_rules('op_balance_dc', 'Opening balance type', 'trim|required|is_dc');

		/* Re-populating form */
		if ($_POST)
		{
			$data['ledger_name']['value'] = $this->input->post('ledger_name', TRUE);
			$data['op_balance']['value'] = $this->input->post('op_balance', TRUE);
			$data['ledger_group_active'] = $this->input->post('ledger_group_id', TRUE);
			$data['op_balance_dc'] = $this->input->post('op_balance_dc', TRUE);
			$data['ledger_type_cashbank'] = $this->input->post('ledger_type_cashbank', TRUE);
		}

		if ($this->form_validation->run() == FALSE)
		{
			$this->messages->add(validation_errors(), 'error');
			$this->template->load('template', 'ledger/add', $data);
			return;
		}
		else
		{
			$data_name = $this->input->post('ledger_name', TRUE);
			$data_group_id = $this->input->post('ledger_group_id', TRUE);
			$data_op_balance = $this->input->post('op_balance', TRUE);
			$data_op_balance_dc = $this->input->post('op_balance_dc', TRUE);
			$data_ledger_type_cashbank_value = $this->input->post('ledger_type_cashbank', TRUE);
			$data_ledger_type_cashbank = "N";

			if ($data_group_id < 5)
			{
				$this->messages->add('Invalid parent group.', 'error');
				$this->template->load('template', 'ledger/add', $data);
				return;
			}

			/* Check if parent group id present */
			$this->db->select('id')->from('groups')->where('id', $data_group_id);
			if ($this->db->get()->num_rows() < 1)
			{
				$this->messages->add('Invalid Parent group.', 'error');
				$this->template->load('template', 'ledger/add', $data);
				return;
			}

			if ($data_ledger_type_cashbank_value == "1")
			{
				$data_ledger_type_cashbank = "B";
			}

			$this->db->trans_start();
			$insert_data = array(
				'name' => $data_name,
				'group_id' => $data_group_id,
				'op_balance' => $data_op_balance,
				'op_balance_dc' => $data_op_balance_dc,
				'type' => $data_ledger_type_cashbank,
			);
			if ( ! $this->db->insert('ledgers', $insert_data))
			{
				$this->db->trans_rollback();
				$this->messages->add('Error addding ' . $data_name . ' - Ledger A/C.', 'error');
				$this->logger->write_message("error", "Error adding Ledger A/C named " . $data_name);
				$this->template->load('template', 'group/add', $data);
				return;
			} else {
				$this->db->trans_complete();
				$this->messages->add('Added ' . $data_name . ' - Ledger A/C.', 'success');
				$this->logger->write_message("success", "Added Ledger A/C named " . $data_name);
				redirect('account');
				return;
			}
		}
		return;
	}

	function edit($id)
	{
		$this->template->set('page_title', 'Edit Ledger');

		/* Check access */
		if ( ! check_access('edit ledger'))
		{
			$this->messages->add('Permission denied.', 'error');
			redirect('account');
			return;
		}

		/* Check for account lock */
		if ($this->config->item('account_locked') == 1)
		{
			$this->messages->add('Account is locked.', 'error');
			redirect('account');
			return;
		}

		/* Checking for valid data */
		$id = $this->input->xss_clean($id);
		$id = (int)$id;
		if ($id < 1)
		{
			$this->messages->add('Invalid Ledger A/C.', 'error');
			redirect('account');
			return;
		}

		/* Loading current group */
		$this->db->from('ledgers')->where('id', $id);
		$ledger_data_q = $this->db->get();
		if ($ledger_data_q->num_rows() < 1)
		{
			$this->messages->add('Invalid Ledger A/C.', 'error');
			redirect('account');
			return;
		}
		$ledger_data = $ledger_data_q->row();

		/* Form fields */
		$data['ledger_name'] = array(
			'name' => 'ledger_name',
			'id' => 'ledger_name',
			'maxlength' => '100',
			'size' => '40',
			'value' => $ledger_data->name,
		);
		$data['ledger_group_id'] = $this->Group_model->get_ledger_groups();
		$data['op_balance'] = array(
			'name' => 'op_balance',
			'id' => 'op_balance',
			'maxlength' => '15',
			'size' => '15',
			'value' => $ledger_data->op_balance,
		);
		$data['ledger_group_active'] = $ledger_data->group_id;
		$data['op_balance_dc'] = $ledger_data->op_balance_dc;
		$data['ledger_id'] = $id;
		if ($ledger_data->type == "B")
			$data['ledger_type_cashbank'] = TRUE;
		else
			$data['ledger_type_cashbank'] = FALSE;

		/* Form validations */
		$this->form_validation->set_rules('ledger_name', 'Ledger name', 'trim|required|min_length[2]|max_length[100]|uniquewithid[ledgers.name.' . $id . ']');
		$this->form_validation->set_rules('ledger_group_id', 'Parent group', 'trim|required|is_natural_no_zero');
		$this->form_validation->set_rules('op_balance', 'Opening balance', 'trim|currency');
		$this->form_validation->set_rules('op_balance_dc', 'Opening balance type', 'trim|required|is_dc');

		/* Re-populating form */
		if ($_POST)
		{
			$data['ledger_name']['value'] = $this->input->post('ledger_name', TRUE);
			$data['ledger_group_active'] = $this->input->post('ledger_group_id', TRUE);
			$data['op_balance']['value'] = $this->input->post('op_balance', TRUE);
			$data['op_balance_dc'] = $this->input->post('op_balance_dc', TRUE);
			$data['ledger_type_cashbank'] = $this->input->post('ledger_type_cashbank', TRUE);
		}

		if ($this->form_validation->run() == FALSE)
		{
			$this->messages->add(validation_errors(), 'error');
			$this->template->load('template', 'ledger/edit', $data);
			return;
		}
		else
		{
			$data_name = $this->input->post('ledger_name', TRUE);
			$data_group_id = $this->input->post('ledger_group_id', TRUE);
			$data_op_balance = $this->input->post('op_balance', TRUE);
			$data_op_balance_dc = $this->input->post('op_balance_dc', TRUE);
			$data_id = $id;
			$data_ledger_type_cashbank_value = $this->input->post('ledger_type_cashbank', TRUE);
			$data_ledger_type_cashbank = "N";

			if ($data_group_id < 5)
			{
				$this->messages->add('Invalid parent group.', 'error');
				$this->template->load('template', 'ledger/edit', $data);
				return;
			}

			/* Check if parent group id present */
			$this->db->select('id')->from('groups')->where('id', $data_group_id);
			if ($this->db->get()->num_rows() < 1)
			{
				$this->messages->add('Invalid Parent group.', 'error');
				$this->template->load('template', 'ledger/edit', $data);
				return;
			}

			if ($data_ledger_type_cashbank_value == "1")
			{
				$data_ledger_type_cashbank = "B";
			}

			$this->db->trans_start();
			$update_data = array(
				'name' => $data_name,
				'group_id' => $data_group_id,
				'op_balance' => $data_op_balance,
				'op_balance_dc' => $data_op_balance_dc,
				'type' => $data_ledger_type_cashbank,
			);
			if ( ! $this->db->where('id', $data_id)->update('ledgers', $update_data))
			{
				$this->db->trans_rollback();
				$this->messages->add('Error updating ' . $data_name . ' - Ledger A/C.', 'error');
				$this->logger->write_message("error", "Error updating Ledger A/C named " . $data_name . " [id:" . $data_id . "]");
				$this->template->load('template', 'ledger/edit', $data);
				return;
			} else {
				$this->db->trans_complete();
				$this->messages->add('Updated ' . $data_name . ' - Ledger A/C.', 'success');
				$this->logger->write_message("success", "Updated Ledger A/C named " . $data_name . " [id:" . $data_id . "]");
				redirect('account');
				return;
			}
		}
		return;
	}

	function delete($id)
	{
		/* Check access */
		if ( ! check_access('delete ledger'))
		{
			$this->messages->add('Permission denied.', 'error');
			redirect('account');
			return;
		}

		/* Check for account lock */
		if ($this->config->item('account_locked') == 1)
		{
			$this->messages->add('Account is locked.', 'error');
			redirect('account');
			return;
		}

		/* Checking for valid data */
		$id = $this->input->xss_clean($id);
		$id = (int)$id;
		if ($id < 1)
		{
			$this->messages->add('Invalid Ledger A/C.', 'error');
			redirect('account');
			return;
		}
		$this->db->from('voucher_items')->where('ledger_id', $id);
		if ($this->db->get()->num_rows() > 0)
		{
			$this->messages->add('Cannot delete non-empty Ledger A/C.', 'error');
			redirect('account');
			return;
		}

		/* Get the ledger details */
		$this->db->from('ledgers')->where('id', $id);
		$ledger_q = $this->db->get();
		if ($ledger_q->num_rows() < 1)
		{
			$this->messages->add('Invalid Ledger A/C.', 'error');
			redirect('account');
			return;
		} else {
			$ledger_data = $ledger_q->row();
		}

		/* Deleting ledger */
		$this->db->trans_start();
		if ( ! $this->db->delete('ledgers', array('id' => $id)))
		{
			$this->db->trans_rollback();
			$this->messages->add('Error deleting ' . $ledger_data->name . ' - Ledger A/C.', 'error');
			$this->logger->write_message("error", "Error deleting Ledger A/C named " . $ledger_data->name . " [id:" . $id . "]");
			redirect('account');
			return;
		} else {
			$this->db->trans_complete();
			$this->messages->add('Deleted ' . $ledger_data->name . ' - Ledger A/C.', 'success');
			$this->logger->write_message("success", "Deleted Ledger A/C named " . $ledger_data->name . " [id:" . $id . "]");
			redirect('account');
			return;
		}
		return;
	}

	function balance($ledger_id = 0)
	{
		if ($ledger_id > 0)
			echo $this->Ledger_model->get_ledger_balance($ledger_id);
		else
			echo "";
		return;
	}
}

/* End of file ledger.php */
/* Location: ./system/application/controllers/ledger.php */
