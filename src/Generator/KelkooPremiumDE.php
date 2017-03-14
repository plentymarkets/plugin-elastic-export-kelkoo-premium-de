<?php

namespace ElasticExportKelkooPremiumDE\Generator;

use ElasticExport\Helper\ElasticExportCoreHelper;
use Plenty\Modules\DataExchange\Contracts\CSVPluginGenerator;
use Plenty\Modules\Helper\Services\ArrayHelper;
use Plenty\Modules\Item\DataLayer\Models\Record;
use Plenty\Modules\Item\DataLayer\Models\RecordList;
use Plenty\Modules\DataExchange\Models\FormatSetting;
use Plenty\Modules\Helper\Models\KeyValue;

class KelkooPremiumDE extends CSVPluginGenerator
{
    /**
     * @var ElasticExportCoreHelper $elasticExportHelper
     */
    private $elasticExportHelper;

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
     * @param ArrayHelper $arrayHelper
     */
    public function __construct(ArrayHelper $arrayHelper)
    {
        $this->arrayHelper = $arrayHelper;
    }

    /**
     * @param array $resultData
     * @param array $formatSettings
     * @param array $filter
     */
    protected function generatePluginContent($resultData, array $formatSettings = [], array $filter = [])
    {
        $this->elasticExportHelper = pluginApp(ElasticExportCoreHelper::class);
        if(is_array($resultData['documents']) && count($resultData['documents']) > 0)
        {
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

            //Create a List of all VariationIds
            $variationIdList = array();
            foreach($resultData['documents'] as $variation)
            {
                $variationIdList[] = $variation['id'];
            }
            
            //Get the missing fields in ES from IDL
            if(is_array($variationIdList) && count($variationIdList) > 0)
            {
                /**
                 * @var \ElasticExportKelkooPremiumDE\IDL_ResultList\KelkooPremiumDE $idlResultList
                 */
                $idlResultList = pluginApp(\ElasticExportKelkooPremiumDE\IDL_ResultList\KelkooPremiumDE::class);
                $idlResultList = $idlResultList->getResultList($variationIdList, $settings);
            }

            //Creates an array with the variationId as key to surpass the sorting problem
            if(isset($idlResultList) && $idlResultList instanceof RecordList)
            {
                $this->createIdlArray($idlResultList);
            }

            foreach($resultData['documents'] as $item)
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

                $data = [
                    'category'      => $this->elasticExportHelper->getCategory((int)$item['data']['defaultCategories'][0]['id'], $settings->get('lang'), $settings->get('plentyId')),
                    'marke'         => $this->elasticExportHelper->getExternalManufacturerName((int)$item['data']['item']['manufacturer']['id']),
                    'title' 		=> $this->elasticExportHelper->getName($item, $settings),
                    'description'   => $this->elasticExportHelper->getDescription($item, $settings, 256),
                    'price' 	    => number_format((float)$this->idlVariations[$item['id']]['variationRetailPrice.price'], 2, '.', ''),
                    'deliverycost' 	=> $deliveryCost,
                    'url' 		    => $this->elasticExportHelper->getUrl($item, $settings, true, false),
                    'image'		    => $this->elasticExportHelper->getMainImage($item, $settings),
                    'availability'  => $this->elasticExportHelper->getAvailability($item, $settings),
                    'offerid'       => $item['id'],
                    'unitaryPrice'  => $this->elasticExportHelper->getBasePrice($item, $this->idlVariations[$item['id']]),
                    'ean'           => $this->elasticExportHelper->getBarcodeByType($item, $settings->get('barcode')),
                ];

                $this->addCSVContent(array_values($data));
            }
        }
    }

    /**
     * @param RecordList $idlResultList
     */
    private function createIdlArray($idlResultList)
    {
        if($idlResultList instanceof RecordList)
        {
            foreach($idlResultList as $idlVariation)
            {
                if($idlVariation instanceof Record)
                {
                    $this->idlVariations[$idlVariation->variationBase->id] = [
                        'itemBase.id' => $idlVariation->itemBase->id,
                        'variationBase.id' => $idlVariation->variationBase->id,
                        'variationRetailPrice.price' => $idlVariation->variationRetailPrice->price,
                    ];
                }
            }
        }
    }
}