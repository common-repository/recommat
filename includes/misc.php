<?php

defined( 'ABSPATH' ) or die( 'Keep Quit' );

function recommat_register_endpoint() {
    global $wp;
    
    //recommat_import
    if ($wp->request == 'recommat_import') {
        recommat_csv_all();
    }
}

// print a big table of csv for each item ID with their upsell
function recommat_csv_all() {
    $redis = recommat_get_redis();

    if(!$redis) return;

    //20014,"21623,28105"
    $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);

    $output = fopen('php://output', 'w');
    $it = NULL;

    while($arr_mems = $redis->scan($it, "item:*")) {
        foreach($arr_mems as $str_mem) {
            //loop all data from redis
            // $product_id = "20014";
            // $rec = "21623,28105";
            $rec = "";
            $i = explode(':', $str_mem);
            $product_id = $i[1];
            // $rec = recommat_get($product_id);
            // var_dump($rec);
            fputcsv($output, array($product_id, $rec));
        }
    }

    fclose($output);
    exit;
}

// handler of GET /recommat_import
add_action('parse_request', 'recommat_register_endpoint', 0);
