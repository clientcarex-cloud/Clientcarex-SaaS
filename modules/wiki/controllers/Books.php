<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Books extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('roles_model');
        $this->load->model('staff_model');
        $this->load->model('wikibooks_model');
    }

    public function index()
    {
        if (!has_permission('wiki_books', '', 'view') && !is_admin()) {
            access_denied('wiki_books');
        }

        $user = get_staff($this->session->userdata('tfa_staffid'));
        $data['title'] = _l('wiki_books_list');
        $filter_query = $this->input->get('filter_query');
        if(!isset($filter_query)){
            $filter_query = "";
        }
        $this->load->model('wikiarticles_model');
        $data['books'] = $this->wikibooks_model->get_all_books($filter_query);
        $data['articles_count'] = count($this->wikiarticles_model->get_all_articles([]));
        
        // Calculate total views across all books
        $total_views = 0;
        foreach($data['books'] as $book){
            $total_views += (int)$book['total_views'];
        }
        $data['total_views'] = $total_views;
        
        $data['filter_query'] = $filter_query;
        $data['user_id'] = $user->staffid;
        $this->load->view('books_manage', $data);
    }

    public function book($id = '')
    {
        if ($this->input->post()) {
            if ($id == '') {
                if (!has_permission('wiki_books', '', 'create')) {
                    access_denied('wiki_books');
                }
                $id = $this->wikibooks_model->add($this->input->post());
                if ($id) {
                    set_alert('success', _l('added_successfully', _l('wiki_book')));
                    redirect(admin_url('wiki/books/book/' . $id));
                }
            } else {
                if (!has_permission('wiki_books', '', 'edit')) {
                    access_denied('wiki_books');
                }
                $success = $this->wikibooks_model->update($this->input->post(), $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('wiki_book')));
                }else{
                
                }
                $back_url = $this->input->post('back_url');
                if(isset($back_url)){
                    redirect($back_url);
                }else{
                    redirect(admin_url('wiki/books/book/' . $id));
                }
            }
        }
        if ($id == '') {
            $title = _l('wiki_create_book');
        } else {
            $data['book']        = $this->wikibooks_model->get($id);
            $back_url = $this->input->get('back_url');
            if(isset($back_url)){
                $data['back_url'] = $back_url;
            }
            $title = _l('edit', _l('wiki_book_lowercase'));
        }
        $data['title']                 = $title;
        $data['roles']    = $this->roles_model->get();
        $data['members'] = $this->staff_model->get('', [
            'active'       => 1,
            'is_not_staff' => 0,
        ]);
        $this->load->view('book', $data);
    }

    public function delete($id)
    {
        if (!has_permission('wiki_books', '', 'delete')) {
            access_denied('wiki_books');
        }
        if (!$id) {
            redirect(admin_url('wiki/books'));
        }
        $response = $this->wikibooks_model->delete($id);
        if ($response == true) {
            set_alert('success', _l('deleted', _l('wiki_book')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('wiki_book_lowercase')));
        }
        redirect(admin_url('wiki/books'));
    }

    /**
     * AJAX endpoint: fetch recent articles for a book preview panel
     */
    public function preview($id = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->input->is_ajax_request()) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            die();
        }

        if (!has_permission('wiki_books', '', 'view') && !has_permission('wiki_articles', '', 'view') && !is_admin()) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            die();
        }

        if(!$id || !is_numeric($id)){
            echo json_encode(['success' => false, 'message' => 'Invalid book ID']);
            die();
        }

        try {
            $book = $this->wikibooks_model->get($id);
            if(!$book){
                echo json_encode(['success' => false, 'message' => 'Book not found']);
                die();
            }

            $articles = $this->wikibooks_model->get_book_articles($id, 5);
            
            // Format articles for JSON response
            $formatted_articles = [];
            foreach($articles as $article){
                $formatted_articles[] = [
                    'id'            => $article['id'],
                    'title'         => $article['title'],
                    'description'   => mb_strimwidth(strip_tags(isset($article['description']) ? $article['description'] : ''), 0, 120, '...'),
                    'type'          => isset($article['type']) ? $article['type'] : 'document',
                    'views'         => (int)(isset($article['view_counter']) ? $article['view_counter'] : 0),
                    'author'        => isset($article['author_fullname']) ? $article['author_fullname'] : '',
                    'updated_at'    => isset($article['updated_at']) ? date("M d, Y", strtotime($article['updated_at'])) : '',
                    'show_url'      => admin_url('wiki/articles/show/' . $article['id']),
                    'edit_url'      => admin_url('wiki/articles/article/' . $article['id']),
                ];
            }

            echo json_encode([
                'success'   => true,
                'book'      => [
                    'id'                => $book->id,
                    'name'              => $book->name,
                    'short_description' => isset($book->short_description) ? $book->short_description : '',
                ],
                'articles'  => $formatted_articles,
                'articles_url' => admin_url('wiki/articles') . '?filter_book_id=' . $book->id,
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        die();
    }
}
