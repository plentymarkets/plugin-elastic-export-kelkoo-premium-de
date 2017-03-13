<?php

namespace ElasticExportKelkooPremiumDE;

use Plenty\Modules\DataExchange\Services\ExportPresetContainer;
use Plenty\Plugin\DataExchangeServiceProvider;

class ElasticExportKelkooPremiumDEServiceProvider extends DataExchangeServiceProvider
{
    public function register()
    {

    }

    public function exports(ExportPresetContainer $container)
    {
        $container->add(
            'KelkooPremiumDE-Plugin',
            'ElasticExportKelkooPremiumDE\ResultField\KelkooPremiumDE',
            'ElasticExportKelkooPremiumDE\Generator\KelkooPremiumDE',
            '',
            true
        );
    }
}