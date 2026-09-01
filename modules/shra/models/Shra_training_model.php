<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * SHRA Self-Training — course content, per-user progress and quizzes.
 *
 * Progress is always keyed on the logged-in staff member, so two people working
 * the same desk never see each other's numbers. Nothing here is cached across
 * requests: the course is small and it has to reflect an edit immediately.
 */
class Shra_training_model extends App_Model
{
    private $t_mod;
    private $t_les;
    private $t_qs;
    private $t_prog;
    private $t_att;

    public function __construct()
    {
        parent::__construct();
        $p            = db_prefix();
        $this->t_mod  = $p . 'shra_training_modules';
        $this->t_les  = $p . 'shra_training_lessons';
        $this->t_qs   = $p . 'shra_training_questions';
        $this->t_prog = $p . 'shra_training_progress';
        $this->t_att  = $p . 'shra_training_attempts';
    }

    /** The tables land with the schema self-heal; every read guards on this. */
    public function ready()
    {
        return $this->db->table_exists($this->t_mod);
    }

    /* ═══════════════════════ Content ═══════════════════════ */

    public function modules($active_only = true)
    {
        if (!$this->ready()) {
            return [];
        }
        if ($active_only) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('sort_order', 'ASC')->order_by('id', 'ASC')->get($this->t_mod)->result();
    }

    public function module($id)
    {
        if (!$this->ready()) {
            return null;
        }

        return $this->db->where('id', (int) $id)->get($this->t_mod)->row();
    }

    public function module_by_slug($slug)
    {
        if (!$this->ready()) {
            return null;
        }

        return $this->db->where('slug', $slug)->get($this->t_mod)->row();
    }

    public function lessons($module_id, $active_only = true)
    {
        if (!$this->ready()) {
            return [];
        }
        $this->db->where('module_id', (int) $module_id);
        if ($active_only) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('sort_order', 'ASC')->order_by('id', 'ASC')->get($this->t_les)->result();
    }

    public function lesson($id)
    {
        if (!$this->ready()) {
            return null;
        }

        return $this->db->where('id', (int) $id)->get($this->t_les)->row();
    }

    public function questions($module_id, $active_only = true)
    {
        if (!$this->ready()) {
            return [];
        }
        $this->db->where('module_id', (int) $module_id);
        if ($active_only) {
            $this->db->where('active', 1);
        }
        $rows = $this->db->order_by('sort_order', 'ASC')->order_by('id', 'ASC')->get($this->t_qs)->result();
        foreach ($rows as $r) {
            $r->options_arr = $this->options_of($r);
        }

        return $rows;
    }

    private function options_of($q)
    {
        $o = json_decode((string) $q->options, true);

        return is_array($o) ? array_values(array_map('strval', $o)) : [];
    }

    /* ═══════════════════════ Per-user progress ═══════════════════════ */

