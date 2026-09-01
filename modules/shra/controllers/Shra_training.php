<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * SHRA Self-Training — the sales course.
 *
 * Every member of the desk gets the same course and their own progress. The
 * content is entirely database-driven (admin/shra/shra_training/manage), so the
 * academy rewrites lessons and quizzes without a developer.
 */
class Shra_training extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('shra/shra_training_model', 'training');
        $this->load->helper('shra/shra');

        if (!shra_training_can()) {
            access_denied('shra training');
        }
    }

    private function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    private function need_manage()
    {
        if (!shra_training_can_manage()) {
            if ($this->input->method() === 'post' || $this->input->is_ajax_request()) {
                $this->json(['success' => false, 'message' => 'Only an administrator can edit the course.']);
                exit;
            }
            access_denied('shra training');
        }
    }

    private function me()
    {
        return (int) get_staff_user_id();
    }

    /* ═══════════════════════ Course home ═══════════════════════ */

    public function index()
    {
        $me = $this->me();

        $data['title']    = 'Self-Training';
        $data['modules']  = $this->training->modules(true);
        $data['stats']    = $this->training->module_stats($me);
        $data['overall']  = $this->training->overall($me);
        $data['badges']   = shra_training_badges($data['overall']);
        $data['cheer']    = shra_training_cheer($data['overall']['percent'], get_staff_full_name($me));
        $data['recent']   = $this->training->attempts($me, null, 5);
        $data['board']    = shra_leads_can('reports') ? $this->training->leaderboard(20) : [];
        $data['can_edit'] = shra_training_can_manage();

        // Lesson counts per module for the cards
        $data['lessons'] = [];
        foreach ($data['modules'] as $m) {
            $data['lessons'][(int) $m->id] = $this->training->lessons($m->id);
        }

        $this->load->view('training/index', $data);
    }

    /* ═══════════════════════ One module: read, then quiz ═══════════════════════ */

    public function module($id = null)
    {
        $m = $this->training->module($id);
        if (!$m || !(int) $m->active) {
            show_404();
        }
        $me = $this->me();

        $stats = $this->training->module_stats($me);
        $s     = $stats[(int) $m->id] ?? null;
        if ($s === null) {
            show_404();
        }

        $data['title']     = $m->title . ' · Self-Training';
        $data['module']    = $m;
        $data['lessons']   = $this->training->lessons($m->id);
        $data['done']      = $this->training->done_lessons($me, $m->id);
        $data['stats']     = $s;
        $data['questions'] = count($this->training->questions($m->id));
        $data['attempts']  = $this->training->attempts($me, $m->id, 5);
        $data['modules']   = $this->training->modules(true);
        $data['all_stats'] = $stats;
        $data['can_edit']  = shra_training_can_manage();

        $this->load->view('training/module', $data);
    }

    /** Tick / untick one lesson for the logged-in user. */
    public function lesson_done()
    {
        $id   = (int) $this->input->post('lesson_id');
        $undo = $this->input->post('undo') == '1';
        $me   = $this->me();

        $lesson = $this->training->lesson($id);
        if (!$lesson) {
            $this->json(['success' => false, 'message' => 'Lesson not found.']);

            return;
        }

        if ($undo) {
            $this->training->uncomplete_lesson($me, $id);
        } else {
            $this->training->complete_lesson($me, $id, (int) $this->input->post('seconds'));
        }

        $stats = $this->training->module_stats($me);
        $s     = $stats[(int) $lesson->module_id] ?? [];

        $this->json([
            'success' => true,
            'stats'   => $s,
            'overall' => $this->training->overall($me)['percent'],
        ]);
    }

    /**
     * Hand the browser a freshly drawn attempt. The correct answers stay on the
     * server — the page only ever knows what it is showing, never what is right.
     */
    public function quiz_start($id = null)
    {
        $m = $this->training->module($id);
        if (!$m) {
            $this->json(['success' => false, 'message' => 'Module not found.']);

            return;
        }

        $quiz = $this->training->draw_quiz($m->id);
        if (!$quiz) {
            $this->json(['success' => false, 'message' => 'This module has no quiz yet.']);

            return;
        }

        $this->json(['success' => true, 'module' => ['id' => (int) $m->id, 'title' => $m->title, 'emoji' => $m->emoji], 'quiz' => $quiz]);
    }

    /** Grade an attempt and store it. */
    public function quiz_submit($id = null)
    {
        $m = $this->training->module($id);
        if (!$m) {
            $this->json(['success' => false, 'message' => 'Module not found.']);

            return;
        }

        $raw = $this->input->post('answers');
        $ans = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($ans) || !count($ans)) {
            $this->json(['success' => false, 'message' => 'No answers were submitted.']);

            return;
        }

        $clean = [];
        foreach ($ans as $qid => $picked) {
            $clean[(int) $qid] = (int) $picked;
        }

        $res = $this->training->grade($this->me(), $m->id, $clean, (int) $this->input->post('seconds'));
        if ($res === 'incomplete') {
            $this->json(['success' => false, 'message' => 'Some answers did not reach us — please take the quiz again.']);

            return;
        }
        if (!$res) {
            $this->json(['success' => false, 'message' => 'Could not grade this attempt.']);

            return;
        }

        $stats = $this->training->module_stats($this->me());
        $this->json([
            'success' => true,
            'result'  => $res,
            'stats'   => $stats[(int) $m->id] ?? [],
            'overall' => $this->training->overall($this->me())['percent'],
        ]);
    }

    /** Wipe this user's own progress for one module and start again. */
    public function reset($id = null)
    {
        $m = $this->training->module($id);
        if (!$m) {
            show_404();
        }
        $this->training->reset_module($this->me(), $m->id);
        set_alert('success', 'Progress cleared — ' . $m->title . ' is ready to start again.');
        redirect(shra_training_url('module/' . (int) $m->id));
    }

    /* ═══════════════════════ Authoring ═══════════════════════ */

    public function manage()
    {
        $this->need_manage();

        $data['title']    = 'Self-Training · Course editor';
        $data['modules']  = $this->training->modules(false);
        $data['lessons']  = [];
        $data['qs']       = [];
        foreach ($data['modules'] as $m) {
            $data['lessons'][(int) $m->id] = $this->training->lessons($m->id, false);
            $data['qs'][(int) $m->id]      = $this->training->questions($m->id, false);
        }
        $data['tokens'] = array_keys(shra_training_tokens());
        $data['open']   = (int) $this->input->get('module');

        $this->load->view('training/manage', $data);
    }

    public function save_module($id = null)
    {
        $this->need_manage();
        $res = $this->training->save_module($this->input->post(null, false) ?: [], $id ? (int) $id : null);
        if (is_string($res)) {
            set_alert('danger', $res);
        } else {
            set_alert('success', $id ? 'Module updated.' : 'Module added.');
            $id = $res;
        }
        redirect(shra_training_url('manage?module=' . (int) $id));
    }

    public function delete_module($id = null)
    {
        $this->need_manage();
        $this->training->delete_module($id);
        set_alert('success', 'Module deleted, along with its lessons, questions and everyone\'s progress on it.');
        redirect(shra_training_url('manage'));
    }

    public function save_lesson($id = null)
    {
        $this->need_manage();
        $post = $this->input->post(null, false) ?: [];
        $res  = $this->training->save_lesson($post, $id ? (int) $id : null);
        if (is_string($res)) {
            set_alert('danger', $res);
        } else {
            set_alert('success', $id ? 'Lesson updated.' : 'Lesson added.');
        }
        redirect(shra_training_url('manage?module=' . (int) ($post['module_id'] ?? 0)) . '#lessons');
    }

    public function delete_lesson($id = null)
    {
        $this->need_manage();
        $l = $this->training->lesson($id);
        $this->training->delete_lesson($id);
        set_alert('success', 'Lesson deleted.');
        redirect(shra_training_url('manage?module=' . (int) ($l->module_id ?? 0)) . '#lessons');
    }

    public function save_question($id = null)
    {
        $this->need_manage();
        $post = $this->input->post(null, false) ?: [];
        $res  = $this->training->save_question($post, $id ? (int) $id : null);
        if (is_string($res)) {
            set_alert('danger', $res);
        } else {
            set_alert('success', $id ? 'Question updated.' : 'Question added.');
        }
        redirect(shra_training_url('manage?module=' . (int) ($post['module_id'] ?? 0)) . '#quiz');
    }

    public function delete_question($id = null)
    {
        $this->need_manage();
        $q = $this->db->where('id', (int) $id)->get(db_prefix() . 'shra_training_questions')->row();
        $this->training->delete_question($id);
        set_alert('success', 'Question deleted.');
        redirect(shra_training_url('manage?module=' . (int) ($q->module_id ?? 0)) . '#quiz');
    }

    /**
     * Re-plant any default module the academy has deleted. Modules that still
     * exist are never touched, so local rewrites survive.
     */
    public function restore_defaults()
    {
        $this->need_manage();
        update_option('shra_training_seeded', '[]');
        require(module_dir_path(SHRA_MODULE_NAME, 'install.php'));
        set_alert('success', 'Any missing default modules have been restored. Your own edits were left alone.');
        redirect(shra_training_url('manage'));
    }
}
