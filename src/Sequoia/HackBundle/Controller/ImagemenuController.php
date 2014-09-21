<?php

namespace Sequoia\HackBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\View\View;
use FOS\Rest\Util\Codes;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use TesseractOCR;
use Buzz\Browser;

class ImagemenuController extends Controller
{

    public function getImagemenuAction()
    {
            $nAppId    = '07153193';
            $nAppKey   = '6219c8eaf48616c9ebaf0e031727e5fa';

            $buzz      = $this->get('buzz');
            $request   = $this->getRequest();
            $imageUrl  = $request->get('image_url');

            $imageContents = file_get_contents($imageUrl);
            $path = '/tmp/'.time().'.jpg';
            file_put_contents($path, $imageContents);

            $tesseract = new TesseractOCR($path);
            $tesseract->setTempDir('/tmp');
            //$tesseract->setWhitelist(range('A','Z'), range('a','z'));
            $data = strtolower($tesseract->recognize());

            $blackListedKeys = array(
                'just',
                'starts',
                'starters',
                'vegetarian'
            );

            $breakKeys = array(
                ']',
                '[',
                '/',
                '\\'
            );

            //str_replace($breakKeys, '' , $data);

            $words = preg_split('/[\s]+/', $data, -1, PREG_SPLIT_NO_EMPTY);

            $items = array();
            $combinedWord = null;

            $fields = 'nf_ingredient_statement,nf_calories,nf_total_fat,nf_serving_weight_grams';

            $id = 0;
            foreach ($words as $word) {
                if (!in_array($word, $blackListedKeys)) {
                    if (intval($word)) {
                        $combinedWord = trim($combinedWord);
                        $items[$id]['id'] = $id;
                        $items[$id]['name'] = $combinedWord;

                        $combinedWord = urlencode($combinedWord);

                        $nutritionUrls[] = "https://api.nutritionix.com/v1_1/search/$combinedWord?results=0:1&fields=item_name,nf_ingredient_statement,nf_calories,nf_total_fat,nf_serving_weight_grams&appId=$nAppId&appKey=$nAppKey";

                        $googleUrls[] = "https://ajax.googleapis.com/ajax/services/search/images?v=1.0&q=$combinedWord&imgsz=large";

                        $combinedWord = null;
                        $id = $id + 1;
                        continue;
                    } else {
                        $combinedWord .= ' '.$word;
                    }
                }
            }

            $googleData = $this->multiCurl($googleUrls);
            $nutritionData = $this->multiCurl($nutritionUrls);

            $id = 0;
            foreach ($googleData as $googleDatum) {
                if ($googleDatum) {
                    $result = json_decode($googleDatum[0], true);
                    $results = $result['responseData']['results'];
                    $i = 0;
                    foreach ($results as $result) {
                        $items[$id]['image_urls'][] = $result['url'];
                        $i = $i + 1;
                        if ($i >= 4) {
                            break;
                        }
                    }
                }
                $id = $id + 1;
            }

            $id = 0;
            foreach ($nutritionData as $nutritionDatum) {
                if ($nutritionDatum) {
                    $result = json_decode($nutritionDatum[0],true);
                    $fields = $result['hits'][0]['fields'];

                    $items[$id]['item_name'] = $fields['item_name'];
                    $items[$id]['ingredients'] = $fields['nf_ingredient_statement'];
                    $items[$id]['calories'] = $fields['nf_calories'];
                    $items[$id]['fat'] = $fields['nf_total_fat'];
                    $items[$id]['serving_weight_grams'] = $fields['nf_serving_weight_grams'];
                }
                $id = $id + 1;
            }

            return View::create(array_values($items));
    }

    /**
    * Takes array of URL's
    */
    protected function multiCurl($urls)
    {
        $nodes = $urls;
        $nodeCount = count($nodes);

        $curlArr = array();
        $master = curl_multi_init();

        for($i = 0; $i < $nodeCount; $i++)
        {
            $url = $nodes[$i];
            $curlArr[$i] = curl_init($url);
            curl_setopt($curlArr[$i], CURLOPT_RETURNTRANSFER, true);
            curl_multi_add_handle($master, $curlArr[$i]);
        }

        do {
            curl_multi_exec($master,$running);
        } while($running > 0);


        for($i = 0; $i < $nodeCount; $i++)
        {
            $results[$i][] = curl_multi_getcontent($curlArr[$i]);
        }

        return $results;
    }
}