    /**
     * Everything one staff member has done, per module, in a single pass —
     * the training home renders straight off this.
     *
     * @return array module_id => ['lessons'=>int,'done'=>int,'percent'=>int,'best'=>int,'passed'=>bool,'attempts'=>int,'complete'=>bool]
     */
    public function module_stats($staff_id)
    {
        $out = [];
        if (!$this->ready()) {
            return $out;
        }
        $staff_id = (int) $staff_id;

        foreach ($this->modules(true) as $m) {
            $out[(int) $m->id] = [
                'lessons'  => 0,
                'done'     => 0,
                'quiz'     => 0,
                'percent'  => 0,
                'best'     => 0,
                'passed'   => false,
                'attempts' => 0,
                'complete' => false,
                'started'  => false,
            ];
        }
        if (!count($out)) {
            return $out;
        }
        $ids = array_keys($out);

        // Lesson counts
        $rows = $this->db->select('module_id, COUNT(*) AS c')->where('active', 1)
            ->where_in('module_id', $ids)->group_by('module_id')->get($this->t_les)->result();
        foreach ($rows as $r) {
            $out[(int) $r->module_id]['lessons'] = (int) $r->c;
        }

        // Question counts (a module with no questions has no quiz gate)
        $rows = $this->db->select('module_id, COUNT(*) AS c')->where('active', 1)
            ->where_in('module_id', $ids)->group_by('module_id')->get($this->t_qs)->result();
        foreach ($rows as $r) {
            $out[(int) $r->module_id]['quiz'] = (int) $r->c;
        }

        // Lessons this person has finished. Joined to the lesson so a deleted or
        // deactivated lesson stops counting towards their total straight away.
        $rows = $this->db->query(
            "SELECT pr.module_id, COUNT(*) AS c
               FROM {$this->t_prog} pr
               JOIN {$this->t_les} l ON l.id = pr.lesson_id AND l.active = 1
              WHERE pr.staff_id = ? AND pr.module_id IN (" . implode(',', array_map('intval', $ids)) . ")
              GROUP BY pr.module_id",
            [$staff_id]
        )->result();
        foreach ($rows as $r) {
            if (isset($out[(int) $r->module_id])) {
                $out[(int) $r->module_id]['done'] = (int) $r->c;
            }
        }

        // Best quiz score + attempt count
        $rows = $this->db->query(
            "SELECT module_id, MAX(percent) AS best, MAX(passed) AS passed, COUNT(*) AS attempts
               FROM {$this->t_att}
              WHERE staff_id = ? AND module_id IN (" . implode(',', array_map('intval', $ids)) . ")
              GROUP BY module_id",
            [$staff_id]
        )->result();
        foreach ($rows as $r) {
            $k = (int) $r->module_id;
            if (!isset($out[$k])) {
                continue;
            }
            $out[$k]['best']     = (int) $r->best;
            $out[$k]['passed']   = (int) $r->passed === 1;
            $out[$k]['attempts'] = (int) $r->attempts;
        }

        foreach ($out as $k => &$s) {
            // Reading is 70% of a module, passing its quiz the other 30% — so a
            // module never sits at 100% with the quiz still untouched.
            $read       = $s['lessons'] ? min(1, $s['done'] / $s['lessons']) : 1;
            $s['percent'] = $s['quiz']
                ? (int) round($read * 70 + ($s['passed'] ? 30 : 0))
                : (int) round($read * 100);
            $s['complete'] = $s['lessons'] === $s['done'] && ($s['quiz'] === 0 || $s['passed']);
            $s['started']  = $s['done'] > 0 || $s['attempts'] > 0;
            // The quiz unlocks once the reading is done — the questions assume it.
            $s['quiz_open'] = $s['quiz'] > 0 && $s['done'] >= $s['lessons'];
        }
        unset($s);

        return $out;
    }

