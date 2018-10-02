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

$longSL  = $exchange->fixPrice($longPrice - $longPrice * $fee, 0.5);
$shortSL = $exchange->fixPrice($shortPrice + $shortPrice * $fee, -0.5);
$longTP  = $exchange->fixPrice($longPrice + $longPrice * 0.01, 0.5);
$shortTP = $exchange->fixPrice($shortPrice - $shortPrice * 0.01, -0.5);

$paramsLongStopLoss = [
    'execInst' => 'Close,LastPrice,ParticipateDoNotInitiate',
    'leavesQty' => $amount,
    'ordStatus' => 'New',
    'ordType' => 'StopLimit',
    'orderQty' => $amount,
    'side' => 'Sell',
//    'price' => 6000, //$longSL
    'price' => $longSL,
//    'stopPx' => 6000, //$longSL
    'stopPx' => $longSL,
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
    'price' => $longTP,
//    'stopPx' => 7000, //$lognTP
    'stopPx' => $longTP,
    'symbol' => 'XBTUSD',
    'timeInForce' => 'GoodTillCancel'
];

$stopLossId = null;
$takeProfitId = null;
$orderPositionId = null;

$params = [
    'orders' => [
        $paramsLongStopLoss,
        $paramsLongTakeProfit,
        $paramsLongLimitOrder
    ]
];

echo 'Set order: ' . $longPrice . "\n";
echo 'Set TP: ' . $longTP . "\n";
echo 'Set SL: ' . $longSL . "\n";

$result = $exchange->createOrderBulk($params);

echo 'Bulk orders: ' . "\n";
echo json_encode($result) . "\n";

if (!isset($result) || !count($result) == 3) {
    if (count($result) > 0) {
        $exchange->deleteAllOrders(['symbol' => 'XBTUSD']);
    }

    echo "Wrong orders!\n";
    die();
}

$stopLossId      = $result[0]['orderID'];
$takeProfitId    = $result[0]['orderID'];
$orderPositionId = $result[0]['orderID'];

$orderStopLoss  = $exchange->fetch_order($stopLossId, $symbol);
$orderTakeProfit = $exchange->fetch_order($takeProfitId, $symbol);

if (!isOrderAndStopsApplied($symbol, $orderPositionId, $stopLossId, $takeProfitId)) {
    echo 'All orders canceled' . "\n";
    die();
}

echo 'Orders set' . "\n";

function isOrderAndStopsApplied($symbol, $orderPositionId, $stopLossId, $takeProfitId) {
    global $exchange;

    echo "Check losses status...\n";

    $orderStopLoss  = $exchange->fetch_order($stopLossId, $symbol);
    $orderTakeProfit = $exchange->fetch_order($takeProfitId, $symbol);

    if ((empty($orderStopLoss) || !isset($orderStopLoss['info']['ordStatus'])) &&
        (empty($orderTakeProfit) || !isset($orderTakeProfit['info']['ordStatus']))) {
        echo "Orders are not processed...\n";
        return isOrderAndStopsApplied($symbol, $orderPositionId, $stopLossId, $takeProfitId);
    }

    if ($orderStopLoss['info']['ordStatus'] == 'Canceled' && $orderTakeProfit['info']['ordStatus'] == 'Canceled') {
        echo "Orders are canceled...\n";

        return false;
    }

    return true;
}