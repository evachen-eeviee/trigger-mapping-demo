<?php

namespace App\Entity;

use App\Triggers\UpdateGladiatorStatus;
use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[UniqueEntity('name', message: 'Un gladiateur porte déjà ce nom prestigieux !')]
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
    public ?int $id = null;

    #[ORM\Column(length: 255)]
    public ?string $name = null;

    #[ORM\Column]
    public int $healthPoints = 100;


    #[ORM\Column(options: ["default" => 0])]
    public int $actionCount = 0;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Range(min: 5, max: 90, notInRangeMessage: 'Il faut au moins {{ min }}% en PV.')]
    public int $statHpPercent = 34; // Par défaut pour faire 100% à trois

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Range(min: 5, max: 90, notInRangeMessage: 'Il faut au moins {{ min }}% en Attaque.')]
    public int $statAtkPercent = 33;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Range(notInRangeMessage: 'Il faut au moins {{ min }}% en Défense.', min: 5, max: 90)]
    public int $statDefPercent = 33;

    #[ORM\Column(length: 50)]
    public string $status = 'alive';

    public function __construct(string $name, int $hpPercent = 34, int $atkPercent = 33, int $defPercent = 33)
    {
        $this->name = $name;
        $this->statHpPercent = $hpPercent;
        $this->statAtkPercent = $atkPercent;
        $this->statDefPercent = $defPercent;
        $this->healthPoints = $hpPercent;
    }

    #[Assert\IsTrue(message: "Le total de tes pourcentages (PV + ATK + DEF) ne peut pas dépasser 100% !")]
    public function isTotalPercentageValid(): bool
    {
        return ($this->statHpPercent + $this->statAtkPercent + $this->statDefPercent) <= 100;
    }

    public function setStatHpPercent(int $statHpPercent): static {
        $this->statHpPercent = $statHpPercent;
        $this->healthPoints = $statHpPercent;
        return $this;
    }

}
