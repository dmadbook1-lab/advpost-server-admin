<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Credit system.
 *
 * Balance lives on users.balance_referral_points (existing column) together
 * with total_referral_points (everything ever received) and
 * used_referral_points (everything ever spent). Every change is written to
 * credit_transactions, so the admin can audit the whole history.
 *
 * All balance changes are atomic single UPDATE statements; a spend can never
 * take the balance below zero.
 */
class Credit_model extends CI_Model
{
    const TYPES = ['purchase', 'admin_grant', 'admin_deduct', 'spend', 'refund', 'bonus'];

    /** Default price list (credits per action). Editable by the admin. */
    const DEFAULT_COSTS = ['ai_photo' => 1, 'ai_video' => 5];

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    // ------------------------------------------------------------------
    // Schema
    // ------------------------------------------------------------------
    public function ensure_tables()
    {
        static $done = false;
        if ($done) { return; }
        $done = true;

        $this->db->query("CREATE TABLE IF NOT EXISTS credit_packages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            credits INT UNSIGNED NOT NULL,
            bonus_credits INT UNSIGNED NOT NULL DEFAULT 0,
            price_inr DECIMAL(10,2) NOT NULL,
            badge VARCHAR(40) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS credit_settings (
            k VARCHAR(60) NOT NULL PRIMARY KEY,
            v VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS credit_orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            package_id INT UNSIGNED NOT NULL,
            credits INT UNSIGNED NOT NULL,
            amount_paise INT UNSIGNED NOT NULL,
            razorpay_order_id VARCHAR(64) NOT NULL,
            razorpay_payment_id VARCHAR(64) NULL,
            status ENUM('created','paid','failed') NOT NULL DEFAULT 'created',
            created_at DATETIME NOT NULL,
            paid_at DATETIME NULL,
            UNIQUE KEY uq_rzp_order (razorpay_order_id),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS credit_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('purchase','admin_grant','admin_deduct','spend','refund','bonus') NOT NULL,
            credits INT NOT NULL,
            balance_after INT NOT NULL,
            ref VARCHAR(80) NULL,
            note VARCHAR(255) NULL,
            admin_id INT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_user (user_id),
            KEY idx_type (type),
            UNIQUE KEY uq_ref (type, ref)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if ((int) $this->db->count_all('credit_packages') === 0) {
            $now = date('Y-m-d H:i:s');
            $seed = [
                ['Starter', 10, 0, 99, null, 1],
                ['Popular', 30, 3, 249, 'Popular', 2],
                ['Pro', 100, 15, 699, 'Best value', 3],
            ];
            foreach ($seed as $s) {
                $this->db->insert('credit_packages', [
                    'name' => $s[0], 'credits' => $s[1], 'bonus_credits' => $s[2],
                    'price_inr' => $s[3], 'badge' => $s[4], 'sort_order' => $s[5],
                    'is_active' => 1, 'created_at' => $now,
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Pricing
    // ------------------------------------------------------------------
    public function get_costs()
    {
        $this->ensure_tables();
        $costs = self::DEFAULT_COSTS;
        foreach ($this->db->get('credit_settings')->result() as $r) {
            if (strpos($r->k, 'cost_') === 0) {
                $costs[substr($r->k, 5)] = (int) $r->v;
            }
        }
        return $costs;
    }

    public function set_cost($action, $credits)
    {
        $this->ensure_tables();
        $this->db->query(
            "INSERT INTO credit_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)",
            ['cost_' . $action, (string) max(0, (int) $credits)]
        );
    }

    /** Feature switches (stored in credit_settings as flag_<name>). AI Video starts switched off. */
    const DEFAULT_FLAGS = ['video_enabled' => 0];

    public function get_flag($name)
    {
        $this->ensure_tables();
        $row = $this->db->where('k', 'flag_' . $name)->get('credit_settings')->row();
        if ($row) { return (int) $row->v === 1; }
        return !empty(self::DEFAULT_FLAGS[$name]);
    }

    public function set_flag($name, $on)
    {
        $this->ensure_tables();
        $this->db->query(
            "INSERT INTO credit_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)",
            ['flag_' . $name, $on ? '1' : '0']
        );
    }

    public function cost_of($action)
    {
        $c = $this->get_costs();
        return isset($c[$action]) ? (int) $c[$action] : null;
    }

    // ------------------------------------------------------------------
    // Packages
    // ------------------------------------------------------------------
    public function get_packages($onlyActive = true)
    {
        $this->ensure_tables();
        if ($onlyActive) { $this->db->where('is_active', 1); }
        return $this->db->order_by('sort_order', 'ASC')->order_by('id', 'ASC')->get('credit_packages')->result();
    }

    public function get_package($id)
    {
        $this->ensure_tables();
        return $this->db->where('id', (int) $id)->get('credit_packages')->row();
    }

    public function save_package(array $d, $id = 0)
    {
        $this->ensure_tables();
        $row = [
            'name'          => mb_substr(trim($d['name']), 0, 80),
            'credits'       => max(1, (int) $d['credits']),
            'bonus_credits' => max(0, (int) ($d['bonus_credits'] ?? 0)),
            'price_inr'     => max(1, round((float) $d['price_inr'], 2)),
            'badge'         => ($d['badge'] ?? '') !== '' ? mb_substr(trim($d['badge']), 0, 40) : null,
            'sort_order'    => (int) ($d['sort_order'] ?? 0),
            'is_active'     => !empty($d['is_active']) ? 1 : 0,
        ];
        if ($id > 0) {
            $row['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', (int) $id)->update('credit_packages', $row);
            return (int) $id;
        }
        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('credit_packages', $row);
        return (int) $this->db->insert_id();
    }

    public function delete_package($id)
    {
        $this->ensure_tables();
        // Packages that were bought stay in credit_orders for the records, so
        // just hide them instead of deleting.
        $this->db->where('id', (int) $id)->update('credit_packages', ['is_active' => 0]);
    }

    // ------------------------------------------------------------------
    // Balance + ledger (atomic)
    // ------------------------------------------------------------------
    public function get_balance($userId)
    {
        $u = $this->db->select('balance_referral_points')->where('id', (int) $userId)->get('users')->row();
        return $u ? (int) $u->balance_referral_points : 0;
    }

    private function log_tx($userId, $type, $credits, $ref, $note, $adminId)
    {
        $this->db->insert('credit_transactions', [
            'user_id'       => (int) $userId,
            'type'          => $type,
            'credits'       => (int) $credits,
            'balance_after' => $this->get_balance($userId),
            'ref'           => $ref !== null && $ref !== '' ? mb_substr($ref, 0, 80) : null,
            'note'          => $note !== null && $note !== '' ? mb_substr($note, 0, 255) : null,
            'admin_id'      => $adminId ? (int) $adminId : null,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /** Adds credits (purchase / admin grant / refund / bonus). Returns new balance or false. */
    public function add($userId, $credits, $type, $ref = null, $note = null, $adminId = null)
    {
        $this->ensure_tables();
        $credits = (int) $credits;
        if ($credits <= 0 || !in_array($type, ['purchase', 'admin_grant', 'refund', 'bonus'], true)) {
            return false;
        }

        $this->db->trans_begin();

        // Idempotency: the same (type, ref) can only be applied once.
        if ($ref !== null && $ref !== '') {
            $dup = $this->db->where(['type' => $type, 'ref' => $ref])->get('credit_transactions')->row();
            if ($dup) {
                $this->db->trans_rollback();
                return (int) $dup->balance_after;
            }
        }

        $counted = in_array($type, ['refund'], true) ? 'used_referral_points = GREATEST(used_referral_points - ?, 0)'
                                                      : 'total_referral_points = total_referral_points + ?';
        $this->db->query(
            "UPDATE users SET balance_referral_points = balance_referral_points + ?, $counted WHERE id = ?",
            [$credits, $credits, (int) $userId]
        );
        if ($this->db->affected_rows() < 1 && !$this->db->where('id', (int) $userId)->count_all_results('users')) {
            $this->db->trans_rollback();
            return false;
        }
        $this->log_tx($userId, $type, $credits, $ref, $note, $adminId);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return $this->get_balance($userId);
    }

    /** Removes credits (spend / admin deduct). Returns new balance, or false when the balance is too low. */
    public function remove($userId, $credits, $type, $ref = null, $note = null, $adminId = null)
    {
        $this->ensure_tables();
        $credits = (int) $credits;
        if ($credits <= 0 || !in_array($type, ['spend', 'admin_deduct'], true)) {
            return false;
        }

        $this->db->trans_begin();

        if ($ref !== null && $ref !== '') {
            $dup = $this->db->where(['type' => $type, 'ref' => $ref])->get('credit_transactions')->row();
            if ($dup) {
                $this->db->trans_rollback();
                return (int) $dup->balance_after;
            }
        }

        $this->db->query(
            "UPDATE users SET balance_referral_points = balance_referral_points - ?,
                              used_referral_points = used_referral_points + ?
             WHERE id = ? AND balance_referral_points >= ?",
            [$credits, $credits, (int) $userId, $credits]
        );
        if ($this->db->affected_rows() < 1) {
            $this->db->trans_rollback();
            return false;
        }
        $this->log_tx($userId, $type, -$credits, $ref, $note, $adminId);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return $this->get_balance($userId);
    }

    /** Refunds a previous spend (once). Returns new balance or false. */
    public function refund_spend($userId, $ref)
    {
        $this->ensure_tables();
        $spend = $this->db->where(['type' => 'spend', 'ref' => $ref, 'user_id' => (int) $userId])
            ->get('credit_transactions')->row();
        if (!$spend) { return false; }
        return $this->add($userId, abs((int) $spend->credits), 'refund', $ref, 'Refund: generation failed');
    }

    // ------------------------------------------------------------------
    // Orders (Razorpay purchase)
    // ------------------------------------------------------------------
    public function create_order($userId, $pkg, $razorpayOrderId)
    {
        $this->ensure_tables();
        $this->db->insert('credit_orders', [
            'user_id'           => (int) $userId,
            'package_id'        => (int) $pkg->id,
            'credits'           => (int) $pkg->credits + (int) $pkg->bonus_credits,
            'amount_paise'      => (int) round($pkg->price_inr * 100),
            'razorpay_order_id' => $razorpayOrderId,
            'status'            => 'created',
            'created_at'        => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    public function get_order_by_rzp($razorpayOrderId)
    {
        $this->ensure_tables();
        return $this->db->where('razorpay_order_id', $razorpayOrderId)->get('credit_orders')->row();
    }

    /** Marks an order paid and credits the user exactly once. Returns new balance or false. */
    public function complete_order($order, $paymentId)
    {
        if (!$order) { return false; }
        if ($order->status === 'paid') { return $this->get_balance($order->user_id); }

        $balance = $this->add(
            $order->user_id, $order->credits, 'purchase',
            $order->razorpay_order_id, 'Purchased via Razorpay (' . $paymentId . ')'
        );
        if ($balance === false) { return false; }

        $this->db->where('id', $order->id)->update('credit_orders', [
            'status' => 'paid', 'razorpay_payment_id' => $paymentId, 'paid_at' => date('Y-m-d H:i:s'),
        ]);
        return $balance;
    }

    // ------------------------------------------------------------------
    // Reporting
    // ------------------------------------------------------------------
    public function get_history($userId, $limit = 50)
    {
        $this->ensure_tables();
        return $this->db->where('user_id', (int) $userId)
            ->order_by('id', 'DESC')->limit((int) $limit)->get('credit_transactions')->result();
    }

    public function get_ledger($limit = 500, $type = '', $userId = 0)
    {
        $this->ensure_tables();
        $this->db->select("t.*, u.first_name, u.mobile, u.email", false)
            ->from('credit_transactions t')
            ->join('users u', 'u.id = t.user_id', 'left');
        if ($type !== '' && in_array($type, self::TYPES, true)) { $this->db->where('t.type', $type); }
        if ($userId > 0) { $this->db->where('t.user_id', (int) $userId); }
        return $this->db->order_by('t.id', 'DESC')->limit((int) $limit)->get()->result();
    }

    public function stats()
    {
        $this->ensure_tables();
        $q = $this->db->query("SELECT
            COALESCE(SUM(balance_referral_points),0) AS in_circulation,
            COALESCE(SUM(total_referral_points),0)   AS ever_issued,
            COALESCE(SUM(used_referral_points),0)    AS ever_used
          FROM users")->row_array();
        $r = $this->db->query("SELECT COALESCE(SUM(amount_paise),0) AS paise, COUNT(*) AS n
                               FROM credit_orders WHERE status = 'paid'")->row_array();
        $q['revenue_inr'] = round($r['paise'] / 100, 2);
        $q['paid_orders'] = (int) $r['n'];
        return $q;
    }

    public function search_users($term, $limit = 15)
    {
        $term = trim((string) $term);
        $this->db->select('id, first_name, mobile, email, balance_referral_points')->from('users');
        if ($term !== '') {
            if (ctype_digit($term)) {
                $this->db->group_start()->where('id', (int) $term)->or_like('mobile', $term)->group_end();
            } else {
                $this->db->group_start()->like('first_name', $term)->or_like('email', $term)->group_end();
            }
        }
        return $this->db->order_by('id', 'DESC')->limit((int) $limit)->get()->result();
    }
}
