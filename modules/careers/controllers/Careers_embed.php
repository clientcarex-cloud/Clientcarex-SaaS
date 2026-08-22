<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Careers — embeddable widget.
 *
 * The paste-anywhere surface: one <script> tag on any website (WordPress, a
 * static page, another CMS) renders the live openings, the job detail and a
 * working apply form with CV upload. No API key, no proxy, no .env — the data
 * it serves is exactly what a visitor would see on a public careers page, so it
 * is deliberately unauthenticated and CORS-open.
 *
 * What protects it: only `published` postings are ever returned, applications
 * run through the same validation, honeypot and per-IP throttle as every other
 * intake, and the whole surface can be switched off (or limited to named
 * domains) in Careers → Settings.
 *
 * Endpoints (all under careers/careers_embed/):
 *   GET  js                → the widget script
 *   GET  data[?slug=]      → openings, or one opening with its apply form
 *   POST apply             → application intake (multipart, CV attached)
 *   POST track             → view counter
 *   GET  page              → standalone listing, for an <iframe> embed
 *   GET  job/{slug}        → standalone opening, shareable + Google Jobs markup
 */
class Careers_embed extends ClientsController
{
    public function __construct()
    {
        parent::__construct();

        careers_load_lang();
        $this->load->model('careers/careers_model');

        // A browser sends OPTIONS before some cross-origin posts; answer it
        // before any endpoint logic runs.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            $this->cors();
            $this->output->set_status_header(204)->_display();
            exit;
        }

        if (!careers_opt_bool('careers_embed_enabled')) {
            $this->cors();
            $this->respond(['ok' => false, 'error' => 'The careers widget is disabled.'], 403);
        }

