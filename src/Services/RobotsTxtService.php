<?php

namespace Giga\Cms\Services;

/**
 * "robots.txt generato/configurabile (distinto dal meta robots per-entry)"
 * (SEO). Se robots_txt_custom è impostato, prevale integralmente
 * (override totale dell'admin) — altrimenti generato: Allow universale
 * più riferimento alla sitemap se site_url è configurato.
 *
 * Solo generazione del contenuto: servire la risposta su una rotta
 * /robots.txt resta al progetto consumer, stesso principio già seguito
 * per il Migration Runner ("trigger via script CLI, non rotta web") —
 * giga-cms non definisce rotte.
 */
class RobotsTxtService
{
    private InstallationSettingService $settingService;

    public function __construct()
    {
        $this->settingService = new InstallationSettingService();
    }

    public function generate(): string
    {
        $custom = $this->settingService->get('robots_txt_custom');
        if ($custom !== null && trim($custom) !== '') {
            return $custom;
        }

        $lines = ['User-agent: *', 'Allow: /'];

        $siteUrl = $this->settingService->get('site_url');
        if ($siteUrl !== null && trim($siteUrl) !== '') {
            $lines[] = '';
            $lines[] = 'Sitemap: ' . rtrim($siteUrl, '/') . '/sitemap.xml';
        }

        return implode("\n", $lines) . "\n";
    }
}
