<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;
use Intervention\Image\ImageManagerStatic as Image; 

class GoogleIdentityService
{
    public function verifyId($imagePath)
    {
        try {
            $imagePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath);

            $tempFileName = 'processed_' . basename($imagePath);
            $tempPath = storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $tempFileName);
            
            $img = Image::make($imagePath);
            $img->save($tempPath);  
                
            $tesseractFolder = 'C:\Program Files\Tesseract-OCR';
            $tesseractDataDir = $tesseractFolder . DIRECTORY_SEPARATOR . 'tessdata';

            $text = (new TesseractOCR($tempPath)) 
                ->executable($tesseractFolder . DIRECTORY_SEPARATOR . 'tesseract.exe')
                ->tessdataDir($tesseractDataDir)
                ->lang('ara') 
                ->psm(3) 
                ->oem(1)
                ->config('user_defined_dpi', '300')
                ->run();

            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return [
                'success' => true,
                'text'    => $this->cleanText($text),
            ];

        } catch (\Exception $e) {
            if (isset($tempPath) && file_exists($tempPath)) {
                unlink($tempPath);
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function cleanText($text)
    {
        $text = preg_replace('/[\x{200E}\x{200F}\x{200B}\x{200C}\x{200D}]/u', '', $text);
        
        $text = trim($text);
        
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        
        $text = preg_replace('/[ \t]+/u', ' ', $text);

        $text = str_replace(['أ','إ','آ'], 'ا', $text);
        $text = str_replace('ى', 'ي', $text);
        $text = str_replace('ة', 'ه', $text);

        return $text;
    }
}