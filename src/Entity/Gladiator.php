<?php


namespace App\Entity;

use App\Triggers\UpdateGladiatorStatus;
use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;

#[ORM\Entity]
#[Trigger(
    name: 'trg_gladiator_status_update',
    function: 'fn_update_gladiator_status',
    on: ['UPDATE'],
    when: 'BEFORE',
    scope: 'ROW',
    className: UpdateGladiatorStatus::class
)]
class Gladiator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private int $healthPoints = 100;

    #[ORM\Column(length: 50)]
    private string $status = 'alive';

    public function __construct(string $name, int $healthPoints = 100)
    {
        $this->name = $name;
        $this->healthPoints = $healthPoints;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getHealthPoints(): int
    {
        return $this->healthPoints;
    }

    public function setHealthPoints(int $healthPoints): static
    {
        $this->healthPoints = $healthPoints;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }
}
