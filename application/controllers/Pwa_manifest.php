<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * PWA Manifest Controller
 *
 * Serves the Web App Manifest dynamically so it can include
 * the company name from database settings and the correct base URL.
 * Route: pwa/manifest
 */
class Pwa_manifest extends App_Controller
{
    public function index()
    {
        $companyName = get_option('companyname') ?: 'ClientcareX';
        $shortName   = mb_substr($companyName, 0, 12);

        $manifest = [
            'name'                    => $companyName,
            'short_name'              => $shortName,
            'description'             => $companyName . ' — Healthcare CRM',
            'start_url'               => admin_url(),
            'scope'                   => base_url(),
            'display'                 => get_option('ccx_wpa_display_mode') ?: 'standalone',
            'orientation'             => get_option('ccx_wpa_orientation') ?: 'any',
            'theme_color'             => get_option('ccx_wpa_theme_color') ?: '#1B74E4',
            'background_color'        => get_option('ccx_wpa_background_color') ?: '#f0f4f8',
            'icons'                   => [
                [
                    'src'     => base_url('assets/images/pwa/icon-192x192.png'),
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src'     => base_url('assets/images/pwa/icon-512x512.png'),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
            'categories'              => ['medical', 'health', 'business', 'productivity'],
            'prefer_related_applications' => false,
        ];

        // Allow modules to extend/modify the manifest
        $manifest = hooks()->apply_filters('pwa_manifest', $manifest);

        $this->output
            ->set_content_type('application/manifest+json')
            ->set_header('Cache-Control: public, max-age=86400')
            ->set_output(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
