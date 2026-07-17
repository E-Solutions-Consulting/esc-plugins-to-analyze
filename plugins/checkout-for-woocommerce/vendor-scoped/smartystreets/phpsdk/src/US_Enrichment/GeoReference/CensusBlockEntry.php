<?php

namespace CheckoutWC\SmartyStreets\PhpSdk\US_Enrichment\GeoReference;

use CheckoutWC\SmartyStreets\PhpSdk\ArrayUtil;
class CensusBlockEntry
{
    //region [ Fields ]
    public $accuracy, $geoid;
    //endregion
    public function __construct($obj = null)
    {
        if ($obj == null) {
            return;
        }
        $this->accuracy = ArrayUtil::getField($obj, "accuracy");
        $this->geoid = ArrayUtil::getField($obj, "geoid");
    }
}
