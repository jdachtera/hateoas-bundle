<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 29.01.15
 * Time: 17:55
 */

namespace uebb\HateoasBundle\Service;


use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Common\Util\Debug;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use JMS\Serializer\Serializer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Validator;
use uebb\HateoasBundle\Entity\ResourceInterface;
use uebb\HateoasBundle\Event\ActionEvent;
use uebb\HateoasBundle\Event\AddLinkActionEventData;
use uebb\HateoasBundle\Event\GetActionEventData;
use uebb\HateoasBundle\Event\GetCollectionActionEventData;
use uebb\HateoasBundle\Event\GetLinkCollectionActionEventData;
use uebb\HateoasBundle\Event\HateoasActionEvent;
use uebb\HateoasBundle\Event\PatchActionEventData;
use uebb\HateoasBundle\Event\PatchPropertyActionEventData;
use uebb\HateoasBundle\Event\PostActionEventData;
use uebb\HateoasBundle\Event\RemoveActionEventData;
use uebb\HateoasBundle\Event\RemoveLinkActionEventData;

class RequestProcessor
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var LinkParser
     */
    protected $linkParser;

    /**
     * @var LinkResolver
     */
    protected $linkResolver;

    /**
     * @var FormResolver
     */
    protected $formResolver;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var QueryParser
     */
    protected $queryParser;

    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var Validator
     */
    protected $validator;


    public function __construct(EntityManagerInterface $entityManager, LinkParser $linkParser, LinkResolver $linkResolver, FormResolver $formResolver, EventDispatcherInterface $dispatcher, QueryParser $queryParser, Serializer $serializer, Validator\RecursiveValidator $validator)
    {
        $this->entityManager = $entityManager;
        $this->linkParser = $linkParser;
        $this->linkResolver = $linkResolver;
        $this->formResolver = $formResolver;
        $this->dispatcher = $dispatcher;
        $this->queryParser = $queryParser;
        $this->serializer = $serializer;
        $this->validator = $validator;
    }

    /**
     * @param ActionEvent $event
     */
    public function dispatchActionEvent(ActionEvent $event)
    {
        $this->dispatcher->dispatch('uebb.hateoas.action', $event);
        $this->dispatcher->dispatch('uebb.hateoas.action_' .  $event->getId(), $event);
    }

    /**
     * @param $entityName
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function getRepository($entityName)
    {
        return $this->entityManager->getRepository($entityName);
    }

    /**
     * @param $entityName
     * @return \Doctrine\Common\Persistence\Mapping\ClassMetadata
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \Exception
     */
    protected function getClassMetadata($entityName)
    {
        return $this->entityManager->getMetadataFactory()->getMetadataFor($entityName);
    }

    /**
     * Get the entity name of a resource. This is the last part of the fully qualified class name
     *
     * @param ResourceInterface $resource
     * @return string
     */
    protected function getEntityName($resource)
    {
        return basename($this->getClassMetadata(get_class($resource))->getName());
    }

    protected function getIdentifierFieldName($entityName)
    {
        return $this->getClassMetadata($entityName)->getSingleIdentifierFieldName();
    }


    /**
     * Constructs a base queryManager from a request for a certain entity
     *
     * @param Request $request - The http request
     * @param string $entityName - The entitiy name. If it is null, the value name of the controller is used
     * @return \Doctrine\ORM\Query|QueryBuilder
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function getResources($entityName, Request $request)
    {
        $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new GetCollectionActionEventData($entityName)));
        $queryBuilder = $this->getBaseQueryBuilder($entityName, $request);
        $this->dispatchActionEvent(new ActionEvent(ActionEvent::POST, new GetCollectionActionEventData($entityName, $queryBuilder)));
        return $queryBuilder;
    }

    public function getBaseQueryBuilder($entityName, Request $request)
    {
        /** @var \Doctrine\ORM\Query|\Doctrine\ORM\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getRepository($entityName)->createQueryBuilder('e');

        $this->queryParser->applyQueryParameters($entityName, $request, $queryBuilder);

        return $queryBuilder;
    }


    /**
     * Get a single resource by id
     *
     * @param string $entityName - The entitiy name. If it is null, the value name of the controller is used
     * @param $id - The resource id
     * @return null
     * @throws \Doctrine\ORM\Query\QueryException
     */
    public function getResource($entityName, $id)
    {
        $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new GetActionEventData($entityName, $id)));

        /** @var \Doctrine\ORM\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getRepository($entityName)->createQueryBuilder('e');

        $criteria = Criteria::create();
        $criteria->andWhere(new Comparison('id', '=', $id));

        $criteria->setMaxResults(1);
        $queryBuilder->addCriteria($criteria);

        $resourceClassName = $this->getRepository($entityName)->getClassName();

        $result = $queryBuilder->getQuery()->getResult();

        $resource = count($result) ? $result[0] : NULL;

        if (!$resource instanceof $resourceClassName) {
            throw new NotFoundHttpException('Resource ' . $entityName . ':' . $id . ' not found');
        }

        $this->dispatchActionEvent(new ActionEvent(ActionEvent::POST, new GetActionEventData($entityName, $id, $resource)));

        return $resource;
    }


    /**
     * Get the last modified date for a query. Simply uses the timestamp of the newest result item or false if none was found
     *
     * @param QueryBuilder $source
     * @return \DateTime
     */
    protected function getLastModifiedDateForQuery(QueryBuilder $source)
    {
        $queryBuilder = clone $source;
        $queryBuilder->select('e.updated');
        $queryBuilder->orderBy($queryBuilder->expr()->desc('e.updated'));
        $queryBuilder->setMaxResults(1);
        $result = $queryBuilder->getQuery()->getScalarResult();
        return count($result) === 1 ? new \DateTime($result[0]['updated']) : false;

    }

    /**
     * Get the queryBuilder for the related resources
     *
     * @param integer $id - The id of the resource
     * @param string $rel - The name of the related resources property
     * @param Criteria $criteria - Optional criteria
     * @return QueryBuilder
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function getRelatedResources($entityName, Request $request, $resource, $rel)
    {
        $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new GetLinkCollectionActionEventData($entityName, $resource, $rel)));

        /** @var ClassMetadata $metadata */
        $mapping = $this->getClassMetadata($entityName)->getAssociationMapping($rel);
        $relatedMapping = $this->getClassMetadata($mapping['targetEntity'])->getAssociationMapping(
            $mapping['isOwningSide'] ? $mapping['inversedBy'] : $mapping['mappedBy']
        );

        /** @var \Doctrine\ORM\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getBaseQueryBuilder($mapping['targetEntity'], $request);

        $queryBuilder->innerJoin('e.' . $relatedMapping['fieldName'], $relatedMapping['fieldName']);
        $queryBuilder->andWhere($relatedMapping['fieldName'] . '.id = :parent_id');

        $queryBuilder->setParameter('parent_id', $resource->getId());

        $this->dispatchActionEvent(new ActionEvent(ActionEvent::POST, new GetLinkCollectionActionEventData($entityName, $resource, $rel, $queryBuilder)));

        return $queryBuilder;
    }


    /**
     * Apply the request body o hal+json format to a resource. If the resource is NULL a new is created
     *
     * @param Request $request - The http request
     * @param ResourceInterface|NULL $resource - The resource
     */
    public function createResource($entityName, Request $request)
    {
        $resourceClassName = $this->getClassMetadata($entityName)->getName();

        $resource = new $resourceClassName();

        $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new PostActionEventData($entityName, $resource)));

        $links = $this->linkResolver->resolveResourceLinks(
            $this->linkParser->parseLinks($request)
        );

        $this->addLinks($entityName, $resource, $links);

        $form = $this->formResolver->getForm($resource);

        $data = array();

        foreach ($request->request->getIterator() as $k => $v) {
            $data[$k] = $v;
            $request->request->remove($k);
        }
        $files = array();
        foreach ($request->files->getIterator() as $k => $v) {
            $files[$k] = $v;
        }
        $request->files->add(array($form->getName() => $files));
        $request->request->set($form->getName(), $data);
        $form->handleRequest($request);


        $this->dispatchActionEvent(new ActionEvent(ActionEvent::POST, new PostActionEventData($entityName, $resource)));

        return $resource;
    }

    public function removeResource($entityName, $id)
    {
        $resource = $this->getResource($entityName, $id);
        $eventData = new RemoveActionEventData($entityName, $resource);

        $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, $eventData));

        $this->entityManager->remove($resource);

        $this->dispatchActionEvent(new ActionEvent(ActionEvent::POST, $eventData));

        return $resource;
    }


    /**
     * Connect links to a resource
     *
     * @param $entityName
     * @param ResourceInterface $resource -  The resource
     * @param array $links - The links
     * @throws NotAcceptableHttpException
     */
    protected function addLinks($entityName, $resource, $links)
    {
        /** @var ClassMetadata $metadata */
        $metadata = $this->getClassMetadata($entityName);
        if ($links === null) {
            return;
        }

        $accessor = PropertyAccess::createPropertyAccessor();

        foreach ($metadata->getAssociationNames() as $associationName) {
            if (!array_key_exists($associationName, $links)) {
                continue;
            }

            $relatedClass = $metadata->getAssociationTargetClass($associationName);
            $isInverse = $metadata->isAssociationInverseSide($associationName);

            if ($isInverse) {
                /** @var ClassMetadata $targetMetadata */
                $targetMetadata = $this->getClassMetadata($relatedClass);
                $targetField = $metadata->getAssociationMappedByTargetField($associationName);
            }

            if ($metadata->isSingleValuedAssociation($associationName)) {


                if (count($links[$associationName]) > 1) {
                    throw new ConflictHttpException('Resource can only have one ' . $associationName . ' relation');
                }
                $value = count($links[$associationName]) ? $links[$associationName][0] : null;


                if (!($value instanceof $relatedClass)) {
                    throw new NotAcceptableHttpException("Wrong resource type or resource not found.");
                } else {
                    $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new AddLinkActionEventData($entityName, $resource, $associationName, $value)));

                    if ($isInverse) {
                        if ($targetMetadata->isSingleValuedAssociation($targetField)) {
                            $accessor->setValue($value, $targetField, $resource);
                        } else {
                            $collection = $accessor->getValue($value, $targetField);
                            if ($collection->contains($resource)) {
                                throw new ConflictHttpException("Resource cannot be linked twice");
                            } else {
                                $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new AddLinkActionEventData($entityName, $resource, $associationName, $value)));
                                $collection->add($resource);
                            }
                        }
                    } else {
                        $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new AddLinkActionEventData($entityName, $resource, $associationName, $value)));
                        $accessor->setValue($resource, $associationName, $value);
                    }
                    $this->entityManager->persist($value);
                    $this->dispatchActionEvent(new ActionEvent(ActionEvent::POST, new AddLinkActionEventData($entityName, $resource, $associationName, $value)));
                }
            } else {


                $collection = $accessor->getValue($resource, $associationName);

                forEach ($links[$associationName] as $value) {
                    if (!($value instanceof $relatedClass)) {
                        throw new UnsupportedMediaTypeHttpException("Wrong resource type or resource not found: " . get_class($value));
                    }

                    if ($isInverse) {
                        if ($targetMetadata->isSingleValuedAssociation($targetField)) {
                            $accessor->setValue($value, $targetField, $resource);
                        } else {
                            $collection = $accessor->getValue($value, $targetField);
                            if ($collection->contains($resource)) {
                                throw new ConflictHttpException("Resource cannot be linked twice");
                            } else {
                                $this->dispatchActionEvent($entityName, 'link', $resource, $associationName, $value);
                                $collection->add($resource);
                            }
                        }
                    } else {
                        if ($collection->contains($value)) {
                            throw new ConflictHttpException("Resource cannot be linked twice");
                        } else {
                            $this->dispatchActionEvent($entityName, 'link', $resource, $associationName, $value);
                            $collection->add($value);
                        }
                    }
                    $this->entityManager->persist($value);
                }
                //$accessor->setValue($resource, $associationName, $collection);

            }

        }
    }

    /**
     * Disconnect links from a resource
     *
     * @param $entityName
     * @param ResourceInterface $resource - The resource
     * @param array $links - The links
     */
    protected function removeLinks($entityName, $resource, $links)
    {
        /** @var ClassMetadata $targetMetadata */
        $metadata = $this->getClassMetadata($entityName);
        if ($links === null) {
            return;
        }

        $accessor = PropertyAccess::createPropertyAccessor();

        foreach ($metadata->getAssociationNames() as $associationName) {
            if (!array_key_exists($associationName, $links)) {
                continue;
            }

            $relatedClass = $metadata->getAssociationTargetClass($associationName);
            $isInverse = $metadata->isAssociationInverseSide($associationName);
            if ($isInverse) {
                /** @var ClassMetadata $targetMetadata */
                $targetMetadata = $this->getClassMetadata($relatedClass);
                $targetField = $metadata->getAssociationMappedByTargetField($associationName);
            }

            if ($metadata->isSingleValuedAssociation($associationName)) {
                if (count($links[$associationName]) > 1) {
                    throw new ConflictHttpException('Cannot remove more than one resource from singular ' . $associationName . ' relation');
                }
                $value = count($links[$associationName]) ? $links[$associationName][0] : null;
                $originalValue = $accessor->getValue($resource, $associationName);
                if ($originalValue === $value) {
                    $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new RemoveLinkActionEventData($entityName, $resource, $associationName, $value)));
                    $accessor->setValue($resource, $associationName, null);
                    $this->entityManager->persist($value);
                    $this->dispatchActionEvent(new ActionEvent(ActionEvent::POST, new RemoveLinkActionEventData($entityName, $resource, $associationName, $value)));
                } else {
                    throw new PreconditionFailedHttpException("Resource " . $this->entityName . ":" . ($resource->getId() ? $resource->getId() : "new") . " is not linked to " . $relatedClass . ":" . $value->getId());
                }

            } else {
                $collection = $accessor->getValue($resource, $associationName);

                forEach ($links[$associationName] as $value) {
                    if (!($value instanceof $relatedClass)) {
                        throw new UnsupportedMediaTypeHttpException("Wrong resource type or resource not found: " . get_class($value));
                    }

                    if ($isInverse) {
                        if ($targetMetadata->isSingleValuedAssociation($targetField)) {
                            $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new RemoveLinkActionEventData($entityName, $resource, $associationName, $value)));
                            $accessor->setValue($value, $targetField, NULL);
                        } else {
                            $collection = $accessor->getValue($value, $targetField);
                            if ($collection->contains($resource)) {
                                $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new RemoveLinkActionEventData($entityName, $resource, $associationName, $value)));
                                $collection->removeElement($resource);
                            } else {
                                throw new PreconditionFailedHttpException("Resource " . $entityName . ":" . ($resource->getId() ? $resource->getId() : "new") . " is not linked to " . $relatedClass . ":" . $value->getId());
                            }
                        }
                    } else {
                        if ($collection->contains($value)) {
                            $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new RemoveLinkActionEventData($entityName, $resource, $associationName, $value)));
                            $collection->removeElement($value);
                        } else {
                            throw new PreconditionFailedHttpException("Resource " . $entityName . ":" . ($resource->getId() ? $resource->getId() : "new") . " is not linked to " . $relatedClass . ":" . $value->getId());
                        }
                    }
                    $this->entityManager->persist($value);
                    $this->dispatchActionEvent(new ActionEvent(ActionEvent::POST, new RemoveLinkActionEventData($entityName, $resource, $associationName, $value)));
                }
            }

        }
    }

    public function patchResourceCollection($entityName, $resource, $rel, $actions)
    {
        /** @var ClassMetadata $metadata */
        $metadata = $this->getClassMetadata($entityName);

        $linksToRemove = array();
        $linksToAdd = array();

        foreach ($actions as $action) {
            $path_parts = explode('/', $action['path']);
            array_shift($path_parts);
            if ($path_parts[0] === '_links' && $path_parts[1] === 'items') {

                $values = $action['value'];
                if (!is_array($values)) {
                    $values = array('href' => $values);
                }

                // Check if it's a not numeric array
                if ((bool)count(array_filter(array_keys($values), 'is_string'))) {
                    $values = array($values);
                }

                if (!isset($action['op'])) {
                    throw new \InvalidArgumentException('No operation given.');
                }

                if (($action['op'] === 'add')) {
                    foreach ($values as $value) {
                        $linksToAdd[$rel][] = $value['href'];
                    }
                } else if ($action['op'] === 'remove') {
                    foreach ($values as $value) {
                        $linksToRemove[$rel][] = $value['href'];
                    }
                } else {
                    throw new \InvalidArgumentException('Operation ' . $action['op'] . ' is not implemented for relation ' . $path_parts[1]);
                }
            }
        }

        $this->removeLinks($entityName, $resource, $this->linkResolver->resolveResourceLinks($linksToRemove));
        $this->addLinks($entityName, $resource, $this->linkResolver->resolveResourceLinks($linksToAdd));
    }

    public function patchResource($entityName, $resource, array $actions)
    {
        $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new PatchActionEventData($entityName, $resource)));

        /** @var ClassMetadata $metadata */
        $metadata = $this->getClassMetadata($entityName);

        $accessor = PropertyAccess::createPropertyAccessor();

        $linksToRemove = array();
        $linksToAdd = array();

        $data = array();

        foreach ($actions as $action) {
            if (!isset($action['op'])) {
                throw new \InvalidArgumentException('No operation given.');
            }

            $path_parts = explode('/', $action['path']);
            array_shift($path_parts);
            if ($path_parts[0] === '_links') {

                if (!$metadata->hasAssociation($path_parts[1]) || !$metadata->isSingleValuedAssociation($path_parts[1])) {
                    throw new BadRequestHttpException('Resource has no singular relation named ' . $path_parts[1]);
                }
                if ($action['op'] === 'replace' || $action['op'] === 'add') {
                    $linksToAdd[$path_parts[1]][] = $action['value']['href'];
                } else if ($action['op'] === 'remove') {
                    $linksToRemove[$path_parts[1]][] = $action['value']['href'];
                } else {
                    throw new \InvalidArgumentException('Operation ' . $action['op'] . ' is not implemented for relation ' . $path_parts[1]);
                }
            } else {
                if (count($path_parts) > 1) {
                    throw new AccessDeniedHttpException('Not allowed to change properties of sub-object');
                }
                switch ($action['op']) {
                    case 'add':
                        if ($accessor->getValue($resource, $path_parts[0]) === NULL) {
                            // If the property is not present, simply add it.
                            $data[$path_parts[0]] = $action['value'];
                        } else {
                            $data[$path_parts[0]] = (intval($accessor->getValue($resource, $path_parts[0])) + intval($action['value']));
                        }
                        break;
                    case 'remove':
                        $data[$path_parts[0]] = (intval($accessor->getValue($resource, $path_parts[0])) - intval($action['value']));
                        break;
                    case 'multiply':
                        $data[$path_parts[0]] = (intval($accessor->getValue($resource, $path_parts[0])) * intval($action['value']));
                        break;
                    case 'divide':
                        $data[$path_parts[0]] = (intval($accessor->getValue($resource, $path_parts[0])) / intval($action['value']));
                        break;
                    case 'replace':
                        $data[$path_parts[0]] = $action['value'];
                        break;
                    default:
                        throw new NotImplementedException('Operation ' . $action['op'] . ' is not implemented');
                        break;
                }

                $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new PatchPropertyActionEventData($entityName, $resource, $path_parts[0], $action['value'])));
            }
        }


        $this->removeLinks($entityName, $resource, $this->linkResolver->resolveResourceLinks($linksToRemove));
        $this->addLinks($entityName, $resource, $this->linkResolver->resolveResourceLinks($linksToAdd));

        $form = $this->formResolver->getForm($resource);
        $form->submit($data, FALSE);

        foreach($data as $propertyName => $propertyValue) {
            $this->dispatchActionEvent(new ActionEvent(ActionEvent::POST, new PatchPropertyActionEventData($entityName, $resource, $propertyName, $propertyValue)));
        }

        $this->dispatchActionEvent(new ActionEvent(ActionEvent::PRE, new PatchActionEventData($entityName, $resource)));
    }


}