        $this->cors();
    }

    /* ══════════════════════════════ Widget ════════════════════════════════ */

    /**
     * The widget script.
     *
     * Served through the controller rather than as a plain asset URL so the
     * embed snippet stays short, the API base is baked in (the host page never
     * has to know it) and the response can be cached by the browser.
     */
    public function js()
    {
        $path = module_dir_path(CAREERS_MODULE_NAME, 'assets/js/careers_embed.js');

        if (!is_file($path)) {
            $this->output->set_status_header(404)->set_output('/* careers widget missing */')->_display();
            exit;
        }

        $script = file_get_contents($path);
        $script = str_replace('__CAREERS_EMBED_BASE__', site_url('careers/careers_embed'), $script);

        $this->output
            ->set_content_type('application/javascript', 'utf-8')
            ->set_header('Cache-Control: public, max-age=600')
            ->set_output($script)
            ->_display();
        exit;
    }

    /**
     * Everything the widget renders, in one call: the list, or a single opening
     * with its screening questions and form configuration.
     *
     * The unused `$data` parameter is not optional to keep: ClientsController
     * declares `data($data)` (its view-data setter) and PHP refuses to compile
     * a narrower override — `public function data()` fatals the moment this
     * file is required, so EVERY url under careers/careers_embed answered a
     * blank 500, the widget script included. Do not drop the parameter; the
     * endpoint itself reads only the query string.
     */
    public function data($data = null)
    {
        $slug = trim((string) $this->input->get('slug'));

        if ($slug !== '') {
            $job = $this->careers_model->public_job($slug);

            if (!$job) {
                $this->respond(['ok' => true, 'found' => false, 'slug' => $slug]);
            }

            $expired = !empty($job->deadline) && $job->deadline !== '0000-00-00' && $job->deadline < date('Y-m-d');

            $questions = [];
            foreach ($this->careers_model->questions($job->id) as $question) {
                $questions[] = careers_public_question($question);
            }

            if ((int) $this->input->get('track') === 1) {
                $this->careers_model->track_view($job->id);
            }

            $related = [];
            foreach ($this->careers_model->related_jobs($job) as $other) {
                $related[] = careers_public_job($other);
            }

            $this->respond([
                'ok'              => true,
                'found'           => true,
                'expired'         => $expired,
                'company'         => careers_company_name(),
                'apply_enabled'   => careers_opt_bool('careers_allow_public_apply') && !$expired,
                'alerts_enabled'  => careers_opt_bool('careers_alerts_enabled'),
                'resume_required' => careers_opt_bool('careers_resume_required'),
                'max_resume_mb'   => (int) careers_opt('careers_max_resume_mb'),
                'allowed_ext'     => careers_allowed_extensions(),
                'questions'       => $questions,
                'related'         => $related,
                'job'             => careers_public_job($job, true),
            ]);
        }

        $jobs = $this->careers_model->public_jobs([
            'type'       => (string) $this->input->get('type'),
            'department' => (string) $this->input->get('department'),
            'limit'      => (int) $this->input->get('limit'),
        ]);

        $list        = [];
        $departments = [];
        $locations   = [];
        $types       = [];

        foreach ($jobs as $job) {
            $row    = careers_public_job($job);
            $list[] = $row;

            if ($row['department'] !== '') {
                $departments[$row['department']] = true;
            }
            if ($row['location'] !== '') {
                $locations[$row['location']] = true;
            }
            $types[$row['type']] = $row['type_label'];
        }

        $this->respond([
            'ok'             => true,
            'generated_at'   => gmdate('c'),
            'total'          => count($list),
            'company'        => careers_company_name(),
            'apply_enabled'  => careers_opt_bool('careers_allow_public_apply'),
            // The host page decides whether to render a "notify me" form; the
            // subscribe endpoint refuses when this is off, so telling it up
            // front is the difference between no form and a rejected one.
            'alerts_enabled' => careers_opt_bool('careers_alerts_enabled'),
            'facets'         => [
                'departments' => array_keys($departments),
                'locations'   => array_keys($locations),
                'types'       => $types,
            ],
            'jobs' => $list,
        ]);
    }

    /**
     * Application intake straight from the widget — the candidate's browser
     * posts here, so there is no proxy in between and $_FILES carries the CV.
     */
    public function apply()
    {
        $slug = trim((string) $this->input->post('slug'));
        $job  = $slug !== '' ? $this->careers_model->public_job($slug) : null;

        if (!$job) {
            $this->respond(['ok' => false, 'error' => 'This position is no longer accepting applications.'], 404);
        }

        $result = $this->careers_model->process_public_application($job, $_POST, $_FILES, [
            'ip_address' => (string) $this->input->ip_address(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'source'     => 'embed',
        ]);

        if (empty($result['ok'])) {
            $this->respond([
                'ok'        => false,
                'error'     => $result['error'],
                'duplicate' => !empty($result['duplicate']),
            ], $result['status']);
        }

        $this->respond([
            'ok'        => true,
            'reference' => $result['reference'],
            'message'   => 'Your application has been received. Our talent team will be in touch.',
        ]);
    }

    /**
     * Job alerts, straight from a host page's "notify me" form — the same
     * keyless path as the widget, so a careers page needs no proxy at all.
     */
    public function subscribe()
    {
        if (!careers_opt_bool('careers_alerts_enabled')) {
            $this->respond(['ok' => false, 'error' => 'Job alerts are not available right now.'], 403);
        }

        if (trim((string) $this->input->post('company_website')) !== '') {
            $this->respond(['ok' => true, 'message' => 'Subscribed.']);
        }

        $id = $this->careers_model->subscribe(
            (string) $this->input->post('email'),
            (string) $this->input->post('name'),
            (string) $this->input->post('departments'),
            (string) $this->input->post('job_types')
        );

        if (!$id) {
            $this->respond(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
        }

        $this->respond(['ok' => true, 'message' => 'You are on the list — we will email you when a matching role opens.']);
    }

    public function track()
    {
        $slug = trim((string) ($this->input->post('slug') ?: $this->input->get('slug')));
        $job  = $slug !== '' ? $this->careers_model->public_job($slug) : null;

        if ($job) {
            $this->careers_model->track_view($job->id);
        }

        $this->respond(['ok' => true]);
    }

    /* ════════════════════════ Standalone pages ════════════════════════════ */

    /**
     * The same widget on a bare page, for an <iframe> embed and for anyone who
     * would rather link than paste a script. It reports its height to the host
     * page, so the iframe can size itself.
     */
    public function page()
    {
        $this->render_page(
            careers_company_name() . ' — Careers',
            '<div data-careers-embed'
                . $this->passthrough_attr('department')
                . $this->passthrough_attr('type')
                . $this->passthrough_attr('layout')
                . $this->passthrough_attr('theme')
                . $this->passthrough_attr('accent')
                . $this->passthrough_attr('filters')
                . '></div>'
        );
    }

    /**
     * One opening as a shareable page, with the JobPosting structured data
     * Google Jobs needs — the widget links here when a host page wants a real
     * URL per role.
     */
    public function job($slug = '')
    {
        $job = $slug !== '' ? $this->careers_model->public_job($slug) : null;

        if (!$job) {
            $this->output->set_status_header(404);
            $this->render_page('Position not found', '<div data-careers-embed></div>');

            return;
        }

        $row  = careers_public_job($job, true);
        $head = '<script type="application/ld+json">' . json_encode($this->job_posting_schema($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';

        // The description is rendered into the page as well as the widget, so a
        // crawler that does not run JavaScript still reads the whole posting.
        $noscript = '<noscript><h1>' . html_escape($row['title']) . '</h1>'
            . $row['description'] . $row['responsibilities'] . $row['requirements'] . '</noscript>';

        $this->render_page(
            $row['title'] . ' — ' . careers_company_name(),
            $noscript . '<div data-careers-embed data-job="' . html_escape($row['slug']) . '"></div>',
            $head,
            $row['seo_description'] ?: $row['summary']
        );
    }

    /* ═══════════════════════════ Internals ════════════════════════════════ */

    private function job_posting_schema(array $row)
    {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'JobPosting',
            'title'       => $row['title'],
            'description' => $row['description'] . $row['responsibilities'] . $row['requirements'] . $row['benefits'],
            'identifier'  => ['@type' => 'PropertyValue', 'name' => careers_company_name(), 'value' => $row['reference']],
            'datePosted'  => $row['posted_iso'],
            'employmentType'     => $row['type_schema'],
            'hiringOrganization' => ['@type' => 'Organization', 'name' => careers_company_name(), 'sameAs' => site_url()],
            'totalJobOpenings'   => $row['openings'],
            'url'                => site_url('careers/careers_embed/job/' . $row['slug']),
        ];

        if ($row['deadline_iso']) {
            $schema['validThrough'] = $row['deadline_iso'];
        }
        if ($row['work_mode'] === 'remote') {
            $schema['jobLocationType'] = 'TELECOMMUTE';
        }
        if ($row['city'] !== '' || $row['country'] !== '') {
            $schema['jobLocation'] = [
                '@type'   => 'Place',
                'address' => array_filter([
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => $row['address'],
                    'addressLocality' => $row['city'],
                    'addressRegion'   => $row['state'],
                    'postalCode'      => $row['postal_code'],
                    'addressCountry'  => $row['country'],
                ], static function ($v) {
                    return $v !== '';
                }),
            ];
        }
        if ($row['salary_min'] !== null || $row['salary_max'] !== null) {
            $schema['baseSalary'] = [
                '@type'    => 'MonetaryAmount',
                'currency' => $row['salary_currency'],
                'value'    => array_filter([
                    '@type'    => 'QuantitativeValue',
                    'minValue' => $row['salary_min'],
                    'maxValue' => $row['salary_max'],
                    'unitText' => $row['salary_unit'],
                ], static function ($v) {
                    return $v !== null;
                }),
            ];
        }

        return $schema;
    }

    /** Carry a whitelisted query parameter onto the widget container. */
    private function passthrough_attr($name)
    {
        $value = trim((string) $this->input->get($name));

        if ($value === '' || !preg_match('/^[A-Za-z0-9 #,_\-]{1,60}$/', $value)) {
            return '';
        }

        return ' data-' . $name . '="' . html_escape($value) . '"';
    }

    private function render_page($title, $body, $head = '', $description = '')
    {
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . html_escape($title) . '</title>'
            . ($description !== '' ? '<meta name="description" content="' . html_escape(careers_excerpt($description, 155)) . '">' : '')
            . $head
            . '<style>body{margin:0;padding:24px;background:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}</style>'
            . '</head><body>' . $body
            . '<script src="' . site_url('careers/careers_embed/js') . '"></script>'
            // Height reporting, so a host page's iframe can grow with the content.
            . '<script>(function(){function s(){if(window.parent!==window){window.parent.postMessage({careersEmbedHeight:document.documentElement.scrollHeight},"*");}}'
            . 'window.addEventListener("load",s);setInterval(s,700);}());</script>'
            . '</body></html>';

        $this->output
            ->set_content_type('text/html', 'utf-8')
            ->set_output($html)
            ->_display();
        exit;
    }

    /**
     * Cross-origin headers.
     *
     * Blank `careers_embed_domains` means any site may embed the widget, which
     * is the point of a paste-anywhere snippet. Filling it in restricts the
     * browser to the named hosts — the response is public data either way, so
     * this is about controlling where the form can be shown, not secrecy.
     */
    private function cors()
    {
        $allowed = careers_split_list(careers_opt('careers_embed_domains'), 30);
        $origin  = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));

        if (empty($allowed)) {
            $this->output->set_header('Access-Control-Allow-Origin: *');
        } elseif ($origin !== '') {
            $host = strtolower((string) parse_url($origin, PHP_URL_HOST));

            foreach ($allowed as $domain) {
                $domain = strtolower(preg_replace('#^https?://#', '', trim($domain, " /")));
                if ($domain !== '' && ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain)) {
                    $this->output->set_header('Access-Control-Allow-Origin: ' . $origin);
                    $this->output->set_header('Vary: Origin');
                    break;
                }
            }
        }

        $this->output->set_header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        $this->output->set_header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
        $this->output->set_header('Access-Control-Max-Age: 86400');
    }

    private function respond(array $payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->_display();
        exit;
    }
}
