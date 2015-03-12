<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 31.01.15
 * Time: 18:21
 */

namespace uebb\HateoasBundle\Entity;


use Doctrine\ORM\Mapping as ORM;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Security\Core\Tests\Encoder\PasswordEncoder;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use uebb\HateoasBundle\Annotation as UebbHateoas;


/**
 * Class User
 * @ORM\MappedSuperclass
 * @Serializer\ExclusionPolicy("all")
 */
class User extends Resource implements UserInterface
{

    /**
     * @var String
     * @ORM\Column(type="string")
     * @UebbHateoas\QueryAble
     * @UebbHateoas\FormField
     * @Serializer\Expose
     */
    protected $username;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $password;

    /**
     * @UebbHateoas\FormField
     * @var string
     */
    protected $plainPassword;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $salt;

    /**
     * @var PasswordEncoder
     */
    protected $encoder;


    /**
     * @var array
     * @ORM\Column(type="simple_array")
     * @Assert\Choice(
     *      choices = {"ROLE_ADMIN", "ROLE_USER"},
     *      multipleMessage = "user.validation.roles.invalid_choice",
     *      multiple = true,
     *      minMessage = "user.validation.roles.min",
     *      min = 1
     * )
     * @Assert\NotNull(message = "user.validation.roles.null")
     * @UebbHateoas\QueryAble
     * @UebbHateoas\FormField
     * @Serializer\Expose
     */
    protected $roles;

    /**
     * Returns the roles granted to the user.
     *
     * <code>
     * public function getRoles()
     * {
     *     return array('ROLE_USER');
     * }
     * </code>
     *
     * Alternatively, the roles might be stored on a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return Role[] The user roles
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param PasswordEncoder $encoder
     */
    public function setEncoder($encoder)
    {
        $this->encoder = $encoder;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @param $plainPassword
     */
    public function setPlainPassword($plainPassword)
    {

        if (trim($plainPassword) !== '') {
            if ($this->encoder) {
                $this->salt = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
                $this->password = $this->encoder->encodePassword($plainPassword, $this->salt);
            }

            $this->plainPassword = $plainPassword;
        }

    }

    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the encoded password. On authentication, a plain-text
     * password will be salted, encoded, and then compared to this value.
     *
     * @return string The password
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $salt
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;
    }


    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string|null The salt
     */
    public function getSalt()
    {
        return $this->salt;
    }


    /**
     * @param $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials()
    {
        $this->plainPassword = '';
    }

    /**
     * @param array $roles
     */
    public function setRoles($roles)
    {
        $this->roles = $roles;
    }

    /**
     * @return string
     */
    public function getPlainPassword()
    {
        return $this->plainPassword;
    }


}