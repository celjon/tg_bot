<?php

namespace App\Service;

use App\Entity\Plan;
use App\Repository\PlanRepository;
use App\Service\DTO\Bothub\PlanDTO;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

class PlanService
{
    /** @var PlanRepository */
    private $planRepo;
    /** @var EntityManagerInterface */
    private $em;

    /**
     * PlanService constructor.
     * @param PlanRepository $planRepo
     * @param EntityManagerInterface $em
     */
    public function __construct(PlanRepository $planRepo, EntityManagerInterface $em)
    {
        $this->planRepo = $planRepo;
        $this->em = $em;
    }

    /**
     * @param PlanDTO[] $plans
     * @return Plan[]
     * @throws Exception
     */
    public function updatePlans(array $plans): array
    {
        $this->removeAllPlans();
        $result = [];
        foreach ($plans as $plan) {
            $result[] = $this->addPlan($plan->id, $plan->type, $plan->price, $plan->currency, $plan->tokens);
        }
        usort($result, function (Plan $a, Plan $b) {
            if ($a->getTokens() > $b->getTokens()) {
                return 1;
            } elseif ($a->getTokens() < $b->getTokens()) {
                return -1;
            } else {
                return 0;
            }
        });
        return $result;
    }

    /**
     * @return Plan[]
     */
    public function getAllEnabledPlans(): array
    {
        return $this->planRepo->findByCurrencies(Plan::ENABLED_CURRENCIES);
    }

    /**
     * @param string $type
     * @return Plan[]
     */
    public function getPlansByType(string $type): array
    {
        return $this->planRepo->findByType($type);
    }

    /**
     * @param string $bothubId
     * @param string $type
     * @param float $price
     * @param string $currency
     * @param int $tokens
     * @return Plan
     */
    private function addPlan(string $bothubId, string $type, float $price, string $currency, int $tokens): Plan
    {
        $plan = (new Plan())
            ->setBothubId($bothubId)
            ->setType($type)
            ->setPrice($price)
            ->setCurrency($currency)
            ->setTokens($tokens)
        ;
        $this->em->persist($plan);
        return $plan;
    }

    /**
     * @throws Exception
     */
    private function removeAllPlans(): void
    {
        $this->planRepo->deleteAll();
    }
}