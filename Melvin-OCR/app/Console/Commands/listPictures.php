<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class listPictures extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'list:pictures {user : to be added to tag} {event : to be added to category} {folderPath : full path of image location}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'OCR, UPLOAD And INSERT all the pictures in a folder to wordpress';
    
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
//        $wpUrl = config('app.wp_url');
//        $wpPw = config('app.wpe_pw');
//        $wcKey = config('app.wc_key');
//        $wcSecret = config('app.wc_secret');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //init path, user, web url, projectID
        $path = $this->argument('folderPath');
        $user = $this->argument('user');
        $eventName = $this->argument('event');
        
        //$url = config('app.wp_url');
        $projectId = config('app.gc_project_id');
        
        //glob search through image files
        $files = glob($path."\\*.{JPEG,JPG,PNG,jpeg,jpg,png}", GLOB_BRACE);
        
        foreach ($files as $key=>$filePath) {
            $this->OCR($filePath,$projectId,$user);    
        }
        
    }  
    
    public function OCR($filePath,$projectId,$user)
    {
        //init variables
        $vision = new \Google\Cloud\Vision\VisionClient([
            'projectId' => $projectId,
        ]);
        $description = [];
        $currentDate = date('Y-m-d');
        
        //get image file name from filepath
        $imageName = $this->extractFileName($filePath);
         
        if (!$this->checkOCRFileExists($user,$currentDate,$imageName)) {
            // file doesn't exist
            // google cloud vision havent complete yet/failed
            print('Google Cloud Vision for '.$imageName.' begins' .PHP_EOL);
            
            
            // [START text_detection]
            $stringText = $this->textOCR($vision,$filePath);
            array_push($description, $stringText);
            
            // [START label_detection]
            $arrayLabel = $this->labelOCR($vision,$filePath);
            foreach ($arrayLabel as $label) {
                array_push($description, $label->description());
            }

            //export OCR result to text file
            $description = array_filter($description);
            $desc = implode(' ', $description);
            if (!empty($description)) {
                print($this->exportOCR($description,$user,$currentDate,$imageName) .PHP_EOL);
            
                //[START picture uploading]
                $this->uploadImage($desc,$filePath,$user,$currentDate,$imageName);
            }
        }else{
            print('Google Cloud Vision for '.$imageName.' has been completed' .PHP_EOL);
            $jsonData = file_get_contents(public_path()."\\OCR\\".$user."\\".$currentDate. "\\" .$imageName.".txt");
            $jsonObj = json_decode($jsonData, true);
            //[START picture uploading]
            $this->uploadImage($jsonObj['description'],$filePath,$user,$currentDate,$imageName);
        }
    }  
    
    public function extractFileName($filePath)
    {
        $pos = strrpos($filePath, "\\");
        $imageName = $pos === false ? $filePath : substr($filePath, $pos + 1);
        
        return $imageName;
    }
    
    public function checkOCRFileExists($user,$currentDate,$imageName)
    {
        return (file_exists(public_path()."\\OCR\\".$user."\\".$currentDate. "\\" .$imageName.".txt"));
    }
    
    public function textOCR($visionClient,$filePath)
    {
        $image = $visionClient->image(file_get_contents($filePath), ['TEXT_DETECTION']);
        $result = $visionClient->annotate($image);
        
        //if result not empty
        if(!empty($result->text())){
            //replace new lines and multi spaces with single space
            $string = trim(preg_replace('/\s+/', ' ', $result->text()[0]->description()));
            
            return $string;
        }
    }
    
    public function labelOCR($visionClient,$filePath)
    {
        $image = $visionClient->image(file_get_contents($filePath), ['LABEL_DETECTION']);
        $result = $visionClient->annotate($image);
        if(!empty($result->labels())){
            return $result->labels();
        }
    }
    
    public function exportOCR($description,$user,$currentDate,$imageName)
    {
        $myObj['description'] = implode(' ', $description);
        $myObj['timestamp'] = date('Y-m-d H:i:s');
        $Obj = json_encode($myObj);

        if (!is_dir(public_path()."\\OCR\\".$user."\\".$currentDate)) {
          // dir doesn't exist, make it
          mkdir(public_path()."\\OCR\\".$user."\\".$currentDate, 0777, true);
        }
        file_put_contents(public_path()."\\OCR\\".$user."\\".$currentDate. "\\" .$imageName.".txt",$Obj);
        return 'Google Cloud Vision for '.$imageName.' has succeeded';
    }
    
    public function uploadImage($desc,$filePath,$user,$currentDate,$imageName)
    {
        $url = config('app.wp_url');
        
        if (!$this->checkUploadFileExists($user,$currentDate,$imageName)) {
            // file doesn't exist
            // image uploading havent complete yet/failed
            print('Image Uploading for '.$imageName.' begins' .PHP_EOL);
            
            // [START image uploading]
            $data = file_get_contents($filePath);
            $result = $this->curlUpload($url,$data,$imageName);
            
            //if result not empty
            if($result !=='upload failed'){
                $imageUrl= json_decode($result, true)['guid']['raw'];
                $mediaId = json_decode($result, true)['id'];
                print($this->exportUploadResult($mediaId,$imageUrl,$user,$currentDate,$imageName) .PHP_EOL);
                //[START product inserting]
                $this->insertProduct($mediaId,$imageUrl,$desc,$user,$currentDate,$imageName);
            }
            
        }else{
            print 'Image Uploading for '.$imageName.' has been completed' .PHP_EOL;
            $jsonData = file_get_contents(public_path()."\\Upload\\".$user."\\".$currentDate. "\\" .$imageName.".txt");
            $jsonObj = json_decode($jsonData, true);
            //[START product inserting]
            $this->insertProduct($jsonObj['mediaId'],$jsonObj['imageUrl'],$desc,$user,$currentDate,$imageName);
        }
    }
    
    public function checkUploadFileExists($user,$currentDate,$imageName)
    {
        return (file_exists(public_path()."\\Upload\\".$user."\\".$currentDate. "\\" .$imageName.".txt"));
    }
    
    public function curlUpload($url,$data,$imageName)
    {
        $curl = curl_init($url."/wp-json/wp/v2/media");
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic '.config('app.wpe_pw'),
                "Cache-Control: no-cache",
                "Content-Disposition: Attachment; filename=".$imageName,
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
        
        //if result not empty
        if($result && !$error){
            return $result;
        }else{
            return 'upload failed';
        }
    }
    
    public function exportUploadResult($mediaId,$imageUrl,$user,$currentDate,$imageName){
            
        $myObj['mediaId'] = $mediaId;
        $myObj['imageUrl']= $imageUrl;
        $Obj = json_encode($myObj);
        
        if (!is_dir(public_path()."\\Upload\\".$user."\\".$currentDate)) {
            // dir doesn't exist, make it
            mkdir(public_path()."\\Upload\\".$user."\\".$currentDate, 0777, true);
        }
        file_put_contents(public_path()."\\Upload\\".$user."\\".$currentDate. "\\" .$imageName.".txt", $Obj);
        return $imageName." uploaded successfully";
    }

    private $wc_client = null;

    /**
     * woocommerce client factory
     *
     * @return \Automattic\WooCommerce\Client|null
     */
    private function getWoocommerceClient() {
        if( ! $this->wc_client) {

            $this->wc_client = new \Automattic\WooCommerce\Client(
                config('app.wp_url'),
                config('app.wc_key'),
                config('app.wc_secret'),
                [
                    'wp_api' => true,
                    'version' => 'wc/v1',
                ]
            );
        }

        return $this->wc_client;
    }

    public function insertProduct($mediaId,$imageUrl,$description,$user,$currentDate,$imageName)
    {
        $eventName = $this->argument('event');
        
        if (!$this->checkProductFileExists($user,$currentDate,$imageName)) {
            // file doesn't exist
            // product inserting havent complete yet/failed
            print('Product Inserting for '.$imageName.' begins' .PHP_EOL);

            //init woocommerce client
            $woocommerce = $this->getWoocommerceClient();
    
            //init product data
            $data = [
                'name' => $eventName." #".$mediaId,
                'type' => 'simple',
                'regular_price' => '4.99 MYR',
                'description' => $description,
                'images' => [
                    [
                        'name' => $imageName,
                        'src' => $imageUrl,
                        'position' => 0
                    ]
                ]
            ];
            
            //[START product inserting]
            $result = $woocommerce->post('products', $data);
            $delete = $this->curlDeleteUpload($mediaId);
            print($this->exportProductResult($result,$user,$currentDate,$imageName) .PHP_EOL);
        }else{
            print 'Product Inserting for '.$imageName.' has been completed' .PHP_EOL;
        }
    }
    
    public function checkProductFileExists($user,$currentDate,$imageName)
    {
        return (file_exists(public_path()."\\Product\\".$user."\\".$currentDate. "\\" .$imageName.".txt"));
    }
    
    public function curlDeleteUpload($mediaId){
       $url = config('app.wp_url');
       $curl = curl_init($url."/wp-json/wp/v2/media/".$mediaId.'?force=true');
       curl_setopt_array($curl, [
       CURLOPT_HTTPHEADER => [
                    'Authorization: Basic '.config('app.wpe_pw'),
       ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST =>1,
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_VERBOSE => 1,
                CURLOPT_FOLLOWLOCATION => 1,
            ]);

            $result = curl_exec($curl);
            $error = curl_error($curl);
            curl_close($curl); 

        if($result && !$error){
            return $result;    
        }else{
            print('upload failed');    
        }
    }
    
    public function exportProductResult($result,$user,$currentDate,$imageName){
        $jsonResponse= json_encode($result);  
        if (!is_dir(public_path()."\\Product\\".$user."\\".$currentDate)) {
            // dir doesn't exist, make it
            mkdir(public_path()."\\Product\\".$user."\\".$currentDate, 0777, true);
        }
        file_put_contents(public_path()."\\Product\\".$user."\\".$currentDate. "\\" .$imageName.".txt", $jsonResponse);
        return $imageName." product inserted successfully";
    }
                          
}