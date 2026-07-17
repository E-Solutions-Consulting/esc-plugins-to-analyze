<?php

namespace CheckoutWC\SmartyStreets\PhpSdk;

interface Serializer
{
    function serialize($obj);
    function deserialize($payload);
}
