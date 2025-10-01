<?php
declare(strict_types=1);

namespace App\Entity;

class User
{
    private ?string $id = null;
    private string $email = '';
    private ?string $password = '';
    private string $fullName = '';
    private ?string $sex = null;
    private ?int $age = null;
    private ?string $career = null;
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;

    private ?bool $isVerified = false;
    private ?\DateTimeImmutable $emailVerifiedAt = null;


    public function __construct(
        ?string $id = null,
        ?string $email = null,
        ?string $password = null,
        ?string $fullName = null,
        ?string $sex = null,
        ?int $age = null,
        ?string $career = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?bool $isVerified = false,
        ?\DateTimeImmutable $emailVerifiedAt = null
    ) {
        $this->id = $id;
        $this->email = $email ?? '';
        $this->password = $password;
        $this->fullName = $fullName ?? '';
        $this->sex = $sex;
        $this->age = $age;
        $this->career = $career;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->isVerified = $isVerified;
        $this->emailVerifiedAt = $emailVerifiedAt;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     *
     * @return self
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }
    /**
     * @return mixed
     */
    public function getFullName()
    {
        return $this->fullName;
    }

    /**
     * @param mixed $fullName
     *
     * @return self
     */
    public function setFullName($fullName)
    {
        $this->fullName = $fullName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSex()
    {
        return $this->sex;
    }

    /**
     * @param mixed $sex
     *
     * @return self
     */
    public function setSex($sex)
    {
        $this->sex = $sex;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAge()
    {
        return $this->age;
    }

    /**
     * @param mixed $age
     *
     * @return self
     */
    public function setAge($age)
    {
        $this->age = $age;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCareer()
    {
        return $this->career;
    }

    /**
     * @param mixed $career
     *
     * @return self
     */
    public function setCareer($career)
    {
        $this->career = $career;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $createdAt
     *
     * @return self
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param mixed $updatedAt
     *
     * @return self
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getEmailVerifiedAt()
    {
        return $this->emailVerifiedAt;
    }
    public function setEmailVerifiedAt($emailVerifiedAt)
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }
    public function getIsVerified(): bool
    {
        return $this->isVerified;
    }
    public function setIsVerified(bool $isVerified)
    {
        $this->isVerified = $isVerified;
        return $this;
    }
}
