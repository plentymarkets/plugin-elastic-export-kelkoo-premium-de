<?php

namespace ElasticExportKelkooPremiumDE\Generator;

use ElasticExport\Helper\ElasticExportCoreHelper;
use ElasticExport\Helper\ElasticExportPriceHelper;
use ElasticExport\Helper\ElasticExportStockHelper;
use Plenty\Modules\DataExchange\Contracts\CSVPluginGenerator;
use Plenty\Modules\Helper\Services\ArrayHelper;
use Plenty\Modules\Item\DataLayer\Models\Record;
use Plenty\Modules\Item\DataLayer\Models\RecordList;
use Plenty\Modules\DataExchange\Models\FormatSetting;
use Plenty\Modules\Helper\Models\KeyValue;
use Plenty\Modules\Item\Search\Contracts\VariationElasticSearchScrollRepositoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * Class KelkooPremiumDE
 *
 * @package ElasticExportKelkooPremiumDE\Generator
 */
class KelkooPremiumDE extends CSVPluginGenerator
{
	use Loggable;
	
    /**
     * @var ElasticExportCoreHelper $elasticExportHelper
     */
    private $elasticExportHelper;

	/**
	 * @var ElasticExportStockHelper $elasticExportStockHelper
	 */
    private $elasticExportStockHelper;

	/**
	 * @var ElasticExportPriceHelper $elasticExportPriceHelper
	 */
    private $elasticExportPriceHelper;

    /**
     * @var ArrayHelper $arrayHelper
     */
    private $arrayHelper;

    /**
     * @var array $idlVariations
     */
    private $idlVariations = array();

    /**
     * KelkooPremiumDE constructor.
	 *
     * @param ArrayHelper $arrayHelper
     */
    public function __construct(ArrayHelper $arrayHelper)
    {
        $this->arrayHelper = $arrayHelper;
    }

    /**
     * @param VariationElasticSearchScrollRepositoryContract $elasticSearch
     * @param array $formatSettings
     * @param array $filter
     */
    protected function generatePluginContent($elasticSearch, array $formatSettings = [], array $filter = [])
    {
        $this->elasticExportHelper = pluginApp(ElasticExportCoreHelper::class);
        $this->elasticExportStockHelper = pluginApp(ElasticExportStockHelper::class);
		$this->elasticExportPriceHelper = pluginApp(ElasticExportPriceHelper::class);

		$settings = $this->arrayHelper->buildMapFromObjectList($formatSettings, 'key', 'value');

		$this->setDelimiter(" ");

		$this->addCSVContent([
			'category',
			'marke',
			'title',
			'description',
			'price',
			'deliverycost',
			'url',
			'image',
			'availability',
			'offerid',
			'unitaryPrice',
			'ean',

		]);

		$limitReached = false;
		$lines = 0;
		$startTime = microtime(true);

        if($elasticSearch instanceof VariationElasticSearchScrollRepositoryContract)
        {
			do
			{
				if($limitReached === true)
				{
					break;
				}

				$this->getLogger(__METHOD__)->debug('ElasticExportKelkooPremiumDE::log.writtenlines', ['lines written' => $lines]);

				$esStartTime = microtime(true);

				$resultList = $elasticSearch->execute();

				$this->getLogger(__METHOD__)->debug('ElasticExportKelkooPremiumDE::log.esDuration', [
					'Elastic Search duration' => microtime(true) - $esStartTime,
				]);

				if(count($resultList['error']) > 0)
				{
					$this->getLogger(__METHOD__)->error('ElasticExportKelkooPremiumDE::log.occurredElasticSearchErrors', [
						'error message' => $resultList['error'],
					]);
				}

				$buildRowStartTime = microtime(true);

				if(is_array($resultList['documents']) && count($resultList['documents']) > 0)
				{
					foreach($resultList['documents'] as $item)
					{
						if($this->elasticExportStockHelper->isFilteredByStock($item, $filter))
						{
							continue;
						}

						try
						{
							$this->buildRow($item, $settings);
							$lines++;
						}
						catch(\Throwable $exception)
						{
							$this->getLogger(__METHOD__)->error('ElasticExportKelkooPremiumDE::log.buildRowError', [
								'error' => $exception->getMessage(),
								'line' => $exception->getLine(),
								'variation ID' => $item['id']
							]);
						}

						$this->getLogger(__METHOD__)->debug('ElasticExportKelkooPremiumDE::log.buildRowDuration', [
							'Build Row duration' => microtime(true) - $buildRowStartTime,
						]);

						if($lines == $filter['limit'])
						{
							$limitReached = true;
							break;
						}
					}	
				}
			}
			while($elasticSearch->hasNext());
        }

		$this->getLogger(__METHOD__)->debug('ElasticExportKelkooPremiumDE::log.fileGenerationDuration', [
			'Whole file generation duration' => microtime(true) - $startTime,
		]);
    }

	/**
	 * @param array $item
	 * @param KeyValue $settings
	 */
    private function buildRow($item, $settings)
	{
		$deliveryCost = $this->elasticExportHelper->getShippingCost($item['data']['item']['id'], $settings);

		if(!is_null($deliveryCost))
		{
			$deliveryCost = number_format((float)$deliveryCost, 2, ',', '');
		}
		else
		{
			$deliveryCost = '';
		}

		$priceList = $this->elasticExportPriceHelper->getPriceList($item, $settings, 2, '.');

		$data = [
			'category'      => $this->elasticExportHelper->getCategory((int)$item['data']['defaultCategories'][0]['id'], $settings->get('lang'), $settings->get('plentyId')),
			'marke'         => $this->elasticExportHelper->getExternalManufacturerName((int)$item['data']['item']['manufacturer']['id']),
			'title' 		=> $this->elasticExportHelper->getMutatedName($item, $settings),
			'description'   => $this->elasticExportHelper->getMutatedDescription($item, $settings, 256),
			'price' 	    => $priceList['price'],
			'deliverycost' 	=> $deliveryCost,
			'url' 		    => $this->elasticExportHelper->getMutatedUrl($item, $settings, true, false),
			'image'		    => $this->elasticExportHelper->getMainImage($item, $settings),
			'availability'  => $this->elasticExportHelper->getAvailability($item, $settings),
			'offerid'       => $item['id'],
			'unitaryPrice'  => $this->elasticExportPriceHelper->getBasePrice($item, $priceList['price']),
			'ean'           => $this->elasticExportHelper->getBarcodeByType($item, $settings->get('barcode')),
		];

		$this->addCSVContent(array_values($data));
	}
}