<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 18.02.15
 * Time: 13:29
 */

namespace uebb\HateoasBundle\Service;


use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use uebb\HateoasBundle\Entity\File;

class ImageResizer
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    protected $formats = array();


    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        foreach(array('png', 'jpeg', 'webp', 'gif') as $format) {
            if (function_exists('image' . $format)) {
                $this->formats[] = $format;
            }
        }
    }

    public function resizeImage(File $file, $targetWidth, $targetHeight, $allowedStep = 10, $targetFormat = 'png')
    {
        if (!in_array($targetFormat, $this->formats)) {
            $targetFormat = 'png';
        }

        $targetHeight = (int)round($targetHeight / $allowedStep) * $allowedStep;
        $targetWidth = (int)round($targetWidth / $allowedStep) * $allowedStep;

        $filename = $file->getFullPath($this->container->getParameter('uebb.hateoas.upload_dir'));

        try {
            list($sourceWidth, $sourceHeight, $type) = getimagesize($filename);
        } catch(\Exception $e) {
            throw new NotFoundHttpException('', $e);
        }

        if ($targetWidth === 0) {
            $targetWidth = $sourceWidth;
        }
        if ($targetHeight === 0) {
            $targetHeight = $sourceHeight;
        }

        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;


        if ($sourceWidth <= $targetWidth && $sourceHeight <= $targetHeight) {
            $targetWidth = $sourceWidth;
            $targetHeight = $sourceHeight;
        } else if ($targetRatio > $sourceRatio) {
            $targetWidth = (int)($targetHeight * $sourceRatio);
        } else if ($targetRatio < $sourceRatio) {
            $targetHeight = (int)($targetWidth / $sourceRatio);
        }

        $cache_filename = $file->getFullPath($this->container->getParameter('uebb.hateoas.cache_dir')) . '-' . strval($targetWidth) . 'x' . strval($targetHeight) . '.' . $targetFormat;


        if (!is_file($cache_filename)) {
            switch ($type) {
                case IMAGETYPE_GIF:
                    $sourceImage = imagecreatefromgif($filename);
                    break;
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($filename);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($filename);
                    break;
                default:
                    throw new NotFoundHttpException();
                    break;
            }

            $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
            imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

            $dir = dirname($cache_filename);
            if (!is_dir($dir)) {
                mkdir($dir, 0770, TRUE);
            }

            switch($targetFormat) {
                case 'png':
                    imagepng($targetImage, $cache_filename, 9, PNG_ALL_FILTERS);
                    break;
                case 'jpeg':
                    imagejpeg($targetImage, $cache_filename, 100);
                    break;
                case 'webp':
                    imagewebp($targetImage, $cache_filename);
                    break;
                case 'gif':
                    imagegif($targetImage, $cache_filename);
                    break;
            }

            imagedestroy($sourceImage);
            imagedestroy($targetImage);
        }

        return $cache_filename;
    }
}