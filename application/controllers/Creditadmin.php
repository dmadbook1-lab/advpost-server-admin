<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin: manage the credit system (prices, packages, assigning credits, ledger).
 * Admin role only.
 */
class Creditadmin extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
        $this->load->database();
        $this->load->helper(['url', 'form']);
        $this->load->model('Credit_model');
        date_default_timezone_set('Asia/Kolkata');

        if (!$this->session->userdata('is_login') || $this->session->userdata('role') !== 'Admin') {
            redirect('welcome/index');
            exit;
        }
    }

    public function index()
    {
        $type = (string) $this->input->get('type', true);
        $uid  = (int) $this->input->get('user', true);

        $data['stats']    = $this->Credit_model->stats();
        $data['costs']    = $this->Credit_model->get_costs();
        $data['video_enabled'] = $this->Credit_model->get_flag('video_enabled');
        $data['packages'] = $this->Credit_model->get_packages(false);
        $data['ledger']   = $this->Credit_model->get_ledger(500, $type, $uid);
        $data['filter_type'] = $type;
        $data['filter_user'] = $uid;
        $data['tab']      = (string) $this->input->get('tab', true) ?: 'overview';
        $this->load->view('admin_view/credits', $data);
    }

    /** JSON for the "assign credits" user picker. */
    public function find_users()
    {
        $rows = $this->Credit_model->search_users($this->input->get('q', true), 12);
        $out = [];
        foreach ($rows as $u) {
            $out[] = [
                'id' => (int) $u->id,
                'label' => trim(($u->first_name ?: 'User') . ' · ' . ($u->mobile ?: $u->email ?: '')) . ' (#' . $u->id . ')',
                'balance' => (int) $u->balance_referral_points,
            ];
        }
        header('Content-Type: application/json');
        echo json_encode($out);
    }

    public function save_costs()
    {
        foreach (['ai_photo', 'ai_video'] as $a) {
            $v = $this->input->post('cost_' . $a);
            if ($v !== null && $v !== '') { $this->Credit_model->set_cost($a, (int) $v); }
        }
        $this->Credit_model->set_flag('video_enabled', $this->input->post('video_enabled') ? 1 : 0);
        $this->flash('Credit prices and availability updated');
        redirect('creditadmin?tab=pricing');
    }

    public function save_package()
    {
        $id = (int) $this->input->post('id');
        $name = trim((string) $this->input->post('name', true));
        $credits = (int) $this->input->post('credits');
        $price = (float) $this->input->post('price_inr');
        if ($name === '' || $credits < 1 || $price < 1) {
            $this->flash('Name, credits (≥1) and price (≥ ₹1) are required', 'danger');
            redirect('creditadmin?tab=packages');
            return;
        }
        $this->Credit_model->save_package([
            'name' => $name, 'credits' => $credits,
            'bonus_credits' => (int) $this->input->post('bonus_credits'),
            'price_inr' => $price,
            'badge' => (string) $this->input->post('badge', true),
            'sort_order' => (int) $this->input->post('sort_order'),
            'is_active' => $this->input->post('is_active') ? 1 : 0,
        ], $id);
        $this->flash($id ? 'Package updated' : 'Package added');
        redirect('creditadmin?tab=packages');
    }

    public function delete_package()
    {
        $this->Credit_model->delete_package((int) $this->input->post('id'));
        $this->flash('Package hidden from the app');
        redirect('creditadmin?tab=packages');
    }

    /** Give credits to (or take credits from) any user. */
    public function assign()
    {
        $userId = (int) $this->input->post('user_id');
        $amount = (int) $this->input->post('credits');
        $mode   = $this->input->post('mode') === 'deduct' ? 'deduct' : 'grant';
        $note   = trim((string) $this->input->post('note', true));

        $user = $userId > 0 ? $this->db->where('id', $userId)->get('users')->row() : null;
        if (!$user || $amount < 1 || $amount > 100000) {
            $this->flash('Pick a user and enter credits between 1 and 100000', 'danger');
            redirect('creditadmin?tab=assign');
            return;
        }

        $adminId = (int) $this->session->userdata('id');
        $label = ($user->first_name ?: 'User') . ' (#' . $user->id . ')';
        if ($mode === 'grant') {
            $bal = $this->Credit_model->add($userId, $amount, 'admin_grant', null, $note ?: 'Added by admin', $adminId);
            $this->flash($bal === false ? 'Could not add credits' : "Added $amount credits to $label. New balance: $bal",
                         $bal === false ? 'danger' : 'success');
        } else {
            $bal = $this->Credit_model->remove($userId, $amount, 'admin_deduct', null, $note ?: 'Removed by admin', $adminId);
            $this->flash($bal === false ? "$label only has " . $this->Credit_model->get_balance($userId) . ' credits' : "Removed $amount credits from $label. New balance: $bal",
                         $bal === false ? 'danger' : 'success');
        }
        redirect('creditadmin?tab=assign');
    }

    private function flash($msg, $kind = 'success')
    {
        $this->session->set_flashdata('msg', $msg);
        $this->session->set_flashdata('msg_kind', $kind);
    }
}
