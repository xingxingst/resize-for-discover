<?php

class ImageResizerForDiscover
{
    const HDRATIO = 16 / 9;
    const CRTRATIO = 4 / 3;
    const RECTRATIO = 1;
    private $lowestPix = 800000;
    private $lowestWidth = 1200;
    private $HDheight = 675;
    private $CRTheight = 900;
    private $rectHeight = 1200;
    private $suffix = '-resize';
    private $savePath;
    private $backgroundColor;
    public static $initialColor = '#000000';


    function __construct($path, $overWrite=FALSE, $backgroundColor = "")	{
        if (!is_string($path) || !is_file($path)) {
            throw new Exception('ImageResizerForDiscover Error: File not found');
        }
        $this->path = $path;
        $this->originalImageInfo = $this->getOriginalImage();
        if ($overWrite) $this->setSuffix('');
        $this->setBackgroundColor($backgroundColor);
    }
    
    public function setSuffix($string){
        $this->suffix = $string;
    }

    public function setBackgroundColor($color){
        if($color === "") $color = self::$initialColor;
        $this->backgroundColor =  $color == 'transparent' ? 'transparent' : ltrim($color, '#');
    }

    public function resizeForDiscover($mode=0, $all=FALSE){
        if(!$all && $this->originalImageInfo[0] * $this->originalImageInfo[1] >= $this->lowestPix &&
        $this->originalImageInfo[0] >= $this->lowestWidth) return FALSE;

        $this-> resizeToRatio($mode);

        return TRUE;
    }

    public function resize($width, $height)
    {
        if (is_int($width) === FALSE || is_int($height) === FALSE) {
            throw new Exception('ImageResizerForDiscover Error: $width or $height is invalid');
        }
        $this->resizeImage($width, $height);
    }

    private function resizeToRatio($mode=0){
        /** 
         * mode
         * 0 | 1:1
         * 1 | 4:3
         * 2 | 16:9
         * 3 | closer 16:9 or 4:3
         * 4 | closer 16:9 or 1:1
         * 5 | closer 4:3 or 1:1
         * 6 | closer 16:9 or 4:3 or 1:1
         * 7 | fixed, to above width 1200px and height 1200px
         * 8 | fixed, to above width 1200px and height 900px
         * 9 | fixed, to above width 1200px and height 675px
        */
        $width = $this->originalImageInfo[0];
        $height = $this->originalImageInfo[1];
        if($mode >= 7) {
            $compareHeight=[
                7 => $this->rectHeight,
                8 => $this->CRTheight,
                9 => $this->HDheight
            ];

            $widthRaito = $this->lowestWidth / $width;
            $heightRaito = $compareHeight[$mode] / $height;
            if($widthRaito < 1 && $heightRaito < 1){
                throw new Exception('ImageResizerForDiscover Error: This image is enough big.');
            }

            $pixelRatio = $widthRaito > $heightRaito ? $widthRaito : $heightRaito;
            
  
            $width = round( $width * $pixelRatio);
            $height = round($height * $pixelRatio);
            
            $this->resizeImage($width, $height);
            return;
        }

        $ratio = $width / $height;
        $ratiosDiff = [
            $this->ratioCloser(self::RECTRATIO, $ratio),
            $this->ratioCloser(self::CRTRATIO, $ratio),
            $this->ratioCloser(self::HDRATIO, $ratio),
            INF, //dummy
        ];

        if($mode >= 3){
            unset($ratiosDiff[$mode-3]);
            $closer = min($ratiosDiff);
            $mode =  array_keys($ratiosDiff, $closer);
            $mode = is_array($mode) ? $mode[0] : $mode;
        }

        $modePair = [
            ['ratio'=> self::RECTRATIO, 'height'=> $this->rectHeight],
            ['ratio'=> self::CRTRATIO, 'height'=> $this->CRTheight],
            ['ratio'=> self::HDRATIO, 'height'=> $this->HDheight]
        ];


        if($height > $modePair[$mode]['height']){
            $width = round($height * $modePair[$mode]['ratio']);
        }else{
            $width = $this->lowestWidth;
            $height = $modePair[$mode]['height'];
        }

        $this->resizeImage($width, $height);
    }

    private function ratioCloser($defRatio, $ratio){
        return abs($defRatio - $ratio);
    }

    /**
     * Outputs the image of the specified size. 
     * If necessary, add a black belt while maintaining the aspect ratio.
     * PNG,JPEG,GIF only
     * @param int $width
     * @param int $height
     */
    private function resizeImage($width, $height)
    {

        $shrunkenParams = $this->calculateShrunkenParams($width, $height, $this->originalImageInfo[0], $this->originalImageInfo[1]);

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === FALSE) {
            throw new Exception('ImageResizerForDiscover Error: create $canvas failed');
        }

        if($this->backgroundColor == 'transparent'){
            if($this->originalImageInfo[2] === IMAGETYPE_PNG){
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $color = 0x7fff0000;
                $success = imagefill($canvas, 0, 0, $color);
            }else{
                $color = imagecolorallocate($canvas,  0,  0,  0);
                imagecolortransparent($canvas, $color);
                $success = TRUE;
            }
        }else{
            $rgb = str_split($this->backgroundColor,2);
            $color = imagecolorallocate($canvas,  hexdec($rgb[0]),  hexdec($rgb[1]),  hexdec($rgb[2]));
            $success = imagefill($canvas, 0, 0, $color);
        }

