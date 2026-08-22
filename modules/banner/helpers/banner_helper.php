<?php

defined('BASEPATH') || exit('No direct script access allowed');

if (!function_exists('handleBannerImageUpload')) {
    function handleBannerImageUpload($id = '') {
        $path = get_upload_path_by_type('banner').'/';
        $CI = &get_instance();
        $totalUploaded = 0;

        if (
            isset($_FILES['file']['name'])
            && ('' != $_FILES['file']['name'] || is_array($_FILES['file']['name']) && count($_FILES['file']['name']) > 0)
        ) {
            _file_attachments_index_fix('file');
            // Get the temp file path
            $tmpFilePath = $_FILES['file']['tmp_name'];
            // Make sure we have a filepath
            if (!empty($tmpFilePath) && '' != $tmpFilePath) {
                $extension = strtolower(pathinfo($_FILES['file']['name'], \PATHINFO_EXTENSION));
                $allowed_extensions = [
                    'jpg',
                    'jpeg',
                    'png',
                    'bmp',
                    'webp',
                ];

                if (
                    _perfex_upload_error($_FILES['file']['error'])
                    || !in_array($extension, $allowed_extensions)
                ) {
                    set_alert('danger', _l('image_extenstion_not_allowed'));

                    return false;
                }

                _maybe_create_upload_path($path);
                $filename = unique_filename($path, $_FILES['file']['name']);
                $newFilePath = $path.$filename;

                // Upload the file into the temp dir
                if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                    $adminIds = $CI->input->post('staff_ids');
                    $clientIds = $CI->input->post('client_ids');

                    $postData = $CI->input->post();
                    $attachment = [
                        'detail' => $filename,
                        'title' => $CI->input->post('title'),
                        'start_date' => to_sql_date($postData['start_date']),
                        'end_date' => to_sql_date($postData['end_date']),
                        'admin_area' => !empty($adminIds) ? 1 : 0,
                        'clients_area' => !empty($clientIds) ? 1 : 0,
                        'staff_ids' => !empty($adminIds) ? serialize($adminIds) : '',
                        'client_ids' => !empty($clientIds) ? serialize($clientIds) : '',
                        'has_action' => isset($postData['has_action']) ? 1 : 0,
                        'action_target' => isset($postData['action_target']) ? 1 : 0,
                        'action_label' => isset($postData['has_action']) ? $postData['action_label'] : '',
                        'action_url' => isset($postData['has_action']) ? $postData['action_url'] : '',
                        'label_color' => $postData['label_color']
                    ];

                    $CI->banner_model->addBannerImageToDB($attachment, $id);

                    log_activity('Banner Added Successfully');

                    ++$totalUploaded;
                }
            }
        }

        return (bool) $totalUploaded;
    }
}

function is_serialized($data) {
    // If it's not a string, it can't be serialized
    if (!is_string($data)) {
        return false;
    }

    // Trim any whitespace
    $data = trim($data);

    // Serialized data starts with 'a:', 's:', 'i:', etc.
    return 'N;' === $data || preg_match('/^([sia]:|O:|a:|b:|d:|i:|s:)/', $data);
}

/*
 * Get details of banners with status set to 1 from the database.
 *
 * @return array An array containing details of banners with status set to 1.
 */
if (!function_exists('getBannerDetails')) {
    function getBannerDetails($allowArea) {
        $res = [];

        $details = get_instance()->db->get_where(db_prefix().'banner', ['status' => 1])->result_array();

        // Filter out banners whose time duration is finished or not available for currently logged-in staff member
        $filteredData = array_filter($details, function ($value, $key) use ($allowArea) {
            $today = date('Y-m-d');
            $isInRange = $today >= $value['start_date'] && $today <= $value['end_date'];

            $ids = ('admin_area' == $allowArea) ? $value['staff_ids'] : $value['client_ids'];
            if ($isInRange) {
                if (is_serialized($ids)) {
                    return in_array(('admin_area' == $allowArea) ? get_staff_user_id() : get_client_user_id(), unserialize($ids));
                }
            }

            return false;
        }, \ARRAY_FILTER_USE_BOTH);

        return $filteredData;
    }
}

/*
 * Fetch active master-authored "tenant banners" targeting the current tenant.
 *
 * Runs ONLY inside a tenant context; reads the MASTER database cross-DB so a
 * banner published from the master account appears on tenant dashboards without
 * duplicating the row per tenant. Each returned row carries an absolute
 * `image_url` (served from the master domain) that renderBanner() prefers.
 *
 * @param string $allowArea 'admin_area' or 'clients_area'
 * @return array
 */
