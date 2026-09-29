<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class ProjectMonitoring extends BaseConfig
{
    public bool $enabled;
    public string $baseUrl;
    public string $syncSecret;
    public string $siteBypassToken;
    public int $timeout;

    public function __construct()
    {
        parent::__construct();
        $this->enabled = filter_var(env('projectMonitoring.enabled', false), FILTER_VALIDATE_BOOL);
        $this->baseUrl = rtrim((string) env('projectMonitoring.baseUrl', 'https://client-project-monitoring-portal.ronilece18.chatgpt.site'), '/');
        $this->syncSecret = (string) env('projectMonitoring.syncSecret', '');
        $this->siteBypassToken = (string) env('projectMonitoring.siteBypassToken', '');
        $this->timeout = max(3, min(30, (int) env('projectMonitoring.timeout', 10)));
    }
}
