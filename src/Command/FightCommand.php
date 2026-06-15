<?php

namespace App\Command;

use App\Entity\Gladiator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:fight', description: 'Simule un combat dans l\'arène')]
class FightCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    /**
     * @throws ORMException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $gladiator = $this->em->getRepository(Gladiator::class)->findOneBy([]);

        if (!$gladiator) {
            $io->writeln("✨ Un nouveau champion entre dans l'arène : Maximus !");
            $gladiator = new Gladiator('Maximus', 100);
            $this->em->persist($gladiator);
            $this->em->flush();
        } elseif ($gladiator->getHealthPoints() <= 0) {
            $io->writeln("💖 Maximus est ressuscité pour un nouveau combat !");
            $gladiator->setHealthPoints(100);
            $gladiator->setStatus('alive');
            $this->em->flush();
        }

        $damage = rand(1, 20);
        $io->writeln(sprintf("⚔️ Maximus (HP actuel: %d) subit une attaque de %d dégâts !", $gladiator->getHealthPoints(), $damage));

        $gladiator->setHealthPoints($gladiator->getHealthPoints() - $damage);
        $this->em->flush();
        
        $this->em->refresh($gladiator);

        $io->info(sprintf("Résultat en BDD -> HP réels: %d | Statut forcé par Trigger SQL: %s",
            $gladiator->getHealthPoints(),
            $gladiator->getStatus()
        ));

        return Command::SUCCESS;
    }
}
