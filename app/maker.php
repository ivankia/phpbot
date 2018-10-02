<?php

use ccxt\bitmex;

$root = dirname(dirname(__FILE__));

include $root . '/ccxt.php';

date_default_timezone_set ('UTC');

$exchange = new bitmex([
    'apiKey' => 'iNuEcSOdC7F735D65sI6PH3C',
    'secret' => 'A5kS-mBKf-GlBLATTkGekuYVD6nH2hp16P4YGM9Q-osfZTB9',
    'enableRateLimit' => true,
]);

$symbol            = 'BTC/USD';
$orderBookWidth    = 3;
$orderBookPosition = $orderBookWidth - 1;
$fee               = 0.025 / 100;
$amount            = 100; // USD

$orderBook = $exchange->fetchOrderBook($symbol, $orderBookWidth);

$longPrice  = $orderBook['bids'][2][0];
$shortPrice = $orderBook['asks'][2][0];

$longSL  = fixPrice($longPrice - $longPrice * $fee, 0.5);
$shortSL = fixPrice($shortPrice + $shortPrice * $fee, -0.5);
$longTP  = fixPrice($longPrice + $longPrice * 0.01, 0.5);
$shortTP = fixPrice($shortPrice - $shortPrice * 0.01, -0.5);

$paramsLongStopLoss = [
    'execInst' => 'Close,LastPrice,ParticipateDoNotInitiate',
    'leavesQty' => $amount,
    'ordStatus' => 'New',
    'ordType' => 'StopLimit',
    'orderQty' => $amount,
    'side' => 'Sell',
//    'price' => 6000, //$longSL
    'price' => $longSL+100,
//    'stopPx' => 6000, //$longSL
    'stopPx' => $longSL+100,
    'symbol' => 'XBTUSD',
    'timeInForce' => 'GoodTillCancel',
];

$paramsLongLimitOrder = [
    'execInst'    => 'ParticipateDoNotInitiate',
    'leavesQty'   => $amount,
    'ordType'     => 'Limit',
    'orderQty'    => $amount,
//    'price'       => 6500, //$longPrice
    'price'       => $longPrice,
    'ordStatus'   => 'New',
    'side'        => 'Buy',
    'symbol'      => 'XBTUSD',
    'timeInForce' => 'GoodTillCancel',
];

$paramsLongTakeProfit = [
    'execInst' => 'Close,LastPrice,ParticipateDoNotInitiate',
    'leavesQty' => $amount,
    'ordStatus' =>  'New',
    'ordType' => 'LimitIfTouched',
    'orderQty' => $amount,
    'side' => 'Sell',
//    'price' => 6999, // $lognTP
    'price' => $longTP-100,
//    'stopPx' => 7000, //$lognTP
    'stopPx' => $longTP-100,
    'symbol' => 'XBTUSD',
    'timeInForce' => 'GoodTillCancel'
];

$stopLossId = null;
$takeProfitId = null;
$orderPosition = null;

$params = [
    'orders' => [
        $paramsLongStopLoss,
        $paramsLongTakeProfit,
        $paramsLongLimitOrder
    ]
];

$result = $exchange->createOrderBulk($params);

if (!isset($result) || !count($result) == 3) {
    if (count($result) > 0) {
        foreach ($result as $order) {
            $exchange->deleteAllOrders(['symbol' => $symbol, ['Content-Type' => 'application/x-www-form-urlencoded']]);
//            $exchange->cancel_order($order['orderID'], $symbol);
        }
    }

    echo "Wrong orders!\n";
    die();
}

$stopLossId    = $result[0]['orderID'];
$takeProfitId  = $result[0]['orderID'];
$orderPosition = $result[0]['orderID'];

$orderStopLoss  = $exchange->fetch_order($stopLossId, $symbol, ['ordStatus' => 'Canceled']);
$orderTakeProfit = $exchange->fetch_order($takeProfitId, $symbol, ['ordStatus' => 'Canceled']);

if (!empty($orderStopLoss) || !empty($orderTakeProfit)) {
    $result = $exchange->fetch('https://testnet.bitmex.com/api/v1/order/all', 'DELETE');
    echo 'SL/TP Canceled' . "\n" . $result . "\n";
    die('Exit');
}

echo 'Done!' . "\n";

function fixPrice($value, $appendix = 0.5) {
    if ($value - round($value)) {
        return round($value) + $appendix;
    }

    return $value;
}