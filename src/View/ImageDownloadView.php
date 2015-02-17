<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 16.02.15
 * Time: 17:51
 */

namespace uebb\HateoasBundle\View;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImageDownloadView extends FileDownloadView
{

    protected function serveFile($filename, $displayName, $mimeType, Request $request, $asAttachment = false)
    {
        $targetWidth = intval($request->query->get('width', '0'), 10);
        $targetHeight = intval($request->query->get('height', '0'), 10);

        $allowedStep = 10;

        $targetHeight = (int)round($targetHeight / $allowedStep) * $allowedStep;
        $targetWidth = (int)round($targetWidth / $allowedStep) * $allowedStep;

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

        $cache_filename = $filename . '-' . strval($targetWidth) . 'x' . strval($targetHeight);


        $displayName = pathinfo($displayName, PATHINFO_FILENAME);
        if ($targetHeight !== $sourceHeight && $targetWidth !== $sourceWidth) {
            $displayName .= '-' . $targetWidth . 'x' . $targetHeight . '.jpeg';
        }
        $displayName .= '.jpeg';

        if (is_file($cache_filename)) {
            return parent::serveFile($cache_filename, $displayName, 'image/jpeg', $request, $asAttachment);
        } else {


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

            imagejpeg($targetImage, $cache_filename, 100);
            imagedestroy($sourceImage);
            imagedestroy($targetImage);

            return parent::serveFile($cache_filename, $displayName, 'image/jpeg', $request, $asAttachment);

        }
    }
}