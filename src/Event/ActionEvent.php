<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 20.05.14
 * Time: 12:17
 */

namespace uebb\HateoasBundle\Event;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class EntityVoteData
 *
 * This represents the data for a security voter request
 *
 * @package uebb\HateoasBundle\Security\Authorization\Voter
 */
class ActionEvent extends Event
{

    /**
     * @var
     */
    protected $stage;

    const PRE = 'pre';
    const POST = 'post';
    const PERSIST = 'persist';

    /**
     * @var string
     */
    protected $action;

    /**
     * @var ActionEventData
     */
    protected $data;

    /**
     * @param $stage
     * @param ActionEventData $data
     */
    public function __construct($stage, ActionEventData $data)
    {
        $this->stage = $stage;
        $this->data = $data;
        $this->action = Container::underscore(substr(join('', array_slice(explode('\\', get_class($data)), -1)), 0, -15));
    }

    /**
     * @return mixed
     */
    public function getStage()
    {
        return $this->stage;
    }

    /**
     * @return ActionEventData
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->stage . '_' . $this->action;
    }



} 