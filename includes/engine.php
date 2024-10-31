<?php

function recommat_get_redis() {
    static $redis = null;

    if ($redis === null) {
        $redis = new Redis();
        if(!$redis) {
            return false;
        }
        // $redis->connect('localhost', 6379);
        $options = get_option( 'recommat_settings' );
        
        if(!is_numeric($options['recommat_redis_port'])) {
            return false;
        }
        try {
            $redis->connect($options['recommat_redis_url'], $options['recommat_redis_port']);
            $redis->auth($options['recommat_redis_auth']);
        } catch (Exception $e) {
            return false;
        }

    }
    return $redis;
}


/**
 * get raw scores from engine
 * 
 * @param Array/INT $product_ids an array of product ids
 * @param BOOL $cached if true, can get it from cache. If false, get it from engine raw data and store as cache
 * 
 * @return Array $recommended_products list of DESC ordered product IDs as key, value as score
 * 
 **/
function recommat_get_model($product_ids = array(), $cached = true) {
    if(is_numeric($product_ids)) {
        $product_ids = array($product_ids);
    }

    $hash = 'cache:'.md5(implode(',', $product_ids));
    $keys = array();
    foreach ($product_ids as $product_id) {
        $keys[] = 'item:'.$product_id.':orders';
    }
    $redis = recommat_get_redis();
    
    if(!$redis) return array();

    if($cached) {
        // try to get info from cache
        if($redis->exists($hash)) {
            $recommend_products = $redis->zRevRangeByScore($hash, '+inf', '-inf', ['withscores' => TRUE, 'LIMIT' => array(0, 10)]);
            return $recommend_products;
        }
    }

    // otherwise if no cache, or skip cache, generate one

    //Get all orders for this items
    //SUNION item:milk:orders items:banana:orders
    $order_keys = $redis->sUnion($keys);

    $keys = array();
    foreach ($order_keys as $order_id) {
        $keys[] = 'order:'.$order_id.':items';
    }
    //Join all order’s products
    //SUNIONSTORE order:U1:all_recommended order:U1:items order:U2:items order:U3:items
    // $all_products = $redis->sUnion($keys);
    
    //Find the diff with current order
    // SDIFF order:U1:all_recommended order:U1:items
    // $recommend_products = array_diff($all_products, $product_ids);

    // zunionstore result 3 key1 key2 key3 aggregate sum
    $redis->zunionstore($hash, $keys, array_fill(0, count($keys), 1), 'SUM');
    //remove itself from the list
    foreach ($product_ids as $product_id) {
        $redis->zRem($hash, $product_id);
    }
    // zrevrangebyscore result +inf -inf withscore
    $recommend_products = $redis->zRevRangeByScore($hash, '+inf', '-inf', ['withscores' => TRUE, 'LIMIT' => array(0, 10)]);
    return $recommend_products;
}

?>