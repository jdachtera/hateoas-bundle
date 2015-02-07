HATEOAS Symfony Bundle
======================

This bundle simplifies the process of creating HATEOAS Style APIs with Symfony 2 and doctrine.

It is a work in progress and not ready production yes.

Quick Start:
------------

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
php app/console generate:doctrine:form TestBundle:Person
php app/console generate:doctrine:form TestBundle:Address
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

Now you have a working api.





TODO:
-----

- Refactor QueryBuilder. Make where parsing more robust (better regexes)
- Implement aggregate queries: COUNT, AVG, MIN, MAX
- Implement configuration
  - QueryAble(maxDepth=-...)
- Add automatic documentation from Doc Comments
- HTML Views
- Write guide
