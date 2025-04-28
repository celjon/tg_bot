<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

class UserService
{
    /** @var UserRepository */
    private $userRepo;
    /** @var EntityManagerInterface */
    private $em;

    /**
     * UserService constructor.
     * @param UserRepository $userRepo
     * @param EntityManagerInterface $em
     */
    public function __construct(UserRepository $userRepo, EntityManagerInterface $em)
    {
        $this->userRepo = $userRepo;
        $this->em = $em;
    }

    /**
     * @param int|null $tgId
     * @param string|null $firstName
     * @param string|null $lastName
     * @param string|null $username
     * @param string|null $languageCode
     * @return User
     * @throws NonUniqueResultException
     */
    public function getOrAddUser(
        ?int $tgId,
        ?string $firstName,
        ?string $lastName,
        ?string $username,
        ?string $languageCode
    ): User {
        $user = $this->userRepo->findOneByTgIdOrUsername($tgId, $username);
        if (!$user) {
            $user = (new User())
                ->setTgId($tgId)
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setUsername($username)
                ->setLanguageCode($languageCode)
                ->setRegisteredAt(new DateTimeImmutable())
                ->setCurrentChatIndex(1)
                ->setCurrentChatListPage(1)
            ;
            $this->em->persist($user);
        } elseif ($username) {
            $user->setUsername($username);
        }
        if (!$user->getTgId() && $tgId) {
            $user->setTgId($tgId)->setFirstName($firstName)->setLastName($lastName)->setLanguageCode($languageCode);
        }
        return $user;
    }

    public function getAllUsers(int $batchSize = 100): \Generator
    {
        $offset = 0;

        do {
            $users = $this->userRepo->findBy([], ['id' => 'DESC'], $batchSize, $offset);

            $offset += $batchSize;

            foreach ($users as $user) {
                yield $user;
            }
        } while (count($users) === $batchSize);
    }
}