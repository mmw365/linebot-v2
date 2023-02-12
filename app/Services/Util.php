<?php
 
namespace App\Services;

use Illuminate\Support\Facades\Http;

class Util
{
    public static function toNarrowNumber($str) {
        $str = str_replace("０", "0", $str);
        $str = str_replace("１", "1", $str);
        $str = str_replace("２", "2", $str);
        $str = str_replace("３", "3", $str);
        $str = str_replace("４", "4", $str);
        $str = str_replace("５", "5", $str);
        $str = str_replace("６", "6", $str);
        $str = str_replace("７", "7", $str);
        $str = str_replace("８", "8", $str);
        $str = str_replace("９", "9", $str);
        return $str;
    }

    public static function createPasscode($len) {
        // First char should be non numeric
        $r = random_int(0, 25);
        $code = chr($r + 65);
        for($i = 1; $i < $len; $i++) {
            $r = random_int(0, 35);
            if($r < 10) {
                $code .= $r;
            } else {
                $code .= chr($r + 55);
            }
        }
        return $code;
    }
}