if (!function_exists('get_tenant_banner_details')) {
    function get_tenant_banner_details($allowArea) {
        if (!function_exists('perfex_saas_is_tenant') || !perfex_saas_is_tenant()) {
            return [];
        }
        if (!function_exists('perfex_saas_raw_query') || !function_exists('perfex_saas_master_db_prefix')) {
            return [];
        }

        $slug = function_exists('perfex_saas_tenant_slug') ? perfex_saas_tenant_slug() : false;
        if (!$slug) {
            return [];
        }

        $table      = perfex_saas_master_db_prefix() . 'banner_tenant_banner';
        $today      = date('Y-m-d'); // server generated, safe to inline
        $areaColumn = ('admin_area' == $allowArea) ? 'admin_area' : 'clients_area';

        try {
            $rows = perfex_saas_raw_query(
                "SELECT * FROM `{$table}` WHERE `status` = 1 AND `{$areaColumn}` = 1 AND `start_date` <= '{$today}' AND `end_date` >= '{$today}'",
                [],
                true
            );
        } catch (\Throwable $e) {
            return [];
        }

        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        $filtered = [];
        foreach ($rows as $row) {
            $row = (array) $row;

            // Target filter: all tenants, or this slug is in the selected list.
            $targetType = $row['target_type'] ?? 'all';
            if ('selected' === $targetType) {
                $slugs = is_serialized($row['target_slugs']) ? unserialize($row['target_slugs']) : [];
                if (!is_array($slugs) || !in_array($slug, $slugs)) {
                    continue;
                }
            }

            $filtered[] = $row;
        }

        return $filtered;
    }
}

if (!function_exists('renderBanner')) {
    function renderBanner($details) {
        $content = '';

        if (!empty($details['banner'])) {
            $preparList = '<ol class="carousel-indicators mtop20">';
            $preparContent = '<div class="carousel-inner">';
            $i = 0;

            if (get_option('enabled_banner_random_mode')) {
                shuffle($details['banner']);
            }

            foreach ($details['banner'] as $detail) {
                $active = (0 == $i) ? 'active' : '';
                $target = ($detail['action_target'] == 1) ? 'blank' : '';
                $action_url = !empty($detail['action_url']) ? $detail['action_url'] : 'javascript:void(0)';
                $preparList .= '<li data-target="#myCarousel" data-slide-to="' . $i . '" class="' . $active . '"></li>';
                $preparContent .= '<div class="item '. $active .'">
                                        <div class="panel">';

                $circle = (!is_mobile()) ? 'banner_circle' : '';
                $preparContent .= '<span class="' . $circle . ' banner_numbertext">'. $i + 1 .' / ' . count($details['banner']) . '</span>';
                if ($detail['has_action'] == 1) {
                    $preparContent .= '<a href="' . $action_url . '" target="' . $target . '">';
                }
                $img_src = !empty($detail['image_url']) ? $detail['image_url'] : site_url().'uploads/banner/' . $detail['detail'];
                $preparContent .= '<img src="'. $img_src . '" alt="' . $detail['detail'] . '" class="tw-w-full image-slideshow">';

                if ($detail['has_action'] == 1) {
                    $preparContent .= '</a>';
                    $caption = '<a href="' . $action_url . '" target="' . $target . '" style="color:' . $detail['label_color'] . '">' . $detail['action_label'] . '</a>';
                    $preparContent .= '<div class="caption_text">' . $caption . '</div>';
                }
                $preparContent .= '</div>
                                </div>';
                $i++;
            }
            $interval = get_option('time_of_banner_presentation') ?? '7000';
            $preparList .= '</ol>';
            $preparContent .= '</div>';

            $content = '<div class="col-md-12 mbot15">';
            $content .= '<div id="myCarousel" class="carousel slide" data-ride="carousel" data-interval="' . $interval . '">';
            $content .= $preparList;
            $content .= $preparContent;
            if (count($details['banner']) > 1) {
                $content .= '<a class="carousel-control" href="#myCarousel" data-slide="prev">
                                <span class="glyphicon glyphicon-chevron-left text-dark"></span>
                            </a>';
                $content .= '<a class="carousel-control" href="#myCarousel" data-slide="next" style="right:0; left:auto">
                                <span class="glyphicon glyphicon-chevron-right text-dark"></span>
                            </a>';
            }

            $content .= '</div></div>';
        }

        if (!empty($details['news'])) {
            $content .= '<div class="col-md-12 tw-my-8">';
            foreach ($details['news'] as $news) {
                $description = unserialize($news['news_details']);
                $news_type = get_news_types($news['news_type']);
                $content .= '<input type="hidden" id="custom_news_type" value="' . $news['news_type'] . '">';
                $content .= '<input type="hidden" id="custom_news_speed" value="' . $news_type['speed'] . '">';
                $content .= '<div class="acme-news-ticker">
                            <div class="acme-news-ticker-label" style="background: '. $news['title_bg_color'].';color: ' . $news['title_text_color'] . '; "><i class="' . $news['title_icon'] . ' tw-mr-2"></i>' . $news['news_title'] . '</div>

                            <div class="acme-news-ticker-box">
                                <ul class="my-news-ticker">';
                foreach ($description as $desc) {
                    $content .= '<li><span style="color:' . $desc['description_text_color'] . '">' . $desc['news_description'] . '</span></li>';
                }
                $content .= '</ul>
                            </div>
                            <div class="' . $news_type['btn_class'] . '">';
                foreach ($news_type['button'] as $key => $type) {
                    $arrow_class = ($news['news_type'] != 'marquee' && $news_type['type'][$key] != 'toggle') ? 'acme-news-ticker-arrow ' : '';
                    $content .= '<button class="' . $arrow_class . $type . '"></button>';
                }
                $content .= '</div>
                        </div> ';
            }
            $content .= '</div>';
        }

        return $content;
    }
}

