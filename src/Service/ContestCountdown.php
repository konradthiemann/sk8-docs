<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Days left until the skate contest that the whole platform is preparing for.
 */
final class ContestCountdown
{
    private readonly \DateTimeImmutable $contestDate;

    public function __construct(
        #[Autowire('%app.contest_date%')]
        string $contestDate,
    ) {
        $this->contestDate = new \DateTimeImmutable($contestDate . ' 00:00:00');
    }

    public function getContestDate(): \DateTimeImmutable
    {
        return $this->contestDate;
    }

    public function daysLeft(?\DateTimeImmutable $today = null): int
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $days = (int) $today->diff($this->contestDate)->format('%r%a');

        return max(0, $days);
    }
}
