<?php

namespace App\Http\Controllers;

use App\Http\Requests;
use Illuminate\Http\Request;

class AssetsController extends Controller
{

    private static function isAlreadyAsset($line)
    {
        if (strpos($line, '{{ secure_asset(\'') || strpos($line, '{{ asset(\'') || strpos($line, '\') }}') || strpos($line, '{}')) {
            return true;
        } else return false;
    }

    private static function hasProtocol($line)
    {
        $protocol = strpos($line, 'http') ? strpos($line, 'http') : strpos($line, 'https');
        $agnostic = strpos($line, 'href="//') || strpos($line, 'href=\'//') || strpos($line, 'src="//') || strpos($line, 'src=\'//');
        if ($protocol || $agnostic)
            return true;
        else return false;
    }

    public function apply(Request $request)
    {
        $original = $request->string;
        $assetLeft = $request->secure ? '{{ secure_asset(\'' : '{{ asset(\'';
        $assetRight = '\') }}';
        $href = 'href="';
        $src = 'src="';
        $final = [];
        $lines = preg_split('/([^>]+[>]+)/', $original, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        foreach ($lines as $line) {
            $line = trim($line);
            $hrefPos = strpos($line, $href);
            $srcPos = strpos($line, $src);
            if (preg_match('/<\/script>/', $line)) {
                $final[sizeof($final) - 1] = last($final) . $line;
                continue;
            }

            if (self::hasProtocol($line) || self::isAlreadyAsset($line) || (!$hrefPos && !$srcPos)) {
                array_push($final, $line);
                continue;
            }

            $extension = self::getExtension($line);
            if ($hrefPos) {
                $left = substr_replace($line, $assetLeft, $hrefPos + strlen($href), 0);
                if ($extensionPos = strpos($left, $extension))
                    array_push($final, substr_replace($left, $assetRight, $extensionPos + strlen($extension), 0));
            } else if ($srcPos) {
                $left = substr_replace($line, $assetLeft, $srcPos + strlen($src), 0);
                if ($extensionPos = strpos($left, $extension))
                    array_push($final, substr_replace($left, $assetRight, $extensionPos + strlen($extension), 0));
            } else {
                array_push($final, $line);
            }
        }

        return json_encode($final);
    }

    private function getExtension($string)
    {
        $extensions = ['.js', '.css', '.jpeg', '.png', '.xml', '.ico', '.jpg', '.gif', '.pdf'];
        foreach ($extensions as $extension) {
            if ($posBegin = strpos($string, $extension)) {
                $posEnd = strpos($string, '"', $posBegin);
                $version = substr($string, $posBegin + strlen($extension), $posEnd - $posBegin - strlen($extension));
                return $extension . $version;
            }
        }
        return " ";
    }
}
