<?php

namespace CheckoutWC\SmartyStreets\PhpSdk\US_Enrichment\GeoReference;

use CheckoutWC\SmartyStreets\PhpSdk\ArrayUtil;
class CensusTractEntry
{
    //region [ Fields ]
    public $code;
    //endregion
    public function __construct($obj = null)
    {
        if ($obj == null) {
            return;
        }
        $this->code = ArrayUtil::getField($obj, "code");
    }
}
