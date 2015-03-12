<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 08.05.14
 * Time: 12:00
 */

namespace uebb\HateoasBundle\Service;

interface LinkResolverInterface
{

    /**
     * Resolve an array of links in the format
     *  array(
     *      rel => array(...links...)
     * )
     *
     * @param array $links
     * @return array
     */
    public function resolveLinks($links);

    /**
     *
     *
     * @param $href - The resource link
     * @return ResourceInterface
     */
    public function resolveLink($href);
}