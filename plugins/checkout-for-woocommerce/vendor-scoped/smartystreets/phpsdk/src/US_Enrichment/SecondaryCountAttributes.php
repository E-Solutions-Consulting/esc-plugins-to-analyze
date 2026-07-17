<?php

namespace CheckoutWC\SmartyStreets\PhpSdk\US_Enrichment;

use CheckoutWC\SmartyStreets\PhpSdk\ArrayUtil;
class SecondaryCountAttributes
{
    //region [ Fields ]
    public $count;
    //endregion
    public function __construct($obj = null)
    {
        if ($obj == null) {
            return;
        }
        $this->count = ArrayUtil::getField($obj, "count");
    }
}