    /** Which lessons of a module this person has ticked off: lesson_id => datetime. */
    public function done_lessons($staff_id, $module_id)
    {
        if (!$this->ready()) {
            return [];
        }
        $rows = $this->db->select('lesson_id, completed_at')
            ->where('staff_id', (int) $staff_id)->where('module_id', (int) $module_id)
            ->get($this->t_prog)->result();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->lesson_id] = $r->completed_at;
        }

        return $out;
    }

    /**
     * Course-wide numbers for the dashboard card and the training header.
     */
    public function overall($staff_id)
    {
        $stats = $this->module_stats($staff_id);
        $mods  = $this->modules(true);
        $by_id = [];
        foreach ($mods as $m) {
            $by_id[(int) $m->id] = $m;
        }

        $o = [
            'modules'         => count($stats),
            'modules_done'    => 0,
            'modules_started' => 0,
            'lessons'         => 0,
            'lessons_done'    => 0,
            'quizzes'         => 0,
            'quizzes_passed'  => 0,
            'perfect_quizzes' => 0,
            'percent'         => 0,
            'passed_slugs'    => [],
            'next'            => null,     // the module to nudge them towards
            'streak'          => $this->streak($staff_id),
            'last_at'         => null,
        ];

        $sum  = 0;
        $open = [];
        foreach ($stats as $id => $s) {
            $sum += $s['percent'];
            $o['lessons']      += $s['lessons'];
            $o['lessons_done'] += $s['done'];
            if ($s['quiz']) {
                $o['quizzes']++;
            }
            if ($s['passed']) {
                $o['quizzes_passed']++;
                if (isset($by_id[$id])) {
                    $o['passed_slugs'][] = $by_id[$id]->slug;
                }
            }
            if ($s['best'] >= 100) {
                $o['perfect_quizzes']++;
            }
            if ($s['complete']) {
                $o['modules_done']++;
            }
            if ($s['started']) {
                $o['modules_started']++;
            }
            // Remember every unfinished module in course order — the nudge is
            // picked from these once the loop has seen them all.
            if (!$s['complete'] && isset($by_id[$id])) {
                $open[] = ['module' => $by_id[$id], 'started' => $s['started'], 'stats' => $s];
            }
        }
        $o['percent'] = $o['modules'] ? (int) round($sum / $o['modules']) : 0;

        // Nudge towards something already begun; otherwise the next unopened module.
        foreach ($open as $cand) {
            if ($cand['started']) {
                $o['next'] = $cand;
                break;
            }
        }
        if ($o['next'] === null && count($open)) {
            $o['next'] = $open[0];
        }

        if ($this->ready()) {
            $row = $this->db->query(
                "SELECT MAX(d) AS d FROM (
                    SELECT MAX(completed_at) AS d FROM {$this->t_prog} WHERE staff_id = ?
                    UNION ALL
                    SELECT MAX(created_at)  AS d FROM {$this->t_att}  WHERE staff_id = ?
                 ) x",
                [(int) $staff_id, (int) $staff_id]
            )->row();
            $o['last_at'] = $row ? $row->d : null;
        }

        return $o;
    }

    /**
     * Consecutive days (ending today or yesterday) on which this person did
     * something. Purely a motivator — nothing depends on it.
     */
    public function streak($staff_id)
    {
        if (!$this->ready()) {
            return 0;
        }
        $rows = $this->db->query(
            "SELECT DISTINCT d FROM (
                SELECT DATE(completed_at) AS d FROM {$this->t_prog} WHERE staff_id = ?
                UNION
                SELECT DATE(created_at)   AS d FROM {$this->t_att}  WHERE staff_id = ?
             ) x ORDER BY d DESC LIMIT 90",
            [(int) $staff_id, (int) $staff_id]
        )->result();
        if (!count($rows)) {
            return 0;
        }

        // Anchor on the app's today, never the DB server's — the two can differ.
        $today  = date('Y-m-d');
        $first  = $rows[0]->d;
        $cursor = $first === $today ? $today : date('Y-m-d', strtotime($today . ' -1 day'));
        if ($first !== $cursor) {
            return 0;
        }

        $have = [];
        foreach ($rows as $r) {
            $have[$r->d] = true;
        }
        $n = 0;
        while (isset($have[$cursor])) {
            $n++;
            $cursor = date('Y-m-d', strtotime($cursor . ' -1 day'));
        }

        return $n;
    }

    /** Tick a lesson off for this person. Idempotent — re-reading is not re-counting. */
    public function complete_lesson($staff_id, $lesson_id, $seconds = 0)
    {
        if (!$this->ready()) {
            return false;
        }
        $l = $this->lesson($lesson_id);
        if (!$l) {
            return false;
        }
        $staff_id = (int) $staff_id;

        $existing = $this->db->where('staff_id', $staff_id)->where('lesson_id', (int) $l->id)
            ->get($this->t_prog)->row();
        if ($existing) {
            if ((int) $seconds > 0) {
                $this->db->where('id', $existing->id)->update($this->t_prog, ['seconds' => (int) $existing->seconds + (int) $seconds]);
            }

            return true;
        }

        $this->db->insert($this->t_prog, [
            'staff_id'     => $staff_id,
            'module_id'    => (int) $l->module_id,
            'lesson_id'    => (int) $l->id,
            'seconds'      => max(0, (int) $seconds),
            // PHP time, never NOW() — the app timezone and the DB server's differ.
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function uncomplete_lesson($staff_id, $lesson_id)
    {
        if (!$this->ready()) {
            return false;
        }
        $this->db->where('staff_id', (int) $staff_id)->where('lesson_id', (int) $lesson_id)->delete($this->t_prog);

        return true;
    }

    /* ═══════════════════════ Quiz ═══════════════════════ */

    /**
     * Draw an attempt: a shuffled subset of the module's questions, with the
     * answer options shuffled too, so a quiz cannot be passed by memorising
     * "always the third one". The correct index is NEVER sent to the browser.
     *
     * @return array ['questions'=>[['id','question','options'=>[['i','text']]]], 'pass'=>int]
     */
    public function draw_quiz($module_id)
    {
        $m  = $this->module($module_id);
        $qs = $this->questions($module_id);
        if (!$m || !count($qs)) {
            return null;
        }

        shuffle($qs);
        $take = (int) $m->quiz_count;
        if ($take > 0 && $take < count($qs)) {
            $qs = array_slice($qs, 0, $take);
        }

        $out = [];
        foreach ($qs as $q) {
            $opts = $q->options_arr;
            if (count($opts) < 2) {
                continue;
            }
            $idx = range(0, count($opts) - 1);
            shuffle($idx);
            $shown = [];
            foreach ($idx as $i) {
                $shown[] = ['i' => $i, 'text' => $opts[$i]];
            }
            $out[] = ['id' => (int) $q->id, 'question' => $q->question, 'options' => $shown];
        }

        return count($out) ? ['questions' => $out, 'pass' => (int) $m->pass_percent] : null;
    }

    /**
     * Grade an attempt. $answers is question_id => picked option index, exactly
     * as drawn — the grading re-reads the correct index from the database, so a
     * doctored payload cannot award itself a pass.
     */
    public function grade($staff_id, $module_id, array $answers, $seconds = 0)
    {
        $m = $this->module($module_id);
        if (!$m) {
            return null;
        }
        $ids = array_map('intval', array_keys($answers));
        if (!count($ids)) {
            return null;
        }

        $rows = $this->db->where('module_id', (int) $m->id)->where_in('id', $ids)->get($this->t_qs)->result();
        if (!count($rows)) {
            return null;
        }

        // The drawn set is not held server-side, so the one thing worth checking is
        // that a full attempt came back: without this, a doctored payload could
        // answer one easy question and bank 100% for the module.
        $pool     = (int) $this->db->where('module_id', (int) $m->id)->where('active', 1)->count_all_results($this->t_qs);
        $expected = (int) $m->quiz_count > 0 ? min((int) $m->quiz_count, $pool) : $pool;
        if (count($rows) < $expected) {
            return 'incomplete';
        }

        $detail = [];
        $ok     = 0;
        foreach ($rows as $q) {
            $opts   = $this->options_of($q);
            $picked = isset($answers[$q->id]) ? (int) $answers[$q->id] : -1;
            $right  = $picked === (int) $q->correct;
            if ($right) {
                $ok++;
            }
            $detail[] = [
                'q'           => (int) $q->id,
                'question'    => $q->question,
                'picked'      => $picked,
                'picked_text' => $opts[$picked] ?? '',
                'correct'     => (int) $q->correct,
                'answer'      => $opts[(int) $q->correct] ?? '',
                'right'       => $right,
                'explanation' => (string) $q->explanation,
            ];
        }

        $total   = count($rows);
        $percent = (int) round($ok / max(1, $total) * 100);
        $passed  = $percent >= (int) $m->pass_percent;

        $this->db->insert($this->t_att, [
            'staff_id'   => (int) $staff_id,
            'module_id'  => (int) $m->id,
            'total'      => $total,
            'correct'    => $ok,
            'percent'    => $percent,
            'passed'     => $passed ? 1 : 0,
            'answers'    => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'seconds'    => max(0, (int) $seconds),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $best = (int) $this->db->select_max('percent', 'p')->where('staff_id', (int) $staff_id)
            ->where('module_id', (int) $m->id)->get($this->t_att)->row()->p;

        return [
            'total'      => $total,
            'correct'    => $ok,
            'percent'    => $percent,
            'pass'       => (int) $m->pass_percent,
            'passed'     => $passed,
            'best'       => $best,
            'first_pass' => $passed && (int) $this->db->where('staff_id', (int) $staff_id)
                ->where('module_id', (int) $m->id)->where('passed', 1)->count_all_results($this->t_att) === 1,
            'detail'     => $detail,
        ];
    }

    public function attempts($staff_id, $module_id = null, $limit = 10)
    {
        if (!$this->ready()) {
            return [];
        }
        $this->db->where('staff_id', (int) $staff_id);
        if ($module_id !== null) {
            $this->db->where('module_id', (int) $module_id);
        }

        return $this->db->order_by('id', 'DESC')->limit((int) $limit)->get($this->t_att)->result();
    }

    /** Start this module over — used by "Retake from scratch". */
    public function reset_module($staff_id, $module_id)
    {
        if (!$this->ready()) {
            return false;
        }
        $this->db->where('staff_id', (int) $staff_id)->where('module_id', (int) $module_id)->delete($this->t_prog);
        $this->db->where('staff_id', (int) $staff_id)->where('module_id', (int) $module_id)->delete($this->t_att);

        return true;
    }

    /* ═══════════════════════ Team view ═══════════════════════ */

    /**
     * Where the whole desk stands. Managers use it to see who has not started;
     * everybody else sees it as a friendly leaderboard.
     */
    public function leaderboard($limit = 25)
    {
        if (!$this->ready()) {
            return [];
        }
        $staff = shra_lead_agents(true);
        $out   = [];
        foreach ($staff as $s) {
            $o = $this->overall((int) $s->staffid);
            $out[] = (object) [
                'staffid'      => (int) $s->staffid,
                'name'         => $s->full_name,
                'percent'      => $o['percent'],
                'modules_done' => $o['modules_done'],
                'modules'      => $o['modules'],
                'quizzes'      => $o['quizzes_passed'],
                'streak'       => $o['streak'],
                'last_at'      => $o['last_at'],
            ];
        }
        usort($out, function ($a, $b) {
            return $b->percent <=> $a->percent ?: strcasecmp($a->name, $b->name);
        });

        return array_slice($out, 0, (int) $limit);
    }

    /* ═══════════════════════ Authoring (admin) ═══════════════════════ */

    public function save_module(array $in, $id = null)
    {
        $data = [
            'title'        => trim((string) ($in['title'] ?? '')),
            'emoji'        => trim((string) ($in['emoji'] ?? '')) ?: '📘',
            'icon'         => trim((string) ($in['icon'] ?? '')) ?: 'fa-solid fa-book-open',
            'tagline'      => trim((string) ($in['tagline'] ?? '')),
            'intro'        => (string) ($in['intro'] ?? ''),
            'pass_percent' => max(0, min(100, (int) ($in['pass_percent'] ?? 70))),
            'quiz_count'   => max(0, (int) ($in['quiz_count'] ?? 0)),
            'sort_order'   => (int) ($in['sort_order'] ?? 0),
            'active'       => !empty($in['active']) ? 1 : 0,
        ];
        if ($data['title'] === '') {
            return 'Give the module a title.';
        }

        if ($id) {
            $this->db->where('id', (int) $id)->update($this->t_mod, $data);

            return (int) $id;
        }

        $slug = $this->unique_slug($data['title']);
        $data['slug'] = $slug;
        if (!$data['sort_order']) {
            $max = $this->db->select_max('sort_order', 'm')->get($this->t_mod)->row();
            $data['sort_order'] = (int) ($max->m ?? 0) + 10;
        }
        $this->db->insert($this->t_mod, $data);

        return (int) $this->db->insert_id();
    }

    private function unique_slug($title)
    {
        $base = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($title))), '-');
        $base = $base !== '' ? substr($base, 0, 50) : 'module';
        $slug = $base;
        $n    = 2;
        while ($this->db->where('slug', $slug)->get($this->t_mod)->row()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    public function delete_module($id)
    {
        if (!$this->ready()) {
            return false;
        }
        $id = (int) $id;
        $this->db->where('module_id', $id)->delete($this->t_les);
        $this->db->where('module_id', $id)->delete($this->t_qs);
        $this->db->where('module_id', $id)->delete($this->t_prog);
        $this->db->where('module_id', $id)->delete($this->t_att);
        $this->db->where('id', $id)->delete($this->t_mod);

        return true;
    }

    public function save_lesson(array $in, $id = null)
    {
        $data = [
            'title'      => trim((string) ($in['title'] ?? '')),
            'emoji'      => trim((string) ($in['emoji'] ?? '')) ?: '📖',
            'body'       => (string) ($in['body'] ?? ''),
            'takeaway'   => trim((string) ($in['takeaway'] ?? '')),
            'sort_order' => (int) ($in['sort_order'] ?? 0),
            'active'     => !empty($in['active']) ? 1 : 0,
        ];
        if ($data['title'] === '') {
            return 'Give the lesson a title.';
        }

        if ($id) {
            $this->db->where('id', (int) $id)->update($this->t_les, $data);

            return (int) $id;
        }

        $data['module_id'] = (int) ($in['module_id'] ?? 0);
        if (!$data['module_id']) {
            return 'Pick a module for this lesson.';
        }
        if (!$data['sort_order']) {
            $max = $this->db->select_max('sort_order', 'm')->where('module_id', $data['module_id'])->get($this->t_les)->row();
            $data['sort_order'] = (int) ($max->m ?? 0) + 10;
        }
        $this->db->insert($this->t_les, $data);

        return (int) $this->db->insert_id();
    }

    public function delete_lesson($id)
    {
        if (!$this->ready()) {
            return false;
        }
        $this->db->where('lesson_id', (int) $id)->delete($this->t_prog);
        $this->db->where('id', (int) $id)->delete($this->t_les);

        return true;
    }

    public function save_question(array $in, $id = null)
    {
        // Blank answer boxes are dropped, so the ticked index has to be carried
        // across the gap — otherwise emptying box 2 silently promotes box 4's
        // answer to "correct".
        $raw     = array_map('trim', (array) ($in['options'] ?? []));
        $ticked  = (int) ($in['correct'] ?? 0);
        $opts    = [];
        $correct = -1;
        foreach ($raw as $i => $o) {
            if ($o === '') {
                continue;
            }
            if ($i === $ticked) {
                $correct = count($opts);
            }
            $opts[] = $o;
        }
        if (count($opts) < 2) {
            return 'A question needs at least two answers.';
        }
        if ($correct < 0) {
            return 'Tick which answer is the right one (the answer you ticked was left blank).';
        }

        $data = [
            'question'    => trim((string) ($in['question'] ?? '')),
            'options'     => json_encode($opts, JSON_UNESCAPED_UNICODE),
            'correct'     => $correct,
            'explanation' => trim((string) ($in['explanation'] ?? '')),
            'sort_order'  => (int) ($in['sort_order'] ?? 0),
            'active'      => !empty($in['active']) ? 1 : 0,
        ];
        if ($data['question'] === '') {
            return 'Write the question.';
        }

        if ($id) {
            $this->db->where('id', (int) $id)->update($this->t_qs, $data);

            return (int) $id;
        }

        $data['module_id'] = (int) ($in['module_id'] ?? 0);
        if (!$data['module_id']) {
            return 'Pick a module for this question.';
        }
        if (!$data['sort_order']) {
            $max = $this->db->select_max('sort_order', 'm')->where('module_id', $data['module_id'])->get($this->t_qs)->row();
            $data['sort_order'] = (int) ($max->m ?? 0) + 10;
        }
        $this->db->insert($this->t_qs, $data);

        return (int) $this->db->insert_id();
    }

    public function delete_question($id)
    {
        if (!$this->ready()) {
            return false;
        }
        $this->db->where('id', (int) $id)->delete($this->t_qs);

        return true;
    }
}
