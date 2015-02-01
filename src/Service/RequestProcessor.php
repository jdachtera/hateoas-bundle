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
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use uebb\HateoasBundle\Entity\ResourceInterface;
use uebb\HateoasBundle\Event\HateoasActionEvent;

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
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var QueryParser
     */
    protected $queryParser;


    public function __construct(EntityManagerInterface $entityManager, LinkParser $linkParser, FormFactoryInterface $formFactory, EventDispatcherInterface $dispatcher, QueryParser $queryParser)
    {
        $this->entityManager = $entityManager;
        $this->linkParser = $linkParser;
        $this->formFactory = $formFactory;
        $this->dispatcher = $dispatcher;
        $this->queryParser = $queryParser;
    }


    /**
     * @param $timeOfEvent
     * @param String $entityName
     * @param String $method
     * @param ResourceInterface $resource
     * @param String $propertyName
     * @param mixed $propertyValue
     */
    protected function dispatchActionEvent($timeOfEvent, $entityName, $method, $resource = NULL, $propertyName = NULL, $propertyValue = NULL)
    {
        $event = new HateoasActionEvent(
            $method,
            $this->getClassMetadata($entityName)->getName(),
            $resource,
            $propertyName,
            $propertyValue
        );

        $this->dispatcher->dispatch('uebb.hateoas.' . $timeOfEvent . '_action', $event);
        $this->dispatcher->dispatch('uebb.hateoas.' . $timeOfEvent . '_' . $method, $event);
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
        $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'cget');

        /** @var \Doctrine\ORM\Query|\Doctrine\ORM\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getRepository($entityName)->createQueryBuilder('e');

        $criteria = $this->queryParser->getCriteria($entityName, $request);

        $queryBuilder->addCriteria($criteria);

        $this->queryParser->parseFilter($entityName, $request, $queryBuilder);

        return $queryBuilder;
    }


    /**
     * Get a single resource by id
     *
     * @param $id - The resource id
     * @param string $entityName - The entitiy name. If it is null, the value name of the controller is used
     * @return null
     */
    public function getResource($entityName, $id)
    {
        $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'get');

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

        $this->dispatchActionEvent(HateoasActionEvent::AFTER, $entityName, 'get', $resource);

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
     * Remove all relations from a resource
     *
     * @param ResourceInterface $resource - The resource
     */
    protected function removeRelations($entityName, ResourceInterface $resource)
    {
        /** @var ClassMetadata $metadata */
        $metadata = $this->getClassMetadata($entityName);

        $accessor = PropertyAccess::createPropertyAccessor();

        foreach ($metadata->getAssociationMappings() as $mapping) {

            if (!$mapping['isCascadeRemove']) {

                /** @var ClassMetadata $targetMetadata */
                $targetMetadata = $this->getClassMetadata($mapping['targetEntity']);

                if ($metadata->isSingleValuedAssociation($mapping['fieldName'])) {
                    $targetResources = array();
                    $targetResource = $targetResource = $accessor->getValue($resource, $mapping['fieldName']);
                    if ($targetResource instanceof $mapping['targetEntity']) {
                        $targetResources[] = $targetResource;
                    }
                    $accessor->setValue($resource, $mapping['fieldName'], NULL);
                } else {
                    $targetResources = $accessor->getValue($resource, $mapping['fieldName'])->toArray();
                    $accessor->getValue($resource, $mapping['fieldName'])->clear();
                }

                if (trim($mapping['mappedBy']) !== '' && $targetMetadata->hasAssociation($mapping['mappedBy'])) {

                    foreach ($targetResources as $targetResource) {
                        if ($targetMetadata->isSingleValuedAssociation($mapping['mappedBy'])) {
                            $accessor->setValue($targetResource, $mapping['mappedBy'], NULL);
                        } else {
                            $accessor->getValue($targetResource, $mapping['mappedBy'])->remove($resource);
                        }
                        $this->entityManager->persist($targetResource);
                    }
                }
            }

        }
    }

    /**
     * Get the queryBuilder for the related resources
     *
     * @param integer $id - The id of the resource
     * @param string $propertyName - The name of the related resources property
     * @param Criteria $criteria - Optional criteria
     * @return QueryBuilder
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function getRelatedResources($entityName, Request $request, $resource, $propertyName)
    {
        /** @var ClassMetadata $metadata */
        $mapping = $this->getClassMetadata($entityName)->getAssociationMapping($propertyName);
        $relatedMapping = $this->getClassMetadata($mapping['targetEntity'])->getAssociationMapping(
            $mapping['isOwningSide'] ? $mapping['inversedBy'] : $mapping['mappedBy']
        );

        /** @var \Doctrine\ORM\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getResources($mapping['targetEntity'], $request);

        $queryBuilder->innerJoin('e.' . $relatedMapping['fieldName'], $relatedMapping['fieldName']);
        $queryBuilder->andWhere($relatedMapping['fieldName'] . '.id = :parent_id');

        $queryBuilder->setParameter('parent_id', $resource->getId());

        return $queryBuilder;
    }


    /**
     * Get the form for a resource
     *
     * @param ResourceInterface $resource
     * @return Form
     */
    protected function getForm($resource)
    {

        $resourceClassName = ClassUtils::getClass($resource);
        $resourceParts = explode("\\", $resourceClassName);
        $resourceParts[count($resourceParts) - 2] = 'Form';
        $resourceParts[count($resourceParts) - 1] .= 'Type';
        $formType = implode("\\", $resourceParts);

        return $this->formFactory->create(new $formType(), $resource);
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
        $links = $request->attributes->get('links');

        $this->clearLinks($entityName, $resource);
        $this->addLinks($entityName, $resource, $links);

        $form = $this->getForm($resource);

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

        $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'post', $resource);

        return $resource;
    }

    public function removeResource($entityName, $id)
    {
        $resource = $this->getResource($entityName, $id);
        $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'delete', $resource);

        $this->entityManager->remove($resource);
        $this->entityManager->flush();
    }


    /**
     * Remove all links from a resource
     *
     * @param ResourceInterface $resource - The resource
     */
    protected function clearLinks($entityName, $resource)
    {
        $metadata = $this->getClassMetadata($entityName);
        $accessor = PropertyAccess::createPropertyAccessor();

        foreach ($metadata->getAssociationNames() as $associationName) {
            if ($metadata->isSingleValuedAssociation($associationName)) {
                $accessor->setValue($resource, $associationName, NULL);
            } else {
                $accessor->getValue($resource, $associationName)->clear();
            }
        }
    }


    /**
     * Connect links to a resource
     *
     * @param array $links - The links
     * @param ResourceInterface $resource -  The resource
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
                    $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'link', $resource, $associationName, $value);

                    if ($isInverse) {
                        if ($targetMetadata->isSingleValuedAssociation($targetField)) {
                            $accessor->setValue($value, $targetField, $resource);
                        } else {
                            $collection = $accessor->getValue($value, $targetField);
                            if ($collection->contains($resource)) {
                                throw new ConflictHttpException("Resource cannot be linked twice");
                            } else {
                                $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'link', $resource, $associationName, $value);
                                $collection->add($resource);
                            }
                        }
                    } else {
                        $accessor->setValue($resource, $associationName, $value);
                    }
                    $value->setUpdated(NULL);
                    $resource->setUpdated(NULL);
                    $this->entityManager->persist($value);
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
                                $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'link', $resource, $associationName, $value);
                                $collection->add($resource);
                            }
                        }
                    } else {
                        if ($collection->contains($value)) {
                            throw new ConflictHttpException("Resource cannot be linked twice");
                        } else {
                            $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'link', $resource, $associationName, $value);
                            $collection->add($value);
                        }
                    }

                    $value->setUpdated(NULL);
                    $resource->setUpdated(NULL);
                    $this->entityManager->persist($value);
                }
                //$accessor->setValue($resource, $associationName, $collection);

            }

        }
    }

    /**
     * Disconnect links from a resource
     *
     * @param array $links - The links
     * @param ResourceInterface $resource - The resource
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
                    $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'unlink', $resource, $associationName, $value);
                    $accessor->setValue($resource, $associationName, null);
                } else {
                    throw new PreconditionFailedHttpException("Resource " . $this->entityName . ":" . ($resource->getId() ? $resource->getId() : "new") . " is not linked to " . $relatedClass . ":" . $value->getId());
                }

                $value->setUpdated(NULL);
                $resource->setUpdated(NULL);
                $this->entityManager->persist($value);
            } else {
                $collection = $accessor->getValue($resource, $associationName);

                forEach ($links[$associationName] as $value) {
                    if (!($value instanceof $relatedClass)) {
                        throw new UnsupportedMediaTypeHttpException("Wrong resource type or resource not found: " . get_class($value));
                    }
                    if ($isInverse) {
                        if ($targetMetadata->isSingleValuedAssociation($targetField)) {
                            $accessor->setValue($value, $targetField, NULL);
                        } else {
                            $collection = $accessor->getValue($value, $targetField);
                            if ($collection->contains($resource)) {
                                $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'unlink', $resource, $associationName, $value);
                                $collection->removeElement($resource);
                            } else {
                                throw new PreconditionFailedHttpException("Resource " . $this->entityName . ":" . ($resource->getId() ? $resource->getId() : "new") . " is not linked to " . $relatedClass . ":" . $value->getId());
                            }
                        }
                    } else {
                        if ($collection->contains($value)) {
                            $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'unlink', $resource, $associationName, $value);
                            $collection->removeElement($value);
                        } else {
                            throw new PreconditionFailedHttpException("Resource " . $this->entityName . ":" . ($resource->getId() ? $resource->getId() : "new") . " is not linked to " . $relatedClass . ":" . $value->getId());
                        }
                    }
                    $value->setUpdated(NULL);
                    $resource->setUpdated(NULL);
                    $this->entityManager->persist($value);
                }
            }

        }
    }

    public function patchResource($entityName, $resource, array $actions)
    {
        $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'patch', $resource);

        /** @var ClassMetadata $metadata */
        $metadata = $this->getClassMetadata($entityName);

        $accessor = PropertyAccess::createPropertyAccessor();

        $remove_links = array();
        $add_links = array();

        $data = array();


        foreach ($actions as $action) {
            $path_parts = explode('/', $action['path']);
            array_shift($path_parts);
            if ($path_parts[0] === '_links') {
                if (!$metadata->hasAssociation($path_parts[1])) {
                    throw new BadRequestHttpException('Resource has no relation named ' . $path_parts[1]);
                }

                $isSingleValued = $metadata->isSingleValuedAssociation($path_parts[1]);

                $values = $action['value'];
                if (!is_array($values)) {
                    $values = array('href' => $values);
                }

                // Check if it's a not numeric array
                if ((bool)count(array_filter(array_keys($values), 'is_string'))) {
                    $values = array($values);
                }

                if ($isSingleValued ? ($action['op'] === 'replace' || $action['op'] === 'add') : $action['op'] === 'add') {
                    foreach ($values as $value) {
                        $add_links[$path_parts[1]][] = $value['href'];
                    }
                } else if ($action['op'] === 'remove') {
                    foreach ($values as $value) {
                        $remove_links[$path_parts[1]][] = $value['href'];
                    }
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
                $this->dispatchActionEvent(HateoasActionEvent::BEFORE, $entityName, 'patchProperty', $resource, $path_parts[0], $data[$path_parts[0]]);
            }
        }

        $resolver = $this->get('uebb.hateoas.link_resolver');
        if (count($remove_links)) {
            $remove_links = $resolver->resolveResourceLinks($remove_links);
            $this->removeLinks($entityName, $resource, $remove_links);
        }
        if (count($add_links)) {
            $add_links = $resolver->resolveResourceLinks($add_links);
            $this->addLinks($entityName, $resource, $add_links);
        }

        $form = $this->getForm($resource);
        $serializer = $this->container->get('jms_serializer');
        $currentData = json_decode($serializer->serialize($resource, 'json'));


        /**
         * Fetch current field data
         */
        foreach ($metadata->getFieldNames() as $property) {
            if ($form->has($property) && !array_key_exists($property, $data)) {
                try {
                    $data[$property] = $accessor->getValue($currentData, $property);
                } catch (NoSuchPropertyException $e) {
                    $data[$property] = $accessor->getValue($resource, $property);
                } catch (NoSuchPropertyException $e) {
                }

            }
        }

        $form->submit($data);

        /** @var RecursiveValidator $validator */
        $validator = $this->get('validator');
        $validationErrors = $validator->validate($resource);

        if ($validationErrors->count() === 0) {
            $resource->setUpdated(NULL);
            $this->entityManager->persist($resource);
            $this->entityManager->flush();

            return $this->view(NULL, 204);
        } else {
            return $this->validationErrorView($validationErrors);
        }
    }


}