        if ($color === FALSE) {
            throw new Exception('ImageResizerForDiscover Error: create $color failed');
        }

        if ($success === FALSE) {
            throw new Exception('ImageResizerForDiscover Error: imagefill() failed');
        }

        $success = imagecopyresampled(
            $canvas, $this->originalImageInfo['resource'], $shrunkenParams['x'], $shrunkenParams['y'], 0, 0,
            $shrunkenParams['width'], $shrunkenParams['height'], $this->originalImageInfo[0], $this->originalImageInfo[1]
        );
        if ($success === FALSE) {
            throw new Exception('ImageResizerForDiscover Error: imagecopyresampled() failed');
        }

        if(empty($this->savePath)) $this->setSavePath();
        switch ($this->originalImageInfo[2]) {
            case IMAGETYPE_GIF:
                imagegif($canvas,$this->savePath);
                break;
            case IMAGETYPE_JPEG:
                imagejpeg($canvas,$this->savePath);
                break;
            case IMAGETYPE_PNG:
                imagepng($canvas,$this->savePath);
                break;
            default:
                imagejpeg($canvas,$this->savePath);
        }

        imagedestroy($canvas);
        imagedestroy($this->originalImageInfo['resource']);
    }

    public static function mb_pathinfo($path, $options = null){
        $ret = array('dirname' => '', 'basename' => '', 'extension' => '', 'filename' => '');
        $pathinfo = array();
        if (preg_match('%^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$%im', $path, $pathinfo)) {
            if (array_key_exists(1, $pathinfo)) {
                $ret['dirname'] = $pathinfo[1];
            }
            if (array_key_exists(2, $pathinfo)) {
                $ret['basename'] = $pathinfo[2];
            }
            if (array_key_exists(5, $pathinfo)) {
                $ret['extension'] = $pathinfo[5];
            }
            if (array_key_exists(3, $pathinfo)) {
                $ret['filename'] = $pathinfo[3];
            }
        }
        switch ($options) {
            case PATHINFO_DIRNAME:
            case 'dirname':
                return $ret['dirname'];
            case PATHINFO_BASENAME:
            case 'basename':
                return $ret['basename'];
            case PATHINFO_EXTENSION:
            case 'extension':
                return $ret['extension'];
            case PATHINFO_FILENAME:
            case 'filename':
                return $ret['filename'];
            default:
                return $ret;
        }
    }

    public function getSavePath(){
        return $this->savePath;
    }

    public function setSavePath($path=''){
        if($path){
            $this->savePath = $path;
        }else{
            $pathInfo = self::mb_pathinfo($this->path);
            $this->savePath =  $this->pathCombine($pathInfo['dirname'], $pathInfo['filename'] . $this->suffix . '.' . $pathInfo['extension']);
        }
 
    }

    /**
     * Generate an image resource from path.
     * @param string $path
     * @return array an image resource
     */
    private function getOriginalImage()
    {
        $imageInfo = getimagesize($this->path);
        $originalImage = FALSE;

        if (is_array($imageInfo) === FALSE) {
            throw new Exception('ImageResizerForDiscover Error: This path is not a image.');
        }

        switch ($imageInfo[2]) {
            case IMAGETYPE_GIF:
                $originalImage = imagecreatefromgif($this->path);
                break;
            case IMAGETYPE_JPEG:
                $originalImage = imagecreatefromjpeg($this->path);
                break;
            case IMAGETYPE_PNG:
                $originalImage = imagecreatefrompng($this->path);
                break;
            default:
                throw new Exception('ImageResizerForDiscover Error: This type is not supported.');
        }

        if ($originalImage === FALSE) {
            throw new Exception('ImageResizerForDiscover Error: get image failed');
        }

        $imageInfo['resource'] = $originalImage;

        return $imageInfo;
    }

    /**
     * Get the coordinates, width, and height of the reduced image
     * @param int $targetWidth width after transform
     * @param int $targetHeight height after transform
     * @param int $currentWidth  now width
     * @param int $currentHeight now height
     * @return array
     */
    private function calculateShrunkenParams($targetWidth, $targetHeight, $currentWidth, $currentHeight)
    {
        $shrunkenWidth = $targetWidth;
        $shrunkenHeight = $currentHeight * ($targetWidth / $currentWidth);
        if ($shrunkenHeight > $targetHeight) {
            $shrunkenWidth = $currentWidth * ($targetHeight / $currentHeight);
            $shrunkenHeight = $targetHeight;
        }

        //描画開始時点の座標
        $x = ($targetWidth - $shrunkenWidth) / 2;
        $y = ($targetHeight - $shrunkenHeight) / 2;

        return array('x' => $x, 'y' => $y, 'width' => $shrunkenWidth, 'height' => $shrunkenHeight);
    }
    private function pathCombine($dir, $file)
    {
        return rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . $file;
    }
 
}
