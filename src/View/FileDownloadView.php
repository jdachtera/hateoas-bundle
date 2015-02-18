<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 16.02.15
 * Time: 17:33
 */

namespace uebb\HateoasBundle\View;


use Doctrine\Common\Util\Debug;
use FOS\RestBundle\View\View;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FileDownloadView extends View
{

    public function __construct($filename, $displayName, Request $request, $asAttachment = false)
    {
        parent::__construct();
        $this->setResponse($this->serveFile($filename, $displayName, $request, $asAttachment));
    }

    protected function serveFile($filename, $displayName, Request $request, $asAttachment = false)
    {

        $lastModified = new \DateTime();
        $lastModified->setTimestamp(filemtime($filename));

        $ifModifiedSince = $request->headers->has('If-Modified-Since') ? new \DateTime($request->headers->get('If-Modified-Since')) : false;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filename);

        $response = new BinaryFileResponse($filename);
        $response->setMaxAge(10);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->headers->set('Content-Type', $mimeType);
        $response->setLastModified($lastModified);
        $response->setContentDisposition(
            $asAttachment ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            $displayName,
            mb_convert_encoding($displayName, "ASCII", "UTF-8")
        );


        $response->prepare($request);

        if ($ifModifiedSince && $ifModifiedSince <= $lastModified) {
            $response->setStatusCode(304);
        }

        return $response;
    }

}