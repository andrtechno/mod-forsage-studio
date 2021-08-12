<?php

namespace panix\mod\forsage\components;

use Yii;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class Image extends UploadedFile
{

    public $isDownloaded = false;

    public function __construct($name, $tempName, $type, $size, $error)
    {
        $this->name = $name;
        $this->tempName = $tempName;
        $this->type = $type;
        $this->size = $size;
        $this->error = $error;
        parent::__construct([]);
    }
    public static function create($fullPath)
    {
        if (!file_exists($fullPath))
            return false;
        $name = explode(DIRECTORY_SEPARATOR, $fullPath);

        return new self(end($name), $fullPath, FileHelper::getMimeType($fullPath), filesize($fullPath), false);
    }
    /**
     * @param string $image name in /uploads/ e.g. somename.jpg
     * @return Image|false
     */
    public static function create2($image)
    {
        // $isDownloaded = substr($image, 0, 4) === 'http';
        $isDownloaded = preg_match('/http(s?)\:\/\//i', $image);

        //if( !preg_match('/http(s?)\:\/\//i', $url) ) {
        // URL does NOT contain http:// or https://
        //}


        if ($isDownloaded) {
            $tmpName = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . sha1(pathinfo($image, PATHINFO_FILENAME)) . '.' . pathinfo($image, PATHINFO_EXTENSION);

            if ((bool)parse_url($image) && !file_exists($tmpName)) {
                $fileHeader = get_headers($image, 1);
                //if ((int) (substr($fileHeader[0], 9, 3)) === 200 || (int) (substr($fileHeader[0], 9, 3)) === 301){
                //    file_put_contents($tmpName, file_get_contents($image));

                if (in_array((int)(substr($fileHeader[0], 9, 3)), [200, 301])) {
                    //CMS::dump($fileHeader['Content-Type'][0]);die;
                    //if (in_array($fileHeader['Content-Type'][0], ['image/jpeg'])) {
                    $get = @file_get_contents($image);
                    if ($get) {
                        file_put_contents($tmpName, $get);
                    } else {
                        return false;
                    }

                }
            }
        } else {
            $tmpName = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . $image;
        }


        if (!file_exists($tmpName))
            return false;

        $result = new self($image, $tmpName, FileHelper::getMimeType($tmpName), filesize($tmpName), UPLOAD_ERR_OK);
        $result->isDownloaded = $isDownloaded;
        return $result;

    }

    /**
     * @param string $file
     * @param bool $deleteTempFile
     * @return bool
     */
    public function saveAs($file, $deleteTempFile = false)
    {

        //if(!file_exists($this->tempName) || empty($this->tempName)){
        //echo $file;
        //     echo $this->tempName;die;
        // }
        if(file_exists($this->tempName)){
            return copy($this->tempName, $file);
        }

        /* if ($this->error == UPLOAD_ERR_OK) {
             if ($deleteTempFile) {
                 return move_uploaded_file($this->tempName, $file);
             } elseif (is_uploaded_file($this->tempName)) {
                 return copy($this->tempName, $file);
             }
         }*/

    }

    public function deleteTempFile()
    {
        @unlink($this->tempName);
    }

}
