HATEOAS Symfony Bundle
======================

This bundle simplifies the process of creating HATEOAS Style APIs with Symfony 2 and doctrine.

It is a work in progress and not ready production yet.

Quick Start:
------------

The bundle needs some global configuration for the JMSSerializer and FOSRestBundle. The easiest way to set up a new project is to use the [uebb/hateoas-distribution](https://github.com/uebb/hateoas-distribution):

```
$ composer create-project -s dev uebb/hateoas-distribution my_api_project
```

If you don't want to use it take a look at the app/config/config.yml of the distribution. Add the bundle to your project:

```
$ composer require uebb/hateoas-bundle
```

Start by defining your doctrine models:

Person.php
```php
<?php
namespace TestBundle\Entity;

use uebb\HateoasBundle\Entity\Resource;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Hateoas\Configuration\Annotation as Hateoas;
use uebb\HateoasBundle\Annotation as UebbHateoas;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
* @ORM\Entity
* @Hateoas\RelationProvider("uebb.hateoas.relation_provider:addRelations")
* @Serializer\ExclusionPolicy("all")
*/
class Person extends Resource {
  /**
   * @var String
   * @ORM\Column(type="string")
   * @UebbHateoas\QueryAble
   * @Serializer\Expose
   */
  protected $name;
  
    /**
   * @var ArrayCollection<Address>
   * @ORM\OneToMany(targetEntity="Address", mappedBy="user")
   *
   * @UebbHateoas\QueryAble
   */ 
  protected $addresses;
  
  public functions setName() ...
  public functions getName() ...
  public functions setAddress() ...
  public functions getAddress() ...
  public functions addAddress() ...
  public functions removeAddress() ...
}
```

Address.php
```php
<?php

namespace TestBundle\Entity;

use uebb\HateoasBundle\Entity\Resource;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Hateoas\Configuration\Annotation as Hateoas;
use uebb\HateoasBundle\Annotation as UebbHateoas;
use Symfony\Component\Validator\Constraints as Assert;

/**
* @ORM\Entity
* @Hateoas\RelationProvider("uebb.hateoas.relation_provider:addRelations")
* @Serializer\ExclusionPolicy("all")
*/
class Adress extends Resource {

  /**
   * @var Person
   * @ORM\ManyToMany(targetEntity="Person", mappedBy="addresses")
   *
   * @UebbHateoas\QueryAble
   */
  protected $user;
  
  /**
   * @var String
   * @ORM\Column(type="string")
   * @UebbHateoas\QueryAble
   * @Serializer\Expose
   */
  protected $addressText;
  
  public function getUser() ...
  public function setUser() ...
  public function getAddressText() ..
  public function setAddressText() ..
}
```

Generate form types for each model:
```
$ php app/console generate:doctrine:form TestBundle:Person
$ php app/console generate:doctrine:form TestBundle:Address
```

Set up a a controller for each resource:

PersonController.php
```php
<?php

namespace TestBundle\Controller;

use uebb\HateoasBundle\Controller\HateoasController;

class PersonController extends HateoasController
{
    protected $entityName = 'AppBundle:Person';
}
````

PersonController.php
```php
<?php

namespace TestBundle\Controller;

use uebb\HateoasBundle\Controller\HateoasController;

class AddressController extends HateoasController
{
    protected $entityName = 'AppBundle:Address';
}
````


Add the routes:

app/config/routes.yml
```yaml
person:
    type: hateoas
    resource: TestBundle\Controller\PersonController
    
address:
    type: hateoas
    resource: TestBundle\Controller\AddressController    

root:
    path:      /
    defaults:  { _controller: HateoasBundle:Root:getRoot }
```

Now you have a working api with the following routes:

| Method  | Type        | Url                  | Description                                 |
|---------|-------------|----------------------|---------------------------------------------|
| GET     | Resource    | /                    | Api Root with links to resource collections |
| GET     | Collection  | /users               | Get all users                               |
| POST    | Resource    | /users               | Post a new user resource                    |
| GET     | Resource    | /users/{id}          | Get a single user                           |
| PATCH   | Patch       | /user/{id}           | Update a user with a patch                  |
| GET     | Collection  | /user/{id}/addresses | Get all addresses of a user                 |
| PATCH   | Patch       | /user/{id}/addresses | Update the users address collection         |
| GET     | Collection  | /addresses           | Get all users                               |
| POST    | Resource    | /addresses           | Post a new address resource                 |
| GET     | Resource    | /addresses/{id}      | Get a single address                        |
| PATCH   | Patch       | /addresses/{id}      | Update an address with a patch              |

Queries and Sorting
-------------------

All collection resources are automatically paginated and can be filtered and ordered via GET parameters:

where: name="Steve" OR address.addressText="Park Av. 6"

order: name DESC, address.addressText ASC

page: 1



TODO:
-----

- Refactor QueryBuilder. Make where parsing more robust (better regexes)
- Implement aggregate queries: COUNT, AVG, MIN, MAX
- Implement configuration
  - QueryAble(maxDepth=-...)
- Add automatic documentation from Doc Comments
- HTML Views
- Write guide
