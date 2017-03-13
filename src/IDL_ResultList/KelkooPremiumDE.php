<?php

namespace ElasticExportKelkooPremiumDE\IDL_ResultList;

use Plenty\Modules\Item\DataLayer\Contracts\ItemDataLayerRepositoryContract;
use Plenty\Modules\Helper\Models\KeyValue;
use Plenty\Modules\Item\DataLayer\Models\RecordList;

class KelkooPremiumDE
{
    const KELKOO_PREMIUM_DE = 6.00;
    /**
     * @param array $variationIds
     * @param KeyValue $settings
     * @return RecordList|string
     */
    public function getResultList($variationIds, $settings)
    {
        $referrer = $settings->get('referrerId') ? $settings->get('referrerId') : self::KELKOO_PREMIUM_DE;

        if(is_array($variationIds) && count($variationIds) > 0)
        {
            $searchFilter = array(
                'variationBase.hasId' => array(
                    'id' => $variationIds
                )
            );

            $resultFields = array(
                'itemBase' => array(
                    'id',
                ),

                'variationBase' => array(
                    'id'
                ),

                'variationRetailPrice' => array(
                    'params' => array(
                        'referrerId' => $referrer,
                    ),
                    'fields' => array(
                        'price',
                    ),
                ),
            );

            $itemDataLayer = pluginApp(ItemDataLayerRepositoryContract::class);
            /**
             * @var ItemDataLayerRepositoryContract $itemDataLayer
             */
            $itemDataLayer = $itemDataLayer->search($resultFields, $searchFilter);
            return $itemDataLayer;
        }
        return '';
    }
}