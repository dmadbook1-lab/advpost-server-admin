<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('User_model');  

        // Agar login check karna hai
        if(!$this->session->userdata('email')) {
            redirect('welcome/index');
        }
    }

    // Show user list
    public function users() {
        $data['users'] = $this->User_model->get_all_users();
        $this->load->view('userlist', $data);
    }

    // Edit page
    public function edit($id) {
        $data['user'] = $this->User_model->get_user_by_id($id);
        $this->load->view('edit_user', $data);
    }

    // Update user
    public function update($id) {
        $formData = [
            'name'     => $this->input->post('name'),
            'email'    => $this->input->post('email'),
            'number'   => $this->input->post('number'),
            'password' => $this->input->post('password')
        ];

        $this->User_model->update_user($id, $formData);
        $this->session->set_flashdata('msg', 'User updated successfully!');
        redirect('users');
    }

    // Delete user
    public function delete($id) {
        $this->User_model->delete_user($id);
        $this->session->set_flashdata('msg', 'User deleted successfully!');
        redirect('users');
    }
}
