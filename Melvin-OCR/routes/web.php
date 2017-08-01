<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/googlevision',function(){
    $url = 'http://127.0.0.1/wordpress';
    $path = "C:\\Users\\CheahHong\\Desktop\\images";
    $user = "Melvin";
    //glob search through image files
    $files = glob($path."\\*.{jpg,png}", GLOB_BRACE);
//    $files = scandir($path);
//    $files = array_filter($files, function($file) {
//        return ($file != '.' && $file != '..' && $file != '.DS_Store' && $file != 'Thumbs.db');
//    });
//    print_r($files);
        
    $projectId = 'melvin-ocr';

    foreach ($files as $key=>$filepath) {
        //initiate new client
        $vision = new \Google\Cloud\Vision\VisionClient([
            'projectId' => $projectId,
        ]);
        
        //initiate desciption array
        $description = [];
        
        //get image file name from filepath
        $pos = strrpos($filepath, "\\");
        $imagename = $pos === false ? $filepath : substr($filepath, $pos + 1);
        
        $currentDate = date('Y-m-d');
         
        if (!file_exists(public_path()."\\OCR\\".$user."\\".$currentDate. "\\" .$imagename.".txt")) {
            // file doesn't exist
            // google vision isnt complete yet/failed
            print('Google Cloud Vision for '.$imagename.' begins\n');
            
            // [START text_detection]
            $imageText = $vision->image(file_get_contents($filepath), ['TEXT_DETECTION']);
            $resultText = $vision->annotate($imageText);
            if(!empty($resultText->text())){
                //replace new lines and multi spaces with single space
                $string = trim(preg_replace('/\s+/', ' ', $resultText->text()[0]->description()));
                array_push($description, $string);
            }

            // [START label_detection]
            $imageLabel = $vision->image(file_get_contents($filepath), ['LABEL_DETECTION']);
            $resultLabel = $vision->annotate($imageLabel);
            if(!empty($resultLabel->labels())){
                foreach ($resultLabel->labels() as $key=>$label) {
                    array_push($description, $label->description());
                }
            }

            $description = array_filter($description);

            if (!empty($description)) {
                $myObj['description'] = implode(' ', $description);
                $myObj['timestamp'] = date('Y-m-d H:i:s');
                $Obj = json_encode($myObj);
                echo $Obj;
                if (!is_dir(public_path()."\\OCR\\".$user."\\".$currentDate)) {
                  // dir doesn't exist, make it
                  mkdir(public_path()."\\OCR\\".$user."\\".$currentDate, 0777, true);
                }
                file_put_contents(public_path()."\\OCR\\".$user."\\".$currentDate. "\\" .$imagename.".txt", $Obj);
                
            }else{
                print('Google Cloud Vision for '.$imagename.' failed\n');
            }
        }else{
            print('Google Cloud Vision for '.$imagename.' has been completed\n');
        }
        
    }
    
})->name('OCR');

Route::get('/woo',function(){
    $woocommerce = new \Automattic\WooCommerce\Client(
        'http://127.0.0.1/wordpress', 
        'ck_d219e6eb8afd5fef33658df247277bef8f0f7f7c', 
        'cs_5144702af39d50ecafef230e678999688cdafea5',
        [
            'wp_api' => true,
            'version' => 'wc/v1',
        ]
    );
    
    $data = [
        'name' => 'New Premium Quality',
        'type' => 'simple',
        'regular_price' => '21.99',
        'description' => 'This is a white shirt =D',
        'short_description' => 'Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.',
        'categories' => [
            [
                'id' => 9
            ],
            [
                'id' => 14
            ]
        ],
        'images' => [
            [
                'name' => 'T-Shirt White Front',
                'src' => 'http://127.0.0.1/wordpress/wp-content/uploads/2017/08/multiple-2-1.jpg',
                'position' => 0
            ]
        ]
    ];

    print_r($woocommerce->post('products', $data));

//    $product = $woocommerce->get('products',['per_page'=>1]);
//    dd($product);
});


Route::get('/wp_upload',function(){
    $img = 'C:/Users/CheahHong/Desktop/images/marathon.jpg';
    $url = 'http://127.0.0.1/wordpress';
    $data = file_get_contents($img);
    $curl = curl_init($url."/wp-json/wp/v2/media");
    curl_setopt_array($curl, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic amlhaG9uZzk2OkRsR2NtZXdVSWtVR2tlTFBHQ3hSNFV6NQ==',
            "Cache-Control: no-cache",
            "Content-Disposition: Attachment; filename=mara.jpg",
            "Content-Type: application/x-www-form-urlencoded",
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST =>1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_VERBOSE => 1,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_POSTFIELDS => $data
    ]);

    $result = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    
    if($result && !$error){
        print(json_decode($result, true)['guid']['raw']);    
    }else{
        print('upload failed');    
    }
    
//    dd(\GuzzleHttp\json_decode($result,TRUE), $error);
//    return 'done';
});


Route::get('/wp_upload_melvin',function(){
    $img = 'C:/Users/CheahHong/Desktop/images/marathon.jpg';
    $url = 'http://fotogie.com';
    $data = file_get_contents($img);
    $curl = curl_init($url."/wp-json/wp/v2/media");
    curl_setopt_array($curl, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic YWRtaW4yOmZIV3dWT05oeTB2SkxodEJBTWM2TEFzbA==',
            "Cache-Control: no-cache",
            "Content-Disposition: Attachment; filename=marathon.jpg",
            "Content-Type: application/x-www-form-urlencoded",
        ],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST =>1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_VERBOSE => 1,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_POSTFIELDS => $data
    ]);

    $result = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    dd(\GuzzleHttp\json_decode($result,TRUE), $error);

    return 'done';

});

Route::get('/wooMelvin',function(){
    
    $woocommerce = new \Automattic\WooCommerce\Client(
        'http://127.0.0.1/wordpress', 
        'ck_a746559e4747828615fb166f306ba796750805fd', 
        'cs_5144702af39d50ecafef230e678999688cdafea5',
        [
            'wp_api' => true,
            'version' => 'wc/v1',
        ]
    );
    
//    print_r($woocommerce->get('products'));
    $product = $woocommerce->get('products',['per_page'=>2]);
    dd($product);
});