<?php

namespace App\Service\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class RepositoryFactory
{
    /** @var EntityManagerInterface */
    private $em;

    /**
     * RepositoryFactory constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param string $entityClassName
     * @return EntityRepository
     */
    public function getRepository(string $entityClassName): EntityRepository
    {
        return $this->em->getRepository($entityClassName);
    }
}