if (!function_exists('get_news_picker')) {
    function get_news_picker() {
        $CI = get_instance();
        $module = $CI->app_modules->get('banner');

        // Safely get the 'uri' value or fallback
        $uri = isset($module['headers']['uri']) ? basename($module['headers']['uri']) : 'banner';

        // Generate a unique content string from the current URL
        $clean_url = preg_replace(['#/admin.*#', '#https?://#', '/[^a-zA-Z0-9]+/'], ['', '', '-'], current_full_url());
        $news_content = $uri . '-' . trim($clean_url, '-');

        return [
            'news_content' => $news_content
        ];
    }
}



if (!function_exists('getNewsTicker')) {
    function getNewsTicker($allowArea) {
        $res = [];

        $news_ticker = get_instance()->db->get_where(db_prefix().'news_ticker', ['status' => 1])->result_array();

        // Filter out banners whose time duration is finished or not available for currently logged-in staff member
        $filteredData = array_filter($news_ticker, function ($value, $key) use ($allowArea) {
            $today = date('Y-m-d');
            $isInRange = $today >= $value['start_date'] && $today <= $value['end_date'];

            $ids = ('admin_area' == $allowArea) ? $value['staff_ids'] : $value['client_ids'];
            if ($isInRange) {
                if (is_serialized($ids)) {
                    return in_array(('admin_area' == $allowArea) ? get_staff_user_id() : get_client_user_id(), unserialize($ids));
                }
            }

            return false;
        }, \ARRAY_FILTER_USE_BOTH);

        return $filteredData;
    }
}

if (!function_exists('get_news_types')) {
    function get_news_types($id = '') {
        $news_types = [
            [
                'id' => 'horizontal',
                'name' => _l('horizontal'),
                'button' => [
                    'acme-news-ticker-prev',
                    'acme-news-ticker-pause',
                    'acme-news-ticker-next',
                ],
                'type' => [
                    'prev',
                    'toggle',
                    'next',
                ],
                'speed' => '',
                'btn_class' => 'acme-news-ticker-controls acme-news-ticker-horizontal-controls',
            ],
            [
                'id' => 'marquee',
                'name' => _l('marquee'),
                'button' => [
                    'acme-news-ticker-pause',
                ],
                'type' => [
                    'toggle',
                ],
                'speed' => 0.05,
                'btn_class' => 'acme-news-ticker-controls acme-news-ticker-horizontal-controls',
            ],
            [
                'id' => 'typewriter',
                'name' => _l('typewriter'),
                'button' => [
                    'acme-news-ticker-prev',
                    'acme-news-ticker-pause',
                    'acme-news-ticker-next',
                ],
                'type' => [
                    'prev',
                    'toggle',
                    'next',
                ],
                'speed' => 50,
                'btn_class' => 'acme-news-ticker-controls acme-news-ticker-horizontal-controls',
            ],
            [
                'id' => 'vertical',
                'name' => _l('vertical'),
                'button' => [
                    'acme-news-ticker-prev',
                    'acme-news-ticker-pause',
                    'acme-news-ticker-next',
                ],
                'type' => [
                    'prev',
                    'toggle',
                    'next',
                ],
                'speed' => 600,
                'btn_class' => 'acme-news-ticker-controls acme-news-ticker-vertical-controls'
            ],
        ];

        if (empty($id)) {
            return $news_types;
        }

        $index = array_search($id, array_column($news_types, 'id'));
        return $index !== false ? $news_types[$index] : null;
    }
}
