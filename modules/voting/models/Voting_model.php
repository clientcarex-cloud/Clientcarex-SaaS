<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Voting_model extends App_Model
{
    private $polls_table;
    private $questions_table;
    private $options_table;
    private $votes_table;

    private $devices_table;

    public function __construct()
    {
        parent::__construct();
        $this->polls_table     = db_prefix() . 'voting_polls';
        $this->questions_table = db_prefix() . 'voting_questions';
        $this->options_table   = db_prefix() . 'voting_options';
        $this->votes_table     = db_prefix() . 'voting_votes';
        $this->devices_table   = db_prefix() . 'voting_devices';
        $this->ensure_schema();
    }

    /**
     * Self-heal for installs activated before v1.1 — the devices table is
     * the newest object, so its absence means install.php must re-run
     * (install.php is fully idempotent).
     */
    public function ensure_schema()
    {
        if (!$this->db->table_exists($this->devices_table)) {
            require(__DIR__ . '/../install.php');
        }
    }

    /* ── Polls ─────────────────────────────────────────────────────────── */

    public function get_polls($id = null)
    {
        if ($id !== null) {
            $poll = $this->db->where('id', (int) $id)->get($this->polls_table)->row();
            if ($poll) {
                $poll->questions = $this->get_questions($poll->id);
            }

            return $poll;
        }

        return $this->db->query("SELECT p.*,
            (SELECT COUNT(*) FROM `{$this->questions_table}` q WHERE q.poll_id = p.id) AS question_count,
            (SELECT COUNT(*) FROM `{$this->votes_table}` v WHERE v.poll_id = p.id) AS vote_count,
            (SELECT COUNT(DISTINCT v.voter_token) FROM `{$this->votes_table}` v WHERE v.poll_id = p.id) AS voter_count
            FROM `{$this->polls_table}` p ORDER BY p.id DESC")->result();
    }

    public function get_summary()
    {
        return [
            'total_polls' => (int) $this->db->count_all($this->polls_table),
            'live_polls'  => (int) $this->db->where('status', 'live')->count_all_results($this->polls_table),
            'total_votes' => (int) $this->db->count_all($this->votes_table),
            'today_votes' => (int) $this->db->where('DATE(created_at)', date('Y-m-d'))->count_all_results($this->votes_table),
        ];
    }

    public function get_poll_by_code($code)
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        return $this->db->where('code', $code)->get($this->polls_table)->row();
    }

    public function add_poll($data)
    {
        $this->db->insert($this->polls_table, [
            'title'        => trim($data['title']),
            'description'  => isset($data['description']) ? trim($data['description']) : null,
            'code'          => $this->_generate_code(),
            'allow_revote'  => 0, // votes are final (feature retired 2026-07)
            'collect_names' => !empty($data['collect_names']) ? 1 : 0,
            'created_by'    => get_staff_user_id(),
        ]);
        $poll_id = $this->db->insert_id();

        if ($poll_id) {
            $this->_save_questions($poll_id, isset($data['questions']) ? $data['questions'] : []);
        }

        return $poll_id;
    }

    public function update_poll($id, $data)
    {
        $this->db->where('id', (int) $id)->update($this->polls_table, [
            'title'         => trim($data['title']),
            'description'   => isset($data['description']) ? trim($data['description']) : null,
            'allow_revote'  => 0, // votes are final (feature retired 2026-07)
            'collect_names' => !empty($data['collect_names']) ? 1 : 0,
            'updated_by'    => get_staff_user_id(),
        ]);

        $this->_save_questions((int) $id, isset($data['questions']) ? $data['questions'] : []);

        // Active question may have been removed by the edit
        $poll = $this->db->where('id', (int) $id)->get($this->polls_table)->row();
        if ($poll && $poll->active_question_id) {
            $exists = $this->db->where('id', $poll->active_question_id)
                ->where('poll_id', $poll->id)
                ->count_all_results($this->questions_table);
            if (!$exists) {
                $this->db->where('id', $poll->id)->update($this->polls_table, ['active_question_id' => null]);
            }
        }

        return true;
    }

    public function delete_poll($id)
    {
        $id = (int) $id;
        $question_ids = array_column($this->db->select('id')->where('poll_id', $id)->get($this->questions_table)->result_array(), 'id');

        if (count($question_ids)) {
            $this->db->where_in('question_id', $question_ids)->delete($this->options_table);
        }
        $this->db->where('poll_id', $id)->delete($this->votes_table);
        $this->db->where('poll_id', $id)->delete($this->devices_table);
        $this->db->where('poll_id', $id)->delete($this->questions_table);
        $this->db->where('id', $id)->delete($this->polls_table);

        return true;
    }

    /**
     * Sync questions/options from the builder while preserving ids that are
     * still present, so votes already cast survive an edit.
     * $questions = [{id?, question, options: [{id?, label}]}]
     */
    private function _save_questions($poll_id, $questions)
    {
        $keep_q = [];
        $sort   = 0;

        foreach ((array) $questions as $q) {
            $text = isset($q['question']) ? trim((string) $q['question']) : '';
            if ($text === '') {
                continue;
            }

            $qid = !empty($q['id']) ? (int) $q['id'] : 0;
            if ($qid) {
                $owned = $this->db->where('id', $qid)->where('poll_id', $poll_id)->count_all_results($this->questions_table);
                $qid   = $owned ? $qid : 0;
            }

            if ($qid) {
                $this->db->where('id', $qid)->update($this->questions_table, ['question' => $text, 'sort_order' => $sort]);
            } else {
                $this->db->insert($this->questions_table, ['poll_id' => $poll_id, 'question' => $text, 'sort_order' => $sort]);
                $qid = $this->db->insert_id();
            }
            $keep_q[] = $qid;
            $sort++;

            // ── Options ──
            $keep_o = [];
            $osort  = 0;
            foreach ((array) (isset($q['options']) ? $q['options'] : []) as $o) {
                $label = isset($o['label']) ? trim((string) $o['label']) : '';
                if ($label === '') {
                    continue;
                }

                $oid = !empty($o['id']) ? (int) $o['id'] : 0;
                if ($oid) {
                    $owned = $this->db->where('id', $oid)->where('question_id', $qid)->count_all_results($this->options_table);
                    $oid   = $owned ? $oid : 0;
                }

                if ($oid) {
                    $this->db->where('id', $oid)->update($this->options_table, ['label' => $label, 'sort_order' => $osort]);
                } else {
                    $this->db->insert($this->options_table, ['question_id' => $qid, 'label' => $label, 'sort_order' => $osort]);
                    $oid = $this->db->insert_id();
                }
                $keep_o[] = $oid;
                $osort++;
            }

            // Remove deleted options (+ their votes)
            $this->db->where('question_id', $qid);
            if (count($keep_o)) {
                $this->db->where_not_in('id', $keep_o);
            }
            $gone_o = array_column($this->db->get($this->options_table)->result_array(), 'id');
            if (count($gone_o)) {
                $this->db->where_in('id', $gone_o)->delete($this->options_table);
                $this->db->where_in('option_id', $gone_o)->delete($this->votes_table);
            }
        }

        // Remove deleted questions (+ options + votes)
        $this->db->where('poll_id', $poll_id);
        if (count($keep_q)) {
            $this->db->where_not_in('id', $keep_q);
        }
        $gone_q = array_column($this->db->get($this->questions_table)->result_array(), 'id');
        if (count($gone_q)) {
            $this->db->where_in('question_id', $gone_q)->delete($this->options_table);
            $this->db->where_in('question_id', $gone_q)->delete($this->votes_table);
            $this->db->where_in('id', $gone_q)->delete($this->questions_table);
        }
    }

    /* ── Questions / options / results ─────────────────────────────────── */

    public function get_questions($poll_id)
    {
        $questions = $this->db->where('poll_id', (int) $poll_id)
            ->order_by('sort_order', 'asc')->order_by('id', 'asc')
            ->get($this->questions_table)->result();

        foreach ($questions as $q) {
            $q->options = $this->db->where('question_id', $q->id)
                ->order_by('sort_order', 'asc')->order_by('id', 'asc')
                ->get($this->options_table)->result();
        }

        return $questions;
    }

    /** [question_id => ['total' => n, 'options' => [option_id => n]]] */
    public function get_vote_counts($poll_id)
    {
        $rows = $this->db->query(
            "SELECT question_id, option_id, COUNT(*) AS c FROM `{$this->votes_table}`
             WHERE poll_id = ? GROUP BY question_id, option_id",
            [(int) $poll_id]
        )->result();

        $out = [];
        foreach ($rows as $r) {
            if (!isset($out[$r->question_id])) {
                $out[$r->question_id] = ['total' => 0, 'options' => []];
            }
            $out[$r->question_id]['options'][$r->option_id] = (int) $r->c;
            $out[$r->question_id]['total'] += (int) $r->c;
        }

        return $out;
    }

    /* ── Live control ──────────────────────────────────────────────────── */

    public function set_status($poll_id, $status)
    {
        if (!in_array($status, ['draft', 'live', 'ended'], true)) {
            return false;
        }

        $update = ['status' => $status, 'updated_by' => get_staff_user_id()];

        // Going live with no active question → auto-activate the first one
        if ($status === 'live') {
            $poll = $this->db->where('id', (int) $poll_id)->get($this->polls_table)->row();
            if ($poll && !$poll->active_question_id) {
                $first = $this->db->where('poll_id', $poll->id)
                    ->order_by('sort_order', 'asc')->order_by('id', 'asc')
                    ->limit(1)->get($this->questions_table)->row();
                $update['active_question_id'] = $first ? $first->id : null;
            }
        }

        $this->db->where('id', (int) $poll_id)->update($this->polls_table, $update);

        return true;
    }

    /** $question_id = null → hold screen ("get ready") */
    public function set_active_question($poll_id, $question_id)
    {
        if ($question_id !== null) {
            $owned = $this->db->where('id', (int) $question_id)
                ->where('poll_id', (int) $poll_id)
                ->count_all_results($this->questions_table);
            if (!$owned) {
                return false;
            }
        }

        $this->db->where('id', (int) $poll_id)->update($this->polls_table, [
            'active_question_id' => $question_id ? (int) $question_id : null,
            'updated_by'         => get_staff_user_id(),
        ]);

        return true;
    }

    /** Activate the question after the current one; returns new id or null (no more) */
    public function next_question($poll)
    {
        $questions = $this->db->where('poll_id', $poll->id)
            ->order_by('sort_order', 'asc')->order_by('id', 'asc')
            ->get($this->questions_table)->result();

        if (!count($questions)) {
            return null;
        }

        $next = null;
        if (!$poll->active_question_id) {
            $next = $questions[0]->id;
        } else {
            $found = false;
            foreach ($questions as $q) {
                if ($found) {
                    $next = $q->id;
                    break;
                }
                if ((int) $q->id === (int) $poll->active_question_id) {
                    $found = true;
                }
            }
        }

        $this->set_active_question($poll->id, $next);

        return $next;
    }

    public function reset_votes($poll_id, $question_id = null)
    {
        $this->db->where('poll_id', (int) $poll_id);
        if ($question_id) {
            $this->db->where('question_id', (int) $question_id);
        }
        $this->db->delete($this->votes_table);

        return true;
    }

    /* ── Voting ────────────────────────────────────────────────────────── */

    /**
     * Cast an anonymous vote. Votes are FINAL — one per device per question,
     * never changeable. Returns [success, message?].
     * $voter_name is required (non-empty) when the poll collects names.
     */
    public function cast_vote($poll, $question_id, $option_id, $voter_token, $ip = null, $voter_name = '')
    {
        if ($poll->status !== 'live' || (int) $poll->active_question_id !== (int) $question_id) {
            return ['success' => false, 'message' => 'Voting is not open for this question.'];
        }

        $option = $this->db->where('id', (int) $option_id)
            ->where('question_id', (int) $question_id)
            ->get($this->options_table)->row();
        if (!$option) {
            return ['success' => false, 'message' => 'Invalid option.'];
        }

        $voter_name = trim(mb_substr(strip_tags((string) $voter_name), 0, 120));
        if (!empty($poll->collect_names) && $voter_name === '') {
            return ['success' => false, 'message' => 'Please enter your name to vote.', 'name_required' => true];
        }

        $existing = $this->db->where('question_id', (int) $question_id)
            ->where('voter_token', $voter_token)
            ->count_all_results($this->votes_table);

        if ($existing) {
            return ['success' => false, 'message' => 'You have already voted on this question.', 'already' => true];
        }

        // Unique key (question_id, voter_token) absorbs double-tap races
        $this->db->query(
            "INSERT IGNORE INTO `{$this->votes_table}` (poll_id, question_id, option_id, voter_token, voter_name, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)",
            [(int) $poll->id, (int) $question_id, (int) $option_id, $voter_token, $voter_name !== '' ? $voter_name : null, $ip]
        );

        return ['success' => true];
    }

    /**
     * Register / refresh a device that opened the voter link.
     * Powers the "devices joined" counter on the telecast screen.
     */
    public function touch_device($poll_id, $voter_token)
    {
        $this->db->query(
            "INSERT INTO `{$this->devices_table}` (poll_id, voter_token)
             VALUES (?, ?) ON DUPLICATE KEY UPDATE last_seen = NOW()",
            [(int) $poll_id, $voter_token]
        );
    }

    /* ── State payloads (polled via AJAX) ──────────────────────────────── */

    /**
     * Public page state. Only the active question is exposed while live;
     * the full summary is exposed once ended.
     */
    public function public_state($poll)
    {
        $counts = $this->get_vote_counts($poll->id);

        $state = [
            'status'  => $poll->status,
            'title'   => $poll->title,
            'active'  => null,
            'summary' => null,
            // audience stats — telecast screen only (ballot_state omits these)
            'devices' => $this->count_devices($poll->id),
            'votes'   => (int) $this->db->where('poll_id', $poll->id)->count_all_results($this->votes_table),
        ];

        if ($poll->status === 'live' && $poll->active_question_id) {
            $questions = $this->get_questions($poll->id);
            foreach ($questions as $i => $q) {
                if ((int) $q->id === (int) $poll->active_question_id) {
                    $state['active'] = $this->_question_payload($q, $counts, $i + 1, count($questions));
                    break;
                }
            }
        }

        if ($poll->status === 'ended') {
            $questions = $this->get_questions($poll->id);
            $summary   = [];
            foreach ($questions as $i => $q) {
                $summary[] = $this->_question_payload($q, $counts, $i + 1, count($questions));
            }
            $state['summary'] = $summary;
        }

        return $state;
    }

    /**
     * Voter ballot state — deliberately carries NO vote counts/percentages,
     * only what a voter needs: the active question and its option labels.
     */
    public function ballot_state($poll)
    {
        $state = [
            'status' => $poll->status,
            'title'  => $poll->title,
            'active' => null,
        ];

        if ($poll->status === 'live' && $poll->active_question_id) {
            $questions = $this->get_questions($poll->id);
            foreach ($questions as $i => $q) {
                if ((int) $q->id === (int) $poll->active_question_id) {
                    $options = [];
                    foreach ($q->options as $o) {
                        $options[] = ['id' => (int) $o->id, 'label' => $o->label];
                    }
                    $state['active'] = [
                        'id'       => (int) $q->id,
                        'number'   => $i + 1,
                        'of'       => count($questions),
                        'question' => $q->question,
                        'options'  => $options,
                    ];
                    break;
                }
            }
        }

        return $state;
    }

    /** Admin monitor state: every question with live counts (+ names if collected). */
    public function admin_state($poll)
    {
        $counts    = $this->get_vote_counts($poll->id);
        $questions = $this->get_questions($poll->id);
        $names     = !empty($poll->collect_names) ? $this->get_voter_names($poll->id) : [];

        $payload = [];
        foreach ($questions as $i => $q) {
            $item = $this->_question_payload($q, $counts, $i + 1, count($questions));
            if (!empty($poll->collect_names)) {
                $item['names'] = isset($names[$q->id]) ? $names[$q->id] : [];
            }
            $payload[] = $item;
        }

        $voters = (int) $this->db->query(
            "SELECT COUNT(DISTINCT voter_token) AS c FROM `{$this->votes_table}` WHERE poll_id = ?",
            [(int) $poll->id]
        )->row()->c;

        return [
            'status'    => $poll->status,
            'active_id' => $poll->active_question_id ? (int) $poll->active_question_id : null,
            'voters'    => $voters,
            'devices'   => $this->count_devices($poll->id),
            'questions' => $payload,
        ];
    }

    public function count_devices($poll_id)
    {
        return (int) $this->db->where('poll_id', (int) $poll_id)->count_all_results($this->devices_table);
    }

    /** [question_id => [{name, option}]] — only meaningful when collect_names is on */
    public function get_voter_names($poll_id)
    {
        $rows = $this->db->query(
            "SELECT v.question_id, v.voter_name, o.label
             FROM `{$this->votes_table}` v
             JOIN `{$this->options_table}` o ON o.id = v.option_id
             WHERE v.poll_id = ? AND v.voter_name IS NOT NULL AND v.voter_name != ''
             ORDER BY v.created_at ASC",
            [(int) $poll_id]
        )->result();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->question_id][] = ['name' => $r->voter_name, 'option' => $r->label];
        }

        return $out;
    }

    private function _question_payload($q, $counts, $number, $total_questions)
    {
        $qc      = isset($counts[$q->id]) ? $counts[$q->id] : ['total' => 0, 'options' => []];
        $options = [];
        foreach ($q->options as $o) {
            $votes     = isset($qc['options'][$o->id]) ? $qc['options'][$o->id] : 0;
            $options[] = [
                'id'      => (int) $o->id,
                'label'   => $o->label,
                'votes'   => $votes,
                'percent' => $qc['total'] ? round($votes * 100 / $qc['total']) : 0,
            ];
        }

        return [
            'id'       => (int) $q->id,
            'number'   => $number,
            'of'       => $total_questions,
            'question' => $q->question,
            'total'    => $qc['total'],
            'options'  => $options,
        ];
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    /**
     * Short, unambiguous, easy-to-type public code (no 0/O/1/I/L).
     * 4 chars ≈ 700k combinations; grows to 5+ on collision streaks.
     */
    private function _generate_code()
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $length   = 4;

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $exists = $this->db->where('code', $code)->count_all_results($this->polls_table);
            if (!$exists) {
                return $code;
            }
            if ($attempt > 0 && $attempt % 5 === 0) {
                $length++;
            }
        }

        return strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    }
}
