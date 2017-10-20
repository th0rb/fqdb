<?php

$autoloadFiles = array(__DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../../autoload.php');

foreach ($autoloadFiles as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
    }
}

function timeRun($ru, $rus, $index) {
    return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
    -  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
}

function adOsConvert($osName) {
    switch ($osName) {
        case 'ALL':
            return 99;
        case 'iOS':
            return 1;
        case 'Android':
            return 2;
        default:
            var_dump ('Unknown OS: ' . $osName);
            die;
    }
}

function adCountryConvert($countryArray, $countryData) {

    if ($countryArray['undef']) {
        return [999]; //ALL countries
    }

    $countryIds = [];
    foreach ($countryArray['list'] as $countryCode) {
        if(array_key_exists($countryCode, $countryData)) {
            $countryIds[] = $countryData[$countryCode];
        }
    }
    return $countryIds;
}

$createString = "CREATE TABLE `Ad_test` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`clickky_id` bigint unsigned DEFAULT NULL,
`application_id` int(11) unsigned DEFAULT NULL,
`name` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
`advertiser_id` int(11) unsigned NOT NULL,
`platform` tinyint(3) unsigned DEFAULT NULL,
`adtype` tinyint(3) unsigned DEFAULT NULL,
`price` double DEFAULT 0,
`link` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
`limitday` int(11) unsigned DEFAULT NULL,
`limitall` int(11) unsigned DEFAULT NULL,
`leads_overall` int(11) unsigned NOT NULL DEFAULT '0',
`trafficType` tinyint(3) NOT NULL DEFAULT '1' COMMENT '0 - non incent; 1 - incent',
`isfree` tinyint(3) unsigned DEFAULT NULL,
`avg_cr` double DEFAULT NULL,
`has_suitable_creatives` tinyint(1) DEFAULT NULL,
`device_types` smallint(5) unsigned NOT NULL DEFAULT '0',
`targeting_os` tinyint DEFAULT NULL,

PRIMARY KEY (`id`),
KEY `adtype_idx` (`adtype`),
KEY `old_idx` (`clickky_id`),
KEY `blacklist_join_helper` (`advertiser_id`) USING HASH,
KEY `active_adtype_platform` (`adtype`,`platform`) USING BTREE,
KEY `device_types` (`device_types`),
KEY `targeting_os` (`targeting_os`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

$createCountryTargeting = "CREATE TABLE `Ad_countries` (
`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
`ad_id` int(11) unsigned NOT NULL,
`country_id` int(11) unsigned NOT NULL,

PRIMARY KEY (`id`),
KEY `ad_id` (`ad_id`),
KEY `country_id` (`country_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";


use \Readdle\Database\FQDB;

$fqdb = new FQDB('mysql:host=localhost;dbname=clickky_ci', 'clickky', 'clickky_pass');


$res = $fqdb->execute("DROP TABLE IF EXISTS `Ad_test`");
$res = $fqdb->execute($createString);


$res = $fqdb->execute("DROP TABLE IF EXISTS `Ad_countries`");
$res = $fqdb->execute($createCountryTargeting);


$countryData = [];
$fqdb->queryTableCallback("SELECT `id`, `country_code` FROM `country`", [],function ($row) use (&$countryData) {
    //global $countryData;
    $countryData[$row['country_code']] = $row['id'];
    return;
});

/**
 *$countryData =
 * array(249) {
    ["AD"]=>
        string(3) "560"
    ["AE"]=>
        string(3) "239"
    ...}
  */

//================= export =====================\\

$selectAdString = "SELECT
        id as clickkyId, application_id as appId, name, iduser as adv_id, platform, adtype as adType, link,
        limitday, limitall, leads_overall as leadsOverall, trafficType as trafficType, isfree as free, avg_cr as cr,
        price, has_suitable_creatives as creatives, device_types as deviceTypes, raw_targeting as targeting
            FROM `active_Ad`";

$adDataArray = $fqdb->queryTable($selectAdString);


//fill with data
$insertAdString = "INSERT INTO `Ad_test` VALUES (
  NULL, :clickkyId, :appId, :name, :adv_id, :platform, :adType, :price, :link, :limitday, :limitall, :leadsOverall,
  :trafficType, :free, :cr, :creatives, :deviceTypes, :osId)";


foreach ($adDataArray as $adData) {

    $targetingInfo = json_decode($adData['targeting'], true);

    $adOsArray = array_keys($targetingInfo['os']);
    if (count($adOsArray) == 1) {
        //only one OS
        $adOs = reset($adOsArray);
    } else {
        $adOs = 'ALL';
    }

    unset($adData['targeting']);

    foreach($adData as $key => $val) {
        $adData[':' . $key] = $val; //add ":" to keys to fit placeholders naming convention
        unset($adData[$key]);
    }
    $adData[':osId'] = adOsConvert($adOs);

    $adId = $fqdb->insert($insertAdString, $adData);
    $insertCountries = adCountryConvert($targetingInfo['country'], $countryData);

    $insertCountriesQuery = "INSERT INTO `Ad_countries` VALUES (NULL, :adId, :countryId)";
    foreach ($insertCountries as $countryId) {
        $data = [
            ':adId' => $adId,
            ':countryId' => $countryId
        ];
        $fqdb->insert($insertCountriesQuery, $data);
    }
}


echo "done\n";
die;


//lets start selecting data
$rustart = getrusage();
$time1 = microtime(true);

$query1 = "SELECT ad.* FROM `Ad_test` ad
            INNER JOIN `Ad_countries` adc ON ad.id = adc.ad_id
            WHERE (adc.country_id = 155 OR adc.country_id=999)  AND ad.targeting_os=1";

$query2 = "SELECT ad.* FROM `Ad_test` ad
            INNER JOIN `Ad_countries` adc ON ad.id = adc.ad_id
            WHERE (adc.country_id = 257 OR adc.country_id=999)
            AND ad.targeting_os=2 AND ad.avg_cr > 0 AND ad.trafficType=1";

$query3 = "SELECT ad.* FROM `Ad_test` ad
            INNER JOIN `Ad_countries` adc ON ad.id = adc.ad_id
            WHERE ad.isfree = 1
            AND ad.targeting_os=1 AND ad.avg_cr > 0 AND ad.trafficType=0";

$fqdb->queryTable($query1);
$fqdb->queryTable($query2);
$fqdb->queryTable($query3);

$ru = getrusage();
echo $ad_count . " random campanies stats:\n";
echo "This process used " . timeRun($ru, $rustart, "utime") .
    " ms for its computations\n";
echo "It spent " . timeRun($ru, $rustart, "stime") .
    " ms in system calls\n";

$time2 = microtime(true);
echo 'script execution time: ' . ($time2 - $time1) . " seconds\n"; //value in seconds

echo "done\n";

/*
 *
 * {
 *      "os": *  {"iOS":{"list":[],"undef":false,"is_allow":true}},
 *      "country":{"list":["RU"],"undef":false,"is_allow":true},
 *      "manufacturer":[],
 *      "device_type":{"list":["smartphone"],"undef":false,"is_allow":true}
 * }
 *
 * countries ids = 152-729
 *
 */
