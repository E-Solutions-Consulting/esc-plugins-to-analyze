<?php

namespace CheckoutWC\SmartyStreets\PhpSdk;

interface Sender
{
    function send(Request $request);
}
