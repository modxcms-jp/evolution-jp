<?php

require_once MODX_CORE_PATH . 'site_exporter.class.inc.php';

/**
 * EvoSiteExporter を作るだけの薄い入口（M2 で廃止予定）。
 */
class EXPORT_SITE
{
    public function __construct()
    {
        $this->setUrlMode();
    }

    public function create(array $options)
    {
        return new EvoSiteExporter($options);
    }

    private function setUrlMode()
    {
        if (evo()->config('friendly_urls') == 0) {
            evo()->config['friendly_urls'] = 1;
            evo()->config['use_alias_path'] = 1;
            evo()->clearCache();
        }
    }
}

$this->export = new EXPORT_SITE;
